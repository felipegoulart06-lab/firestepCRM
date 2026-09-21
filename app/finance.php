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

function finance_method_needs_receipt(?string $method): bool
{
    return in_array(finance_normalize_method($method), ['pix', 'cartao', 'transferencia'], true);
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
    finance_try('ALTER TABLE finance_entries ADD COLUMN agent_id VARCHAR(64)');
    finance_try('ALTER TABLE finance_entries ADD COLUMN pay_doc VARCHAR(80)');
    finance_try('ALTER TABLE finance_entries ADD COLUMN pay_installments INTEGER DEFAULT 1');
    finance_try('ALTER TABLE finance_entries ADD COLUMN pay_installment_amount DECIMAL(12,2) DEFAULT 0');
    finance_try('CREATE INDEX IF NOT EXISTS idx_finance_tenant ON finance_entries (tenant_id, kind, status)');
    finance_try('CREATE UNIQUE INDEX IF NOT EXISTS idx_finance_source ON finance_entries (tenant_id, source_type, source_id) WHERE source_id IS NOT NULL AND source_type IS NOT NULL');
    appointment_commission_ensure_schema();
    $ready = true;
}

function appointment_commission_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_agent_id text');
            db()->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_type text');
            db()->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_value numeric(12,2)');
            db()->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS commission_amount numeric(12,2)');
            db()->exec('ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reserva_n integer');
            db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS document_kind text');
            db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS cpf text');
            db()->exec('ALTER TABLE finance_entries ADD COLUMN IF NOT EXISTS agent_id text');
            return;
        }
        $appt = array_column(db()->query('PRAGMA table_info(appointments)')->fetchAll(), 'name');
        foreach (['commission_agent_id' => 'TEXT', 'commission_type' => 'TEXT', 'commission_value' => 'REAL', 'commission_amount' => 'REAL', 'reserva_n' => 'INTEGER'] as $col => $def) {
            if (!in_array($col, $appt, true)) {
                db()->exec("ALTER TABLE appointments ADD COLUMN $col $def");
            }
        }
        $users = array_column(db()->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        foreach (['document_kind' => 'TEXT', 'cpf' => 'TEXT'] as $col => $def) {
            if (!in_array($col, $users, true)) {
                db()->exec("ALTER TABLE users ADD COLUMN $col $def");
            }
        }
        $fin = array_column(db()->query('PRAGMA table_info(finance_entries)')->fetchAll(), 'name');
        if (!in_array('agent_id', $fin, true)) {
            db()->exec('ALTER TABLE finance_entries ADD COLUMN agent_id TEXT');
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function appointment_assign_reserva(string $tenantId, string $appointmentId): int
{
    appointment_commission_ensure_schema();
    $cur = one('SELECT reserva_n FROM appointments WHERE id=? AND tenant_id=?', [$appointmentId, $tenantId]);
    $n = (int)($cur['reserva_n'] ?? 0);
    if ($n > 0) {
        return $n;
    }
    $max = (int)(one('SELECT COALESCE(MAX(reserva_n),0) c FROM appointments WHERE tenant_id=?', [$tenantId])['c'] ?? 0);
    $n = $max + 1;
    q('UPDATE appointments SET reserva_n=? WHERE id=? AND tenant_id=?', [$n, $appointmentId, $tenantId]);
    return $n;
}

function appointment_backfill_reserva(string $tenantId): void
{
    appointment_commission_ensure_schema();
    $rows = all('SELECT id FROM appointments WHERE tenant_id=? AND (reserva_n IS NULL OR reserva_n=0) ORDER BY created_at ASC, starts_at ASC', [$tenantId]);
    foreach ($rows as $row) {
        appointment_assign_reserva($tenantId, (string)$row['id']);
    }
}

function appointment_reserva_label(?array $a): string
{
    $n = (int)($a['reserva_n'] ?? 0);
    return $n > 0 ? '#'.$n : '—';
}

function appointment_type_label(?array $a): string
{
    $st = (string)($a['finance_status'] ?? '');
    if ($st === 'billed') {
        return 'Faturado';
    }
    if ($st === 'paid') {
        $pay = finance_pay_label($a['payment_method'] ?? '');
        return $pay !== '—' ? $pay : 'Pago';
    }
    $pay = finance_pay_label($a['payment_method'] ?? '');
    if ($pay !== '—') {
        return $pay;
    }
    $kind = service_price_kind(['price_kind' => $a['price_kind'] ?? 'priced']);
    if ($kind === 'cortesia') {
        return 'Cortesia';
    }
    if ($kind === 'reuniao') {
        return 'Reunião';
    }
    return '—';
}

function appointment_value_label(?array $a): string
{
    $kind = service_price_kind(['price_kind' => $a['price_kind'] ?? 'priced']);
    if ($kind === 'cortesia' || $kind === 'reuniao') {
        return service_price_label(['price_kind' => $kind, 'price' => 0]);
    }
    $fin = (float)($a['finance_amount'] ?? 0);
    $price = (float)($a['service_price'] ?? 0);
    $n = $fin > 0 ? $fin : $price;
    return $n > 0 ? money($n) : '—';
}

function parse_appointment_commission(string $tenantId, float $servicePrice): array
{
    $on = in_array((string)post('commission_on', ''), ['1', 'on'], true);
    if (!$on) {
        return ['ok' => true, 'enabled' => false];
    }
    $agentId = (string)post('commission_agent_id', '');
    $agent = one("SELECT id, name FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$agentId, $tenantId]);
    if (!$agent) {
        return ['ok' => false, 'message' => 'Selecione o agente cadastrado para o repasse/comissão.'];
    }
    $type = strtolower((string)post('commission_type', ''));
    if (!in_array($type, ['fixed', 'percent'], true)) {
        return ['ok' => false, 'message' => 'Escolha comissão em valor fixo ou em porcentagem.'];
    }
    $raw = parse_money_input(post('commission_value'));
    if ($raw <= 0) {
        return ['ok' => false, 'message' => 'Informe o valor da comissão.'];
    }
    if ($servicePrice <= 0) {
        return ['ok' => false, 'message' => 'O serviço precisa ter preço para calcular o repasse.'];
    }
    if ($type === 'percent' && $raw > 100) {
        return ['ok' => false, 'message' => 'A porcentagem de comissão não pode passar de 100%.'];
    }
    $amount = $type === 'percent' ? round($servicePrice * ($raw / 100), 2) : round($raw, 2);
    if ($amount > $servicePrice + 0.001) {
        return ['ok' => false, 'message' => 'O repasse/comissão não pode ser maior que o valor do serviço.'];
    }
    return [
        'ok' => true,
        'enabled' => true,
        'agent_id' => (string)$agent['id'],
        'type' => $type,
        'value' => $raw,
        'amount' => $amount,
    ];
}

function store_appointment_commission(string $tenantId, string $appointmentId, array $parsed): void
{
    appointment_commission_ensure_schema();
    if (empty($parsed['enabled'])) {
        q('UPDATE appointments SET commission_agent_id=NULL, commission_type=NULL, commission_value=NULL, commission_amount=NULL WHERE id=? AND tenant_id=?', [
            $appointmentId, $tenantId,
        ]);
        return;
    }
    q('UPDATE appointments SET commission_agent_id=?, commission_type=?, commission_value=?, commission_amount=? WHERE id=? AND tenant_id=?', [
        $parsed['agent_id'], $parsed['type'], $parsed['value'], $parsed['amount'], $appointmentId, $tenantId,
    ]);
}

function finance_cancel_open(?array $row): void
{
    if ($row && in_array($row['status'], ['open', 'billed'], true)) {
        q("UPDATE finance_entries SET status='cancelled', updated_at=? WHERE id=? AND tenant_id=?", [
            now(), $row['id'], $row['tenant_id'],
        ]);
    }
}

function finance_sum_source(string $tenantId, string $sourceType, array $statuses): float
{
    $sql = 'SELECT COALESCE(SUM(amount),0) c FROM finance_entries WHERE tenant_id=? AND source_type=?';
    $p = [$tenantId, $sourceType];
    if ($statuses) {
        $sql .= ' AND status IN ('.implode(',', array_fill(0, count($statuses), '?')).')';
        array_push($p, ...$statuses);
    }
    return (float)(one($sql, $p)['c'] ?? 0);
}

function finance_source_label(?string $type, ?string $id): string
{
    if ($type === 'appointment' && $id) {
        return 'Agendamento #'.substr($id, 0, 8);
    }
    if ($type === 'appointment_commission' && $id) {
        return 'Repasse agente #'.substr($id, 0, 8);
    }
    if ($type === 'appointment_commission_reversal' && $id) {
        return 'Estorno de repasse #'.substr($id, 0, 8);
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
    $repasse = finance_sum_source($tenantId, 'appointment_commission', ['open', 'paid']);
    $saldo = $recebido - $saidas;
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
        'saldo' => $saldo,
        'faturado' => finance_sum($tenantId, 'receivable', ['billed']),
        'repasse' => $repasse,
        'repasse_aberto' => finance_sum_source($tenantId, 'appointment_commission', ['open']),
        'lucro_presumido' => $previsto - $repasse,
        'lucro_liquido' => $saldo,
    ];
}

function sync_appointment_finance(string $tenantId, string $appointmentId): void
{
    ensure_finance_schema();
    if ($tenantId === '' || $appointmentId === '') {
        return;
    }
    $appt = one(
        "SELECT a.*, s.name service_name, s.price service_price, ag.name commission_agent_name
         FROM appointments a
         LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
         LEFT JOIN users ag ON ag.id=a.commission_agent_id AND ag.tenant_id=a.tenant_id
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
    $comm = finance_find_source($tenantId, 'appointment_commission', $appointmentId);
    $cancelled = ($appt['status'] ?? '') === 'CANCELLED';
    $commAmount = round((float)($appt['commission_amount'] ?? 0), 2);
    $agentId = trim((string)($appt['commission_agent_id'] ?? ''));
    $agentName = trim((string)($appt['commission_agent_name'] ?? '')) ?: 'agente';
    $commDesc = 'Repasse/comissão · '.$agentName.' · '.((string)($appt['service_name'] ?? 'Serviço'));

    if ($cancelled) {
        finance_cancel_open($row);
        finance_cancel_open($comm);
        if ($row && $row['status'] === 'paid' && !finance_find_source($tenantId, 'appointment_reversal', $appointmentId)) {
            q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tenantId, 'entry', 'out', 'paid', 'Estorno · '.$desc, (float)$row['amount'], $due, now(),
                $appt['client_id'], 'Cancelamento após recebimento', 'appointment_reversal', $appointmentId, now(), now(),
            ]);
        }
        if ($comm && $comm['status'] === 'paid' && !finance_find_source($tenantId, 'appointment_commission_reversal', $appointmentId)) {
            q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,agent_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
                uid(), $tenantId, 'entry', 'in', 'paid', 'Estorno de repasse · '.$agentName, (float)$comm['amount'], $due, now(),
                $appt['client_id'], $agentId !== '' ? $agentId : ($comm['agent_id'] ?? null), 'Cancelamento após pagamento da comissão',
                'appointment_commission_reversal', $appointmentId, now(), now(),
            ]);
        }
        return;
    }

    if ($amount <= 0) {
        finance_cancel_open($row);
    } elseif (!$row) {
        q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenantId, 'receivable', 'in', 'open', $desc, $amount, $due, null,
            $appt['client_id'], 'Gerado automaticamente pelo agendamento', 'appointment', $appointmentId, now(), now(),
        ]);
    } elseif (in_array($row['status'], ['open', 'billed'], true)) {
        q('UPDATE finance_entries SET description=?, amount=?, due_date=?, client_id=?, updated_at=? WHERE id=? AND tenant_id=? AND status IN (?,?)', [
            $desc, $amount, $due, $appt['client_id'], now(), $row['id'], $tenantId, 'open', 'billed',
        ]);
    }

    $wantComm = $commAmount > 0 && $agentId !== '';
    if (!$wantComm) {
        finance_cancel_open($comm);
        return;
    }
    if (!$comm) {
        q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,agent_id,notes,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            uid(), $tenantId, 'payable', 'out', 'open', $commDesc, $commAmount, $due, null,
            $appt['client_id'], $agentId, 'Repasse automático ao agente', 'appointment_commission', $appointmentId, now(), now(),
        ]);
        return;
    }
    if (in_array($comm['status'], ['open', 'billed'], true)) {
        q('UPDATE finance_entries SET description=?, amount=?, due_date=?, client_id=?, agent_id=?, updated_at=? WHERE id=? AND tenant_id=? AND status IN (?,?)', [
            $commDesc, $commAmount, $due, $appt['client_id'], $agentId, now(), $comm['id'], $tenantId, 'open', 'billed',
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
    $sql = "SELECT f.*, c.name client_name, c.cpf client_cpf, c.phone client_phone, c.whatsapp client_whatsapp, u.name agent_name FROM finance_entries f
            LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
            LEFT JOIN users u ON u.id=f.agent_id AND u.tenant_id=f.tenant_id
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
        $sql .= ' AND (f.description LIKE ? OR COALESCE(c.name,\'\') LIKE ? OR COALESCE(u.name,\'\') LIKE ?)';
        $like = '%'.$search.'%';
        $p[] = $like;
        $p[] = $like;
        $p[] = $like;
    }
    $sql .= ' ORDER BY COALESCE(f.due_date, f.created_at) DESC, f.created_at DESC';
    return all($sql, $p);
}

function finance_set_status(string $tenantId, string $id, string $st, array $extra = []): array
{
    $row = one('SELECT * FROM finance_entries WHERE id=? AND tenant_id=?', [$id, $tenantId]);
    if (!$row || !in_array($st, ['paid', 'billed', 'cancelled'], true)) {
        return ['ok' => false, 'message' => 'Não foi possível atualizar este registro.'];
    }
    if (in_array($row['status'], ['paid', 'cancelled'], true)) {
        return ['ok' => false, 'message' => 'Este registro já foi encerrado.'];
    }
    $posted = finance_normalize_method($extra['payment_method'] ?? '');
    $method = $posted !== '' ? $posted : finance_normalize_method($row['payment_method'] ?? '');
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
        $paidAmt = isset($extra['amount_paid']) && is_numeric($extra['amount_paid'])
            ? round((float)$extra['amount_paid'], 2)
            : (float)$row['amount'];
        if ($paidAmt <= 0) {
            $paidAmt = (float)$row['amount'];
        }
        $doc = trim((string)($extra['pay_doc'] ?? ''));
        $installments = max(1, min(24, (int)($extra['pay_installments'] ?? 1)));
        $instAmt = isset($extra['pay_installment_amount']) && is_numeric($extra['pay_installment_amount'])
            ? round((float)$extra['pay_installment_amount'], 2)
            : ($installments > 1 ? round($paidAmt / $installments, 2) : 0.0);
        if ($row['kind'] === 'receivable') {
            if ($method === '') {
                return ['ok' => false, 'message' => 'Selecione como o pagamento foi feito.'];
            }
            if (finance_method_needs_receipt($method)) {
                if ($doc === '' || strlen($doc) > 80) {
                    return ['ok' => false, 'message' => 'Informe o DOC ou NSU do PIX, cartão ou transferência.'];
                }
                if ($installments > 1 && $instAmt <= 0) {
                    return ['ok' => false, 'message' => 'Informe o valor da parcela.'];
                }
            } else {
                $doc = '';
                $installments = 1;
                $instAmt = 0.0;
            }
        }
        q('UPDATE finance_entries SET status=?, payment_method=?, paid_at=?, amount_paid=?, pay_doc=?, pay_installments=?, pay_installment_amount=?, updated_at=? WHERE id=? AND tenant_id=?', [
            'paid', $method !== '' ? $method : ($row['payment_method'] ?? null), now(), $paidAmt, $doc !== '' ? $doc : null, $installments, $instAmt, now(), $row['id'], $tenantId,
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

function finance_charge_card(array $tenant, array $entry): array
{
    $cfg = uazapi_config($tenant);
    $lh = letterhead_config($tenant);
    $name = trim((string)($entry['client_name'] ?? ''));
    $phoneRaw = (string)($entry['client_whatsapp'] ?? '') ?: (string)($entry['client_phone'] ?? '');
    $amount = money((float)($entry['amount'] ?? 0));
    $due = !empty($entry['due_date']) ? date('d/m/Y', strtotime((string)$entry['due_date'])) : 'sem vencimento';
    $company = (string)($tenant['display_name'] ?: $tenant['business_name'] ?: 'FirestepCRM');
    $title = 'Cobrança · '.$company;
    $desc = ($name !== '' ? $name : 'Cliente').' está com '.$amount.' em aberto referente a '.(string)($entry['description'] ?? 'serviço').'. Vencimento: '.$due.'.';
    $image = trim((string)($cfg['image'] ?? ''));
    if ($image === '') {
        $image = trim((string)($lh['logo'] ?? ''));
    }
    $buttons = [];
    $buttonsApi = [];
    if ($cfg['pix_key'] !== '') {
        $buttons[] = ['label' => 'Copiar PIX', 'hint' => 'copy', 'type' => 'COPY', 'value' => $cfg['pix_key']];
        $buttonsApi[] = ['id' => $cfg['pix_key'], 'text' => 'Copiar PIX', 'type' => 'COPY'];
    }
    $call = uazapi_wa_number((string)($tenant['whatsapp'] ?: $tenant['phone'] ?: ($lh['phone'] ?? '')));
    if ($call !== '') {
        $buttons[] = ['label' => 'Ligar', 'hint' => 'call', 'type' => 'CALL', 'value' => '+'.$call];
        $buttonsApi[] = ['id' => '+'.$call, 'text' => 'Ligar', 'type' => 'CALL'];
    }
    $site = trim((string)($tenant['website'] ?? ''));
    if ($site !== '' && preg_match('#^https?://#i', $site)) {
        $buttons[] = ['label' => 'Site', 'hint' => 'url', 'type' => 'URL', 'value' => $site];
        $buttonsApi[] = ['id' => $site, 'text' => 'Site', 'type' => 'URL'];
    }
    $buttons[] = ['label' => 'Já paguei', 'hint' => 'reply', 'type' => 'REPLY', 'value' => 'ja_paguei'];
    $buttonsApi[] = ['id' => 'ja_paguei', 'text' => 'Já paguei', 'type' => 'REPLY'];
    if (count($buttons) > 3) {
        $buttons = array_values(array_merge(array_slice($buttons, 0, 2), [end($buttons)]));
        $buttonsApi = array_values(array_merge(array_slice($buttonsApi, 0, 2), [end($buttonsApi)]));
    }
    return [
        'title' => $title,
        'description' => $desc,
        'image' => $image,
        'name' => $name !== '' ? $name : '—',
        'phone' => phone_fmt($phoneRaw),
        'phone_raw' => $phoneRaw,
        'amount' => $amount,
        'buttons' => $buttons,
        'buttons_api' => $buttonsApi,
        'can_send' => uazapi_wa_number($phoneRaw) !== '',
    ];
}

function finance_charge_clip(string $s, int $max): string
{
    $s = trim($s);
    if (function_exists('mb_substr')) {
        return (string)mb_substr($s, 0, $max);
    }
    return substr($s, 0, $max);
}

function finance_charge_sanitize_image(string $src): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }
    if (preg_match('#^https://#i', $src)) {
        return finance_charge_clip($src, 2000);
    }
    if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\s]+$#i', $src)) {
        return $src;
    }
    return '';
}

