<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const VIEWS = ROOT . '/views';

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
require_once __DIR__ . '/security.php';

function env_str(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return trim((string)$value, " \t\n\r\0\x0B\"'");
}

function cron_secret_ok(): bool
{
    $secret = (string)(env_str('CRON_SECRET') ?? '');
    if ($secret === '') {
        return false;
    }
    $got = '';
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (stripos($auth, 'Bearer ') === 0) {
        $got = trim(substr($auth, 7));
    }
    if ($got === '') {
        $got = (string)($_GET['token'] ?? '');
    }
    return $got !== '' && hash_equals($secret, $got);
}

function load_env_file(?string $path = null): void
{
    $path ??= ROOT . '/.env';
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key === '' || env_str($key) !== null) {
            continue;
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

load_env_file();

function is_vercel(): bool
{
    return env_str('VERCEL') === '1' || env_str('VERCEL_ENV') !== null;
}

function storage_dir(): string
{
    static $dir = null;
    if ($dir) {
        return $dir;
    }
    $dir = is_vercel() ? '/tmp/firestepcrm' : (ROOT . '/storage');
    foreach ([$dir, $dir . '/sessions'] as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
    return $dir;
}

function database_url(): ?string
{
    foreach (['DATABASE_URL', 'POSTGRES_URL', 'POSTGRES_PRISMA_URL', 'SUPABASE_DB_URL', 'POSTGRES_URL_NON_POOLING'] as $key) {
        $url = env_str($key);
        if ($url) {
            return $url;
        }
    }
    return null;
}

function missing_database_config(): void
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Configurar banco</title>';
    echo '<style>body{font:16px/1.5 Inter,Segoe UI,sans-serif;background:#f6f7f9;color:#101828;margin:0;display:grid;place-items:center;min-height:100vh;padding:24px}main{max-width:560px;background:#fff;border:1px solid #d9dee7;border-radius:8px;padding:28px 32px}h1{font-size:22px;margin:0 0 12px}ol{padding-left:20px}code{background:#f2f4f7;padding:1px 6px;border-radius:4px}</style></head><body><main>';
    echo '<h1>Falta a conexão com o banco</h1>';
    echo '<p>Na Vercel o FirestepCRM precisa do Postgres do Supabase. Defina a variável <code>DATABASE_URL</code> e publique de novo.</p>';
    echo '<ol>';
    echo '<li>Abra o projeto na Vercel → <b>Settings</b> → <b>Environment Variables</b>.</li>';
    echo '<li>Adicione <code>DATABASE_URL</code> nos ambientes Production, Preview e Development.</li>';
    echo '<li>Use a URI do <b>Transaction pooler</b> do Supabase (porta <code>6543</code>), por exemplo:<br><code>postgresql://postgres.REF:SENHA@aws-0-sa-east-1.pooler.supabase.com:6543/postgres</code></li>';
    echo '<li>Em <b>Deployments</b>, faça <b>Redeploy</b> (sem cache).</li>';
    echo '</ol>';
    echo '<p>No Supabase: <b>Project Settings → Database → Connection string → URI</b>. Aplique também as migrations da pasta <code>supabase/migrations</code>.</p>';
    echo '</main></body></html>';
    exit;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $url = database_url();
    if (env_str('FIRESTEP_SQLITE')) {
        $url = null;
    }
    if ($url) {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException('DATABASE_URL inválida.');
        }
        $dbName = ltrim((string)($parts['path'] ?? '/postgres'), '/');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=require;connect_timeout=5;options=--client-encoding=UTF8',
            $parts['host'],
            $parts['port'] ?? '5432',
            $dbName !== '' ? $dbName : 'postgres'
        );
        if (str_contains((string)$parts['host'], 'pooler.supabase.com') && (string)($parts['port'] ?? '5432') === '5432') {
            $dsn = sprintf(
                'pgsql:host=%s;port=6543;dbname=%s;sslmode=require;connect_timeout=5',
                $parts['host'],
                $dbName !== '' ? $dbName : 'postgres'
            );
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        if (($parts['port'] ?? '') === '6543' || str_contains($url, 'pgbouncer')) {
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        $pdo = new PDO($dsn, urldecode((string)($parts['user'] ?? 'postgres')), urldecode((string)($parts['pass'] ?? '')), $options);
        users_ensure_roles($pdo);
        require_once __DIR__ . '/coverage.php';
        coverage_ensure_schema();
        services_ensure_schema();
        return $pdo;
    }

    if (is_vercel()) {
        missing_database_config();
    }

    $path = env_str('FIRESTEP_SQLITE') ?: (storage_dir() . '/nexo.sqlite');
    $fresh = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path, null, null, $options);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    migrate_database($pdo);
    require_once __DIR__ . '/coverage.php';
    coverage_ensure_schema();
    if ($fresh || !$pdo->query('SELECT 1 FROM users LIMIT 1')->fetch()) {
        require __DIR__ . '/seed.php';
        nexo_seed($pdo);
    }
    users_ensure_roles($pdo);
    return $pdo;
}

function is_pgsql(): bool
{
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
}

function sql_true(string $column): string
{
    return is_pgsql() ? $column . ' IS TRUE' : $column . '=1';
}

function sql_false(string $column): string
{
    return is_pgsql() ? $column . ' IS FALSE' : $column . '=0';
}

function sql_not_blank(string $column): string
{
    return is_pgsql() ? $column . ' IS NOT NULL' : "COALESCE($column,'')!=''";
}

function empty_to_null(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function br_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string)$value) ?? '';
}

function br_doc_kind_from_value(?string $value): string
{
    $len = strlen(br_digits($value));
    if ($len === 14) {
        return 'cnpj';
    }
    if ($len === 11) {
        return 'cpf';
    }
    return '';
}

