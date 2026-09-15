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

expect(is_user_admin(['role' => 'user_admin']), 'user_admin é Master');
expect(is_user_admin(['role' => 'MASTER']), 'MASTER legado é Master');
expect(is_user_crm(['role' => 'user_crm']), 'user_crm é admin da empresa');
expect(is_user_crm(['role' => 'TENANT_ADMIN']), 'TENANT_ADMIN legado é user_crm');
expect(is_user_agent(['role' => 'user_agent']), 'user_agent é funcionário');
expect(!is_user_crm(['role' => 'user_agent']), 'agente não é user_crm');
expect(!is_user_admin(['role' => 'user_crm']), 'user_crm não é Master');
expect(agent_route_forbidden('/app/financeiro/lancamentos'), 'agente bloqueia financeiro');
expect(agent_route_forbidden('/app/agentes'), 'agente não gerencia agentes');
expect(!agent_route_forbidden('/app/agenda'), 'agente acessa agenda');
expect(!agent_route_forbidden('/app/clientes'), 'agente acessa clientes');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) de papéis falharam.\n");
    exit(1);
}
echo "Papéis user_admin / user_crm / user_agent aprovados.\n";
