<?php
declare(strict_types=1);

const ROOT = __DIR__ . '/..';
const VIEWS = ROOT . '/views';

date_default_timezone_set('America/Sao_Paulo');

function env_str(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

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
    return env_str('DATABASE_URL') ?: env_str('POSTGRES_URL') ?: env_str('SUPABASE_DB_URL');
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
    if ($url) {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException('DATABASE_URL inválida.');
        }
        $dbName = ltrim((string)($parts['path'] ?? '/postgres'), '/');
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
            $parts['host'],
            $parts['port'] ?? '5432',
            $dbName !== '' ? $dbName : 'postgres'
        );
        if (($parts['port'] ?? '') === '6543' || str_contains($url, 'pgbouncer')) {
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        $pdo = new PDO($dsn, urldecode((string)($parts['user'] ?? 'postgres')), urldecode((string)($parts['pass'] ?? '')), $options);
        $pdo->exec("SET TIME ZONE 'America/Sao_Paulo'");
        try {
            $hasUsers = (bool)$pdo->query('SELECT 1 FROM users LIMIT 1')->fetch();
        } catch (Throwable $e) {
            throw new RuntimeException('Schema do banco não encontrado. Aplique as migrations do Supabase antes de publicar.', 0, $e);
        }
        if (!$hasUsers) {
            require __DIR__ . '/seed.php';
            nexo_seed($pdo);
        }
        return $pdo;
    }

    if (is_vercel()) {
        throw new RuntimeException('Defina DATABASE_URL (Postgres/Supabase) para publicar na Vercel.');
    }

    $path = storage_dir() . '/nexo.sqlite';
    $fresh = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path, null, null, $options);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    migrate_database($pdo);
    if ($fresh || !$pdo->query('SELECT 1 FROM users LIMIT 1')->fetch()) {
        require __DIR__ . '/seed.php';
        nexo_seed($pdo);
    }
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
    return is_pgsql() ? $on : ($on ? 1 : 0);
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
        ],
        'users' => [
            'last_login_at' => 'TEXT',
        ],
        'clients' => ['utm_source' => 'TEXT', 'utm_medium' => 'TEXT', 'utm_campaign' => 'TEXT'],
        'requests' => ['utm_source' => 'TEXT', 'utm_medium' => 'TEXT', 'utm_campaign' => 'TEXT', 'metadata' => 'TEXT'],
        'appointments' => ['request_id' => 'TEXT', 'metadata' => 'TEXT'],
        'services' => [
            'buffer_minutes' => 'INTEGER DEFAULT 0',
            'deposit' => 'REAL DEFAULT 0',
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
}

function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; base-uri 'self'; form-action 'self'");
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
    return one("SELECT * FROM users WHERE tenant_id=? AND role='TENANT_ADMIN'", [$tenantId]);
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
    header('Location: ' . $to);
    exit;
}

function url(string $path = '/'): string
{
    return $path === '' ? '/' : $path;
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
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
    $t = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) {
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
        'webhook' => '<path d="M18 16.5a3 3 0 1 0 3-3"/><path d="m18 16.5-3 5.2M6 7.5a3 3 0 1 0-3 3"/><path d="m6 7.5 3-5.2M8.7 17H5a3 3 0 0 1-2.6-4.5M15.3 7H19a3 3 0 0 1 2.6 4.5M9 7l6 10"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.55V21h-4v-.08a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3v-4h.08a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3h4v.08a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21v4h-.08a1.7 1.7 0 0 0-1.52 1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3M15 3h6v18h-6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow-up' => '<path d="m18 15-6-6-6 6"/>',
        'tag' => '<path d="M20 13 13 20 4 11V4h7z"/><circle cx="8.5" cy="8.5" r="1"/>',
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
    return one("SELECT a.*, c.name client_name, c.phone client_phone, c.whatsapp client_whatsapp, c.email client_email, s.name service_name, s.duration_minutes, r.message request_message, r.utm_source, r.utm_medium, r.utm_campaign
        FROM appointments a
        JOIN clients c ON c.id=a.client_id
        LEFT JOIN services s ON s.id=a.service_id
        LEFT JOIN requests r ON r.id=a.request_id
        WHERE a.id=? AND a.tenant_id=?", [$id, $tenantId]);
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
    unset($params['edit'], $params['new'], $params['block'], $params['convert']);
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
        q('DELETE FROM rate_limits WHERE hit_at < ?', [date('c', time() - max($window, 86400))]);
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
