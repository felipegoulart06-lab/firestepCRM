<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-comm-' . bin2hex(random_bytes(4)) . '.sqlite';
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
$day = date('Y-m-d', strtotime('+2 days'));
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-comm', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-a', 'ten-a', 'Ana', '11999999999', 'ana@ex.com', '529.982.247-25', 'ACTIVE', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-pj', 'ten-a', 'Loja Ltda', '1133334444', 'pj@ex.com', '11.222.333/0001-81', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'svc-a', 'ten-a', 'Corte', 60, 200, 'ACTIVE', $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,document_kind,cpf,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    'ag-a', 'ten-a', 'Agente Ana', 'ag@ex.com', 'aga', 'x', 'user_agent', 'cpf', '390.533.447-05', 0, 1, $now,
]);
$tenant = ['id' => 'ten-a', 'business_hours' => null, 'display_name' => 'A', 'business_name' => 'Empresa A'];

$_POST = ['commission_on' => '1', 'commission_agent_id' => 'ag-a', 'commission_type' => 'fixed', 'commission_value' => '250'];
$tooMuch = parse_appointment_commission('ten-a', 200);
expect(empty($tooMuch['ok']), 'repasse maior que o serviço é recusado');

$_POST = ['commission_on' => '1', 'commission_agent_id' => 'ag-a', 'commission_type' => 'percent', 'commission_value' => '10'];
$okPct = parse_appointment_commission('ten-a', 200);
expect(!empty($okPct['ok']) && (float)$okPct['amount'] === 20.0, '10% de 200 vira R$ 20');

$res = create_appointment($tenant, [
    'client_id' => 'cli-a', 'service_id' => 'svc-a', 'date' => $day, 'start' => '10:00',
    'status' => 'SCHEDULED', 'source' => 'Manual', 'allow_waiting' => true,
    'commission' => $okPct,
]);
expect(!empty($res['ok']), 'cria agendamento já com comissão');
expect(one('SELECT status FROM appointments WHERE id=?', [$res['id']])['status'] === 'SCHEDULED', 'entra como agendado');

$recv = finance_find_source('ten-a', 'appointment', $res['id']);
$pay = finance_find_source('ten-a', 'appointment_commission', $res['id']);
expect($recv && $recv['status'] === 'open' && (float)$recv['amount'] === 200.0, 'a receber do serviço em aberto');
expect($pay && $pay['kind'] === 'payable' && (float)$pay['amount'] === 20.0 && $pay['agent_id'] === 'ag-a', 'repasse vira conta a pagar do agente');

$ov = finance_overview('ten-a');
expect($ov['previsto'] === 200.0, 'previsto é o valor do serviço');
expect($ov['receber'] === 200.0, 'a receber é o serviço');
expect($ov['repasse'] === 20.0, 'repasse entra no total de comissão');
expect($ov['lucro_presumido'] === 180.0, 'lucro presumido desconta o repasse');
expect($ov['lucro_liquido'] === 0.0, 'líquido ainda zero enquanto não recebe/paga');

q("UPDATE appointments SET status='DONE' WHERE id=? AND tenant_id=?", [$res['id'], 'ten-a']);
sync_appointment_finance('ten-a', $res['id']);
expect(finance_find_source('ten-a', 'appointment', $res['id'])['status'] === 'open', 'finalizar não duplica nem baixa sozinho o caixa');
expect(count(all("SELECT id FROM finance_entries WHERE tenant_id='ten-a' AND source_id=?", [$res['id']])) === 2, 'continuidade: um a receber e um repasse');

$docs = report_build($tenant, [
    'from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'appt_status' => 'ALL', 'client_status' => 'ALL',
    'finance_status' => 'all', 'user_ids' => [], 'admins_only' => false, 'service_ids' => [],
    'kinds' => ['documentos'], 'include_totals' => true, 'include_client_summary' => false,
]);
expect($docs['sections'][0]['title'] === 'Clientes CPF', 'bloco CPF');
expect($docs['sections'][1]['title'] === 'Clientes CNPJ', 'bloco CNPJ');
expect($docs['sections'][2]['rows'][0][0] === 'Agente Ana', 'documento do agente');

$modal = file_get_contents(dirname(__DIR__).'/views/app/modal_appointment.php');
expect(str_contains($modal, 'Repasse/comissão ao agente') && str_contains($modal, 'commission_type'), 'formulário de agendamento pede comissão');

@unlink($tmp);
exit($fail ? 1 : 0);
