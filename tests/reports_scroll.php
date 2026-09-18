<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$js = file_get_contents($root . '/public/assets/app.js');
$css = file_get_contents($root . '/public/assets/app.css');
$fail = 0;

if (!str_contains($js, '.fx-filter-panel') || !str_contains($js, '.fx-overlay')) {
    echo "FAIL JS ainda bloqueia o scroll dos filtros de relatório\n";
    $fail++;
}
if (!str_contains($js, 'dataset.fxKind') || !str_contains($js, 'showKind')) {
    echo "FAIL JS não troca o container de filtro pelo tipo do relatório\n";
    $fail++;
}
if (preg_match('/function blockScrollBehindModal[\s\S]+?closest\(\'\.overlay-panel\'\)/', $js)
    && !str_contains($js, '.fx-filter-panel')) {
    echo "FAIL blockScrollBehindModal só libera .overlay-panel\n";
    $fail++;
}
if (!str_contains($css, '.fx-overlay') || !str_contains($css, 'overflow:auto')) {
    echo "FAIL overlay de relatório sem overflow\n";
    $fail++;
}
if (preg_match('/\.fx-filter-panel\{[^}]*max-height:calc\(100vh/', $css)) {
    echo "FAIL painel de filtro ainda prende a altura e compete com o overlay\n";
    $fail++;
}
if (!str_contains($css, '.fx-filter-grid') || !str_contains($css, 'min(1120px')) {
    echo "FAIL filtros de relatório não usam o painel largo em colunas\n";
    $fail++;
}

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Scroll dos filtros de Relatórios liberado no overlay.\n";
