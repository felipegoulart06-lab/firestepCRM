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

$css = file_get_contents($root.'/public/assets/app.css');
$js = file_get_contents($root.'/public/assets/assistant.js');
$flow = file_get_contents($root.'/public/assets/assistant-flow.js');
$layoutEnd = file_get_contents($root.'/views/layout_app_end.php');
$index = file_get_contents($root.'/public/index.php');
$masterNav = file_get_contents($root.'/views/layout_master_start.php');
$appJs = file_get_contents($root.'/public/assets/app.js');

expect(str_contains($css, '.fs-assist{') && str_contains($css, 'pointer-events:none'), 'widget não cobre a tela inteira');
expect(str_contains($css, 'bottom:max(28px') && str_contains($js, 'priscila.jpg') && str_contains($js, 'Priscila'), 'ícone da Priscila um pouco mais baixo');
expect(!str_contains($js, 'class="overlay"') && !str_contains($css, '.fs-assist.overlay'), 'chat não usa overlay que trava o painel');
expect(str_contains($flow, '"📅 Agenda"') && str_contains($flow, '"❓ Minha dúvida não está aqui"') && str_contains($flow, 'duvida'), 'fluxo guiado tem menu e dúvida livre');
expect(str_contains($flow, 'Como usar a Agenda?') && str_contains($flow, 'Para que servem os Webhooks?'), 'perguntas das áreas do painel');
expect(str_contains($layoutEnd, 'assistant-flow.js') && str_contains($layoutEnd, 'assistant.js'), 'scripts do assistente no layout do CRM');
expect(str_contains($index, '/app/assistente/duvida') && str_contains($index, '/master/assistente'), 'rotas do usuário e do Admin Master');
expect(str_contains($masterNav, '/master/assistente'), 'menu do Admin Master abre o inbox');
expect(str_contains($appJs, 'fsAssistMount'), 'soft-nav não descarta o widget');
expect(is_file($root.'/app/assistant.php') && is_file($root.'/views/master/assistente.php'), 'módulo e tela do Admin Master');
expect(is_file($root.'/public/assets/priscila.jpg'), 'foto da Priscila nos assets');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) do assistente falharam.\n");
    exit(1);
}
echo "Assistente ok.\n";
