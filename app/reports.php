<?php
declare(strict_types=1);

const REPORT_KINDS = ['atendimentos', 'clientes', 'origens', 'financeiro', 'cliente_resumo', 'contratos', 'abrangencia', 'fornecedores'];

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
        return $kinds;
    }
    $path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    return match ($path) {
        '/app/relatorios/atendimentos.pdf' => ['atendimentos'],
        '/app/relatorios/clientes.pdf' => ['clientes'],
        '/app/relatorios/origens.pdf' => ['origens'],
        '/app/relatorios/contrato.pdf' => ['contratos'],
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
    return [
        'from' => $from,
        'to' => $to,
        'appt_status' => $appt,
        'client_status' => $client,
        'finance_status' => $fin,
        'user_ids' => report_id_list($_GET['users'] ?? []),
        'admins_only' => ($_GET['admins'] ?? '') === '1',
        'service_ids' => report_id_list($_GET['services'] ?? []),
        'kinds' => report_kinds_from_request(),
        'include_totals' => ($_GET['totals'] ?? '1') !== '0',
        'include_client_summary' => ($_GET['client_summary'] ?? '') === '1',
        'contract_id' => report_id_list($_GET['contract_id'] ?? [])[0] ?? '',
    ];
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

function report_section(string $title, array $headers, array $rows, array $foot = []): array
{
    return ['title' => $title, 'headers' => $headers, 'rows' => $rows, 'foot' => $foot];
}

function report_atendimentos(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $sql = "SELECT a.*, c.name client_name, s.name service_name
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        WHERE a.tenant_id=? AND a.starts_at>=? AND a.starts_at<=?";
    $params = [$tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59'];
    if ($filters['appt_status'] !== 'ALL') {
        $sql .= ' AND a.status=?';
        $params[] = $filters['appt_status'];
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
    $rows = array_values(array_filter($rows, static fn(array $r) => report_match_actor($actors[$r['id']] ?? null, $filters, $roles)));

    $headers = ['Data', 'Cliente', 'Serviço', 'Status'];
    $table = [];
    $byClient = [];
    foreach ($rows as $r) {
        $status = APPT_STATUS[$r['status']][0] ?? $r['status'];
        $table[] = [
            date('d/m/Y H:i', strtotime((string)$r['starts_at'])),
            (string)$r['client_name'],
            (string)($r['service_name'] ?: 'Sem serviço'),
            (string)$status,
        ];
        $name = (string)$r['client_name'];
        $byClient[$name] = ($byClient[$name] ?? 0) + 1;
    }
    $foot = $filters['include_totals'] ? ['Total de atendimentos: '.count($table)] : [];
    $sections = [report_section('Atendimentos', $headers, $table, $foot)];
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
    return [report_section('Cadastros', ['Nome', 'Contato', 'Origem', 'Atend.', 'Status'], $table, $foot)];
}

function report_origens(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $clients = all(
        "SELECT COALESCE(NULLIF(utm_source,''), source, 'Não informado') source, COUNT(*) total
         FROM clients WHERE tenant_id=? AND created_at>=? AND created_at<=?
         GROUP BY COALESCE(NULLIF(utm_source,''), source, 'Não informado')",
        [$tid, $from, $to]
    );
    $requests = all(
        "SELECT COALESCE(NULLIF(utm_source,''), source, 'Não informado') source, COUNT(*) total
         FROM requests WHERE tenant_id=? AND created_at>=? AND created_at<=?
         GROUP BY COALESCE(NULLIF(utm_source,''), source, 'Não informado')",
        [$tid, $from, $to]
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
    $sql = "SELECT f.*, c.name client_name, a.service_id
        FROM finance_entries f
        LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
        LEFT JOIN appointments a ON f.source_type='appointment' AND a.id=f.source_id AND a.tenant_id=f.tenant_id
        WHERE f.tenant_id=? AND f.status!='cancelled'
          AND COALESCE(f.due_date, substr(f.created_at,1,10))>=?
          AND COALESCE(f.due_date, substr(f.created_at,1,10))<=?";
    $params = [$tid, $filters['from'], $filters['to']];
    if ($filters['finance_status'] !== 'all') {
        $sql .= ' AND f.status=?';
        $params[] = $filters['finance_status'];
    }
    if ($filters['service_ids']) {
        $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
        $sql .= " AND a.service_id IN ($ph)";
        $params = array_merge($params, $filters['service_ids']);
    }
    $sql .= ' ORDER BY COALESCE(f.due_date, f.created_at)';
    $rows = all($sql, $params);
    $table = [];
    foreach ($rows as $r) {
        $st = FINANCE_STATUS[$r['status']][0] ?? $r['status'];
        $sign = ($r['flow'] ?? '') === 'out' ? '-' : '+';
        $orig = finance_source_label($r['source_type'] ?? null, $r['source_id'] ?? null);
        $table[] = [
            date('d/m/Y', strtotime((string)($r['due_date'] ?: $r['created_at']))),
            $sign.number_format((float)$r['amount'], 2, ',', '.'),
            (string)$r['description'],
            (string)$st,
            $orig,
        ];
    }
    $ov = finance_overview($tid, $filters['from'].' 00:00:00', $filters['to'].' 23:59:59');
    $foot = [];
    if ($filters['include_totals']) {
        $foot[] = 'Previsto: '.money($ov['previsto']).' · A receber: '.money($ov['receber']).' · Saldo: '.money($ov['saldo']);
    }
    return [report_section('Caixa', ['Data', 'Valor', 'Descrição', 'Status', 'Origem'], $table, $foot)];
}

function report_abrangencia(array $tenant, array $filters): array
{
    coverage_ensure_schema();
    $tid = $tenant['id'];
    $from = $filters['from'].' 00:00:00';
    $to = $filters['to'].' 23:59:59';
    $supRows = [];
    foreach (all('SELECT name,cnpj,document_kind,address,city,state,created_at FROM suppliers WHERE tenant_id=? AND created_at>=? AND created_at<=? ORDER BY name', [$tid, $from, $to]) as $r) {
        $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cnpj'] ?? '');
        if ($kind !== 'cnpj') {
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
        if (br_doc_kind_from_value($r['cpf'] ?? '') !== 'cnpj') {
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
        'clientes' => 'Resumo de clientes',
        'origens' => 'Resumo de origens',
        'financeiro' => 'Resumo do caixa',
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
            'clientes' => report_clientes($tenant, $filters),
            'origens' => report_origens($tenant, $filters),
            'financeiro' => report_financeiro($tenant, $filters),
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
