<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/core.php';

security_headers();

$path = rtrim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'local';

if ($path === '/favicon.ico') {
    $file = dirname(__DIR__) . '/public/assets/favicon.png';
    if (is_file($file)) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}

if (preg_match('#^/assets/([A-Za-z0-9._-]+)$#', $path, $asset)) {
    $file = dirname(__DIR__) . '/public/assets/' . $asset[1];
    if (is_file($file)) {
        $types = ['css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'ico' => 'image/x-icon'];
        header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        readfile($file);
        exit;
    }
}

db();
boot_session();

function current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $user = one('SELECT * FROM users WHERE id=? AND '.sql_true('active'), [$_SESSION['uid']]);
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('/login');
    return $u;
}

function require_master(): array
{
    $u = require_login();
    if ($u['role'] !== 'MASTER') redirect('/app');
    return $u;
}

function require_tenant(): array
{
    $u = require_login();
    if ($u['role'] !== 'TENANT_ADMIN' || !$u['tenant_id']) redirect('/master');
    $t = one('SELECT * FROM tenants WHERE id=?', [$u['tenant_id']]);
    if (!$t || $t['status'] === 'CANCELLED') {
        $_SESSION = [];
        redirect('/login');
    }
    return [$u, $t];
}

function collect_hours(): string
{
    $hours = [];
    for ($d = 0; $d <= 6; $d++) {
        $bstart = post('bstart_'.$d) ?? post('start_break_'.$d);
        $hours[$d] = [
            'closed' => isset($_POST['closed_'.$d]),
            'start' => post('start_'.$d, '08:00'),
            'end' => post('end_'.$d, '18:00'),
            'breaks' => ($bstart && post('bend_'.$d)) ? [['start'=>$bstart,'end'=>post('bend_'.$d)]] : [],
        ];
    }
    return json_encode($hours);
}

/* -------- webhook público -------- */
if (preg_match('#^/api/webhooks/([a-f0-9]+)$#', $path, $m)) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Max-Age: 600');
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Use POST']);
        exit;
    }
    if (!rate_ok('wh:'.$m[1].':'.$ip, 40, 300)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Limite de requisições excedido.']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'JSON inválido.']);
        exit;
    }
    [$code, $out] = ingest_webhook($m[1], $body, $ip);
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/' ) redirect('/login');

