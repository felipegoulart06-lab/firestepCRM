<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-fin-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/finance.php';
require dirname(__DIR__) . '/app/core.php';

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
$insT = function (string $id, string $slug) use ($now) {
    q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
        $id, $id, 'Empresa '.$id, $slug, 'outros', $id.'@ex.com', 'ACTIVE', $now, $now,
    ]);
};
$insT('ten-a', 'emp-a-fin');
$insT('ten-b', 'emp-b-fin');
q('INSERT INTO clients(id,tenant_id,name,phone,email,status,created_at) VALUES(?,?,?,?,?,?,?)', ['cli-a','ten-a','Ana','11999999991','ana@ex.com','ACTIVE',$now]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,status,created_at) VALUES(?,?,?,?,?,?,?)', ['cli-b','ten-b','Bia','11999999992','bia@ex.com','ACTIVE',$now]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', ['svc-a','ten-a','Corte',60,500,'ACTIVE',$now]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', ['svc-b','ten-b','Coloração',60,150,'ACTIVE',$now]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-a','ten-a','cli-a','svc-a',$now,$now,'SCHEDULED','Manual',$now,
]);
q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-b','ten-b','cli-b','svc-b',$now,$now,'SCHEDULED','Manual',$now,
]);

sync_appointment_finance('ten-a', 'ap-a');
sync_appointment_finance('ten-a', 'ap-a');
sync_appointment_finance('ten-b', 'ap-b');

$aRows = all("SELECT * FROM finance_entries WHERE tenant_id='ten-a' AND source_type='appointment'");
expect(count($aRows) === 1, 'um lançamento por agendamento (sem duplicar)');
expect((float)$aRows[0]['amount'] === 500.0, 'agendamento A gera R$ 500 a receber');
expect($aRows[0]['status'] === 'open', 'status inicial aberto');
expect($aRows[0]['source_id'] === 'ap-a', 'origem aponta para o agendamento');

$ovA = finance_overview('ten-a');
expect($ovA['receber'] === 500.0, 'dashboard A receber = 500');
expect($ovA['recebido'] === 0.0, 'dashboard A recebido ainda 0');
expect($ovA['previsto'] === 500.0, 'previsto inclui a receber');

$ovB = finance_overview('ten-b');
expect($ovB['receber'] === 150.0, 'tenant B isolado com 150');
expect(all("SELECT id FROM finance_entries WHERE tenant_id='ten-a' AND source_id='ap-b'") === [], 'A não vê lançamento de B');

q("UPDATE finance_entries SET status='paid', paid_at=?, amount_paid=amount, updated_at=? WHERE id=? AND tenant_id=?", [
    $now, $now, $aRows[0]['id'], 'ten-a',
]);
$ovA2 = finance_overview('ten-a', date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'));
expect($ovA2['receber'] === 0.0, 'após Receber, sai de a receber');
expect($ovA2['recebido'] === 500.0, 'entra em recebido');
expect($ovA2['previsto'] === 500.0, 'previsto permanece 500');

q("UPDATE appointments SET status='CANCELLED' WHERE id='ap-b' AND tenant_id='ten-b'");
sync_appointment_finance('ten-b', 'ap-b');
$ovB2 = finance_overview('ten-b');
expect($ovB2['receber'] === 0.0, 'cancelado pendente some das projeções');
expect($ovB2['previsto'] === 0.0, 'cancelado não fatura');

q("UPDATE services SET price=250 WHERE id='svc-a' AND tenant_id='ten-a'");
sync_appointment_finance('ten-a', 'ap-a');
$paid = one("SELECT amount FROM finance_entries WHERE tenant_id='ten-a' AND source_id='ap-a'");
expect((float)$paid['amount'] === 500.0, 'já pago não muda o valor com o serviço');

q('INSERT INTO appointments(id,tenant_id,client_id,service_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ap-a2','ten-a','cli-a','svc-a',$now,$now,'SCHEDULED','Manual',$now,
]);
sync_appointment_finance('ten-a', 'ap-a2');
$open = one("SELECT amount,status FROM finance_entries WHERE tenant_id='ten-a' AND source_id='ap-a2'");
expect((float)$open['amount'] === 250.0 && $open['status'] === 'open', 'novo agendamento usa preço atual 250');

q("UPDATE finance_entries SET status='paid', paid_at=?, amount_paid=amount WHERE tenant_id='ten-a' AND source_id='ap-a2'", [$now]);
q("UPDATE appointments SET status='CANCELLED' WHERE id='ap-a2' AND tenant_id='ten-a'");
sync_appointment_finance('ten-a', 'ap-a2');
$rev = finance_find_source('ten-a', 'appointment_reversal', 'ap-a2');
expect($rev && $rev['flow'] === 'out' && (float)$rev['amount'] === 250.0, 'estorno após receber');
sync_appointment_finance('ten-a', 'ap-a2');
$revs = all("SELECT id FROM finance_entries WHERE tenant_id='ten-a' AND source_type='appointment_reversal' AND source_id='ap-a2'");
expect(count($revs) === 1, 'estorno não duplica');

