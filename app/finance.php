<?php
declare(strict_types=1);

const FINANCE_STATUS = [
    'open' => ['Em aberto', '#1d4ed8', '#dbeafe'],
    'billed' => ['Faturado', '#6d28d9', '#ede9fe'],
    'paid' => ['Pago', '#15803d', '#dcfce7'],
    'cancelled' => ['Cancelado', '#b91c1c', '#fee2e2'],
];

function badge_finance(string $status): string
{
    $m = FINANCE_STATUS[$status] ?? [$status, '#475569', '#e2e8f0'];
    return '<span class="badge" style="color:'.$m[1].';background:'.$m[2].'">'.e($m[0]).'</span>';
}

function finance_kind_for_page(string $page): ?string
{
    return match ($page) {
        'lancamentos' => 'entry',
        'receber', 'faturado' => 'receivable',
        'pagar' => 'payable',
        default => null,
    };
}

function finance_pages(): array
{
    return [
        'dashboard' => ['Dashboard', '/app/financeiro'],
        'lancamentos' => ['Lançamentos', '/app/financeiro/lancamentos'],
        'receber' => ['Contas a receber', '/app/financeiro/receber'],
        'faturado' => ['Faturado', '/app/financeiro/faturado'],
        'pagar' => ['Contas a pagar', '/app/financeiro/pagar'],
        'relatorios' => ['Relatórios', '/app/financeiro/relatorios'],
    ];
}

function finance_sum(string $tenantId, string $kind, array $statuses, ?string $flow = null, ?string $from = null, ?string $to = null): float
{
    $sql = 'SELECT COALESCE(SUM(amount),0) c FROM finance_entries WHERE tenant_id=? AND kind=?';
    $p = [$tenantId, $kind];
    if ($statuses) {
        $sql .= ' AND status IN ('.implode(',', array_fill(0, count($statuses), '?')).')';
        array_push($p, ...$statuses);
    }
    if ($flow) {
        $sql .= ' AND flow=?';
        $p[] = $flow;
    }
    if ($from) {
        $sql .= ' AND COALESCE(paid_at, due_date, created_at) >= ?';
        $p[] = $from;
    }
    if ($to) {
        $sql .= ' AND COALESCE(paid_at, due_date, created_at) <= ?';
        $p[] = $to;
    }
    return (float)(one($sql, $p)['c'] ?? 0);
}

function finance_back(?string $raw): string
{
    $path = parse_url((string)$raw, PHP_URL_PATH) ?: '';
    if (str_starts_with($path, '/app/financeiro')) {
        return $path;
    }
    return '/app/financeiro';
}

function ensure_finance_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS finance_entries (
        id VARCHAR(64) PRIMARY KEY,
        tenant_id VARCHAR(64) NOT NULL,
        kind VARCHAR(20) NOT NULL,
        flow VARCHAR(8) NOT NULL DEFAULT 'in',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        description TEXT NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        due_date VARCHAR(32),
        paid_at VARCHAR(40),
        client_id VARCHAR(64),
        notes TEXT,
        created_at VARCHAR(40) NOT NULL,
        updated_at VARCHAR(40) NOT NULL
    )";
    q($sql);
    try {
        q('CREATE INDEX IF NOT EXISTS idx_finance_tenant ON finance_entries (tenant_id, kind, status)');
    } catch (Throwable $e) {
        // índice já existe
    }
    $ready = true;
}

function finance_query(string $tenantId, string $page, string $search = ''): array
{
    $sql = "SELECT f.*, c.name client_name FROM finance_entries f
            LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
            WHERE f.tenant_id=?";
    $p = [$tenantId];
    $kind = finance_kind_for_page($page);
    if ($kind) {
        $sql .= ' AND f.kind=?';
        $p[] = $kind;
    }
    if ($page === 'faturado') {
        $sql .= " AND f.status='billed'";
    } elseif ($page === 'receber') {
        $sql .= " AND f.status IN ('open','billed')";
    } elseif ($page === 'pagar') {
        $sql .= " AND f.status IN ('open','paid')";
    } elseif ($page === 'lancamentos') {
        $sql .= " AND f.status!='cancelled'";
    }
    $search = trim($search);
    if ($search !== '') {
        $sql .= ' AND (f.description LIKE ? OR COALESCE(c.name,\'\') LIKE ?)';
        $like = '%'.$search.'%';
        $p[] = $like;
        $p[] = $like;
    }
    $sql .= ' ORDER BY COALESCE(f.due_date, f.created_at) DESC, f.created_at DESC';
    return all($sql, $p);
}
