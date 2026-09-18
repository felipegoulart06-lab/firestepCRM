<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/finance.php';
require dirname(__DIR__) . '/app/reports.php';

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

/* -------- webhook público (domínio cadastrado do tenant; sem sessão/cookie) -------- */
if (preg_match('#^/api/webhooks/([a-f0-9]+)$#', $path, $m)) {
    $hookRow = one('SELECT * FROM webhooks WHERE token=? AND direction=?', [$m[1], 'INBOUND']);
    $hookTenant = $hookRow ? one('SELECT * FROM tenants WHERE id=?', [$hookRow['tenant_id']]) : null;
    $reqOrigin = request_webhook_origin();
    $echoOrigin = webhook_cors_origin_value($reqOrigin);
    $corsOk = (bool)($hookTenant && $echoOrigin !== '' && webhook_origin_matches($hookTenant, $reqOrigin));
    header('Cross-Origin-Resource-Policy: cross-origin');
    if ($echoOrigin !== '') {
        header('Access-Control-Allow-Origin: '.$echoOrigin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Content-Type, Accept');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Max-Age: 600');
    }
    if ($method === 'OPTIONS') {
        http_response_code($echoOrigin !== '' ? 204 : 403);
        exit;
    }
    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Use POST']);
        exit;
    }
    if (!$hookRow || !$hookTenant) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>'Webhook inválido.']);
        exit;
    }
    if (!$corsOk) {
        webhook_log_origin_denied($hookTenant, $hookRow, $reqOrigin);
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>'Este webhook só aceita o domínio cadastrado do site.']);
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
    if (!is_user_admin($u)) redirect('/app');
    return $u;
}