q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,paid_at,client_id,notes,source_type,source_id,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-inv','ten-a','receivable','in','open','Mensalidade fatura',800,finance_invoice_due_date(),null,'cli-a',null,'manual',null,'fatura',$now,$now,
]);
$deny = finance_set_status('ten-a', 'fin-inv', 'paid');
expect(empty($deny['ok']), 'fatura não aceita Receber');
expect(count(finance_query('ten-a', 'receber')) >= 1, 'fatura permanece em A receber');
$bill = finance_set_status('ten-a', 'fin-inv', 'billed');
expect(!empty($bill['ok']), 'Faturar confirma a fatura');
$inv = one("SELECT status,payment_method,amount_paid FROM finance_entries WHERE id='fin-inv'");
expect($inv['status'] === 'billed' && $inv['payment_method'] === 'fatura' && (float)$inv['amount_paid'] === 800.0, 'status faturado com baixa');
$receberIds = array_column(finance_query('ten-a', 'receber'), 'id');
$faturadoIds = array_column(finance_query('ten-a', 'faturado'), 'id');
expect(!in_array('fin-inv', $receberIds, true), 'sai de A receber depois de Faturar');
expect(in_array('fin-inv', $faturadoIds, true), 'entra em Faturado');
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-inv2','ten-a','receivable','in','open','Fatura sem cliente',10,null,null,'manual','fatura',$now,$now,
]);
$needCli = finance_set_status('ten-a', 'fin-inv2', 'billed');
expect(empty($needCli['ok']), 'fatura exige cliente CPF ou CNPJ');
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,payment_method,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-pix','ten-a','receivable','in','open','Avulso PIX',50,null,'cli-a','manual','pix',$now,$now,
]);
$pix = finance_set_status('ten-a', 'fin-pix', 'paid');
expect(empty($pix['ok']), 'PIX em aberto exige DOC ou NSU');
$pixOk = finance_set_status('ten-a', 'fin-pix', 'paid', ['payment_method' => 'pix', 'pay_doc' => 'NSU-9988', 'amount_paid' => 50]);
expect(!empty($pixOk['ok']), 'PIX continua com Receber depois do comprovante');
$pixRow = one("SELECT pay_doc,amount_paid,payment_method FROM finance_entries WHERE id='fin-pix'");
expect($pixRow['pay_doc'] === 'NSU-9988' && (float)$pixRow['amount_paid'] === 50.0, 'grava NSU e valor recebido');
$cashNeed = finance_set_status('ten-a', 'fin-pix', 'paid', ['payment_method' => 'dinheiro']);
expect(empty($cashNeed['ok']), 'já baixado não aceita segunda baixa');
q('INSERT INTO finance_entries(id,tenant_id,kind,flow,status,description,amount,due_date,client_id,source_type,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    'fin-open','ten-a','receivable','in','open','Avulso',20,null,'cli-a','manual',$now,$now,
]);
$needMethod = finance_set_status('ten-a', 'fin-open', 'paid');
expect(empty($needMethod['ok']), 'receber exige forma de pagamento');
$cash = finance_set_status('ten-a', 'fin-open', 'paid', ['payment_method' => 'dinheiro']);
expect(!empty($cash['ok']), 'dinheiro não pede DOC');
$src = file_get_contents(dirname(__DIR__).'/views/app/financeiro_lista.php');
expect(str_contains($src, 'name="payment_method"') && str_contains($src, 'Faturar'), 'formulário pede forma de pagamento e Faturar');
expect(str_contains($src, 'js-fin-charge') && str_contains($src, 'Enviar cobrança'), 'Cobrar abre card para WhatsApp');
expect(uazapi_wa_number('(11) 99999-9991') === '5511999999991', 'telefone vira número WhatsApp');
$card = finance_charge_card(['id'=>'ten-a','display_name'=>'Empresa A','business_name'=>'Empresa A','phone'=>'11911112222','whatsapp'=>'','website'=>'','uazapi_config'=>'{}'], [
    'client_name' => 'Ana', 'client_phone' => '11999999991', 'client_whatsapp' => '', 'amount' => 50, 'description' => 'Avulso PIX', 'due_date' => $now,
]);
expect($card['can_send'] && str_contains($card['description'], 'Ana') && str_contains($card['description'], '50'), 'card de cobrança com nome e valor');
$edited = finance_charge_apply_edits($card, [
    'charge_edited' => '1',
    'charge_title' => 'Título livre',
    'charge_description' => 'Texto do card',
    'charge_image' => 'https://exemplo.com/card.png',
    'charge_btn_text' => ['Pagar agora', 'Ligar'],
    'charge_btn_type' => ['URL', 'CALL'],
    'charge_btn_value' => ['exemplo.com/pagar', '11988887777'],
]);
expect($edited['title'] === 'Título livre' && $edited['description'] === 'Texto do card', 'Cobrar aceita título e descrição editados');
expect($edited['image'] === 'https://exemplo.com/card.png', 'Cobrar aceita imagem por URL');
expect(($edited['buttons_api'][0]['type'] ?? '') === 'URL' && str_starts_with((string)($edited['buttons_api'][0]['id'] ?? ''), 'https://'), 'botão de link guarda a URL');
expect(($edited['buttons_api'][1]['type'] ?? '') === 'CALL' && ($edited['buttons_api'][1]['text'] ?? '') === 'Ligar', 'botão de ligar usa o texto editado');
$badImg = finance_charge_apply_edits($card, ['charge_edited'=>'1','charge_title'=>'X','charge_description'=>'Y','charge_image'=>'javascript:alert(1)','charge_btn_text'=>[],'charge_btn_type'=>[],'charge_btn_value'=>[]]);
expect($badImg['image'] === '', 'imagem inválida é ignorada');
$js = file_get_contents(dirname(__DIR__).'/public/assets/app.js');
expect(str_contains($src, 'charge_title') && str_contains($src, 'Adicionar botão') && str_contains($js, 'charge_btn_text[]'), 'card de cobrança tem campos editáveis');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "$fail teste(s) financeiros falharam.\n");
    exit(1);
}
echo "Fluxo financeiro A receber → Recebido, cancelamento e isolamento aprovados.\n";
