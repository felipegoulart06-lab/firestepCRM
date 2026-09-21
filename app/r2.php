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
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS auto_backup_hashes jsonb NOT NULL DEFAULT '{}'::jsonb");
            return;
        }
        $existing = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('auto_backup', $existing, true)) {
            db()->exec('ALTER TABLE tenants ADD COLUMN auto_backup INTEGER DEFAULT 0');
        }
        if (!in_array('auto_backup_at', $existing, true)) {
            db()->exec('ALTER TABLE tenants ADD COLUMN auto_backup_at TEXT');
        }
        if (!in_array('auto_backup_hashes', $existing, true)) {
            db()->exec("ALTER TABLE tenants ADD COLUMN auto_backup_hashes TEXT DEFAULT '{}'");
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
    $got = request_header('Authorization');
    if (str_starts_with(strtolower($got), 'bearer ')) {
        $got = trim(substr($got, 7));
    }
    if ($got === '') {
        $got = request_header('X-Cron-Secret');
    }
    if ($secret !== null && $secret !== '' && $got !== '' && hash_equals($secret, $got)) {
        return true;
    }
    // Job da Vercel (03:00 Brasília = 06:00 UTC) envia este header.
    return request_header('x-vercel-cron') === '1';
}

function r2_csv_id(string $key): string
{
    $parts = explode('/', str_replace('\\', '/', $key));
    $n = count($parts);
    if ($n >= 2) {
        return $parts[$n - 2].'/'.$parts[$n - 1];
    }
    return $key;
}

function r2_csv_fingerprint(string $csv): string
{
    return hash('sha256', $csv);
}

function r2_csv_has_data(string $csv): bool
{
    $raw = (string)preg_replace('/^\xEF\xBB\xBF/', '', $csv);
    $nl = strpos($raw, "\r\n");
    $skip = 2;
    if ($nl === false) {
        $nl = strpos($raw, "\n");
        $skip = 1;
    }
    if ($nl === false) {
        return false;
    }
    return trim(substr($raw, $nl + $skip)) !== '';
}

