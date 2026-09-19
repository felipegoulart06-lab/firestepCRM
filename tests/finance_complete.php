<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-fin-full-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/finance.php';
require dirname(__DIR__).'/app/core.php';
require dirname(__DIR__).'/app/reports.php';

db();
ensure_finance_schema();
appointment_commission_ensure_schema();

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

function ids(array $rows): array
{
    return array_column($rows, 'id');
}

$now = now();
$day = date('Y-m-d');
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$overdue = date('Y-m-d', strtotime('-10 days'));

q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-lume', 'Lume', 'Studio Lume', 'studio-lume', 'salao', 'lume@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-outra', 'Outra', 'Outra Clínica', 'outra-cli', 'outros', 'outra@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-lume', 'ten-lume', 'Agente Bruna', 'bruna@lume.ex', 'bruna', 'x', 'user_agent', 0, 1, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,whatsapp,email,cpf,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'cli-carla', 'ten-lume', 'Carla Mendes', '11987654321', '11987654321', 'carla@ex.com', '529.982.247-25', 'ACTIVE', $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'cli-outra', 'ten-outra', 'Pedro Outro', '11911110000', 'pedro@ex.com', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'svc-corte', 'ten-lume', 'Corte Lume', 60, 350, 'ACTIVE', $now,
]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,commission_agent_id,commission_type,commission_value,commission_amount,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'ap-lume', 'ten-lume', 'cli-carla', 'svc-corte', $day.' 14:00:00', $day.' 15:00:00', 'SCHEDULED', 'Manual',
    'ag-lume', 'fixed', 35, 35, $now,
]);
sync_appointment_finance('ten-lume', 'ap-lume');

q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,payment_method,amount_paid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-balcao', 'ten-lume', 'entry', 'in', 'paid', 'Venda balcão — shampoo', 120, $day, $now, 'cli-carla', 'Avulso no caixa', 'manual', null, 'dinheiro', 120, $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,notes,source_type,payment_method,amount_paid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-mat', 'ten-lume', 'entry', 'out', 'paid', 'Material de escritório', 30, $day, $now, 'Papelaria', 'manual', 'dinheiro', 30, $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-pix', 'ten-lume', 'receivable', 'in', 'open', 'Sessão extra PIX', 200, $day, 'cli-carla', 'manual', 'pix', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-fat', 'ten-lume', 'receivable', 'in', 'open', 'Pacote mensal fatura', 800, $day, 'cli-carla', 'manual', 'fatura', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-bol', 'ten-lume', 'receivable', 'in', 'open', 'Combo boleto', 40, $day, 'cli-carla', 'manual', 'boleto', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-venc', 'ten-lume', 'receivable', 'in', 'open', 'Atraso combo', 90, $overdue, 'cli-carla', 'manual', 'boleto', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,source_type,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-alug', 'ten-lume', 'payable', 'out', 'open', 'Aluguel da sala', 1500, $day, 'manual', $now, $now,
]);
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-outra', 'ten-outra', 'receivable', 'in', 'open', 'Consulta Pedro', 99, $day, 'cli-outra', 'manual', 'dinheiro', $now, $now,
]);

$pages = finance_pages();
expect(array_keys($pages) === ['dashboard', 'lancamentos', 'receber', 'faturado', 'pagar'], 'submenu: 5 ferramentas');
expect(!isset($pages['relatorios']), 'Relatório de caixa não fica no submenu Financeiro');
expect($pages['dashboard'][1] === '/app/financeiro', 'Dashboard em /app/financeiro');
expect($pages['lancamentos'][1] === '/app/financeiro/lancamentos', 'Lançamentos');
expect($pages['receber'][1] === '/app/financeiro/receber', 'Contas a receber');
expect($pages['faturado'][1] === '/app/financeiro/faturado', 'Faturado');
expect($pages['pagar'][1] === '/app/financeiro/pagar', 'Contas a pagar');
expect(finance_kind_for_page('lancamentos') === 'entry', 'lancamentos = caixa realizado');
expect(finance_kind_for_page('receber') === 'receivable' && finance_kind_for_page('faturado') === 'receivable', 'receber/faturado = a receber');
expect(finance_kind_for_page('pagar') === 'payable', 'pagar = a pagar');
expect(finance_back('/app/financeiro/receber') === '/app/financeiro/receber', 'back permanece no submenu');
expect(finance_back('https://evil.example/x') === '/app/financeiro', 'back externo cai no dashboard');

