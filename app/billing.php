<?php
declare(strict_types=1);

const BILLING_TRIAL_DAYS = 30;
const BILLING_ADDON_COMMUNICATE = 40.00;

function billing_cycles(): array
{
    return [
        'monthly' => [
            'slug' => 'monthly',
            'name' => 'Mensal',
            'kicker' => 'Flexível',
            'monthly' => 39.90,
            'months' => 1,
            'color' => '#2563eb',
            'hint' => 'Cobra todo mês.',
        ],
        'semiannual' => [
            'slug' => 'semiannual',
            'name' => 'Semestral',
            'kicker' => 'Mais usado',
            'monthly' => 34.90,
            'months' => 6,
            'color' => '#101828',
            'hint' => 'Preço mensal com cobrança a cada 6 meses.',
        ],
        'annual' => [
            'slug' => 'annual',
            'name' => 'Anual',
            'kicker' => 'Melhor valor',
            'monthly' => 29.90,
            'months' => 12,
            'color' => '#0f766e',
            'hint' => 'Preço mensal com cobrança uma vez ao ano.',
        ],
    ];
}

function billing_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS trial_ends_at timestamptz');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_cycle text');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS communicate_addon boolean NOT NULL DEFAULT false');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_paid_until timestamptz');
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_status text NOT NULL DEFAULT 'trial'");
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_requested_at timestamptz');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_requested_cycle text');
            db()->exec('ALTER TABLE tenants ADD COLUMN IF NOT EXISTS billing_requested_addon boolean NOT NULL DEFAULT false');
            db()->exec("CREATE TABLE IF NOT EXISTS saas_invoices (
              id text PRIMARY KEY,
              tenant_id text NOT NULL,
              cycle text NOT NULL,
              communicate_addon boolean NOT NULL DEFAULT false,
              months integer NOT NULL,
              amount numeric(12,2) NOT NULL DEFAULT 0,
              status text NOT NULL DEFAULT 'open',
              period_start timestamptz,
              period_end timestamptz,
              created_at timestamptz NOT NULL DEFAULT timezone('utc', now()),
              paid_at timestamptz,
              paid_by text,
              notes text
            )");
            db()->exec('CREATE INDEX IF NOT EXISTS saas_invoices_tenant ON saas_invoices (tenant_id, created_at DESC)');
            db()->exec('CREATE INDEX IF NOT EXISTS saas_invoices_status ON saas_invoices (status, created_at DESC)');
        } else {
            $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
            $add = [
                'trial_ends_at' => 'TEXT',
                'billing_cycle' => 'TEXT',
                'communicate_addon' => 'INTEGER DEFAULT 0',
                'billing_paid_until' => 'TEXT',
                'billing_status' => "TEXT DEFAULT 'trial'",
                'billing_requested_at' => 'TEXT',
                'billing_requested_cycle' => 'TEXT',
                'billing_requested_addon' => 'INTEGER DEFAULT 0',
            ];
            foreach ($add as $name => $def) {
                if (!in_array($name, $cols, true)) {
                    db()->exec("ALTER TABLE tenants ADD COLUMN $name $def");
                }
            }
            db()->exec("CREATE TABLE IF NOT EXISTS saas_invoices (
              id TEXT PRIMARY KEY,
              tenant_id TEXT NOT NULL,
              cycle TEXT NOT NULL,
              communicate_addon INTEGER NOT NULL DEFAULT 0,
              months INTEGER NOT NULL,
              amount REAL NOT NULL DEFAULT 0,
              status TEXT NOT NULL DEFAULT 'open',
              period_start TEXT,
              period_end TEXT,
              created_at TEXT NOT NULL,
              paid_at TEXT,
              paid_by TEXT,
              notes TEXT
            )");
            db()->exec('CREATE INDEX IF NOT EXISTS saas_invoices_tenant ON saas_invoices (tenant_id, created_at)');
            db()->exec('CREATE INDEX IF NOT EXISTS saas_invoices_status ON saas_invoices (status, created_at)');
        }
        billing_backfill_trials();
    } catch (Throwable $e) {
        $done = false;
    }
}

