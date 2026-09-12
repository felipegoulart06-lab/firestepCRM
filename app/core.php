<?php
declare(strict_types=1);

require_once __DIR__ . '/sheets.php';

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
    $password = (string)($in['password'] ?? '');
    if (!password_is_strong($password)) {
        $password = generate_temp_password();
    }
    $tid = uid();
    $uid = uid();
    $tnow = now();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO tenants(id,name,business_name,slug,segment,document,email,phone,whatsapp,city,state,status,plan,primary_color,display_name,timezone,terminology,business_hours,onboarding_done,created_at,updated_at)
           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'.sql_lit_bool(false).',?,?)', [
            $tid, $in['name'], $in['business_name'], $slug, $in['segment'] ?? 'outros',
            $in['document'] ?? null, $email, $in['phone'] ?? null, $in['whatsapp'] ?? null,
            $in['city'] ?? null, $in['state'] ?? null, $in['status'] ?? 'ACTIVE', $in['plan'] ?? 'starter',
            $in['primary_color'] ?? '#2563eb', $in['display_name'] ?? $in['business_name'],
            'America/Sao_Paulo', json_encode($preset['terms'] + ['request'=>'Solicitação','requests'=>'Solicitações'], JSON_UNESCAPED_UNICODE),
            json_encode(default_hours()), $tnow, $tnow,
        ]);
        q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,phone,must_change_password,active,created_at)
           VALUES(?,?,?,?,?,?,?,?,'.sql_lit_bool(true).','.sql_lit_bool(true).',?)', [
            $uid, $tid, $in['name'], $email, $username,
            password_hash($password, PASSWORD_DEFAULT), 'TENANT_ADMIN', $in['phone'] ?? null, $tnow,
        ]);
        foreach ($preset['services'] as $s) {
            q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
                uid(), $tid, $s['name'], $s['category'] ?? null, $s['duration'], $s['price'], 'ACTIVE', $tnow,
            ]);
        }
        $ord = 0;
        foreach ($preset['fields'] as $f) {
            q('INSERT INTO custom_fields(id,tenant_id,label,key,type,options,sort_order) VALUES(?,?,?,?,?,?,?)', [
                uid(), $tid, $f['label'], $f['key'], $f['type'], isset($f['options']) ? json_encode($f['options']) : null, $ord++,
            ]);
        }
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
    push_google_sheets($tenant, 'client', 'upsert', $id);
    return one('SELECT * FROM clients WHERE id=?', [$id]);
}

function emit_outbound(string $tenant, string $event, array $payload): void
{
    if (!one('SELECT id FROM tenants WHERE id=? AND '.sql_true('webhook_access'), [$tenant])) return;
    foreach (all('SELECT * FROM webhooks WHERE tenant_id=? AND direction=? AND '.sql_true('active'), [$tenant, 'OUTBOUND']) as $hook) {
        $events = json_arr($hook['events'] ?: '[]');
        if (!in_array($event, $events, true) || !$hook['url']) continue;
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

function has_conflict(string $tenant, string $start, string $end, ?string $ignoreAppt = null): bool
{
    $sql = 'SELECT id FROM appointments WHERE tenant_id=? AND status!=? AND starts_at<? AND ends_at>?';
    $p = [$tenant, 'CANCELLED', $end, $start];
    if ($ignoreAppt) { $sql .= ' AND id!=?'; $p[] = $ignoreAppt; }
    if (one($sql, $p)) return true;
    return (bool)one('SELECT id FROM calendar_blocks WHERE tenant_id=? AND starts_at<? AND ends_at>?', [$tenant, $end, $start]);
}

function outside_hours(array $tenant, string $start, string $end): bool
{
    $hours = json_arr($tenant['business_hours'] ?: '{}', default_hours());
    $ts = strtotime($start);
    $te = strtotime($end);
    $day = (int)date('w', $ts);
    $cfg = $hours[$day] ?? $hours[(string)$day] ?? null;
    if (!$cfg || !empty($cfg['closed'])) return true;
    $toMin = fn($hm) => ((int)substr($hm,0,2))*60 + (int)substr($hm,3,2);
    $s = ((int)date('G',$ts))*60 + (int)date('i',$ts);
    $e = ((int)date('G',$te))*60 + (int)date('i',$te);
    if ($s < $toMin($cfg['start']) || $e > $toMin($cfg['end'])) return true;
    foreach ($cfg['breaks'] ?? [] as $b) {
        if ($s < $toMin($b['end']) && $e > $toMin($b['start'])) return true;
    }
    return false;
}

function create_appointment(array $tenant, array $in): array
{
    $svc = $in['service_id'] ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$in['service_id'], $tenant['id']]) : null;
    $dur = service_span_minutes($svc);
    $start = $in['date'] . ' ' . $in['start'] . ':00';
    $end = date('Y-m-d H:i:s', strtotime($start) + $dur * 60);
    if (outside_hours($tenant, $start, $end) && empty($in['allow_waiting'])) {
        return ['ok'=>false,'message'=>'Fora do horário de funcionamento.'];
    }
    if (has_conflict($tenant['id'], $start, $end, $in['ignore'] ?? null)) {
        if (empty($in['allow_waiting'])) {
            return ['ok'=>false,'message'=>'Este horário já está ocupado.'];
        }
        $in['status'] = 'WAITING';
    }
    $id = uid();
    q('INSERT INTO appointments(id,tenant_id,client_id,service_id,request_id,starts_at,ends_at,status,source,notes,metadata,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
        $id, $tenant['id'], $in['client_id'], $svc['id'] ?? null, $in['request_id'] ?? null, $start, $end,
        $in['status'] ?? 'SCHEDULED', $in['source'] ?? 'Manual', $in['notes'] ?? null,
        isset($in['metadata']) ? json_encode($in['metadata'], JSON_UNESCAPED_UNICODE) : null, now(),
    ]);
    $cli = one('SELECT name FROM clients WHERE id=?', [$in['client_id']]);
    audit($tenant['id'], $in['user_id'] ?? null, 'appointment.created', 'appointment', $id);
    notify($tenant['id'], 'Novo agendamento', ($cli['name'] ?? '') . ' · ' . $in['date'] . ' ' . $in['start']);
    emit_outbound($tenant['id'], 'appointment.created', ['id'=>$id]);
    push_google_sheets($tenant['id'], 'appointment', 'upsert', $id);
    if (!empty($in['request_id'])) {
        q('UPDATE requests SET status=?, client_id=? WHERE id=? AND tenant_id=?', ['SCHEDULED', $in['client_id'], $in['request_id'], $tenant['id']]);
        audit($tenant['id'], $in['user_id'] ?? null, 'request.converted', 'request', $in['request_id']);
    }
    return ['ok'=>true,'id'=>$id];
}

