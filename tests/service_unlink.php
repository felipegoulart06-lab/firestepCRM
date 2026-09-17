<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-svc-' . bin2hex(random_bytes(4)) . '.sqlite';
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
$day = date('Y-m-d', strtotime('+1 day'));
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-svc', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'cli-a', 'ten-a', 'Ana', '11999999999', 'ana@ex.com', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,price,status,created_at) VALUES(?,?,?,?,?,?,?)', [
    'svc-a', 'ten-a', 'Corte', 60, 100, 'ACTIVE', $now,
]);
q('INSERT INTO requests(id,tenant_id,name,phone,email,desired_date,desired_time,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'req-a', 'ten-a', 'Carlos', '11988887777', 'c@ex.com', $day, '10:00', 'site', 'NEW', $now,
]);
$tenant = ['id' => 'ten-a', 'business_hours' => null, 'display_name' => 'A', 'business_name' => 'Empresa A'];

$noSvc = create_appointment($tenant, [
    'client_id' => 'cli-a', 'date' => $day, 'start' => '08:00', 'status' => 'SCHEDULED', 'allow_waiting' => true,
]);
expect(empty($noSvc['ok']), 'agendamento sem serviço é recusado');

$ok = create_appointment($tenant, [
    'client_id' => 'cli-a', 'service_id' => 'svc-a', 'date' => $day, 'start' => '09:00',
    'status' => 'SCHEDULED', 'source' => 'Manual', 'allow_waiting' => true,
]);
expect(!empty($ok['ok']), 'agendamento com serviço é criado');

$req = one('SELECT * FROM requests WHERE id=?', ['req-a']);
$semServico = convert_request_to_appointment($tenant, $req, null);
expect(empty($semServico['ok']), 'converter solicitação sem serviço é recusado');
$comServico = convert_request_to_appointment($tenant, $req, null, 'svc-a');
expect(!empty($comServico['ok']), 'converter solicitação com serviço escolhido funciona');

q('UPDATE appointments SET service_id=NULL WHERE tenant_id=? AND service_id=?', ['ten-a', 'svc-a']);
q('UPDATE requests SET service_id=NULL WHERE tenant_id=? AND service_id=?', ['ten-a', 'svc-a']);
q('DELETE FROM services WHERE id=? AND tenant_id=?', ['svc-a', 'ten-a']);
$kept = one('SELECT * FROM appointments WHERE id=?', [$ok['id']]);
expect($kept !== null, 'excluir serviço mantém o agendamento');
expect(($kept['service_id'] ?? null) === null, 'agendamento perde só a atribuição do serviço');

$detail = appointment_detail('ten-a', (string)$ok['id']);
expect(empty($detail['service_name']), 'detalhes da reserva ficam sem serviço');

$pdf = build_pdf('Agendamento', ['Serviço: x'], $tenant);
expect(str_starts_with($pdf, '%PDF'), 'PDF continua sendo gerado');

$modal = file_get_contents(dirname(__DIR__).'/views/app/modal_appointment.php');
expect(str_contains($modal, 'saiu do catálogo'), 'detalhes avisam sobre serviço removido');
$pdfSrc = file_get_contents(dirname(__DIR__).'/app/pdf.php');
expect(str_contains($pdfSrc, "if (!empty(\$a['service_name']))"), 'PDF omite serviço quando não há atribuição');
$req_view = file_get_contents(dirname(__DIR__).'/views/app/solicitacoes.php');
expect(str_contains($req_view, 'name="service_id" required'), 'conversão exige serviço na tela');

unlink($tmp);
exit($fail ? 1 : 0);
