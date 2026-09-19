<?php
declare(strict_types=1);

const REPORT_KINDS = [
    'atendimentos', 'agendamentos', 'solicitacoes', 'clientes', 'agentes', 'servicos',
    'origens', 'financeiro', 'cliente_resumo', 'contratos', 'abrangencia', 'fornecedores',
    'documentos', 'documentos_cpf', 'documentos_cnpj', 'documentos_agentes',
];

function report_date(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

function report_id_list(mixed $raw): array
{
    if (is_string($raw) && $raw !== '') {
        $raw = explode(',', $raw);
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $id) {
        $id = trim((string)$id);
        if ($id !== '' && preg_match('/^[A-Za-z0-9._-]{1,80}$/', $id)) {
            $out[] = $id;
        }
    }
    return array_values(array_unique($out));
}

function report_kinds_from_request(): array
{
    $raw = $_GET['types'] ?? $_GET['type'] ?? [];
    if (is_string($raw)) {
        $raw = $raw === '' ? [] : explode(',', $raw);
    }
    $kinds = [];
    foreach ((array)$raw as $kind) {
        $kind = trim((string)$kind);
        if (in_array($kind, REPORT_KINDS, true)) {
            $kinds[] = $kind;
        }
    }
    $kinds = array_values(array_unique($kinds));
    if ($kinds) {
        return [$kinds[0]];
    }
    $path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    return match ($path) {
        '/app/relatorios/atendimentos.pdf' => ['atendimentos'],
        '/app/relatorios/agendamentos.pdf' => ['agendamentos'],
        '/app/relatorios/solicitacoes.pdf' => ['solicitacoes'],
        '/app/relatorios/clientes.pdf' => ['clientes'],
        '/app/relatorios/agentes.pdf' => ['agentes'],
        '/app/relatorios/servicos.pdf' => ['servicos'],
        '/app/relatorios/origens.pdf' => ['origens'],
        '/app/relatorios/contrato.pdf' => ['contratos'],
        '/app/relatorios/fornecedores.pdf' => ['fornecedores'],
        '/app/relatorios/documentos.pdf' => ['documentos'],
        '/app/financeiro/relatorio.pdf' => ['financeiro'],
        default => ['atendimentos'],
    };
}

function report_filters_from_request(): array
{
    $from = report_date($_GET['from'] ?? null, date('Y-m-01'));
    $to = report_date($_GET['to'] ?? null, date('Y-m-d'));
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $period = max(1, min(365, (int)($_GET['period'] ?? 0)));
    if ($period > 0 && empty($_GET['from']) && empty($_GET['to'])) {
        $from = date('Y-m-d', strtotime('-'.$period.' days'));
        $to = date('Y-m-d');
    }
    $appt = strtoupper(trim((string)($_GET['appt_status'] ?? 'ALL')));
    if (!isset(APPT_STATUS[$appt])) {
        $appt = 'ALL';
    }
    $client = strtoupper(trim((string)($_GET['client_status'] ?? $_GET['status'] ?? 'ALL')));
    if (!in_array($client, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
        $client = 'ALL';
    }
    $fin = strtolower(trim((string)($_GET['finance_status'] ?? 'ALL')));
    if (!in_array($fin, ['all', 'open', 'paid', 'billed', 'cancelled'], true)) {
        $fin = 'all';
    }
    $req = strtoupper(trim((string)($_GET['request_status'] ?? 'ALL')));
    if ($req !== 'ALL' && !isset(REQ_STATUS[$req])) {
        $req = 'ALL';
    }
    $source = trim((string)($_GET['source'] ?? ''));
    if (strlen($source) > 80) {
        $source = substr($source, 0, 80);
    }
    $category = trim((string)($_GET['category'] ?? ''));
    if (strlen($category) > 80) {
        $category = substr($category, 0, 80);
    }
    $city = trim((string)($_GET['city'] ?? ''));
    if (strlen($city) > 80) {
        $city = substr($city, 0, 80);
    }
    $state = strtoupper(trim((string)($_GET['state'] ?? '')));
    if (!preg_match('/^[A-Z]{2}$/', $state)) {
        $state = '';
    }
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) > 80) {
        $q = substr($q, 0, 80);
    }
    $agentStatus = strtoupper(trim((string)($_GET['agent_status'] ?? 'ALL')));
    if (!in_array($agentStatus, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
        $agentStatus = 'ALL';
    }
    $agentRole = strtolower(trim((string)($_GET['agent_role'] ?? 'user_agent')));
    if (!in_array($agentRole, ['all', 'user_agent', 'user_crm'], true)) {
        $agentRole = 'user_agent';
    }
    $serviceStatus = strtoupper(trim((string)($_GET['service_status'] ?? 'ALL')));
    if (!in_array($serviceStatus, ['ALL', 'ACTIVE', 'INACTIVE'], true)) {
        $serviceStatus = 'ALL';
    }
    $finKind = strtolower(trim((string)($_GET['finance_kind'] ?? 'all')));
    if (!in_array($finKind, ['all', 'receivable', 'payable'], true)) {
        $finKind = 'all';
    }
    $finFlow = strtolower(trim((string)($_GET['finance_flow'] ?? 'all')));
    if (!in_array($finFlow, ['all', 'in', 'out'], true)) {
        $finFlow = 'all';
    }
    $finDate = strtolower(trim((string)($_GET['finance_date'] ?? 'competence')));
    if (!in_array($finDate, ['competence', 'caixa'], true)) {
        $finDate = 'competence';
    }
    $pay = strtolower(trim((string)($_GET['payment_method'] ?? '')));
    if ($pay !== '' && !isset(FINANCE_PAY_METHODS[$pay])) {
        $pay = '';
    }
    $docKind = strtolower(trim((string)($_GET['document_kind'] ?? 'all')));
    if (!in_array($docKind, ['all', 'cpf', 'cnpj'], true)) {
        $docKind = 'all';
    }
    $docCheck = strtolower(trim((string)($_GET['doc_check'] ?? 'all')));
    if (!in_array($docCheck, ['all', 'pending', 'validated'], true)) {
        $docCheck = 'all';
    }
    $product = trim((string)($_GET['product_type'] ?? ''));
    if (strlen($product) > 80) {
        $product = substr($product, 0, 80);
    }
    $layer = strtolower(trim((string)($_GET['coverage_layer'] ?? 'all')));
    if (!in_array($layer, ['all', 'suppliers', 'clients', 'visits'], true)) {
        $layer = 'all';
    }
    $ageMin = isset($_GET['age_min']) && $_GET['age_min'] !== '' ? (int)$_GET['age_min'] : null;
    $ageMax = isset($_GET['age_max']) && $_GET['age_max'] !== '' ? (int)$_GET['age_max'] : null;
    if ($ageMin !== null) {
        $ageMin = max(0, min(120, $ageMin));
    }
    if ($ageMax !== null) {
        $ageMax = max(0, min(120, $ageMax));
    }
    $minAmount = isset($_GET['min_amount']) && is_numeric($_GET['min_amount']) ? (float)$_GET['min_amount'] : null;
    $maxAmount = isset($_GET['max_amount']) && is_numeric($_GET['max_amount']) ? (float)$_GET['max_amount'] : null;
    $apptType = strtolower(trim((string)($_GET['appt_type'] ?? 'all')));
    if (!in_array($apptType, ['all', 'billed', 'paid', 'cortesia', 'convenio', 'reuniao', 'priced'], true)) {
        $apptType = 'all';
    }
    $reservaRaw = preg_replace('/\D+/', '', (string)($_GET['reserva'] ?? ''));
    $reservaN = $reservaRaw !== '' ? (int)$reservaRaw : null;
    if ($reservaN !== null && $reservaN < 1) {
        $reservaN = null;
    }
    return [
        'from' => $from,
        'to' => $to,
        'appt_status' => $appt,
        'client_status' => $client,
        'finance_status' => $fin,
        'request_status' => $req,
        'source' => $source,
        'category' => $category,
        'city' => $city,
        'state' => $state,
        'q' => $q,
        'agent_status' => $agentStatus,
        'agent_role' => $agentRole,
        'service_status' => $serviceStatus,
        'finance_kind' => $finKind,
        'finance_flow' => $finFlow,
        'finance_date' => $finDate,
        'payment_method' => $pay,
        'document_kind' => $docKind,
        'doc_check' => $docCheck,
        'product_type' => $product,
        'coverage_layer' => $layer,
        'age_min' => $ageMin,
        'age_max' => $ageMax,
        'min_amount' => $minAmount,
        'max_amount' => $maxAmount,
        'appt_type' => $apptType,
        'reserva_n' => $reservaN,
        'user_ids' => report_id_list($_GET['users'] ?? []),
        'admins_only' => ($_GET['admins'] ?? '') === '1',
        'service_ids' => report_id_list($_GET['services'] ?? []),
        'kinds' => report_kinds_from_request(),
        'include_totals' => ($_GET['totals'] ?? '1') !== '0',
        'include_client_summary' => ($_GET['client_summary'] ?? '') === '1',
        'contract_id' => report_id_list($_GET['contract_id'] ?? [])[0] ?? '',
    ];
}

function report_age_years(?string $birth): ?int
{
    $birth = trim((string)$birth);
    if ($birth === '') {
        return null;
    }
    $t = strtotime(substr($birth, 0, 10));
    if ($t === false) {
        return null;
    }
    return (int) floor((time() - $t) / 31557600);
}

function report_match_status(?string $status, string $want): bool
{
    if ($want === 'ALL') {
        return true;
    }
    $isInactive = strtoupper((string)$status) === 'INACTIVE' || $status === '0';
    return $want === 'INACTIVE' ? $isInactive : !$isInactive;
}

function report_match_geo(array $row, array $filters): bool
{
    if (($filters['state'] ?? '') !== '' && strtoupper(trim((string)($row['state'] ?? ''))) !== $filters['state']) {
        return false;
    }
    $city = $filters['city'] ?? '';
    if ($city !== '' && !str_contains(strtolower((string)($row['city'] ?? '')), strtolower($city))) {
        return false;
    }
    return true;
}

function report_match_doc(?string $value, string $want): bool
{
    $has = trim((string)$value) !== '';
    return match ($want) {
        'pending' => !$has,
        'validated' => $has,
        default => true,
    };
}

function report_match_amount(float $value, array $filters): bool
{
    if (($filters['min_amount'] ?? null) !== null && $value < $filters['min_amount']) {
        return false;
    }
    if (($filters['max_amount'] ?? null) !== null && $value > $filters['max_amount']) {
        return false;
    }
    return true;
}

function report_match_age(?int $age, array $filters): bool
{
    if (($filters['age_min'] ?? null) !== null && ($age === null || $age < $filters['age_min'])) {
        return false;
    }
    if (($filters['age_max'] ?? null) !== null && ($age === null || $age > $filters['age_max'])) {
        return false;
    }
    return true;
}

function report_actor_map(string $tenantId, string $action): array
{
    $rows = all(
        "SELECT entity_id, user_id FROM audit_logs WHERE tenant_id=? AND action=? ORDER BY created_at",
        [$tenantId, $action]
    );
    $map = [];
    foreach ($rows as $row) {
        if (!empty($row['entity_id'])) {
            $map[(string)$row['entity_id']] = (string)($row['user_id'] ?? '');
        }
    }
    return $map;
}

function report_user_roles(string $tenantId): array
{
    $map = [];
    foreach (all('SELECT id, role FROM users WHERE tenant_id=?', [$tenantId]) as $row) {
        $map[(string)$row['id']] = (string)$row['role'];
    }
    return $map;
}

function report_match_actor(?string $actorId, array $filters, array $roles): bool
{
    $actorId = $actorId ?: null;
    if ($filters['admins_only']) {
        if (!$actorId || !in_array($roles[$actorId] ?? '', ['user_crm', 'TENANT_ADMIN'], true)) {
            return false;
        }
    }
    if ($filters['user_ids']) {
        return $actorId !== null && in_array($actorId, $filters['user_ids'], true);
    }
    return true;
}

function report_match_appointment_people(array $row, ?string $actorId, array $filters, array $roles): bool
{
    $adminFilters = $filters;
    $adminFilters['user_ids'] = [];
    if (!report_match_actor($actorId, $adminFilters, $roles)) {
        return false;
    }
    if (!$filters['user_ids']) {
        return true;
    }
    $agentId = trim((string)($row['commission_agent_id'] ?? ''));
    return ($actorId !== null && in_array($actorId, $filters['user_ids'], true))
        || ($agentId !== '' && in_array($agentId, $filters['user_ids'], true));
}

function report_match_appt_type(array $row, string $want): bool
{
    if ($want === 'all') {
        return true;
    }
    $st = (string)($row['finance_status'] ?? '');
    $kind = service_price_kind(['price_kind' => $row['price_kind'] ?? 'priced']);
    return match ($want) {
        'billed' => $st === 'billed',
        'paid' => $st === 'paid',
        'cortesia' => $kind === 'cortesia',
        'convenio' => $kind === 'convenio',
        'reuniao' => $kind === 'reuniao',
        'priced' => $kind === 'priced' && $st !== 'billed',
        default => true,
    };
}

function report_section(string $title, array $headers, array $rows, array $foot = []): array
{
    return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'foot' => $foot];
}

