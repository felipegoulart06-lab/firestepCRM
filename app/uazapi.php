<?php
declare(strict_types=1);

function uazapi_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS uazapi_config jsonb NOT NULL DEFAULT '{}'::jsonb");
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        if (!in_array('uazapi_config', $cols, true)) {
            db()->exec("ALTER TABLE tenants ADD COLUMN uazapi_config TEXT DEFAULT '{}'");
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function uazapi_config(array $tenant): array
{
    uazapi_ensure_schema();
    $cfg = json_arr($tenant['uazapi_config'] ?? '{}');
    $url = trim((string)($cfg['url'] ?? ''));
    $token = trim((string)($cfg['token'] ?? ''));
    if ($url === '') {
        $url = (string)env_str('UAZAPI_URL', '');
    }
    if ($token === '') {
        $token = (string)env_str('UAZAPI_TOKEN', '');
    }
    return [
        'url' => rtrim($url, '/'),
        'token' => $token,
        'pix_key' => trim((string)($cfg['pix_key'] ?? '')),
        'image' => trim((string)($cfg['image'] ?? '')),
    ];
}

function uazapi_ready(array $cfg): bool
{
    return $cfg['url'] !== '' && $cfg['token'] !== '' && preg_match('#^https?://#i', $cfg['url']) === 1;
}

function uazapi_save(string $tenantId, array $cfg): void
{
    uazapi_ensure_schema();
    q('UPDATE tenants SET uazapi_config=?, updated_at=? WHERE id=?', [
        json_encode([
            'url' => rtrim(trim((string)($cfg['url'] ?? '')), '/'),
            'token' => trim((string)($cfg['token'] ?? '')),
            'pix_key' => trim((string)($cfg['pix_key'] ?? '')),
            'image' => trim((string)($cfg['image'] ?? '')),
        ], JSON_UNESCAPED_UNICODE),
        now(),
        $tenantId,
    ]);
}

function uazapi_wa_number(?string $raw): string
{
    $d = preg_replace('/\D+/', '', (string)$raw) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '00')) {
        $d = substr($d, 2);
    }
    if (!str_starts_with($d, '55') && (strlen($d) === 10 || strlen($d) === 11)) {
        $d = '55'.$d;
    }
    if (!preg_match('/^55\d{10,11}$/', $d)) {
        return '';
    }
    return $d;
}

function uazapi_http(array $cfg, string $path, array $body): array
{
    if (!uazapi_ready($cfg)) {
        return ['ok' => false, 'code' => 0, 'json' => [], 'raw' => '', 'message' => 'Instância UAZAPI não configurada.'];
    }
    $url = $cfg['url'].'/'.ltrim($path, '/');
    $headers = [
        'Content-Type: application/json',
        'token: '.$cfg['token'],
        'Accept: application/json',
    ];
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = (string)curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === '' && $err !== '') {
            return ['ok' => false, 'code' => $code, 'json' => [], 'raw' => $err, 'message' => 'Falha ao falar com a UAZAPI.'];
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 25,
            'ignore_errors' => true,
        ]]);
        $raw = (string)@file_get_contents($url, false, $ctx);
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }
    $json = json_decode($raw, true);
    $ok = $code >= 200 && $code < 300;
    $msg = is_array($json) ? (string)($json['message'] ?? $json['error'] ?? '') : '';
    return [
        'ok' => $ok,
        'code' => $code,
        'json' => is_array($json) ? $json : [],
        'raw' => $raw,
        'message' => $ok ? 'ok' : ($msg !== '' ? $msg : 'A UAZAPI recusou o envio.'),
    ];
}

function uazapi_send_carousel(array $cfg, string $number, string $text, array $carousel): array
{
    $body = ['number' => $number, 'text' => $text, 'carousel' => $carousel];
    $res = uazapi_http($cfg, '/send/carousel', $body);
    if (!empty($res['ok'])) {
        return $res;
    }
    $choices = [];
    foreach ($carousel as $card) {
        $choices[] = '['.(string)($card['text'] ?? $text).']';
        $img = trim((string)($card['image'] ?? ''));
        if ($img !== '') {
            $choices[] = '{'.$img.'}';
        }
        foreach (($card['buttons'] ?? []) as $btn) {
            $label = (string)($btn['text'] ?? 'Abrir');
            $type = strtoupper((string)($btn['type'] ?? 'REPLY'));
            $id = (string)($btn['id'] ?? '');
            $choices[] = match ($type) {
                'URL' => $label.'|'.$id,
                'CALL' => $label.'|call:'.$id,
                'COPY' => $label.'|copy:'.$id,
                default => $label.'|'.$id,
            };
        }
    }
    return uazapi_http($cfg, '/send/menu', [
        'number' => $number,
        'type' => 'carousel',
        'text' => $text,
        'choices' => $choices,
    ]);
}

function uazapi_send_text(array $cfg, string $number, string $text): array
{
    return uazapi_http($cfg, '/send/text', ['number' => $number, 'text' => $text]);
}

function uazapi_send_charge(array $tenant, array $card): array
{
    $cfg = uazapi_config($tenant);
    $number = uazapi_wa_number($card['phone_raw'] ?? '');
    if ($number === '') {
        return ['ok' => false, 'message' => 'O cliente não tem WhatsApp ou telefone válido.'];
    }
    if (!uazapi_ready($cfg)) {
        return ['ok' => false, 'message' => 'Configure a UAZAPI em Configurações → Integrações.'];
    }
    $carousel = [[
        'text' => '*'.$card['title']."*\n".$card['description'],
        'image' => (string)($card['image'] ?? ''),
        'buttons' => $card['buttons_api'] ?? [],
    ]];
    if ($carousel[0]['image'] === '') {
        unset($carousel[0]['image']);
    }
    $res = uazapi_send_carousel($cfg, $number, (string)$card['title'], $carousel);
    if (!empty($res['ok'])) {
        return ['ok' => true, 'message' => 'Cobrança enviada no WhatsApp do cliente.'];
    }
    $fallback = $card['title']."\n\n".$card['description'];
    $txt = uazapi_send_text($cfg, $number, $fallback);
    if (!empty($txt['ok'])) {
        return ['ok' => true, 'message' => 'Cobrança enviada como mensagem de texto no WhatsApp.'];
    }
    return ['ok' => false, 'message' => (string)($res['message'] ?? 'Não foi possível enviar a cobrança.')];
}
