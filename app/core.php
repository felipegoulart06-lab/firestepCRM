<?php
declare(strict_types=1);

require_once __DIR__ . '/pdf.php';

function create_tenant_panel(array $in, ?string $actor = null): array
{
    $segments = require __DIR__ . '/segments.php';
    $preset = $segments[$in['segment'] ?? 'outros'] ?? $segments['outros'];
    $base = slugify((string)($in['business_name'] ?: $in['name']));
    $slug = $base;
    $i = 1;
    while (one('SELECT id FROM tenants WHERE slug=?', [$slug])) {
        $slug = $base . '-' . $i++;
    }
    $email = strtolower(trim((string)$in['email']));
    $username = strtolower(trim((string)$in['username']));
    if ($username === '' || str_contains($username, '@')) {
        $username = $slug;
        $n = 1;
        while (login_taken($email, $username)) {
            $username = $slug . $n++;
        }
    }
    if (login_taken($email, $username)) {
        throw new RuntimeException('E-mail ou usuário já cadastrado.');
    }
    $password = generate_temp_password();
    $tid = uid();
    $uid = uid();
    $tnow = now();
    if (function_exists('r2_ensure_schema')) {
        r2_ensure_schema();
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO tenants(id,name,business_name,slug,segment,document,email,phone,whatsapp,city,state,status,plan,primary_color,display_name,timezone,terminology,business_hours,webhook_access,onboarding_done,created_at,updated_at)
           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'.sql_lit_bool(false).','.sql_lit_bool(false).',?,?)', [
            $tid, $in['name'], $in['business_name'], $slug, $in['segment'] ?? 'outros',
            $in['document'] ?? null, $email, $in['phone'] ?? null, $in['whatsapp'] ?? null,
            $in['city'] ?? null, $in['state'] ?? null, $in['status'] ?? 'ACTIVE', $in['plan'] ?? 'starter',
            $in['primary_color'] ?? '#2563eb', $in['display_name'] ?? $in['business_name'],
            'America/Sao_Paulo', json_encode($preset['terms'] + ['request'=>'Solicitação','requests'=>'Solicitações'], JSON_UNESCAPED_UNICODE),
            json_encode(default_hours()), $tnow, $tnow,
        ]);
        q('UPDATE tenants SET auto_backup='.sql_lit_bool(false).' WHERE id=?', [$tid]);
        q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,phone,must_change_password,active,created_at)
           VALUES(?,?,?,?,?,?,?,?,'.sql_lit_bool(true).','.sql_lit_bool(true).',?)', [
            $uid, $tid, $in['name'], $email, $username,
            password_hash($password, PASSWORD_DEFAULT), 'user_crm', $in['phone'] ?? null, $tnow,
        ]);
        q('INSERT INTO webhooks(id,tenant_id,name,direction,token,secret,events,active,created_at) VALUES(?,?,?,?,?,?,?,'.sql_lit_bool(true).',?)', [
            uid(), $tid, 'Entrada do site', 'INBOUND', bin2hex(random_bytes(16)), bin2hex(random_bytes(24)),
            json_encode(['request','appointment']), $tnow,
        ]);
        audit($tid, $actor, 'tenant.created', 'tenant', $tid);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return ['tenant_id' => $tid, 'user_id' => $uid, 'username' => $username, 'password' => $password, 'email' => $email];
}

function find_or_create_client(string $tenant, string $name, ?string $phone, ?string $email, string $source = 'Webhook', ?string $whatsapp = null, array $attribution = []): array
{
    $digits = $phone ? preg_replace('/\D/', '', $phone) : '';
    if ($digits) {
        $c = one('SELECT * FROM clients WHERE tenant_id=? AND (phone LIKE ? OR whatsapp LIKE ?) LIMIT 1', [$tenant, "%$digits%", "%$digits%"]);
        if ($c) return $c;
    }
    if ($email) {
        $c = one('SELECT * FROM clients WHERE tenant_id=? AND lower(email)=lower(?) LIMIT 1', [$tenant, $email]);
        if ($c) return $c;
    }
    $id = uid();
    q('INSERT INTO clients(id,tenant_id,name,phone,whatsapp,email,source,utm_source,utm_medium,utm_campaign,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
        $id, $tenant, $name, $phone, $whatsapp ?: $phone, $email ? strtolower($email) : null, $source,
        $attribution['utm_source'] ?? null, $attribution['utm_medium'] ?? null, $attribution['utm_campaign'] ?? null,
        'ACTIVE', now(),
    ]);
    audit($tenant, null, 'client.created', 'client', $id);
    emit_outbound($tenant, 'client.created', ['id'=>$id,'name'=>$name]);
    return one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$id, $tenant]);
}