function billing_backfill_trials(): void
{
    if (one("SELECT key FROM platform_settings WHERE key=?", ['billing_trial_backfill'])) {
        return;
    }
    $now = now();
    $rows = all('SELECT id, created_at, trial_ends_at, billing_paid_until FROM tenants');
    foreach ($rows as $row) {
        $trialEnd = trim((string)($row['trial_ends_at'] ?? ''));
        if ($trialEnd === '') {
            $trialEnd = billing_plus_days((string)$row['created_at'], BILLING_TRIAL_DAYS);
        }
        $paid = trim((string)($row['billing_paid_until'] ?? ''));
        if ($paid === '' && strtotime($trialEnd) !== false && strtotime($trialEnd) < time()) {
            $trialEnd = billing_plus_days($now, BILLING_TRIAL_DAYS);
        }
        q('UPDATE tenants SET trial_ends_at=? WHERE id=?', [$trialEnd, $row['id']]);
    }
    try {
        q('INSERT INTO platform_settings(key,value,updated_at) VALUES(?,?,?)', ['billing_trial_backfill', '1', $now]);
    } catch (Throwable $e) {
    }
}

function billing_plus_days(string $from, int $days): string
{
    $dt = date_create($from) ?: date_create(now());
    $dt->modify('+'.$days.' days');
    return $dt->format('Y-m-d H:i:s');
}

function billing_plus_months(string $from, int $months): string
{
    $dt = date_create($from) ?: date_create(now());
    $dt->modify('+'.$months.' months');
    return $dt->format('Y-m-d H:i:s');
}

function billing_start_trial(string $tenantId, ?string $from = null): string
{
    billing_ensure_schema();
    $end = billing_plus_days($from ?: now(), BILLING_TRIAL_DAYS);
    q("UPDATE tenants SET trial_ends_at=?, billing_status='trial', updated_at=? WHERE id=?", [$end, now(), $tenantId]);
    return $end;
}

function billing_quote(string $cycle, bool $addon): array
{
    $meta = billing_cycles()[$cycle] ?? null;
    if (!$meta) {
        throw new InvalidArgumentException('Plano inválido.');
    }
    $monthly = (float)$meta['monthly'] + ($addon ? BILLING_ADDON_COMMUNICATE : 0.0);
    $months = (int)$meta['months'];
    return [
        'cycle' => $cycle,
        'name' => $meta['name'],
        'months' => $months,
        'monthly' => $monthly,
        'base_monthly' => (float)$meta['monthly'],
        'addon' => $addon,
        'addon_monthly' => $addon ? BILLING_ADDON_COMMUNICATE : 0.0,
        'amount' => round($monthly * $months, 2),
    ];
}

function billing_ts(?string $value): ?int
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $t = strtotime($value);
    return $t ?: null;
}

function billing_snapshot(array $tenant): array
{
    billing_ensure_schema();
    $now = time();
    $trialEnd = billing_ts($tenant['trial_ends_at'] ?? null) ?? strtotime(billing_plus_days((string)($tenant['created_at'] ?? now()), BILLING_TRIAL_DAYS));
    $paidUntil = billing_ts($tenant['billing_paid_until'] ?? null);
    $inTrial = $now < $trialEnd;
    $paid = $paidUntil !== null && $now < $paidUntil;
    $open = billing_open_invoice((string)($tenant['id'] ?? ''));
    $status = 'past_due';
    if ($paid) {
        $status = 'active';
    } elseif ($inTrial) {
        $status = 'trial';
    } elseif ($open) {
        $status = 'pending';
    }
    $trialDays = (int)max(0, ceil(($trialEnd - $now) / 86400));
    $paidDays = $paidUntil ? (int)max(0, ceil(($paidUntil - $now) / 86400)) : 0;
    return [
        'status' => $status,
        'locked' => !$inTrial && !$paid,
        'trial' => $inTrial && !$paid,
        'paid' => $paid,
        'trial_ends_at' => date('Y-m-d H:i:s', $trialEnd),
        'trial_days' => $inTrial ? $trialDays : 0,
        'paid_until' => $paidUntil ? date('Y-m-d H:i:s', $paidUntil) : null,
        'paid_days' => $paidDays,
        'cycle' => (string)($tenant['billing_cycle'] ?? ''),
        'communicate' => $inTrial || ($paid && !empty($tenant['communicate_addon'])),
        'open_invoice' => $open,
        'requested_cycle' => (string)($tenant['billing_requested_cycle'] ?? ''),
        'requested_addon' => !empty($tenant['billing_requested_addon']),
        'requested_at' => $tenant['billing_requested_at'] ?? null,
    ];
}

function billing_sync(array $tenant): array
{
    if (empty($tenant['id'])) {
        return $tenant;
    }
    billing_ensure_schema();
    if (trim((string)($tenant['trial_ends_at'] ?? '')) === '') {
        billing_start_trial((string)$tenant['id'], (string)($tenant['created_at'] ?? now()));
        $fresh = one('SELECT * FROM tenants WHERE id=?', [$tenant['id']]);
        if ($fresh) {
            $tenant = $fresh;
        }
    }
    $snap = billing_snapshot($tenant);
    if (($tenant['billing_status'] ?? '') !== $snap['status']) {
        q('UPDATE tenants SET billing_status=?, updated_at=? WHERE id=?', [$snap['status'], now(), $tenant['id']]);
        $tenant['billing_status'] = $snap['status'];
    }
    $tenant['_billing'] = $snap;
    return $tenant;
}