function format_br_document(?string $value, ?string $kind = null): string
{
    $d = br_digits($value);
    $kind = $kind ?: br_doc_kind_from_value($d);
    if ($kind === 'cpf') {
        $d = substr($d, 0, 11);
        $out = substr($d, 0, min(3, strlen($d)));
        if (strlen($d) > 3) {
            $out .= '.' . substr($d, 3, min(3, strlen($d) - 3));
        }
        if (strlen($d) > 6) {
            $out .= '.' . substr($d, 6, min(3, strlen($d) - 6));
        }
        if (strlen($d) > 9) {
            $out .= '-' . substr($d, 9, 2);
        }
        return $out;
    }
    if ($kind === 'cnpj') {
        $d = substr($d, 0, 14);
        $out = substr($d, 0, min(2, strlen($d)));
        if (strlen($d) > 2) {
            $out .= '.' . substr($d, 2, min(3, strlen($d) - 2));
        }
        if (strlen($d) > 5) {
            $out .= '.' . substr($d, 5, min(3, strlen($d) - 5));
        }
        if (strlen($d) > 8) {
            $out .= '/' . substr($d, 8, min(4, strlen($d) - 8));
        }
        if (strlen($d) > 12) {
            $out .= '-' . substr($d, 12, 2);
        }
        return $out;
    }
    return (string)$value;
}

function parse_br_document(?string $kind, ?string $value, bool $required = true): array
{
    $kind = strtolower(trim((string)$kind));
    $digits = br_digits($value);
    if ($kind !== 'cpf' && $kind !== 'cnpj') {
        return [false, null, 'Selecione se o documento é CPF ou CNPJ.'];
    }
    if ($digits === '') {
        if ($required) {
            return [false, null, $kind === 'cpf' ? 'Informe o CPF no formato 000.000.000-00.' : 'Informe o CNPJ no formato 00.000.000/0001-00.'];
        }
        return [true, null, null];
    }
    if ($kind === 'cpf' && strlen($digits) !== 11) {
        return [false, null, 'CPF deve ter 11 dígitos: 000.000.000-00.'];
    }
    if ($kind === 'cnpj' && strlen($digits) !== 14) {
        return [false, null, 'CNPJ deve ter 14 dígitos: 00.000.000/0001-00.'];
    }
    return [true, format_br_document($digits, $kind), null];
}

function br_document_fields(string $inputName, ?string $value = null, bool $required = true, string $kindName = 'document_kind', array $fill = []): void
{
    $kind = strtolower((string)($fill[$kindName] ?? $_POST[$kindName] ?? br_doc_kind_from_value($value)));
    if ($kind !== 'cpf' && $kind !== 'cnpj') {
        $kind = '';
    }
    $rawNumber = $fill[$inputName] ?? null;
    $shown = $rawNumber !== null && $rawNumber !== ''
        ? (string)$rawNumber
        : ($value ? format_br_document($value, $kind ?: null) : '');
    $placeholder = $kind === 'cnpj' ? '00.000.000/0001-00' : ($kind === 'cpf' ? '000.000.000-00' : 'Selecione CPF ou CNPJ');
    ?>
<div data-doc-group>
  <label class="label">Tipo de documento</label>
  <select class="select js-doc-kind" name="<?= e($kindName) ?>" <?= $required ? 'required' : '' ?>>
    <option value="">Selecione</option>
    <option value="cpf" <?= $kind === 'cpf' ? 'selected' : '' ?>>CPF</option>
    <option value="cnpj" <?= $kind === 'cnpj' ? 'selected' : '' ?>>CNPJ</option>
  </select>
</div>
<div data-doc-group-number>
  <label class="label js-doc-label"><?= $kind === 'cnpj' ? 'CNPJ' : ($kind === 'cpf' ? 'CPF' : 'Número do documento') ?></label>
  <input class="input js-doc-number" name="<?= e($inputName) ?>" value="<?= e($shown) ?>" inputmode="numeric" autocomplete="off" placeholder="<?= e($placeholder) ?>" maxlength="18" <?= $required ? 'required data-required="1"' : '' ?>>
</div>
    <?php
}

function sql_lit_bool(bool $value): string
{
    if (is_pgsql()) {
        return $value ? 'TRUE' : 'FALSE';
    }
    return $value ? '1' : '0';
}

function db_bool(mixed $value): mixed
{
    $on = !empty($value) && $value !== '0' && $value !== 'f' && $value !== false;
    if (is_pgsql()) {
        return $on ? 'true' : 'false';
    }
    return $on ? 1 : 0;
}

function normalize_row(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    foreach ($row as $key => $value) {
        if ($value === 't' || $value === true) {
            $row[$key] = 1;
        } elseif ($value === 'f' || $value === false) {
            $row[$key] = 0;
        }
    }
    return $row;
}

