<?php
declare(strict_types=1);

const FINANCE_STATUS = [
    'open' => ['Em aberto', '#1d4ed8', '#dbeafe'],
    'billed' => ['Faturado', '#6d28d9', '#ede9fe'],
    'paid' => ['Pago', '#15803d', '#dcfce7'],
    'cancelled' => ['Cancelado', '#b91c1c', '#fee2e2'],
];

const FINANCE_PAY_METHODS = [
    'pix' => 'PIX',
    'dinheiro' => 'Dinheiro',
    'cartao' => 'Cartão',
    'transferencia' => 'Transferência',
    'boleto' => 'Boleto',
    'fatura' => 'Fatura',
];

function finance_pay_methods(): array
{
    return FINANCE_PAY_METHODS;
}

function finance_pay_label(?string $method): string
{
    $method = (string)$method;
    return FINANCE_PAY_METHODS[$method] ?? ($method !== '' ? $method : '—');
}

function finance_is_invoice(?string $method): bool
{
    return (string)$method === 'fatura';
}

function finance_normalize_method(?string $method): string
{
    $method = strtolower(trim((string)$method));
    return isset(FINANCE_PAY_METHODS[$method]) ? $method : '';
}

function finance_invoice_due_date(?string $from = null): string
{
    $base = $from ? strtotime($from) : time();
    if ($base === false) {
        $base = time();
    }
    return date('Y-m-01', strtotime('first day of next month', $base));
}

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

function finance_try(string $sql): void
{
    try {
        q($sql);
    } catch (Throwable $e) {
        // coluna ou índice já existe
    }
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
    finance_try('ALTER TABLE finance_entries ADD COLUMN source_type VARCHAR(40)');
    finance_try('ALTER TABLE finance_entries ADD COLUMN source_id VARCHAR(64)');
    finance_try('ALTER TABLE finance_entries ADD COLUMN amount_paid DECIMAL(12,2) DEFAULT 0');
    finance_try('ALTER TABLE finance_entries ADD COLUMN payment_method VARCHAR(40)');
    finance_try('CREATE INDEX IF NOT EXISTS idx_finance_tenant ON finance_entries (tenant_id, kind, status)');
    finance_try('CREATE UNIQUE INDEX IF NOT EXISTS idx_finance_source ON finance_entries (tenant_id, source_type, source_id) WHERE source_id IS NOT NULL AND source_type IS NOT NULL');
    $ready = true;
}

function finance_source_label(?string $type, ?string $id): string
{
    if ($type === 'appointment' && $id) {
        return 'Agendamento #'.substr($id, 0, 8);
    }
    if ($type === 'appointment_reversal' && $id) {
        return 'Estorno agendamento #'.substr($id, 0, 8);
    }
    if ($type === 'manual' || !$type) {
        return 'Manual';
    }
    return (string)$type;
}

function finance_find_source(string $tenantId, string $type, string $sourceId): ?array
{
    return one('SELECT * FROM finance_entries WHERE tenant_id=? AND source_type=? AND source_id=? LIMIT 1', [$tenantId, $type, $sourceId]);
}

function finance_overview(string $tenantId, ?string $from = null, ?string $to = null): array
{
    $receber = finance_sum($tenantId, 'receivable', ['open']);
    $previsto = finance_sum($tenantId, 'receivable', ['open', 'billed', 'paid']);
    $recebido = finance_sum($tenantId, 'receivable', ['paid', 'billed'], 'in', $from, $to)
        + finance_sum($tenantId, 'entry', ['paid'], 'in', $from, $to);
    $pagar = finance_sum($tenantId, 'payable', ['open']);
    $saidas = finance_sum($tenantId, 'entry', ['paid'], 'out', $from, $to)
        + finance_sum($tenantId, 'payable', ['paid'], 'out', $from, $to);
    $today = date('Y-m-d');
    $vencido = (float)(one(
        "SELECT COALESCE(SUM(amount),0) c FROM finance_entries WHERE tenant_id=? AND kind='receivable' AND status='open' AND due_date IS NOT NULL AND due_date<?",
        [$tenantId, $today]
    )['c'] ?? 0);
    return [
        'previsto' => $previsto,
        'receber' => $receber,
        'recebido' => $recebido,
        'vencido' => $vencido,
        'pagar' => $pagar,
        'saidas' => $saidas,
        'saldo' => $recebido - $saidas,
        'faturado' => finance_sum($tenantId, 'receivable', ['billed']),
    ];
}

