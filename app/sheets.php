<?php
declare(strict_types=1);

function platform_settings(): array
{
    $row = one("SELECT value FROM platform_settings WHERE key='app'");
    $raw = $row['value'] ?? '{}';
    if (is_array($raw)) {
        $data = $raw;
    } else {
        $data = json_decode((string)$raw, true);
    }
    if (!is_array($data)) {
        $data = [];
    }
    foreach (['google_client_id' => 'GOOGLE_CLIENT_ID', 'google_client_secret' => 'GOOGLE_CLIENT_SECRET'] as $field => $envKey) {
        $fromEnv = env_str($envKey);
        if ($fromEnv) {
            $data[$field] = $fromEnv;
        }
    }
    return $data;
}

function save_platform_settings(array $settings): void
{
    $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE);
    $now = now();
    if (is_pgsql()) {
        q("INSERT INTO platform_settings(key,value,updated_at) VALUES('app', CAST(? AS jsonb), ?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=EXCLUDED.updated_at", [$encoded, $now]);
        return;
    }
    q("INSERT OR REPLACE INTO platform_settings(key,value,updated_at) VALUES('app',?,?)", [$encoded, $now]);
}

function google_oauth_ready(): bool
{
    $p = platform_settings();
    return !empty($p['google_client_id']) && !empty($p['google_client_secret']);
}

function google_redirect_uri(): string
{
    return app_url() . '/app/google/callback';
}

function sheets_headers(): array
{
    return [
        'client' => ['id','nome','telefone','whatsapp','email','cpf','nascimento','origem','status','observacoes','utm_source','utm_medium','utm_campaign','criado_em'],
        'appointment' => ['id','cliente','telefone','email','servico','data','inicio','fim','status','origem','observacoes','criado_em'],
        'service' => ['id','nome','categoria','descricao','duracao_min','intervalo_min','preco','sinal','local','endereco_link','capacidade','status','agendavel_online','exige_confirmacao','criado_em'],
    ];
}

function sheets_titles(): array
{
    return ['client'=>'Clientes','appointment'=>'Agendamentos','service'=>'Serviços'];
}

function sheets_config(array $tenant): array
{
    $cfg = json_arr($tenant['sheets_config'] ?? '{}');
    return $cfg + [
        'enabled' => 0,
        'google_email' => '',
        'access_token' => '',
        'refresh_token' => '',
        'expires_at' => 0,
        'spreadsheet_id' => '',
        'spreadsheet_url' => '',
        'last_sync' => null,
        'last_status' => null,
        'last_error' => null,
    ];
}

function sheets_save(string $tenantId, array $cfg): void
{
    q('UPDATE tenants SET sheets_config=?, updated_at=? WHERE id=?', [json_encode($cfg, JSON_UNESCAPED_UNICODE), now(), $tenantId]);
}

function google_http(string $method, string $url, mixed $body = null, ?string $accessToken = null, bool $form = false): array
{
    $headers = ['Accept: application/json'];
    if ($accessToken) $headers[] = 'Authorization: Bearer '.$accessToken;
    $payload = null;
    if ($body !== null) {
        if ($form) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $payload = http_build_query($body);
        } else {
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $raw = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http'=>[
            'method'=>$method,
            'header'=>implode("\r\n", $headers),
            'content'=>$payload ?? '',
            'timeout'=>20,
            'ignore_errors'=>true,
            'follow_location'=>1,
        ]]);
        $raw = (string)@file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $code = (int)$m[1];
    }
    $json = json_decode($raw, true);
    return ['ok'=>$code>=200 && $code<300, 'code'=>$code, 'json'=>is_array($json)?$json:[], 'raw'=>$raw];
}

function google_auth_url(string $tenantId): string
{
    $p = platform_settings();
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_tenant'] = $tenantId;
    $params = [
        'client_id' => $p['google_client_id'],
        'redirect_uri' => google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/userinfo.email',
        'access_type' => 'offline',
        'prompt' => 'select_account consent',
        'include_granted_scopes' => 'true',
        'state' => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($params);
}

function google_exchange_code(string $code): array
{
    $p = platform_settings();
    return google_http('POST', 'https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $p['google_client_id'],
        'client_secret' => $p['google_client_secret'],
        'redirect_uri' => google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ], null, true);
}

