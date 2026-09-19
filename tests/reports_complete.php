<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-rep-full-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/finance.php';
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/reports.php';

db();
coverage_ensure_schema();
ensure_finance_schema();
appointment_commission_ensure_schema();
letterhead_ensure_schema();

$fail = 0;
function expect($ok, string $msg): void
{
    global $fail;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL $msg\n";
}

function col(array $doc, int $i, int $sec = 0): array
{
    return array_map('strval', array_column($doc['sections'][$sec]['rows'] ?? [], $i));
}

function n(array $doc, int $sec = 0): int
{
    return count($doc['sections'][$sec]['rows'] ?? []);
}

function nAll(array $doc): int
{
    $t = 0;
    foreach ($doc['sections'] ?? [] as $s) {
        $t += count($s['rows'] ?? []);
    }
    return $t;
}

$now = now();
$day = date('Y-m-d');
$from = date('Y-m-01');
$oldDay = date('Y-m-d', strtotime('-40 days'));
$oldTs = $oldDay.' 12:00:00';
$birthAdult = date('Y-m-d', strtotime('-36 years'));
$birthKid = date('Y-m-d', strtotime('-10 years'));

q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-full', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-b', 'B', 'Empresa B', 'emp-b-full', 'outros', 'b@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ua', 'ten-a', 'Admin A', 'ua@ex.com', 'ua', 'x', 'TENANT_ADMIN', 0, 1, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-a', 'ten-a', 'Agente Ana', 'aga@ex.com', 'aga', 'x', 'user_agent', 0, 1, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-off', 'ten-a', 'Agente Off', 'ago@ex.com', 'ago', 'x', 'user_agent', 0, 0, $now,
]);
q("UPDATE users SET document_kind='cpf', cpf='529.982.247-25' WHERE id='ag-a'");
q("UPDATE users SET document_kind='', cpf='' WHERE id='ag-off'");

q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,utm_source,status,birth_date,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'cli-ana', 'ten-a', 'Ana', '11911112222', 'ana@ex.com', '529.982.247-25', 'Instagram', 'Instagram', 'ACTIVE', $birthAdult, 'São Paulo', 'SP', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,utm_source,status,birth_date,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'cli-bruno', 'ten-a', 'Bruno', '11933334444', 'bruno@ex.com', '', 'Google', 'Google', 'INACTIVE', $birthAdult, 'Rio', 'RJ', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,utm_source,status,birth_date,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'cli-ltda', 'ten-a', 'Empresa Ltda', '1133334444', 'ltda@ex.com', '11.222.333/0001-81', 'LinkedIn', 'LinkedIn', 'ACTIVE', null, 'Campinas', 'SP', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,status,birth_date,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    'cli-kid', 'ten-a', 'Kida', '11900001111', 'kid@ex.com', '390.533.447-05', 'Instagram', 'ACTIVE', $birthKid, 'São Paulo', 'SP', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-old', 'ten-a', 'Oldie', '11', 'old@ex.com', 'site', 'ACTIVE', $oldTs,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-b', 'ten-b', 'Bia', '22', 'bia@ex.com', 'Google', 'ACTIVE', $now,
]);

q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,price_kind,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'svc-corte', 'ten-a', 'Corte', 'Cabelo', 60, 100, 'priced', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,price_kind,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'svc-barba', 'ten-a', 'Barba', 'Cabelo', 30, 40, 'priced', 'INACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,price_kind,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'svc-cort', 'ten-a', 'Cortesia', 'Extra', 15, 0, 'cortesia', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,price_kind,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'svc-conv', 'ten-a', 'Convênio', 'Extra', 20, 0, 'convenio', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,category,duration_minutes,price,price_kind,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'svc-reu', 'ten-a', 'Reunião', 'Extra', 30, 0, 'reuniao', 'ACTIVE', $now,
]);

