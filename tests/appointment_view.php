<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;

$agenda = file_get_contents($root . '/views/app/agenda.php');
$modal = file_get_contents($root . '/views/app/modal_appointment.php');
$index = file_get_contents($root . '/public/index.php');
$agendamentos = file_get_contents($root . '/views/app/agendamentos.php');

if (str_contains($agenda, "/app/agenda?edit=")) {
    echo "FAIL Agenda ainda abre detalhes com ?edit=\n";
    $fail++;
}
if (!str_contains($agenda, '&ver=')) {
    echo "FAIL Agenda não abre detalhes com ?ver=\n";
    $fail++;
}
if (!str_contains($agenda, '$viewOnly = (bool)$edit')) {
    echo "FAIL Agenda não força visualização dos detalhes\n";
    $fail++;
}
if (!str_contains($agenda, '$allowEditFromDetails = false')) {
    echo "FAIL Agenda ainda permite ir para Editar no overlay\n";
    $fail++;
}
if (!str_contains($modal, '$allowEditFromDetails')) {
    echo "FAIL Modal não respeita permissão de Editar no overlay\n";
    $fail++;
}
if (!str_contains($modal, '$modalInline')) {
    echo "FAIL Modal não pode abrir na lista sem overlay\n";
    $fail++;
}
if (!str_contains($modal, 'name="allow_edit"')) {
    echo "FAIL Formulário de edição sem allow_edit\n";
    $fail++;
}
if (!str_contains($index, "post('allow_edit') !== '1'")) {
    echo "FAIL Salvamento não exige allow_edit\n";
    $fail++;
}
if (!str_contains($index, 'appointment_commission_ensure_schema')) {
    echo "FAIL painel não garante colunas de comissão antes de abrir detalhes\n";
    $fail++;
}
if (!str_contains($index, "/app/agendamentos?ver=")) {
    echo "FAIL Criar/converter não abre detalhes em modo ver\n";
    $fail++;
}
if (!str_contains($agendamentos, '$allowEditFromDetails = $viewOnly')) {
    echo "FAIL Lista Agendamentos não oferece Editar a partir de Ver\n";
    $fail++;
}
if (!str_contains($index, "'tenant'=>\$tenant") || !str_contains($index, "view('app/agendamentos'")) {
    echo "FAIL Lista Agendamentos não passa o tenant ao abrir detalhes\n";
    $fail++;
}
if (!str_contains($agendamentos, '$modalInline = true') || !str_contains($agendamentos, '?ver=') || !str_contains($agendamentos, '?edit=')) {
    echo "FAIL Lista Agendamentos com links de Ver/Editar quebrados\n";
    $fail++;
}
if (str_contains($agendamentos, 'reserva.pdf')) {
    echo "FAIL Lista Agendamentos ainda mostra download de PDF na tabela\n";
    $fail++;
}
if (!str_contains($modal, 'reserva.pdf') || !str_contains($modal, '$viewOnly')) {
    echo "FAIL PDF da reserva não fica nos detalhes\n";
    $fail++;
}
if (!str_contains($agenda, 'data-density') || !str_contains($agenda, 'cal-busy-') || !str_contains($agenda, 'agenda_busy_level')) {
    echo "FAIL Agenda não compacta reservas quando o dia enche\n";
    $fail++;
}
$css = file_get_contents($root . '/public/assets/app.css');
if (!str_contains($css, '.cal-busy-4 .ev') || !str_contains($css, 'data-density="4"')) {
    echo "FAIL CSS da agenda sem tamanhos menores por densidade\n";
    $fail++;
}

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Detalhes da Agenda são só leitura; edição só em Agendamentos > Editar.\n";