function google_refresh_token(array &$cfg): bool
{
    if (empty($cfg['refresh_token'])) return false;
    $p = platform_settings();
    $res = google_http('POST', 'https://oauth2.googleapis.com/token', [
        'refresh_token' => $cfg['refresh_token'],
        'client_id' => $p['google_client_id'],
        'client_secret' => $p['google_client_secret'],
        'grant_type' => 'refresh_token',
    ], null, true);
    if (empty($res['json']['access_token'])) return false;
    $cfg['access_token'] = $res['json']['access_token'];
    $cfg['expires_at'] = time() + (int)($res['json']['expires_in'] ?? 3500);
    if (!empty($res['json']['refresh_token'])) $cfg['refresh_token'] = $res['json']['refresh_token'];
    return true;
}

function google_token_for(string $tenantId, array &$cfg): ?string
{
    if (empty($cfg['access_token']) && empty($cfg['refresh_token'])) return null;
    if ((int)$cfg['expires_at'] < time() + 60) {
        if (!google_refresh_token($cfg)) return null;
        sheets_save($tenantId, $cfg);
    }
    return $cfg['access_token'] ?: null;
}

function sheets_connected(array $cfg): bool
{
    return !empty($cfg['refresh_token']) || !empty($cfg['access_token']);
}

function sheets_client_row(array $c): array
{
    return [
        'id' => $c['id'],
        'nome' => $c['name'] ?? '',
        'telefone' => $c['phone'] ?? '',
        'whatsapp' => $c['whatsapp'] ?? '',
        'email' => $c['email'] ?? '',
        'cpf' => $c['cpf'] ?? '',
        'nascimento' => $c['birth_date'] ?? '',
        'origem' => $c['source'] ?? '',
        'status' => $c['status'] ?? '',
        'observacoes' => $c['notes'] ?? '',
        'utm_source' => $c['utm_source'] ?? '',
        'utm_medium' => $c['utm_medium'] ?? '',
        'utm_campaign' => $c['utm_campaign'] ?? '',
        'criado_em' => $c['created_at'] ?? now(),
    ];
}

function sheets_appointment_row(array $a): array
{
    $status = APPT_STATUS[$a['status'] ?? ''][0] ?? ($a['status'] ?? '');
    return [
        'id' => $a['id'],
        'cliente' => $a['client_name'] ?? '',
        'telefone' => $a['client_phone'] ?? '',
        'email' => $a['client_email'] ?? '',
        'servico' => $a['service_name'] ?? '',
        'data' => $a['starts_at'] ? substr($a['starts_at'], 0, 10) : '',
        'inicio' => $a['starts_at'] ? substr($a['starts_at'], 11, 5) : '',
        'fim' => $a['ends_at'] ? substr($a['ends_at'], 11, 5) : '',
        'status' => $status,
        'origem' => $a['source'] ?? '',
        'observacoes' => $a['notes'] ?? '',
        'criado_em' => $a['created_at'] ?? now(),
    ];
}

function sheets_service_row(array $s): array
{
    return [
        'id' => $s['id'],
        'nome' => $s['name'] ?? '',
        'categoria' => $s['category'] ?? '',
        'descricao' => $s['description'] ?? '',
        'duracao_min' => (int)($s['duration_minutes'] ?? 0),
        'intervalo_min' => (int)($s['buffer_minutes'] ?? 0),
        'preco' => (float)($s['price'] ?? 0),
        'sinal' => (float)($s['deposit'] ?? 0),
        'local' => service_location_label($s['location_type'] ?? null),
        'endereco_link' => $s['location_note'] ?? '',
        'capacidade' => (int)($s['capacity'] ?? 1),
        'status' => ($s['status'] ?? '') === 'ACTIVE' ? 'Ativo' : 'Inativo',
        'agendavel_online' => !empty($s['bookable_online']) ? 'Sim' : 'Não',
        'exige_confirmacao' => !empty($s['requires_confirmation']) ? 'Sim' : 'Não',
        'criado_em' => $s['created_at'] ?? now(),
    ];
}