function emit_outbound(string $tenant, string $event, array $payload): void
{
    if (!one('SELECT id FROM tenants WHERE id=? AND '.sql_true('webhook_access'), [$tenant])) return;
    foreach (all('SELECT * FROM webhooks WHERE tenant_id=? AND direction=? AND '.sql_true('active'), [$tenant, 'OUTBOUND']) as $hook) {
        $events = json_arr($hook['events'] ?: '[]');
        if (!in_array($event, $events, true) || !$hook['url']) continue;
        if (!webhook_url_allowed((string)$hook['url'])) {
            q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,response,http_status,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tenant, $hook['id'], $event, json_encode($payload), 'URL bloqueada (SSRF)', null, 'error', 'outbound', now(),
            ]);
            continue;
        }
        $ok = false; $code = null; $resp = '';
        $ctx = stream_context_create(['http'=>[
            'method'=>'POST','header'=>"Content-Type: application/json\r\nX-Firestep-Event: $event\r\nX-Firestep-Token: {$hook['secret']}\r\n",
            'content'=>json_encode(['event'=>$event,'payload'=>$payload,'at'=>now()]),
            'timeout'=>4,'ignore_errors'=>true,
        ]]);
        $resp = (string)@file_get_contents($hook['url'], false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
            $ok = $code >= 200 && $code < 300;
        }
        q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,response,http_status,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenant, $hook['id'], $event, json_encode($payload), substr($resp, 0, 400), $code, $ok ? 'sent' : 'error', 'outbound', now(),
        ]);
    }
}

function find_slot_conflict(string $tenant, string $start, string $end, ?string $ignoreAppt = null): ?array
{
    $sql = "SELECT a.id, a.starts_at, a.ends_at, c.name client_name, s.name service_name
            FROM appointments a
            JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
            LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
            WHERE a.tenant_id=? AND a.status!=? AND a.starts_at<? AND a.ends_at>?";
    $p = [$tenant, 'CANCELLED', $end, $start];
    if ($ignoreAppt) {
        $sql .= ' AND a.id!=?';
        $p[] = $ignoreAppt;
    }
    $sql .= ' ORDER BY a.starts_at LIMIT 1';
    $row = one($sql, $p);
    if ($row) {
        $row['kind'] = 'appointment';
        return $row;
    }
    $block = one('SELECT id, starts_at, ends_at, reason FROM calendar_blocks WHERE tenant_id=? AND starts_at<? AND ends_at>? LIMIT 1', [$tenant, $end, $start]);
    if ($block) {
        $block['kind'] = 'block';
        return $block;
    }
    return null;
}

function has_conflict(string $tenant, string $start, string $end, ?string $ignoreAppt = null): bool
{
    return find_slot_conflict($tenant, $start, $end, $ignoreAppt) !== null;
}

function slot_conflict_message(?array $conflict, int $durationMinutes): string
{
    $need = $durationMinutes.' minuto'.($durationMinutes === 1 ? '' : 's');
    if (!$conflict) {
        return 'Não é possível agendar neste horário. O serviço dura '.$need.' e o período não está livre.';
    }
    $when = substr((string)$conflict['starts_at'], 11, 5).'–'.substr((string)$conflict['ends_at'], 11, 5);
    if (($conflict['kind'] ?? '') === 'block') {
        $why = trim((string)($conflict['reason'] ?? '')) !== '' ? ' ('.trim((string)$conflict['reason']).')' : '';
        return 'Não é possível agendar. O serviço dura '.$need.' e esse período está bloqueado'.$why.' das '.$when.'. Remova o bloqueio ou escolha outro horário.';
    }
    $who = (string)($conflict['client_name'] ?? 'outro cliente');
    $svc = trim((string)($conflict['service_name'] ?? ''));
    $svcBit = $svc !== '' ? ' · '.$svc : '';
    return 'Não é possível agendar. O serviço dura '.$need.' e esse período já está ocupado pelo agendamento de '.$who.$svcBit.' ('.$when.'). Exclua ou altere esse agendamento para liberar o tempo do serviço.';
}