function require_tenant(): array
{
    $u = require_login();
    if ((!is_user_crm($u) && !is_user_agent($u)) || empty($u['tenant_id'])) redirect('/master');
    app_home_ensure_schema();
    services_ensure_schema();
    notes_ensure_schema();
    if (function_exists('appointment_commission_ensure_schema')) {
        appointment_commission_ensure_schema();
    }
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

if ($path === '/' ) redirect('/login');

/* -------- auth -------- */
if ($path === '/login' && $method === 'GET') {
    if (current_user()) {
        $u = current_user();
        redirect_app_home(null, $u);
    }
    view('login', ['error' => null]);
    exit;
}
if ($path === '/login' && $method === 'POST') {
    csrf_check();
    $login = strtolower(post('login', ''));
    if (!rate_ok('login:'.$ip, 8, 900) || ($login !== '' && !rate_ok('loginu:'.$login, 8, 900))) {
        view('login', ['error'=>'Muitas tentativas. Aguarde alguns minutos.']);
        exit;
    }
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
    if (empty($user['must_change_password'])) {
        q('UPDATE users SET last_login_at=? WHERE id=?', [now(), $user['id']]);
    }
    redirect_app_home(null, $user);
}
if ($path === '/logout' && $method === 'POST') {
    csrf_check();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'] ?? '/',
            'domain' => $p['domain'] ?? '',
            'secure' => (bool)($p['secure'] ?? false),
            'httponly' => true,
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
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
            if (!post('name') || !post('business_name') || !post('display_name') || !post('segment') || !$email || !post('phone') || !post('city') || !post('state') || !$username) {
                bounce_form('/master/clientes/novo', 'Preencha todos os campos obrigatórios. Só WhatsApp e cor principal são opcionais.');
            }
            [$docOk, $document, $docError] = parse_br_document(post('document_kind'), post('document'), true);
            if (!$docOk) {
                bounce_form('/master/clientes/novo', (string)$docError);
            }
            if (login_taken($email, $username ?: $email)) {
                bounce_form('/master/clientes/novo', 'E-mail ou usuário já está em uso. Não use o login do Admin Master.');
            }
            try {
                $created = create_tenant_panel([
                    'name'=>post('name'),'business_name'=>post('business_name'),'segment'=>post('segment','outros'),
                    'document'=>$document,'email'=>$email,'phone'=>post('phone'),'whatsapp'=>post('whatsapp'),
                    'city'=>post('city'),'state'=>strtoupper((string)post('state')),'username'=>$username,
                    'plan'=>'starter','status'=>post('status','ACTIVE'),'primary_color'=>post('primary_color','#2563eb'),
                    'display_name'=>post('display_name'),
                ], $user['id']);
            } catch (Throwable $e) {
                bounce_form('/master/clientes/novo', 'Não foi possível criar o acesso: '.$e->getMessage());
            }
            unset($_SESSION['form_old']);
            flash('Cliente criado. Baixe o dossiê e gere o token de acesso quando for entregar o login.');
            redirect('/master/clientes/ficha?id='.$created['tenant_id']);
        }
        if ($path === '/master/clientes/token') {
            $tenantId = (string)post('id', '');
            $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
            $admin = $tenant ? tenant_admin($tenant['id']) : null;
            if (!$tenant || !$admin) {
                flash('Cliente não encontrado.', 'error');
                redirect('/master/clientes');
            }
            if (!empty($tenant['access_token_viewed_at'])) {
                flash('Este token já foi revelado e não pode ser visto de novo.', 'error');
                redirect('/master/clientes/ficha?id='.$tenantId);
            }
            if (saas_access_confirmed($admin)) {
                flash('O cliente já entrou e definiu a senha permanente.', 'error');
                redirect('/master/clientes/ficha?id='.$tenantId);
            }
            if (post('confirm') !== '1') {
                flash('Confirme que deseja gerar o token. Ele só aparece uma vez.', 'error');
                redirect('/master/clientes/ficha?id='.$tenantId);
            }
            $password = generate_temp_password();
            q('UPDATE users SET password_hash=?, must_change_password='.sql_lit_bool(true).', last_login_at=NULL, active='.sql_lit_bool(true).' WHERE id=?', [
                password_hash($password, PASSWORD_DEFAULT), $admin['id'],
            ]);
            q('UPDATE tenants SET access_token_generated_at=?, updated_at=? WHERE id=?', [now(), now(), $tenantId]);
            audit($tenantId, $user['id'], 'tenant.access_token_generated', 'user', $admin['id']);
            $_SESSION['issued_access'] = [
                'tenant_id' => $tenantId,
                'username' => $admin['username'],
                'email' => $admin['email'],
                'password' => $password,
            ];
            redirect('/master/clientes/ficha?id='.$tenantId);
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
            $tenantId = (string)post('id', '');
            $row = $tenantId !== '' ? one('SELECT id, business_name, webhook_access FROM tenants WHERE id=?', [$tenantId]) : null;
            if (!$row) {
                flash('Cliente não encontrado.', 'error');
                redirect('/master/integracoes');
            }
            $allow = post('action') === 'approve';
            if ($allow) {
                q('UPDATE tenants SET webhook_access='.sql_lit_bool(true).', webhook_approved_at=?, webhook_approved_by=?, updated_at=? WHERE id=?', [now(), $user['id'], now(), $tenantId]);
            } else {
                q('UPDATE tenants SET webhook_access='.sql_lit_bool(false).', webhook_requested_at=NULL, webhook_approved_at=NULL, webhook_approved_by=NULL, updated_at=? WHERE id=?', [now(), $tenantId]);
            }
            notify($tenantId, $allow ? 'Integração aprovada' : 'Acesso a Webhooks removido', $allow ? 'O Admin Master liberou o menu Webhooks.' : 'Solicite uma nova autorização para acessar Webhooks.');
            audit($tenantId, $user['id'], $allow ? 'webhook.access_approved' : 'webhook.access_revoked', 'tenant', $tenantId);
            flash($allow ? 'Acesso a Webhooks aprovado. O cliente já pode usar Integrações no painel dele.' : 'Acesso a Webhooks removido.');
            redirect('/master/integracoes');
        }
        if ($path === '/master/configuracoes/google') {
            $settings = platform_settings();
            $currentId = trim((string)($settings['google_client_id'] ?? ''));
            $clientId = trim((string)post('google_client_id', ''));
            if ($clientId === '') {
                $clientId = $currentId;
            }
            if ($clientId !== '' && !str_contains($clientId, '.apps.googleusercontent.com')) {
                flash('O Client ID deve terminar com .apps.googleusercontent.com. Não use nome de pessoa nem e-mail.');
                redirect('/master/configuracoes/tecnico?edit=google');
            }
            if ($currentId !== '' && $clientId !== $currentId && post('confirm_id_change') !== '1') {
                flash('Marque a confirmação para trocar o Client ID. Nada foi alterado.');
                redirect('/master/configuracoes/tecnico?edit=google');
            }
            $settings['google_client_id'] = $clientId;
            $hasSecret = trim((string)($settings['google_client_secret'] ?? '')) !== '';
            $replace = post('replace_secret') === '1' || !$hasSecret;
            $secret = trim((string)post('google_client_secret', ''));
            if ($replace && $secret !== '') {
                $settings['google_client_secret'] = $secret;
            } elseif ($replace && $hasSecret && $secret === '') {
                flash('Para substituir o secret, cole o valor novo. O atual foi mantido.');
                redirect('/master/configuracoes/tecnico?edit=google');
            }
            save_platform_settings($settings);
            flash('Credenciais técnicas do Google salvas. O login dos profissionais fica no painel de cada cliente.');
            redirect('/master/configuracoes/tecnico');
        }
        if ($path === '/master/configuracoes/folha') {
            try {
                $cfg = letterhead_from_post(platform_letterhead_config());
            } catch (Throwable $e) {
                flash($e->getMessage());
                redirect('/master/configuracoes?edit=folha');
            }
            platform_letterhead_save($cfg);
            flash('Cabeçalho da plataforma salvo. Ative para aparecer nos PDFs.');
            redirect('/master/configuracoes');
        }
        if ($path === '/master/configuracoes/folha/ativar') {
            $cfg = platform_letterhead_config();
            $on = post('active') === '1';
            if ($on && !platform_letterhead_ready($cfg)) {
                flash('Salve o cabeçalho com nome, dados e logo antes de ativar.');
                redirect('/master/configuracoes?edit=folha');
            }
            $cfg['active'] = $on;
            platform_letterhead_save($cfg);
            flash($on ? 'Cabeçalho ativo nos PDFs da plataforma.' : 'Cabeçalho desativado nos PDFs.');
            redirect('/master/configuracoes');
        }
        if ($path === '/master/planos/salvar') {
            redirect('/master');
        }
        if ($path === '/master/segmentos/criar') {
            $name = trim((string)post('name', ''));
            $category = trim((string)post('category', ''));
            if ($name === '' || $category === '') {
                flash('Informe o nome e a categoria do segmento.');
                redirect('/master/segmentos');
            }
            $base = slugify($name, 'segmento');
            $slug = $base;
            $i = 1;
            while (one('SELECT id FROM segments WHERE slug=?', [$slug])) {
                $slug = $base.'-'.$i++;
            }
            q('INSERT INTO segments(id,name,slug,category,active) VALUES(?,?,?,?,'.sql_lit_bool(true).')', [uid(), $name, $slug, $category]);
            flash('Segmento "'.$name.'" criado. Já aparece em Criar cliente.');
            redirect('/master/segmentos');
        }
        if ($path === '/master/segmentos/status') {
            $id = (string)post('id', '');
            $on = post('active') === '1';
            if ($id !== '') {
                q('UPDATE segments SET active='.sql_lit_bool($on).' WHERE id=?', [$id]);
                flash($on ? 'Segmento ativado.' : 'Segmento ocultado.');
            }
            redirect('/master/segmentos');
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
            'pendingIntegrations'=>all("SELECT t.id, t.business_name, t.name, t.email, t.webhook_requested_at
                FROM tenants t
                WHERE ".sql_not_blank('t.webhook_requested_at')." AND ".sql_false('t.webhook_access')."
                ORDER BY t.webhook_requested_at DESC"),
        ]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/objetivo') {
        layout_start('master', compact('user','path'));
        view('master/objetivo');
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes') {
        layout_start('master', compact('user','path'));
        view('master/clientes', ['tenants'=>all("SELECT t.*, u.username login_username, u.email login_email, u.id login_user_id, u.must_change_password, u.last_login_at
            FROM tenants t
            LEFT JOIN users u ON ".sql_tenant_admin_join()."
            ORDER BY t.created_at DESC")]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes/ficha' || $path === '/master/clientes/acesso') {
        $tenant = one('SELECT * FROM tenants WHERE id=?', [$_GET['id'] ?? '']);
        if (!$tenant) redirect('/master/clientes');
        $admin = tenant_admin($tenant['id']);
        $issued = $_SESSION['issued_access'] ?? null;
        if (!$issued || ($issued['tenant_id'] ?? '') !== $tenant['id']) {
            $issued = null;
        } else {
            unset($_SESSION['issued_access']);
            if (empty($tenant['access_token_viewed_at'])) {
                q('UPDATE tenants SET access_token_viewed_at=?, updated_at=? WHERE id=?', [now(), now(), $tenant['id']]);
                $tenant['access_token_viewed_at'] = now();
                audit($tenant['id'], $user['id'], 'tenant.access_token_viewed', 'tenant', $tenant['id']);
            }
        }
        $segment = one('SELECT * FROM segments WHERE slug=?', [$tenant['segment'] ?? '']);
        layout_start('master', compact('user','path'));
        view('master/ficha', ['tenant'=>$tenant,'admin'=>$admin,'issued'=>$issued,'segment'=>$segment]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/clientes/dossie') {
        $tenant = one('SELECT * FROM tenants WHERE id=?', [$_GET['id'] ?? '']);
        if (!$tenant) redirect('/master/clientes');
        $admin = tenant_admin($tenant['id']);
        $segment = one('SELECT * FROM segments WHERE slug=?', [$tenant['segment'] ?? '']);
        send_tenant_dossier_pdf($tenant, $admin, $segment);
    }
    if ($path === '/master/clientes/novo') {
        layout_start('master', compact('user','path'));
        view('master/novo', ['old'=>take_old_form()]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/segmentos') {
        layout_start('master', compact('user','path'));
        view('master/segmentos', [
            'items'=>all('SELECT * FROM segments ORDER BY category, name'),
            'categories'=>array_values(array_unique(array_filter(array_map(fn($r) => $r['category'] ?? '', all('SELECT DISTINCT category FROM segments ORDER BY category'))))),
        ]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/planos') {
        redirect('/master');
    }
    if ($path === '/master/integracoes') {
        layout_start('master', compact('user','path'));
        view('master/integracoes', [
            'pending'=>all("SELECT t.*, u.username login_username, u.email login_email
                FROM tenants t
                LEFT JOIN users u ON ".sql_tenant_admin_join()."
                WHERE ".sql_not_blank('t.webhook_requested_at')." AND ".sql_false('t.webhook_access')."
                ORDER BY t.webhook_requested_at DESC"),
            'approved'=>all("SELECT t.*, u.username login_username
                FROM tenants t
                LEFT JOIN users u ON ".sql_tenant_admin_join()."
                WHERE ".sql_true('t.webhook_access')."
                ORDER BY t.updated_at DESC"),
        ]);
        layout_end('master');
        exit;
    }
    if ($path === '/master/logs') { layout_start('master', compact('user','path')); view('master/logs', ['logs'=>all('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 80')]); layout_end('master'); exit; }
    if ($path === '/master/configuracoes') { layout_start('master', compact('user','path')); view('master/config'); layout_end('master'); exit; }
    if ($path === '/master/configuracoes/tecnico') { layout_start('master', compact('user','path')); view('master/tecnico'); layout_end('master'); exit; }
    http_response_code(404); echo 'Não encontrado'; exit;
}

/* -------- APP -------- */
if (str_starts_with($path, '/app')) {
    [$user, $tenant] = require_tenant();
    $allowedWhileMustChange = ['/app/senha'];
    if (!empty($user['must_change_password']) && !in_array($path, $allowedWhileMustChange, true)) {
        redirect('/app/senha');
    }
    if (empty($tenant['onboarding_done']) && !is_user_agent($user) && str_starts_with($path, '/app') && !in_array($path, ['/app/senha', '/app/onboarding'], true)) {
        redirect('/app/onboarding');
    }
    if (is_user_agent($user) && agent_route_forbidden($path)) {
        flash('Este menu é exclusivo do administrador da empresa.', 'error');
        redirect(app_home_path($tenant, $user));
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
        $oauthTid = (string)($_SESSION['google_oauth_tenant'] ?? '');
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_tenant']);
        if ($oauthTid === '' || !hash_equals((string)$tenant['id'], $oauthTid)) {
            flash('Falha na autenticação Google. Tente novamente.');
            redirect('/app/configuracoes?tab=integracoes');
        }
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
        if ($path === '/app/senha') {
            $pw = (string)post('password', '');
            $confirm = (string)post('password_confirm', '');
            if ($pw === '' || $confirm === '') {
                flash('Informe e confirme a nova senha.', 'error');
                redirect('/app/senha');
            }
            if ($pw !== $confirm) {
                flash('A confirmação da senha não confere.', 'error');
                redirect('/app/senha');
            }
            if (!password_is_strong($pw)) {
                flash('A senha precisa ter no mínimo 10 caracteres, com letras e números. Os demais dados não foram alterados.', 'error');
                redirect('/app/senha');
            }
            q('UPDATE users SET password_hash=?, must_change_password='.sql_lit_bool(false).', last_login_at=? WHERE id=? AND tenant_id=?', [
                password_hash($pw, PASSWORD_DEFAULT), now(), $user['id'], $tid,
            ]);
            session_regenerate_id(true);
            flash('Senha definida. Bem-vindo ao painel.');
            redirect(empty($tenant['onboarding_done']) ? '/app/onboarding' : app_home_path($tenant, $user));
        }
        if ($path === '/app/notificacoes/ler') {
            q('UPDATE notifications SET read_flag='.sql_lit_bool(true).' WHERE tenant_id=?', [$tid]);
            redirect('/app');
        }
        if ($path === '/app/webhooks/solicitar') {
            if (!empty($tenant['webhook_access'])) {
                flash('As integrações deste painel já estão liberadas.');
                redirect('/app/webhooks');
            }
            if (empty($tenant['webhook_requested_at'])) {
                q('UPDATE tenants SET webhook_requested_at=?, updated_at=? WHERE id=?', [now(), now(), $tid]);
                audit($tid, $user['id'], 'webhook.access_requested', 'tenant', $tid);
                flash('Solicitação enviada ao Admin Master. O acesso só abre depois da aprovação.');
            }
            redirect('/app/webhooks');
        }
        if (str_starts_with($path, '/app/webhooks/') && empty($tenant['webhook_access'])) {
            flash('A integração ainda não foi autorizada pelo Admin Master.');
            redirect('/app/webhooks');
        }
        if ($path === '/app/agenda/salvar') {
            $back = app_return_url(post('return_to'), '/app/agenda');
            $retryNew = str_contains($back, '/agendamentos') ? '/app/agendamentos?new=1' : '/app/agenda?new=1';
            if (post('lock_client_id')) $retryNew .= (str_contains($retryNew, '?') ? '&' : '?').'client_id='.urlencode((string)post('lock_client_id'));
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
            if (!$cid) { flash('Informe o cliente do agendamento.', 'error'); redirect($retryNew); }
            $id = post('id');
            $svc = post('service_id') ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [post('service_id'), $tid]) : null;
            if (!$svc) {
                flash('Selecione o serviço. A duração cadastrada define quanto tempo o horário precisa ficar livre.', 'error');
                redirect($id ? $back : $retryNew);
            }
            $commission = ['ok' => true, 'enabled' => false];
            if (is_user_crm($user)) {
                $commission = parse_appointment_commission($tid, (float)($svc['price'] ?? 0));
                if (empty($commission['ok'])) {
                    flash((string)$commission['message'], 'error');
                    redirect($id ? $back : $retryNew);
                }
            }
            $isExt = is_user_crm($user) && (post('external_visit') === '1' || post('external_visit') === 'on');
            $visitAddrs = (array)($_POST['visit_addresses'] ?? []);
            $visitFilled = array_values(array_filter(array_map('trim', $visitAddrs), static fn($v) => $v !== ''));
            if ($isExt && !$visitFilled) {
                flash('Para atendimento externo, informe ao menos um endereço.', 'error');
                redirect($id ? $back : $retryNew);
            }
            if ($id) {
                if (post('allow_edit') !== '1') {
                    flash('Para alterar um agendamento, use Agendamentos > Editar.');
                    redirect('/app/agendamentos?ver='.urlencode((string)$id));
                }
                $start = post('date').' '.post('start').':00';
                $end = date('Y-m-d H:i:s', strtotime($start) + service_span_minutes($svc)*60);
                if (outside_hours($tenant, $start, $end)) {
                    bounce_form('/app/agendamentos?edit='.urlencode((string)$id), 'Fora do horário de funcionamento ('.business_hours_label($tenant, (string)post('date')).'). O término do serviço também precisa caber no expediente.');
                }
                $conflict = find_slot_conflict($tid, $start, $end, $id);
                if ($conflict) {
                    flash(slot_conflict_message($conflict, (int)$svc['duration_minutes']), 'error');
                    redirect($back);
                }
                $prev = one('SELECT * FROM appointments WHERE id=? AND tenant_id=?', [$id, $tid]);
                q('UPDATE appointments SET client_id=?, service_id=?, starts_at=?, ends_at=?, status=?, notes=? WHERE id=? AND tenant_id=?',
                    [$cid, $svc['id']??null, $start, $end, post('status','SCHEDULED'), post('notes'), $id, $tid]);
                if ($prev && $prev['status'] !== post('status') && post('status')==='CONFIRMED') emit_outbound($tid, 'appointment.confirmed', ['id'=>$id]);
                if (post('status')==='CANCELLED') { notify($tid, 'Agendamento cancelado', 'Um horário foi cancelado.'); emit_outbound($tid, 'appointment.cancelled', ['id'=>$id]); }
                push_google_sheets($tid, 'appointment', 'upsert', $id);
                sync_appointment_finance($tid, $id);
                save_appointment_visits($tid, $id, ['external_visit' => $isExt ? '1' : '0', 'visit_addresses' => $visitAddrs, 'visit_lats' => (array)($_POST['visit_lats'] ?? []), 'visit_lngs' => (array)($_POST['visit_lngs'] ?? [])]);
                if (is_user_crm($user)) {
                    store_appointment_commission($tid, $id, $commission);
                }
                flash('Agendamento atualizado.');
            } else {
                $res = create_appointment($tenant, [
                    'client_id'=>$cid,'service_id'=>post('service_id'),'date'=>post('date'),'start'=>post('start'),
                    'status'=>'SCHEDULED','notes'=>post('notes'),'request_id'=>$rid,'user_id'=>$user['id'],
                    'source'=>$req['source'] ?? 'Manual',
                    'metadata'=>$req && !empty($req['metadata']) ? (json_decode($req['metadata'], true) ?: null) : null,
                    'commission'=> is_user_crm($user) ? $commission : null,
                ]);
                flash($res['ok'] ? 'Agendamento criado.' : $res['message'], $res['ok'] ? 'ok' : 'error');
                if ($res['ok']) {
                    save_appointment_visits($tid, (string)$res['id'], ['external_visit' => $isExt ? '1' : '0', 'visit_addresses' => $visitAddrs, 'visit_lats' => (array)($_POST['visit_lats'] ?? []), 'visit_lngs' => (array)($_POST['visit_lngs'] ?? [])]);
                    redirect(str_contains($back, '/clientes') ? $back : (str_contains($back, '/agendamentos') ? '/app/agendamentos' : '/app/agenda'));
                }
                bounce_form($retryNew, (string)$res['message']);
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
            $back = $id ? '/app/clientes/editar?id='.$id : '/app/clientes/novo';
            $kind = strtolower(trim((string)post('document_kind', '')));
            $name = trim((string)post('name', ''));
            $phone = trim((string)post('phone', ''));
            $email = strtolower(trim((string)post('email', '')));
            if ($kind !== 'cpf' && $kind !== 'cnpj') {
                bounce_form($back, 'Informe se o cliente é CPF ou CNPJ.');
            }
            if ($name === '' || $phone === '' || $email === '') {
                bounce_form($back, $kind === 'cnpj'
                    ? 'Razão social, telefone e e-mail são obrigatórios.'
                    : 'Nome, telefone e e-mail são obrigatórios. WhatsApp é opcional.');
            }
            [$docOk, $document, $docErr] = parse_br_document($kind, post('cpf'), true);
            if (!$docOk) {
                bounce_form($back, $docErr);
            }
            $address = trim((string)post('address', ''));
            $city = trim((string)post('city', ''));
            $state = strtoupper(trim((string)post('state', '')));
            $cep = trim((string)post('cep', ''));
            $trade = $kind === 'cnpj' ? trim((string)post('trade_name', '')) : '';
            $ie = $kind === 'cnpj' ? trim((string)post('state_registration', '')) : '';
            $contact = $kind === 'cnpj' ? trim((string)post('contact_name', '')) : '';
            $birth = $kind === 'cpf' ? empty_to_null(post('birth_date')) : null;
            if ($kind === 'cnpj' && ($address === '' || $city === '' || $state === '' || $cep === '')) {
                bounce_form($back, 'Cliente CNPJ precisa de endereço completo: logradouro, cidade, UF e CEP.');
            }
            $data = [$name, $phone, post('whatsapp'), $email, $document, $birth, post('source','Outro'), post('notes'), post('status','ACTIVE'), $trade !== '' ? $trade : null, $ie !== '' ? $ie : null, $contact !== '' ? $contact : null];
            if ($id) {
                q('UPDATE clients SET name=?,phone=?,whatsapp=?,email=?,cpf=?,birth_date=?,source=?,notes=?,status=?,trade_name=?,state_registration=?,contact_name=? WHERE id=? AND tenant_id=?', [...$data, $id, $tid]);
                audit($tid, $user['id'], 'client.updated', 'client', $id);
            } else {
                $id = uid();
                q('INSERT INTO clients(id,tenant_id,name,phone,whatsapp,email,cpf,birth_date,source,notes,status,trade_name,state_registration,contact_name,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$id,$tid,...$data, now()]);
                audit($tid, $user['id'], 'client.created', 'client', $id);
                emit_outbound($tid, 'client.created', ['id'=>$id,'name'=>$name]);
            }
            foreach (all('SELECT * FROM custom_fields WHERE tenant_id=?', [$tid]) as $f) {
                $val = post('cf_'.$f['key']);
                $ex = one('SELECT id FROM custom_field_values WHERE field_id=? AND client_id=? AND tenant_id=?', [$f['id'], $id, $tid]);
                if ($ex) q('UPDATE custom_field_values SET value=? WHERE id=? AND tenant_id=?', [$val, $ex['id'], $tid]);
                else q('INSERT INTO custom_field_values(id,tenant_id,field_id,client_id,value) VALUES(?,?,?,?,?)', [uid(),$tid,$f['id'],$id,$val]);
            }
            push_google_sheets($tid, 'client', 'upsert', $id);
            locate_client_if_cnpj($tid, $id, $document, $address, $city, $state, $cep, post('lat'), post('lng'));
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
        if ($path === '/app/agentes/salvar') {
            if (!is_user_crm($user)) {
                flash('Somente o administrador da empresa gerencia agentes.', 'error');
                redirect('/app/agenda');
            }
            $id = trim((string)post('id', ''));
            $name = trim((string)post('name', ''));
            $email = strtolower(trim((string)post('email', '')));
            $username = strtolower(trim((string)post('username', '')));
            $phone = trim((string)post('phone', ''));
            $password = (string)post('password', '');
            $back = $id ? '/app/agentes?edit='.urlencode($id) : '/app/agentes?novo=1';
            if ($name === '' || $email === '' || $username === '') {
                bounce_form($back, 'Informe nome, e-mail e usuário do agente.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                bounce_form($back, 'Informe um e-mail válido.');
            }
            if (login_taken($email, $username, $id !== '' ? $id : null)) {
                bounce_form($back, 'E-mail ou usuário já está em uso.');
            }
            $existing = $id !== '' ? one("SELECT * FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$id, $tid]) : null;
            if ($id !== '' && !$existing) {
                flash('Agente não encontrado.', 'error');
                redirect('/app/agentes');
            }
            $kind = strtolower(trim((string)post('document_kind', '')));
            $document = null;
            if ($kind !== '' || trim((string)post('cpf', '')) !== '') {
                [$docOk, $document, $docErr] = parse_br_document($kind, post('cpf'), true);
                if (!$docOk) {
                    bounce_form($back, $docErr ?: 'Informe o CPF ou CNPJ do agente.');
                }
            }
            if (!$existing && $password === '') {
                bounce_form($back, 'Defina a senha de primeiro acesso do agente.');
            }
            if (!$existing && $password !== '' && !password_is_strong($password)) {
                bounce_form($back, 'A senha precisa ter pelo menos 10 caracteres, com letra e número.');
            }
            $active = isset($_POST['active']) ? db_bool(true) : db_bool(false);
            if ($existing) {
                q('UPDATE users SET name=?, email=?, username=?, phone=?, document_kind=?, cpf=?, active=? WHERE id=? AND tenant_id=? AND role=?',
                    [$name, $email, $username, $phone !== '' ? $phone : null, $kind !== '' ? $kind : null, $document, $active, $id, $tid, 'user_agent']);
                audit($tid, $user['id'], 'agent.updated', 'user', $id);
                flash('Agente atualizado.');
            } else {
                $id = uid();
                q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,phone,document_kind,cpf,must_change_password,active,created_at)
                   VALUES(?,?,?,?,?,?,?,?,?,?,'.sql_lit_bool(true).',?,?)', [
                    $id, $tid, $name, $email, $username, password_hash($password, PASSWORD_DEFAULT), 'user_agent',
                    $phone !== '' ? $phone : null, $kind !== '' ? $kind : null, $document, $active, now(),
                ]);
                audit($tid, $user['id'], 'agent.created', 'user', $id);
                flash('Agente criado. Ele entra no mesmo login do CRM, com o usuário e a senha de primeiro acesso.');
            }
            unset($_SESSION['form_old']);
            redirect('/app/agentes?ver='.urlencode((string)$id));
        }
        if ($path === '/app/agentes/excluir') {
            if (!is_user_crm($user)) {
                flash('Somente o administrador da empresa gerencia agentes.', 'error');
                redirect('/app/agenda');
            }
            $delId = (string)post('id', '');
            $agent = one("SELECT id FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$delId, $tid]);
            if (!$agent) {
                flash('Agente não encontrado.', 'error');
                redirect('/app/agentes');
            }
            q("DELETE FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$delId, $tid]);
            audit($tid, $user['id'], 'agent.deleted', 'user', $delId);
            flash('Agente removido.');
            redirect('/app/agentes');
        }
        if ($path === '/app/fornecedores/salvar') {
            coverage_ensure_schema();
            $id = trim((string)post('id', ''));
            $back = $id !== '' ? '/app/fornecedores?edit='.urlencode($id) : '/app/fornecedores?novo=1';
            [$docOk, $document, $docErr] = parse_br_document(post('document_kind'), post('cnpj'), true);
            $name = trim((string)post('name', ''));
            $address = trim((string)post('address', ''));
            $city = trim((string)post('city', ''));
            $state = strtoupper(trim((string)post('state', '')));
            $cep = trim((string)post('cep', ''));
            $product = trim((string)post('product_type', ''));
            $phone = trim((string)post('phone', ''));
            $email = strtolower(trim((string)post('email', '')));
            $contact = trim((string)post('contact_name', ''));
            $kind = strtolower(trim((string)post('document_kind', '')));
            if ($name === '' || !$docOk) {
                bounce_form($back, $name === '' ? 'Informe o nome do fornecedor.' : $docErr);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                bounce_form($back, 'Informe um e-mail válido ou deixe em branco.');
            }
            $lat = null;
            $lng = null;
            if ($kind === 'cnpj' && ($address !== '' || $city !== '' || $cep !== '')) {
                $pos = geo_posted_point(post('lat'), post('lng')) ?: locate_br_address($address, $city, $state, $cep);
                $lat = $pos['lat'];
                $lng = $pos['lng'];
            }
            $notes = post('notes');
            if ($id !== '') {
                $ex = one('SELECT id FROM suppliers WHERE id=? AND tenant_id=?', [$id, $tid]);
                if (!$ex) {
                    flash('Fornecedor não encontrado.', 'error');
                    redirect('/app/fornecedores');
                }
                q('UPDATE suppliers SET name=?,cnpj=?,document_kind=?,product_type=?,contact_name=?,phone=?,email=?,address=?,city=?,state=?,cep=?,lat=?,lng=?,notes=? WHERE id=? AND tenant_id=?', [
                    $name, $document, $kind, $product !== '' ? $product : null, $contact !== '' ? $contact : null,
                    $phone !== '' ? $phone : null, $email !== '' ? $email : null, $address ?: null, $city ?: null, $state ?: null, $cep ?: null,
                    $lat, $lng, $notes, $id, $tid,
                ]);
                flash('Fornecedor atualizado.');
            } else {
                $id = uid();
                q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,contact_name,phone,email,address,city,state,cep,lat,lng,notes,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                    $id, $tid, $name, $document, $kind, $product !== '' ? $product : null, $contact !== '' ? $contact : null,
                    $phone !== '' ? $phone : null, $email !== '' ? $email : null, $address ?: null, $city ?: null, $state ?: null, $cep ?: null,
                    $lat, $lng, $notes, now(),
                ]);
                flash('Fornecedor cadastrado.');
            }
            redirect('/app/fornecedores?ver='.urlencode((string)$id));
        }
        if ($path === '/app/fornecedores/excluir') {
            coverage_ensure_schema();
            q('DELETE FROM suppliers WHERE id=? AND tenant_id=?', [post('id'), $tid]);
            flash('Fornecedor removido.');
            redirect('/app/fornecedores');
        }
        if ($path === '/app/servicos/salvar') {
            services_ensure_schema();
            $id = post('id');
            $pricing = parse_service_pricing();
            if (empty($pricing['ok'])) {
                bounce_form($id ? '/app/servicos?edit='.urlencode((string)$id) : '/app/servicos?novo=1', (string)$pricing['message']);
            }
            $fields = [
                post('name'), post('category'), post('description'),
                max(5, (int)post('duration_minutes','60')), max(0, (int)post('buffer_minutes','0')),
                (float)$pricing['price'], (float)$pricing['deposit'], (string)$pricing['price_kind'], post('color','#2563eb'),
                post('location_type','presencial'), post('location_note'),
                isset($_POST['bookable_online']) ? db_bool(true) : db_bool(false), isset($_POST['requires_confirmation']) ? db_bool(true) : db_bool(false),
                max(1, (int)post('capacity','1')), max(0, (int)post('min_notice_hours','0')),
                max(0, (int)post('max_advance_days','60')), post('client_instructions'), post('internal_notes'),
                post('status','ACTIVE'),
            ];
            if ($id) {
                q('UPDATE services SET name=?,category=?,description=?,duration_minutes=?,buffer_minutes=?,price=?,deposit=?,price_kind=?,color=?,location_type=?,location_note=?,bookable_online=?,requires_confirmation=?,capacity=?,min_notice_hours=?,max_advance_days=?,client_instructions=?,internal_notes=?,status=? WHERE id=? AND tenant_id=?',
                    array_merge($fields, [$id, $tid]));
            } else {
                $id = uid();
                q('INSERT INTO services(id,tenant_id,name,category,description,duration_minutes,buffer_minutes,price,deposit,price_kind,color,location_type,location_note,bookable_online,requires_confirmation,capacity,min_notice_hours,max_advance_days,client_instructions,internal_notes,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge([$id, $tid], $fields, [now()]));
            }
            push_google_sheets($tid, 'service', 'upsert', $id);
            foreach (all("SELECT id FROM appointments WHERE tenant_id=? AND service_id=? AND status!='CANCELLED'", [$tid, $id]) as $ap) {
                sync_appointment_finance($tid, $ap['id']);
            }
            flash('Serviço salvo.');
            redirect('/app/servicos?ver='.urlencode((string)$id));
        }
        if ($path === '/app/servicos/excluir') {
            $delId = (string)post('id', '');
            $svc = one('SELECT id FROM services WHERE id=? AND tenant_id=?', [$delId, $tid]);
            if (!$svc) {
                flash('Serviço não encontrado.', 'error');
                redirect('/app/servicos');
            }
            q('UPDATE appointments SET service_id=NULL WHERE tenant_id=? AND service_id=?', [$tid, $delId]);
            q('UPDATE requests SET service_id=NULL WHERE tenant_id=? AND service_id=?', [$tid, $delId]);
            q('DELETE FROM services WHERE id=? AND tenant_id=?', [$delId, $tid]);
            push_google_sheets($tid, 'service', 'delete', $delId);
            flash('Serviço excluído.');
            redirect('/app/servicos');
        }
        if ($path === '/app/anotacoes/salvar') {
            notes_ensure_schema();
            $id = (string)post('id', '');
            $title = trim((string)post('title', ''));
            $body = trim((string)post('body', ''));
            $date = (string)post('note_date', date('Y-m-d'));
            if ($title === '' || $body === '') {
                bounce_form($id !== '' ? '/app/anotacoes?edit='.urlencode($id) : '/app/anotacoes?novo=1', 'Preencha o título e o texto da anotação.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                bounce_form($id !== '' ? '/app/anotacoes?edit='.urlencode($id) : '/app/anotacoes?novo=1', 'Informe a data da ocorrência.');
            }
            $title = substr($title, 0, 140);
            $body = substr($body, 0, 8000);
            $onAgenda = isset($_POST['show_on_agenda']) ? db_bool(true) : db_bool(false);
            if ($id !== '') {
                $ex = one('SELECT * FROM user_notes WHERE id=? AND tenant_id=?', [$id, $tid]);
                if (!$ex || !note_can_manage($user, $ex)) {
                    flash('Anotação não encontrada.', 'error');
                    redirect('/app/anotacoes');
                }
                q('UPDATE user_notes SET title=?, body=?, note_date=?, show_on_agenda=?, updated_at=? WHERE id=? AND tenant_id=?', [
                    $title, $body, $date, $onAgenda, now(), $id, $tid,
                ]);
            } else {
                $id = uid();
                q('INSERT INTO user_notes(id,tenant_id,user_id,title,body,note_date,show_on_agenda,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
                    $id, $tid, $user['id'], $title, $body, $date, $onAgenda, now(), now(),
                ]);
            }
            flash('Anotação salva.');
            redirect('/app/anotacoes');
        }
        if ($path === '/app/anotacoes/excluir') {
            notes_ensure_schema();
            $delId = (string)post('id', '');
            $note = one('SELECT * FROM user_notes WHERE id=? AND tenant_id=?', [$delId, $tid]);
            if (!$note || !note_can_manage($user, $note)) {
                flash('Anotação não encontrada.', 'error');
                redirect('/app/anotacoes');
            }
            q('DELETE FROM user_notes WHERE id=? AND tenant_id=?', [$delId, $tid]);
            flash('Anotação excluída.');
            redirect('/app/anotacoes');
        }
        if ($path === '/app/solicitacoes/converter') {
            $req = one('SELECT * FROM requests WHERE id=? AND tenant_id=?', [post('id'), $tid]);
            if (!$req) { flash('Solicitação não encontrada.'); redirect('/app/solicitacoes'); }
            if ($req['status'] === 'SCHEDULED') { flash('Esta solicitação já foi convertida.'); redirect('/app/agendamentos'); }
            if ($req['status'] === 'ARCHIVED') { flash('Reabra a solicitação antes de converter.'); redirect('/app/solicitacoes?ver='.$req['id']); }
            $res = convert_request_to_appointment($tenant, $req, $user['id'], (string)post('service_id', '') ?: null);
            flash($res['ok'] ? 'Solicitação convertida em agendamento.' : $res['message'], $res['ok'] ? 'ok' : 'error');
            redirect($res['ok'] ? '/app/agendamentos?ver='.urlencode((string)$res['id']) : '/app/solicitacoes?ver='.$req['id'].'&converter=1');
        }
        if ($path === '/app/solicitacoes/criar') {
            redirect('/app/solicitacoes');
        }
        if ($path === '/app/solicitacoes/status') {
            $st = post('status');
            $allowed = ['NEW','CONTACTED','WAITING_CLIENT','SCHEDULED','DONE','LOST','ARCHIVED'];
            if (!in_array($st, $allowed, true)) {
                flash('Status inválido.');
                redirect('/app/solicitacoes');
            }
            q('UPDATE requests SET status=? WHERE id=? AND tenant_id=?', [$st, post('id'), $tid]);
            if ($st === 'ARCHIVED') flash('Solicitação arquivada.');
            $back = '/app/solicitacoes';
            if (post('f')) $back .= '?f='.urlencode((string)post('f'));
            redirect($back);
        }
        if ($path === '/app/kanban') {
            $st = (string)post('status', '');
            $id = (string)post('id', '');
            if ((string)post('confirm', '') !== '1') {
                flash('Confirme com Sim para alterar o status.', 'error');
                redirect('/app/kanban');
            }
            if (!isset(APPT_STATUS[$st])) {
                flash('Status inválido.');
                redirect('/app/kanban');
            }
            $prev = one('SELECT * FROM appointments WHERE id=? AND tenant_id=?', [$id, $tid]);
            if (!$prev) {
                flash('Agendamento não encontrado.', 'error');
                redirect('/app/kanban');
            }
            if ($prev['status'] === $st) {
                redirect('/app/kanban');
            }
            q('UPDATE appointments SET status=? WHERE id=? AND tenant_id=?', [$st, $id, $tid]);
            if ($prev['status'] !== $st && $st === 'CONFIRMED') {
                emit_outbound($tid, 'appointment.confirmed', ['id'=>$id]);
            }
            if ($st === 'CANCELLED') {
                notify($tid, 'Agendamento cancelado', 'Um horário foi cancelado.');
                emit_outbound($tid, 'appointment.cancelled', ['id'=>$id]);
            }
            push_google_sheets($tid, 'appointment', 'upsert', $id);
            sync_appointment_finance($tid, $id);
            redirect('/app/kanban');
        }
        if ($path === '/app/webhooks/dominio') {
            $host = normalize_site_host((string)post('site_domain', ''));
            $valid = tenant_webhook_hosts(['analytics_config' => json_encode(['site_domain' => $host])]);
            if (!$valid) {
                flash('Informe um domínio válido (ex.: meusite.com.br).', 'error');
                redirect('/app/webhooks');
            }
            $host = $valid[0];
            $hosts = tenant_webhook_hosts($tenant);
            if (in_array($host, $hosts, true)) {
                flash('Esse domínio já está autorizado.');
                redirect('/app/webhooks');
            }
            $hosts[] = $host;
            save_tenant_webhook_hosts($tid, $tenant, $hosts);
            audit($tid, $user['id'], 'webhook.domain_saved', 'tenant', $tid);
            flash('Domínio autorizado. O site nesse endereço pode chamar o webhook.');
            redirect('/app/webhooks');
        }
        if ($path === '/app/webhooks/dominio/remover') {
            $drop = normalize_site_host((string)post('host', ''));
            $hosts = array_values(array_filter(tenant_webhook_hosts($tenant), static fn($h) => $h !== $drop));
            save_tenant_webhook_hosts($tid, $tenant, $hosts);
            audit($tid, $user['id'], 'webhook.domain_removed', 'tenant', $tid);
            flash('Domínio removido da lista autorizada.');
            redirect('/app/webhooks');
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
            $outUrl = trim((string)post('url'));
            if ($outUrl !== '' && !webhook_url_allowed($outUrl)) {
                flash('URL de webhook inválida. Use HTTPS público (sem localhost ou rede interna).');
                redirect('/app/webhooks');
            }
            if ($ex) q('UPDATE webhooks SET url=?, events=?, active=? WHERE id=? AND tenant_id=?', [$outUrl, $events, db_bool(isset($_POST['active'])), $ex['id'], $tid]);
            else q('INSERT INTO webhooks(id,tenant_id,name,direction,token,secret,url,events,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
                [uid(),$tid,'Saída','OUTBOUND',bin2hex(random_bytes(8)),bin2hex(random_bytes(16)),$outUrl,$events,db_bool(isset($_POST['active'])),now()]);
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
        if ($path === '/app/configuracoes/inicio') {
            app_home_ensure_schema();
            $choice = trim((string)post('home_path', '/app/agenda'));
            $allowed = app_home_choices(null, $tenant);
            if (!isset($allowed[$choice])) {
                flash('Escolha um menu válido para a tela inicial.', 'error');
                redirect('/app/configuracoes?tab=avancado');
            }
            q('UPDATE tenants SET home_path=?, updated_at=? WHERE id=?', [$choice, now(), $tid]);
            flash('Tela inicial salva. Toda entrada no CRM abre nesse menu.');
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
        if ($path === '/app/configuracoes/folha') {
            letterhead_ensure_schema();
            $fresh = one('SELECT * FROM tenants WHERE id=?', [$tid]);
            $current = letterhead_config($fresh ?: $tenant);
            try {
                $cfg = letterhead_from_post($current);
            } catch (Throwable $e) {
                flash($e->getMessage());
                redirect('/app/configuracoes?tab=avancado&edit=folha');
            }
            letterhead_save($tid, $cfg);
            flash('Cabeçalho de folha salvo. Agora você pode ativá-lo nos contratos.');
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/folha/ativar') {
            letterhead_ensure_schema();
            $fresh = one('SELECT * FROM tenants WHERE id=?', [$tid]);
            $cfg = letterhead_config($fresh ?: $tenant);
            $on = post('active') === '1';
            if ($on && !letterhead_complete($cfg)) {
                flash('Salve o cabeçalho completo antes de ativar.');
                redirect('/app/configuracoes?tab=avancado&edit=folha');
            }
            $cfg['active'] = $on;
            letterhead_save($tid, $cfg);
            flash($on ? 'Cabeçalho ativo: todos os contratos passam a usá-lo.' : 'Cabeçalho desativado nos contratos.');
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/assinatura') {
            letterhead_ensure_schema();
            $fresh = one('SELECT * FROM tenants WHERE id=?', [$tid]);
            $current = signature_config($fresh ?: $tenant);
            try {
                $cfg = signature_from_post($current);
            } catch (Throwable $e) {
                flash($e->getMessage());
                redirect('/app/configuracoes?tab=avancado&edit=assinatura');
            }
            signature_save($tid, $cfg);
            flash('Assinatura eletrônica salva. Agora você pode ativá-la nos contratos.');
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/assinatura/ativar') {
            letterhead_ensure_schema();
            $fresh = one('SELECT * FROM tenants WHERE id=?', [$tid]);
            $cfg = signature_config($fresh ?: $tenant);
            $on = post('active') === '1';
            if ($on && !signature_complete($cfg)) {
                flash('Envie e salve a imagem da assinatura antes de ativar.');
                redirect('/app/configuracoes?tab=avancado&edit=assinatura');
            }
            $cfg['active'] = $on;
            signature_save($tid, $cfg);
            flash($on ? 'Assinatura ativa: aparece no rodapé dos contratos.' : 'Assinatura desativada nos contratos.');
            redirect('/app/configuracoes?tab=avancado');
        }
        if ($path === '/app/configuracoes/clausulas') {
            letterhead_ensure_schema();
            try {
                clauses_save($tid, clauses_from_post());
            } catch (Throwable $e) {
                flash($e->getMessage());
                redirect('/app/configuracoes?tab=avancado&edit=clausulas');
            }
            flash('Contratos de agendamento salvos. Cada lista entra no PDF com serviços, valores e cláusulas.');
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
            if ($pw) {
                q('UPDATE users SET name=?, password_hash=?, must_change_password='.sql_lit_bool(false).' WHERE id=? AND tenant_id=?', [post('name'), password_hash($pw, PASSWORD_DEFAULT), $user['id'], $tid]);
                session_regenerate_id(true);
            } else {
                q('UPDATE users SET name=? WHERE id=? AND tenant_id=?', [post('name'), $user['id'], $tid]);
            }
            flash('Conta atualizada.');
            redirect($mustChange && $pw ? app_home_path($tenant, $user) : '/app/configuracoes?tab=conta');
        }
        if ($path === '/app/configuracoes/analytics') {
            $gtm = strtoupper(post('gtm_id', ''));
            if ($gtm && !preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
                flash('O ID do Google Tag Manager deve seguir o formato GTM-XXXXXXX.');
                redirect('/app/configuracoes?tab=integracoes');
            }
            $cfg = analytics_config($tenant);
            $cfg['gtm_id'] = $gtm;
            $cfg['site_domain'] = implode(', ', tenant_webhook_hosts(['analytics_config' => json_encode(['site_domain' => (string)post('site_domain', '')])]));
            q('UPDATE tenants SET analytics_config=?, updated_at=? WHERE id=?', [
                json_encode($cfg, JSON_UNESCAPED_UNICODE), now(), $tid,
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
        if ($path === '/app/financeiro/salvar') {
            ensure_finance_schema();
            $kind = post('kind', '');
            if (!in_array($kind, ['entry', 'receivable', 'payable'], true)) {
                flash('Tipo financeiro inválido.', 'error');
                redirect('/app/financeiro');
            }
            $desc = trim((string)post('description', ''));
            $amount = (float)str_replace(',', '.', (string)post('amount', '0'));
            if ($desc === '' || $amount <= 0) {
                bounce_form(finance_back(post('back')), 'Informe descrição e um valor maior que zero.');
            }
            $clientId = post('client_id');
            if ($clientId) {
                $ok = one('SELECT id FROM clients WHERE id=? AND tenant_id=?', [$clientId, $tid]);
                if (!$ok) {
                    $clientId = null;
                }
            } else {
                $clientId = null;
            }
            $flow = $kind === 'payable' ? 'out' : ($kind === 'receivable' ? 'in' : (post('flow', 'in') === 'out' ? 'out' : 'in'));
            $status = $kind === 'entry' ? 'paid' : 'open';
            $method = $kind === 'entry' ? '' : finance_normalize_method(post('payment_method'));
            $due = post('due_date') ?: null;
            if ($kind === 'receivable' && $method === '') {
                bounce_form(finance_back(post('back')), 'Informe a forma de pagamento.');
            }
            if ($method === 'fatura') {
                if (!$clientId) {
                    bounce_form(finance_back(post('back')), 'Fatura exige um cliente CPF ou CNPJ.');
                }
                if (!$due) {
                    $due = finance_invoice_due_date();
                }
            }
            q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tid, $kind, $flow, $status, $desc, $amount, $due,
                $status === 'paid' ? now() : null, $clientId, post('notes'), 'manual', null, $method ?: null, now(), now(),
            ]);
            flash('Registro financeiro salvo.');
            redirect(finance_back(post('back')));
        }
        if ($path === '/app/financeiro/status') {
            ensure_finance_schema();
            $res = finance_set_status($tid, (string)post('id'), (string)post('status', ''), [
                'payment_method' => post('payment_method'),
                'pay_doc' => post('pay_doc'),
                'amount_paid' => post('amount_paid'),
                'pay_installments' => post('pay_installments'),
                'pay_installment_amount' => post('pay_installment_amount'),
            ]);
            flash($res['message'], empty($res['ok']) ? 'error' : 'ok');
            redirect(finance_back(post('back')));
        }
        if ($path === '/app/financeiro/cobrar') {
            ensure_finance_schema();
            $entry = one(
                "SELECT f.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp
                 FROM finance_entries f
                 LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
                 WHERE f.id=? AND f.tenant_id=?",
                [(string)post('id'), $tid]
            );
            if (!$entry || ($entry['kind'] ?? '') !== 'receivable' || ($entry['status'] ?? '') !== 'open') {
                flash('Conta a receber não encontrada ou já baixada.', 'error');
                redirect(finance_back(post('back')));
            }
            $fresh = one('SELECT * FROM tenants WHERE id=?', [$tid]) ?: $tenant;
            $card = finance_charge_card($fresh, $entry);
            $sent = uazapi_send_charge($fresh, $card);
            flash($sent['message'], empty($sent['ok']) ? 'error' : 'ok');
            redirect(finance_back(post('back')));
        }
        if ($path === '/app/configuracoes/uazapi') {
            uazapi_ensure_schema();
            $cur = uazapi_config($tenant);
            $url = rtrim(trim((string)post('uazapi_url', '')), '/');
            $token = trim((string)post('uazapi_token', ''));
            if ($token === '') {
                $token = $cur['token'];
            }
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                flash('A URL da UAZAPI deve começar com http:// ou https://.', 'error');
                redirect('/app/configuracoes?tab=integracoes');
            }
            uazapi_save($tid, [
                'url' => $url,
                'token' => $token,
                'pix_key' => trim((string)post('uazapi_pix', '')),
                'image' => trim((string)post('uazapi_image', '')),
            ]);
            flash('Integração UAZAPI atualizada.');
            redirect('/app/configuracoes?tab=integracoes');
        }
        if ($path === '/app/onboarding') {
            $step = (int)($_POST['step'] ?? 1);
            try {
                if ($step === 1) {
                    $name = trim((string)post('business_name', $tenant['business_name']));
                    $phone = trim((string)post('phone'));
                    $wa = trim((string)post('whatsapp'));
                    if ($name === '' || $phone === '') {
                        bounce_form('/app/onboarding?step=1', 'Informe o nome comercial e o telefone para continuar.');
                    }
                    if ($wa === '') {
                        $wa = $phone;
                    }
                    q('UPDATE tenants SET business_name=?, display_name=?, phone=?, whatsapp=?, updated_at=? WHERE id=?', [$name, $name, $phone, $wa, now(), $tid]);
                }
                if ($step === 2) {
                    q('UPDATE tenants SET business_hours=?, updated_at=? WHERE id=?', [collect_hours(), now(), $tid]);
                }
                if ($step >= 4) {
                    q('UPDATE tenants SET onboarding_done='.sql_lit_bool(true).', updated_at=? WHERE id=?', [now(), $tid]);
                    flash('Painel configurado. Você já pode agendar.');
                    redirect('/app');
                }
            } catch (Throwable $e) {
                bounce_form('/app/onboarding?step='.$step, 'Não foi possível salvar este passo. Tente de novo.');
            }
            redirect('/app/onboarding?step='.($step+1));
        }
    }

    if ($path === '/app/senha') {
        if (empty($user['must_change_password'])) {
            redirect(app_home_path($tenant, $user));
        }
        layout_start('lock', compact('user','tenant','path'));
        view('app/senha', compact('user','tenant'));
        layout_end('lock');
        exit;
    }

    if ($path === '/app/onboarding' || (!$tenant['onboarding_done'] && $path === '/app')) {
        if ($tenant['onboarding_done'] && $path === '/app/onboarding') redirect('/app');
        layout_start('onboard', compact('user','tenant','path'));
        view('app/onboarding', ['tenant'=>$tenant,'user'=>$user,'old'=>take_old_form(),'services'=>all('SELECT * FROM services WHERE tenant_id=? ORDER BY name', [$tenant['id']])]);
        layout_end('onboard');
        exit;
    }

    if (!empty($_GET['delblock'])) {
        q('DELETE FROM calendar_blocks WHERE id=? AND tenant_id=?', [$_GET['delblock'], $tenant['id']]);
        flash('Bloqueio removido.');
        redirect('/app/agenda');
    }

    if ($path === '/app/geo/search') {
        header('Content-Type: application/json; charset=utf-8');
        if (!rate_ok('geo:'.($user['id'] ?? $ip), 40, 60)) {
            http_response_code(429);
            echo json_encode(['ok'=>false,'items'=>[],'error'=>'Muitas buscas.']);
            exit;
        }
        $q = trim((string)($_GET['q'] ?? ''));
        echo json_encode(['ok'=>true,'items'=>geocode_search($q)], JSON_UNESCAPED_UNICODE);
        exit;
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
            'topServices'=>all("SELECT s.name, COUNT(a.id) total FROM appointments a LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.status!='CANCELLED' GROUP BY s.name ORDER BY total DESC LIMIT 6", [$tid,$from]),
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

    if ($path === '/app/relatorios/preview') {
        report_send_preview($tenant);
    }

    if ($path === '/app/relatorios/arquivo.pdf'
        || (str_starts_with($path, '/app/relatorios/') && str_ends_with($path, '.pdf'))) {
        report_send_pdf($tenant);
    }

    if ($path === '/app/agendamentos/reserva.pdf') {
        require_once dirname(__DIR__) . '/app/pdf.php';
        $appt = appointment_detail($tenant['id'], (string)($_GET['id'] ?? ''));
        if (!$appt) { http_response_code(404); exit('Agendamento não encontrado.'); }
        send_appointment_pdf($tenant, $appt);
    }

    if ($path === '/app/clientes/resumo.pdf') {
        require_once dirname(__DIR__) . '/app/pdf.php';
        $client = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['id'] ?? '', $tenant['id']]);
        if (!$client) { http_response_code(404); exit('Cadastro não encontrado.'); }
        $rows = all("SELECT a.*,s.name service_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.client_id=? ORDER BY a.starts_at DESC", [$tenant['id'],$client['id']]);
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
        download_pdf('Resumo de atendimentos - '.$client['name'], $lines, 'resumo-'.preg_replace('/[^a-z0-9]+/i','-',strtolower($client['name'])).'.pdf', $tenant);
    }

    if ($path === '/app') {
        $home = app_home_path($tenant, $user);
        if ($home !== '/app') {
            redirect($home);
        }
        $today = date('Y-m-d');
        $week = date('Y-m-d', strtotime('+7 days'));
        layout_start('app', compact('user','tenant','path'));
        view('app/home', [
            'user'=>$user,'tenant'=>$tenant,
            'todayCount'=>one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<? AND status!='CANCELLED'", [$tenant['id'], $today.' 00:00:00', date('Y-m-d', strtotime('+1 day')).' 00:00:00'])['c'],
            'weekCount'=>one("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<? AND status!='CANCELLED'", [$tenant['id'], $today.' 00:00:00', $week.' 23:59:59'])['c'],
            'newReq'=>one("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND status='NEW'", [$tenant['id']])['c'],
            'cliCount'=>one('SELECT COUNT(*) c FROM clients WHERE tenant_id=?', [$tenant['id']])['c'],
            'upcoming'=>all("SELECT a.*, c.name client_name, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.status!='CANCELLED' ORDER BY a.starts_at LIMIT 6", [$tenant['id'], now()]),
            'recentReq'=>all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? ORDER BY r.created_at DESC LIMIT 5", [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/agenda') {
        $from = date('Y-m-d', strtotime('-31 days')).' 00:00:00';
        $to = date('Y-m-d', strtotime('+62 days')).' 23:59:59';
        $ap = all("SELECT a.*, c.name client_name, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<=?", [$tenant['id'],$from,$to]);
        $bl = all('SELECT * FROM calendar_blocks WHERE tenant_id=? AND starts_at>=? AND starts_at<=?', [$tenant['id'],$from,$to]);
        $events = [];
        foreach ($ap as $a) $events[] = ['id'=>$a['id'],'kind'=>'appointment','title'=>$a['client_name'],'subtitle'=>$a['service_name'],'status'=>$a['status'],'source'=>$a['source'],'start'=>$a['starts_at'],'end'=>$a['ends_at']];
        foreach ($bl as $b) $events[] = ['id'=>$b['id'],'kind'=>'block','title'=>$b['reason']?:'Bloqueio','start'=>$b['starts_at'],'end'=>$b['ends_at']];
        $pendingReq = all("SELECT r.*, s.name service_name, s.duration_minutes FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? AND r.status NOT IN ('SCHEDULED','DONE','ARCHIVED','LOST') AND ".sql_not_blank('r.desired_date'), [$tenant['id']]);
        foreach ($pendingReq as $r) {
            $time = substr((string)($r['desired_time'] ?: '09:00'), 0, 5);
            $start = $r['desired_date'].' '.$time.':00';
            $end = date('Y-m-d H:i:s', strtotime($start) + max(30, (int)($r['duration_minutes'] ?? 60)) * 60);
            $events[] = ['id'=>$r['id'],'kind'=>'request','title'=>$r['name'],'subtitle'=>$r['service_name'] ?: 'Solicitação','status'=>'REQUEST','source'=>$r['source'],'start'=>$start,'end'=>$end];
        }
        foreach (notes_agenda_events($tenant['id'], $from, $to) as $noteEv) {
            $events[] = $noteEv;
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/agenda', [
            'tenant'=>$tenant,'events'=>$events,
            'clients'=>all('SELECT id,name,phone FROM clients WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'services'=>all('SELECT * FROM services WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'agents'=>all("SELECT id, name FROM users WHERE tenant_id=? AND role='user_agent' AND ".sql_true('active')." ORDER BY name", [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/anotacoes') {
        notes_ensure_schema();
        $items = all(
            'SELECT n.*, u.name author_name FROM user_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.tenant_id=? ORDER BY n.note_date DESC, n.created_at DESC',
            [$tenant['id']]
        );
        $edit = null;
        $viewing = null;
        if (!empty($_GET['edit'])) {
            $edit = one('SELECT n.*, u.name author_name FROM user_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.id=? AND n.tenant_id=?', [$_GET['edit'], $tenant['id']]);
            if (!$edit || !note_can_manage($user, $edit)) {
                flash('Anotação não encontrada.', 'error');
                $edit = null;
            }
        }
        if (!$edit && !empty($_GET['ver'])) {
            $viewing = one('SELECT n.*, u.name author_name FROM user_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.id=? AND n.tenant_id=?', [$_GET['ver'], $tenant['id']]);
            if (!$viewing) {
                flash('Anotação não encontrada.', 'error');
            }
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/anotacoes', [
            'user' => $user,
            'items' => $items,
            'edit' => $edit,
            'viewing' => $viewing,
            'creating' => !$edit && !$viewing && !empty($_GET['novo']),
            'old' => take_old_form(),
        ]);
        layout_end('app');
        exit;
    }

    if ($path === '/app/solicitacoes') {
        $detail = null;
        if (!empty($_GET['ver'])) {
            $detail = one("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.id=? AND r.tenant_id=?", [$_GET['ver'], $tenant['id']]);
            if (!$detail) flash('Solicitação não encontrada.');
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/solicitacoes', [
            'tenant'=>$tenant,
            'requests'=>all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? ORDER BY r.created_at DESC", [$tenant['id']]),
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
                FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
                WHERE a.tenant_id=? AND (c.name LIKE ? OR COALESCE(c.phone,'') LIKE ? OR COALESCE(c.email,'') LIKE ? OR COALESCE(s.name,'') LIKE ? OR COALESCE(a.source,'') LIKE ?)";
        $p = [$tenant['id'], $q, $q, $q, $q, $q];
        if ($st !== 'ALL') { $sql .= ' AND a.status=?'; $p[] = $st; }
        if ($src !== 'ALL') { $sql .= ' AND a.source=?'; $p[] = $src; }
        $sql .= ' ORDER BY a.starts_at DESC';
        $items = all($sql, $p);
        $sources = all("SELECT DISTINCT source FROM appointments WHERE tenant_id=? AND COALESCE(source,'')!='' ORDER BY source", [$tenant['id']]);
        $edit = !empty($_GET['edit']) ? appointment_detail($tenant['id'], (string)$_GET['edit']) : null;
        $viewing = !$edit && !empty($_GET['ver']) ? appointment_detail($tenant['id'], (string)$_GET['ver']) : null;
        if (!empty($_GET['edit']) && !$edit) {
            flash('Agendamento não encontrado.', 'error');
        } elseif (!empty($_GET['ver']) && !$viewing) {
            flash('Agendamento não encontrado.', 'error');
        }
        $creating = !$edit && !$viewing && !empty($_GET['new']);
        $forcedClient = null;
        if ($creating && !empty($_GET['client_id'])) {
            $forcedClient = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['client_id'], $tenant['id']]);
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/agendamentos', [
            'user'=>$user,
            'tenant'=>$tenant,
            'items'=>$items, 'sources'=>$sources, 'statusFilter'=>$st, 'sourceFilter'=>$src, 'search'=>trim($_GET['q'] ?? ''),
            'edit'=>$edit,
            'viewing'=>$viewing,
            'creating'=>$creating,
            'forcedClient'=>$forcedClient,
            'clients'=>all('SELECT id,name,phone FROM clients WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'services'=>all('SELECT * FROM services WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'],'ACTIVE']),
            'agents'=>all("SELECT id, name FROM users WHERE tenant_id=? AND role='user_agent' AND ".sql_true('active')." ORDER BY name", [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/kanban') {
        layout_start('app', compact('user','tenant','path'));
        view('app/kanban', ['items'=>all(
            "SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, s.name service_name
             FROM appointments a
             JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
             LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
             WHERE a.tenant_id=?
             ORDER BY a.starts_at DESC",
            [$tenant['id']]
        )]);
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
        view('app/cliente', ['client'=>[], 'fields'=>all('SELECT * FROM custom_fields WHERE tenant_id=? ORDER BY sort_order', [$tenant['id']]), 'values'=>[], 'appts'=>[], 'last'=>null,'next'=>null,'totalAp'=>0,'totalReq'=>0,'old'=>take_old_form()]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/clientes/ver' || $path === '/app/clientes/editar') {
        $c = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['id']??'', $tenant['id']]);
        if (!$c) { http_response_code(404); echo 'Não encontrado'; exit; }
        $appts = all("SELECT a.*, s.name service_name FROM appointments a LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.client_id=? ORDER BY a.starts_at DESC", [$tenant['id'],$c['id']]);
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
            'viewOnly' => $path === '/app/clientes/ver',
            'fields'=>all('SELECT * FROM custom_fields WHERE tenant_id=? ORDER BY sort_order', [$tenant['id']]),
            'values'=>$vals,'appts'=>$appts,'last'=>$last,'next'=>$next,
            'totalAp'=>count(array_filter($appts, fn($a)=>$a['status']!=='CANCELLED')),
            'totalReq'=>one('SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND (client_id=? OR phone=?)', [$tenant['id'],$c['id'],$c['phone']??''])['c'],
            'old'=>take_old_form(),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/abrangencia') {
        coverage_ensure_schema();
        layout_start('app', compact('user','tenant','path'));
        view('app/abrangencia', [
            'pins' => coverage_pins($tenant['id']),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/fornecedores') {
        coverage_ensure_schema();
        $edit = null;
        $viewing = null;
        $editId = trim((string)($_GET['edit'] ?? $_GET['id'] ?? ''));
        $verId = trim((string)($_GET['ver'] ?? ''));
        if ($verId !== '') {
            $viewing = one('SELECT * FROM suppliers WHERE id=? AND tenant_id=?', [$verId, $tenant['id']]);
        }
        if (!$viewing && $editId !== '' && empty($_GET['ver'])) {
            $edit = one('SELECT * FROM suppliers WHERE id=? AND tenant_id=?', [$editId, $tenant['id']]);
        }
        $search = trim((string)($_GET['q'] ?? ''));
        $sql = 'SELECT * FROM suppliers WHERE tenant_id=?';
        $p = [$tenant['id']];
        if ($search !== '') {
            $sql .= ' AND (name LIKE ? OR COALESCE(cnpj,\'\') LIKE ? OR COALESCE(product_type,\'\') LIKE ? OR COALESCE(contact_name,\'\') LIKE ? OR COALESCE(city,\'\') LIKE ?)';
            $like = '%'.$search.'%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY name';
        layout_start('app', compact('user','tenant','path'));
        view('app/fornecedores', [
            'items' => all($sql, $p),
            'edit' => $edit,
            'viewing' => $viewing,
            'novo' => isset($_GET['novo']) && !$edit && !$viewing,
            'old' => take_old_form(),
            'search' => $search,
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
    if ($path === '/app/agentes') {
        if (!is_user_crm($user)) {
            flash('Somente o administrador da empresa gerencia agentes.', 'error');
            redirect(app_home_path($tenant, $user));
        }
        $edit = null;
        $viewing = null;
        $verId = trim((string)($_GET['ver'] ?? ''));
        $editId = trim((string)($_GET['edit'] ?? ''));
        if ($verId !== '') {
            $viewing = one("SELECT * FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$verId, $tenant['id']]);
        }
        if (!$viewing && $editId !== '') {
            $edit = one("SELECT * FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$editId, $tenant['id']]);
        }
        layout_start('app', compact('user','tenant','path'));
        view('app/agentes', [
            'tenant'=>$tenant,
            'user'=>$user,
            'edit'=>$edit,
            'viewing'=>$viewing,
            'novo'=>isset($_GET['novo']) && !$edit && !$viewing,
            'old'=>take_old_form(),
            'agents'=>all("SELECT * FROM users WHERE tenant_id=? AND role='user_agent' ORDER BY name", [$tenant['id']]),
        ]);
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
        $hosts = tenant_webhook_hosts($tenant);
        $ready = $hosts !== [];
        $reveal = $ready && (($_GET['show'] ?? '') === '1');
        layout_start('app', compact('user','tenant','path'));
        view('app/webhooks', [
            'inbound'=>$in,
            'outbound'=>one('SELECT * FROM webhooks WHERE tenant_id=? AND direction=?', [$tenant['id'],'OUTBOUND']),
            'url'=>($reveal && $in) ? app_url().'/api/webhooks/'.$in['token'] : '',
            'hosts'=>$hosts,
            'ready'=>$ready,
            'reveal'=>$reveal,
            'logs'=>all('SELECT * FROM webhook_logs WHERE tenant_id=? ORDER BY created_at DESC LIMIT 30', [$tenant['id']]),
        ]);
        layout_end('app');
        exit;
    }
    if ($path === '/app/configuracoes') {
        letterhead_ensure_schema();
        $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenant['id']]) ?: $tenant;
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
    if ($path === '/app/financeiro' || str_starts_with($path, '/app/financeiro/')) {
        if ($path === '/app/financeiro/relatorios') {
            redirect('/app/relatorios');
        }
        ensure_finance_schema();
        finance_backfill_appointments($tenant['id']);
        $clients = all('SELECT id,name,cpf FROM clients WHERE tenant_id=? AND status=? ORDER BY name', [$tenant['id'], 'ACTIVE']);
        $page = 'dashboard';
        if ($path === '/app/financeiro/lancamentos') $page = 'lancamentos';
        elseif ($path === '/app/financeiro/receber') $page = 'receber';
        elseif ($path === '/app/financeiro/faturado') $page = 'faturado';
        elseif ($path === '/app/financeiro/pagar') $page = 'pagar';
        elseif ($path === '/app/financeiro/relatorio.pdf') {
            if (empty($_GET['types']) && empty($_GET['type'])) {
                $_GET['types'] = ['financeiro'];
            }
            report_send_pdf($tenant);
        }
        if ($path !== '/app/financeiro' && $page === 'dashboard') {
            http_response_code(404);
            echo 'Página não encontrada.';
            exit;
        }
        layout_start('app', compact('user','tenant','path'));
        if ($page === 'dashboard') {
            view('app/financeiro_dashboard', ['tenant'=>$tenant]);
        } else {
            view('app/financeiro_lista', [
                'tenant'=>$tenant,
                'page'=>$page,
                'items'=>finance_query($tenant['id'], $page, trim($_GET['q'] ?? '')),
                'search'=>trim($_GET['q'] ?? ''),
                'clients'=>$clients,
            ]);
        }
        layout_end('app');
        exit;
    }
}

http_response_code(404);
echo 'Página não encontrada.';
