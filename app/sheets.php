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
        'request' => ['id','nome','telefone','email','servico','origem','status','desejada','criado_em'],
        'agent' => ['id','nome','email','telefone','situacao','criado_em'],
        'supplier' => ['id','nome','documento','produto','cidade','estado','email','telefone','criado_em'],
        'finance' => ['id','descricao','valor','vencimento','status','forma','cliente','origem','criado_em'],
        'resumo' => ['metrica','valor'],
    ];
}

function sheets_titles(): array
{
    return [
        'appointment' => 'Agendamentos',
        'request' => 'Solicitações',
        'client' => 'Clientes',
        'agent' => 'Agentes',
        'supplier' => 'Fornecedores',
        'service' => 'Serviços',
    ];
}

function sheets_entity_file(string $entity): string
{
    return match ($entity) {
        'appointment' => 'agendamentos',
        'request' => 'solicitacoes',
        'client' => 'clientes',
        'agent' => 'agentes',
        'supplier' => 'fornecedores',
        'service' => 'servicos',
        default => $entity,
    };
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
        'drive_folder_id' => '',
        'drive_folder_url' => '',
        'folders' => [],
        'files' => [],
        'last_sync' => null,
        'last_status' => null,
        'last_error' => null,
    ];
}

function google_oauth_scopes(): string
{
    return implode(' ', [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/spreadsheets',
        'https://www.googleapis.com/auth/userinfo.email',
    ]);
}

function google_drive_folders(): array
{
    return [
        'agendamentos' => 'AGENDAMENTOS',
        'solicitacoes' => 'SOLICITAÇÕES',
        'clientes' => 'CLIENTES',
        'agentes' => 'AGENTES',
        'fornecedores' => 'FORNECEDORES',
        'servicos' => 'SERVIÇOS',
        'financeiro' => 'FINANCEIRO',
    ];
}

function google_finance_files(): array
{
    return [
        'financeiro_dashboard' => 'Dashboard',
        'financeiro_lancamentos' => 'Lançamentos',
        'financeiro_receber' => 'Contas a receber',
        'financeiro_faturado' => 'Faturado',
        'financeiro_pagar' => 'Contas a pagar',
        'financeiro_relatorios' => 'Relatórios',
    ];
}