function convert_request_to_appointment(array $tenant, array $req, ?string $userId = null): array
{
    $client = find_or_create_client($tenant['id'], $req['name'], $req['phone'] ?? null, $req['email'] ?? null, $req['source'] ?? 'Manual', $req['phone'] ?? null);
    $date = $req['desired_date'] ?: date('Y-m-d');
    $start = substr((string)($req['desired_time'] ?: '09:00'), 0, 5);
    $meta = !empty($req['metadata']) ? (json_decode($req['metadata'], true) ?: null) : $req;
    $res = create_appointment($tenant, [
        'client_id' => $client['id'],
        'service_id' => $req['service_id'] ?? null,
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
        $svc = one('SELECT * FROM services WHERE tenant_id=? AND name LIKE ? AND status=?', [$tenant['id'], '%'.$body['service'].'%', 'ACTIVE']);
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
    $date = $body['date'] ?? $body['desired_date'] ?? null;
    $time = $body['time'] ?? $body['desired_time'] ?? null;
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
        q('INSERT INTO webhook_logs(id,tenant_id,webhook_id,event,payload,http_status,status,response,source,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenant['id'], $hook['id'], 'Nova solicitação', json_encode($body), 200, 'ok', 'Horário ocupado. Criada solicitação.', $source, now(),
        ]);
        return [200, ['ok'=>true,'kind'=>'request','id'=>$rid,'warning'=>$res['message']]];
    }
    $rid = uid();
    q('INSERT INTO requests(id,tenant_id,client_id,service_id,name,phone,email,desired_date,desired_time,message,source,utm_source,utm_medium,utm_campaign,metadata,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $rid, $tenant['id'], $client['id'], $svc['id']??null, $name, $phone?:null, $email?:null,
        $body['desired_date']??$date, $body['desired_time']??$time, $body['message']??null, $source,
        $attribution['utm_source'], $attribution['utm_medium'], $attribution['utm_campaign'],
        json_encode($body, JSON_UNESCAPED_UNICODE), 'NEW', now(),
    ]);
    notify($tenant['id'], 'Nova solicitação recebida', $name);
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

    $next = one("SELECT a.id,a.starts_at,c.name client_name,s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? AND a.status NOT IN ('CANCELLED','DONE') AND a.starts_at BETWEEN ? AND ? ORDER BY a.starts_at LIMIT 1", [
        $tid, now(), date('Y-m-d H:i:s', time()+3600),
    ]);
    if ($next) {
        $messages[] = [
            'id'=>'upcoming-'.$next['id'],'kind'=>'appointment','title'=>'Próximo horário',
            'body'=>substr($next['starts_at'], 11, 5).' · '.$next['client_name'].' · '.($next['service_name'] ?: 'Atendimento'),
            'url'=>'/app/agenda?edit='.$next['id'],'created_at'=>$next['starts_at'],
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
