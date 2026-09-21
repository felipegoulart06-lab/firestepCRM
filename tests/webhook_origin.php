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
expect(webhook_origin_matches($tenant, 'https://lp.firestep.cloud') === true, 'subdomínio do domínio salvo passa');
expect(webhook_origin_matches($tenant, 'https://evil.test') === false, 'outro domínio bloqueado');
expect(webhook_origin_matches($tenant, 'https://firestep.cloud.evil.test') === false, 'sufixo falso bloqueado');
expect(webhook_origin_matches(['analytics_config' => '{}'], 'https://firestep.cloud') === false, 'sem domínio cadastrado bloqueia');

$multi = ['analytics_config' => json_encode(['site_domain' => 'templates.firestep.cloud, outro.com.br'])];
expect(tenant_webhook_hosts($multi) === ['templates.firestep.cloud', 'outro.com.br'], 'aceita vários domínios');
expect(webhook_origin_matches($multi, 'https://templates.firestep.cloud') === true, 'primeiro domínio da lista passa');
expect(webhook_origin_matches($multi, 'https://www.outro.com.br') === true, 'segundo domínio da lista passa');
expect(webhook_origin_matches($multi, 'https://evil.test') === false, 'fora da lista continua bloqueado');
expect(webhook_cors_origin_value('https://www.firestep.cloud/form') === 'https://www.firestep.cloud', 'CORS ecoa só o origin');
$cfgUi = file_get_contents(dirname(__DIR__).'/views/app/config.php');
expect(!str_contains($cfgUi, 'Google Tag Manager') && !str_contains($cfgUi, 'gtm_id'), 'GTM não aparece nas Configurações');
$idx = file_get_contents(dirname(__DIR__).'/public/index.php');
expect(!str_contains($idx, '/app/configuracoes/analytics'), 'rota de GTM removida');

$_SERVER['HTTP_ORIGIN'] = '';
$_SERVER['HTTP_REFERER'] = 'https://www.firestep.cloud/contato';
expect(request_webhook_origin() === 'https://www.firestep.cloud/contato', 'Referer entra se Origin vier vazio');
expect(webhook_origin_matches($tenant, request_webhook_origin()) === true, 'Referer do site salvo autoriza');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) de origem do webhook falharam.\n");
    exit(1);
}
echo "Origem do webhook aprovada.\n";
