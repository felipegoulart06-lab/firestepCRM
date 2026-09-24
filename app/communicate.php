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

function communicate_templates_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS communicate_templates jsonb NOT NULL DEFAULT '{}'::jsonb");
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('communicate_templates', $cols, true)) {
            db()->exec("ALTER TABLE tenants ADD COLUMN communicate_templates TEXT DEFAULT '{}'");
        }
    } catch (Throwable) {
        $done = false;
    }
}

function communicate_templates_all(array $tenant): array
{
    $raw = $tenant['communicate_templates'] ?? '{}';
    $data = is_array($raw) ? $raw : json_arr($raw);
    return is_array($data) ? $data : [];
}

function communicate_template_of(array $tenant, string $kind): array
{
    $tpl = communicate_templates_all($tenant)[$kind] ?? null;
    if (!is_array($tpl)) {
        return ['intro' => null, 'outro' => null, 'selected' => null, 'attach_pdf' => null, 'send_buttons' => null, 'buttons' => null, 'image_url' => null];
    }
    $selected = $tpl['selected'] ?? null;
    if (is_array($selected)) {
        $selected = array_values(array_filter(array_map('strval', $selected), static fn(string $key): bool => $key !== ''));
    } else {
        $selected = null;
    }
    return [
        'intro' => array_key_exists('intro', $tpl) ? (string)$tpl['intro'] : null,
        'outro' => array_key_exists('outro', $tpl) ? (string)$tpl['outro'] : null,
        'selected' => $selected,
        'attach_pdf' => array_key_exists('attach_pdf', $tpl) ? !empty($tpl['attach_pdf']) : null,
        'send_buttons' => array_key_exists('send_buttons', $tpl) ? !empty($tpl['send_buttons']) : null,
        'buttons' => is_array($tpl['buttons'] ?? null) ? array_values(array_slice($tpl['buttons'], 0, 3)) : null,
        'image_url' => array_key_exists('image_url', $tpl) ? trim((string)$tpl['image_url']) : null,
    ];
}

function communicate_template_save(string $tenantId, array &$tenant, string $kind, string $intro, string $outro, array $selected, array $extra = []): void
{
    if (!isset(communicate_kinds()[$kind])) {
        return;
    }
    communicate_templates_ensure_schema();
    $all = communicate_templates_all($tenant);
    $all[$kind] = [
        'intro' => $intro,
        'outro' => $outro,
        'selected' => array_values(array_filter(array_map('strval', $selected), static fn(string $key): bool => $key !== '')),
        'attach_pdf' => !empty($extra['attach_pdf']),
        'send_buttons' => !empty($extra['send_buttons']),
        'buttons' => is_array($extra['buttons'] ?? null) ? array_values(array_slice($extra['buttons'], 0, 3)) : [],
        'image_url' => trim((string)($extra['image_url'] ?? '')),
    ];
    $encoded = json_encode($all, JSON_UNESCAPED_UNICODE);
    $tenant['communicate_templates'] = $all;
    try {
        if (is_pgsql()) {
            q('UPDATE tenants SET communicate_templates=CAST(? AS jsonb), updated_at=? WHERE id=?', [$encoded, now(), $tenantId]);
        } else {
            q('UPDATE tenants SET communicate_templates=?, updated_at=? WHERE id=?', [$encoded, now(), $tenantId]);
        }
    } catch (Throwable) {
        // coluna ausente em ambiente sem migrate; o payload desta sessão ainda usa $tenant
    }
}