function billing_locked(array $tenant): bool
{
    return !empty(billing_of($tenant)['locked']);
}

function billing_of(array $tenant): array
{
    return $tenant['_billing'] ?? billing_snapshot($tenant);
}

function billing_communicate_ok(array $tenant): bool
{
    return !empty(billing_of($tenant)['communicate']);
}

function billing_route_allowed(string $path): bool
{
    foreach (['/app/assinatura', '/app/senha'] as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
            return true;
        }
    }
    return false;
}

function billing_open_invoice(string $tenantId): ?array
{
    if ($tenantId === '') {
        return null;
    }
    billing_ensure_schema();
    return one("SELECT * FROM saas_invoices WHERE tenant_id=? AND status='open' ORDER BY created_at DESC LIMIT 1", [$tenantId]);
}

function billing_invoices(string $tenantId, int $limit = 20): array
{
    billing_ensure_schema();
    return all('SELECT * FROM saas_invoices WHERE tenant_id=? ORDER BY created_at DESC LIMIT '.(int)$limit, [$tenantId]);
}

function billing_request(array $tenant, string $cycle, bool $addon, ?string $actor = null): array
{
    billing_ensure_schema();
    $quote = billing_quote($cycle, $addon);
    $tid = (string)$tenant['id'];
    q("UPDATE saas_invoices SET status='cancelled' WHERE tenant_id=? AND status='open'", [$tid]);
    $id = uid();
    $now = now();
    q('INSERT INTO saas_invoices(id,tenant_id,cycle,communicate_addon,months,amount,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
        $id, $tid, $cycle, db_bool($addon), $quote['months'], $quote['amount'], 'open', $now,
    ]);
    q('UPDATE tenants SET billing_requested_at=?, billing_requested_cycle=?, billing_requested_addon=?, updated_at=? WHERE id=?', [
        $now, $cycle, db_bool($addon), $now, $tid,
    ]);
    audit($tid, $actor, 'billing.requested', 'saas_invoice', $id);
    notify($tid, 'Pedido de plano enviado', 'O Admin Master confirma o pagamento e libera o período: '.$quote['name'].' · '.money($quote['amount']).'.');
    return ['ok' => true, 'invoice_id' => $id, 'quote' => $quote];
}

function billing_confirm(string $invoiceId, ?string $actor = null, string $notes = ''): array
{
    billing_ensure_schema();
    $inv = one('SELECT * FROM saas_invoices WHERE id=?', [$invoiceId]);
    if (!$inv || ($inv['status'] ?? '') !== 'open') {
        return ['ok' => false, 'message' => 'Cobrança não encontrada ou já tratada.'];
    }
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$inv['tenant_id']]);
    if (!$tenant) {
        return ['ok' => false, 'message' => 'Cliente não encontrado.'];
    }
    $quote = billing_quote((string)$inv['cycle'], !empty($inv['communicate_addon']));
    $startFrom = now();
    $paidTs = billing_ts($tenant['billing_paid_until'] ?? null);
    if ($paidTs && $paidTs > time()) {
        $startFrom = date('Y-m-d H:i:s', $paidTs);
    }
    $periodEnd = billing_plus_months($startFrom, (int)$quote['months']);
    $now = now();
    q("UPDATE saas_invoices SET status='paid', paid_at=?, paid_by=?, period_start=?, period_end=?, notes=? WHERE id=?", [
        $now, $actor, $startFrom, $periodEnd, $notes !== '' ? $notes : null, $invoiceId,
    ]);
    q("UPDATE tenants SET billing_cycle=?, communicate_addon=?, billing_paid_until=?, billing_status='active', billing_requested_at=NULL, billing_requested_cycle=NULL, billing_requested_addon=?, plan=?, updated_at=? WHERE id=?", [
        $quote['cycle'], db_bool($quote['addon']), $periodEnd, db_bool(false), $quote['cycle'], $now, $tenant['id'],
    ]);
    audit((string)$tenant['id'], $actor, 'billing.paid', 'saas_invoice', $invoiceId);
    notify((string)$tenant['id'], 'Assinatura liberada', 'Pagamento confirmado. Acesso até '.date('d/m/Y', strtotime($periodEnd)).' · '.$quote['name'].($quote['addon'] ? ' + Comunicador' : '').'.');
    return ['ok' => true, 'until' => $periodEnd];
}