$nav = file_get_contents(dirname(__DIR__).'/views/layout_app_start.php');
expect(str_contains($nav, 'finance_pages()') && str_contains($nav, 'Financeiro'), 'menu lateral monta o submenu via finance_pages()');

$dash = file_get_contents(dirname(__DIR__).'/views/app/financeiro_dashboard.php');
expect(str_contains($dash, 'Previsto') && str_contains($dash, 'A receber') && str_contains($dash, 'Repasse'), 'dashboard: cards de previsto, receber e repasse');
expect(str_contains($dash, '/app/financeiro/receber') && str_contains($dash, '/app/financeiro/faturado') && str_contains($dash, '/app/financeiro/pagar'), 'dashboard: atalhos dos submenus');
expect(str_contains($dash, '/app/relatorios') && str_contains($dash, 'Relatório de caixa'), 'dashboard: caixa aponta para Relatórios');
expect(str_contains($dash, 'Últimos movimentos') && str_contains($dash, '/app/financeiro/lancamentos'), 'dashboard: últimos movimentos');

$lista = file_get_contents(dirname(__DIR__).'/views/app/financeiro_lista.php');
expect(str_contains($lista, 'name="q"') && str_contains($lista, 'Novo lançamento') && str_contains($lista, 'Nova conta a receber') && str_contains($lista, 'Nova conta a pagar'), 'listas: busca e cadastro');
expect(str_contains($lista, 'Faturar') && str_contains($lista, 'js-fin-receive') && str_contains($lista, 'Pagar') && str_contains($lista, 'Enviar cobrança'), 'listas: Faturar, Receber, Pagar, Cobrar');
expect(str_contains($lista, 'name="payment_method"') && str_contains($lista, 'pay_doc') && str_contains($lista, 'pay_installments'), 'formulário de baixa com forma, DOC e parcelas');
expect(str_contains($lista, 'finance_pay_methods()') && str_contains($lista, 'name="payment_method"'), 'formulário usa o catálogo de formas de pagamento');

$ov = finance_overview('ten-lume', $monthStart, $monthEnd);
$appt = finance_find_source('ten-lume', 'appointment', 'ap-lume');
$comm = finance_find_source('ten-lume', 'appointment_commission', 'ap-lume');
expect($appt && (float)$appt['amount'] === 350.0 && $appt['status'] === 'open', 'Dashboard/agendamento: R$ 350 a receber');
expect($comm && (float)$comm['amount'] === 35.0 && $comm['kind'] === 'payable', 'Dashboard: repasse R$ 35 em a pagar');
expect($ov['receber'] === 350.0 + 200.0 + 800.0 + 90.0 + 40.0, 'Dashboard: a receber = agendamento + PIX + fatura + atraso + boleto');
expect($ov['faturado'] === 0.0, 'Dashboard: faturado ainda zero');
expect($ov['pagar'] === 1500.0 + 35.0, 'Dashboard: a pagar = aluguel + repasse');
expect($ov['recebido'] === 120.0, 'Dashboard: recebido no mês = venda balcão');
expect($ov['saidas'] === 30.0, 'Dashboard: saídas = material (aluguel ainda aberto)');
expect($ov['saldo'] === 90.0, 'Dashboard: saldo 120-30');
expect($ov['vencido'] === 90.0, 'Dashboard: vencido = combo atrasado');
expect($ov['repasse'] === 35.0 && $ov['repasse_aberto'] === 35.0, 'Dashboard: card de repasse');
expect($ov['previsto'] === 350.0 + 200.0 + 800.0 + 90.0 + 40.0, 'Dashboard: previsto soma recebíveis');
expect($ov['lucro_presumido'] === $ov['previsto'] - 35.0, 'Dashboard: lucro presumido desconta repasse');

$lanc = finance_query('ten-lume', 'lancamentos');
expect(ids($lanc) === ['fin-balcao', 'fin-mat'] || (in_array('fin-balcao', ids($lanc), true) && in_array('fin-mat', ids($lanc), true) && count($lanc) === 2), 'Lançamentos: só entradas/saídas de caixa (kind=entry)');
expect(!in_array('fin-pix', ids($lanc), true), 'Lançamentos: conta a receber não mistura no caixa realizado');
$lancQ = finance_query('ten-lume', 'lancamentos', 'escritório');
expect(count($lancQ) === 1 && $lancQ[0]['id'] === 'fin-mat', 'Lançamentos: filtro por descrição');
$lancCli = finance_query('ten-lume', 'lancamentos', 'Carla');
expect(count($lancCli) === 1 && $lancCli[0]['id'] === 'fin-balcao', 'Lançamentos: filtro por cliente');