function hm_to_minutes(?string $hm, bool $closing = false): int
{
    $hm = trim((string)$hm);
    if ($hm === '24:00') {
        return 1440;
    }
    if ($hm === '' || $hm === '00:00') {
        return $closing ? 1440 : 0;
    }
    $h = (int)substr($hm, 0, 2);
    $m = (int)substr($hm, 3, 2);
    $total = ($h * 60) + $m;
    if ($closing && $total === 0) {
        return 1440;
    }
    return $total;
}

function hours_day_closed(?array $cfg): bool
{
    if (!$cfg) {
        return true;
    }
    $c = $cfg['closed'] ?? false;
    return $c === true || $c === 1 || $c === '1' || $c === 'true';
}

function tenant_hours_map(array $tenant): array
{
    $raw = json_arr($tenant['business_hours'] ?? '', default_hours());
    if (!is_array($raw) || $raw === []) {
        $raw = default_hours();
    }
    $out = [];
    $fallback = default_hours();
    for ($d = 0; $d <= 6; $d++) {
        $cfg = $raw[$d] ?? $raw[(string)$d] ?? $fallback[$d];
        $out[$d] = is_array($cfg) ? $cfg : $fallback[$d];
    }
    return $out;
}

function agenda_hour_range(array $tenant, array $events = []): array
{
    $minH = 0;
    $maxH = 23;
    return range($minH, $maxH);
}

function business_day_config(array $tenant, string $date): ?array
{
    $ts = strtotime($date.' 12:00:00');
    if ($ts === false) {
        return null;
    }
    $day = (int)date('w', $ts);
    $cfg = tenant_hours_map($tenant)[$day] ?? null;
    return is_array($cfg) ? $cfg : null;
}

function business_hours_label(array $tenant, string $date): string
{
    $cfg = business_day_config($tenant, $date);
    if (hours_day_closed($cfg)) {
        $days = ['domingo','segunda','terça','quarta','quinta','sexta','sábado'];
        $ts = strtotime($date.' 12:00:00');
        $name = $days[(int)date('w', $ts ?: time())] ?? 'este dia';
        return 'fechado '.$name;
    }
    $end = (string)($cfg['end'] ?? '18:00');
    if ($end === '00:00' || $end === '24:00') {
        $end = '24:00';
    }
    return substr((string)($cfg['start'] ?? '08:00'), 0, 5).'–'.$end;
}

function outside_hours(array $tenant, string $start, string $end): bool
{
    $cfg = business_day_config($tenant, substr($start, 0, 10));
    if (hours_day_closed($cfg)) {
        return true;
    }
    $ts = strtotime($start);
    $te = strtotime($end);
    if ($ts === false || $te === false) {
        return true;
    }
    $open = hm_to_minutes($cfg['start'] ?? '08:00', false);
    $close = hm_to_minutes($cfg['end'] ?? '18:00', true);
    if ($close <= $open) {
        $close += 1440;
    }
    $s = ((int)date('G', $ts)) * 60 + (int)date('i', $ts);
    $e = ((int)date('G', $te)) * 60 + (int)date('i', $te);
    if ($e <= $s && substr($end, 0, 10) !== substr($start, 0, 10)) {
        $e += 1440;
    }
    if ($s < $open || $e > $close) {
        return true;
    }
    foreach ($cfg['breaks'] ?? [] as $b) {
        $bsRaw = trim((string)($b['start'] ?? ''));
        $beRaw = trim((string)($b['end'] ?? ''));
        if ($bsRaw === '' || $beRaw === '') {
            continue;
        }
        $bs = hm_to_minutes($bsRaw, false);
        $be = hm_to_minutes($beRaw, true);
        if ($be <= $bs) {
            continue;
        }
        if ($s < $be && $e > $bs) {
            return true;
        }
    }
    return false;
}

