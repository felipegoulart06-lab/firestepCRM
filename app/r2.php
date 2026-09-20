<?php
declare(strict_types=1);

function r2_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS auto_backup boolean NOT NULL DEFAULT false');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS auto_backup_at timestamptz');
            return;
        }
        $existing = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('auto_backup', $existing, true)) {
            db()->exec('ALTER TABLE tenants ADD COLUMN auto_backup INTEGER DEFAULT 0');
        }
        if (!in_array('auto_backup_at', $existing, true)) {
            db()->exec('ALTER TABLE tenants ADD COLUMN auto_backup_at TEXT');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function r2_config(): array
{
    $account = env_str('R2_ACCOUNT_ID', '97ffef1ae71def38ec8915c7c530fbd8') ?: '97ffef1ae71def38ec8915c7c530fbd8';
    $endpoint = rtrim((string)(env_str('R2_ENDPOINT') ?: 'https://'.$account.'.r2.cloudflarestorage.com'), '/');
    return [
        'account' => $account,
        'endpoint' => $endpoint,
        'bucket' => env_str('R2_BUCKET', 'firestepcrm') ?: 'firestepcrm',
        'key' => env_str('R2_ACCESS_KEY_ID'),
        'secret' => env_str('R2_SECRET_ACCESS_KEY'),
        'region' => env_str('R2_REGION', 'auto') ?: 'auto',
    ];
}

function r2_ready(): bool
{
    $c = r2_config();
    return $c['key'] !== null && $c['key'] !== ''
        && $c['secret'] !== null && $c['secret'] !== ''
        && $c['bucket'] !== ''
        && $c['endpoint'] !== '';
}

function r2_enabled(array $tenant): bool
{
    $v = $tenant['auto_backup'] ?? 0;
    return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
}

function r2_cron_secret_ok(): bool
{
    $secret = env_str('CRON_SECRET');
    if ($secret === null || $secret === '') {
        return false;
    }
    $got = request_header('Authorization');
    if (str_starts_with(strtolower($got), 'bearer ')) {
        $got = trim(substr($got, 7));
    }
    if ($got === '') {
        $got = request_header('X-Cron-Secret');
    }
    return $got !== '' && hash_equals($secret, $got);
}

function r2_redact(array $row): array
{
    foreach (['password_hash', 'password', 'current_password', 'secret_access_key', 'client_secret'] as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            $row[$key] = '[redacted]';
        }
    }
    return $row;
}

function r2_object_key(string $tenantId, ?string $at = null): string
{
    $stamp = $at ?: gmdate('Y-m-d\TH-i-s\Z');
    return 'tenants/'.$tenantId.'/crm-'.$stamp.'.json';
}

function r2_uri_encode(string $value, bool $encodeSlash = true): string
{
    $encoded = rawurlencode($value);
    $encoded = str_replace(['%7E', '+'], ['~', '%20'], $encoded);
    if (!$encodeSlash) {
        $encoded = str_replace('%2F', '/', $encoded);
    }
    return $encoded;
}

function r2_canonical_path(string $bucket, string $key): string
{
    $path = '/'.r2_uri_encode($bucket, false);
    if ($key !== '') {
        $path .= '/'.r2_uri_encode($key, false);
    }
    return $path;
}

function r2_sign(string $method, string $path, string $query, array $headers, string $payloadHash, array $cfg): string
{
    ksort($headers);
    $signed = implode(';', array_keys($headers));
    $canonicalHeaders = '';
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= $name.':'.trim((string)$value)."\n";
    }
    $canonical = $method."\n".$path."\n".$query."\n".$canonicalHeaders."\n".$signed."\n".$payloadHash;
    $date = $headers['x-amz-date'];
    $short = substr($date, 0, 8);
    $scope = $short.'/'.$cfg['region'].'/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n".$date."\n".$scope."\n".hash('sha256', $canonical);
    $kDate = hash_hmac('sha256', $short, 'AWS4'.$cfg['secret'], true);
    $kRegion = hash_hmac('sha256', $cfg['region'], $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    return 'AWS4-HMAC-SHA256 Credential='.$cfg['key'].'/'.$scope.', SignedHeaders='.$signed.', Signature='.$signature;
}

