<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-cv-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('FIRESTEP_NO_GEO=1');
$_ENV['FIRESTEP_NO_GEO'] = '1';
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/finance.php';
require dirname(__DIR__) . '/app/core.php';

db();
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
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-cv', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-b', 'B', 'Empresa B', 'emp-b-cv', 'outros', 'b@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,cpf,source,status,created_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'cli-a', 'ten-a', 'Empresa Cliente', '11999990000', 'c@ex.com', '11.222.333/0001-81', 'Manual', 'ACTIVE', $now,
]);
q('INSERT INTO services(id,tenant_id,name,duration_minutes,status,created_at) VALUES(?,?,?,?,?,?)', [
    'svc-a', 'ten-a', 'Visita', 60, 'ACTIVE', $now,
]);
$tenantA = ['id'=>'ten-a','business_hours'=>null];

locate_client_if_cnpj('ten-a', 'cli-a', '11.222.333/0001-81', 'Av Paulista 1000', 'São Paulo', 'SP', '01310-100');
$cli = one('SELECT lat,lng FROM clients WHERE id=? AND tenant_id=?', ['cli-a','ten-a']);
expect($cli && $cli['lat'] !== null, 'cliente CNPJ ganha coordenadas');

q('INSERT INTO suppliers(id,tenant_id,name,cnpj,address,city,state,lat,lng,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'sup-a', 'ten-a', 'Fornecedor A', '00.000.000/0001-91', 'Rua X', 'Campinas', 'SP', -22.9, -47.0, $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,address,city,state,lat,lng,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'sup-b', 'ten-b', 'Fornecedor B', '00.000.000/0001-91', 'Rua Y', 'Curitiba', 'PR', -25.4, -49.2, $now,
]);

$res = create_appointment($tenantA, [
    'client_id'=>'cli-a','service_id'=>'svc-a','date'=>date('Y-m-d'),'start'=>'10:00',
    'status'=>'SCHEDULED','source'=>'Manual','user_id'=>null,'allow_waiting'=>true,
]);
expect(!empty($res['ok']), 'agendamento manual criado'.(empty($res['ok']) ? ' ('.($res['message']??'').')' : ''));
save_appointment_visits('ten-a', (string)$res['id'], [
    'external_visit'=>'1',
    'visit_addresses'=>['Rua Augusta 500, São Paulo, SP', 'Av Brasil 10, Rio de Janeiro, RJ'],
]);

$pins = coverage_pins('ten-a');
$kinds = array_column($pins, 'kind');
expect(in_array('supplier', $kinds, true), 'pin de fornecedor da empresa A');
expect(in_array('client', $kinds, true), 'pin de cliente CNPJ');
expect(in_array('visit', $kinds, true), 'pin de atendimento externo');
expect(isset($pins[0]['lat'], $pins[0]['lng']), 'pins têm latitude e longitude');
expect(count(array_filter($pins, fn($p) => $p['kind']==='visit')) === 2, 'dois endereços viram dois pins');
expect(!in_array('Fornecedor B', array_column($pins, 'label'), true), 'fornecedor de outro tenant não aparece');

$other = create_appointment($tenantA, [
    'client_id'=>'cli-a','service_id'=>'svc-a','date'=>date('Y-m-d'),'start'=>'14:00',
    'status'=>'SCHEDULED','source'=>'Webhook','user_id'=>null, 'allow_waiting'=>true,
]);
save_appointment_visits('ten-a', (string)$other['id'], [
    'external_visit'=>'1',
    'visit_addresses'=>['Praça da Sé, São Paulo, SP'],
]);
$pins2 = coverage_pins('ten-a');
expect(count(array_filter($pins2, fn($p) => $p['kind']==='visit')) === 2, 'agendamento que não é Manual não entra no mapa');

expect(agent_route_forbidden('/app/abrangencia'), 'agente não acessa Abrangência');
$view = file_get_contents(dirname(__DIR__).'/views/app/abrangencia.php');
expect(str_contains($view, 'Legenda do mapa') && str_contains($view, 'id="map"'), 'página tem mapa e legenda');
expect(str_contains($view, 'coverage-pins') && str_contains($view, 'loadMapbox') && str_contains($view, 'mapbox://styles/mapbox/streets-v12'), 'pins reais no Mapbox');
$modal = file_get_contents(dirname(__DIR__).'/views/app/modal_appointment.php');
expect(str_contains($modal, 'external_visit') && str_contains($modal, 'visit_addresses[]'), 'formulário de agendamento tem atendimento externo');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Abrangência: mapa, pins e atendimento externo aprovados.\n";
@unlink($tmp);