function billing_reject(string $invoiceId, ?string $actor = null): array
{
    billing_ensure_schema();
    $inv = one('SELECT * FROM saas_invoices WHERE id=?', [$invoiceId]);
    if (!$inv || ($inv['status'] ?? '') !== 'open') {
        return ['ok' => false, 'message' => 'Cobrança não encontrada ou já tratada.'];
    }
    q("UPDATE saas_invoices SET status='cancelled' WHERE id=?", [$invoiceId]);
    q('UPDATE tenants SET billing_requested_at=NULL, billing_requested_cycle=NULL, billing_requested_addon=?, updated_at=? WHERE id=?', [
        db_bool(false), now(), $inv['tenant_id'],
    ]);
    audit((string)$inv['tenant_id'], $actor, 'billing.rejected', 'saas_invoice', $invoiceId);
    notify((string)$inv['tenant_id'], 'Pedido de plano recusado', 'O Admin Master recusou a cobrança em aberto. Escolha o plano de novo se precisar.');
    return ['ok' => true];
}

function billing_grant(string $tenantId, string $cycle, bool $addon, ?string $actor = null): array
{
    billing_ensure_schema();
    $tenant = one('SELECT * FROM tenants WHERE id=?', [$tenantId]);
    if (!$tenant) {
        return ['ok' => false, 'message' => 'Cliente não encontrado.'];
    }
    $quote = billing_quote($cycle, $addon);
    $id = uid();
    $now = now();
    q('INSERT INTO saas_invoices(id,tenant_id,cycle,communicate_addon,months,amount,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
        $id, $tenantId, $cycle, db_bool($addon), $quote['months'], $quote['amount'], 'open', $now,
    ]);
    return billing_confirm($id, $actor, 'Liberado pelo Admin Master');
}

function billing_platform(): array
{
    $row = one("SELECT value FROM platform_settings WHERE key='billing'");
    $raw = $row['value'] ?? '{}';
    $data = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
    return [
        'pix' => trim((string)($data['pix'] ?? '')),
        'instructions' => trim((string)($data['instructions'] ?? '')),
    ];
}

function billing_platform_save(array $in): void
{
    $payload = json_encode([
        'pix' => trim((string)($in['pix'] ?? '')),
        'instructions' => trim((string)($in['instructions'] ?? '')),
    ], JSON_UNESCAPED_UNICODE);
    $now = now();
    if (is_pgsql()) {
        q("INSERT INTO platform_settings(key,value,updated_at) VALUES('billing', CAST(? AS jsonb), ?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=EXCLUDED.updated_at", [$payload, $now]);
        return;
    }
    q("INSERT OR REPLACE INTO platform_settings(key,value,updated_at) VALUES('billing',?,?)", [$payload, $now]);
}

function billing_status_label(string $status): array
{
    return match ($status) {
        'trial' => ['Teste grátis', '#1d4ed8', '#dbeafe'],
        'active' => ['Pago', '#166534', '#dcfce7'],
        'pending' => ['Aguardando pagamento', '#b54708', '#fffaeb'],
        'past_due' => ['Bloqueado', '#b91c1c', '#fee2e2'],
        default => [$status, '#344054', '#f2f4f7'],
    };
}

function billing_master_rows(): array
{
    billing_ensure_schema();
    $tenants = all('SELECT t.*, u.username login_username, u.email login_email
        FROM tenants t
        LEFT JOIN users u ON '.sql_tenant_admin_join().'
        ORDER BY t.created_at DESC');
    $out = [];
    foreach ($tenants as $t) {
        $t = billing_sync($t);
        $out[] = $t;
    }
    return $out;
}

function billing_master_stats(array $rows): array
{
    $stats = ['trial' => 0, 'active' => 0, 'pending' => 0, 'past_due' => 0, 'mrr' => 0.0];
    foreach ($rows as $t) {
        $snap = billing_of($t);
        $st = $snap['status'];
        if (isset($stats[$st])) {
            $stats[$st]++;
        }
        if ($snap['paid'] && $snap['cycle'] && isset(billing_cycles()[$snap['cycle']])) {
            $stats['mrr'] += billing_quote($snap['cycle'], !empty($t['communicate_addon']))['monthly'];
        }
    }
    $stats['open'] = all("SELECT i.*, t.business_name, t.name, t.email
        FROM saas_invoices i
        JOIN tenants t ON t.id=i.tenant_id
        WHERE i.status='open'
        ORDER BY i.created_at DESC");
    return $stats;
}
