<?php
declare(strict_types=1);

$root = dirname(__DIR__);
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

$landing = file_get_contents($root . '/Firestep.cloud/index.html');
$js = file_get_contents($root . '/Firestep.cloud/script.js');
$nav = file_get_contents($root . '/views/layout_master_start.php');
$obj = file_get_contents($root . '/views/master/objetivo.php');
$home = file_get_contents($root . '/views/master/home.php');
$index = file_get_contents($root . '/public/index.php');

expect(str_contains($landing, 'templates.firestep.cloud') && str_contains($landing, 'FirestepCRM'), 'landing vende template + CRM');
expect(str_contains($landing, 'solução completa') || str_contains($landing, 'solucao-completa'), 'landing fala solução completa');
expect(str_contains($landing, 'id="interesse"'), 'formulário escolhe template, CRM ou os dois');
expect(str_contains($js, 'utm_campaign: "solucao-completa"'), 'webhook marca campanha da oferta completa');
expect(str_contains($nav, '/master/objetivo'), 'menu Master tem Objetivo');
expect(str_contains($index, "/master/objetivo"), 'rota Objetivo no front controller');
expect(str_contains($obj, 'assinaturas recorrentes') && str_contains($obj, 'não é o objetivo principal'), 'texto permanente no painel');
expect(str_contains($home, '/master/objetivo'), 'dashboard aponta para Objetivo');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Landing Firestep + Objetivo Master alinhados à oferta site+CRM.\n";