$appts = [
    ['ap-bill', 'cli-ana', 'svc-corte', $day.' 09:00:00', $day.' 10:00:00', 'SCHEDULED', 'WhatsApp', $now],
    ['ap-paid', 'cli-bruno', 'svc-corte', $day.' 10:00:00', $day.' 11:00:00', 'CONFIRMED', 'Manual', $now],
    ['ap-cort', 'cli-ana', 'svc-cort', $day.' 11:00:00', $day.' 11:15:00', 'DONE', 'Instagram', $now],
    ['ap-conv', 'cli-ana', 'svc-conv', $day.' 12:00:00', $day.' 12:20:00', 'WAITING', 'Manual', $now],
    ['ap-reu', 'cli-ana', 'svc-reu', $day.' 13:00:00', $day.' 13:30:00', 'SCHEDULED', 'Manual', $now],
    ['ap-barba', 'cli-ana', 'svc-barba', $day.' 14:00:00', $day.' 14:30:00', 'DONE', 'Site', $now],
    ['ap-vis', 'cli-ltda', 'svc-corte', $day.' 16:00:00', $day.' 17:00:00', 'SCHEDULED', 'Manual', $now],
    ['ap-old', 'cli-ana', 'svc-corte', $oldDay.' 10:00:00', $oldDay.' 11:00:00', 'DONE', 'Manual', $oldTs],
    ['ap-b', 'cli-b', 'svc-corte', $day.' 10:00:00', $day.' 11:00:00', 'SCHEDULED', 'Manual', $now],
];
foreach ($appts as $a) {
    q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', array_merge([$a[0], $a[0] === 'ap-b' ? 'ten-b' : 'ten-a'], array_slice($a, 1)));
}
q("UPDATE appointments SET visit_type='externo' WHERE id='ap-vis'");
q("UPDATE appointments SET commission_agent_id='ag-a' WHERE id='ap-bill'");
q('INSERT INTO appointment_stops(id,tenant_id,appointment_id,sort_order,address,lat,lng,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'st-1', 'ten-a', 'ap-vis', 0, 'Av Paulista 1000', -23.56, -46.65, $now,
]);

q('INSERT INTO requests(id,tenant_id,service_id,name,phone,email,source,utm_source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'req-new', 'ten-a', 'svc-corte', 'Carlos', '11988887777', 'c@ex.com', 'site', 'site', 'NEW', $now,
]);
q('INSERT INTO requests(id,tenant_id,service_id,name,phone,email,source,utm_source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'req-lost', 'ten-a', 'svc-barba', 'Maria', '11977776666', 'm@ex.com', 'whatsapp', 'whatsapp', 'LOST', $now,
]);
q('INSERT INTO requests(id,tenant_id,service_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'req-old', 'ten-a', 'svc-corte', 'Pedro', '11966665555', 'p@ex.com', 'site', 'NEW', $oldTs,
]);

q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-ap-bill', 'ten-a', 'ua', 'appointment.created', 'appointment', 'ap-bill', $now,
]);
q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-ap-barba', 'ten-a', 'ag-a', 'appointment.created', 'appointment', 'ap-barba', $now,
]);
q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-cli-ana', 'ten-a', 'ua', 'client.created', 'client', 'cli-ana', $now,
]);
q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-cli-bruno', 'ten-a', 'ag-a', 'client.created', 'client', 'cli-bruno', $now,
]);

q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,source_type,source_id,payment_method,agent_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-bill', 'ten-a', 'receivable', 'in', 'billed', 'Corte faturado', 150, $day, null, 'cli-ana', 'appointment', 'ap-bill', 'fatura', 'ag-a', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,source_type,source_id,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-paid', 'ten-a', 'receivable', 'in', 'paid', 'Corte pago', 80, $day, $now, 'cli-bruno', 'appointment', 'ap-paid', 'pix', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,agent_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-rep', 'ten-a', 'payable', 'out', 'open', 'Repasse Ana', 50, $day, 'ag-a', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-big', 'ten-a', 'payable', 'out', 'open', 'Fornecedor grande', 300, $day, 'transferencia', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-comp', 'ten-a', 'receivable', 'in', 'open', 'Só competência', 11, $day, $oldTs, $oldTs, $oldTs,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-caixa', 'ten-a', 'receivable', 'in', 'paid', 'Só caixa', 22, $oldDay, $now, $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'fin-can', 'ten-a', 'receivable', 'in', 'cancelled', 'Cancelado', 99, $day, $now, $now,
]);