function finance_charge_normalize_buttons(array $texts, array $types, array $values): array
{
    $out = [];
    $api = [];
    $n = max(count($texts), count($types), count($values));
    for ($i = 0; $i < $n && count($out) < 3; $i++) {
        $label = finance_charge_clip((string)($texts[$i] ?? ''), 20);
        if ($label === '') {
            continue;
        }
        $type = strtoupper(trim((string)($types[$i] ?? 'REPLY')));
        if (!in_array($type, ['URL', 'CALL', 'COPY', 'REPLY'], true)) {
            $type = 'REPLY';
        }
        $raw = trim((string)($values[$i] ?? ''));
        $id = $raw;
        if ($type === 'URL') {
            if ($id !== '' && !preg_match('#^https?://#i', $id)) {
                $id = 'https://'.$id;
            }
            if ($id === '' || !preg_match('#^https?://[^\s]+$#i', $id)) {
                continue;
            }
            $id = finance_charge_clip($id, 500);
        } elseif ($type === 'CALL') {
            $num = uazapi_wa_number($id);
            if ($num === '') {
                continue;
            }
            $id = '+'.$num;
        } elseif ($type === 'COPY') {
            $id = finance_charge_clip($id !== '' ? $id : $label, 400);
        } else {
            $id = finance_charge_clip($id !== '' ? $id : $label, 80);
        }
        $hint = match ($type) {
            'URL' => 'url',
            'CALL' => 'call',
            'COPY' => 'copy',
            default => 'reply',
        };
        $out[] = ['label' => $label, 'type' => $type, 'value' => $id, 'hint' => $hint];
        $api[] = ['id' => $id, 'text' => $label, 'type' => $type];
    }
    return ['buttons' => $out, 'buttons_api' => $api];
}

function finance_charge_apply_edits(array $card, array $in): array
{
    if (trim((string)($in['charge_edited'] ?? '')) !== '1') {
        return $card;
    }
    $title = finance_charge_clip((string)($in['charge_title'] ?? ''), 120);
    if ($title !== '') {
        $card['title'] = $title;
    }
    $desc = finance_charge_clip((string)($in['charge_description'] ?? ''), 700);
    if ($desc !== '') {
        $card['description'] = $desc;
    }
    $card['image'] = finance_charge_sanitize_image((string)($in['charge_image'] ?? ''));
    $norm = finance_charge_normalize_buttons(
        is_array($in['charge_btn_text'] ?? null) ? $in['charge_btn_text'] : [],
        is_array($in['charge_btn_type'] ?? null) ? $in['charge_btn_type'] : [],
        is_array($in['charge_btn_value'] ?? null) ? $in['charge_btn_value'] : []
    );
    $card['buttons'] = $norm['buttons'];
    $card['buttons_api'] = $norm['buttons_api'];
    return $card;
}

