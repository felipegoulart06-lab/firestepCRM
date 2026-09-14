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

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "$fail teste(s) financeiros falharam.\n");
    exit(1);
}
echo "Fluxo financeiro A receber → Recebido, cancelamento e isolamento aprovados.\n";