function communicate_payload(string $kind, array $row, array $tenant): array
{
    $vars = communicate_variables($kind, $row, $tenant);
    $payload = [
        'kind' => $kind,
        'id' => (string)$row['id'],
        'name' => (string)($row['name'] ?? 'destinatário'),
        'phone' => communicate_phone($row),
        'variables' => $vars,
        'selected' => array_keys($vars),
        'intro' => 'Olá '.(string)($row['name'] ?? '').', tudo bem?',
        'outro' => '',
        'can_pdf' => $kind === 'appointment',
        'attach_pdf' => $kind === 'appointment',
        'send_buttons' => false,
        'buttons' => [['text' => '', 'type' => 'REPLY', 'id' => ''], ['text' => '', 'type' => 'URL', 'id' => ''], ['text' => '', 'type' => 'CALL', 'id' => '']],
        'image_url' => '',
    ];
    $tpl = communicate_template_of($tenant, $kind);
    if ($tpl['intro'] !== null) {
        $payload['intro'] = $tpl['intro'];
    }
    if ($tpl['outro'] !== null) {
        $payload['outro'] = $tpl['outro'];
    }
    if ($tpl['selected'] !== null) {
        $payload['selected'] = array_values(array_filter($tpl['selected'], static fn(string $key): bool => isset($vars[$key])));
    }
    if ($kind === 'appointment') {
        if ($tpl['attach_pdf'] !== null) {
            $payload['attach_pdf'] = !empty($tpl['attach_pdf']);
        }
        if ($tpl['send_buttons'] !== null) {
            $payload['send_buttons'] = !empty($tpl['send_buttons']);
        }
        if ($tpl['buttons'] !== null && $tpl['buttons'] !== []) {
            $payload['buttons'] = array_pad($tpl['buttons'], 3, ['text' => '', 'type' => 'REPLY', 'id' => '']);
        }
        if ($tpl['image_url'] !== null) {
            $payload['image_url'] = (string)$tpl['image_url'];
        }
    }
    return $payload;
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

function communicate_image_from_post(array $tenant, string $kind): string
{
    if (post('remove_image') === '1') {
        return '';
    }
    $kept = trim((string)post('image_url', ''));
    if (!preg_match('#^https://#i', $kept)) {
        $kept = '';
    }
    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $kept;
    }
    if ((int)($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }
    if ((int)($file['size'] ?? 0) > 1000000) {
        throw new RuntimeException('A imagem deve ter no máximo 1 MB.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $bin = $tmp !== '' ? (string)file_get_contents($tmp) : '';
    $info = $bin !== '' ? @getimagesizefromstring($bin) : false;
    if (!is_array($info) || empty($info['mime'])) {
        throw new RuntimeException('Envie JPEG, PNG ou WEBP.');
    }
    $mime = strtolower((string)$info['mime']);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => '',
    };
    if ($ext === '') {
        throw new RuntimeException('Envie JPEG, PNG ou WEBP.');
    }
    if (!function_exists('r2_ready') || !r2_ready()) {
        throw new RuntimeException('A Cloudflare R2 não está configurada para guardar a imagem.');
    }
    $tid = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string)($tenant['id'] ?? 'tenant')) ?: 'tenant';
    $key = 'comunicar/'.$tid.'/'.$kind.'/'.bin2hex(random_bytes(8)).'.'.$ext;
    $put = r2_put_bytes($key, $bin, $mime);
    if (empty($put['ok']) || ($put['url'] ?? '') === '') {
        throw new RuntimeException('Não foi possível guardar a imagem na Cloudflare.');
    }
    return (string)$put['url'];
}

function communicate_buttons_from_post(): array
{
    $texts = is_array($_POST['btn_text'] ?? null) ? $_POST['btn_text'] : [];
    $types = is_array($_POST['btn_type'] ?? null) ? $_POST['btn_type'] : [];
    $values = is_array($_POST['btn_value'] ?? null) ? $_POST['btn_value'] : [];
    if (!function_exists('finance_charge_normalize_buttons')) {
        return [];
    }
    return finance_charge_normalize_buttons($texts, $types, $values);
}

