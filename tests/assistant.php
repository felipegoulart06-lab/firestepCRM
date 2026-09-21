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
$layoutStart = file_get_contents($root.'/views/layout_app_start.php');
$layoutEnd = file_get_contents($root.'/views/layout_app_end.php');
$index = file_get_contents($root.'/public/index.php');
$masterNav = file_get_contents($root.'/views/layout_master_start.php');
$appJs = file_get_contents($root.'/public/assets/app.js');
$helpers = file_get_contents($root.'/app/helpers.php');

expect(str_contains($css, '.fs-assist{') && str_contains($css, 'pointer-events:none'), 'widget não cobre a tela inteira');
expect(str_contains($css, 'bottom:max(28px') && str_contains($js, 'priscila.jpg') && str_contains($js, 'Priscila'), 'ícone da Priscila um pouco mais baixo');
expect(str_contains($css, 'font-family:Poppins') && str_contains($css, 'font-size:15px'), 'fonte Poppins um pouco maior');
expect(!str_contains($css, '#075e54') && !str_contains($css, '#d9fdd3') && !str_contains($css, '#ece5dd'), 'chat sem cores de WhatsApp');
expect(str_contains($css, '.fs-assist.is-open .fs-assist-fab{display:none'), 'ícone some quando o chat abre');
expect(str_contains($js, 'setOpen(true)') && str_contains($js, 'CHOICE_MAX = 5'), 'abre só pelo ícone e limita 5 botões');
expect(!str_contains($js, 'class="overlay"') && !str_contains($css, '.fs-assist.overlay'), 'chat não usa overlay que trava o painel');
expect(str_contains($flow, 'Minha dúvida não está aqui') && str_contains($flow, 'duvida'), 'fluxo tem dúvida livre');
expect(!str_contains($flow, '📅') && !str_contains($flow, '👋'), 'menu sem emojis');
expect(str_contains($flow, 'Como navegar no calendário?') && str_contains($flow, 'Como autorizar o site?'), 'perguntas das áreas do painel');
expect(str_contains($flow, 'AUTO BACKUP') && str_contains($flow, 'UAZAPI') && str_contains($flow, 'Contas a receber'), 'cobertura de backup, WhatsApp e financeiro');
expect(str_contains($layoutStart, 'fonts.googleapis.com') && str_contains($helpers, 'fonts.gstatic.com'), 'Poppins liberada no layout e na CSP');
expect(str_contains($helpers, 'https://api.mapbox.com') && str_contains($helpers, "style-src 'self' 'unsafe-inline' https://api.mapbox.com"), 'CSP ainda libera Mapbox');
expect(str_contains($layoutEnd, 'assistant-flow.js') && str_contains($layoutEnd, 'assistant.js'), 'scripts do assistente no layout do CRM');
expect(str_contains($index, '/app/assistente/duvida') && str_contains($index, '/master/assistente'), 'rotas do usuário e do Admin Master');
expect(str_contains($masterNav, '/master/assistente'), 'menu do Admin Master abre o inbox');
expect(str_contains($appJs, 'fsAssistMount'), 'soft-nav não descarta o widget');
expect(is_file($root.'/app/assistant.php') && is_file($root.'/views/master/assistente.php'), 'módulo e tela do Admin Master');
expect(is_file($root.'/public/assets/priscila.jpg'), 'foto da Priscila nos assets');

preg_match_all('/choices:\s*\[(.*?)\]/s', $flow, $blocks);
$maxChoices = 0;
foreach ($blocks[1] as $block) {
    $n = preg_match_all('/\{\s*label:/', $block);
    $maxChoices = max($maxChoices, $n);
}
expect($maxChoices > 0 && $maxChoices <= 5, 'nenhum grupo do fluxo passa de 5 botões ('.$maxChoices.')');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) do assistente falharam.\n");
    exit(1);
}
echo "Assistente ok.\n";
