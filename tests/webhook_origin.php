<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

$fail = 0;
function expect(bool $ok, string $msg): void
{
    global $fail;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL $msg\n";
}

expect(normalize_site_host('https://www.MeuSite.com.br/form') === 'meusite.com.br', 'normaliza host com www e path');
expect(normalize_site_host('firestep.cloud') === 'firestep.cloud', 'host simples');
expect(normalize_site_host('*') === '', 'asterisco recusado');

$tenant = ['analytics_config' => json_encode(['site_domain' => 'firestep.cloud'])];
expect(tenant_webhook_hosts($tenant) === ['firestep.cloud'], 'lista o domínio salvo');
expect(webhook_origin_matches($tenant, 'https://firestep.cloud') === true, 'Origin do site passa');
expect(webhook_origin_matches($tenant, 'https://www.firestep.cloud') === true, 'www equivale');
expect(webhook_origin_matches($tenant, 'https://evil.test') === false, 'outro domínio bloqueado');
expect(webhook_origin_matches(['analytics_config' => '{}'], 'https://firestep.cloud') === false, 'sem domínio cadastrado bloqueia');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) de origem do webhook falharam.\n");
    exit(1);
}
echo "Origem do webhook aprovada.\n";