q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,contact_name,phone,email,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    'sup-pecas', 'ten-a', 'Peças Silva', '529.982.247-25', 'cpf', 'Peças', 'São Paulo', 'SP', 'João', '11900000001', 'pecas@ex.com', $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,contact_name,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'sup-ata', 'ten-a', 'Atacado Ltda', '11.222.333/0001-81', 'cnpj', 'Limpeza', 'Campinas', 'SP', 'Maria', $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'sup-rj', 'ten-a', 'RJ Co', '11.222.333/0001-81', 'cnpj', 'Peças', 'Niterói', 'RJ', $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'sup-old', 'ten-a', 'Velho', '11.222.333/0001-81', 'cnpj', 'Peças', 'São Paulo', 'SP', $oldTs,
]);

$clauses = json_encode([
    'lists' => [[
        'id' => 'ct-1',
        'name' => 'Contrato teste',
        'appointment_ids' => ['ap-bill'],
        'html' => '<p>Cláusula 1</p>',
    ]],
], JSON_UNESCAPED_UNICODE);
q('UPDATE tenants SET clauses_config=? WHERE id=?', [$clauses, 'ten-a']);

$tenant = [
    'id' => 'ten-a',
    'display_name' => 'A',
    'business_name' => 'Empresa A',
    'segment' => 'outros',
    'clauses_config' => $clauses,
];

function run(array $get) : array
{
    global $tenant, $from, $day;
    $_GET = array_merge(['from' => $from, 'to' => $day], $get);
    $_SERVER['REQUEST_URI'] = '/app/relatorios/preview';
    return report_build($tenant, report_filters_from_request());
}

$inMonth = 7;
$allAppt = run(['types' => ['agendamentos']]);
expect(n($allAppt) === $inMonth, 'agendamentos: período do mês (7 no tenant A)');
expect(!in_array('Bia', col($allAppt, 2), true), 'agendamentos: não vaza tenant B');
expect($allAppt['sections'][0]['headers'][0] === 'N° reserva', 'agendamentos: coluna N° reserva primeiro');
expect(n(run(['types' => ['agendamentos'], 'from' => $oldDay, 'to' => $oldDay])) === 1, 'agendamentos: período antigo só o old');
expect(n(run(['types' => ['agendamentos'], 'appt_status' => 'DONE'])) === 2, 'agendamentos: status DONE');
expect(n(run(['types' => ['agendamentos'], 'appt_status' => 'WAITING'])) === 1, 'agendamentos: status WAITING');
expect(n(run(['types' => ['agendamentos'], 'source' => 'WhatsApp'])) === 1, 'agendamentos: origem WhatsApp');
expect(n(run(['types' => ['agendamentos'], 'appt_type' => 'billed'])) === 1 && col(run(['types' => ['agendamentos'], 'appt_type' => 'billed']), 6) === ['Faturado'], 'agendamentos: tipo Faturado');
expect(n(run(['types' => ['agendamentos'], 'appt_type' => 'paid'])) === 1, 'agendamentos: tipo Pago');
expect(n(run(['types' => ['agendamentos'], 'appt_type' => 'cortesia'])) === 1, 'agendamentos: tipo Cortesia');
expect(n(run(['types' => ['agendamentos'], 'appt_type' => 'convenio'])) === 1, 'agendamentos: tipo Convênio');
expect(n(run(['types' => ['agendamentos'], 'appt_type' => 'reuniao'])) === 1, 'agendamentos: tipo Reunião');
$priced = run(['types' => ['agendamentos'], 'appt_type' => 'priced']);
expect(n($priced) >= 2 && !in_array('Faturado', col($priced, 6), true), 'agendamentos: tipo Com valor exclui faturado');
$resN = (int)(one("SELECT reserva_n FROM appointments WHERE id='ap-bill'")['reserva_n'] ?? 0);
expect($resN > 0 && n(run(['types' => ['agendamentos'], 'reserva' => '#'.$resN])) === 1, 'agendamentos: N° reserva');
expect(n(run(['types' => ['agendamentos'], 'services' => ['svc-barba']])) === 1, 'agendamentos: serviço Barba');
$byTeam = run(['types' => ['agendamentos'], 'users' => ['ag-a']]);
expect(n($byTeam) === 2, 'agendamentos: equipe = criador ou agente da comissão');
$sum = run(['types' => ['agendamentos'], 'client_summary' => '1']);
expect(count($sum['sections']) === 2 && $sum['sections'][1]['title'] === 'Resumo por cliente', 'agendamentos: resumo por cliente');
$noTot = run(['types' => ['agendamentos'], 'totals' => '0']);
expect($noTot['sections'][0]['foot'] === [], 'agendamentos: sem totais');

