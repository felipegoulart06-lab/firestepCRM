<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    $root . '/public/index.php',
    $root . '/app/core.php',
    $root . '/app/helpers.php',
    $root . '/app/sheets.php',
    $root . '/app/finance.php',
];
$fail = 0;
$patterns = [
    '/JOIN clients c ON c\.id=a\.client_id(?! AND c\.tenant_id)/' => 'JOIN clients sem tenant_id',
    '/LEFT JOIN services s ON s\.id=a\.service_id(?! AND s\.tenant_id)/' => 'JOIN services (appointment) sem tenant_id',
    '/LEFT JOIN services s ON s\.id=r\.service_id(?! AND s\.tenant_id)/' => 'JOIN services (request) sem tenant_id',
];
foreach ($files as $file) {
    $src = file_get_contents($file);
    if ($src === false) {
        echo "FAIL não leu $file\n";
        $fail++;
        continue;
    }
    foreach ($patterns as $re => $label) {
        if (preg_match_all($re, $src, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $hit) {
                echo "FAIL $label em $file: {$hit[0]}\n";
                $fail++;
            }
        }
    }
}
if ($fail) {
    fwrite(STDERR, "$fail ocorrência(s) insegura(s).\n");
    exit(1);
}
echo "JOINs de clientes/serviços exigem tenant_id.\n";