function communicate_send(array $tenant, string $kind, string $id, string $intro, array $selected, string $outro, array $opts = []): array
{
    $row = communicate_load((string)$tenant['id'], $kind, $id);
    if (!$row) {
        return ['ok' => false, 'message' => 'Registro não encontrado.'];
    }
    $message = communicate_message($intro, $selected, communicate_variables($kind, $row, $tenant), $outro);
    $attachPdf = $kind === 'appointment' && !empty($opts['attach_pdf']);
    $buttonsApi = ($kind === 'appointment' && !empty($opts['send_buttons'])) ? ($opts['buttons_api'] ?? []) : [];
    $imageUrl = $kind === 'appointment' ? trim((string)($opts['image_url'] ?? '')) : '';
    if ($imageUrl !== '' && !preg_match('#^https://#i', $imageUrl)) {
        $imageUrl = '';
    }
    if ($message === '' && !$attachPdf && $imageUrl === '') {
        return ['ok' => false, 'message' => 'Escreva uma mensagem, selecione uma variável, envie o PDF ou anexe uma imagem.'];
    }
    communicate_template_save((string)$tenant['id'], $tenant, $kind, $intro, $outro, $selected, [
        'attach_pdf' => $attachPdf,
        'send_buttons' => $buttonsApi !== [],
        'buttons' => $opts['buttons'] ?? [],
        'image_url' => $imageUrl,
    ]);
    return communicate_dispatch($tenant, $row, $message, [
        'attach_pdf' => $attachPdf,
        'buttons_api' => $buttonsApi,
        'image_url' => $imageUrl,
        'kind' => $kind,
        'id' => $id,
    ]);
}

function communicate_dispatch(array $tenant, array $row, string $message, array $opts = []): array
{
    $number = uazapi_wa_number(communicate_phone($row));
    if ($number === '') {
        return ['ok' => false, 'message' => 'O destinatário não possui WhatsApp ou telefone válido.'];
    }
    $cfg = uazapi_config($tenant);
    if (!uazapi_ready($cfg)) {
        return ['ok' => false, 'message' => 'Configure o WhatsApp em Configurações → Integrações.'];
    }
    $who = (string)($row['name'] ?? 'destinatário');
    $buttonsApi = is_array($opts['buttons_api'] ?? null) ? array_values($opts['buttons_api']) : [];
    $attachPdf = !empty($opts['attach_pdf']);
    $imageUrl = trim((string)($opts['image_url'] ?? ''));
    if ($imageUrl !== '' && !preg_match('#^https://#i', $imageUrl)) {
        $imageUrl = '';
    }
    $okAny = false;
    $lastFail = 'Não foi possível enviar a mensagem.';
    $cardText = $message !== '' ? $message : 'Sua reserva';

    if ($buttonsApi !== []) {
        $btn = uazapi_send_buttons($cfg, $number, $cardText, $buttonsApi, $imageUrl);
        if (!empty($btn['ok'])) {
            $okAny = true;
        } else {
            $lastFail = (string)($btn['message'] ?? $lastFail);
            if ($imageUrl !== '') {
                $img = uazapi_send_image($cfg, $number, $imageUrl, $cardText);
                if (!empty($img['ok'])) {
                    $okAny = true;
                }
            }
            if (!$okAny && $message !== '') {
                $txt = uazapi_send_text($cfg, $number, $message);
                if (!empty($txt['ok'])) {
                    $okAny = true;
                } else {
                    $lastFail = (string)($txt['message'] ?? $lastFail);
                }
            }
        }
    } elseif ($imageUrl !== '') {
        $caption = $message !== '' && !$attachPdf ? $message : ($message !== '' ? $message : '');
        $img = uazapi_send_image($cfg, $number, $imageUrl, $caption);
        if (!empty($img['ok'])) {
            $okAny = true;
        } else {
            $lastFail = (string)($img['message'] ?? $lastFail);
            if ($message !== '' && !$attachPdf) {
                $txt = uazapi_send_text($cfg, $number, $message);
                if (!empty($txt['ok'])) {
                    $okAny = true;
                }
            }
        }
    } elseif ($message !== '' && !$attachPdf) {
        $sent = uazapi_send_text($cfg, $number, $message);
        if (!empty($sent['ok'])) {
            $okAny = true;
        } else {
            $lastFail = (string)($sent['message'] ?? $lastFail);
        }
    }

    if ($attachPdf) {
        require_once __DIR__.'/pdf.php';
        $pack = appointment_pdf_pack($tenant, $row);
        $caption = $message !== '' && $buttonsApi === [] && $imageUrl === '' ? $message : 'Confirmação da reserva em PDF.';
        $doc = uazapi_send_document($cfg, $number, $pack['bytes'], $pack['filename'], $caption);
        if (!empty($doc['ok'])) {
            $okAny = true;
        } else {
            $lastFail = (string)($doc['message'] ?? $lastFail);
            if ($message !== '' && $buttonsApi === [] && $imageUrl === '') {
                $txt = uazapi_send_text($cfg, $number, $message);
                if (!empty($txt['ok'])) {
                    $okAny = true;
                }
            }
        }
    }

    if ($okAny) {
        $bits = ['Mensagem enviada para o WhatsApp de '.$who];
        if ($attachPdf) {
            $bits[] = 'com o PDF de confirmação da reserva';
        }
        if ($imageUrl !== '') {
            $bits[] = 'com imagem';
        }
        if ($buttonsApi !== []) {
            $bits[] = 'e botões';
        }
        return ['ok' => true, 'message' => implode(' ', $bits).'.'];
    }
    return ['ok' => false, 'message' => $lastFail];
}