function migrate_database(PDO $pdo): void
{
    $columns = [
        'tenants' => [
            'analytics_config' => "TEXT DEFAULT '{}'",
            'webhook_access' => 'INTEGER DEFAULT 0',
            'webhook_requested_at' => 'TEXT',
            'webhook_approved_at' => 'TEXT',
            'webhook_approved_by' => 'TEXT',
            'sheets_config' => "TEXT DEFAULT '{}'",
            'letterhead_config' => "TEXT DEFAULT '{}'",
            'signature_config' => "TEXT DEFAULT '{}'",
            'clauses_config' => "TEXT DEFAULT '{}'",
            'access_token_generated_at' => 'TEXT',
            'access_token_viewed_at' => 'TEXT',
            'home_path' => "TEXT DEFAULT '/app/agenda'",
        ],
        'users' => [
            'last_login_at' => 'TEXT',
            'document_kind' => 'TEXT',
            'cpf' => 'TEXT',
        ],
        'clients' => ['utm_source' => 'TEXT', 'utm_medium' => 'TEXT', 'utm_campaign' => 'TEXT', 'address' => 'TEXT', 'city' => 'TEXT', 'state' => 'TEXT', 'cep' => 'TEXT', 'lat' => 'REAL', 'lng' => 'REAL', 'trade_name' => 'TEXT', 'state_registration' => 'TEXT', 'contact_name' => 'TEXT'],
        'requests' => ['utm_source' => 'TEXT', 'utm_medium' => 'TEXT', 'utm_campaign' => 'TEXT', 'metadata' => 'TEXT'],
        'appointments' => [
            'request_id' => 'TEXT',
            'metadata' => 'TEXT',
            'visit_type' => "TEXT DEFAULT 'interno'",
            'commission_agent_id' => 'TEXT',
            'commission_type' => 'TEXT',
            'commission_value' => 'REAL',
            'commission_amount' => 'REAL',
            'reserva_n' => 'INTEGER',
        ],
        'services' => [
            'buffer_minutes' => 'INTEGER DEFAULT 0',
            'deposit' => 'REAL DEFAULT 0',
            'price_kind' => "TEXT DEFAULT 'priced'",
            'location_type' => "TEXT DEFAULT 'presencial'",
            'location_note' => 'TEXT',
            'bookable_online' => 'INTEGER DEFAULT 1',
            'requires_confirmation' => 'INTEGER DEFAULT 0',
            'capacity' => 'INTEGER DEFAULT 1',
            'min_notice_hours' => 'INTEGER DEFAULT 0',
            'max_advance_days' => 'INTEGER DEFAULT 60',
            'client_instructions' => 'TEXT',
            'internal_notes' => 'TEXT',
        ],
        'finance_entries' => [
            'source_type' => 'TEXT',
            'source_id' => 'TEXT',
            'amount_paid' => 'REAL DEFAULT 0',
            'payment_method' => 'TEXT',
            'agent_id' => 'TEXT',
        ],
    ];
    foreach ($columns as $table => $wanted) {
        $existing = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        foreach ($wanted as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $name $definition");
            }
        }
    }
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lc ON users(lower(email))');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS users_username_lc ON users(lower(username))');
    $pdo->exec("CREATE TABLE IF NOT EXISTS finance_entries (
      id TEXT PRIMARY KEY,
      tenant_id TEXT NOT NULL,
      kind TEXT NOT NULL,
      flow TEXT NOT NULL DEFAULT 'in',
      status TEXT NOT NULL DEFAULT 'open',
      description TEXT NOT NULL,
      amount REAL NOT NULL DEFAULT 0,
      due_date TEXT,
      paid_at TEXT,
      client_id TEXT,
      notes TEXT,
      source_type TEXT,
      source_id TEXT,
      amount_paid REAL DEFAULT 0,
      payment_method TEXT,
      agent_id TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_finance_tenant ON finance_entries(tenant_id, kind, status)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_finance_source ON finance_entries(tenant_id, source_type, source_id) WHERE source_id IS NOT NULL AND source_type IS NOT NULL');
}

function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https://api.mapbox.com https://tiles.mapbox.com https://*.tiles.mapbox.com https://*.mapbox.com; style-src 'self' 'unsafe-inline' https://api.mapbox.com https://*.mapbox.com; script-src 'self' 'unsafe-inline' https://api.mapbox.com blob:; connect-src 'self' https://api.mapbox.com https://events.mapbox.com https://tiles.mapbox.com https://*.tiles.mapbox.com https://*.mapbox.com; worker-src 'self' blob: https://api.mapbox.com; child-src blob:; font-src 'self' data: https://api.mapbox.com https://*.mapbox.com; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (function_exists('is_vercel') && is_vercel());
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

class PgSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $row = one('SELECT data FROM php_sessions WHERE id=? AND expires_at > ?', [$id, date('c')]);
        return $row['data'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $exp = date('c', time() + max(60, (int)ini_get('session.gc_maxlifetime')));
        q('INSERT INTO php_sessions(id, data, expires_at) VALUES(?,?,?) ON CONFLICT (id) DO UPDATE SET data=EXCLUDED.data, expires_at=EXCLUDED.expires_at', [$id, $data, $exp]);
        return true;
    }

    public function destroy(string $id): bool
    {
        q('DELETE FROM php_sessions WHERE id=?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        if (random_int(1, 40) !== 1) {
            return 0;
        }
        $st = q('DELETE FROM php_sessions WHERE expires_at < ?', [date('c')]);
        return $st->rowCount();
    }
}

function boot_session(): void
{
    session_name('firestepcrm');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || is_vercel();
    if (is_pgsql()) {
        session_set_save_handler(new PgSessionHandler(), true);
    } else {
        session_save_path(storage_dir() . '/sessions');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    harden_request();
}

function slugify(string $value, string $fallback = 'negocio'): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii === false || $ascii === '') {
        $ascii = $value;
    }
    $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $ascii));
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : $fallback;
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 10
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/\d/', $password);
}

function password_matches(?string $plain, mixed $hash): bool
{
    if (!is_string($hash) || $hash === '') {
        return false;
    }
    return password_verify((string)$plain, $hash);
}