function create_appointment(array $tenant, array $in): array
{
    $svc = !empty($in['service_id']) ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$in['service_id'], $tenant['id']]) : null;
    if (!$svc) {
        return ['ok'=>false,'message'=>'Selecione o serviço. A duração cadastrada define quanto tempo o horário precisa ficar livre.'];
    }
    $dur = service_span_minutes($svc);
    $start = $in['date'] . ' ' . substr((string)$in['start'], 0, 5) . ':00';
    $end = date('Y-m-d H:i:s', strtotime($start) + $dur * 60);
    if (outside_hours($tenant, $start, $end) && empty($in['allow_waiting'])) {
        $label = business_hours_label($tenant, $in['date']);
        return ['ok'=>false,'message'=>'Fora do horário de funcionamento ('.$label.'). O término do serviço também precisa caber no expediente.'];
    }
    $conflict = find_slot_conflict($tenant['id'], $start, $end, $in['ignore'] ?? null);
    if ($conflict) {
        if (empty($in['allow_waiting'])) {
            return ['ok'=>false,'message'=>slot_conflict_message($conflict, (int)($svc['duration_minutes'] ?? $dur))];
        }
        $in['status'] = 'WAITING';
    }
    $cli = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$in['client_id'] ?? '', $tenant['id']]);
    if (!$cli) {
        return ['ok'=>false,'message'=>'Cliente inválido.'];
    }
    if (!empty($in['request_id']) && !one('SELECT id FROM requests WHERE id=? AND tenant_id=?', [$in['request_id'], $tenant['id']])) {
        return ['ok'=>false,'message'=>'Solicitação inválida.'];
    }
    $id = uid();
    q('INSERT INTO appointments(id,tenant_id,client_id,service_id,request_id,starts_at,ends_at,status,source,notes,metadata,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
        $id, $tenant['id'], $cli['id'], $svc['id'] ?? null, $in['request_id'] ?? null, $start, $end,
        $in['status'] ?? 'SCHEDULED', $in['source'] ?? 'Manual', $in['notes'] ?? null,
        isset($in['metadata']) ? json_encode($in['metadata'], JSON_UNESCAPED_UNICODE) : null, now(),
    ]);
    appointment_assign_reserva($tenant['id'], $id);
    audit($tenant['id'], $in['user_id'] ?? null, 'appointment.created', 'appointment', $id);
    notify($tenant['id'], 'Novo agendamento', ($cli['name'] ?? '') . ' · ' . $in['date'] . ' ' . $in['start']);
    emit_outbound($tenant['id'], 'appointment.created', ['id'=>$id]);
    if (!empty($in['commission']) && is_array($in['commission'])) {
        store_appointment_commission($tenant['id'], $id, $in['commission']);
    }
    sync_appointment_finance($tenant['id'], $id);
    if (!empty($in['request_id'])) {
        q('UPDATE requests SET status=?, client_id=? WHERE id=? AND tenant_id=?', ['SCHEDULED', $in['client_id'], $in['request_id'], $tenant['id']]);
        audit($tenant['id'], $in['user_id'] ?? null, 'request.converted', 'request', $in['request_id']);
    }
    if (function_exists('communicate_automation_fire')) {
        communicate_automation_fire($tenant, 'appointment.created', 'appointment', $id);
    }
    return ['ok'=>true,'id'=>$id];
}

function convert_request_to_appointment(array $tenant, array $req, ?string $userId = null, ?string $serviceId = null): array
{
    $serviceId = $serviceId ?: ($req['service_id'] ?? null);
    if (!$serviceId || !one('SELECT id FROM services WHERE id=? AND tenant_id=?', [$serviceId, $tenant['id']])) {
        return ['ok'=>false,'message'=>'Escolha o serviço da reserva. Se o serviço saiu do catálogo, cadastre-o em Serviços antes de converter.'];
    }
    $client = find_or_create_client($tenant['id'], $req['name'], $req['phone'] ?? null, $req['email'] ?? null, $req['source'] ?? 'Manual', $req['phone'] ?? null);
    $date = $req['desired_date'] ?: date('Y-m-d');
    $start = substr((string)($req['desired_time'] ?: '09:00'), 0, 5);
    $meta = !empty($req['metadata']) ? (json_decode($req['metadata'], true) ?: null) : $req;
    $res = create_appointment($tenant, [
        'client_id' => $client['id'],
        'service_id' => $serviceId,
        'date' => $date,
        'start' => $start,
        'status' => 'SCHEDULED',
        'source' => $req['source'] ?: 'Manual',
        'notes' => $req['message'] ?? null,
        'request_id' => $req['id'],
        'user_id' => $userId,
        'metadata' => is_array($meta) ? $meta : null,
        'allow_waiting' => true,
    ]);
    return $res;
}