function sheets_load_appointment(string $tenantId, string $id): ?array
{
    return one("SELECT a.*, c.name client_name, c.phone client_phone, c.email client_email, s.name service_name
        FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id
        WHERE a.id=? AND a.tenant_id=?", [$id, $tenantId]);
}

function sheets_line(string $entity, array $row): array
{
    $out = [];
    foreach (sheets_headers()[$entity] as $key) $out[] = (string)($row[$key] ?? '');
    return $out;
}

function sheets_api(string $method, string $path, array $cfg, mixed $body = null): array
{
    return google_http($method, 'https://sheets.googleapis.com/v4/'.$path, $body, $cfg['access_token']);
}

function sheets_ensure_spreadsheet(string $tenantId, array $tenant, array &$cfg): bool
{
    if (!empty($cfg['spreadsheet_id'])) return true;
    $title = 'FirestepCRM · '.($tenant['display_name'] ?: $tenant['business_name'] ?: 'CRM');
    $res = sheets_api('POST', 'spreadsheets', $cfg, [
        'properties' => ['title' => $title, 'locale' => 'pt_BR', 'timeZone' => $tenant['timezone'] ?? 'America/Sao_Paulo'],
        'sheets' => array_map(fn($name) => ['properties'=>['title'=>$name]], array_values(sheets_titles())),
    ]);
    if (empty($res['json']['spreadsheetId'])) return false;
    $cfg['spreadsheet_id'] = $res['json']['spreadsheetId'];
    $cfg['spreadsheet_url'] = $res['json']['spreadsheetUrl'] ?? ('https://docs.google.com/spreadsheets/d/'.$cfg['spreadsheet_id']);
    sheets_save($tenantId, $cfg);
    foreach (sheets_titles() as $entity => $name) {
        sheets_write_values($cfg, $name, [sheets_headers()[$entity]]);
    }
    return true;
}

function sheets_write_values(array $cfg, string $sheet, array $values, string $range = 'A1'): array
{
    $encoded = rawurlencode("'".$sheet."'!".$range);
    return sheets_api('PUT', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).'/values/'.$encoded.'?valueInputOption=RAW', $cfg, [
        'range' => "'".$sheet."'!".$range,
        'majorDimension' => 'ROWS',
        'values' => $values,
    ]);
}

function sheets_append_values(array $cfg, string $sheet, array $values): array
{
    $encoded = rawurlencode("'".$sheet."'!A1");
    return sheets_api('POST', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).'/values/'.$encoded.':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', $cfg, [
        'values' => $values,
    ]);
}

function sheets_read_ids(array $cfg, string $sheet): array
{
    $encoded = rawurlencode("'".$sheet."'!A:A");
    $res = sheets_api('GET', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).'/values/'.$encoded, $cfg);
    $map = [];
    foreach (($res['json']['values'] ?? []) as $i => $row) {
        if ($i === 0) continue;
        $id = (string)($row[0] ?? '');
        if ($id !== '') $map[$id] = $i + 1;
    }
    return $map;
}

function sheets_delete_row(array $cfg, string $sheet, int $rowNumber): void
{
    $meta = sheets_api('GET', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).'?fields=sheets.properties', $cfg);
    $sheetId = null;
    foreach (($meta['json']['sheets'] ?? []) as $sh) {
        if (($sh['properties']['title'] ?? '') === $sheet) $sheetId = $sh['properties']['sheetId'] ?? null;
    }
    if ($sheetId === null) return;
    sheets_api('POST', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).':batchUpdate', $cfg, [
        'requests' => [[
            'deleteDimension' => [
                'range' => ['sheetId'=>$sheetId, 'dimension'=>'ROWS', 'startIndex'=>$rowNumber-1, 'endIndex'=>$rowNumber],
            ],
        ]],
    ]);
}

function sheets_finish(string $tenantId, array $cfg, bool $ok, ?string $error = null): array
{
    $cfg['last_sync'] = now();
    $cfg['last_status'] = $ok ? 'ok' : 'error';
    $cfg['last_error'] = $ok ? null : $error;
    $cfg['enabled'] = sheets_connected($cfg) ? 1 : 0;
    sheets_save($tenantId, $cfg);
    return ['ok'=>$ok, 'message'=>$ok ? 'Sincronizado com o Google Sheets.' : ($error ?: 'Falha na sincronização.')];
}

