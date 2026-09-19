<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-mx-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
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
    'ten-m', 'M', 'Empresa M', 'emp-m', 'outros', 'm@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO clients(id,tenant_id,name,phone,email,source,status,created_at) VALUES(?,?,?,?,?,?,?,?)', [
    'cli-m', 'ten-m', 'Cliente M', '11999990000', 'c@ex.com', 'WhatsApp', 'ACTIVE', $now,
]);
$tenant = ['id' => 'ten-m', 'primary_color' => '#2563eb', 'business_hours' => null];
$data = metrics_build($tenant, 30);
expect(isset($data['newClients'], $data['revenue'], $data['months']), 'dashboard monta KPIs e série mensal');
expect(count($data['months']) === 12, 'evolução usa 12 meses');
$view = file_get_contents(dirname(__DIR__) . '/views/app/metricas.php');
$css = file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
expect(str_contains($view, 'N° reserva') && str_contains($view, 'Agente') && str_contains($view, 'Tipo'), 'últimos agendamentos mostram reserva, agente e tipo');
expect(str_contains($view, '365 =>') && str_contains($view, 'Hoje'), 'recorte inclui hoje e 12 meses');
expect(str_contains($css, '.mx-kpis') && !str_contains($view, 'background:#000') && !str_contains($view, 'fundo preto'), 'visual claro do CRM, sem fundo preto');
$index = file_get_contents(dirname(__DIR__) . '/public/index.php');
expect(str_contains($index, 'metrics_build'), 'rota usa o dashboard novo');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    @unlink($tmp);
    exit(1);
}
echo "Métricas no estilo do painel, com o visual claro do CRM.\n";
@unlink($tmp);