function webhook_log_origin_denied(array $tenant, array $hook, string $from): void
{
    q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
        uid(), $tenant['id'], $hook['id'], 'Origem bloqueada',
        json_encode(['origin'=>$from], JSON_UNESCAPED_UNICODE),
        403, 'error', 'Domínio não autorizado.',
        $from !== '' ? webhook_origin_host($from) : 'sem-origem', now(),
    ]);
}

function ingest_webhook(string $token, array $body, string $ip): array
{
    if (!rate_ok('wh:'.$token.':'.$ip, 40, 600)) {
        return [429, ['error'=>'Muitas requisições.']];
    }
    $hook = one('SELECT * FROM webhooks WHERE token=? AND direction=? AND '.sql_true('active'), [$token, 'INBOUND']);
    if (!$hook) return [404, ['error'=>'Webhook inválido.']];
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$hook['tenant_id']]);
    if (!$tenant || $tenant['status'] !== 'ACTIVE') return [403, ['error'=>'Conta indisponível.']];
    if (empty($tenant['webhook_access'])) return [403, ['error'=>'Integração aguardando autorização.']];
    $hosts = tenant_webhook_hosts($tenant);
    if (!$hosts) {
        return [403, ['error'=>'Cadastre o domínio do site nas Integrações antes de usar o webhook.']];
    }
    $from = request_webhook_origin();
    if ($from === '' || !webhook_origin_matches($tenant, $from)) {
        webhook_log_origin_denied($tenant, $hook, $from);
        return [403, ['error'=>'Este webhook só aceita o domínio cadastrado do site.']];
    }
    unset($body['tenant_id'], $body['company_id'], $body['role'], $body['is_admin']);
    $type = strtolower((string)($body['type'] ?? 'request'));
    if ($type === 'analytics') {
        $event = strtolower(trim((string)($body['event'] ?? 'visitor_active')));
        if (!in_array($event, ['visitor_active','page_view','social_click','form_view','form_start'], true)) {
            return [400, ['error'=>'Evento analítico inválido.']];
        }
        $visitorId = substr(trim((string)($body['visitor_id'] ?? $body['session_id'] ?? '')), 0, 100);
        if ($visitorId === '') return [400, ['error'=>'Informe visitor_id.']];
        $metadata = [
            'visitor_id'=>$visitorId,
            'page'=>substr((string)($body['page'] ?? ''), 0, 500),
            'path'=>substr((string)($body['path'] ?? ''), 0, 500),
            'referrer'=>substr((string)($body['referrer'] ?? ''), 0, 500),
        ];
        q('INSERT INTO analytics_events(id,tenant_id,event,source,medium,campaign,metadata,created_at) VALUES(?,?,?,?,?,?,?,?)', [
            uid(), $tenant['id'], $event, normalize_source((string)($body['source'] ?? 'Site')),
            substr((string)($body['medium'] ?? ''), 0, 100), substr((string)($body['campaign'] ?? ''), 0, 160),
            json_encode($metadata, JSON_UNESCAPED_UNICODE), now(),
        ]);
        return [200, ['ok'=>true,'kind'=>'analytics','event'=>$event]];
    }
    $name = trim((string)($body['name'] ?? ''));
    $phone = trim((string)($body['phone'] ?? $body['whatsapp'] ?? ''));
    $email = trim((string)($body['email'] ?? ''));
    if ($name === '' || ($phone === '' && $email === '')) {
        q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenant['id'], $hook['id'], 'Validação', json_encode($body), 400, 'error', 'Informe nome e telefone ou e-mail.', $body['source'] ?? 'Website', now(),
        ]);
        return [400, ['error'=>'Informe o nome e telefone ou e-mail.']];
    }
    $source = normalize_source((string)($body['source'] ?? 'Website'));
    $svc = null;
    if (!empty($body['service'])) {
        $needle = str_replace(['%', '_'], ['\\%', '\\_'], (string)$body['service']);
        $svc = one('SELECT * FROM services WHERE tenant_id=? AND name LIKE ? AND status=?', [$tenant['id'], '%'.$needle.'%', 'ACTIVE']);
    }
    $attribution = [
        'utm_source' => $body['utm_source'] ?? null,
        'utm_medium' => $body['utm_medium'] ?? null,
        'utm_campaign' => $body['utm_campaign'] ?? null,
    ];
    $client = find_or_create_client($tenant['id'], $name, $phone ?: null, $email ?: null, $source, $body['whatsapp'] ?? $phone, $attribution);
    q('INSERT INTO analytics_events(id,tenant_id,event,source,medium,campaign,metadata,created_at) VALUES(?,?,?,?,?,?,?,?)', [
        uid(), $tenant['id'], 'lead_received', $attribution['utm_source'] ?: $source,
        $attribution['utm_medium'], $attribution['utm_campaign'],
        json_encode(['type'=>$type], JSON_UNESCAPED_UNICODE), now(),
    ]);
    $date = empty_to_null($body['date'] ?? $body['desired_date'] ?? null);
    $time = empty_to_null($body['time'] ?? $body['desired_time'] ?? null);
    if ($type === 'appointment' && $date && $time) {
        $res = create_appointment($tenant, [
            'client_id'=>$client['id'],'service_id'=>$svc['id']??null,'date'=>$date,'start'=>$time,
            'status'=>'WAITING','source'=>'Webhook','notes'=>$body['notes'] ?? $body['message'] ?? null,
            'metadata'=>$body,
        ]);
        if ($res['ok']) {
            q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tenant['id'], $hook['id'], 'Novo agendamento', json_encode($body), 200, 'ok', 'Aguardando confirmação', $source, now(),
            ]);
            return [200, ['ok'=>true,'kind'=>'appointment','id'=>$res['id'],'status'=>'WAITING']];
        }
        $rid = uid();
        q('INSERT INTO requests(id,tenant_id,client_id,service_id,name,phone,email,desired_date,desired_time,message,source,utm_source,utm_medium,utm_campaign,metadata,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            $rid, $tenant['id'], $client['id'], $svc['id']??null, $name, $phone?:null, $email?:null, $date, $time,
            trim(($body['message']??'').' ('.$res['message'].')'), $source,
            $attribution['utm_source'], $attribution['utm_medium'], $attribution['utm_campaign'],
            json_encode($body, JSON_UNESCAPED_UNICODE), 'NEW', now(),
        ]);
        notify($tenant['id'], 'Nova solicitação recebida', $name.' — horário ocupado');
        if (function_exists('communicate_automation_fire')) {
            communicate_automation_fire($tenant, 'request.created', 'request', $rid);
        }
        q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenant['id'], $hook['id'], 'Nova solicitação', json_encode($body), 200, 'ok', 'Horário ocupado. Criada solicitação.', $source, now(),
        ]);
        return [200, ['ok'=>true,'kind'=>'request','id'=>$rid,'warning'=>$res['message']]];
    }
    $rid = uid();
    q('INSERT INTO requests(id,tenant_id,client_id,service_id,name,phone,email,desired_date,desired_time,message,source,utm_source,utm_medium,utm_campaign,metadata,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $rid, $tenant['id'], $client['id'], $svc['id']??null, $name, $phone?:null, $email?:null,
        $date, $time, $body['message']??null, $source,
        $attribution['utm_source'], $attribution['utm_medium'], $attribution['utm_campaign'],
        json_encode($body, JSON_UNESCAPED_UNICODE), 'NEW', now(),
    ]);
    notify($tenant['id'], 'Nova solicitação recebida', $name);
    if (function_exists('communicate_automation_fire')) {
        communicate_automation_fire($tenant, 'request.created', 'request', $rid);
    }
    emit_outbound($tenant['id'], 'request.received', ['id'=>$rid,'name'=>$name]);
    q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
        uid(), $tenant['id'], $hook['id'], 'Nova solicitação', json_encode($body), 200, 'ok', 'Recebido', $source, now(),
    ]);
    return [200, ['ok'=>true,'kind'=>'request','id'=>$rid]];
}

