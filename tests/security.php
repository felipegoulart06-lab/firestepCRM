<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/security.php';

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

expect(safe_internal_path('/app/clientes?id=abc') === '/app/clientes?id=abc', 'relative redirect allowed');
expect(safe_internal_path('https://evil.test') === '/', 'absolute URL rejected');
expect(safe_internal_path('//evil.test') === '/', 'protocol-relative rejected');
expect(safe_internal_path("/app\nLocation: https://evil") === '/', 'header injection rejected');
expect(webhook_url_allowed('https://hooks.example.com/x') === true, 'https webhook allowed');
expect(webhook_url_allowed('http://hooks.example.com/x') === false, 'http webhook blocked');
expect(webhook_url_allowed('https://127.0.0.1/x') === false, 'loopback blocked');
expect(webhook_url_allowed('https://169.254.169.254/latest') === false, 'link-local blocked');
expect(webhook_url_allowed('https://10.0.0.8/hook') === false, 'private ipv4 blocked');

$_POST = ['tenant_id' => 'other-company', 'role' => 'MASTER', 'name' => 'ok'];
$_GET = ['company_id' => 'x'];
harden_request();
expect(!isset($_POST['tenant_id']) && !isset($_POST['role']) && ($_POST['name'] ?? '') === 'ok', 'privilege fields stripped from POST');
expect(!isset($_GET['company_id']), 'company_id stripped from GET');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) falharam.\n");
    exit(1);
}
echo "Todos os testes de isolamento de entrada passaram.\n";