function communicate_automation_catalog(): array
{
    return [
        'appointment_created' => [
            'event' => 'appointment.created',
            'kind' => 'appointment',
            'label' => 'Novo agendamento',
            'hint' => 'Quando um horário é criado no painel ou pelo site.',
            'intro' => 'Olá! Seu agendamento foi registrado.',
            'outro' => 'Qualquer dúvida, responda esta mensagem.',
        ],
        'appointment_confirmed' => [
            'event' => 'appointment.confirmed',
            'kind' => 'appointment',
            'label' => 'Agendamento confirmado',
            'hint' => 'Quando o status muda para Confirmado.',
            'intro' => 'Olá! Confirmamos o seu horário.',
            'outro' => 'Até breve.',
        ],
        'appointment_cancelled' => [
            'event' => 'appointment.cancelled',
            'kind' => 'appointment',
            'label' => 'Agendamento cancelado',
            'hint' => 'Quando o horário é cancelado.',
            'intro' => 'Olá! Seu agendamento foi cancelado.',
            'outro' => 'Se quiser remarcar, fale conosco.',
        ],
        'appointment_reminder' => [
            'event' => 'appointment.reminder',
            'kind' => 'appointment',
            'label' => '1 dia antes do agendamento',
            'hint' => 'Dispara sozinho no dia anterior ao horário, no WhatsApp do cliente. Conferido de manhã.',
            'intro' => 'Olá! Lembrete: amanhã você tem um horário marcado.',
            'outro' => 'Esperamos você.',
            'days_before' => 1,
        ],
        'request_created' => [
            'event' => 'request.created',
            'kind' => 'request',
            'label' => 'Nova solicitação',
            'hint' => 'Quando um pedido entra pelo site ou webhook.',
            'intro' => 'Olá! Recebemos a sua solicitação.',
            'outro' => 'Em breve retornamos.',
        ],
    ];
}