function r2_hashes_of(array $tenant): array
{
    $raw = $tenant['auto_backup_hashes'] ?? [];
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function r2_should_put_csv(string $csv, string $id, array $prevHashes): bool
{
    if (!r2_csv_has_data($csv)) {
        return false;
    }
    $fp = r2_csv_fingerprint($csv);
    return ($prevHashes[$id] ?? '') !== $fp;
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

function r2_backup_date(?string $date = null): string
{
    if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    return (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
}

function r2_tenant_folder(array $tenant): string
{
    $slug = trim((string)($tenant['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugify((string)($tenant['display_name'] ?: $tenant['business_name'] ?: $tenant['name'] ?: $tenant['id'] ?? 'conta'));
    }
    $slug = str_replace(['\\', '/', '..'], '-', $slug);
    return $slug !== '' ? $slug : (string)($tenant['id'] ?? 'conta');
}

function r2_csv_key(string $tenantFolder, string $date, string $section, string $file): string
{
    return 'backups/'.$tenantFolder.'/'.$date.'/'.$section.'/'.$file;
}

function r2_csv_cell(mixed $value): string
{
    if ($value === null || $value === false) {
        return '';
    }
    if ($value === true) {
        $value = '1';
    }
    $s = str_replace("\0", '', (string)$value);
    if (strpbrk($s, ";\"\r\n") !== false) {
        return '"'.str_replace('"', '""', $s).'"';
    }
    return $s;
}

function r2_csv(array $headers, array $lines): string
{
    $out = "\xEF\xBB\xBF".implode(';', array_map('r2_csv_cell', $headers))."\r\n";
    foreach ($lines as $line) {
        $cells = [];
        foreach ($line as $cell) {
            $cells[] = r2_csv_cell($cell);
        }
        $out .= implode(';', $cells)."\r\n";
    }
    return $out;
}

function r2_fmt_when(?string $value, bool $withTime = true): string
{
    if (!$value) {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function r2_fmt_money(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (function_exists('money')) {
        return money((float)$value);
    }
    return number_format((float)$value, 2, ',', '.');
}

function r2_try_all(string $sql, array $params = []): array
{
    try {
        return all($sql, $params);
    } catch (Throwable $e) {
        return [];
    }
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

function r2_request(string $method, string $key = '', ?string $body = null, array $query = [], string $contentType = 'application/octet-stream'): array
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
        $headers['content-type'] = $contentType;
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
        CURLOPT_TIMEOUT => 90,
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

function r2_put_csv(string $key, string $csv): array
{
    return r2_request('PUT', $key, $csv, [], 'text/csv');
}

function r2_ping(): array
{
    [$code, $body] = r2_request('GET', '', null, ['list-type' => '2', 'max-keys' => '1']);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'code' => $code];
    }
    return ['ok' => false, 'code' => $code, 'error' => trim(strip_tags($body))];
}

function r2_backup_prepare(string $tenantId): void
{
    r2_ensure_schema();
    require_once __DIR__ . '/finance.php';
    if (function_exists('coverage_ensure_schema')) {
        coverage_ensure_schema();
    }
    if (function_exists('services_ensure_schema')) {
        services_ensure_schema();
    }
    if (function_exists('appointment_commission_ensure_schema')) {
        appointment_commission_ensure_schema();
    }
    if (function_exists('ensure_finance_schema')) {
        ensure_finance_schema();
    }
}

function r2_finance_lines(string $tenantId, string $page): array
{
    $rows = [];
    if (function_exists('finance_query')) {
        $rows = finance_query($tenantId, $page);
    } else {
        $kind = match ($page) {
            'pagar' => 'payable',
            'receber', 'faturado' => 'receivable',
            default => null,
        };
        $sql = 'SELECT * FROM finance_entries WHERE tenant_id=?';
        $p = [$tenantId];
        if ($kind) {
            $sql .= ' AND kind=?';
            $p[] = $kind;
        }
        if ($page === 'faturado') {
            $sql .= " AND status='billed'";
        } elseif ($page === 'receber') {
            $sql .= " AND status='open'";
        } elseif ($page === 'pagar') {
            $sql .= " AND status IN ('open','paid')";
        } elseif ($page === 'lancamentos') {
            $sql .= " AND status!='cancelled'";
        }
        $rows = r2_try_all($sql.' ORDER BY created_at DESC', $p);
    }
    $lines = [];
    foreach ($rows as $row) {
        $status = $row['status'] ?? '';
        if (defined('FINANCE_STATUS') && isset(FINANCE_STATUS[$status][0])) {
            $status = FINANCE_STATUS[$status][0];
        }
        $method = function_exists('finance_pay_label') ? finance_pay_label($row['payment_method'] ?? '') : (string)($row['payment_method'] ?? '');
        $origem = function_exists('finance_source_label')
            ? finance_source_label($row['source_type'] ?? null, $row['source_id'] ?? null)
            : (string)($row['source_type'] ?? '');
        $flow = ($row['flow'] ?? '') === 'out' ? 'Saída' : (($row['flow'] ?? '') === 'in' ? 'Entrada' : (string)($row['flow'] ?? ''));
        $lines[] = [
            (string)($row['description'] ?? ''),
            $origem,
            (string)($row['client_name'] ?? ''),
            (string)($row['agent_name'] ?? ''),
            $method,
            $flow,
            r2_fmt_money($row['amount'] ?? 0),
            r2_fmt_when($row['due_date'] ?? null, false),
            r2_fmt_when($row['paid_at'] ?? null, false),
            $status,
            (string)($row['notes'] ?? ''),
        ];
    }
    return $lines;
}

function r2_backup_files(array $tenant): array
{
    $tid = (string)$tenant['id'];
    $date = r2_backup_date();
    $folder = r2_tenant_folder($tenant);
    $financeHeaders = ['Descrição', 'Origem', 'Cliente', 'Agente', 'Forma', 'Fluxo', 'Valor', 'Vencimento', 'Pagamento', 'Status', 'Observações'];

    $appointments = r2_try_all(
        "SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, s.name service_name, s.price service_price, s.price_kind, u.name agent_name
         FROM appointments a
         LEFT JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
         LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
         LEFT JOIN users u ON u.id=a.commission_agent_id AND u.tenant_id=a.tenant_id
         WHERE a.tenant_id=?
         ORDER BY a.starts_at",
        [$tid]
    );
    $apptLines = [];
    foreach ($appointments as $a) {
        $reserva = function_exists('appointment_reserva_label') ? appointment_reserva_label($a) : (string)($a['reserva_n'] ?? '');
        $valor = function_exists('appointment_value_label') ? appointment_value_label($a) : r2_fmt_money($a['service_price'] ?? $a['price'] ?? 0);
        $tipo = function_exists('appointment_type_label') ? appointment_type_label($a) : (string)($a['visit_type'] ?? '');
        $status = APPT_STATUS[$a['status'] ?? ''][0] ?? (string)($a['status'] ?? '');
        $apptLines[] = [
            $reserva,
            r2_fmt_when($a['starts_at'] ?? null, false),
            substr((string)($a['starts_at'] ?? ''), 11, 5),
            substr((string)($a['ends_at'] ?? ''), 11, 5),
            (string)($a['client_name'] ?? ''),
            (string)($a['client_phone'] ?: $a['client_whatsapp'] ?: ''),
            (string)($a['agent_name'] ?? ''),
            (string)($a['service_name'] ?? ''),
            $valor,
            $tipo,
            $status,
            (string)($a['notes'] ?? ''),
        ];
    }

    $requests = r2_try_all(
        "SELECT r.*, s.name service_name FROM requests r
         LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id
         WHERE r.tenant_id=? ORDER BY r.created_at",
        [$tid]
    );
    $reqLines = [];
    foreach ($requests as $r) {
        $reqLines[] = [
            r2_fmt_when($r['created_at'] ?? null),
            (string)($r['name'] ?? ''),
            (string)($r['phone'] ?: $r['whatsapp'] ?: ''),
            (string)($r['email'] ?? ''),
            (string)($r['service_name'] ?? ''),
            r2_fmt_when($r['desired_date'] ?? null, false),
            (string)($r['source'] ?? ''),
            REQ_STATUS[$r['status'] ?? ''] ?? (string)($r['status'] ?? ''),
            (string)($r['message'] ?? ''),
        ];
    }

    $clients = r2_try_all('SELECT * FROM clients WHERE tenant_id=? ORDER BY name', [$tid]);
    $clientLines = [];
    foreach ($clients as $c) {
        $clientLines[] = [
            (string)($c['name'] ?? ''),
            (string)($c['trade_name'] ?? ''),
            (string)($c['contact_name'] ?? ''),
            (string)($c['phone'] ?? ''),
            (string)($c['whatsapp'] ?? ''),
            (string)($c['email'] ?? ''),
            (string)($c['cpf'] ?? $c['document'] ?? ''),
            (string)($c['address'] ?? ''),
            (string)($c['city'] ?? ''),
            (string)($c['state'] ?? ''),
            (string)($c['cep'] ?? ''),
            ($c['status'] ?? '') === 'ACTIVE' ? 'Ativo' : (string)($c['status'] ?? ''),
            r2_fmt_when($c['created_at'] ?? null, false),
        ];
    }

    $agents = r2_try_all("SELECT id,name,username,email,phone,document_kind,cpf,active,last_login_at,created_at FROM users WHERE tenant_id=? AND role='user_agent' ORDER BY name", [$tid]);
    $agentLines = [];
    foreach ($agents as $u) {
        $active = $u['active'] ?? 1;
        $agentLines[] = [
            (string)($u['name'] ?? ''),
            (string)($u['username'] ?? ''),
            (string)($u['document_kind'] ?? ''),
            (string)($u['cpf'] ?? ''),
            (string)($u['email'] ?? ''),
            (string)($u['phone'] ?? ''),
            ($active === 0 || $active === '0' || $active === false || $active === 'f') ? 'Inativo' : 'Ativo',
            r2_fmt_when($u['last_login_at'] ?? null),
            r2_fmt_when($u['created_at'] ?? null, false),
        ];
    }

    $suppliers = r2_try_all('SELECT * FROM suppliers WHERE tenant_id=? ORDER BY name', [$tid]);
    $supLines = [];
    foreach ($suppliers as $s) {
        $supLines[] = [
            (string)($s['name'] ?? ''),
            (string)($s['document_kind'] ?? ''),
            (string)($s['cnpj'] ?? $s['document'] ?? ''),
            (string)($s['product_type'] ?? ''),
            (string)($s['contact_name'] ?? ''),
            (string)($s['phone'] ?? ''),
            (string)($s['email'] ?? ''),
            (string)($s['address'] ?? ''),
            (string)($s['city'] ?? ''),
            (string)($s['state'] ?? ''),
            (string)($s['cep'] ?? ''),
            (string)($s['notes'] ?? ''),
        ];
    }

    $services = r2_try_all('SELECT * FROM services WHERE tenant_id=? ORDER BY name', [$tid]);
    $svcLines = [];
    foreach ($services as $s) {
        $preco = function_exists('service_price_label') ? service_price_label($s) : r2_fmt_money($s['price'] ?? 0);
        $svcLines[] = [
            (string)($s['name'] ?? ''),
            (string)($s['category'] ?? ''),
            (string)($s['duration_minutes'] ?? ''),
            $preco,
            (string)($s['location_type'] ?? ''),
            (string)($s['location_note'] ?? ''),
            ($s['status'] ?? '') === 'ACTIVE' ? 'Ativo' : (string)($s['status'] ?? ''),
            (string)($s['description'] ?? ''),
        ];
    }

    return [
        [r2_csv_key($folder, $date, 'Agendamentos', 'agendamentos.csv'), r2_csv(['N° reserva', 'Data', 'Início', 'Fim', 'Cliente', 'Telefone', 'Agente', 'Serviço', 'Valor', 'Tipo', 'Status', 'Observações'], $apptLines)],
        [r2_csv_key($folder, $date, 'Solicitações', 'solicitacoes.csv'), r2_csv(['Data', 'Cliente', 'Telefone', 'E-mail', 'Serviço', 'Data desejada', 'Origem', 'Status', 'Mensagem'], $reqLines)],
        [r2_csv_key($folder, $date, 'Clientes', 'clientes.csv'), r2_csv(['Nome', 'Nome fantasia', 'Contato', 'Telefone', 'WhatsApp', 'E-mail', 'Documento', 'Endereço', 'Cidade', 'UF', 'CEP', 'Status', 'Cadastro'], $clientLines)],
        [r2_csv_key($folder, $date, 'Agentes', 'agentes.csv'), r2_csv(['Nome', 'Usuário', 'Tipo documento', 'Documento', 'E-mail', 'Telefone', 'Status', 'Último acesso', 'Cadastro'], $agentLines)],
        [r2_csv_key($folder, $date, 'Fornecedores', 'fornecedores.csv'), r2_csv(['Nome', 'Tipo documento', 'Documento', 'Produto', 'Contato', 'Telefone', 'E-mail', 'Endereço', 'Cidade', 'UF', 'CEP', 'Observações'], $supLines)],
        [r2_csv_key($folder, $date, 'Serviços', 'servicos.csv'), r2_csv(['Serviço', 'Categoria', 'Duração', 'Preço', 'Local', 'Observação local', 'Status', 'Descrição'], $svcLines)],
        [r2_csv_key($folder, $date, 'financeiro', 'contas-a-pagar.csv'), r2_csv($financeHeaders, r2_finance_lines($tid, 'pagar'))],
        [r2_csv_key($folder, $date, 'financeiro', 'contas-a-receber.csv'), r2_csv($financeHeaders, r2_finance_lines($tid, 'receber'))],
        [r2_csv_key($folder, $date, 'financeiro', 'faturado.csv'), r2_csv($financeHeaders, r2_finance_lines($tid, 'faturado'))],
        [r2_csv_key($folder, $date, 'financeiro', 'lancamentos.csv'), r2_csv($financeHeaders, r2_finance_lines($tid, 'lancamentos'))],
    ];
}

function r2_backup_tenant(string $tenantId): array
{
    r2_backup_prepare($tenantId);
    if (!r2_ready()) {
        return ['ok' => false, 'error' => 'R2 não configurado.'];
    }
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    if (!$tenant) {
        return ['ok' => false, 'error' => 'Conta não encontrada.'];
    }
    $files = r2_backup_files($tenant);
    $hashes = r2_hashes_of($tenant);
    $uploaded = [];
    $skipped = [];
    $errors = [];
    foreach ($files as [$key, $csv]) {
        $id = r2_csv_id($key);
        if (!r2_should_put_csv($csv, $id, $hashes)) {
            $skipped[] = $id;
            continue;
        }
        [$code, $body] = r2_put_csv($key, $csv);
        if ($code >= 200 && $code < 300) {
            $uploaded[] = $key;
            $hashes[$id] = r2_csv_fingerprint($csv);
            continue;
        }
        $errors[] = ['key' => $key, 'code' => $code, 'error' => trim(strip_tags((string)$body))];
    }
    try {
        q('UPDATE tenants SET auto_backup_at=?, auto_backup_hashes=? WHERE id=?', [
            now(),
            json_encode($hashes, JSON_UNESCAPED_UNICODE),
            $tenantId,
        ]);
    } catch (Throwable $e) {
        q('UPDATE tenants SET auto_backup_at=? WHERE id=?', [now(), $tenantId]);
    }
    return [
        'ok' => $errors === [],
        'folder' => 'backups/'.r2_tenant_folder($tenant).'/'.r2_backup_date(),
        'files' => count($uploaded),
        'uploaded' => $uploaded,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

function r2_backup_all_tenants(): array
{
    r2_ensure_schema();
    $out = [];
    foreach (all("SELECT * FROM tenants WHERE status='ACTIVE'") as $tenant) {
        if (!r2_enabled($tenant)) {
            continue;
        }
        $out[] = ['tenant_id' => $tenant['id'], 'tenant' => r2_tenant_folder($tenant)] + r2_backup_tenant($tenant['id']);
    }
    return $out;
}
