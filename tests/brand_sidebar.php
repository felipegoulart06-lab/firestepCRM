<?php
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/views/layout_app_start.php');
$fail = 0;
if (!str_contains($src, 'class="brand-name">FirestepCRM')) {
    echo "FAIL plataforma não é FirestepCRM no topo do menu\n";
    $fail++;
}
if (!str_contains($src, "business_name") || !str_contains($src, 'class="brand-sub"')) {
    echo "FAIL segundo linha não usa o nome da empresa\n";
    $fail++;
}
if (!str_contains($src, 'is_user_agent($user)') || !str_contains($src, 'brand-agent')) {
    echo "FAIL agente não aparece abaixo da empresa\n";
    $fail++;
}
if (!str_contains($src, "business_name'] ?: \$tenant['name'] ?: \$tenant['display_name']")) {
    echo "FAIL ainda prioriza display_name (pode repetir FirestepCRM)\n";
    $fail++;
}
$css = file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
if (!str_contains($css, 'align-self:stretch') || !str_contains($css, 'min-height:100vh')) {
    echo "FAIL coluna do menu não acompanha a altura da página\n";
    $fail++;
}
if (str_contains($css, 'position:sticky;top:0;height:100vh')) {
    echo "FAIL menu lateral ainda tem altura finita de 100vh\n";
    $fail++;
}
if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Cabeçalho do menu: plataforma, empresa e agente.\n";