$reqs = run(['types' => ['solicitacoes']]);
expect(n($reqs) === 2, 'solicitações: período');
expect(n(run(['types' => ['solicitacoes'], 'q' => 'Carlos'])) === 1, 'solicitações: busca nome');
expect(n(run(['types' => ['solicitacoes'], 'q' => '11988887777'])) === 1, 'solicitações: busca telefone');
expect(n(run(['types' => ['solicitacoes'], 'q' => 'req-lost'])) === 1, 'solicitações: busca protocolo');
expect(n(run(['types' => ['solicitacoes'], 'request_status' => 'LOST'])) === 1, 'solicitações: status LOST');
expect(n(run(['types' => ['solicitacoes'], 'request_status' => 'NEW'])) === 1, 'solicitações: status NEW');
expect(n(run(['types' => ['solicitacoes'], 'source' => 'site'])) === 1, 'solicitações: canal site');
expect(n(run(['types' => ['solicitacoes'], 'services' => ['svc-barba']])) === 1, 'solicitações: serviço');
expect(n(run(['types' => ['solicitacoes'], 'from' => $oldDay, 'to' => $oldDay])) === 1, 'solicitações: período antigo');

$cli = run(['types' => ['clientes']]);
expect(n($cli) === 4, 'clientes: cadastros do mês (sem Oldie)');
expect(n(run(['types' => ['clientes'], 'client_status' => 'INACTIVE'])) === 1, 'clientes: inativos');
expect(n(run(['types' => ['clientes'], 'client_status' => 'ACTIVE'])) === 3, 'clientes: ativos do mês');
expect(n(run(['types' => ['clientes'], 'source' => 'Instagram'])) === 2, 'clientes: origem Instagram');
expect(n(run(['types' => ['clientes'], 'state' => 'RJ'])) === 1, 'clientes: UF RJ');
expect(n(run(['types' => ['clientes'], 'city' => 'Campinas'])) === 1, 'clientes: cidade');
expect(n(run(['types' => ['clientes'], 'admins' => '1'])) === 1, 'clientes: só admin');
expect(n(run(['types' => ['clientes'], 'users' => ['ag-a']])) === 1, 'clientes: equipe que cadastrou');
expect(n(run(['types' => ['clientes'], 'services' => ['svc-barba']])) === 1, 'clientes: já usaram Barba');
expect(n(run(['types' => ['clientes'], 'from' => $oldDay, 'to' => $oldDay])) === 1, 'clientes: cadastro antigo');

$ag = run(['types' => ['agentes']]);
expect(n($ag) === 2, 'agentes: padrão user_agent (ativo e inativo)');
$agAllRole = run(['types' => ['agentes'], 'agent_role' => 'all']);
expect(n($agAllRole) === 3, 'agentes: toda a equipe');
expect(n(run(['types' => ['agentes'], 'agent_role' => 'user_crm'])) === 1, 'agentes: só admin');
expect(n(run(['types' => ['agentes'], 'agent_status' => 'ACTIVE', 'agent_role' => 'all'])) === 2, 'agentes: ativos');
expect(n(run(['types' => ['agentes'], 'agent_status' => 'INACTIVE'])) === 1, 'agentes: inativos');
expect(n(run(['types' => ['agentes'], 'q' => 'Off', 'agent_role' => 'all'])) === 1, 'agentes: busca nome');
expect(n(run(['types' => ['agentes'], 'users' => ['ag-a']])) === 1, 'agentes: checkbox pessoa');
$agVol = run(['types' => ['agentes'], 'from' => $from, 'to' => $day]);
expect((int)$agVol['sections'][0]['rows'][0][4] >= 1, 'agentes: volume de agendamentos no período');

$svc = run(['types' => ['servicos']]);
expect(n($svc) === 5, 'serviços: catálogo completo');
expect(n(run(['types' => ['servicos'], 'category' => 'Extra'])) === 3, 'serviços: categoria Extra');
expect(n(run(['types' => ['servicos'], 'service_status' => 'INACTIVE'])) === 1, 'serviços: inativos');
expect(n(run(['types' => ['servicos'], 'services' => ['svc-corte']])) === 1, 'serviços: lista');
$svcCount = run(['types' => ['servicos'], 'services' => ['svc-corte']]);
expect((int)$svcCount['sections'][0]['rows'][0][3] >= 1, 'serviços: conta agendamentos no período');

