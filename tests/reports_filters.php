<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-rep-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/finance.php';
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/reports.php';

db();
ensure_finance_schema();

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

$now = now();
$day = date('Y-m-d');
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-rep', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-b', 'B', 'Empresa B', 'emp-b-rep', 'outros', 'b@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ua', 'ten-a', 'Admin A', 'ua@ex.com', 'ua', 'x', 'TENANT_ADMIN', 0, 1, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'cli-a', 'ten-a', 'Ana', '11', 'ana@ex.com', '529.982.247-25', 'Instagram', 'ACTIVE', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-b', 'ten-b', 'Bia', '22', 'bia@ex.com', 'Google', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'svc-a', 'ten-a', 'Corte', 60, 100, 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'svc-x', 'ten-a', 'Barba', 30, 40, 'ACTIVE', $now,
]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-a', 'ten-a', 'cli-a', 'svc-a', $day.' 10:00:00', $day.' 11:00:00', 'SCHEDULED', 'Manual', $now,
]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-x', 'ten-a', 'cli-a', 'svc-x', $day.' 14:00:00', $day.' 14:30:00', 'DONE', 'Manual', $now,
]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-b', 'ten-b', 'cli-b', 'svc-a', $day.' 10:00:00', $day.' 11:00:00', 'SCHEDULED', 'Manual', $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-a', 'ten-a', 'Agente Ana', 'ag@ex.com', 'ag', 'x', 'user_agent', 0, 1, $now,
]);
q('INSERT INTO requests(id,tenant_id,service_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'req-a', 'ten-a', 'svc-a', 'Carlos', '11988887777', 'c@ex.com', 'site', 'NEW', $now,
]);
q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-a', 'ten-a', 'ua', 'appointment.created', 'appointment', 'ap-a', $now,
]);
q('INSERT INTO audit_logs(id,tenant_id,user_id,action,entity,entity_id,created_at) VALUES(?,?,?,?,?,?,?)', [
    'lg-ag', 'ten-a', 'ag-a', 'appointment.created', 'appointment', 'ap-x', $now,
]);

$tenantA = ['id' => 'ten-a', 'display_name' => 'A', 'business_name' => 'Empresa A'];
$base = [
    'from' => date('Y-m-01'),
    'to' => $day,
    'appt_status' => 'ALL',
    'client_status' => 'ALL',
    'finance_status' => 'all',
    'user_ids' => [],
    'admins_only' => false,
    'service_ids' => [],
    'kinds' => ['atendimentos'],
    'include_totals' => true,
    'include_client_summary' => false,
];

$all = report_build($tenantA, $base);
expect(count($all['sections'][0]['rows']) === 2, 'atendimentos do tenant A: 2');
expect(!str_contains(json_encode($all), 'Bia'), 'não vaza cliente do tenant B');

$svc = report_build($tenantA, array_merge($base, ['service_ids' => ['svc-x']]));
expect(count($svc['sections'][0]['rows']) === 1, 'filtro por serviço');
expect($svc['sections'][0]['rows'][0][4] === 'Barba', 'serviço filtrado é Barba');

$st = report_build($tenantA, array_merge($base, ['appt_status' => 'DONE']));
expect(count($st['sections'][0]['rows']) === 1, 'filtro por status');

$adm = report_build($tenantA, array_merge($base, ['admins_only' => true]));
expect(count($adm['sections'][0]['rows']) === 1, 'somente admin (audit)');

$sum = report_build($tenantA, array_merge($base, ['kinds' => ['cliente_resumo']]));
expect($sum['sections'][0]['title'] === 'Resumo por cliente', 'tipo resumo de cliente');
expect((int)$sum['sections'][0]['rows'][0][1] === 2, 'Ana com 2 atendimentos');

$orig = report_build($tenantA, array_merge($base, ['kinds' => ['origens']]));
expect($orig['sections'][0]['rows'][0][0] === 'Instagram', 'origem Instagram');

