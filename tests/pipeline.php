<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/helpers.php';
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

$nav = file_get_contents($root . '/views/layout_app_start.php');
$kanban = file_get_contents($root . '/views/app/kanban.php');
$index = file_get_contents($root . '/public/index.php');

$metricas = strpos($nav, '/app/metricas');
$pipeline = strpos($nav, '/app/kanban');
$atend = strpos($nav, 'ATENDIMENTO');
expect($metricas !== false && $pipeline !== false && $metricas < $pipeline, 'Pipeline fica abaixo de Métricas');
expect($pipeline !== false && $atend !== false && $pipeline < $atend, 'Pipeline fica acima de Atendimento');

expect(str_contains($kanban, 'APPT_STATUS') && str_contains($kanban, 'client_name'), 'Pipeline usa status e nomes de agendamento');
expect(!str_contains($kanban, 'Pipeline de solicitações'), 'título não é só de solicitações');

expect(str_contains($index, 'FROM appointments a') && str_contains($index, "if (\$path === '/app/kanban')"), 'lista do Pipeline lê appointments');
expect(str_contains($index, 'UPDATE appointments SET status=?') && str_contains($index, "post('confirm'") && str_contains($index, "if (\$path === '/app/kanban')"), 'Pipeline só atualiza status após confirmar');
expect(!str_contains($kanban, 'kanban-arrow') && !str_contains($kanban, '›') && !str_contains($kanban, '‹'), 'Pipeline não usa setas para mudar status');
expect(str_contains($kanban, 'js-kanban-status') && str_contains($kanban, 'kanban-yes') && str_contains($kanban, '>Sim<') && str_contains($kanban, '>Não<'), 'Pipeline pede Sim ou Não ao mudar status');
expect(str_contains($kanban, 'view=card') && str_contains($kanban, 'view=compact') && str_contains($kanban, 'kcard-full') && str_contains($kanban, 'kcard-reserva'), 'Pipeline tem vista de card e vista só com nº da reserva');
expect(str_contains($index, 'pipeline_view_save') && str_contains($index, 'pipeline_view_of'), 'última vista do Pipeline é gravada e reaberta');
expect(pipeline_view_normalize('compact') === 'compact' && pipeline_view_normalize('foo') === 'card', 'vista inválida cai em cards');
$_COOKIE = [];
expect(pipeline_view_of(['id'=>'u-pipe']) === 'card', 'sem cookie a vista padrão é card');
$ck = pipeline_view_cookie_name('u-pipe');
$_COOKIE[$ck] = 'compact';
expect(pipeline_view_of(['id'=>'u-pipe']) === 'compact', 'cookie compacto volta ao reabrir o menu');
$_COOKIE[$ck] = 'card';
expect(pipeline_view_of(['id'=>'u-pipe']) === 'card', 'cookie de cards permanece');
expect(str_contains($index, "if (\$path === '/app/solicitacoes/status')"), 'solicitações continuam com status próprio');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Pipeline mostra todos os agendamentos e fica abaixo de Métricas.\n";