function communicate_automations_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS communicate_automations jsonb NOT NULL DEFAULT '{}'::jsonb");
            db()->exec("CREATE TABLE IF NOT EXISTS communicate_auto_log (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                rule_id TEXT NOT NULL,
                record_id TEXT NOT NULL,
                sent_at TIMESTAMPTZ NOT NULL
            )");
            db()->exec('CREATE UNIQUE INDEX IF NOT EXISTS communicate_auto_log_uniq ON communicate_auto_log(tenant_id, rule_id, record_id)');
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('communicate_automations', $cols, true)) {
            db()->exec("ALTER TABLE tenants ADD COLUMN communicate_automations TEXT DEFAULT '{}'");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS communicate_auto_log (
            id TEXT PRIMARY KEY,
            tenant_id TEXT NOT NULL,
            rule_id TEXT NOT NULL,
            record_id TEXT NOT NULL,
            sent_at TEXT NOT NULL
        )");
        db()->exec('CREATE UNIQUE INDEX IF NOT EXISTS communicate_auto_log_uniq ON communicate_auto_log(tenant_id, rule_id, record_id)');
    } catch (Throwable $e) {
        $done = false;
    }
}

function communicate_automations_of(array $tenant): array
{
    communicate_automations_ensure_schema();
    $raw = $tenant['communicate_automations'] ?? '{}';
    $saved = is_array($raw) ? $raw : json_arr($raw);
    $enabled = !empty($saved['enabled']);
    $rulesIn = is_array($saved['rules'] ?? null) ? $saved['rules'] : [];
    $rules = [];
    foreach (communicate_automation_catalog() as $id => $meta) {
        $row = is_array($rulesIn[$id] ?? null) ? $rulesIn[$id] : [];
        $days = (int)($row['days_before'] ?? 0);
        if ($days < 1) {
            $hoursLegacy = (int)($row['hours_before'] ?? ($meta['hours_before'] ?? 24));
            $days = (int)($meta['days_before'] ?? max(1, (int)round($hoursLegacy / 24)));
        }
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 3) {
            $days = 3;
        }
        $rules[$id] = [
            'enabled' => !empty($row['enabled']),
            'use_template' => array_key_exists('use_template', $row) ? !empty($row['use_template']) : true,
            'intro' => (string)($row['intro'] ?? $meta['intro']),
            'outro' => (string)($row['outro'] ?? $meta['outro']),
            'days_before' => $days,
            'hours_before' => $days * 24,
        ];
    }
    return ['enabled' => $enabled, 'rules' => $rules];
}

function communicate_automations_save(string $tenantId, array $data): void
{
    communicate_automations_ensure_schema();
    $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
    try {
        if (is_pgsql()) {
            q('UPDATE tenants SET communicate_automations=CAST(? AS jsonb), updated_at=? WHERE id=?', [$encoded, now(), $tenantId]);
        } else {
            q('UPDATE tenants SET communicate_automations=?, updated_at=? WHERE id=?', [$encoded, now(), $tenantId]);
        }
    } catch (Throwable) {
    }
}

function communicate_automations_from_post(): array
{
    $saved = ['enabled' => post('auto_enabled') === '1', 'rules' => []];
    foreach (communicate_automation_catalog() as $id => $meta) {
        $days = (int)post('days_'.$id, (string)($meta['days_before'] ?? 1));
        $saved['rules'][$id] = [
            'enabled' => post('on_'.$id) === '1',
            'use_template' => post('tpl_'.$id) === '1',
            'intro' => trim((string)post('intro_'.$id, $meta['intro'])),
            'outro' => trim((string)post('outro_'.$id, $meta['outro'])),
            'days_before' => $days,
        ];
    }
    return communicate_automations_of(['communicate_automations' => $saved]);
}

function communicate_auto_logged(string $tenantId, string $ruleId, string $recordId): bool
{
    communicate_automations_ensure_schema();
    try {
        return (bool)one('SELECT id FROM communicate_auto_log WHERE tenant_id=? AND rule_id=? AND record_id=?', [$tenantId, $ruleId, $recordId]);
    } catch (Throwable) {
        return false;
    }
}

