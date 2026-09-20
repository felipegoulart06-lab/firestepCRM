<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/public/assets/app.js');
$css = file_get_contents($root . '/public/assets/app.css');
$view = file_get_contents($root . '/views/app/solicitacoes.php');
$fail = 0;

if (!str_contains($js, 'function hoistModal') || !str_contains($js, 'appendChild(el)')) {
    echo "FAIL overlay não é movido para o body\n";
    $fail++;
}
if (!str_contains($js, 'function visibleLayer') || !str_contains($js, 'el.hidden')) {
    echo "FAIL lockBehindModal trava overlay oculto\n";
    $fail++;
}
if (preg_match("/lockBehindModal\\(\\)\\{[\\s\\S]*?querySelector\\('\\.overlay'\\)/", $js)) {
    echo "FAIL lockBehindModal ainda pega .overlay escondido\n";
    $fail++;
}
if (!str_contains($css, 'scrollbar-gutter:stable')) {
    echo "FAIL CSS ainda permite piscar no reload\n";
    $fail++;
}
if (str_contains($css, '@view-transition') || str_contains($css, '::view-transition')) {
    echo "FAIL View Transition nativa pode abortar a navegação\n";
    $fail++;
}
if (!str_contains($js, '[data-close-modal]') || !str_contains($js, 'function closeModal')) {
    echo "FAIL Fechar do modal não chama closeModal\n";
    $fail++;
}
if (!preg_match('/data-close-modal[\s\S]{0,40}Fechar/', $view) && !preg_match('/Fechar[\s\S]{0,40}data-close-modal/', $view)) {
    echo "FAIL detalhes da solicitação sem data-close-modal\n";
    $fail++;
}
if (!str_contains($css, 'body.is-modal-open .overlay *') || !str_contains($css, 'pointer-events:auto')) {
    echo "FAIL CSS do overlay continua bloqueando clique\n";
    $fail++;
}
if (!str_contains($js, "t.matches('.overlay, .fx-overlay, .token-modal')")) {
    echo "FAIL clique no fundo do overlay não é ignorado\n";
    $fail++;
}
if (str_contains($js, "t.classList.contains('token-modal')) closeModal")
    || str_contains($js, 'e.target === el) close')
    || str_contains($js, 'e.target === overlay) close')) {
    echo "FAIL clique fora do formulário ainda fecha o overlay\n";
    $fail++;
}
$ficha = file_get_contents($root . '/views/master/ficha.php');
if (str_contains($ficha, 'e.target === modal')) {
    echo "FAIL token master fecha ao clicar fora\n";
    $fail++;
}

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Fechar do modal de solicitação não fica preso no wrap.\n";