coverage_ensure_schema();
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'sup-a', 'ten-a', 'Peças Silva', '529.982.247-25', 'cpf', 'Peças', 'São Paulo', 'SP', $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'sup-cnpj', 'ten-a', 'Atacado Ltda', '11.222.333/0001-81', 'cnpj', 'Limpeza', 'Campinas', 'SP', $now,
]);
$forn = report_build($tenantA, array_merge($base, ['kinds' => ['fornecedores']]));
expect($forn['sections'][0]['title'] === 'Fornecedores', 'relatório de fornecedores');
expect(count($forn['sections'][0]['rows']) === 2, 'dois fornecedores no período');
$abr = report_build($tenantA, array_merge($base, ['kinds' => ['abrangencia']]));
expect(count($abr['sections']) === 3, 'abrangência com três blocos');
expect($abr['sections'][0]['rows'][0][0] === 'Atacado Ltda', 'só CNPJ no bloco de mapa de fornecedores');

$agenda = report_build($tenantA, array_merge($base, ['kinds' => ['agendamentos']]));
expect($agenda['sections'][0]['title'] === 'Agendamentos', 'tipo agendamentos');
expect($agenda['sections'][0]['headers'] === ['N° reserva', 'Data', 'Cliente', 'Agente', 'Serviço', 'Valor', 'Tipo', 'Status'], 'PDF de agendamentos com reserva, agente, valor e tipo');

q("UPDATE appointments SET commission_agent_id='ua' WHERE id='ap-x' AND tenant_id='ten-a'");
$byAgent = report_build($tenantA, array_merge($base, ['user_ids' => ['ua']]));
expect(count($byAgent['sections'][0]['rows']) === 2, 'filtro de equipe inclui agente da comissão');

$nRes = (int)(one("SELECT reserva_n FROM appointments WHERE id='ap-a' AND tenant_id='ten-a'")['reserva_n'] ?? 0);
$byRes = report_build($tenantA, array_merge($base, ['reserva_n' => $nRes]));
expect(count($byRes['sections'][0]['rows']) === 1, 'filtro por N° reserva');
expect($byRes['sections'][0]['rows'][0][2] === 'Ana', 'reserva filtrada é da Ana');

q("INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,source_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)", [
    'fin-bill', 'ten-a', 'receivable', 'in', 'billed', 'Corte faturado', 100, $day, 'cli-a', 'appointment', 'ap-a', $now, $now,
]);
$billed = report_build($tenantA, array_merge($base, ['appt_type' => 'billed']));
expect(count($billed['sections'][0]['rows']) === 1, 'filtro tipo Faturado');
expect($billed['sections'][0]['rows'][0][6] === 'Faturado', 'coluna Tipo = Faturado');

$req = report_build($tenantA, array_merge($base, ['kinds' => ['solicitacoes']]));
expect($req['sections'][0]['title'] === 'Solicitações', 'tipo solicitações');
expect(count($req['sections'][0]['rows']) === 1, 'uma solicitação no período');

$agents = report_build($tenantA, array_merge($base, ['kinds' => ['agentes']]));
expect($agents['sections'][0]['rows'][0][0] === 'Agente Ana', 'relatório de agentes');
expect((int)$agents['sections'][0]['rows'][0][4] === 1, 'agente com 1 agendamento no período');

$svcRep = report_build($tenantA, array_merge($base, ['kinds' => ['servicos']]));
expect(count($svcRep['sections'][0]['rows']) === 2, 'dois serviços no catálogo');

$tree = file_get_contents(dirname(__DIR__).'/views/app/relatorios_fx.php');
expect(!isset(finance_pages()['relatorios']), 'caixa sai do submenu Financeiro e fica em Relatórios');
expect(str_contains($tree, "'kind' => 'financeiro'") && str_contains($tree, 'Relatório de caixa'), 'caixa no menu Relatórios com os demais');
expect(!str_contains($tree, "folder['name'] ?? '') === 'Financeiro'"), 'nenhuma pasta abre sozinha ao entrar em Relatórios');
expect(substr_count($tree, '<ul class="fx-kids" hidden>') >= 1 && !preg_match('/fx-folder is-open/', $tree), 'todas as pastas começam fechadas');
expect(str_contains($tree, "'name' => 'Abrangência'") && str_contains($tree, "'name' => 'Fornecedores'"), 'pastas Abrangência e Fornecedores');
expect(str_contains($tree, "'kind' => 'agendamentos'") && str_contains($tree, "'kind' => 'agentes'"), 'árvore com agendamentos e agentes');
expect(str_contains($tree, 'name="appt_type"') && str_contains($tree, 'name="reserva"'), 'agendamentos filtram tipo e N° reserva');
expect(str_contains($tree, 'data-fx-kind="clientes"') && str_contains($tree, 'name="state"'), 'clientes filtram UF');
expect(str_contains($tree, 'data-fx-kind="financeiro"') && str_contains($tree, 'name="min_amount"'), 'caixa filtra faixa de valor');
expect(str_contains($tree, "'name' => 'Documentos'") && str_contains($tree, "'kind' => 'documentos_cpf'"), 'pasta Documentos com CPF/CNPJ/agentes');