/* -------- auth -------- */
if ($path === '/login' && $method === 'GET') {
    if (current_user()) {
        $u = current_user();
        redirect($u['role']==='MASTER' ? '/master' : '/app/agenda');
    }
    view('login', ['error' => null]);
    exit;
}
if ($path === '/login' && $method === 'POST') {
    csrf_check();
    if (!rate_ok('login:'.$ip, 8, 900)) {
        view('login', ['error'=>'Muitas tentativas. Aguarde alguns minutos.']);
        exit;
    }
    $login = strtolower(post('login', ''));
    $user = one('SELECT * FROM users WHERE lower(username)=? AND '.sql_true('active'), [$login]);
    if (!$user) {
        $user = one('SELECT * FROM users WHERE lower(email)=? AND '.sql_true('active'), [$login]);
    }
    if (!$user || !password_matches(post('password', ''), $user['password_hash'] ?? null)) {
        view('login', ['error'=>'Credenciais inválidas.']);
        exit;
    }
    if ($user['tenant_id']) {
        $t = one('SELECT status FROM tenants WHERE id=?', [$user['tenant_id']]);
        if (!$t || in_array($t['status'], ['CANCELLED','SUSPENDED'], true)) {
            view('login', ['error'=>'Esta conta está indisponível.']);
            exit;
        }
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = $user['id'];
    q('UPDATE users SET last_login_at=? WHERE id=?', [now(), $user['id']]);
    if ($user['role'] === 'MASTER') {
        redirect('/master');
    }
    if (!empty($user['must_change_password'])) {
        flash('Defina uma senha permanente antes de usar o painel.');
        redirect('/app/configuracoes?tab=conta');
    }
    redirect('/app/agenda');
}
if ($path === '/logout' && $method === 'POST') {
    csrf_check();
    $_SESSION = [];
    session_destroy();
    redirect('/login');
}

/* -------- MASTER POST -------- */
if (str_starts_with($path, '/master')) {
    $user = require_master();
    if ($method === 'POST') {
        csrf_check();
        if ($path === '/master/clientes/criar') {
            $email = strtolower((string)post('email', ''));
            $username = strtolower((string)post('username', ''));
            $password = (string)post('password', '');
            $confirm = (string)post('password_confirm', '');
            if (!post('name') || !post('business_name') || !post('display_name') || !post('segment') || !$email || !post('phone') || !post('city') || !post('state') || !$username) {
                flash('Preencha todos os campos obrigatórios. Só WhatsApp e cor principal são opcionais.');
                redirect('/master/clientes/novo');
            }
            [$docOk, $document, $docError] = parse_br_document(post('document_kind'), post('document'), true);
            if (!$docOk) {
                flash($docError);
                redirect('/master/clientes/novo');
            }
            if ($password === '' || $confirm === '') {
                flash('Informe e confirme a senha temporária.');
                redirect('/master/clientes/novo');
            }
            if ($password !== $confirm) {
                flash('A confirmação da senha não confere.');
                redirect('/master/clientes/novo');
            }
            if ($password !== '' && !password_is_strong($password)) {
                flash('A senha precisa ter no mínimo 10 caracteres, com letras e números.');
                redirect('/master/clientes/novo');
            }
            if (login_taken($email, $username ?: $email)) {
                flash('E-mail ou usuário já está em uso. Não use o login do Admin Master.');
                redirect('/master/clientes/novo');
            }
            try {
                $created = create_tenant_panel([
                    'name'=>post('name'),'business_name'=>post('business_name'),'segment'=>post('segment','outros'),
                    'document'=>$document,'email'=>$email,'phone'=>post('phone'),'whatsapp'=>post('whatsapp'),
                    'city'=>post('city'),'state'=>strtoupper((string)post('state')),'username'=>$username,'password'=>$password,
                    'plan'=>'starter','status'=>post('status','ACTIVE'),'primary_color'=>post('primary_color','#2563eb'),
                    'display_name'=>post('display_name'),
                ], $user['id']);
            } catch (Throwable $e) {
                flash('Não foi possível criar o acesso: '.$e->getMessage());
                redirect('/master/clientes/novo');
            }
            $_SESSION['issued_access'] = [
                'tenant_id' => $created['tenant_id'],
                'username' => $created['username'],
                'email' => $created['email'],
                'password' => $created['password'],
            ];
            flash('Cliente SaaS criado. Copie o acesso abaixo — a senha só aparece agora.');
            redirect('/master/clientes/acesso?id='.$created['tenant_id']);
        }
        if ($path === '/master/clientes/acesso') {
            $tenantId = post('id');
            $admin = tenant_admin((string)$tenantId);
            if (saas_access_confirmed($admin)) {
                flash('Acesso e suspensão ficam bloqueados depois que o cliente entra e troca a senha.');
                redirect('/master/clientes');
            }
            if (!$admin) {
                flash('Este cliente ainda não tem usuário de acesso.');
                redirect('/master/clientes');
            }
            $email = strtolower((string)post('email', $admin['email']));
            $username = strtolower((string)post('username', $admin['username']));
            $password = (string)post('password', '');
            if ($password === '') {
                $password = generate_temp_password();
            }
            if (!password_is_strong($password)) {
                flash('A senha precisa ter no mínimo 10 caracteres, com letras e números.');
                redirect('/master/clientes/acesso?id='.$tenantId);
            }
            if (login_taken($email, $username, $admin['id'])) {
                flash('E-mail ou usuário já está em uso.');
                redirect('/master/clientes/acesso?id='.$tenantId);
            }
            q('UPDATE users SET email=?, username=?, password_hash=?, must_change_password='.sql_lit_bool(true).', last_login_at=NULL, active='.sql_lit_bool(true).' WHERE id=?', [
                $email, $username, password_hash($password, PASSWORD_DEFAULT), $admin['id'],
            ]);
            q('UPDATE tenants SET email=?, updated_at=? WHERE id=?', [$email, now(), $tenantId]);
            audit($tenantId, $user['id'], 'tenant.access_reset', 'user', $admin['id']);
            $_SESSION['issued_access'] = [
                'tenant_id' => $tenantId,
                'username' => $username,
                'email' => $email,
                'password' => $password,
            ];
            flash('Acesso redefinido. Copie a senha temporária agora.');
            redirect('/master/clientes/acesso?id='.$tenantId);
        }
        if ($path === '/master/clientes/status') {
            $st = post('status');
            if (!in_array($st, ['ACTIVE','SUSPENDED','OVERDUE','CANCELLED'], true)) {
                redirect('/master/clientes');
            }
            if (saas_access_confirmed(tenant_admin((string)post('id')))) {
                flash('Acesso e suspensão ficam bloqueados depois que o cliente entra e troca a senha.');
                redirect('/master/clientes');
            }
            q('UPDATE tenants SET status=?, updated_at=? WHERE id=?', [$st, now(), post('id')]);
            redirect('/master/clientes');
        }
        if ($path === '/master/clientes/webhook-access') {
            $tenantId = post('id');
            $allow = post('action') === 'approve';
            if ($allow) {
                q('UPDATE tenants SET webhook_access='.sql_lit_bool(true).', webhook_approved_at=?, webhook_approved_by=?, updated_at=? WHERE id=?', [now(), $user['id'], now(), $tenantId]);
            } else {
                q('UPDATE tenants SET webhook_access='.sql_lit_bool(false).', webhook_requested_at=NULL, webhook_approved_at=NULL, webhook_approved_by=NULL, updated_at=? WHERE id=?', [now(), $tenantId]);
            }
            notify($tenantId, $allow ? 'Integração aprovada' : 'Acesso a Webhooks removido', $allow ? 'O Admin Master liberou o menu Webhooks.' : 'Solicite uma nova autorização para acessar Webhooks.');
            audit($tenantId, $user['id'], $allow ? 'webhook.access_approved' : 'webhook.access_revoked', 'tenant', $tenantId);
            flash($allow ? 'Acesso a Webhooks aprovado.' : 'Acesso a Webhooks removido.');
            redirect('/master/clientes');
        }
        if ($path === '/master/configuracoes/google') {
            $clientId = trim((string)post('google_client_id', ''));
            if ($clientId !== '' && !str_contains($clientId, '.apps.googleusercontent.com')) {
                flash('O Client ID deve terminar com .apps.googleusercontent.com. Não use nome de pessoa nem e-mail.');
                redirect('/master/configuracoes/tecnico');
            }
            $settings = platform_settings();
            $settings['google_client_id'] = $clientId;
            $secret = post('google_client_secret');
            if ($secret) $settings['google_client_secret'] = $secret;
            save_platform_settings($settings);
            flash('Credenciais técnicas do Google salvas. O login dos profissionais fica no painel de cada cliente.');
            redirect('/master/configuracoes/tecnico');
        }
        if ($path === '/master/planos/salvar') {
            q('UPDATE plans SET name=?, description=?, active=? WHERE id=?', [post('name'), post('description'), db_bool(isset($_POST['active'])), post('id')]);
            flash('Plano atualizado.');
            redirect('/master/planos');
        }
    }
    if ($path === '/master') {
        layout_start('master', compact('user','path'));
        view('master/home', [
            'total'=>one('SELECT COUNT(*) c FROM tenants')['c'],
            'active'=>one("SELECT COUNT(*) c FROM tenants WHERE status='ACTIVE'")['c'],
            'suspended'=>one("SELECT COUNT(*) c FROM tenants WHERE status='SUSPENDED'")['c'],
            'appts'=>one('SELECT COUNT(*) c FROM appointments')['c'],
            'reqs'=>one('SELECT COUNT(*) c FROM requests')['c'],
            'today'=>one("SELECT COUNT(*) c FROM appointments WHERE starts_at>=?", [date('Y-m-d').' 00:00:00'])['c'],
            'lastTenants'=>all('SELECT * FROM tenants ORDER BY created_at DESC LIMIT 6'),
            'audits'=>all('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8'),
            'errors'=>all("SELECT * FROM webhook_logs WHERE status='error' ORDER BY created_at DESC LIMIT 6"),
        ]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes') {
        layout_start('master', compact('user','path'));
        view('master/clientes', ['tenants'=>all("SELECT t.*, u.username login_username, u.email login_email, u.id login_user_id, u.must_change_password, u.last_login_at
            FROM tenants t
            LEFT JOIN users u ON u.tenant_id=t.id AND u.role='TENANT_ADMIN'
            ORDER BY t.created_at DESC")]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes/acesso') {
        $tenant = one('SELECT * FROM tenants WHERE id=?', [$_GET['id'] ?? '']);
        if (!$tenant) redirect('/master/clientes');
        $admin = tenant_admin($tenant['id']);
        if (saas_access_confirmed($admin) && $method === 'GET') {
            flash('Este cliente já entrou e trocou a senha. Acesso e suspensão estão inativos.');
            redirect('/master/clientes');
        }
        $issued = $_SESSION['issued_access'] ?? null;
        if (!$issued || ($issued['tenant_id'] ?? '') !== $tenant['id']) {
            $issued = null;
        } else {
            unset($_SESSION['issued_access']);
        }
        layout_start('master', compact('user','path'));
        view('master/acesso', ['tenant'=>$tenant,'admin'=>$admin,'issued'=>$issued]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes/novo') {
        layout_start('master', compact('user','path'));
        view('master/novo');
        layout_end('master');
        exit;
    }
    if ($path === '/master/segmentos') {
        layout_start('master', compact('user','path'));
        view('master/segmentos', ['items'=>all('SELECT * FROM segments ORDER BY category')]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/planos') {
        $counts = [];
        foreach (all('SELECT plan, COUNT(*) c FROM tenants GROUP BY plan') as $row) {
            $counts[$row['plan']] = (int)$row['c'];
        }
        layout_start('master', compact('user','path'));
        view('master/planos', ['plans'=>all('SELECT * FROM plans ORDER BY slug'), 'counts'=>$counts]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/integracoes') { layout_start('master', compact('user','path')); view('master/integracoes'); layout_end('master'); exit; }
    if ($path === '/master/logs') { layout_start('master', compact('user','path')); view('master/logs', ['logs'=>all('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 80')]); layout_end('master'); exit; }
    if ($path === '/master/configuracoes') { layout_start('master', compact('user','path')); view('master/config'); layout_end('master'); exit; }
    if ($path === '/master/configuracoes/tecnico') { layout_start('master', compact('user','path')); view('master/tecnico'); layout_end('master'); exit; }
    http_response_code(404); echo 'Não encontrado'; exit;
}

/* -------- APP -------- */
if (str_starts_with($path, '/app')) {
    [$user, $tenant] = require_tenant();
    $allowedWhileMustChange = ['/app/configuracoes', '/app/configuracoes/conta', '/app/google/connect', '/app/google/callback'];
    if (!empty($user['must_change_password']) && !in_array($path, $allowedWhileMustChange, true)) {
        flash('Defina uma senha permanente antes de usar o painel.');
        redirect('/app/configuracoes?tab=conta');
    }

    if ($path === '/app/google/connect' && $method === 'GET') {
        if (!google_oauth_ready()) {
            flash('A autenticação Google ainda não foi configurada pelo Admin Master.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        redirect(google_auth_url($tenant['id']));
    }
    if ($path === '/app/google/callback' && $method === 'GET') {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';
        if (!$code || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
            flash('Falha na autenticação Google. Tente novamente.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_tenant']);
        $token = google_exchange_code($code);
        if (empty($token['json']['access_token'])) {
            flash('O Google não autorizou o acesso. Verifique as credenciais da plataforma.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        $done = google_complete_login($tenant['id'], $token['json']);
        flash($done['message']);
        redirect('/app/configuracoes?tab=integracoes');
    }

    if ($method === 'POST') {
        csrf_check();
        $tid = $tenant['id'];
        if ($path === '/app/notificacoes/ler') {
            q('UPDATE notifications SET read_flag='.sql_lit_bool(true).' WHERE tenant_id=?', [$tid]);
            redirect('/app');
        }
        if ($path === '/app/webhooks/solicitar') {
            if (empty($tenant['webhook_access']) && empty($tenant['webhook_requested_at'])) {
                q('UPDATE tenants SET webhook_requested_at=?, updated_at=? WHERE id=?', [now(), now(), $tid]);
                audit($tid, $user['id'], 'webhook.access_requested', 'tenant', $tid);
                flash('Solicitação enviada ao Admin Master.');
            }
            redirect('/app/webhooks');
        }
        if (str_starts_with($path, '/app/webhooks/') && empty($tenant['webhook_access'])) {
            flash('A integração ainda não foi autorizada pelo Admin Master.');
            redirect('/app/webhooks');
        }
        if ($path === '/app/agenda/salvar') {
            $back = app_return_url(post('return_to'), '/app/agenda');
            $retryNew = '/app/agenda?new=1';
            if (post('lock_client_id')) $retryNew .= '&client_id='.urlencode((string)post('lock_client_id'));
            if (post('from') === 'clientes') $retryNew .= '&from=clientes';
            $cid = null;
            $rid = post('request_id');
            $req = $rid ? one('SELECT * FROM requests WHERE id=? AND tenant_id=?', [$rid, $tid]) : null;
            $lockId = post('lock_client_id');
            if ($lockId) {
                $locked = one('SELECT id FROM clients WHERE id=? AND tenant_id=?', [$lockId, $tid]);
                if (!$locked) { flash('Cliente não encontrado.'); redirect($retryNew); }
                $cid = $locked['id'];
            } elseif (post('id')) {
                $existing = one('SELECT id FROM clients WHERE id=? AND tenant_id=?', [post('client_id'), $tid]);
                $cid = $existing['id'] ?? null;
            } else {
                $mode = post('client_mode');
                if ($mode === 'new') {
                    $name = trim((string)post('new_name', ''));
                    $phone = trim((string)post('new_phone', ''));
                    $email = trim((string)post('new_email', ''));
                    $whats = trim((string)post('new_whatsapp', ''));
                    if ($name === '' || $phone === '') {
                        flash('Para cliente novo, informe nome e telefone.');
                        redirect($retryNew);
                    }
                    $cid = find_or_create_client($tid, $name, $phone, $email !== '' ? $email : null, 'Manual', $whats !== '' ? $whats : $phone)['id'];
                } elseif ($mode === 'existing') {
                    $picked = one('SELECT id FROM clients WHERE id=? AND tenant_id=?', [post('client_id'), $tid]);
                    if (!$picked) {
                        flash('Selecione um cliente cadastrado.');
                        redirect($retryNew);
                    }
                    $cid = $picked['id'];
                } elseif ($rid && $req) {
                    $cid = find_or_create_client($tid, $req['name'], $req['phone'], $req['email'], $req['source'])['id'];
                } else {
                    flash('Escolha cliente novo ou cliente cadastrado.');
                    redirect($retryNew);
                }
            }
            if (!$cid) { flash('Informe o cliente do agendamento.'); redirect($retryNew); }
            $id = post('id');
            if ($id) {
                $svc = post('service_id') ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [post('service_id'), $tid]) : null;
                $start = post('date').' '.post('start').':00';
                $end = date('Y-m-d H:i:s', strtotime($start) + service_span_minutes($svc)*60);
                if (has_conflict($tid, $start, $end, $id)) { flash('Este horário já está ocupado.'); redirect($back); }
                $prev = one('SELECT * FROM appointments WHERE id=? AND tenant_id=?', [$id, $tid]);
                q('UPDATE appointments SET client_id=?, service_id=?, starts_at=?, ends_at=?, status=?, notes=? WHERE id=? AND tenant_id=?',
                    [$cid, $svc['id']??null, $start, $end, post('status','SCHEDULED'), post('notes'), $id, $tid]);
                if ($prev && $prev['status'] !== post('status') && post('status')==='CONFIRMED') emit_outbound($tid, 'appointment.confirmed', ['id'=>$id]);
                if (post('status')==='CANCELLED') { notify($tid, 'Agendamento cancelado', 'Um horário foi cancelado.'); emit_outbound($tid, 'appointment.cancelled', ['id'=>$id]); }
                push_google_sheets($tid, 'appointment', 'upsert', $id);
                flash('Agendamento atualizado.');
            } else {
                $res = create_appointment($tenant, [
                    'client_id'=>$cid,'service_id'=>post('service_id'),'date'=>post('date'),'start'=>post('start'),
                    'status'=>post('status','SCHEDULED'),'notes'=>post('notes'),'request_id'=>$rid,'user_id'=>$user['id'],
                    'source'=>$req['source'] ?? 'Manual',
                    'metadata'=>$req && !empty($req['metadata']) ? (json_decode($req['metadata'], true) ?: null) : null,
                ]);
                flash($res['ok'] ? 'Agendamento criado.' : $res['message']);
                redirect($res['ok'] ? (str_contains($back, '/clientes') ? $back : '/app/agendamentos') : $retryNew);
            }
            redirect($back);
        }
        if ($path === '/app/agenda/bloquear') {
            $date = post('date');
            $all = isset($_POST['all_day']);
            $start = $date.' '.($all?'00:00:00':post('start','12:00').':00');
            $end = $date.' '.($all?'23:59:00':post('end','13:00').':00');
            if (one("SELECT id FROM appointments WHERE tenant_id=? AND status!='CANCELLED' AND starts_at<? AND ends_at>?", [$tid,$end,$start])) {
                flash('Já existe agendamento neste horário.'); redirect('/app/agenda');
            }
            q('INSERT INTO calendar_blocks(id,tenant_id,starts_at,ends_at,reason,all_day,created_at) VALUES(?,?,?,?,?,?,?)',
                [uid(),$tid,$start,$end,post('reason','Bloqueio'), db_bool($all), now()]);
            flash('Horário bloqueado.');
            redirect('/app/agenda');
        }
        if ($path === '/app/clientes/salvar') {
            $id = post('id');
            if (!post('name') || !post('phone') || !post('email')) {
                flash('Nome, telefone e e-mail são obrigatórios. WhatsApp é opcional.');
                redirect($id ? '/app/clientes/ver?id='.$id : '/app/clientes/novo');
            }
            [$docOk, $document, $docErr] = parse_br_document(post('document_kind'), post('cpf'), true);
            if (!$docOk) {
                flash($docErr);
                redirect($id ? '/app/clientes/ver?id='.$id : '/app/clientes/novo');
            }
            $data = [post('name'), post('phone'), post('whatsapp'), strtolower(post('email')), $document, empty_to_null(post('birth_date')), post('source','Outro'), post('notes'), post('status','ACTIVE')];
            if ($id) {
                q('UPDATE clients SET name=?,phone=?,whatsapp=?,email=?,cpf=?,birth_date=?,source=?,notes=?,status=? WHERE id=? AND tenant_id=?', [...$data, $id, $tid]);
                audit($tid, $user['id'], 'client.updated', 'client', $id);
            } else {
                $id = uid();
                q('INSERT INTO clients(id,tenant_id,name,phone,whatsapp,email,cpf,birth_date,source,notes,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$id,$tid,...$data, now()]);
                audit($tid, $user['id'], 'client.created', 'client', $id);
                emit_outbound($tid, 'client.created', ['id'=>$id,'name'=>post('name')]);
            }
            foreach (all('SELECT * FROM custom_fields WHERE tenant_id=?', [$tid]) as $f) {
                $val = post('cf_'.$f['key']);
                $ex = one('SELECT id FROM custom_field_values WHERE field_id=? AND client_id=?', [$f['id'], $id]);
                if ($ex) q('UPDATE custom_field_values SET value=? WHERE id=?', [$val, $ex['id']]);
                else q('INSERT INTO custom_field_values(id,tenant_id,field_id,client_id,value) VALUES(?,?,?,?,?)', [uid(),$tid,$f['id'],$id,$val]);
            }
            push_google_sheets($tid, 'client', 'upsert', $id);
            flash('Cadastro salvo.');
            redirect('/app/clientes/ver?id='.$id);
        }
        if ($path === '/app/clientes/excluir') {
            $delId = post('id');
            q('DELETE FROM clients WHERE id=? AND tenant_id=?', [$delId, $tid]);
            push_google_sheets($tid, 'client', 'delete', $delId);
            flash('Cadastro excluído.');
            redirect('/app/clientes');
        }
        if ($path === '/app/servicos/salvar') {
            $id = post('id');
            $fields = [
                post('name'), post('category'), post('description'),
                max(5, (int)post('duration_minutes','60')), max(0, (int)post('buffer_minutes','0')),
                (float)post('price','0'), (float)post('deposit','0'), post('color','#2563eb'),
                post('location_type','presencial'), post('location_note'),
                isset($_POST['bookable_online']) ? db_bool(true) : db_bool(false), isset($_POST['requires_confirmation']) ? db_bool(true) : db_bool(false),
                max(1, (int)post('capacity','1')), max(0, (int)post('min_notice_hours','0')),
                max(0, (int)post('max_advance_days','60')), post('client_instructions'), post('internal_notes'),
                post('status','ACTIVE'),
            ];
            if ($id) {
                q('UPDATE services SET name=?,category=?,description=?,duration_minutes=?,buffer_minutes=?,price=?,deposit=?,color=?,location_type=?,location_note=?,bookable_online=?,requires_confirmation=?,capacity=?,min_notice_hours=?,max_advance_days=?,client_instructions=?,internal_notes=?,status=? WHERE id=? AND tenant_id=?',
                    array_merge($fields, [$id, $tid]));
            } else {
                $id = uid();
                q('INSERT INTO services(id,tenant_id,name,category,description,duration_minutes,buffer_minutes,price,deposit,color,location_type,location_note,bookable_online,requires_confirmation,capacity,min_notice_hours,max_advance_days,client_instructions,internal_notes,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge([$id, $tid], $fields, [now()]));
            }
            push_google_sheets($tid, 'service', 'upsert', $id);
            flash('Serviço salvo.');
            redirect('/app/servicos');
        }
        if ($path === '/app/solicitacoes/converter') {
            $req = one('SELECT * FROM requests WHERE id=? AND tenant_id=?', [post('id'), $tid]);
            if (!$req) { flash('Solicitação não encontrada.'); redirect('/app/solicitacoes'); }
            if ($req['status'] === 'SCHEDULED') { flash('Esta solicitação já foi convertida.'); redirect('/app/agendamentos'); }
            if ($req['status'] === 'ARCHIVED') { flash('Reabra a solicitação antes de converter.'); redirect('/app/solicitacoes?ver='.$req['id']); }
            $res = convert_request_to_appointment($tenant, $req, $user['id']);
            flash($res['ok'] ? 'Solicitação convertida em agendamento.' : $res['message']);
            redirect($res['ok'] ? '/app/agendamentos' : '/app/solicitacoes?ver='.$req['id']);
        }
        if ($path === '/app/solicitacoes/criar') {
            q('INSERT INTO requests(id,tenant_id,name,phone,email,service_id,desired_date,desired_time,message,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                [uid(),$tid,post('name'),post('phone'),post('email'),post('service_id') ?: null, empty_to_null(post('desired_date')), empty_to_null(post('desired_time')),post('message'),'Manual','NEW',now()]);
            notify($tid, 'Nova solicitação recebida', post('name'));
            flash('Solicitação criada.');
            redirect('/app/solicitacoes');
        }
        if ($path === '/app/solicitacoes/status' || $path === '/app/kanban') {
            $st = post('status');
            $allowed = ['NEW','CONTACTED','WAITING_CLIENT','SCHEDULED','DONE','LOST','ARCHIVED'];
            if (!in_array($st, $allowed, true)) {
                flash('Status inválido.');
                redirect($path === '/app/kanban' ? '/app/kanban' : '/app/solicitacoes');
            }
            q('UPDATE requests SET status=? WHERE id=? AND tenant_id=?', [$st, post('id'), $tid]);
            if ($path === '/app/kanban') {
                redirect('/app/kanban');
            }
            if ($st === 'ARCHIVED') flash('Solicitação arquivada.');
            $back = '/app/solicitacoes';
            if (post('f')) $back .= '?f='.urlencode((string)post('f'));
            redirect($back);
        }
        if ($path === '/app/webhooks/rotacionar') {
            q('UPDATE webhooks SET token=?, secret=? WHERE tenant_id=? AND direction=?', [bin2hex(random_bytes(16)), bin2hex(random_bytes(24)), $tid, 'INBOUND']);
            flash('Novo token gerado.');
            redirect('/app/webhooks');
        }
        if ($path === '/app/webhooks/toggle') {
            $cur = one('SELECT active FROM webhooks WHERE tenant_id=? AND direction=?', [$tid,'INBOUND']);
            q('UPDATE webhooks SET active='.(empty($cur['active']) ? sql_lit_bool(true) : sql_lit_bool(false)).' WHERE tenant_id=? AND direction=?', [$tid, 'INBOUND']);
            redirect('/app/webhooks');
        }
        if ($path === '/app/webhooks/saida') {
            $ex = one('SELECT * FROM webhooks WHERE tenant_id=? AND direction=?', [$tid,'OUTBOUND']);
            $events = json_encode($_POST['events'] ?? []);
            if ($ex) q('UPDATE webhooks SET url=?, events=?, active=? WHERE id=?', [post('url'), $events, db_bool(isset($_POST['active'])), $ex['id']]);
            else q('INSERT INTO webhooks(id,tenant_id,name,direction,token,secret,url,events,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                [uid(),$tid,'Saída','OUTBOUND',bin2hex(random_bytes(8)),bin2hex(random_bytes(16)),post('url'),$events,db_bool(isset($_POST['active'])),now()]);
            flash('Webhook de saída salvo.');
            redirect('/app/webhooks');
        }
        if ($path === '/app/configuracoes/negocio') {
            if (!post('business_name') || !post('display_name') || !post('phone') || !post('email') || !post('city') || !post('state') || !post('address')) {
                flash('Preencha os dados do negócio. Só WhatsApp, Instagram e site são opcionais.');
                redirect('/app/configuracoes?tab=negocio&edit=1');
            }
            [$docOk, $document, $docErr] = parse_br_document(post('document_kind'), post('document'), true);
            if (!$docOk) {
                flash($docErr);
                redirect('/app/configuracoes?tab=negocio&edit=1');
            }
            q('UPDATE tenants SET business_name=?,display_name=?,document=?,phone=?,whatsapp=?,email=?,address=?,city=?,state=?,instagram=?,website=?,timezone=?,updated_at=? WHERE id=?', [
                post('business_name'), post('display_name'), $document, post('phone'), post('whatsapp'), post('email'),
                post('address'), post('city'), strtoupper((string)post('state')), post('instagram'), post('website'), post('timezone','America/Sao_Paulo'),
                now(), $tid,
            ]);
            flash('Dados do negócio atualizados.');
            redirect('/app/configuracoes?tab=negocio');
        }
        if ($path === '/app/configuracoes/horarios') {
            q('UPDATE tenants SET business_hours=?, updated_at=? WHERE id=?', [collect_hours(), now(), $tid]);
            flash('Horário de funcionamento atualizado.');
            redirect('/app/configuracoes?tab=agenda');
        }
        if ($path === '/app/configuracoes/aparencia') {
            q('UPDATE tenants SET primary_color=?, terminology=?, updated_at=? WHERE id=?', [
                post('primary_color','#2563eb'),
                json_encode(['client'=>post('term_client','Cliente'),'clients'=>post('term_clients','Clientes'),'appointment'=>post('term_appointment','Agendamento'),'appointments'=>post('term_appointments','Agendamentos'),'request'=>'Solicitação','requests'=>'Solicitações'], JSON_UNESCAPED_UNICODE),
                now(), $tid,
            ]);
            flash('Aparência atualizada.');
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/campo') {
            $label = post('label');
            $key = strtolower(preg_replace('/[^a-z0-9_]+/','_', $label ?? ''));
            if ($label && $key) {
                $opts = post('options') ? json_encode(array_map('trim', explode(',', post('options')))) : null;
                q('INSERT INTO custom_fields(id,tenant_id,label,key,type,options,sort_order) VALUES(?,?,?,?,?,?,0)', [uid(),$tid,$label,$key,post('type','text'),$opts]);
                flash('Campo personalizado adicionado.');
            }
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/conta') {
            $pw = post('password');
            $confirm = post('password_confirm');
            $mustChange = !empty($user['must_change_password']);
            if ($mustChange && !$pw) {
                flash('Defina uma senha permanente para continuar.');
                redirect('/app/configuracoes?tab=conta');
            }
            if ($pw) {
                if (!$mustChange && !password_matches((string)post('current_password',''), $user['password_hash'] ?? null)) {
                    flash('Informe a senha atual para alterar a senha.');
                    redirect('/app/configuracoes?tab=conta');
                }
                if ($pw !== $confirm) {
                    flash('A confirmação da nova senha não confere.');
                    redirect('/app/configuracoes?tab=conta');
                }
                if (!password_is_strong($pw)) {
                    flash('A nova senha precisa ter no mínimo 10 caracteres, com letras e números.');
                    redirect('/app/configuracoes?tab=conta');
                }
            }
            if ($pw) q('UPDATE users SET name=?, password_hash=?, must_change_password='.sql_lit_bool(false).' WHERE id=?', [post('name'), password_hash($pw, PASSWORD_DEFAULT), $user['id']]);
            else q('UPDATE users SET name=? WHERE id=?', [post('name'), $user['id']]);
            flash('Conta atualizada.');
            redirect($mustChange && $pw ? '/app/agenda' : '/app/configuracoes?tab=conta');
        }
        if ($path === '/app/configuracoes/analytics') {
            $gtm = strtoupper(post('gtm_id', ''));
            if ($gtm && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
                flash('O ID do Google Tag Manager deve seguir o formato GTM-XXXXXXX.');
                redirect('/app/configuracoes?tab=integracoes');
            }
            $config = [
                'gtm_id' => $gtm,
                'site_domain' => post('site_domain'),
            ];
            q('UPDATE tenants SET analytics_config=?, updated_at=? WHERE id=?', [
                json_encode($config, JSON_UNESCAPED_UNICODE), now(), $tid,
            ]);
            flash('Integração analítica atualizada.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        if ($path === '/app/configuracoes/sheets/sync') {
            $result = sync_google_sheets_all($tid);
            flash($result['message']);
            redirect('/app/configuracoes?tab=integracoes');
        }
        if ($path === '/app/google/disconnect') {
            $cfg = sheets_config($tenant);
            $cfg['enabled'] = 0;
            $cfg['access_token'] = '';
            $cfg['refresh_token'] = '';
            $cfg['expires_at'] = 0;
            $cfg['google_email'] = '';
            sheets_save($tid, $cfg);
            flash('Conta Google desconectada.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        if ($path === '/app/onboarding') {
            $step = (int)($_POST['step'] ?? 1);
            if ($step === 1) q('UPDATE tenants SET business_name=?, phone=?, whatsapp=?, updated_at=? WHERE id=?', [post('business_name',$tenant['business_name']), post('phone'), post('whatsapp'), now(), $tid]);
            if ($step === 2) q('UPDATE tenants SET business_hours=?, updated_at=? WHERE id=?', [collect_hours(), now(), $tid]);
            if ($step >= 4) {
                q('UPDATE tenants SET onboarding_done='.sql_lit_bool(true).', updated_at=? WHERE id=?', [now(), $tid]);
                redirect('/app');
            }
            redirect('/app/onboarding?step='.($step+1));
        }
    }

    if ($path === '/app/onboarding' || (!$tenant['onboarding_done'] && $path === '/app')) {
        if ($tenant['onboarding_done'] && $path === '/app/onboarding') redirect('/app');
        layout_start('app', compact('user','tenant','path'));
        view('app/onboarding', ['tenant'=>$tenant,'user'=>$user,'services'=>all('SELECT * FROM services WHERE tenant_id=?', [$tenant['id']])]);
        layout_end('app');
        exit;
    }

    if (!empty($_GET['delblock'])) {
        q('DELETE FROM calendar_blocks WHERE id=? AND tenant_id=?', [$_GET['delblock'], $tenant['id']]);
        flash('Bloqueio removido.');
        redirect('/app/agenda');
    }

    if ($path === '/app/metricas') {
        $period = (int)($_GET['period'] ?? 30);
        $period = in_array($period, [7, 30, 90], true) ? $period : 30;
        $from = date('Y-m-d 00:00:00', strtotime("-$period days"));
        $tid = $tenant['id'];
        $requestTotal = (int)one('SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=?', [$tid,$from])['c'];
        $scheduledRequests = (int)one("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status IN ('SCHEDULED','DONE')", [$tid,$from])['c'];
        $contactRequests = (int)one("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status IN ('CONTACTED','WAITING_CLIENT')", [$tid,$from])['c'];
        $finishedRequests = (int)one("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status='DONE'", [$tid,$from])['c'];
        $appointmentTotal = (int)one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND status!='CANCELLED'", [$tid,$from])['c'];
        $doneAppointments = (int)one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND status='DONE'", [$tid,$from])['c'];
        layout_start('app', compact('user','tenant','path'));
        view('app/metricas', [
            'tenant'=>$tenant,'period'=>$period,
            'newClients'=>(int)one('SELECT COUNT(*) c FROM clients WHERE tenant_id=? AND created_at>=?', [$tid,$from])['c'],
            'requestTotal'=>$requestTotal,'scheduledRequests'=>$scheduledRequests,
            'contactRequests'=>$contactRequests,'finishedRequests'=>$finishedRequests,
            'appointmentTotal'=>$appointmentTotal,'doneAppointments'=>$doneAppointments,
            'sources'=>all("SELECT COALESCE(NULLIF(utm_source,''),source,'Não informado') source, COUNT(*) total FROM clients WHERE tenant_id=? AND created_at>=? GROUP BY COALESCE(NULLIF(utm_source,''),source,'Não informado') ORDER BY total DESC", [$tid,$from]),
            'topServices'=>all("SELECT s.name, COUNT(a.id) total FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.status!='CANCELLED' GROUP BY s.name ORDER BY total DESC LIMIT 6", [$tid,$from]),
            'utmCampaigns'=>(int)one("SELECT COUNT(DISTINCT utm_campaign) c FROM requests WHERE tenant_id=? AND created_at>=? AND utm_campaign IS NOT NULL AND utm_campaign!=''", [$tid,$from])['c'],
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/relatorios') {
        layout_start('app', compact('user','tenant','path'));
        view('app/relatorios', compact('tenant'));
        layout_end('app');
        exit;
    }

    if (str_starts_with($path, '/app/relatorios/') && str_ends_with($path, '.pdf')) {
        require_once dirname(__DIR__) . '/app/pdf.php';
        $tid = $tenant['id'];
        $business = $tenant['display_name'] ?: $tenant['business_name'];
        if ($path === '/app/relatorios/atendimentos.pdf') {
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
            $rows = all("SELECT a.*,c.name client_name,s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<=? ORDER BY a.starts_at", [$tid,$from.' 00:00:00',$to.' 23:59:59']);
            $lines = ["Empresa: $business", "Período: ".date('d/m/Y',strtotime($from))." a ".date('d/m/Y',strtotime($to)), str_repeat('-',80)];
            foreach ($rows as $r) {
                $status = APPT_STATUS[$r['status']][0] ?? $r['status'];
                $lines[] = date('d/m/Y H:i',strtotime($r['starts_at'])).' | '.$r['client_name'].' | '.($r['service_name']?:'Sem serviço').' | '.$status;
            }
            $lines[] = str_repeat('-',80);
            $lines[] = 'Total de atendimentos: '.count($rows);
            download_pdf('Resumo de atendimentos', $lines, 'atendimentos-'.date('Y-m-d').'.pdf');
        }
        if ($path === '/app/relatorios/clientes.pdf') {
            $status = $_GET['status'] ?? 'ALL';
            $sql = "SELECT c.*,COUNT(a.id) total FROM clients c LEFT JOIN appointments a ON a.client_id=c.id AND a.status!='CANCELLED' WHERE c.tenant_id=?";
            $params = [$tid];
            if (in_array($status,['ACTIVE','INACTIVE'],true)) { $sql .= ' AND c.status=?'; $params[]=$status; }
            $sql .= ' GROUP BY c.id ORDER BY c.name';
            $rows = all($sql,$params);
            $lines = ["Empresa: $business", 'Cadastros: '.count($rows), str_repeat('-',80)];
            foreach ($rows as $r) {
                $lines[] = $r['name'].' | '.($r['phone']?:$r['email']?:'sem contato').' | Origem: '.$r['source'].' | Atend.: '.$r['total'];
            }
            download_pdf('Resumo de clientes', $lines, 'clientes-'.date('Y-m-d').'.pdf');
        }
        if ($path === '/app/relatorios/origens.pdf') {
            $period = max(1,min(365,(int)($_GET['period']??30)));
            $from = date('Y-m-d 00:00:00',strtotime("-$period days"));
            $rows = all("SELECT COALESCE(NULLIF(utm_source,''),source,'Não informado') source,COUNT(*) total FROM clients WHERE tenant_id=? AND created_at>=? GROUP BY COALESCE(NULLIF(utm_source,''),source,'Não informado') ORDER BY total DESC",[$tid,$from]);
            $lines = ["Empresa: $business","Período: últimos $period dias",str_repeat('-',80)];
            foreach($rows as $r) $lines[] = $r['source'].' | '.$r['total'].' contatos';
            download_pdf('Resumo de origens', $lines, 'origens-'.date('Y-m-d').'.pdf');
        }
    }

    if ($path === '/app/clientes/resumo.pdf') {
        require_once dirname(__DIR__) . '/app/pdf.php';
        $client = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['id'] ?? '', $tenant['id']]);
        if (!$client) { http_response_code(404); exit('Cadastro não encontrado.'); }
        $rows = all("SELECT a.*,s.name service_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.client_id=? ORDER BY a.starts_at DESC", [$tenant['id'],$client['id']]);
        $lines = [
            'Empresa: '.($tenant['display_name'] ?: $tenant['business_name']),
            'Nome: '.$client['name'],
            'Contato: '.($client['phone'] ?: $client['email'] ?: 'Não informado'),
            'Origem: '.$client['source'],
            str_repeat('-',80),
        ];
        foreach ($rows as $row) {
            $status = APPT_STATUS[$row['status']][0] ?? $row['status'];
            $lines[] = date('d/m/Y H:i',strtotime($row['starts_at'])).' | '.($row['service_name'] ?: 'Atendimento').' | '.$status;
        }
        $lines[] = str_repeat('-',80);
        $lines[] = 'Total de registros: '.count($rows);
        download_pdf('Resumo de atendimentos - '.$client['name'], $lines, 'resumo-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($client['name'])).'.pdf');
    }

    if ($path === '/app') {
        $today = date('Y-m-d');
        $week = date('Y-m-d', strtotime('+7 days'));
        layout_start('app', compact('user','tenant','path'));
        view('app/home', [
            'user'=>$user,'tenant'=>$tenant,
            'todayCount'=>one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<? AND status!='CANCELLED'", [$tenant['id'], $today.' 00:00:00', date('Y-m-d', strtotime('+1 day')).' 00:00:00'])['c'],
            'weekCount'=>one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<? AND status!='CANCELLED'", [$tenant['id'], $today.' 00:00:00', $week.' 23:59:59'])['c'],
            'newReq'=>one("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND status='NEW'", [$tenant['id']])['c'],
            'cliCount'=>one('SELECT COUNT(*) c FROM clients WHERE tenant_id=?', [$tenant['id']])['c'],
            'upcoming'=>all("SELECT a.*, c.name client_name, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.status!='CANCELLED' ORDER BY a.starts_at LIMIT 6", [$tenant['id'], now()]),
            'recentReq'=>all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id WHERE r.tenant_id=? ORDER BY r.created_at DESC LIMIT 5", [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/agenda') {
        $from = date('Y-m-d', strtotime('-31 days')).' 00:00:00';
        $to = date('Y-m-d', strtotime('+62 days')).' 23:59:59';
        $ap = all("SELECT a.*, c.name client_name, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<=?", [$tenant['id'],$from,$to]);
        $bl = all('SELECT * FROM calendar_blocks WHERE tenant_id=? AND starts_at>=? AND starts_at<=?', [$tenant['id'],$from,$to]);
        $events = [];
        foreach ($ap as $a) $events[] = ['id'=>$a['id'],'kind'=>'appointment','title'=>$a['client_name'],'subtitle'=>$a['service_name'],'status'=>$a['status'],'source'=>$a['source'],'start'=>$a['starts_at'],'end'=>$a['ends_at']];
        foreach ($bl as $b) $events[] = ['id'=>$b['id'],'kind'=>'block','title'=>$b['reason']?:'Bloqueio','start'=>$b['starts_at'],'end'=>$b['ends_at']];
        $pendingReq = all("SELECT r.*, s.name service_name, s.duration_minutes FROM requests r LEFT JOIN services s ON s.id=r.service_id WHERE r.tenant_id=? AND r.status NOT IN ('SCHEDULED','DONE','ARCHIVED','LOST') AND ".sql_not_blank('r.desired_date'), [$tenant['id']]);
        foreach ($pendingReq as $r) {
            $time = substr((string)($r['desired_time'] ?: '09:00'), 0, 5);
            $start = $r['desired_date'].' '.$time.':00';
            $end = date('Y-m-d H:i:s', strtotime($start) + max(30, (int)($r['duration_minutes'] ?? 60)) * 60);
            $events[] = ['id'=>$r['id'],'kind'=>'request','title'=>$r['name'],'subtitle'=>$r['service_name'] ?: 'Solicitação','status'=>'REQUEST','source'=>$r['source'],'start'=>$start,'end'=>$end];
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/agenda', [
            'tenant'=>$tenant,'events'=>$events,
            'clients'=>all('SELECT id,name,phone FROM clients WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'services'=>all('SELECT * FROM services WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/solicitacoes') {
        $detail = null;
        if (!empty($_GET['ver'])) {
            $detail = one("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id WHERE r.id=? AND r.tenant_id=?", [$_GET['ver'], $tenant['id']]);
            if (!$detail) flash('Solicitação não encontrada.');
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/solicitacoes', [
            'tenant'=>$tenant,
            'requests'=>all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id WHERE r.tenant_id=? ORDER BY r.created_at DESC", [$tenant['id']]),
            'services'=>all('SELECT * FROM services WHERE tenant_id=? AND status=?', [$tenant['id'],'ACTIVE']),
            'detail'=>$detail,
            'confirmConvert'=>(bool)($detail && !empty($_GET['converter'])),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/agendamentos') {
        $q = '%'.trim($_GET['q'] ?? '').'%';
        $st = $_GET['s'] ?? 'ALL';
        $src = $_GET['origem'] ?? 'ALL';
        $sql = "SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, c.email client_email, s.name service_name
                FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id
                WHERE a.tenant_id=? AND (c.name LIKE ? OR COALESCE(c.phone,'') LIKE ? OR COALESCE(c.email,'') LIKE ? OR COALESCE(s.name,'') LIKE ? OR COALESCE(a.source,'') LIKE ?)";
        $p = [$tenant['id'], $q, $q, $q, $q, $q];
        if ($st !== 'ALL') { $sql .= ' AND a.status=?'; $p[] = $st; }
        if ($src !== 'ALL') { $sql .= ' AND a.source=?'; $p[] = $src; }
        $sql .= ' ORDER BY a.starts_at DESC';
        $items = all($sql, $p);
        $sources = all("SELECT DISTINCT source FROM appointments WHERE tenant_id=? AND COALESCE(source,'')!='' ORDER BY source", [$tenant['id']]);
        $edit = !empty($_GET['edit']) ? appointment_detail($tenant['id'], (string)$_GET['edit']) : null;
        layout_start('app', compact('user','tenant','path'));
        view('app/agendamentos', [
            'items'=>$items, 'sources'=>$sources, 'statusFilter'=>$st, 'sourceFilter'=>$src, 'search'=>trim($_GET['q'] ?? ''),
            'edit'=>$edit,
            'clients'=>all('SELECT id,name,phone FROM clients WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'services'=>all('SELECT * FROM services WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/kanban') {
        layout_start('app', compact('user','tenant','path'));
        view('app/kanban', ['items'=>all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id WHERE r.tenant_id=? AND r.status NOT IN ('ARCHIVED','LOST') ORDER BY r.created_at DESC", [$tenant['id']])]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/clientes') {
        $q = '%'.trim($_GET['q'] ?? '').'%';
        $st = $_GET['s'] ?? 'ALL';
        $sql = "SELECT * FROM clients WHERE tenant_id=? AND (name LIKE ? OR COALESCE(phone,'') LIKE ? OR COALESCE(email,'') LIKE ?)";
        $p = [$tenant['id'], $q, $q, $q];
        if ($st !== 'ALL') { $sql .= ' AND status=?'; $p[] = $st; }
        $sql .= ' ORDER BY name';
        $clients = all($sql, $p);
        $rows = [];
        $now = now();
        foreach ($clients as $c) {
            $last = one("SELECT starts_at FROM appointments WHERE tenant_id=? AND client_id=? AND starts_at<? AND status!='CANCELLED' ORDER BY starts_at DESC LIMIT 1", [$tenant['id'],$c['id'],$now]);
            $next = one("SELECT starts_at FROM appointments WHERE tenant_id=? AND client_id=? AND starts_at>=? AND status!='CANCELLED' ORDER BY starts_at ASC LIMIT 1", [$tenant['id'],$c['id'],$now]);
            $c['last'] = $last ? date('d/m H:i', strtotime($last['starts_at'])) : null;
            $c['next'] = $next ? date('d/m H:i', strtotime($next['starts_at'])) : null;
            $rows[] = $c;
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/clientes', ['rows'=>$rows,'tenant'=>$tenant]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/clientes/novo') {
        layout_start('app', compact('user','tenant','path'));
        view('app/cliente', ['client'=>[], 'fields'=>all('SELECT * FROM custom_fields WHERE tenant_id=? ORDER BY sort_order', [$tenant['id']]), 'values'=>[], 'appts'=>[], 'last'=>null,'next'=>null,'totalAp'=>0,'totalReq'=>0]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/clientes/ver') {
        $c = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['id']??'', $tenant['id']]);
        if (!$c) { http_response_code(404); echo 'Não encontrado'; exit; }
        $appts = all("SELECT a.*, s.name service_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.client_id=? ORDER BY a.starts_at DESC", [$tenant['id'],$c['id']]);
        $now = now();
        $last = null; $next = null;
        foreach ($appts as $a) {
            if ($a['status']==='CANCELLED') continue;
            if ($a['starts_at'] < $now && !$last) $last = date('d/m H:i', strtotime($a['starts_at']));
            if ($a['starts_at'] >= $now) $next = date('d/m H:i', strtotime($a['starts_at']));
        }
        $vals = [];
        foreach (all('SELECT v.value, f.key FROM custom_field_values v JOIN custom_fields f ON f.id=v.field_id WHERE v.client_id=?', [$c['id']]) as $v) $vals[$v['key']]=$v['value'];
        layout_start('app', compact('user','tenant','path'));
        view('app/cliente', [
            'client'=>$c,
            'fields'=>all('SELECT * FROM custom_fields WHERE tenant_id=? ORDER BY sort_order', [$tenant['id']]),
            'values'=>$vals,'appts'=>$appts,'last'=>$last,'next'=>$next,
            'totalAp'=>count(array_filter($appts, fn($a)=>$a['status']!=='CANCELLED')),
            'totalReq'=>one('SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND (client_id=? OR phone=?)', [$tenant['id'],$c['id'],$c['phone']??''])['c'],
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/servicos') {
        layout_start('app', compact('user','tenant','path'));
        view('app/servicos', ['services'=>all('SELECT * FROM services WHERE tenant_id=? ORDER BY name', [$tenant['id']]),'tenant'=>$tenant]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/webhooks') {
        if (empty($tenant['webhook_access'])) {
            layout_start('app', compact('user','tenant','path'));
            view('app/webhook_request', ['tenant'=>$tenant]);
            layout_end('app');
            exit;
        }
        $in = one('SELECT * FROM webhooks WHERE tenant_id=? AND direction=?', [$tenant['id'],'INBOUND']);
        layout_start('app', compact('user','tenant','path'));
        view('app/webhooks', [
            'inbound'=>$in,
            'outbound'=>one('SELECT * FROM webhooks WHERE tenant_id=? AND direction=?', [$tenant['id'],'OUTBOUND']),
            'url'=>$in ? app_url().'/api/webhooks/'.$in['token'] : '',
            'logs'=>all('SELECT * FROM webhook_logs WHERE tenant_id=? ORDER BY created_at DESC LIMIT 30', [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/configuracoes') {
        $analyticsHook = !empty($tenant['webhook_access'])
            ? one("SELECT token FROM webhooks WHERE tenant_id=? AND direction='INBOUND'", [$tenant['id']])
            : null;
        layout_start('app', compact('user','tenant','path'));
        view('app/config', [
            'tenant'=>$tenant,'user'=>$user,
            'fields'=>all('SELECT * FROM custom_fields WHERE tenant_id=? ORDER BY sort_order', [$tenant['id']]),
            'analyticsEndpoint'=>$analyticsHook ? app_url().'/api/webhooks/'.$analyticsHook['token'] : null,
        ]);
        layout_end('app');
        exit;
    }
}

http_response_code(404);
echo 'Página não encontrada.';