$rec = finance_query('ten-lume', 'receber');
$recIds = ids($rec);
expect(in_array('fin-pix', $recIds, true) && in_array('fin-fat', $recIds, true) && in_array('fin-venc', $recIds, true) && in_array('fin-bol', $recIds, true) && in_array($appt['id'], $recIds, true), 'A receber: PIX, fatura, atraso, boleto e agendamento');
expect(!in_array('fin-alug', $recIds, true), 'A receber: aluguel não entra');
$recQ = finance_query('ten-lume', 'receber', 'Pacote');
expect(count($recQ) === 1 && $recQ[0]['id'] === 'fin-fat', 'A receber: busca');

$denyPix = finance_set_status('ten-lume', 'fin-pix', 'paid');
expect(empty($denyPix['ok']), 'A receber: PIX sem NSU bloqueia');
$pixOk = finance_set_status('ten-lume', 'fin-pix', 'paid', ['payment_method' => 'pix', 'pay_doc' => 'E2E123456', 'amount_paid' => 200]);
expect(!empty($pixOk['ok']), 'A receber: PIX com DOC/NSU recebe');
expect(!in_array('fin-pix', ids(finance_query('ten-lume', 'receber')), true), 'A receber: PIX some depois do Receber');

$card = finance_set_status('ten-lume', 'fin-venc', 'paid', [
    'payment_method' => 'cartao', 'pay_doc' => 'NSU-4411', 'pay_installments' => 3, 'pay_installment_amount' => 30, 'amount_paid' => 90,
]);
expect(!empty($card['ok']), 'A receber: cartão com NSU e parcelas');
$vencRow = one("SELECT pay_doc,pay_installments,pay_installment_amount,status FROM finance_entries WHERE id='fin-venc'");
expect($vencRow['status'] === 'paid' && $vencRow['pay_doc'] === 'NSU-4411' && (int)$vencRow['pay_installments'] === 3, 'grava NSU e 3 parcelas');

$denyBillPay = finance_set_status('ten-lume', 'fin-fat', 'paid');
expect(empty($denyBillPay['ok']), 'Fatura não usa Receber');
$bill = finance_set_status('ten-lume', 'fin-fat', 'billed');
expect(!empty($bill['ok']), 'Faturar confirma o pacote mensal');
expect(!in_array('fin-fat', ids(finance_query('ten-lume', 'receber')), true), 'Fatura sai de A receber');
$fat = finance_query('ten-lume', 'faturado');
expect(ids($fat) === ['fin-fat'] || (count($fat) === 1 && $fat[0]['id'] === 'fin-fat'), 'Faturado: só o pacote');
expect($fat[0]['payment_method'] === 'fatura' && (float)$fat[0]['amount_paid'] === 800.0, 'Faturado: forma fatura e valor baixado');
$fatQ = finance_query('ten-lume', 'faturado', 'Carla');
expect(count($fatQ) === 1, 'Faturado: busca por cliente');
$noBillBol = finance_set_status('ten-lume', 'fin-bol', 'billed');
expect(empty($noBillBol['ok']), 'Faturar recusa boleto (só fatura)');

$pagar = finance_query('ten-lume', 'pagar');
$pagarIds = ids($pagar);
expect(in_array('fin-alug', $pagarIds, true) && in_array($comm['id'], $pagarIds, true), 'A pagar: aluguel e repasse da Bruna');
$pagarQ = finance_query('ten-lume', 'pagar', 'Bruna');
expect(count($pagarQ) === 1 && $pagarQ[0]['id'] === $comm['id'], 'A pagar: busca pelo agente');
$payAlug = finance_set_status('ten-lume', 'fin-alug', 'paid');
expect(!empty($payAlug['ok']), 'A pagar: Pagar registra o aluguel');
$alug = one("SELECT status,paid_at FROM finance_entries WHERE id='fin-alug'");
expect($alug['status'] === 'paid' && $alug['paid_at'], 'aluguel pago');
expect(in_array('fin-alug', ids(finance_query('ten-lume', 'pagar')), true), 'A pagar ainda lista pagos (histórico)');