function generate_temp_password(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    for ($i = 0; $i < 12; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out . random_int(10, 99);
}

function login_taken(string $email, string $username, ?string $exceptId = null): bool
{
    $email = strtolower(trim($email));
    $username = strtolower(trim($username));
    $sql = 'SELECT id FROM users WHERE (lower(email)=? OR lower(username)=? OR lower(email)=? OR lower(username)=?)';
    $params = [$email, $email, $username, $username];
    if ($exceptId) {
        $sql .= ' AND id!=?';
        $params[] = $exceptId;
    }
    return (bool)one($sql, $params);
}

function saas_access_confirmed(?array $admin): bool
{
    if (!$admin) {
        return false;
    }
    return (int)($admin['must_change_password'] ?? 1) === 0 && !empty($admin['last_login_at']);
}

function tenant_admin(string $tenantId): ?array
{
    return one("SELECT * FROM users WHERE tenant_id=? AND ".sql_is_crm_admin()." ORDER BY created_at ASC", [$tenantId]);
}

function user_kind(?array $u): string
{
    $role = (string)($u['role'] ?? '');
    if ($role === 'user_admin' || $role === 'MASTER') {
        return 'user_admin';
    }
    if ($role === 'user_crm' || $role === 'TENANT_ADMIN') {
        return 'user_crm';
    }
    if ($role === 'user_agent') {
        return 'user_agent';
    }
    return $role;
}

function is_user_admin(?array $u): bool
{
    return user_kind($u) === 'user_admin';
}

function is_user_crm(?array $u): bool
{
    return user_kind($u) === 'user_crm';
}

function is_user_agent(?array $u): bool
{
    return user_kind($u) === 'user_agent';
}

function sql_is_platform_admin(string $column = 'role'): string
{
    return "($column IN ('user_admin','MASTER'))";
}

function sql_is_crm_admin(string $column = 'role'): string
{
    return "($column IN ('user_crm','TENANT_ADMIN'))";
}

function sql_tenant_admin_join(string $tenantAlias = 't', string $userAlias = 'u'): string
{
    return $userAlias.'.id = (SELECT id FROM users WHERE tenant_id='.$tenantAlias.'.id AND '.sql_is_crm_admin().' ORDER BY created_at ASC LIMIT 1)';
}

function agent_route_forbidden(string $path): bool
{
    foreach (['/app/agentes', '/app/metricas', '/app/servicos', '/app/relatorios', '/app/financeiro', '/app/abrangencia', '/app/fornecedores', '/app/webhooks', '/app/configuracoes', '/app/onboarding', '/app/google'] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            return true;
        }
    }
    return false;
}