$orig = run(['types' => ['origens']]);
expect(n($orig) >= 3, 'origens: vários canais');
$ig = run(['types' => ['origens'], 'source' => 'Instagram']);
expect(n($ig) === 1 && (int)$ig['sections'][0]['rows'][0][1] === 2, 'origens: filtro Instagram (2 cadastros)');

$docs = run(['types' => ['documentos']]);
expect(count($docs['sections']) === 3, 'documentos: 3 blocos');
expect(n($docs, 0) === 2, 'documentos: 2 CPF (Ana e Kida)');
expect(n($docs, 1) === 1, 'documentos: 1 CNPJ');
expect(n($docs, 2) === 2, 'documentos: 2 agentes no período');
expect(n(run(['types' => ['documentos'], 'document_kind' => 'cpf']), 0) === 2 && n(run(['types' => ['documentos'], 'document_kind' => 'cpf']), 1) === 0, 'documentos: só CPF');
expect(n(run(['types' => ['documentos'], 'doc_check' => 'pending']), 0) === 0, 'documentos: CPF pendentes (nenhum sem doc no bloco cpf)');
expect(n(run(['types' => ['documentos'], 'client_status' => 'INACTIVE']), 0) === 0, 'documentos: inativo Bruno sem CPF não entra em CPF');

$cpf = run(['types' => ['documentos_cpf']]);
expect(n($cpf) === 2, 'documentos_cpf: Ana e Kida');
expect(n(run(['types' => ['documentos_cpf'], 'age_min' => '18'])) === 1, 'documentos_cpf: idade mínima');
expect(n(run(['types' => ['documentos_cpf'], 'age_max' => '15'])) === 1, 'documentos_cpf: idade máxima');
expect(n(run(['types' => ['documentos_cpf'], 'state' => 'SP'])) === 2, 'documentos_cpf: UF');
expect(n(run(['types' => ['documentos_cpf'], 'city' => 'São Paulo'])) === 2, 'documentos_cpf: cidade');
expect(n(run(['types' => ['documentos_cpf'], 'doc_check' => 'validated'])) === 2, 'documentos_cpf: validados');
expect(n(run(['types' => ['documentos_cpf'], 'client_status' => 'ACTIVE'])) === 2, 'documentos_cpf: status');

$cnpj = run(['types' => ['documentos_cnpj']]);
expect(n($cnpj) === 1, 'documentos_cnpj: Empresa Ltda');
expect(n(run(['types' => ['documentos_cnpj'], 'q' => 'Ltda'])) === 1, 'documentos_cnpj: busca razão');
expect(n(run(['types' => ['documentos_cnpj'], 'q' => 'zzz'])) === 0, 'documentos_cnpj: busca vazia');
expect(n(run(['types' => ['documentos_cnpj'], 'state' => 'SP'])) === 1, 'documentos_cnpj: UF');
expect(n(run(['types' => ['documentos_cnpj'], 'city' => 'Campinas'])) === 1, 'documentos_cnpj: cidade');
expect(n(run(['types' => ['documentos_cnpj'], 'doc_check' => 'validated'])) === 1, 'documentos_cnpj: validado');
expect(n(run(['types' => ['documentos_cnpj'], 'client_status' => 'INACTIVE'])) === 0, 'documentos_cnpj: inativo');

$dag = run(['types' => ['documentos_agentes']]);
expect(n($dag) === 2, 'documentos_agentes: dois agentes cadastrados no mês');
expect(n(run(['types' => ['documentos_agentes'], 'document_kind' => 'cpf'])) === 1, 'documentos_agentes: só quem tem CPF');
expect(n(run(['types' => ['documentos_agentes'], 'doc_check' => 'pending'])) === 1, 'documentos_agentes: pendente');
expect(n(run(['types' => ['documentos_agentes'], 'agent_status' => 'ACTIVE'])) === 1, 'documentos_agentes: ativos');
expect(n(run(['types' => ['documentos_agentes'], 'q' => 'Off'])) === 1, 'documentos_agentes: busca');