$ov2 = finance_overview('ten-lume', $monthStart, $monthEnd);
expect($ov2['receber'] === 350.0 + 40.0, 'depois das baixas restam agendamento e boleto em aberto');
expect($ov2['faturado'] === 800.0, 'faturado = pacote');
expect($ov2['recebido'] === 120.0 + 200.0 + 90.0 + 800.0, 'recebido inclui PIX, cartão, balcão e faturado');
expect($ov2['saidas'] === 30.0 + 1500.0, 'saídas incluem aluguel pago');
expect($ov2['pagar'] === 35.0, 'a pagar restante = só repasse');

$charge = finance_charge_card(
    ['id' => 'ten-lume', 'display_name' => 'Studio Lume', 'business_name' => 'Studio Lume', 'phone' => '1133334444', 'whatsapp' => '', 'website' => '', 'uazapi_config' => '{}'],
    ['client_name' => 'Carla Mendes', 'client_phone' => '11987654321', 'client_whatsapp' => '11987654321', 'amount' => 350, 'description' => 'Agendamento · Corte Lume', 'due_date' => $day]
);
expect(!empty($charge['can_send']) && str_contains($charge['description'], 'Carla') && str_contains($charge['description'], '350'), 'Cobrar: card WhatsApp com cliente e valor');

$outra = finance_query('ten-outra', 'receber');
expect(count($outra) === 1 && $outra[0]['id'] === 'fin-outra', 'isolamento: Outra Clínica só vê a consulta do Pedro');
expect(finance_query('ten-lume', 'receber', 'Pedro') === [], 'isolamento: Lume não lista Pedro');
expect($ov2['receber'] !== finance_overview('ten-outra')['receber'], 'isolamento: dashboards diferentes');

$caixa = report_build(['id' => 'ten-lume', 'display_name' => 'Studio Lume', 'business_name' => 'Studio Lume'], [
    'from' => date('Y-m-01'),
    'to' => $day,
    'appt_status' => 'ALL',
    'client_status' => 'ALL',
    'finance_status' => 'all',
    'finance_kind' => 'all',
    'finance_flow' => 'all',
    'finance_date' => 'competence',
    'payment_method' => '',
    'user_ids' => [],
    'admins_only' => false,
    'service_ids' => [],
    'kinds' => ['financeiro'],
    'include_totals' => true,
    'include_client_summary' => false,
    'min_amount' => null,
    'max_amount' => null,
]);
$caixaDesc = array_column($caixa['sections'][0]['rows'] ?? [], 2);
expect(in_array('Aluguel da sala', $caixaDesc, true) && in_array('Pacote mensal fatura', $caixaDesc, true), 'Relatório de caixa inclui aluguel e fatura');
expect(!str_contains(json_encode($caixa), 'Consulta Pedro'), 'caixa não vaza o outro tenant');
$cxPay = report_build(['id' => 'ten-lume', 'display_name' => 'Studio Lume', 'business_name' => 'Studio Lume'], [
    'from' => date('Y-m-01'), 'to' => $day, 'appt_status' => 'ALL', 'client_status' => 'ALL',
    'finance_status' => 'billed', 'finance_kind' => 'receivable', 'finance_flow' => 'all',
    'finance_date' => 'competence', 'payment_method' => 'fatura', 'user_ids' => [], 'admins_only' => false,
    'service_ids' => [], 'kinds' => ['financeiro'], 'include_totals' => true, 'include_client_summary' => false,
    'min_amount' => null, 'max_amount' => null,
]);
expect(count($cxPay['sections'][0]['rows']) === 1, 'caixa filtrado: só faturado em fatura');

$cancel = finance_set_status('ten-lume', $appt['id'], 'cancelled');
expect(!empty($cancel['ok']), 'cancelar recebível em aberto');
expect(one("SELECT status FROM finance_entries WHERE id=?", [$appt['id']])['status'] === 'cancelled', 'agendamento some das listas ativas');
expect(!in_array($appt['id'], ids(finance_query('ten-lume', 'receber')), true), 'cancelado fora de A receber');
expect(!in_array($appt['id'], ids(finance_query('ten-lume', 'lancamentos')), true), 'cancelado fora de Lançamentos');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "$fail falha(s) no teste completo do Financeiro.\n");
    exit(1);
}
echo "Financeiro + submenus: todos os recortes com dados fictícios passaram.\n";
exit(0);