$docs = report_build($tenantA, array_merge($base, ['kinds' => ['documentos']]));
expect(count($docs['sections']) === 3, 'documentos com clientes CPF, CNPJ e agentes');
expect($docs['sections'][0]['rows'][0][0] === 'Ana', 'cliente CPF no relatório de documentos');
expect(!str_contains($tree, "icon('pdf'"), 'lista de relatórios sem ícone de PDF');

$_GET = ['from' => $base['from'], 'to' => $base['to'], 'types' => ['clientes', 'origens']];
$parsed = report_filters_from_request();
expect($parsed['kinds'] === ['clientes'], 'um único tipo por request, mesmo com types[] mistos');

$reqLost = report_build($tenantA, array_merge($base, ['kinds' => ['solicitacoes'], 'request_status' => 'LOST']));
expect(count($reqLost['sections'][0]['rows']) === 0, 'filtro de status da solicitação');

$cliSrc = report_build($tenantA, array_merge($base, ['kinds' => ['clientes'], 'source' => 'Instagram']));
expect(count($cliSrc['sections'][0]['rows']) === 1, 'filtro de origem de clientes');

$fornProd = report_build($tenantA, array_merge($base, ['kinds' => ['fornecedores'], 'product_type' => 'Peças']));
expect(count($fornProd['sections'][0]['rows']) === 1, 'filtro de categoria de insumo');
expect($fornProd['sections'][0]['rows'][0][0] === 'Peças Silva', 'fornecedor filtrado por produto');

q("INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,agent_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)", [
    'fin-ag', 'ten-a', 'payable', 'out', 'open', 'Repasse', 50, $day, 'ag-a', $now, $now,
]);
q("INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,agent_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)", [
    'fin-big', 'ten-a', 'payable', 'out', 'open', 'Fornecedor', 200, $day, 'ua', $now, $now,
]);
$finAgent = report_build($tenantA, array_merge($base, ['kinds' => ['financeiro'], 'user_ids' => ['ag-a']]));
expect(count($finAgent['sections'][0]['rows']) === 1, 'caixa filtrado pelo agente');
$finMin = report_build($tenantA, array_merge($base, ['kinds' => ['financeiro'], 'min_amount' => 150]));
expect(count($finMin['sections'][0]['rows']) === 1, 'caixa filtrado por valor mínimo');
expect(str_contains($finMin['sections'][0]['rows'][0][2], 'Fornecedor'), 'lançamento de 200 no filtro de valor');

$_GET = ['from' => $base['from'], 'to' => $base['to'], 'types' => ['agendamentos'], 'reserva' => '#12', 'appt_type' => 'billed'];
$parsedAppt = report_filters_from_request();
expect($parsedAppt['reserva_n'] === 12 && $parsedAppt['appt_type'] === 'billed', 'request parseia reserva e tipo');

preg_match_all('/data-fx-kind="([^"]+)"/', $tree, $kindPanels);
expect(count($kindPanels[1]) === 14 && count(array_unique($kindPanels[1])) === 14, '14 containers de filtro distintos');
expect(!str_contains($tree, 'id="fx-types"'), 'sem lista compartilhada de tipos de relatório');
expect(str_contains(file_get_contents(dirname(__DIR__).'/public/assets/app.js'), 'dataset.fxKind'), 'JS troca o painel pelo kind do arquivo');

unlink($tmp);
exit($fail ? 1 : 0);