function app_home_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS home_path text NOT NULL DEFAULT '/app/agenda'");
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('home_path', $cols, true)) {
            db()->exec("ALTER TABLE tenants ADD COLUMN home_path TEXT DEFAULT '/app/agenda'");
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function app_home_choices(?array $user = null, ?array $tenant = null): array
{
    $terms = $tenant ? terms_of($tenant) : ['clients' => 'Clientes', 'requests' => 'Solicitações'];
    $all = [
        '/app/agenda' => 'Agenda',
        '/app/anotacoes' => 'Anotações',
        '/app' => 'Visão geral',
        '/app/metricas' => 'Métricas',
        '/app/agendamentos' => 'Agendamentos',
        '/app/solicitacoes' => (string)($terms['requests'] ?? 'Solicitações'),
        '/app/kanban' => 'Pipeline',
        '/app/clientes' => (string)($terms['clients'] ?? 'Clientes'),
        '/app/agentes' => 'Agentes',
        '/app/abrangencia' => 'Abrangência',
        '/app/fornecedores' => 'Fornecedores',
        '/app/servicos' => 'Serviços',
        '/app/relatorios' => 'Relatórios',
        '/app/financeiro' => 'Financeiro',
        '/app/webhooks' => 'Webhooks',
        '/app/configuracoes' => 'Configurações',
    ];
    if ($user && is_user_agent($user)) {
        return array_filter($all, static fn(string $path) => !agent_route_forbidden($path), ARRAY_FILTER_USE_KEY);
    }
    return $all;
}

function app_home_stored(?array $tenant): string
{
    app_home_ensure_schema();
    $raw = trim((string)($tenant['home_path'] ?? ''));
    $all = app_home_choices(null, $tenant);
    return isset($all[$raw]) ? $raw : '/app/agenda';
}

function app_home_path(?array $tenant, ?array $user = null): string
{
    $stored = app_home_stored($tenant);
    $forUser = app_home_choices($user, $tenant);
    if (isset($forUser[$stored])) {
        return $stored;
    }
    return '/app/agenda';
}

function pipeline_view_cookie_name(string $userId): string
{
    return 'crm_pv_'.substr(hash('sha256', $userId), 0, 16);
}

function pipeline_view_normalize(?string $raw): string
{
    return $raw === 'compact' ? 'compact' : 'card';
}

function pipeline_view_of(?array $user = null): string
{
    $uid = (string)($user['id'] ?? '');
    $key = $uid !== '' ? pipeline_view_cookie_name($uid) : 'crm_pv';
    return pipeline_view_normalize((string)($_COOKIE[$key] ?? ''));
}

function pipeline_view_save(string $userId, string $view): void
{
    $view = pipeline_view_normalize($view);
    $name = pipeline_view_cookie_name($userId);
    $_COOKIE[$name] = $view;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie($name, $view, [
        'expires' => time() + 86400 * 400,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function redirect_app_home(?array $tenant = null, ?array $user = null): void
{
    if ($user && is_user_admin($user)) {
        redirect('/master');
    }
    if ($user && !empty($user['must_change_password'])) {
        redirect('/app/senha');
    }
    app_home_ensure_schema();
    if (!$tenant && $user && !empty($user['tenant_id'])) {
        $tenant = one('SELECT * FROM tenants WHERE id=?', [$user['tenant_id']]);
    }
    redirect(app_home_path($tenant, $user));
}

function users_ensure_roles(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = $pdo ?? db();
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user_admin','user_crm','user_agent','MASTER','TENANT_ADMIN'))");
        }
        $pdo->exec("UPDATE users SET role='user_admin' WHERE role='MASTER'");
        $pdo->exec("UPDATE users SET role='user_crm' WHERE role='TENANT_ADMIN'");
        $done = true;
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            $pdo->exec("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user_admin','user_crm','user_agent'))");
            try {
                $pdo->exec(<<<'SQL'
CREATE OR REPLACE FUNCTION public.is_master()
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public
AS $$
  SELECT EXISTS (
    SELECT 1
    FROM public.users u
    WHERE u.auth_user_id = auth.uid()
      AND u.role IN ('user_admin', 'MASTER')
      AND u.active = true
  );
$$;
SQL);
            } catch (Throwable $e) {
                error_log('users_ensure_roles is_master: '.$e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('users_ensure_roles: '.$e->getMessage());
    }
}

function uid(): string
{
    return bin2hex(random_bytes(12));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function redirect(string $to): never
{
    header('Location: ' . safe_internal_path($to));
    exit;
}

function url(string $path = '/'): string
{
    return $path === '' ? '/' : $path;
}

function flash(?string $msg = null, string $kind = 'ok'): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        $_SESSION['flash_kind'] = $kind;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $GLOBALS['_last_flash'] = $m;
    $GLOBALS['_last_flash_kind'] = $_SESSION['flash_kind'] ?? 'ok';
    return $m;
}

function flash_kind(): string
{
    $k = $_SESSION['flash_kind'] ?? 'ok';
    unset($_SESSION['flash_kind']);
    return $k === 'error' ? 'error' : 'ok';
}

function bounce_form(string $to, string $msg): never
{
    flash($msg, 'error');
    $keep = $_POST;
    unset($keep['password'], $keep['password_confirm'], $keep['_csrf']);
    $_SESSION['form_old'] = $keep;
    redirect($to);
}

function take_old_form(): array
{
    $old = $_SESSION['form_old'] ?? [];
    unset($_SESSION['form_old']);
    return is_array($old) ? $old : [];
}

function old_fill(array $old, string $key, ?string $default = ''): string
{
    $fallback = (string)($default ?? '');
    $v = $old[$key] ?? $fallback;
    return is_scalar($v) ? (string)$v : $fallback;
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $t = (string)($_POST['_csrf'] ?? '');
    $s = (string)($_SESSION['csrf'] ?? '');
    $ok = $s !== '' && $t !== '' && hash_equals($s, $t) && request_origin_ok();
    if (!$ok) {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if ($path === '/app/onboarding') {
            flash('Sessão expirada. Clique em Continuar novamente.', 'error');
            redirect('/app/onboarding?step='.(int)($_POST['step'] ?? 1));
        }
        http_response_code(419);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function post(string $k, ?string $d = null): ?string
{
    $v = trim((string)($_POST[$k] ?? ''));
    return $v === '' ? $d : $v;
}

function money(float $n): string
{
    return 'R$ ' . number_format($n, 2, ',', '.');
}

function services_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE services ADD COLUMN IF NOT EXISTS price_kind text NOT NULL DEFAULT 'priced'");
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(services)')->fetchAll(), 'name');
        if (!in_array('price_kind', $cols, true)) {
            db()->exec("ALTER TABLE services ADD COLUMN price_kind TEXT DEFAULT 'priced'");
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function notes_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $bool = is_pgsql() ? 'boolean not null default false' : 'INTEGER DEFAULT 0';
        db()->exec("CREATE TABLE IF NOT EXISTS user_notes (
          id TEXT PRIMARY KEY,
          tenant_id TEXT NOT NULL,
          user_id TEXT NOT NULL,
          title TEXT NOT NULL,
          body TEXT,
          note_date TEXT NOT NULL,
          show_on_agenda $bool,
          created_at TEXT NOT NULL,
          updated_at TEXT NOT NULL
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS user_notes_tenant_date ON user_notes(tenant_id, note_date)');
    } catch (Throwable $e) {
        $done = false;
    }
}

function note_can_manage(array $user, array $note): bool
{
    if (is_user_crm($user)) {
        return true;
    }
    return (string)($note['user_id'] ?? '') === (string)($user['id'] ?? '');
}

function notes_agenda_events(string $tenantId, string $from, string $to): array
{
    notes_ensure_schema();
    $fromDay = substr($from, 0, 10);
    $toDay = substr($to, 0, 10);
    $rows = all(
        'SELECT n.*, u.name author_name FROM user_notes n LEFT JOIN users u ON u.id=n.user_id
         WHERE n.tenant_id=? AND '.sql_true('n.show_on_agenda').' AND n.note_date>=? AND n.note_date<=?',
        [$tenantId, $fromDay, $toDay]
    );
    $events = [];
    foreach ($rows as $n) {
        $day = substr((string)$n['note_date'], 0, 10);
        $events[] = [
            'id' => $n['id'],
            'kind' => 'note',
            'title' => (string)$n['title'],
            'subtitle' => 'Anotação · '.((string)($n['author_name'] ?? 'Equipe')),
            'status' => 'NOTE',
            'source' => 'Anotação',
            'start' => $day.' 08:00:00',
            'end' => $day.' 09:00:00',
        ];
    }
    return $events;
}

function service_price_kind(?array $s): string
{
    $k = strtolower(trim((string)($s['price_kind'] ?? '')));
    return in_array($k, ['priced', 'convenio', 'cortesia', 'reuniao'], true) ? $k : 'priced';
}

function service_price_label(?array $s): string
{
    $k = service_price_kind($s);
    if ($k === 'reuniao') {
        return 'Reunião';
    }
    if ($k === 'convenio') {
        return 'Convênio';
    }
    if ($k === 'cortesia') {
        return money(0);
    }
    return money((float)($s['price'] ?? 0));
}

function parse_service_pricing(): array
{
    $has = (string)post('has_price', '1');
    if ($has === '0') {
        $kind = (string)post('no_price_kind', '');
        if (!in_array($kind, ['convenio', 'cortesia', 'reuniao'], true)) {
            return ['ok' => false, 'message' => 'Escolha Convênio, Cortesia ou Reunião.'];
        }
        return ['ok' => true, 'price_kind' => $kind, 'price' => 0.0, 'deposit' => 0.0];
    }
    $price = parse_money_input(post('price'));
    if ($price < 0.01) {
        return ['ok' => false, 'message' => 'Informe o preço em reais, a partir de R$ 0,01.'];
    }
    $deposit = parse_money_input(post('deposit'));
    if ($deposit < 0) {
        $deposit = 0.0;
    }
    return ['ok' => true, 'price_kind' => 'priced', 'price' => $price, 'deposit' => $deposit];
}

function parse_money_input(?string $raw): float
{
    $s = trim(str_replace(['R$', 'r$', ' '], '', (string)$raw));
    if ($s === '') {
        return 0.0;
    }
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (str_contains($s, ',')) {
        $s = str_replace(',', '.', $s);
    }
    if (!is_numeric($s)) {
        return 0.0;
    }
    return round((float)$s, 2);
}

function phone_fmt(?string $v): string
{
    if (!$v) return '—';
    $d = preg_replace('/\D/', '', $v);
    if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    return $v;
}

function greeting(): string
{
    $h = (int)date('G');
    return $h < 12 ? 'Bom dia' : ($h < 18 ? 'Boa tarde' : 'Boa noite');
}

function normalize_source(?string $source): string
{
    $key = strtolower(trim((string)$source));
    return [
        'website'=>'Site', 'site'=>'Site', 'whatsapp'=>'WhatsApp',
        'instagram'=>'Instagram', 'facebook'=>'Facebook', 'google'=>'Google',
        'referral'=>'Indicação', 'indicacao'=>'Indicação', 'indicação'=>'Indicação',
        'phone'=>'Telefone', 'telefone'=>'Telefone', 'manual'=>'Manual',
        'webhook'=>'Webhook',
    ][$key] ?? ($source ?: 'Outro');
}

function icon(string $name, int $size = 18): string
{
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'inbox' => '<path d="M4 4h16v16H4z"/><path d="M4 14h4l2 3h4l2-3h4"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'kanban' => '<rect x="3" y="4" width="5" height="16" rx="1"/><rect x="10" y="4" width="5" height="10" rx="1"/><rect x="17" y="4" width="4" height="13" rx="1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/>',
        'chart' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16h16V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
        'note' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
        'webhook' => '<path d="M18 16.5a3 3 0 1 0 3-3"/><path d="m18 16.5-3 5.2M6 7.5a3 3 0 1 0-3 3"/><path d="m6 7.5 3-5.2M8.7 17H5a3 3 0 0 1-2.6-4.5M15.3 7H19a3 3 0 0 1 2.6 4.5M9 7l6 10"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.55V21h-4v-.08a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3v-4h.08a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3h4v.08a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21v4h-.08a1.7 1.7 0 0 0-1.52 1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M15 3h6v18h-6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow-up' => '<path d="m18 15-6-6-6 6"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.6-6.3"/><path d="M21 3v6h-6"/>',
        'tag' => '<path d="M20 13 13 20 4 11V4h7z"/><circle cx="8.5" cy="8.5" r="1"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 14h2"/>',
        'folder' => '<path d="M3 7h6l2 2h10v10H3z"/><path d="M3 7V5h5l2 2"/>',
        'pdf' => '<path d="M14 2H7a2 2 0 0 0-2 2v16h14V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'map' => '<path d="M9 18 3 20V6l6-2 6 2 6-2v14l-6 2-6-2z"/><path d="M9 4v14M15 6v14"/>',
    ];
    $body = $paths[$name] ?? $paths['list'];
    return '<svg class="ico" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$body.'</svg>';
}

const APPT_STATUS = [
    'WAITING' => ['Aguardando', '#a16207', '#fef9c3'],
    'SCHEDULED' => ['Agendado', '#1d4ed8', '#dbeafe'],
    'CONFIRMED' => ['Confirmado', '#15803d', '#dcfce7'],
    'IN_PROGRESS' => ['Em atendimento', '#6d28d9', '#ede9fe'],
    'DONE' => ['Finalizado', '#475569', '#e2e8f0'],
    'CANCELLED' => ['Cancelado', '#b91c1c', '#fee2e2'],
    'NO_SHOW' => ['Não compareceu', '#9f1239', '#ffe4e6'],
];

const REQ_STATUS = [
    'NEW' => 'Nova',
    'CONTACTED' => 'Em contato',
    'WAITING_CLIENT' => 'Aguardando cliente',
    'SCHEDULED' => 'Agendada',
    'DONE' => 'Finalizada',
    'LOST' => 'Perdida',
    'ARCHIVED' => 'Arquivada',
];

const TENANT_STATUS = [
    'ACTIVE' => 'Ativo',
    'SUSPENDED' => 'Suspenso',
    'OVERDUE' => 'Inadimplente',
    'CANCELLED' => 'Cancelado',
];

function badge_appt(string $s): string
{
    $m = APPT_STATUS[$s] ?? [$s, '#475569', '#e2e8f0'];
    return '<span class="badge" style="background:'.$m[2].';color:'.$m[1].'">'.e($m[0]).'</span>';
}

function badge_req(string $s): string
{
    return '<span class="badge" style="background:#eef2ff;color:#3730a3">'.e(REQ_STATUS[$s] ?? $s).'</span>';
}

function view(string $file, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require VIEWS . '/' . $file . '.php';
}

function head_viewport(): void
{
    echo '<meta name="viewport" id="fs-viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'."\n";
    echo <<<'JS'
<script>
(function () {
  var ua = navigator.userAgent || '';
  var phone = /iPhone|iPod|Windows Phone|BlackBerry|IEMobile|Opera Mini|webOS/i.test(ua)
    || (/Android/i.test(ua) && /Mobile/i.test(ua));
  if (!phone || /iPad|Tablet|PlayBook/i.test(ua)) return;
  var meta = document.getElementById('fs-viewport');
  if (meta) {
    meta.setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover');
  }
  document.documentElement.style.touchAction = 'manipulation';
  function stopPinch(e) {
    if (e.touches && e.touches.length > 1) e.preventDefault();
  }
  document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });
  document.addEventListener('gesturechange', function (e) { e.preventDefault(); }, { passive: false });
  document.addEventListener('touchmove', stopPinch, { passive: false });
})();
</script>
JS;
}

function layout_start(string $kind, array $data): void
{
    extract($data, EXTR_SKIP);
    require VIEWS . '/layout_' . $kind . '_start.php';
}

function layout_end(string $kind): void
{
    require VIEWS . '/layout_' . $kind . '_end.php';
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st;
}

function one(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r ? normalize_row($r) : null;
}

function all(string $sql, array $p = []): array
{
    return array_map(static fn(array $row) => normalize_row($row) ?? $row, q($sql, $p)->fetchAll());
}

function appointment_detail(string $tenantId, string $id): ?array
{
    try {
        if (function_exists('appointment_commission_ensure_schema')) {
            appointment_commission_ensure_schema();
        }
        if (function_exists('coverage_ensure_schema')) {
            coverage_ensure_schema();
        }
    } catch (Throwable $e) {
        // segue com a consulta mais simples
    }
    $params = [$id, $tenantId];
    $sql = "SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, c.email client_email, s.name service_name, s.duration_minutes, s.price service_price, r.message request_message, r.utm_source, r.utm_medium, r.utm_campaign, ag.name commission_agent_name
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        LEFT JOIN requests r ON r.id=a.request_id AND r.tenant_id=a.tenant_id
        LEFT JOIN users ag ON ag.id=a.commission_agent_id AND ag.tenant_id=a.tenant_id
        WHERE a.id=? AND a.tenant_id=?";
    $sqlLite = "SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, c.email client_email, s.name service_name, s.duration_minutes, s.price service_price, r.message request_message, r.utm_source, r.utm_medium, r.utm_campaign
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        LEFT JOIN requests r ON r.id=a.request_id AND r.tenant_id=a.tenant_id
        WHERE a.id=? AND a.tenant_id=?";
    try {
        $row = one($sql, $params);
    } catch (Throwable $e) {
        $row = one($sqlLite, $params);
        if ($row) {
            $row['commission_agent_name'] = null;
        }
    }
    if ($row) {
        try {
            $row['stops'] = all('SELECT address, lat, lng FROM appointment_stops WHERE tenant_id=? AND appointment_id=? ORDER BY sort_order, created_at', [$tenantId, $id]);
        } catch (Throwable $e) {
            $row['stops'] = [];
        }
    }
    return $row;
}

function app_return_url(?string $raw, string $fallback = '/app/agenda'): string
{
    $raw = trim((string)$raw);
    if ($raw === '') return $fallback;
    $path = parse_url($raw, PHP_URL_PATH) ?: '';
    if (!in_array($path, ['/app/agenda', '/app/agendamentos', '/app/clientes', '/app/clientes/ver'], true)) return $fallback;
    $query = parse_url($raw, PHP_URL_QUERY);
    $params = [];
    if ($query) parse_str($query, $params);
    unset($params['edit'], $params['ver'], $params['new'], $params['block'], $params['convert']);
    $query = http_build_query($params);
    return $path . ($query !== '' ? '?'.$query : '');
}

function audit(?string $tenant, ?string $user, string $action, string $entity, ?string $id = null): void
{
    q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)',
        [uid(), $tenant, $user, $action, $entity, $id, now()]);
}

function notify(string $tenant, string $title, string $body): void
{
    q('INSERT INTO notifications(id,tenant_id,title,body,read_flag,created_at) VALUES(?,?,?,?,'.sql_lit_bool(false).',?)',
        [uid(), $tenant, $title, $body, now()]);
}

function rate_ok(string $key, int $limit, int $window): bool
{
    if (is_pgsql()) {
        $since = date('c', time() - $window);
        if (random_int(1, 20) === 1) {
            q('DELETE FROM rate_limits WHERE hit_at < ?', [date('c', time() - max($window, 86400))]);
        }
        $row = one('SELECT COUNT(*) AS c FROM rate_limits WHERE key=? AND hit_at>=?', [$key, $since]);
        if ((int)($row['c'] ?? 0) >= $limit) {
            return false;
        }
        q('INSERT INTO rate_limits(key, hit_at) VALUES(?,?)', [$key, date('c')]);
        return true;
    }
    $file = storage_dir() . '/rate.json';
    $data = file_exists($file) ? json_decode((string)file_get_contents($file), true) : [];
    $now = time();
    $data[$key] = array_values(array_filter($data[$key] ?? [], fn($t) => $t > $now - $window));
    if (count($data[$key]) >= $limit) {
        file_put_contents($file, json_encode($data));
        return false;
    }
    $data[$key][] = $now;
    file_put_contents($file, json_encode($data));
    return true;
}

function default_hours(): array
{
    $brk = [['start'=>'12:00','end'=>'13:00']];
    return [
        0 => ['closed'=>true,'start'=>'08:00','end'=>'18:00','breaks'=>[]],
        1 => ['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>$brk],
        2 => ['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>$brk],
        3 => ['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>$brk],
        4 => ['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>$brk],
        5 => ['closed'=>false,'start'=>'08:00','end'=>'18:00','breaks'=>$brk],
        6 => ['closed'=>true,'start'=>'08:00','end'=>'12:00','breaks'=>[]],
    ];
}

function platform_secret_key(): string
{
    $raw = env_str('APP_KEY') ?: env_str('MASTER_PASSWORD') ?: env_str('DATABASE_URL') ?: 'firestep-platform';
    return hash('sha256', 'firestep.secret.v1|' . $raw, true);
}

function platform_encrypt_secret(string $plain): string
{
    $plain = trim($plain);
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', platform_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false || strlen($tag) !== 16) {
        throw new RuntimeException('Não foi possível cifrar o segredo.');
    }
    return 'enc1:' . base64_encode($iv . $tag . $cipher);
}

function platform_decrypt_secret(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }
    if (!str_starts_with($stored, 'enc1:')) {
        return '';
    }
    $bin = base64_decode(substr($stored, 5), true);
    if ($bin === false || strlen($bin) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', platform_secret_key(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    return $plain === false ? '' : $plain;
}

function platform_leaflet_row(): array
{
    try {
        $row = one("SELECT value FROM platform_settings WHERE key='leaflet'");
    } catch (Throwable) {
        return [];
    }
    return json_arr($row['value'] ?? '{}');
}

function platform_leaflet_has_token(): bool
{
    return trim((string)(platform_leaflet_row()['token'] ?? '')) !== '';
}

function platform_leaflet_token(): string
{
    return platform_decrypt_secret((string)(platform_leaflet_row()['token'] ?? ''));
}

function platform_leaflet_save(?string $plain, bool $clear = false): void
{
    $now = now();
    $token = $clear ? '' : platform_encrypt_secret((string)$plain);
    $encoded = json_encode(['token' => $token, 'updated_at' => $now], JSON_UNESCAPED_UNICODE);
    if (is_pgsql()) {
        q("INSERT INTO platform_settings(key,value,updated_at) VALUES('leaflet', CAST(? AS jsonb), ?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=EXCLUDED.updated_at", [$encoded, $now]);
        return;
    }
    q("INSERT OR REPLACE INTO platform_settings(key,value,updated_at) VALUES('leaflet',?,?)", [$encoded, $now]);
}

function mapbox_public_token(): string
{
    $fromEnv = geocoder_env_token();
    if ($fromEnv !== '') {
        return $fromEnv;
    }
    return platform_leaflet_token();
}

function geocoder_env_token(): string
{
    foreach (['MAPBOX_ACCESS_TOKEN', 'MAPBOX_TOKEN', 'LEAFLET_TOKEN', 'GEOCODER_TOKEN', 'MAPTILER_KEY'] as $key) {
        $v = env_str($key);
        if ($v) {
            return $v;
        }
    }
    return '';
}

function json_arr(mixed $value, array $fallback = []): array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return $fallback;
    }
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function terms_of(array $tenant): array
{
    $t = json_arr($tenant['terminology'] ?? '{}');
    return $t + ['client'=>'Cliente','clients'=>'Clientes','appointment'=>'Agendamento','appointments'=>'Agendamentos','request'=>'Solicitação','requests'=>'Solicitações'];
}

function app_url(): string
{
    $forced = env_str('APP_URL');
    if ($forced) {
        return rtrim($forced, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || is_vercel();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    return ($https ? 'https://' : 'http://') . $host;
}

function service_span_minutes(?array $svc): int
{
    return max(5, (int)($svc['duration_minutes'] ?? 60) + (int)($svc['buffer_minutes'] ?? 0));
}

function service_location_label(?string $type): string
{
    return [
        'presencial' => 'Presencial',
        'online' => 'Online',
        'domicilio' => 'A domicílio',
        'ambos' => 'Presencial ou online',
    ][$type ?? 'presencial'] ?? 'Presencial';
}

function analytics_config(array $tenant): array
{
    return json_arr($tenant['analytics_config'] ?? '{}');
}

function request_header(string $name): string
{
    $want = strtolower($name);
    if (function_exists('getallheaders')) {
        foreach (getallheaders() ?: [] as $k => $v) {
            if (strtolower((string)$k) === $want) {
                return trim((string)$v);
            }
        }
    }
    $key = 'HTTP_'.strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$key] ?? ''));
}

function normalize_site_host(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '' || $value === '*' || $value === 'null') {
        return '';
    }
    if (!str_contains($value, '://')) {
        $value = 'https://'.$value;
    }
    $host = strtolower((string)(parse_url($value, PHP_URL_HOST) ?? ''));
    if ($host === '') {
        return '';
    }
    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }
    return $host;
}