function communicate_auto_mark(string $tenantId, string $ruleId, string $recordId): void
{
    communicate_automations_ensure_schema();
    try {
        q('INSERT INTO communicate_auto_log(id, tenant_id, rule_id, record_id, sent_at) VALUES(?,?,?,?,?)', [
            uid(), $tenantId, $ruleId, $recordId, now(),
        ]);
    } catch (Throwable) {
    }
}

function communicate_automation_fire(array $tenant, string $event, string $kind, string $id): array
{
    try {
        $fresh = one('SELECT * FROM tenants WHERE id=?', [(string)$tenant['id']]) ?: $tenant;
        $cfg = communicate_automations_of($fresh);
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'skip' => 'off'];
        }
        $ruleId = '';
        foreach (communicate_automation_catalog() as $rid => $meta) {
            if ($meta['event'] === $event && $meta['kind'] === $kind) {
                $ruleId = $rid;
                break;
            }
        }
        if ($ruleId === '' || empty($cfg['rules'][$ruleId]['enabled'])) {
            return ['ok' => false, 'skip' => 'rule'];
        }
        if (communicate_auto_logged((string)$fresh['id'], $ruleId, $id)) {
            return ['ok' => false, 'skip' => 'sent'];
        }
        $rule = $cfg['rules'][$ruleId];
        $payload = communicate_payload($kind, communicate_load((string)$fresh['id'], $kind, $id) ?? ['id' => $id], $fresh);
        $intro = !empty($rule['use_template']) ? (string)$payload['intro'] : (string)$rule['intro'];
        $outro = !empty($rule['use_template']) ? (string)$payload['outro'] : (string)$rule['outro'];
        if (trim($intro.$outro) === '') {
            $intro = (string)($rule['intro'] ?: $payload['intro']);
        }
        $row = communicate_load((string)$fresh['id'], $kind, $id);
        if (!$row) {
            return ['ok' => false, 'skip' => 'missing'];
        }
        $message = communicate_message($intro, $payload['selected'] ?? [], communicate_variables($kind, $row, $fresh), $outro);
        if ($message === '') {
            return ['ok' => false, 'skip' => 'empty'];
        }
        $sent = communicate_dispatch($fresh, $row, $message);
        if (!empty($sent['ok'])) {
            communicate_auto_mark((string)$fresh['id'], $ruleId, $id);
        }
        return $sent;
    } catch (Throwable $e) {
        return ['ok' => false, 'skip' => 'error'];
    }
}

function communicate_automation_reminder_window(int $daysBefore = 1): array
{
    if ($daysBefore < 1) {
        $daysBefore = 1;
    }
    if ($daysBefore > 3) {
        $daysBefore = 3;
    }
    $day = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))
        ->modify('+'.$daysBefore.' day')
        ->format('Y-m-d');
    return ['from' => $day.' 00:00:00', 'to' => $day.' 23:59:59', 'day' => $day];
}

function communicate_automation_reminders(): array
{
    communicate_automations_ensure_schema();
    $out = ['sent' => 0, 'tenants' => 0];
    try {
        $tenants = all('SELECT * FROM tenants WHERE status=?', ['ACTIVE']);
    } catch (Throwable) {
        return $out;
    }
    foreach ($tenants as $tenant) {
        $cfg = communicate_automations_of($tenant);
        if (empty($cfg['enabled']) || empty($cfg['rules']['appointment_reminder']['enabled'])) {
            continue;
        }
        $out['tenants']++;
        $days = (int)($cfg['rules']['appointment_reminder']['days_before'] ?? 1);
        $window = communicate_automation_reminder_window($days);
        $rows = all(
            "SELECT id FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<=? AND status NOT IN ('CANCELLED','DONE','NO_SHOW')",
            [(string)$tenant['id'], $window['from'], $window['to']]
        );
        foreach ($rows as $row) {
            $res = communicate_automation_fire($tenant, 'appointment.reminder', 'appointment', (string)$row['id']);
            if (!empty($res['ok'])) {
                $out['sent']++;
            }
        }
    }
    return $out;
}