$fin = run(['types' => ['financeiro']]);
expect(n($fin) >= 4, 'caixa: lançamentos no período (sem cancelado)');
expect(!str_contains(json_encode($fin), 'Cancelado') || n(run(['types' => ['financeiro'], 'finance_status' => 'cancelled'])) === 0, 'caixa: cancelado fora');
expect(n(run(['types' => ['financeiro'], 'finance_kind' => 'payable'])) === 2, 'caixa: a pagar');
expect(n(run(['types' => ['financeiro'], 'finance_kind' => 'receivable'])) >= 2, 'caixa: a receber');
expect(n(run(['types' => ['financeiro'], 'finance_flow' => 'out'])) === 2, 'caixa: saída');
expect(n(run(['types' => ['financeiro'], 'finance_status' => 'billed'])) === 1, 'caixa: faturado');
expect(n(run(['types' => ['financeiro'], 'finance_status' => 'open'])) >= 2, 'caixa: em aberto');
expect(n(run(['types' => ['financeiro'], 'payment_method' => 'pix'])) === 1, 'caixa: PIX');
expect(n(run(['types' => ['financeiro'], 'users' => ['ag-a']])) === 2, 'caixa: agente (faturado+repasse)');
expect(n(run(['types' => ['financeiro'], 'min_amount' => '200'])) === 1, 'caixa: valor mínimo');
expect(n(run(['types' => ['financeiro'], 'max_amount' => '20'])) === 1, 'caixa: valor máximo');
expect(n(run(['types' => ['financeiro'], 'services' => ['svc-corte']])) >= 1, 'caixa: serviço do agendamento');
$comp = run(['types' => ['financeiro'], 'finance_date' => 'competence']);
$cx = run(['types' => ['financeiro'], 'finance_date' => 'caixa']);
$compDesc = col($comp, 2);
$cxDesc = col($cx, 2);
expect(in_array('Só competência', $compDesc, true) && !in_array('Só competência', $cxDesc, true), 'caixa: competência inclui due_date do mês');
expect(in_array('Só caixa', $cxDesc, true) && !in_array('Só caixa', $compDesc, true), 'caixa: data de caixa usa pagamento');

$ctEmpty = run(['types' => ['contratos'], 'appt_status' => 'DONE']);
expect(count($ctEmpty['contract']['items'] ?? []) === 2, 'contratos sem lista: status DONE no período');
$ctSvc = run(['types' => ['contratos'], 'services' => ['svc-barba']]);
expect(count($ctSvc['contract']['items'] ?? []) === 1, 'contratos: serviço');
$ctMin = run(['types' => ['contratos'], 'min_amount' => '50']);
expect(count($ctMin['contract']['items'] ?? []) >= 1, 'contratos: valor mínimo');
$ctMax = run(['types' => ['contratos'], 'max_amount' => '10']);
$ctMaxPrices = array_column($ctMax['contract']['items'] ?? [], 'price');
expect($ctMaxPrices !== [] && max($ctMaxPrices) <= 10, 'contratos: valor máximo 10 exclui Corte de 100');
$ctList = run(['types' => ['contratos'], 'contract_id' => 'ct-1']);
expect(count($ctList['contract']['items'] ?? []) === 1, 'contratos: lista da árvore');

$abr = run(['types' => ['abrangencia']]);
expect(count($abr['sections']) === 3, 'abrangência: 3 camadas');
expect(n($abr, 0) === 2, 'abrangência: fornecedores CNPJ do mês (ata+rj, sem peças CPF, sem old)');
expect(n($abr, 1) === 1, 'abrangência: cliente CNPJ');
expect(n($abr, 2) === 1, 'abrangência: visita externa');
expect(n(run(['types' => ['abrangencia'], 'coverage_layer' => 'suppliers'])) === n($abr, 0)
    && count(run(['types' => ['abrangencia'], 'coverage_layer' => 'suppliers'])['sections']) === 1, 'abrangência: só fornecedores');
expect(count(run(['types' => ['abrangencia'], 'coverage_layer' => 'clients'])['sections']) === 1, 'abrangência: só clientes');
expect(count(run(['types' => ['abrangencia'], 'coverage_layer' => 'visits'])['sections']) === 1, 'abrangência: só visitas');
expect(n(run(['types' => ['abrangencia'], 'state' => 'RJ', 'coverage_layer' => 'suppliers'])) === 1, 'abrangência: UF');
expect(n(run(['types' => ['abrangencia'], 'city' => 'Campinas', 'coverage_layer' => 'suppliers'])) === 1, 'abrangência: cidade');

