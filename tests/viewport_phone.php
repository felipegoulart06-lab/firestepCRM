<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;
$helpers = file_get_contents($root . '/app/helpers.php');
if (!str_contains($helpers, 'function head_viewport') || !str_contains($helpers, 'user-scalable=no')) {
    echo "FAIL head_viewport não bloqueia zoom no celular\n";
    $fail++;
}
if (!str_contains($helpers, '/Android/i.test(ua) && /Mobile/i.test(ua)')) {
    echo "FAIL bloqueio não distingue Android telefone de tablet\n";
    $fail++;
}
foreach ([
    'views/layout_app_start.php',
    'views/layout_lock_start.php',
    'views/layout_master_start.php',
    'views/layout_onboard_start.php',
    'views/login.php',
] as $file) {
    $src = file_get_contents($root . '/' . $file);
    if (!str_contains($src, 'head_viewport()')) {
        echo "FAIL $file sem viewport no cabeçalho\n";
        $fail++;
    }
}
if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Viewport do painel trava zoom só em celular.\n";