function sync_appointment_finance(string $tenantId, string $appointmentId): void
{
    ensure_finance_schema();
    if ($tenantId === '' || $appointmentId === '') {
        return;
    }
    $appt = one(
        "SELECT a.*, s.name service_name, s.price service_price
         FROM appointments a
         LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
         WHERE a.id=? AND a.tenant_id=?",
        [$appointmentId, $tenantId]
    );
    if (!$appt) {
        return;
    }
    $amount = round((float)($appt['service_price'] ?? 0), 2);
    $due = substr((string)$appt['starts_at'], 0, 10);
    $desc = trim('Agendamento · '.((string)($appt['service_name'] ?? 'Serviço')));
    $row = finance_find_source($tenantId, 'appointment', $appointmentId);
    $cancelled = ($appt['status'] ?? '') === 'CANCELLED';

    if ($cancelled) {
        if ($row && in_array($row['status'], ['open', 'billed'], true)) {
            q("UPDATE finance_entries SET status='cancelled', updated_at=? WHERE id=? AND tenant_id=?", [now(), $row['id'], $tenantId]);
        } elseif ($row && $row['status'] === 'paid' && !finance_find_source($tenantId, 'appointment_reversal', $appointmentId)) {
            q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tenantId, 'entry', 'out', 'paid', 'Estorno · '.$desc, (float)$row['amount'], $due, now(),
                $appt['client_id'], 'Cancelamento após recebimento', 'appointment_reversal', $appointmentId, now(), now(),
            ]);
        }
        return;
    }

    if ($amount <= 0) {
        if ($row && in_array($row['status'], ['open', 'billed'], true)) {
            q("UPDATE finance_entries SET status='cancelled', updated_at=? WHERE id=? AND tenant_id=?", [now(), $row['id'], $tenantId]);
        }
        return;
    }

    if (!$row) {
        q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenantId, 'receivable', 'in', 'open', $desc, $amount, $due, null,
            $appt['client_id'], 'Gerado automaticamente pelo agendamento', 'appointment', $appointmentId, now(), now(),
        ]);
        return;
    }
    if (in_array($row['status'], ['open', 'billed'], true)) {
        q('UPDATE finance_entries SET description=?, amount=?, due_date=?, client_id=?, updated_at=? WHERE id=? AND tenant_id=? AND status IN (?,?)', [
            $desc, $amount, $due, $appt['client_id'], now(), $row['id'], $tenantId, 'open', 'billed',
        ]);
    }
}

function finance_backfill_appointments(string $tenantId): void
{
    static $done = [];
    if (isset($done[$tenantId])) {
        return;
    }
    $done[$tenantId] = true;
    ensure_finance_schema();
    $rows = all(
        "SELECT a.id FROM appointments a
         LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
         WHERE a.tenant_id=? AND COALESCE(s.price,0)>0
           AND NOT EXISTS (
             SELECT 1 FROM finance_entries f
             WHERE f.tenant_id=a.tenant_id AND f.source_type='appointment' AND f.source_id=a.id
           )",
        [$tenantId]
    );
    foreach ($rows as $row) {
        sync_appointment_finance($tenantId, $row['id']);
    }
    $cancel = all(
        "SELECT a.id FROM appointments a
         JOIN finance_entries f ON f.tenant_id=a.tenant_id AND f.source_type='appointment' AND f.source_id=a.id
         WHERE a.tenant_id=? AND a.status='CANCELLED' AND f.status IN ('open','billed')",
        [$tenantId]
    );
    foreach ($cancel as $row) {
        sync_appointment_finance($tenantId, $row['id']);
    }
}

function finance_query(string $tenantId, string $page, string $search = ''): array
{
    $sql = "SELECT f.*, c.name client_name, c.cpf client_cpf FROM finance_entries f
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
        $sql .= " AND f.status='open'";
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

function finance_set_status(string $tenantId, string $id, string $st): array
{
    $row = one('SELECT * FROM finance_entries WHERE id=? AND tenant_id=?', [$id, $tenantId]);
    if (!$row || !in_array($st, ['paid', 'billed', 'cancelled'], true)) {
        return ['ok' => false, 'message' => 'Não foi possível atualizar este registro.'];
    }
    if (in_array($row['status'], ['paid', 'cancelled'], true)) {
        return ['ok' => false, 'message' => 'Este registro já foi encerrado.'];
    }
    $method = finance_normalize_method($row['payment_method'] ?? '');
    if ($st === 'paid') {
        if ($row['kind'] !== 'receivable' && $row['kind'] !== 'payable') {
            return ['ok' => false, 'message' => 'Este tipo não recebe baixa por aqui.'];
        }
        if ($row['kind'] === 'receivable' && ($method === 'fatura' || $row['status'] === 'billed')) {
            return ['ok' => false, 'message' => 'Conta em fatura fica em A receber até o pagamento acordado e o clique em Faturar.'];
        }
        if ($row['status'] !== 'open') {
            return ['ok' => false, 'message' => 'Só é possível baixar contas em aberto.'];
        }
        q('UPDATE finance_entries SET status=?, paid_at=?, amount_paid=?, updated_at=? WHERE id=? AND tenant_id=?', [
            'paid', now(), (float)$row['amount'], now(), $row['id'], $tenantId,
        ]);
        return ['ok' => true, 'message' => $row['kind'] === 'payable' ? 'Pagamento registrado.' : 'Recebimento registrado.'];
    }
    if ($st === 'billed') {
        if ($row['kind'] !== 'receivable' || $row['status'] !== 'open') {
            return ['ok' => false, 'message' => 'Só é possível faturar contas a receber em aberto.'];
        }
        if ($method !== '' && $method !== 'fatura') {
            return ['ok' => false, 'message' => 'Faturar vale apenas quando a forma de pagamento é Fatura.'];
        }
        if (empty($row['client_id'])) {
            return ['ok' => false, 'message' => 'Fatura exige um cliente CPF ou CNPJ.'];
        }
        q('UPDATE finance_entries SET status=?, payment_method=?, paid_at=?, amount_paid=?, updated_at=? WHERE id=? AND tenant_id=?', [
            'billed', 'fatura', now(), (float)$row['amount'], now(), $row['id'], $tenantId,
        ]);
        return ['ok' => true, 'message' => 'Fatura confirmada. Saiu de A receber.'];
    }
    q("UPDATE finance_entries SET status='cancelled', updated_at=? WHERE id=? AND tenant_id=?", [now(), $row['id'], $tenantId]);
    return ['ok' => true, 'message' => 'Registro cancelado.'];
}
