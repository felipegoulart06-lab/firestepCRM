<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/core.php';

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

$thu = [
    'business_hours' => json_encode([
        0 => ['closed' => true, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
        1 => ['closed' => false, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
        2 => ['closed' => false, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
        3 => ['closed' => false, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
        4 => ['closed' => false, 'start' => '08:00', 'end' => '00:00', 'breaks' => []],
        5 => ['closed' => false, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
        6 => ['closed' => true, 'start' => '08:00', 'end' => '18:00', 'breaks' => []],
    ], JSON_UNESCAPED_UNICODE),
];

expect(outside_hours($thu, '2026-09-17 10:00:00', '2026-09-17 11:00:00') === false, 'quinta 08:00-00:00 aceita 10h');
expect(outside_hours($thu, '2026-09-17 22:00:00', '2026-09-17 23:00:00') === false, 'quinta até meia-noite aceita 22h');

$mon = $thu;
expect(outside_hours($mon, '2026-09-14 10:00:00', '2026-09-14 11:00:00') === false, 'segunda 10h dentro');
expect(outside_hours($mon, '2026-09-14 17:30:00', '2026-09-14 18:30:00') === true, 'término depois do fecha fica fora');
expect(outside_hours($mon, '2026-09-13 10:00:00', '2026-09-13 11:00:00') === true, 'domingo fechado');

$gap = [
    'business_hours' => json_encode([
        4 => ['closed' => false, 'start' => '08:00', 'end' => '18:00', 'breaks' => [['start' => '', 'end' => '']]],
    ], JSON_UNESCAPED_UNICODE),
];
expect(outside_hours($gap, '2026-09-17 10:00:00', '2026-09-17 11:00:00') === false, 'intervalo vazio não marca o dia como fechado');
expect(hours_day_closed(['closed' => 'false']) === false, 'closed textual false não fecha');
expect(hours_day_closed(['closed' => false]) === false, 'closed boolean false não fecha');

$def = ['business_hours' => json_encode(default_hours(), JSON_UNESCAPED_UNICODE)];
expect(outside_hours($def, '2026-09-14 10:00:00', '2026-09-14 11:00:00') === false, 'expediente padrão aceita 10h');
expect(count(agenda_hour_range($thu, [])) === 24, 'linha do tempo do dia tem 24 horas');

$js = file_get_contents(dirname(__DIR__).'/public/assets/app.js');
expect(!str_contains($js, "t.classList.contains('overlay') || t.classList.contains('token-modal')"), 'clique no fundo do overlay não fecha o formulário');
expect(str_contains($js, 'bindHoursGuard') && str_contains($js, 'js-hours-json'), 'formulário lê horários sem depender de atributo HTML');
expect(str_contains($js, "const dur = svcVal && opt ? Number(opt.getAttribute('data-duration') || 0) : 0"), 'aviso não assume 60 min sem serviço');
$modal = file_get_contents(dirname(__DIR__).'/views/app/modal_appointment.php');
expect(str_contains($modal, 'data-hours-hint') && str_contains($modal, 'Fora do horário de funcionamento'), 'frase no formulário');
expect(str_contains($modal, 'data-hours-guard') && str_contains($modal, 'js-hours-json'), 'horários do formulário em JSON isolado');
$agenda = file_get_contents(dirname(__DIR__).'/views/app/agenda.php');
expect(!str_contains($agenda, 'range(7,20)'), 'agenda não corta o dia em 07:00–20:00');
expect(str_contains($agenda, 'agenda_hour_range') && str_contains($agenda, 'cal-full-day'), 'agenda desenha a linha do tempo completa');
$index = file_get_contents(dirname(__DIR__).'/public/index.php');
expect(str_contains($index, "str_contains(\$back, '/agendamentos') ? '/app/agendamentos' : '/app/agenda'"), 'salvar com sucesso fecha o formulário');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) de horário falharam.\n");
    exit(1);
}
echo "Horário de funcionamento e fechamento do formulário aprovados.\n";
