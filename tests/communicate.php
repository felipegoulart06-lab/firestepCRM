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
expect(str_starts_with((string)$payload['intro'], 'Olá Maria Silva'), 'sem modelo salvo usa saudação padrão');

$tenant['communicate_templates'] = [
    'appointment' => [
        'intro' => "Olá {Nome}, tudo bem?\nRecebemos a sua solicitação de Agendamento!",
        'outro' => 'Em breve o motorista entrará em contato!',
        'selected' => ['nome', 'servico'],
    ],
    'request' => [
        'intro' => 'Pedido recebido',
        'outro' => 'Obrigado',
        'selected' => ['nome'],
    ],
];
$savedAppt = communicate_items('appointment', $appointment, $tenant)['appointment:ap-1'] ?? [];
expect(($savedAppt['intro'] ?? '') === "Olá {Nome}, tudo bem?\nRecebemos a sua solicitação de Agendamento!", 'agendamentos reabre o último intro enviado');
expect(($savedAppt['outro'] ?? '') === 'Em breve o motorista entrará em contato!', 'agendamentos reabre o último outro enviado');
expect(($savedAppt['selected'] ?? []) === ['nome', 'servico'], 'agendamentos reabre as variáveis do último envio');

$requestPayload = communicate_payload('request', [
    'id' => 'rq-1',
    'name' => 'Carlos',
    'phone' => '11988887777',
    'email' => 'c@c.com',
    'status' => 'NEW',
    'source' => 'Site',
], $tenant);
expect(($requestPayload['intro'] ?? '') === 'Pedido recebido', 'solicitações tem modelo próprio');
expect(($requestPayload['intro'] ?? '') !== ($savedAppt['intro'] ?? ''), 'modelos não se misturam entre menus');

$css = file_get_contents(dirname(__DIR__).'/public/assets/app.css');
expect(str_contains($css, '.communicate-chip') && str_contains($css, 'border-radius:0'), 'chips laranja ficam com canto reto');
expect(!str_contains($css, '.communicate-chip{appearance:none;border:1px solid #f59e0b;background:#fbbf24;color:#3b2800;border-radius:999px'), 'chips não usam canto arredondado');

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
expect(str_contains($index, "/app/comunicar/enviar") && str_contains($index, 'communicate_send('), 'rota envia pelo WhatsApp');
expect(str_contains($index, 'communicate_template_save') || str_contains(file_get_contents(dirname(__DIR__).'/app/communicate.php'), 'communicate_template_save'), 'enviar grava o modelo do menu');
expect((bool)preg_match("/view\\('app\\/fornecedores',[\\s\\S]{0,500}'tenant'/", $index), 'Fornecedores recebe tenant e não fica em branco');
$ui = file_get_contents(dirname(__DIR__).'/views/app/config.php').file_get_contents(dirname(__DIR__).'/views/app/communicate_modal.php');
expect(!str_contains($ui, 'UAZAPI') && !str_contains($ui, 'uazapi.com'), 'configuração e Comunicar não mostram o provedor');
expect(str_contains($ui, 'tab=comunicar') && str_contains($ui, 'Automatizar Comunicar'), 'Configurações tem aba de automação do Comunicar');
expect(str_contains($index, '/app/configuracoes/comunicar') && str_contains($index, '/cron/comunicar-auto'), 'salvar automação e cron de lembrete');
$vercel = file_get_contents(dirname(__DIR__).'/vercel.json');
expect(str_contains($vercel, '/cron/comunicar-auto') && str_contains($vercel, '0 11 * * *') && !str_contains($vercel, '15 * * * *'), 'lembrete no Hobby é cron diário');

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firestep-com-'.bin2hex(random_bytes(4)).'.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);
db()->exec(file_get_contents(dirname(__DIR__).'/app/schema.sql'));

$catalog = communicate_automation_catalog();
expect(count($catalog) === 5 && isset($catalog['appointment_reminder'], $catalog['request_created']), 'catálogo cobre agenda, status, lembrete e solicitação');
$defaults = communicate_automations_of(['communicate_automations' => '{}']);
expect(empty($defaults['enabled']) && empty($defaults['rules']['appointment_created']['enabled']), 'automação nasce desligada');
$on = communicate_automations_of(['communicate_automations' => ['enabled' => true, 'rules' => ['appointment_created' => ['enabled' => true, 'hours_before' => 3]]]]);
expect(!empty($on['enabled']) && !empty($on['rules']['appointment_created']['enabled']), 'liga regra de novo agendamento');
expect((int)$on['rules']['appointment_reminder']['hours_before'] === 24, 'lembrete padrão 24 horas');
$skip = communicate_automation_fire(['id' => 'ten-off', 'communicate_automations' => '{}', 'uazapi_config' => '{}'], 'appointment.created', 'appointment', 'ap-x');
expect(($skip['skip'] ?? '') === 'off', 'não dispara com automação desligada');
@unlink($tmp);

if ($fail) {
    fwrite(STDERR, "{$fail} teste(s) de comunicação falharam.\n");
    exit(1);
}
echo "Comunicação WhatsApp disponível nas cinco tabelas.\n";