function tenant_webhook_hosts(array $tenant): array
{
    $raw = (string)(analytics_config($tenant)['site_domain'] ?? '');
    $hosts = [];
    foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $part) {
        $host = normalize_site_host($part);
        if ($host !== '' && $host !== 'localhost' && !str_ends_with($host, '.local') && str_contains($host, '.')) {
            $hosts[] = $host;
        }
    }
    return array_values(array_unique($hosts));
}

function save_tenant_webhook_hosts(string $tenantId, array $tenant, array $hosts): void
{
    $cfg = analytics_config($tenant);
    $cfg['site_domain'] = implode(', ', tenant_webhook_hosts(['analytics_config' => json_encode(['site_domain' => implode(', ', $hosts)])]));
    q('UPDATE tenants SET analytics_config=?, updated_at=? WHERE id=?', [
        json_encode($cfg, JSON_UNESCAPED_UNICODE), now(), $tenantId,
    ]);
}

function webhook_origin_host(?string $origin): string
{
    return normalize_site_host((string)$origin);
}

function webhook_host_covers(string $incoming, string $allowed): bool
{
    if ($incoming === '' || $allowed === '') {
        return false;
    }
    return $incoming === $allowed || str_ends_with($incoming, '.'.$allowed);
}

function webhook_origin_matches(array $tenant, string $originOrUrl): bool
{
    $host = webhook_origin_host($originOrUrl);
    if ($host === '') {
        return false;
    }
    foreach (tenant_webhook_hosts($tenant) as $ok) {
        if (webhook_host_covers($host, $ok)) {
            return true;
        }
    }
    return false;
}

function webhook_cors_origin_value(string $originOrUrl): string
{
    $originOrUrl = trim($originOrUrl);
    if ($originOrUrl === '' || strcasecmp($originOrUrl, 'null') === 0) {
        return '';
    }
    if (!str_contains($originOrUrl, '://')) {
        $originOrUrl = 'https://'.$originOrUrl;
    }
    $p = parse_url($originOrUrl);
    $scheme = strtolower((string)($p['scheme'] ?? 'https'));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    $host = (string)($p['host'] ?? '');
    if ($host === '') {
        return '';
    }
    $port = isset($p['port']) ? ':'.$p['port'] : '';
    return $scheme.'://'.$host.$port;
}

function request_webhook_origin(): string
{
    $origin = request_header('Origin');
    if ($origin !== '' && strcasecmp($origin, 'null') !== 0) {
        return $origin;
    }
    return request_header('Referer');
}

require_once __DIR__ . '/letterhead.php';
require_once __DIR__ . '/clauses.php';
require_once __DIR__ . '/contract.php';
require_once __DIR__ . '/coverage.php';
require_once __DIR__ . '/uazapi.php';
require_once __DIR__ . '/metrics.php';