function r2_request(string $method, string $key = '', ?string $body = null, array $query = []): array
{
    $cfg = r2_config();
    if (!r2_ready()) {
        return [0, 'R2 não configurado.'];
    }
    $payload = $body ?? '';
    $payloadHash = hash('sha256', $payload);
    $host = (string)parse_url($cfg['endpoint'], PHP_URL_HOST);
    $amzDate = gmdate('Ymd\THis\Z');
    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    if ($body !== null) {
        $headers['content-type'] = 'application/json';
    }
    $queryParts = [];
    ksort($query);
    foreach ($query as $qName => $qValue) {
        $queryParts[] = r2_uri_encode((string)$qName).'='.r2_uri_encode((string)$qValue);
    }
    $queryString = implode('&', $queryParts);
    $path = r2_canonical_path($cfg['bucket'], $key);
    $auth = r2_sign($method, $path, $queryString, $headers, $payloadHash, $cfg);
    $url = $cfg['endpoint'].$path;
    if ($queryString !== '') {
        $url .= '?'.$queryString;
    }
    $curlHeaders = ['Authorization: '.$auth];
    foreach ($headers as $name => $value) {
        if ($name === 'host') {
            continue;
        }
        $curlHeaders[] = $name.': '.$value;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $curlHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ca = env_str('R2_CAINFO')
        ?: (string)ini_get('curl.cainfo')
        ?: (string)ini_get('openssl.cafile');
    if ($ca === '' || !is_file($ca)) {
        $localCa = ROOT . '/storage/cacert.pem';
        $ca = is_file($localCa) ? $localCa : '';
    }
    if ($ca !== '' && is_file($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }
    $out = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($out === false) {
        return [0, $err !== '' ? $err : 'Falha de rede no R2.'];
    }
    return [$code, (string)$out];
}

function r2_put_json(string $key, array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return [0, 'JSON inválido.'];
    }
    return r2_request('PUT', $key, $json);
}

function r2_ping(): array
{
    [$code, $body] = r2_request('GET', '', null, ['list-type' => '2', 'max-keys' => '1']);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'code' => $code];
    }
    return ['ok' => false, 'code' => $code, 'error' => trim(strip_tags($body))];
}

function r2_tables_with_tenant(): array
{
    $pdo = db();
    if (is_pgsql()) {
        $stmt = $pdo->query("SELECT table_name FROM information_schema.columns WHERE table_schema='public' AND column_name='tenant_id' ORDER BY table_name");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }
    $names = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    $out = [];
    foreach ($names as $name) {
        $cols = array_column($pdo->query('PRAGMA table_info('.$name.')')->fetchAll(), 'name');
        if (in_array('tenant_id', $cols, true)) {
            $out[] = $name;
        }
    }
    return $out;
}

function r2_snapshot(string $tenantId): array
{
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]) ?: [];
    $data = [
        'generated_at' => gmdate('c'),
        'tenant' => r2_redact($tenant),
        'tables' => [],
    ];
    foreach (r2_tables_with_tenant() as $table) {
        if (!preg_match('/^[a-z0-9_]+$/i', (string)$table)) {
            continue;
        }
        try {
            $rows = all('SELECT * FROM '.$table.' WHERE tenant_id=?', [$tenantId]);
        } catch (Throwable $e) {
            $data['tables'][$table] = ['error' => $e->getMessage()];
            continue;
        }
        $data['tables'][$table] = array_map('r2_redact', $rows);
    }
    return $data;
}

function r2_backup_tenant(string $tenantId): array
{
    r2_ensure_schema();
    if (!r2_ready()) {
        return ['ok' => false, 'error' => 'R2 não configurado.'];
    }
    $key = r2_object_key($tenantId);
    $snap = r2_snapshot($tenantId);
    [$code, $body] = r2_put_json($key, $snap);
    if ($code < 200 || $code >= 300) {
        return ['ok' => false, 'code' => $code, 'error' => trim(strip_tags($body)), 'key' => $key];
    }
    q('UPDATE tenants SET auto_backup_at=? WHERE id=?', [now(), $tenantId]);
    return ['ok' => true, 'code' => $code, 'key' => $key];
}

function r2_backup_all_tenants(): array
{
    r2_ensure_schema();
    $out = [];
    foreach (all("SELECT * FROM tenants WHERE status='ACTIVE'") as $tenant) {
        if (!r2_enabled($tenant)) {
            continue;
        }
        $out[] = ['tenant_id' => $tenant['id']] + r2_backup_tenant($tenant['id']);
    }
    return $out;
}