function assistant_feed(array $tenant): array
{
    $tid = $tenant['id'];
    $messages = [];
    foreach (all('SELECT * FROM notifications WHERE tenant_id=? ORDER BY created_at DESC LIMIT 8', [$tid]) as $n) {
        $title = lower($n['title']);
        $url = str_contains($title, 'solicita') ? '/app/solicitacoes'
            : (str_contains($title, 'agendamento') ? '/app/agenda' : '/app');
        $messages[] = [
            'id'=>'notification-'.$n['id'],'kind'=>'notification','title'=>$n['title'],
            'body'=>$n['body'] ?: 'Há uma nova atualização para você.','url'=>$url,'created_at'=>$n['created_at'],
        ];
    }

    $analytics = analytics_config($tenant);
    if (!empty($analytics['gtm_id'])) {
        $recent = all("SELECT metadata FROM analytics_events WHERE tenant_id=? AND event='visitor_active' AND created_at>=? ORDER BY created_at DESC", [
            $tid, date('Y-m-d H:i:s', time()-300),
        ]);
        $visitors = [];
        foreach ($recent as $event) {
            $meta = json_decode($event['metadata'] ?: '{}', true);
            if (!empty($meta['visitor_id'])) $visitors[$meta['visitor_id']] = true;
        }
        $count = count($visitors);
        if ($count > 0) {
            $messages[] = [
                'id'=>'visitors-'.date('YmdHi', (int)(floor(time()/300)*300)),
                'kind'=>'analytics','title'=>'Movimento no seu site',
                'body'=>$count === 1
                    ? 'Hey! Há 1 pessoa navegando no seu site neste momento.'
                    : "Hey! Há {$count} pessoas navegando no seu site neste momento.",
                'url'=>'/app/metricas','created_at'=>now(),
            ];
        }
    }

    foreach (all("SELECT id,event,response,created_at FROM webhook_logs WHERE tenant_id=? AND status='error' AND created_at>=? ORDER BY created_at DESC LIMIT 3", [
        $tid, date('Y-m-d H:i:s', time()-86400),
    ]) as $log) {
        $messages[] = [
            'id'=>'webhook-error-'.$log['id'],'kind'=>'warning','title'=>'Atenção na integração',
            'body'=>$log['event'].': '.($log['response'] ?: 'a requisição não foi processada.'),
            'url'=>'/app/webhooks','created_at'=>$log['created_at'],
        ];
    }

    $next = one("SELECT a.id,a.starts_at,c.name client_name,s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.status NOT IN ('CANCELLED','DONE') AND a.starts_at BETWEEN ? AND ? ORDER BY a.starts_at LIMIT 1", [
        $tid, now(), date('Y-m-d H:i:s', time()+3600),
    ]);
    if ($next) {
        $messages[] = [
            'id'=>'upcoming-'.$next['id'],'kind'=>'appointment','title'=>'Próximo horário',
            'body'=>substr($next['starts_at'], 11, 5).' · '.$next['client_name'].' · '.($next['service_name'] ?: 'Atendimento'),
            'url'=>'/app/agenda?ver='.$next['id'],'created_at'=>$next['starts_at'],
        ];
    }

    $inbound = !empty($tenant['webhook_access']) ? one("SELECT active FROM webhooks WHERE tenant_id=? AND direction='INBOUND'", [$tid]) : null;
    if ($inbound && !$inbound['active']) {
        $messages[] = [
            'id'=>'webhook-disabled','kind'=>'warning','title'=>'Webhook desativado',
            'body'=>'Seu site não poderá enviar novas solicitações enquanto a integração estiver desativada.',
            'url'=>'/app/webhooks','created_at'=>now(),
        ];
    }

    usort($messages, fn($a,$b) => strcmp($b['created_at'], $a['created_at']));
    return array_slice($messages, 0, 15);
}