function push_google_sheets(string $tenantId, string $entity, string $action, ?string $id): void
{
    if (!$id) return;
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    if (!$tenant) return;
    $cfg = sheets_config($tenant);
    if (!sheets_connected($cfg) || !google_token_for($tenantId, $cfg)) return;
    if (!sheets_ensure_spreadsheet($tenantId, $tenant, $cfg)) return;
    $titles = sheets_titles();
    $sheet = $titles[$entity] ?? null;
    if (!$sheet) return;
    if ($action === 'delete') {
        $ids = sheets_read_ids($cfg, $sheet);
        if (!empty($ids[$id])) sheets_delete_row($cfg, $sheet, $ids[$id]);
        sheets_finish($tenantId, $cfg, true);
        return;
    }
    $row = null;
    if ($entity === 'client') {
        $item = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        $row = $item ? sheets_client_row($item) : null;
    } elseif ($entity === 'appointment') {
        $item = sheets_load_appointment($tenantId, $id);
        $row = $item ? sheets_appointment_row($item) : null;
    } elseif ($entity === 'service') {
        $item = one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        $row = $item ? sheets_service_row($item) : null;
    }
    if (!$row) return;
    $ids = sheets_read_ids($cfg, $sheet);
    $line = sheets_line($entity, $row);
    if (!empty($ids[$id])) {
        $res = sheets_write_values($cfg, $sheet, [$line], 'A'.$ids[$id]);
    } else {
        $res = sheets_append_values($cfg, $sheet, [$line]);
    }
    sheets_finish($tenantId, $cfg, !empty($res['ok']), $res['ok'] ? null : substr($res['raw'] ?: 'Erro ao gravar na planilha.', 0, 240));
}

function sync_google_sheets_all(string $tenantId): array
{
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    if (!$tenant) return ['ok'=>false,'message'=>'Conta não encontrada.'];
    $cfg = sheets_config($tenant);
    if (!sheets_connected($cfg) || !google_token_for($tenantId, $cfg)) {
        return ['ok'=>false,'message'=>'Conecte sua conta Google para sincronizar.'];
    }
    if (!sheets_ensure_spreadsheet($tenantId, $tenant, $cfg)) {
        return sheets_finish($tenantId, $cfg, false, 'Não foi possível criar a planilha no Google.');
    }
    $sets = [
        'client' => array_map('sheets_client_row', all('SELECT * FROM clients WHERE tenant_id=? ORDER BY created_at', [$tenantId])),
        'service' => array_map('sheets_service_row', all('SELECT * FROM services WHERE tenant_id=? ORDER BY name', [$tenantId])),
        'appointment' => array_map('sheets_appointment_row', all("SELECT a.*, c.name client_name, c.phone client_phone, c.email client_email, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id LEFT JOIN services s ON s.id=a.service_id WHERE a.tenant_id=? ORDER BY a.starts_at", [$tenantId])),
    ];
    foreach ($sets as $entity => $rows) {
        $values = [sheets_headers()[$entity]];
        foreach ($rows as $row) $values[] = sheets_line($entity, $row);
        $sheet = sheets_titles()[$entity];
        $clear = sheets_api('POST', 'spreadsheets/'.rawurlencode($cfg['spreadsheet_id']).'/values/'.rawurlencode("'".$sheet."'").':clear', $cfg, new stdClass());
        $res = sheets_write_values($cfg, $sheet, $values);
        if (empty($res['ok'])) {
            return sheets_finish($tenantId, $cfg, false, substr($res['raw'] ?: 'Falha ao enviar '.$sheet.'.', 0, 240));
        }
        unset($clear);
    }
    return sheets_finish($tenantId, $cfg, true);
}

function google_complete_login(string $tenantId, array $tokens): array
{
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    $cfg = sheets_config($tenant);
    $cfg['access_token'] = $tokens['access_token'] ?? '';
    if (!empty($tokens['refresh_token'])) $cfg['refresh_token'] = $tokens['refresh_token'];
    $cfg['expires_at'] = time() + (int)($tokens['expires_in'] ?? 3500);
    $cfg['enabled'] = 1;
    $info = google_http('GET', 'https://www.googleapis.com/oauth2/v2/userinfo', null, $cfg['access_token']);
    $cfg['google_email'] = $info['json']['email'] ?? ($cfg['google_email'] ?? '');
    if (!google_token_for($tenantId, $cfg) && empty($cfg['access_token'])) {
        return ['ok'=>false,'message'=>'Não foi possível autenticar no Google.'];
    }
    sheets_save($tenantId, $cfg);
    if (!sheets_ensure_spreadsheet($tenantId, $tenant, $cfg)) {
        return ['ok'=>false,'message'=>'Google autenticado, mas a planilha não pôde ser criada.'];
    }
    sync_google_sheets_all($tenantId);
    return ['ok'=>true,'message'=>'Google conectado. A planilha já está sincronizando automaticamente.'];
}
