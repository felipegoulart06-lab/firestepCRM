<?php
declare(strict_types=1);

function communicate_kinds(): array
{
    return [
        'appointment' => '/app/agendamentos',
        'request' => '/app/solicitacoes',
        'client' => '/app/clientes',
        'agent' => '/app/agentes',
        'supplier' => '/app/fornecedores',
    ];
}

function communicate_phone(array $row): string
{
    $whatsapp = trim((string)($row['whatsapp'] ?? ''));
    return $whatsapp !== '' ? $whatsapp : trim((string)($row['phone'] ?? ''));
}

function communicate_load(string $tenantId, string $kind, string $id): ?array
{
    return match ($kind) {
        'appointment' => one(
            "SELECT a.*, c.name, c.phone, c.whatsapp, c.email, s.name service_name, u.name agent_name
             FROM appointments a
             JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
             LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
             LEFT JOIN users u ON u.id=a.commission_agent_id AND u.tenant_id=a.tenant_id
             WHERE a.id=? AND a.tenant_id=?",
            [$id, $tenantId]
        ),
        'request' => one(
            "SELECT r.*, s.name service_name FROM requests r
             LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id
             WHERE r.id=? AND r.tenant_id=?",
            [$id, $tenantId]
        ),
        'client' => one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$id, $tenantId]),
        'agent' => one("SELECT * FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", [$id, $tenantId]),
        'supplier' => one('SELECT * FROM suppliers WHERE id=? AND tenant_id=?', [$id, $tenantId]),
        default => null,
    };
}

function communicate_variables(string $kind, array $row, array $tenant): array
{
    $vars = [
        'nome' => ['Nome', (string)($row['name'] ?? '')],
        'telefone' => ['Telefone', phone_fmt(communicate_phone($row))],
        'email' => ['E-mail', (string)($row['email'] ?? '')],
        'empresa' => ['Empresa', (string)($tenant['display_name'] ?: $tenant['business_name'] ?: '')],
    ];
    if ($kind === 'appointment') {
        $vars += [
            'reserva' => ['Nº da reserva', appointment_reserva_label($row)],
            'servico' => ['Serviço', (string)($row['service_name'] ?? '')],
            'data' => ['Data', !empty($row['starts_at']) ? date('d/m/Y', strtotime((string)$row['starts_at'])) : ''],
            'horario' => ['Horário', !empty($row['starts_at']) ? date('H:i', strtotime((string)$row['starts_at'])) : ''],
            'agente' => ['Agente', (string)($row['agent_name'] ?? '')],
            'status' => ['Status', (string)(APPT_STATUS[$row['status'] ?? ''][0] ?? $row['status'] ?? '')],
        ];
    } elseif ($kind === 'request') {
        $vars += [
            'servico' => ['Serviço', (string)($row['service_name'] ?? '')],
            'data_desejada' => ['Data desejada', !empty($row['desired_date']) ? date('d/m/Y', strtotime((string)$row['desired_date'])) : ''],
            'horario_desejado' => ['Horário desejado', substr((string)($row['desired_time'] ?? ''), 0, 5)],
            'status' => ['Status', (string)($row['status'] ?? '')],
            'origem' => ['Origem', (string)($row['source'] ?? '')],
        ];
    } elseif ($kind === 'client') {
        $vars += [
            'documento' => ['CPF/CNPJ', (string)($row['cpf'] ?? '')],
            'origem' => ['Origem', (string)($row['source'] ?? '')],
            'status' => ['Status', (string)($row['status'] ?? '')],
        ];
    } elseif ($kind === 'agent') {
        $vars += [
            'documento' => ['CPF/CNPJ', (string)($row['cpf'] ?? '')],
            'usuario' => ['Usuário', (string)($row['username'] ?? '')],
        ];
    } elseif ($kind === 'supplier') {
        $vars += [
            'documento' => ['CPF/CNPJ', (string)($row['cnpj'] ?? '')],
            'contato' => ['Contato', (string)($row['contact_name'] ?? '')],
            'produto' => ['Produto/serviço', (string)($row['product_type'] ?? '')],
            'cidade' => ['Cidade', trim((string)($row['city'] ?? '').' '.(string)($row['state'] ?? ''))],
        ];
    }
    return array_filter($vars, static fn(array $v): bool => trim((string)$v[1]) !== '');
}

function communicate_message(string $intro, array $selected, array $vars, string $outro): string
{
    $parts = [];
    $intro = trim($intro);
    if ($intro !== '') {
        $parts[] = $intro;
    }
    $lines = [];
    foreach ($selected as $key) {
        if (!isset($vars[$key])) {
            continue;
        }
        [$label, $value] = $vars[$key];
        $lines[] = '*'.$label.':* '.$value;
    }
    if ($lines) {
        $parts[] = implode("\n", $lines);
    }
    $outro = trim($outro);
    if ($outro !== '') {
        $parts[] = $outro;
    }
    return trim(implode("\n\n", $parts));
}

function communicate_payload(string $kind, array $row, array $tenant): array
{
    $vars = communicate_variables($kind, $row, $tenant);
    return [
        'kind' => $kind,
        'id' => (string)$row['id'],
        'name' => (string)($row['name'] ?? 'destinatário'),
        'phone' => communicate_phone($row),
        'variables' => $vars,
        'selected' => array_keys($vars),
        'intro' => 'Olá '.(string)($row['name'] ?? '').', tudo bem?',
        'outro' => '',
    ];
}

function communicate_items(string $kind, array $rows, array $tenant): array
{
    $items = [];
    foreach ($rows as $source) {
        $id = (string)($source['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $row = $source;
        if ($kind === 'appointment') {
            $row['name'] = $row['name'] ?? $row['client_name'] ?? '';
            $row['phone'] = $row['phone'] ?? $row['client_phone'] ?? '';
            $row['whatsapp'] = $row['whatsapp'] ?? $row['client_whatsapp'] ?? '';
        }
        $items[$kind.':'.$id] = communicate_payload($kind, $row, $tenant);
    }
    return $items;
}

function communicate_back(string $kind, ?string $requested = null): string
{
    $fallback = communicate_kinds()[$kind] ?? '/app';
    $requested = safe_internal_path((string)$requested);
    return str_starts_with($requested, $fallback) ? $requested : $fallback;
}

function communicate_send(array $tenant, string $kind, string $id, string $intro, array $selected, string $outro): array
{
    $row = communicate_load((string)$tenant['id'], $kind, $id);
    if (!$row) {
        return ['ok' => false, 'message' => 'Registro não encontrado.'];
    }
    $number = uazapi_wa_number(communicate_phone($row));
    if ($number === '') {
        return ['ok' => false, 'message' => 'O destinatário não possui WhatsApp ou telefone válido.'];
    }
    $cfg = uazapi_config($tenant);
    if (!uazapi_ready($cfg)) {
        return ['ok' => false, 'message' => 'Configure a UAZAPI em Configurações → Integrações.'];
    }
    $message = communicate_message($intro, $selected, communicate_variables($kind, $row, $tenant), $outro);
    if ($message === '') {
        return ['ok' => false, 'message' => 'Escreva uma mensagem ou selecione pelo menos uma variável.'];
    }
    $sent = uazapi_send_text($cfg, $number, $message);
    return !empty($sent['ok'])
        ? ['ok' => true, 'message' => 'Mensagem enviada com sucesso para o WhatsApp de '.$row['name'].'.']
        : ['ok' => false, 'message' => (string)($sent['message'] ?? 'Não foi possível enviar a mensagem.')];
}
