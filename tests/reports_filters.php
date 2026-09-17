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
expect($svc['sections'][0]['rows'][0][2] === 'Barba', 'serviço filtrado é Barba');

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

$req = report_build($tenantA, array_merge($base, ['kinds' => ['solicitacoes']]));
expect($req['sections'][0]['title'] === 'Solicitações', 'tipo solicitações');
expect(count($req['sections'][0]['rows']) === 1, 'uma solicitação no período');

$agents = report_build($tenantA, array_merge($base, ['kinds' => ['agentes']]));
expect($agents['sections'][0]['rows'][0][0] === 'Agente Ana', 'relatório de agentes');
expect((int)$agents['sections'][0]['rows'][0][4] === 1, 'agente com 1 agendamento no período');

$svcRep = report_build($tenantA, array_merge($base, ['kinds' => ['servicos']]));
expect(count($svcRep['sections'][0]['rows']) === 2, 'dois serviços no catálogo');

$tree = file_get_contents(dirname(__DIR__).'/views/app/relatorios_fx.php');
expect(str_contains($tree, "'name' => 'Abrangência'") && str_contains($tree, "'name' => 'Fornecedores'"), 'pastas Abrangência e Fornecedores');
expect(str_contains($tree, "'kind' => 'agendamentos'") && str_contains($tree, "'kind' => 'agentes'"), 'árvore com agendamentos e agentes');
expect(str_contains($tree, "'name' => 'Documentos'") && str_contains($tree, "'kind' => 'documentos_cpf'"), 'pasta Documentos com CPF/CNPJ/agentes');

$docs = report_build($tenantA, array_merge($base, ['kinds' => ['documentos']]));
expect(count($docs['sections']) === 3, 'documentos com clientes CPF, CNPJ e agentes');
expect($docs['sections'][0]['rows'][0][0] === 'Ana', 'cliente CPF no relatório de documentos');
expect(!str_contains($tree, "icon('pdf'"), 'lista de relatórios sem ícone de PDF');

$_GET = ['from' => $base['from'], 'to' => $base['to'], 'types' => ['clientes', 'origens']];
$parsed = report_filters_from_request();
expect($parsed['kinds'] === ['clientes', 'origens'], 'types[] no request');

unlink($tmp);
exit($fail ? 1 : 0);