function google_folder_files(): array
{
    return [
        'agendamentos' => ['agendamentos' => 'Agendamentos'],
        'solicitacoes' => ['solicitacoes' => 'Solicitações'],
        'clientes' => ['clientes' => 'Clientes'],
        'agentes' => ['agentes' => 'Agentes'],
        'fornecedores' => ['fornecedores' => 'Fornecedores'],
        'servicos' => ['servicos' => 'Serviços'],
        'financeiro' => google_finance_files(),
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
            CURLOPT_TIMEOUT => 35,
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
            'timeout'=>35,
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
        'scope' => google_oauth_scopes(),
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
        'preco' => service_price_kind($s) === 'priced' ? (float)($s['price'] ?? 0) : 0,
        'tipo_preco' => service_price_label($s),
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

function sheets_request_row(array $r): array
{
    $desired = trim((string)($r['desired_date'] ?? ''));
    if ($desired !== '' && !empty($r['desired_time'])) {
        $desired .= ' '.$r['desired_time'];
    }
    return [
        'id' => $r['id'] ?? '',
        'nome' => $r['name'] ?? '',
        'telefone' => $r['phone'] ?? '',
        'email' => $r['email'] ?? '',
        'servico' => $r['service_name'] ?? '',
        'origem' => (string)($r['utm_source'] ?: $r['source'] ?: ''),
        'status' => (string)(REQ_STATUS[$r['status'] ?? ''] ?? ($r['status'] ?? '')),
        'desejada' => $desired,
        'criado_em' => $r['created_at'] ?? now(),
    ];
}

function sheets_agent_row(array $u): array
{
    return [
        'id' => $u['id'] ?? '',
        'nome' => $u['name'] ?? '',
        'email' => $u['email'] ?? '',
        'telefone' => $u['phone'] ?? '',
        'situacao' => !empty($u['active']) ? 'Ativo' : 'Inativo',
        'criado_em' => $u['created_at'] ?? now(),
    ];
}

function sheets_supplier_row(array $s): array
{
    return [
        'id' => $s['id'] ?? '',
        'nome' => $s['name'] ?? '',
        'documento' => $s['cnpj'] ?? '',
        'produto' => $s['product_type'] ?? '',
        'cidade' => $s['city'] ?? '',
        'estado' => $s['state'] ?? '',
        'email' => $s['email'] ?? '',
        'telefone' => $s['phone'] ?? '',
        'criado_em' => $s['created_at'] ?? now(),
    ];
}

function sheets_finance_row(array $r): array
{
    $st = FINANCE_STATUS[$r['status'] ?? ''][0] ?? ($r['status'] ?? '');
    return [
        'id' => $r['id'] ?? '',
        'descricao' => $r['description'] ?? '',
        'valor' => (string)($r['amount'] ?? ''),
        'vencimento' => $r['due_date'] ?? '',
        'status' => (string)$st,
        'forma' => finance_pay_label($r['payment_method'] ?? ''),
        'cliente' => $r['client_name'] ?? ($r['agent_name'] ?? ''),
        'origem' => finance_source_label($r['source_type'] ?? null, $r['source_id'] ?? null),
        'criado_em' => $r['created_at'] ?? now(),
    ];
}
function sheets_load_appointment(string $tenantId, string $id): ?array
{
    return one("SELECT a.*, c.name client_name, c.phone client_phone, c.email client_email, s.name service_name
        FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
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

function drive_api(string $method, string $path, array $cfg, mixed $body = null): array
{
    return google_http($method, 'https://www.googleapis.com/drive/v3/'.$path, $body, $cfg['access_token']);
}

function sheets_file_meta(array $cfg, string $fileKey): array
{
    $files = is_array($cfg['files'] ?? null) ? $cfg['files'] : [];
    return is_array($files[$fileKey] ?? null) ? $files[$fileKey] : [];
}

function sheets_book_id(array $cfg, ?string $fileKey = null): string
{
    if ($fileKey) {
        $id = (string)(sheets_file_meta($cfg, $fileKey)['id'] ?? '');
        if ($id !== '') {
            return $id;
        }
    }
    return (string)($cfg['spreadsheet_id'] ?? '');
}

function sheets_tab_name(array $cfg, ?string $fileKey = null, string $fallback = 'Dados'): string
{
    if ($fileKey) {
        $name = trim((string)(sheets_file_meta($cfg, $fileKey)['sheet'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }
    return $fallback;
}

function drive_create_folder(array $cfg, string $name, ?string $parentId = null): array
{
    $body = [
        'name' => $name,
        'mimeType' => 'application/vnd.google-apps.folder',
    ];
    if ($parentId) {
        $body['parents'] = [$parentId];
    }
    return drive_api('POST', 'files?fields=id,name,webViewLink', $cfg, $body);
}

function drive_create_spreadsheet(array $cfg, string $name, string $parentId, string $tab): ?array
{
    $created = drive_api('POST', 'files?fields=id,name,webViewLink', $cfg, [
        'name' => $name,
        'mimeType' => 'application/vnd.google-apps.spreadsheet',
        'parents' => [$parentId],
    ]);
    $id = (string)($created['json']['id'] ?? '');
    if ($id === '') {
        return null;
    }
    $meta = sheets_api('GET', 'spreadsheets/'.rawurlencode($id).'?fields=spreadsheetUrl,sheets.properties', $cfg);
    $sheetId = $meta['json']['sheets'][0]['properties']['sheetId'] ?? 0;
    $current = (string)($meta['json']['sheets'][0]['properties']['title'] ?? '');
    if ($current !== $tab) {
        sheets_api('POST', 'spreadsheets/'.rawurlencode($id).':batchUpdate', $cfg, [
            'requests' => [[
                'updateSheetProperties' => [
                    'properties' => ['sheetId' => $sheetId, 'title' => $tab],
                    'fields' => 'title',
                ],
            ]],
        ]);
    }
    return [
        'id' => $id,
        'url' => (string)($created['json']['webViewLink'] ?? $meta['json']['spreadsheetUrl'] ?? ('https://docs.google.com/spreadsheets/d/'.$id)),
        'sheet' => $tab,
        'name' => $name,
    ];
}

function sheets_drive_ready(array $cfg): bool
{
    if (($cfg['drive_folder_id'] ?? '') === '') {
        return false;
    }
    $folders = is_array($cfg['folders'] ?? null) ? $cfg['folders'] : [];
    foreach (array_keys(google_drive_folders()) as $key) {
        if (($folders[$key] ?? '') === '') {
            return false;
        }
    }
    $files = is_array($cfg['files'] ?? null) ? $cfg['files'] : [];
    foreach (google_folder_files() as $group) {
        foreach (array_keys($group) as $fileKey) {
            if (($files[$fileKey]['id'] ?? '') === '') {
                return false;
            }
        }
    }
    return true;
}

function sheets_ensure_drive_tree(string $tenantId, array $tenant, array &$cfg): bool
{
    if (sheets_drive_ready($cfg)) {
        if (($cfg['spreadsheet_url'] ?? '') === '' && ($cfg['drive_folder_url'] ?? '') !== '') {
            $cfg['spreadsheet_url'] = $cfg['drive_folder_url'];
            sheets_save($tenantId, $cfg);
        }
        return true;
    }
    $rootName = 'FirestepCRM · '.($tenant['display_name'] ?: $tenant['business_name'] ?: 'CRM');
    if (($cfg['drive_folder_id'] ?? '') === '') {
        $root = drive_create_folder($cfg, $rootName);
        $rootId = (string)($root['json']['id'] ?? '');
        if ($rootId === '') {
            return false;
        }
        $cfg['drive_folder_id'] = $rootId;
        $cfg['drive_folder_url'] = (string)($root['json']['webViewLink'] ?? ('https://drive.google.com/drive/folders/'.$rootId));
        $cfg['spreadsheet_url'] = $cfg['drive_folder_url'];
        $cfg['folders'] = is_array($cfg['folders'] ?? null) ? $cfg['folders'] : [];
        $cfg['files'] = is_array($cfg['files'] ?? null) ? $cfg['files'] : [];
        sheets_save($tenantId, $cfg);
    }
    $folders = is_array($cfg['folders'] ?? null) ? $cfg['folders'] : [];
    foreach (google_drive_folders() as $key => $name) {
        if (($folders[$key] ?? '') !== '') {
            continue;
        }
        $made = drive_create_folder($cfg, $name, $cfg['drive_folder_id']);
        $fid = (string)($made['json']['id'] ?? '');
        if ($fid === '') {
            return false;
        }
        $folders[$key] = $fid;
        $cfg['folders'] = $folders;
        sheets_save($tenantId, $cfg);
    }
    $files = is_array($cfg['files'] ?? null) ? $cfg['files'] : [];
    $headerMap = [
        'agendamentos' => 'appointment',
        'solicitacoes' => 'request',
        'clientes' => 'client',
        'agentes' => 'agent',
        'fornecedores' => 'supplier',
        'servicos' => 'service',
        'financeiro_dashboard' => 'resumo',
        'financeiro_lancamentos' => 'finance',
        'financeiro_receber' => 'finance',
        'financeiro_faturado' => 'finance',
        'financeiro_pagar' => 'finance',
        'financeiro_relatorios' => 'resumo',
    ];
    foreach (google_folder_files() as $folderKey => $group) {
        $parent = (string)($folders[$folderKey] ?? '');
        if ($parent === '') {
            return false;
        }
        foreach ($group as $fileKey => $fileName) {
            if (($files[$fileKey]['id'] ?? '') !== '') {
                continue;
            }
            $tab = $fileName;
            $made = drive_create_spreadsheet($cfg, $fileName, $parent, $tab);
            if (!$made) {
                return false;
            }
            $files[$fileKey] = $made;
            $cfg['files'] = $files;
            sheets_save($tenantId, $cfg);
            $entity = $headerMap[$fileKey] ?? 'resumo';
            sheets_write_file($cfg, $fileKey, [sheets_headers()[$entity] ?? ['metrica', 'valor']]);
        }
    }
    $cfg['folders'] = $folders;
    $cfg['files'] = $files;
    $cfg['spreadsheet_url'] = $cfg['drive_folder_url'] ?: ($cfg['spreadsheet_url'] ?? '');
    sheets_save($tenantId, $cfg);
    return sheets_drive_ready($cfg);
}

function sheets_ensure_spreadsheet(string $tenantId, array $tenant, array &$cfg): bool
{
    return sheets_ensure_drive_tree($tenantId, $tenant, $cfg);
}

function sheets_write_file(array $cfg, string $fileKey, array $values, string $range = 'A1'): array
{
    $book = sheets_book_id($cfg, $fileKey);
    $sheet = sheets_tab_name($cfg, $fileKey);
    if ($book === '') {
        return ['ok' => false, 'code' => 0, 'json' => [], 'raw' => 'Arquivo Google ausente.'];
    }
    $encoded = rawurlencode("'".$sheet."'!".$range);
    return sheets_api('PUT', 'spreadsheets/'.rawurlencode($book).'/values/'.$encoded.'?valueInputOption=RAW', $cfg, [
        'range' => "'".$sheet."'!".$range,
        'majorDimension' => 'ROWS',
        'values' => $values,
    ]);
}

function sheets_clear_file(array $cfg, string $fileKey): array
{
    $book = sheets_book_id($cfg, $fileKey);
    $sheet = sheets_tab_name($cfg, $fileKey);
    if ($book === '') {
        return ['ok' => false];
    }
    return sheets_api('POST', 'spreadsheets/'.rawurlencode($book).'/values/'.rawurlencode("'".$sheet."'").':clear', $cfg, new stdClass());
}

function sheets_write_values(array $cfg, string $sheet, array $values, string $range = 'A1', ?string $fileKey = null): array
{
    if ($fileKey) {
        return sheets_write_file($cfg, $fileKey, $values, $range);
    }
    $book = sheets_book_id($cfg);
    $encoded = rawurlencode("'".$sheet."'!".$range);
    return sheets_api('PUT', 'spreadsheets/'.rawurlencode($book).'/values/'.$encoded.'?valueInputOption=RAW', $cfg, [
        'range' => "'".$sheet."'!".$range,
        'majorDimension' => 'ROWS',
        'values' => $values,
    ]);
}

function sheets_append_values(array $cfg, string $sheet, array $values, ?string $fileKey = null): array
{
    $book = sheets_book_id($cfg, $fileKey);
    $tab = $fileKey ? sheets_tab_name($cfg, $fileKey) : $sheet;
    $encoded = rawurlencode("'".$tab."'!A1");
    return sheets_api('POST', 'spreadsheets/'.rawurlencode($book).'/values/'.$encoded.':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', $cfg, [
        'values' => $values,
    ]);
}

function sheets_read_ids(array $cfg, string $sheet, ?string $fileKey = null): array
{
    $book = sheets_book_id($cfg, $fileKey);
    $tab = $fileKey ? sheets_tab_name($cfg, $fileKey) : $sheet;
    $encoded = rawurlencode("'".$tab."'!A:A");
    $res = sheets_api('GET', 'spreadsheets/'.rawurlencode($book).'/values/'.$encoded, $cfg);
    $map = [];
    foreach (($res['json']['values'] ?? []) as $i => $row) {
        if ($i === 0) continue;
        $id = (string)($row[0] ?? '');
        if ($id !== '') $map[$id] = $i + 1;
    }
    return $map;
}

function sheets_delete_row(array $cfg, string $sheet, int $rowNumber, ?string $fileKey = null): void
{
    $book = sheets_book_id($cfg, $fileKey);
    $tab = $fileKey ? sheets_tab_name($cfg, $fileKey) : $sheet;
    $meta = sheets_api('GET', 'spreadsheets/'.rawurlencode($book).'?fields=sheets.properties', $cfg);
    $sheetId = null;
    foreach (($meta['json']['sheets'] ?? []) as $sh) {
        if (($sh['properties']['title'] ?? '') === $tab) $sheetId = $sh['properties']['sheetId'] ?? null;
    }
    if ($sheetId === null) return;
    sheets_api('POST', 'spreadsheets/'.rawurlencode($book).':batchUpdate', $cfg, [
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
    return ['ok'=>$ok, 'message'=>$ok ? 'Sincronizado com a pasta do Google Drive.' : ($error ?: 'Falha na sincronização.')];
}

function push_google_sheets(string $tenantId, string $entity, string $action, ?string $id): void
{
    if (!$id) return;
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    if (!$tenant) return;
    $cfg = sheets_config($tenant);
    if (!sheets_connected($cfg) || !google_token_for($tenantId, $cfg)) return;
    if (!sheets_ensure_spreadsheet($tenantId, $tenant, $cfg)) return;
    $fileKey = sheets_entity_file($entity);
    $sheet = sheets_tab_name($cfg, $fileKey, sheets_titles()[$entity] ?? 'Dados');
    if (sheets_book_id($cfg, $fileKey) === '') return;
    if ($action === 'delete') {
        $ids = sheets_read_ids($cfg, $sheet, $fileKey);
        if (!empty($ids[$id])) sheets_delete_row($cfg, $sheet, $ids[$id], $fileKey);
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
    } elseif ($entity === 'request') {
        $item = one("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.id=? AND r.tenant_id=?", [$id, $tenantId]);
        $row = $item ? sheets_request_row($item) : null;
    } elseif ($entity === 'agent') {
        $item = one("SELECT * FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$id, $tenantId]);
        $row = $item ? sheets_agent_row($item) : null;
    } elseif ($entity === 'supplier') {
        $item = one('SELECT * FROM suppliers WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        $row = $item ? sheets_supplier_row($item) : null;
    }
    if (!$row) return;
    $ids = sheets_read_ids($cfg, $sheet, $fileKey);
    $line = sheets_line($entity, $row);
    if (!empty($ids[$id])) {
        $res = sheets_write_values($cfg, $sheet, [$line], 'A'.$ids[$id], $fileKey);
    } else {
        $res = sheets_append_values($cfg, $sheet, [$line], $fileKey);
    }
    sheets_finish($tenantId, $cfg, !empty($res['ok']), $res['ok'] ? null : substr($res['raw'] ?: 'Erro ao gravar no Drive.', 0, 240));
}

function sheets_resumo_rows(array $tenant, string $tenantId): array
{
    $values = [sheets_headers()['resumo']];
    if (function_exists('finance_overview')) {
        ensure_finance_schema();
        $ov = finance_overview($tenantId);
        $values[] = ['Empresa', (string)($tenant['display_name'] ?: $tenant['business_name'] ?: '')];
        $values[] = ['A receber', (string)($ov['receber'] ?? 0)];
        $values[] = ['Faturado', (string)($ov['faturado'] ?? 0)];
        $values[] = ['Previsto', (string)($ov['previsto'] ?? 0)];
        $values[] = ['Recebido', (string)($ov['recebido'] ?? 0)];
        $values[] = ['A pagar', (string)($ov['pagar'] ?? ($ov['repasse'] ?? 0))];
        $values[] = ['Atualizado em', now()];
    }
    return $values;
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
        return sheets_finish($tenantId, $cfg, false, 'Não foi possível criar a pasta no Google Drive.');
    }
    if (function_exists('coverage_ensure_schema')) {
        coverage_ensure_schema();
    }
    $sets = [
        'appointment' => ['agendamentos', array_map('sheets_appointment_row', all("SELECT a.*, c.name client_name, c.phone client_phone, c.email client_email, s.name service_name FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? ORDER BY a.starts_at", [$tenantId]))],
        'request' => ['solicitacoes', array_map('sheets_request_row', all("SELECT r.*, s.name service_name FROM requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? ORDER BY r.created_at", [$tenantId]))],
        'client' => ['clientes', array_map('sheets_client_row', all('SELECT * FROM clients WHERE tenant_id=? ORDER BY created_at', [$tenantId]))],
        'agent' => ['agentes', array_map('sheets_agent_row', all("SELECT * FROM users WHERE tenant_id=? AND role='user_agent' ORDER BY name", [$tenantId]))],
        'supplier' => ['fornecedores', array_map('sheets_supplier_row', all('SELECT * FROM suppliers WHERE tenant_id=? ORDER BY name', [$tenantId]))],
        'service' => ['servicos', array_map('sheets_service_row', all('SELECT * FROM services WHERE tenant_id=? ORDER BY name', [$tenantId]))],
    ];
    foreach ($sets as $entity => [$fileKey, $rows]) {
        $values = [sheets_headers()[$entity]];
        foreach ($rows as $row) $values[] = sheets_line($entity, $row);
        sheets_clear_file($cfg, $fileKey);
        $res = sheets_write_file($cfg, $fileKey, $values);
        if (empty($res['ok'])) {
            return sheets_finish($tenantId, $cfg, false, substr($res['raw'] ?: 'Falha ao enviar '.$fileKey.'.', 0, 240));
        }
    }
    if (function_exists('finance_query')) {
        ensure_finance_schema();
        $finMap = [
            'financeiro_lancamentos' => 'lancamentos',
            'financeiro_receber' => 'receber',
            'financeiro_faturado' => 'faturado',
            'financeiro_pagar' => 'pagar',
        ];
        foreach ($finMap as $fileKey => $page) {
            $values = [sheets_headers()['finance']];
            foreach (finance_query($tenantId, $page) as $row) {
                $values[] = sheets_line('finance', sheets_finance_row($row));
            }
            sheets_clear_file($cfg, $fileKey);
            $res = sheets_write_file($cfg, $fileKey, $values);
            if (empty($res['ok'])) {
                return sheets_finish($tenantId, $cfg, false, substr($res['raw'] ?: 'Falha ao enviar '.$fileKey.'.', 0, 240));
            }
        }
        $resumo = sheets_resumo_rows($tenant, $tenantId);
        foreach (['financeiro_dashboard', 'financeiro_relatorios'] as $fileKey) {
            sheets_clear_file($cfg, $fileKey);
            $res = sheets_write_file($cfg, $fileKey, $resumo);
            if (empty($res['ok'])) {
                return sheets_finish($tenantId, $cfg, false, substr($res['raw'] ?: 'Falha ao enviar '.$fileKey.'.', 0, 240));
            }
        }
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
        return ['ok'=>false,'message'=>'Google autenticado, mas a pasta no Drive não pôde ser criada.'];
    }
    sync_google_sheets_all($tenantId);
    return ['ok'=>true,'message'=>'Google conectado. A pasta do Drive já está com as 7 pastas e os arquivos do Financeiro.'];
}