function report_atendimentos(array $tenant, array $filters): array
{
    appointment_commission_ensure_schema();
    ensure_finance_schema();
    $tid = $tenant['id'];
    appointment_backfill_reserva($tid);
    $sql = "SELECT a.*, c.name client_name, s.name service_name, s.price service_price, s.price_kind,
            ag.name agent_name, f.status finance_status, f.payment_method, f.amount finance_amount
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        LEFT JOIN users ag ON ag.id=a.commission_agent_id AND ag.tenant_id=a.tenant_id
        LEFT JOIN finance_entries f ON f.id=(
            SELECT id FROM finance_entries
            WHERE tenant_id=a.tenant_id AND source_type='appointment' AND source_id=a.id
            ORDER BY created_at DESC LIMIT 1
        )
        WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<=?";
    $params = [$tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59'];
    if ($filters['appt_status'] !== 'ALL') {
        $sql .= ' AND a.status=?';
        $params[] = $filters['appt_status'];
    }
    if (($filters['source'] ?? '') !== '') {
        $sql .= ' AND a.source=?';
        $params[] = $filters['source'];
    }
    if (($filters['reserva_n'] ?? null) !== null) {
        $sql .= ' AND a.reserva_n=?';
        $params[] = $filters['reserva_n'];
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND a.service_id IN ($ph)";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= ' ORDER BY a.starts_at';
    $rows = all($sql, $params);
    $actors = report_actor_map($tid, 'appointment.created');
    $roles = report_user_roles($tid);
    $typeWant = (string)($filters['appt_type'] ?? 'all');
    $rows = array_values(array_filter($rows, static function (array $r) use ($actors, $filters, $roles, $typeWant) {
        if (!report_match_appointment_people($r, $actors[$r['id']] ?? null, $filters, $roles)) {
            return false;
        }
        return report_match_appt_type($r, $typeWant);
    }));

    $headers = ['N° reserva', 'Data', 'Cliente', 'Agente', 'Serviço', 'Valor', 'Tipo', 'Status'];
    $table = [];
    $byClient = [];
    foreach ($rows as $r) {
        $status = APPT_STATUS[$r['status']][0] ?? $r['status'];
        $table[] = [
            appointment_reserva_label($r),
            date('d/m/Y H:i', strtotime((string)$r['starts_at'])),
            (string)$r['client_name'],
            (string)($r['agent_name'] ?: '—'),
            (string)($r['service_name'] ?: 'Sem serviço'),
            appointment_value_label($r),
            appointment_type_label($r),
            (string)$status,
        ];
        $name = (string)$r['client_name'];
        $byClient[$name] = ($byClient[$name] ?? 0) + 1;
    }
    $secTitle = trim((string)($filters['section_title'] ?? '')) ?: 'Atendimentos';
    $foot = $filters['include_totals'] ? ['Total: '.count($table)] : [];
    $sections = [report_section($secTitle, $headers, $table, $foot)];
    if (!empty($filters['_client_summary_only']) || ($filters['include_client_summary'] && !in_array('cliente_resumo', $filters['kinds'], true))) {
        ksort($byClient, SORT_NATURAL | SORT_FLAG_CASE);
        $sumRows = [];
        foreach ($byClient as $name => $total) {
            $sumRows[] = [$name, (string)$total];
        }
        $sections[] = report_section('Resumo por cliente', ['Cliente', 'Atendimentos'], $sumRows);
    }
    return $sections;
}

function report_clientes(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $sql = "SELECT c.*, COUNT(a.id) total FROM clients c
        LEFT JOIN appointments a ON a.client_id=c.id AND a.tenant_id=c.tenant_id AND a.status!='CANCELLED'
          AND a.starts_at>=? AND a.starts_at<=?
        WHERE c.tenant_id=? AND c.created_at>=? AND c.created_at<=?";
    $params = [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59', $tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59'];
    if ($filters['client_status'] !== 'ALL') {
        $sql .= ' AND c.status=?';
        $params[] = $filters['client_status'];
    }
    if (($filters['source'] ?? '') !== '') {
        $sql .= " AND COALESCE(NULLIF(c.utm_source,''), c.source)=?";
        $params[] = $filters['source'];
    }
    if (($filters['state'] ?? '') !== '') {
        $sql .= ' AND UPPER(TRIM(c.state))=?';
        $params[] = $filters['state'];
    }
    if (($filters['city'] ?? '') !== '') {
        $sql .= ' AND LOWER(c.city) LIKE LOWER(?)';
        $params[] = '%'.$filters['city'].'%';
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND EXISTS (SELECT 1 FROM appointments ax WHERE ax.client_id=c.id AND ax.tenant_id=c.tenant_id AND ax.service_id IN ($ph))";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= ' GROUP BY c.id ORDER BY c.name';
    $rows = all($sql, $params);
    $actors = report_actor_map($tid, 'client.created');
    $roles = report_user_roles($tid);
    $rows = array_values(array_filter($rows, static fn(array $r) => report_match_actor($actors[$r['id']] ?? null, $filters, $roles)));

    $table = [];
    foreach ($rows as $r) {
        $st = ($r['status'] ?? '') === 'INACTIVE' ? 'Inativo' : 'Ativo';
        $table[] = [
            (string)$r['name'],
            (string)($r['phone'] ?: $r['email'] ?: 'sem contato'),
            (string)($r['source'] ?: 'Não informado'),
            (string)$r['total'],
            $st,
        ];
    }
    $foot = $filters['include_totals'] ? ['Cadastros: '.count($table)] : [];
    return [report_section('Clientes', ['Nome', 'Contato', 'Origem', 'Atend.', 'Status'], $table, $foot)];
}

function report_agendamentos(array $tenant, array $filters): array
{
    $copy = $filters;
    $copy['section_title'] = 'Agendamentos';
    return report_atendimentos($tenant, $copy);
}

function report_solicitacoes(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $sql = "SELECT r.*, s.name service_name FROM requests r
        LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id
        WHERE r.tenant_id=? AND r.created_at>=? AND r.created_at<=?";
    $params = [$tid, $from, $to];
    if (($filters['request_status'] ?? 'ALL') !== 'ALL') {
        $sql .= ' AND r.status=?';
        $params[] = $filters['request_status'];
    }
    if (($filters['source'] ?? '') !== '') {
        $sql .= " AND COALESCE(NULLIF(r.utm_source,''), r.source)=?";
        $params[] = $filters['source'];
    }
    if (($filters['q'] ?? '') !== '') {
        $like = '%'.$filters['q'].'%';
        $sql .= ' AND (r.id LIKE ? OR r.name LIKE ? OR r.phone LIKE ? OR r.email LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND r.service_id IN ($ph)";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= ' ORDER BY r.created_at';
    $rows = all($sql, $params);
    $table = [];
    foreach ($rows as $r) {
        $desired = trim((string)($r['desired_date'] ?? ''));
        if ($desired !== '' && !empty($r['desired_time'])) {
            $desired .= ' '.$r['desired_time'];
        }
        $source = (string)($r['utm_source'] ?: $r['source'] ?: 'Não informado');
        $table[] = [
            date('d/m/Y H:i', strtotime((string)$r['created_at'])),
            (string)$r['name'],
            phone_fmt($r['phone'] ?? '') ?: '—',
            (string)($r['service_name'] ?: 'Sem serviço'),
            $desired !== '' ? $desired : '—',
            $source,
            (string)(REQ_STATUS[$r['status']] ?? $r['status']),
        ];
    }
    $foot = $filters['include_totals'] ? ['Solicitações: '.count($table)] : [];
    return [report_section('Solicitações', ['Recebida em', 'Nome', 'Telefone', 'Serviço', 'Desejada', 'Origem', 'Status'], $table, $foot)];
}

function report_agentes(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.active,
        (SELECT COUNT(*) FROM audit_logs al
         INNER JOIN appointments a ON a.id=al.entity_id AND a.tenant_id=al.tenant_id
         WHERE al.tenant_id=u.tenant_id AND al.user_id=u.id AND al.action='appointment.created'
           AND a.starts_at>=? AND a.starts_at<=?) appt_count,
        (SELECT COUNT(*) FROM audit_logs al
         INNER JOIN clients c ON c.id=al.entity_id AND c.tenant_id=al.tenant_id
         WHERE al.tenant_id=u.tenant_id AND al.user_id=u.id AND al.action='client.created'
           AND c.created_at>=? AND c.created_at<=?) client_count
        FROM users u
        WHERE u.tenant_id=?";
    $params = [$from, $to, $from, $to, $tid];
    $role = $filters['agent_role'] ?? 'user_agent';
    if ($role === 'user_crm') {
        $sql .= " AND u.role IN ('user_crm','TENANT_ADMIN')";
    } elseif ($role === 'all') {
        $sql .= " AND u.role IN ('user_agent','user_crm','TENANT_ADMIN','user_admin')";
    } else {
        $sql .= " AND u.role='user_agent'";
    }
    if (($filters['agent_status'] ?? 'ALL') === 'ACTIVE') {
        $sql .= ' AND '.sql_true('u.active');
    } elseif (($filters['agent_status'] ?? 'ALL') === 'INACTIVE') {
        $sql .= ' AND NOT ('.sql_true('u.active').')';
    }
    if (($filters['q'] ?? '') !== '') {
        $like = '%'.$filters['q'].'%';
        $sql .= ' AND (u.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if ($filters['user_ids']) {
        $ph = implode(',', array_fill(0, count($filters['user_ids']), '?'));
        $sql .= " AND u.id IN ($ph)";
        $params = array_merge($params, $filters['user_ids']);
    }
    $sql .= ' ORDER BY u.name';
    $table = [];
    foreach (all($sql, $params) as $r) {
        $table[] = [
            (string)$r['name'],
            (string)($r['email'] ?: '—'),
            phone_fmt($r['phone'] ?? '') ?: '—',
            !empty($r['active']) ? 'Ativo' : 'Inativo',
            (string)(int)$r['appt_count'],
            (string)(int)$r['client_count'],
        ];
    }
    $foot = $filters['include_totals'] ? ['Agentes: '.count($table)] : [];
    return [report_section('Agentes', ['Nome', 'E-mail', 'Telefone', 'Situação', 'Agend.', 'Cadastros'], $table, $foot)];
}

function report_servicos(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $sql = "SELECT s.*, COUNT(a.id) appt_total
        FROM services s
        LEFT JOIN appointments a ON a.service_id=s.id AND a.tenant_id=s.tenant_id
          AND a.starts_at>=? AND a.starts_at<=? AND a.status!='CANCELLED'
        WHERE s.tenant_id=?";
    $params = [$from, $to, $tid];
    if (($filters['category'] ?? '') !== '') {
        $sql .= ' AND s.category=?';
        $params[] = $filters['category'];
    }
    if (($filters['service_status'] ?? 'ALL') !== 'ALL') {
        $sql .= ' AND s.status=?';
        $params[] = $filters['service_status'];
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND s.id IN ($ph)";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= ' GROUP BY s.id ORDER BY s.name';
    $table = [];
    foreach (all($sql, $params) as $r) {
        $st = ($r['status'] ?? '') === 'INACTIVE' ? 'Inativo' : 'Ativo';
        $table[] = [
            (string)$r['name'],
            (string)((int)($r['duration_minutes'] ?? 0)).' min',
            service_price_label($r),
            (string)(int)$r['appt_total'],
            $st,
        ];
    }
    $foot = $filters['include_totals'] ? ['Serviços: '.count($table)] : [];
    return [report_section('Serviços', ['Nome', 'Duração', 'Preço', 'Agend.', 'Status'], $table, $foot)];
}

function report_origens(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $srcSql = '';
    $srcParams = [];
    if (($filters['source'] ?? '') !== '') {
        $srcSql = " AND COALESCE(NULLIF(utm_source,''), source, 'Não informado')=?";
        $srcParams[] = $filters['source'];
    }
    $clients = all(
        "SELECT COALESCE(NULLIF(utm_source,''), source, 'Não informado') source, COUNT(*) total
         FROM clients WHERE tenant_id=? AND created_at>=? AND created_at<=?".$srcSql."
         GROUP BY COALESCE(NULLIF(utm_source,''), source, 'Não informado')",
        array_merge([$tid, $from, $to], $srcParams)
    );
    $requests = all(
        "SELECT COALESCE(NULLIF(utm_source,''), source, 'Não informado') source, COUNT(*) total
         FROM requests WHERE tenant_id=? AND created_at>=? AND created_at<=?".$srcSql."
         GROUP BY COALESCE(NULLIF(utm_source,''), source, 'Não informado')",
        array_merge([$tid, $from, $to], $srcParams)
    );
    $map = [];
    foreach ($clients as $r) {
        $map[$r['source']] = [(int)$r['total'], 0];
    }
    foreach ($requests as $r) {
        $cur = $map[$r['source']] ?? [0, 0];
        $cur[1] = (int)$r['total'];
        $map[$r['source']] = $cur;
    }
    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    $table = [];
    $cTot = 0;
    $rTot = 0;
    foreach ($map as $source => [$c, $req]) {
        $table[] = [(string)$source, (string)$c, (string)$req];
        $cTot += $c;
        $rTot += $req;
    }
    $foot = $filters['include_totals'] ? ['Cadastros: '.$cTot.' · Solicitações: '.$rTot] : [];
    return [report_section('Origens', ['Canal', 'Cadastros', 'Solicitações'], $table, $foot)];
}

function report_financeiro(array $tenant, array $filters): array
{
    ensure_finance_schema();
    $tid = $tenant['id'];
    $sql = "SELECT f.*, c.name client_name, u.name agent_name, a.service_id
        FROM finance_entries f
        LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
        LEFT JOIN users u ON u.id=f.agent_id AND u.tenant_id=f.tenant_id
        LEFT JOIN appointments a ON a.id=f.source_id AND a.tenant_id=f.tenant_id
          AND f.source_type IN ('appointment','appointment_commission')
        WHERE f.tenant_id=? AND f.status!='cancelled'";
    $dateCol = ($filters['finance_date'] ?? 'competence') === 'caixa'
        ? "substr(COALESCE(f.paid_at, f.created_at),1,10)"
        : "COALESCE(f.due_date, substr(f.created_at,1,10))";
    $sql .= " AND $dateCol>=? AND $dateCol<=?";
    $params = [$tid, $filters['from'], $filters['to']];
    if ($filters['finance_status'] !== 'all') {
        $sql .= ' AND f.status=?';
        $params[] = $filters['finance_status'];
    }
    if (($filters['finance_kind'] ?? 'all') !== 'all') {
        $sql .= ' AND f.kind=?';
        $params[] = $filters['finance_kind'];
    }
    if (($filters['finance_flow'] ?? 'all') !== 'all') {
        $sql .= ' AND f.flow=?';
        $params[] = $filters['finance_flow'];
    }
    if (($filters['payment_method'] ?? '') !== '') {
        $sql .= ' AND f.payment_method=?';
        $params[] = $filters['payment_method'];
    }
    if ($filters['user_ids']) {
        $ph = implode(',', array_fill(0, count($filters['user_ids']), '?'));
        $sql .= " AND f.agent_id IN ($ph)";
        $params = array_merge($params, $filters['user_ids']);
    }
    if (($filters['min_amount'] ?? null) !== null) {
        $sql .= ' AND f.amount>=?';
        $params[] = $filters['min_amount'];
    }
    if (($filters['max_amount'] ?? null) !== null) {
        $sql .= ' AND f.amount<=?';
        $params[] = $filters['max_amount'];
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND a.service_id IN ($ph)";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= " ORDER BY $dateCol";
    $rows = all($sql, $params);
    $table = [];
    foreach ($rows as $r) {
        $st = FINANCE_STATUS[$r['status']][0] ?? $r['status'];
        $sign = ($r['flow'] ?? '') === 'out' ? '-' : '+';
        $orig = finance_source_label($r['source_type'] ?? null, $r['source_id'] ?? null);
        $who = ($r['source_type'] ?? '') === 'appointment_commission'
            ? ('Agente: '.((string)($r['agent_name'] ?: '—')))
            : ('Cliente: '.((string)($r['client_name'] ?: '—')));
        $table[] = [
            date('d/m/Y', strtotime((string)($r['due_date'] ?: $r['created_at']))),
            $sign.number_format((float)$r['amount'], 2, ',', '.'),
            (string)$r['description'],
            $who,
            (string)$st,
            $orig,
        ];
    }
    $ov = finance_overview($tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59');
    $foot = [];
    if ($filters['include_totals']) {
        $foot[] = 'Previsto: '.money($ov['previsto']).' · A receber: '.money($ov['receber']).' · Repasse: '.money((float)($ov['repasse'] ?? 0));
        $foot[] = 'Lucro presumido: '.money((float)($ov['lucro_presumido'] ?? 0)).' · Lucro líquido: '.money((float)($ov['lucro_liquido'] ?? $ov['saldo']));
    }
    return [report_section('Caixa', ['Data', 'Valor', 'Descrição', 'Atribuição', 'Status', 'Origem'], $table, $foot)];
}

function report_documentos(array $tenant, array $filters, string $scope = 'all'): array
{
    appointment_commission_ensure_schema();
    coverage_ensure_schema();
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $sections = [];
    $wantCpf = in_array($scope, ['all', 'cpf'], true);
    $wantCnpj = in_array($scope, ['all', 'cnpj'], true);
    $wantAgents = in_array($scope, ['all', 'agentes'], true);
    $docKindWant = $filters['document_kind'] ?? 'all';
    if ($wantCpf || $wantCnpj) {
        $cpfRows = [];
        $cnpjRows = [];
        foreach (all('SELECT name, cpf, phone, email, status, birth_date, city, state, created_at FROM clients WHERE tenant_id=? AND created_at>=? AND created_at<=? ORDER BY name', [$tid, $from, $to]) as $r) {
            if (!report_match_status($r['status'] ?? 'ACTIVE', $filters['client_status'] ?? 'ALL')) {
                continue;
            }
            if (!report_match_geo($r, $filters)) {
                continue;
            }
            if (($filters['q'] ?? '') !== '' && !str_contains(strtolower((string)$r['name']), strtolower($filters['q']))) {
                continue;
            }
            $kind = br_doc_kind_from_value($r['cpf'] ?? '');
            if ($docKindWant !== 'all' && $kind !== $docKindWant) {
                continue;
            }
            if (!report_match_doc($r['cpf'] ?? '', $filters['doc_check'] ?? 'all')) {
                continue;
            }
            $age = report_age_years($r['birth_date'] ?? null);
            if ($kind === 'cpf' && !report_match_age($age, $filters)) {
                continue;
            }
            $line = [
                (string)$r['name'],
                $kind ? format_br_document($r['cpf'], $kind) : '—',
                phone_fmt($r['phone'] ?? '') ?: '—',
                (string)($r['email'] ?: '—'),
                ($r['status'] ?? '') === 'INACTIVE' ? 'Inativo' : 'Ativo',
            ];
            if ($kind === 'cnpj') {
                $cnpjRows[] = $line;
            } elseif ($kind === 'cpf') {
                $cpfRows[] = $line;
            }
        }
        $headers = ['Nome', 'Documento', 'Telefone', 'E-mail', 'Status'];
        if ($wantCpf) {
            $sections[] = report_section('Clientes CPF', $headers, $cpfRows, $filters['include_totals'] ? ['Total: '.count($cpfRows)] : []);
        }
        if ($wantCnpj) {
            $sections[] = report_section('Clientes CNPJ', $headers, $cnpjRows, $filters['include_totals'] ? ['Total: '.count($cnpjRows)] : []);
        }
    }
    if ($wantAgents) {
        $agentRows = [];
        foreach (all("SELECT id, name, email, phone, document_kind, cpf, active, created_at FROM users WHERE tenant_id=? AND role='user_agent' AND created_at>=? AND created_at<=? ORDER BY name", [$tid, $from, $to]) as $r) {
            if (!report_match_status(!empty($r['active']) ? 'ACTIVE' : 'INACTIVE', $filters['agent_status'] ?? 'ALL')) {
                continue;
            }
            $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cpf'] ?? '');
            if ($docKindWant !== 'all' && $kind !== $docKindWant) {
                continue;
            }
            if (!report_match_doc($r['cpf'] ?? '', $filters['doc_check'] ?? 'all')) {
                continue;
            }
            if (($filters['q'] ?? '') !== '' && !str_contains(strtolower((string)$r['name'].' '.$r['id']), strtolower($filters['q']))) {
                continue;
            }
            $agentRows[] = [
                (string)$r['name'],
                $kind ? strtoupper($kind) : '—',
                ($kind === 'cpf' || $kind === 'cnpj') ? format_br_document($r['cpf'], $kind) : ((string)($r['cpf'] ?: '—')),
                phone_fmt($r['phone'] ?? '') ?: '—',
                (string)($r['email'] ?: '—'),
                !empty($r['active']) ? 'Ativo' : 'Inativo',
            ];
        }
        $sections[] = report_section('Agentes', ['Nome', 'Tipo', 'Documento', 'Telefone', 'E-mail', 'Situação'], $agentRows, $filters['include_totals'] ? ['Total: '.count($agentRows)] : []);
    }
    return $sections;
}

function report_abrangencia(array $tenant, array $filters): array
{
    coverage_ensure_schema();
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $supRows = [];
    $layer = $filters['coverage_layer'] ?? 'all';
    foreach (all('SELECT name,cnpj,document_kind,address,city,state,created_at FROM suppliers WHERE tenant_id=? AND created_at>=? AND created_at<=? ORDER BY name', [$tid, $from, $to]) as $r) {
        $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cnpj'] ?? '');
        if ($kind !== 'cnpj' || !report_match_geo($r, $filters)) {
            continue;
        }
        $supRows[] = [
            (string)$r['name'],
            format_br_document($r['cnpj'], 'cnpj'),
            trim(($r['address'] ?? '').' '.($r['city'] ?? '').' '.($r['state'] ?? '')) ?: '—',
        ];
    }
    $cliRows = [];
    foreach (all('SELECT name,cpf,address,city,state FROM clients WHERE tenant_id=? AND created_at>=? AND created_at<=? ORDER BY name', [$tid, $from, $to]) as $r) {
        if (br_doc_kind_from_value($r['cpf'] ?? '') !== 'cnpj' || !report_match_geo($r, $filters)) {
            continue;
        }
        $cliRows[] = [
            (string)$r['name'],
            format_br_document($r['cpf'], 'cnpj'),
            trim(($r['address'] ?? '').' '.($r['city'] ?? '').' '.($r['state'] ?? '')) ?: '—',
        ];
    }
    $visitRows = [];
    foreach (all("SELECT s.address,c.name client_name,a.starts_at
        FROM appointment_stops s
        JOIN appointments a ON a.id=s.appointment_id AND a.tenant_id=s.tenant_id
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        WHERE s.tenant_id=? AND a.visit_type='externo' AND a.source='Manual' AND a.status!='CANCELLED'
          AND a.starts_at>=? AND a.starts_at<=?
        ORDER BY a.starts_at", [$tid, $from, $to]) as $r) {
        $visitRows[] = [
            date('d/m/Y H:i', strtotime((string)$r['starts_at'])),
            (string)$r['client_name'],
            (string)$r['address'],
        ];
    }
    $sections = [
        report_section('Fornecedores CNPJ no mapa', ['Nome', 'CNPJ', 'Local'], $supRows, $filters['include_totals'] ? ['Total: '.count($supRows)] : []),
        report_section('Clientes CNPJ no mapa', ['Nome', 'CNPJ', 'Local'], $cliRows, $filters['include_totals'] ? ['Total: '.count($cliRows)] : []),
        report_section('Agendamentos externos (manual)', ['Data', 'Cliente', 'Endereço'], $visitRows, $filters['include_totals'] ? ['Total: '.count($visitRows)] : []),
    ];
    if ($layer === 'suppliers') {
        return [$sections[0]];
    }
    if ($layer === 'clients') {
        return [$sections[1]];
    }
    if ($layer === 'visits') {
        return [$sections[2]];
    }
    return $sections;
}

function report_fornecedores(array $tenant, array $filters): array
{
    coverage_ensure_schema();
    $tid = $tenant['id'];
    $rows = all('SELECT * FROM suppliers WHERE tenant_id=? AND created_at>=? AND created_at<=? ORDER BY name', [
        $tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59',
    ]);
    $table = [];
    foreach ($rows as $r) {
        $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cnpj'] ?? '');
        if (($filters['document_kind'] ?? 'all') !== 'all' && $kind !== $filters['document_kind']) {
            continue;
        }
        if (($filters['product_type'] ?? '') !== '' && strcasecmp((string)($r['product_type'] ?? ''), $filters['product_type']) !== 0) {
            continue;
        }
        if (!report_match_geo($r, $filters)) {
            continue;
        }
        if (($filters['q'] ?? '') !== '' && !str_contains(strtolower((string)$r['name']), strtolower($filters['q']))) {
            continue;
        }
        $doc = ($kind === 'cpf' || $kind === 'cnpj') ? format_br_document($r['cnpj'], $kind) : (string)($r['cnpj'] ?: '—');
        $table[] = [
            (string)$r['name'],
            strtoupper($kind ?: '—'),
            $doc,
            (string)($r['product_type'] ?: '—'),
            (string)($r['contact_name'] ?: '—'),
            phone_fmt($r['phone'] ?? '') ?: '—',
            (string)($r['email'] ?: '—'),
            trim(($r['city'] ?? '').' '.($r['state'] ?? '')) ?: '—',
        ];
    }
    $foot = $filters['include_totals'] ? ['Fornecedores: '.count($table)] : [];
    return [report_section('Fornecedores', ['Nome', 'Tipo', 'Documento', 'Produto', 'Contato', 'Telefone', 'E-mail', 'Cidade'], $table, $foot)];
}

function report_cliente_resumo_only(array $tenant, array $filters): array
{
    $copy = $filters;
    $copy['include_client_summary'] = true;
    $copy['_client_summary_only'] = true;
    $sections = report_atendimentos($tenant, $copy);
    return isset($sections[1]) ? [$sections[1]] : [report_section('Resumo por cliente', ['Cliente', 'Atendimentos'], [])];
}

function report_build(array $tenant, array $filters): array
{
    $business = $tenant['display_name'] ?: $tenant['business_name'];
    $kinds = $filters['kinds'] ?: ['atendimentos'];
    $labels = [
        'atendimentos' => 'Resumo de atendimentos',
        'agendamentos' => 'Relatório de agendamentos',
        'solicitacoes' => 'Relatório de solicitações',
        'clientes' => 'Relatório de clientes',
        'agentes' => 'Relatório de agentes',
        'servicos' => 'Relatório de serviços',
        'origens' => 'Resumo de origens',
        'financeiro' => 'Relatório de caixa',
        'documentos' => 'Documentos',
        'documentos_cpf' => 'Documentos de clientes CPF',
        'documentos_cnpj' => 'Documentos de clientes CNPJ',
        'documentos_agentes' => 'Documentos de agentes',
        'cliente_resumo' => 'Resumo por cliente',
        'contratos' => 'Contrato de prestação',
        'abrangencia' => 'Abrangência',
        'fornecedores' => 'Fornecedores',
    ];
    $sections = [];
    $contract = null;
    foreach ($kinds as $kind) {
        if ($kind === 'contratos') {
            $contract = contract_items($tenant, $filters);
            continue;
        }
        $part = match ($kind) {
            'atendimentos' => report_atendimentos($tenant, $filters),
            'agendamentos' => report_agendamentos($tenant, $filters),
            'solicitacoes' => report_solicitacoes($tenant, $filters),
            'clientes' => report_clientes($tenant, $filters),
            'agentes' => report_agentes($tenant, $filters),
            'servicos' => report_servicos($tenant, $filters),
            'origens' => report_origens($tenant, $filters),
            'financeiro' => report_financeiro($tenant, $filters),
            'documentos' => report_documentos($tenant, $filters, 'all'),
            'documentos_cpf' => report_documentos($tenant, $filters, 'cpf'),
            'documentos_cnpj' => report_documentos($tenant, $filters, 'cnpj'),
            'documentos_agentes' => report_documentos($tenant, $filters, 'agentes'),
            'cliente_resumo' => report_cliente_resumo_only($tenant, $filters),
            'abrangencia' => report_abrangencia($tenant, $filters),
            'fornecedores' => report_fornecedores($tenant, $filters),
            default => [],
        };
        foreach ($part as $sec) {
            $sections[] = $sec;
        }
    }
    if ($contract && count($kinds) === 1) {
        $title = trim((string)($contract['list_name'] ?? '')) ?: 'Contrato de prestação de serviços';
    } elseif (count($kinds) === 1) {
        $title = $labels[$kinds[0]] ?? 'Relatório';
    } else {
        $title = $contract ? 'Contrato e relatórios' : 'Relatórios operacionais';
    }
    $useLh = $contract ? letterhead_preview($tenant, false) : letterhead_preview($tenant);
    return [
        'title' => $title,
        'filename' => ($contract ? 'contrato-' : 'relatorio-').date('Y-m-d').'.pdf',
        'company' => (string)$business,
        'period' => date('d/m/Y', strtotime($filters['from'])).' a '.date('d/m/Y', strtotime($filters['to'])),
        'generated' => date('d/m/Y H:i'),
        'sections' => $sections,
        'contract' => $contract,
        'letterhead' => $useLh,
        'signature' => signature_preview($tenant),
    ];
}

function report_to_lines(array $doc): array
{
    $lines = ['Empresa: '.$doc['company'], 'Período: '.$doc['period'], str_repeat('-', 80)];
    foreach ($doc['sections'] as $sec) {
        $lines[] = $sec['title'];
        if ($sec['headers']) {
            $lines[] = implode(' | ', $sec['headers']);
        }
        if (!$sec['rows']) {
            $lines[] = '(sem registros no filtro)';
        }
        foreach ($sec['rows'] as $row) {
            $lines[] = implode(' | ', $row);
        }
        foreach ($sec['foot'] as $f) {
            $lines[] = $f;
        }
        $lines[] = str_repeat('-', 80);
    }
    return $lines;
}

function report_send_pdf(array $tenant): never
{
    letterhead_ensure_schema();
    $row = one('SELECT letterhead_config, signature_config, clauses_config FROM tenants WHERE id=?', [$tenant['id']]);
    if ($row) {
        $tenant['letterhead_config'] = $row['letterhead_config'] ?? '{}';
        $tenant['signature_config'] = $row['signature_config'] ?? '{}';
        $tenant['clauses_config'] = $row['clauses_config'] ?? '{}';
    }
    $doc = report_build($tenant, report_filters_from_request());
    if (!empty($doc['contract'])) {
        contract_send_pdf($tenant, $doc);
    }
    download_pdf($doc['title'], report_to_lines($doc), $doc['filename'], $tenant);
}

function report_send_preview(array $tenant): never
{
    letterhead_ensure_schema();
    $row = one('SELECT letterhead_config, signature_config, clauses_config FROM tenants WHERE id=?', [$tenant['id']]);
    if ($row) {
        $tenant['letterhead_config'] = $row['letterhead_config'] ?? '{}';
        $tenant['signature_config'] = $row['signature_config'] ?? '{}';
        $tenant['clauses_config'] = $row['clauses_config'] ?? '{}';
    }
    $doc = report_build($tenant, report_filters_from_request());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($doc, JSON_UNESCAPED_UNICODE);
    exit;
}
