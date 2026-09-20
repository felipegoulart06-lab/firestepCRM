<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/finance.php';

$fail = 0;
function expect($ok, string $message): void
{
    global $fail;
    if ($ok) {
        echo "OK  {$message}\n";
        return;
    }
    $fail++;
    echo "FAIL {$message}\n";
}

$tenant = ['id' => 'ten-c', 'display_name' => 'Empresa Exemplo', 'business_name' => 'Empresa Exemplo'];
$appointment = [[
    'id' => 'ap-1',
    'client_name' => 'Maria Silva',
    'client_phone' => '11999999999',
    'client_whatsapp' => '',
    'reserva_n' => 42,
    'service_name' => 'Transfer',
    'starts_at' => '2026-09-20 14:30:00',
    'status' => 'CONFIRMED',
    'agent_name' => 'João',
]];
$payloads = communicate_items('appointment', $appointment, $tenant);
$payload = $payloads['appointment:ap-1'] ?? [];
expect(($payload['name'] ?? '') === 'Maria Silva', 'agendamento usa o cliente como destinatário');
expect(($payload['phone'] ?? '') === '11999999999', 'agendamento usa o telefone do cliente');
expect(isset($payload['variables']['reserva'], $payload['variables']['servico']), 'agendamento oferece reserva e serviço');

$message = communicate_message('Olá!', ['nome', 'empresa'], [
    'nome' => ['Nome', 'Maria'],
    'empresa' => ['Empresa', 'Empresa Exemplo'],
], 'Até breve.');
expect(str_contains($message, '*Nome:* Maria') && str_contains($message, '*Empresa:* Empresa Exemplo'), 'mensagem inclui variáveis selecionadas');
expect(!str_contains(communicate_message('', ['inexistente'], [], ''), 'inexistente'), 'variáveis desconhecidas são ignoradas');

expect(communicate_back('client', '/app/clientes?s=ACTIVE') === '/app/clientes?s=ACTIVE', 'retorno mantém filtro do menu correto');
expect(communicate_back('client', '/app/fornecedores') === '/app/clientes', 'retorno não pode trocar de menu');
expect(communicate_back('invalid', 'https://example.com') === '/app', 'retorno externo é bloqueado');

$views = [
    'agendamentos.php' => 'appointment:',
    'solicitacoes.php' => 'request:',
    'clientes.php' => 'client:',
    'agentes.php' => 'agent:',
    'fornecedores.php' => 'supplier:',
];
foreach ($views as $file => $key) {
    $src = file_get_contents(dirname(__DIR__).'/views/app/'.$file);
    expect(str_contains($src, 'js-communicate') && str_contains($src, $key), $file.' possui ação Comunicar');
    expect(str_contains($src, 'row-actions'), $file.' alinha Comunicar na mesma faixa de ações');
    expect(str_contains($src, 'communicate_modal.php'), $file.' inclui o configurador da mensagem');
}

$index = file_get_contents(dirname(__DIR__).'/public/index.php');
expect(str_contains($index, "/app/comunicar/enviar") && str_contains($index, 'communicate_send('), 'rota envia pela UAZAPI');
expect(str_contains($index, "flash((string)\$result['message']"), 'resultado do envio é avisado ao usuário');

if ($fail) {
    fwrite(STDERR, "{$fail} teste(s) de comunicação falharam.\n");
    exit(1);
}
echo "Comunicação UAZAPI disponível nas cinco tabelas.\n";