$forn = run(['types' => ['fornecedores']]);
expect(n($forn) === 3, 'fornecedores: 3 no mês');
expect(n(run(['types' => ['fornecedores'], 'product_type' => 'Limpeza'])) === 1, 'fornecedores: insumo');
expect(n(run(['types' => ['fornecedores'], 'document_kind' => 'cpf'])) === 1, 'fornecedores: CPF');
expect(n(run(['types' => ['fornecedores'], 'state' => 'RJ'])) === 1, 'fornecedores: UF');
expect(n(run(['types' => ['fornecedores'], 'city' => 'Campinas'])) === 1, 'fornecedores: cidade');
expect(n(run(['types' => ['fornecedores'], 'q' => 'Silva'])) === 1, 'fornecedores: nome');
expect(n(run(['types' => ['fornecedores'], 'from' => $oldDay, 'to' => $oldDay])) === 1, 'fornecedores: cadastro antigo');

$resumo = run(['types' => ['cliente_resumo']]);
expect($resumo['sections'][0]['title'] === 'Resumo por cliente', 'alias interno cliente_resumo');

$tree = file_get_contents(dirname(__DIR__).'/views/app/relatorios_fx.php');
$kindsUi = [
    'agendamentos' => ['name="appt_status"', 'name="source"', 'name="appt_type"', 'name="reserva"', 'fx_filter_period', 'fx_filter_people', 'fx_filter_services', 'fx_filter_totals(true)'],
    'solicitacoes' => ['name="q"', 'name="request_status"', 'name="source"', 'fx_filter_period', 'fx_filter_services', 'fx_filter_totals()'],
    'clientes' => ['name="client_status"', 'name="source"', 'name="state"', 'name="city"', 'name="admins"', 'fx_filter_period', 'fx_filter_people', 'fx_filter_services'],
    'agentes' => ['name="q"', 'name="agent_role"', 'name="agent_status"', 'fx_filter_period', 'fx_filter_people'],
    'servicos' => ['name="category"', 'name="service_status"', 'fx_filter_period', 'fx_filter_services'],
    'origens' => ['name="source"', 'fx_filter_period', 'fx_filter_totals()'],
    'documentos' => ['name="document_kind"', 'name="doc_check"', 'name="client_status"', 'fx_filter_period'],
    'documentos_cpf' => ['name="age_min"', 'name="age_max"', 'name="doc_check"', 'name="state"', 'name="city"', 'name="client_status"'],
    'documentos_cnpj' => ['name="doc_check"', 'name="client_status"', 'name="state"', 'name="city"', 'name="q"'],
    'documentos_agentes' => ['name="document_kind"', 'name="doc_check"', 'name="agent_status"', 'name="q"'],
    'financeiro' => ['name="finance_date"', 'name="finance_kind"', 'name="finance_flow"', 'name="finance_status"', 'name="payment_method"', 'name="min_amount"', 'name="max_amount"', 'fx_filter_people', 'fx_filter_services'],
    'contratos' => ['name="appt_status"', 'name="min_amount"', 'name="max_amount"', 'fx_filter_services'],
    'abrangencia' => ['name="coverage_layer"', 'name="state"', 'name="city"', 'fx_filter_period'],
    'fornecedores' => ['name="product_type"', 'name="document_kind"', 'name="state"', 'name="city"', 'name="q"'],
];
preg_match_all('/data-fx-kind="([^"]+)"/', $tree, $kindPanels);
foreach ($kindsUi as $kind => $needles) {
    if (!preg_match('/data-fx-kind="'.preg_quote($kind, '/').'"[^>]*>(.*?)<div class="fx-kind-panel"/s', $tree, $m)) {
        preg_match('/data-fx-kind="'.preg_quote($kind, '/').'"[^>]*>(.*)$/s', $tree, $m);
    }
    $chunk = $m[1] ?? '';
    $missing = [];
    foreach ($needles as $needle) {
        if (!str_contains($chunk, $needle)) {
            $missing[] = $needle;
        }
    }
    expect($missing === [], 'UI '.$kind.': '.($missing ? implode(',', $missing) : 'filtros do formulário presentes'));
}
expect(count($kindPanels[1]) === 14 && count(array_unique($kindPanels[1])) === 14, '14 painéis exclusivos na árvore');

@unlink($tmp);
exit($fail ? 1 : 0);
