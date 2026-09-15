<?php
declare(strict_types=1);

const LETTERHEAD_PALETTE = [
    '#0f2744', '#1d4ed8', '#0f766e', '#7c2d12',
    '#4c1d95', '#be123c', '#0f172a', '#0369a1',
];

function letterhead_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        if (is_pgsql()) {
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS letterhead_config jsonb NOT NULL DEFAULT '{}'::jsonb");
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS signature_config jsonb NOT NULL DEFAULT '{}'::jsonb");
            db()->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS clauses_config jsonb NOT NULL DEFAULT '{}'::jsonb");
            return;
        }
        $cols = array_column(db()->query('PRAGMA table_info(tenants)')->fetchAll(), 'name');
        foreach (['letterhead_config', 'signature_config', 'clauses_config'] as $col) {
            if (!in_array($col, $cols, true)) {
                db()->exec("ALTER TABLE tenants ADD COLUMN $col TEXT DEFAULT '{}'");
            }
        }
    } catch (Throwable $e) {
        $done = false;
    }
}

function letterhead_config(array $tenant): array
{
    letterhead_ensure_schema();
    $cfg = json_arr($tenant['letterhead_config'] ?? '{}');
    return $cfg + [
        'trade_name' => '',
        'email' => '',
        'phone' => '',
        'document_kind' => '',
        'document' => '',
        'cep' => '',
        'address' => '',
        'color' => '#0f2744',
        'logo' => '',
        'saved' => false,
        'active' => false,
    ];
}

function letterhead_hex(string $color): string
{
    $color = strtoupper(trim($color));
    if (preg_match('/^#[0-9A-F]{6}$/', $color)) {
        return $color;
    }
    return '#0F2744';
}

function letterhead_ink(string $hex): string
{
    $hex = ltrim(letterhead_hex($hex), '#');
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    $lum = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    return $lum > 0.62 ? '#101828' : '#FFFFFF';
}

function letterhead_complete(array $cfg): bool
{
    return trim((string)($cfg['trade_name'] ?? '')) !== ''
        && trim((string)($cfg['email'] ?? '')) !== ''
        && trim((string)($cfg['phone'] ?? '')) !== ''
        && trim((string)($cfg['document'] ?? '')) !== ''
        && trim((string)($cfg['cep'] ?? '')) !== ''
        && trim((string)($cfg['address'] ?? '')) !== ''
        && !empty($cfg['saved']);
}

function letterhead_active(array $tenant): bool
{
    $cfg = letterhead_config($tenant);
    return !empty($cfg['active']) && letterhead_complete($cfg);
}

function letterhead_cep(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if (strlen($d) !== 8) {
        return null;
    }
    return substr($d, 0, 5).'-'.substr($d, 5);
}

function letterhead_jpeg(array $cfg): ?array
{
    return contract_data_image((string)($cfg['logo'] ?? ''));
}

function contract_data_image(string $data): ?array
{
    if ($data === '' || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#i', $data, $m)) {
        return null;
    }
    $bin = base64_decode(preg_replace('/\s+/', '', $m[2]) ?: '', true);
    if ($bin === false || strlen($bin) < 32) {
        return null;
    }
    $info = @getimagesizefromstring($bin);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }
    if (($info['mime'] ?? '') === 'image/jpeg') {
        return ['bytes' => $bin, 'w' => (int)$info[0], 'h' => (int)$info[1]];
    }
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }
    $im = @imagecreatefromstring($bin);
    if (!$im) {
        return null;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $canvas = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
    imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
    imagedestroy($im);
    ob_start();
    imagejpeg($canvas, null, 92);
    imagedestroy($canvas);
    $jpeg = (string)ob_get_clean();
    $info2 = @getimagesizefromstring($jpeg);
    if (!$jpeg || !$info2) {
        return null;
    }
    return ['bytes' => $jpeg, 'w' => (int)$info2[0], 'h' => (int)$info2[1]];
}

function signature_config(array $tenant): array
{
    letterhead_ensure_schema();
    $cfg = json_arr($tenant['signature_config'] ?? '{}');
    return $cfg + [
        'image' => '',
        'saved' => false,
        'active' => false,
    ];
}

function signature_complete(array $cfg): bool
{
    return !empty($cfg['saved']) && trim((string)($cfg['image'] ?? '')) !== '';
}

function signature_active(array $tenant): bool
{
    $cfg = signature_config($tenant);
    return !empty($cfg['active']) && signature_complete($cfg);
}

function signature_preview(array $tenant): ?array
{
    if (!signature_active($tenant)) {
        return null;
    }
    $cfg = signature_config($tenant);
    return [
        'active' => true,
        'image' => (string)$cfg['image'],
    ];
}

function signature_from_post(array $current): array
{
    $hasUpload = is_array($_FILES['signature'] ?? null) && (($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $image = $hasUpload
        ? contract_read_image('signature', $current['image'] ?? '', 'assinatura')
        : (string)($current['image'] ?? '');
    if (!$hasUpload && !empty($_POST['remove_signature'])) {
        $image = '';
    }
    if ($image === '') {
        throw new RuntimeException('Envie uma imagem da assinatura (JPEG, PNG ou WEBP).');
    }
    return [
        'image' => $image,
        'saved' => true,
        'active' => !empty($current['active']),
    ];
}

function signature_save(string $tenantId, array $cfg): void
{
    letterhead_ensure_schema();
    q('UPDATE tenants SET signature_config=?, updated_at=? WHERE id=?', [
        json_encode($cfg, JSON_UNESCAPED_UNICODE), now(), $tenantId,
    ]);
}

function contract_read_image(string $field, ?string $existing, string $label): string
{
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return (string)$existing;
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a '.$label.'.');
    }
    if (($file['size'] ?? 0) > 220000) {
        throw new RuntimeException('A '.$label.' deve ter no máximo 200 KB.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $bin = $tmp !== '' ? (string)file_get_contents($tmp) : '';
    $info = $bin !== '' ? @getimagesizefromstring($bin) : false;
    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('A '.$label.' precisa ser JPEG, PNG ou WEBP.');
    }
    return 'data:'.$mime.';base64,'.base64_encode($bin);
}

function letterhead_preview(array $tenant, bool $requireActive = true): ?array
{
    $cfg = letterhead_config($tenant);
    if ($requireActive && (empty($cfg['active']) || !letterhead_complete($cfg))) {
        return null;
    }
    if (!letterhead_complete($cfg)) {
        $color = letterhead_hex((string)($cfg['color'] ?: '#0f2744'));
        return [
            'active' => true,
            'color' => $color,
            'ink' => letterhead_ink($color),
            'name' => (string)($tenant['display_name'] ?: $tenant['business_name'] ?: ''),
            'logo' => (string)($cfg['logo'] ?: ''),
            'lines' => array_values(array_filter([
                trim((string)($tenant['email'] ?? '').' · '.(string)($tenant['phone'] ?? '')),
                trim((string)($tenant['address'] ?? '')),
            ])),
        ];
    }
    $color = letterhead_hex((string)$cfg['color']);
    $kind = strtoupper((string)($cfg['document_kind'] ?: 'doc'));
    return [
        'active' => true,
        'color' => $color,
        'ink' => letterhead_ink($color),
        'name' => (string)$cfg['trade_name'],
        'logo' => (string)($cfg['logo'] ?: ''),
        'lines' => array_values(array_filter([
            trim($cfg['email'].' · '.$cfg['phone']),
            trim($kind.' '.$cfg['document']),
            trim('CEP '.$cfg['cep'].' · '.$cfg['address']),
        ])),
    ];
}

function letterhead_read_logo(?string $existing): string
{
    $file = $_FILES['logo'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return (string)$existing;
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a logo.');
    }
    if (($file['size'] ?? 0) > 220000) {
        throw new RuntimeException('A logo deve ter no máximo 200 KB.');
    }
    $tmp = (string)($file['tmp_name'] ?? '');
    $bin = $tmp !== '' ? (string)file_get_contents($tmp) : '';
    $info = $bin !== '' ? @getimagesizefromstring($bin) : false;
    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('A logo precisa ser JPEG, PNG ou WEBP.');
    }
    return 'data:'.$mime.';base64,'.base64_encode($bin);
}

function letterhead_from_post(array $current): array
{
    $email = strtolower(trim((string)post('email', '')));
    $cep = letterhead_cep((string)post('cep', ''));
    [$docOk, $document, $docErr] = parse_br_document(post('document_kind'), post('document'), true);
    if (!$docOk) {
        throw new RuntimeException($docErr ?: 'Informe CPF ou CNPJ.');
    }
    if (trim((string)post('trade_name', '')) === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe nome fantasia e um e-mail válido.');
    }
    if (trim((string)post('phone', '')) === '' || trim((string)post('address', '')) === '' || !$cep) {
        throw new RuntimeException('Informe telefone, CEP (00000-000) e o endereço completo.');
    }
    $hasUpload = is_array($_FILES['logo'] ?? null) && (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $logo = $hasUpload ? letterhead_read_logo($current['logo'] ?? '') : (string)($current['logo'] ?? '');
    if (!$hasUpload && !empty($_POST['remove_logo'])) {
        $logo = '';
    }
    return [
        'trade_name' => trim((string)post('trade_name')),
        'email' => $email,
        'phone' => trim((string)post('phone')),
        'document_kind' => strtolower((string)post('document_kind')),
        'document' => (string)$document,
        'cep' => $cep,
        'address' => trim((string)post('address')),
        'color' => letterhead_hex((string)post('color', '#0f2744')),
        'logo' => $logo,
        'saved' => true,
        'active' => !empty($current['active']),
    ];
}

function letterhead_save(string $tenantId, array $cfg): void
{
    letterhead_ensure_schema();
    q('UPDATE tenants SET letterhead_config=?, updated_at=? WHERE id=?', [
        json_encode($cfg, JSON_UNESCAPED_UNICODE), now(), $tenantId,
    ]);
}

function platform_letterhead_row(): mixed
{
    try {
        return one("SELECT value FROM platform_settings WHERE key='letterhead'");
    } catch (Throwable $e) {
        return null;
    }
}

function platform_letterhead_config(): array
{
    $row = platform_letterhead_row();
    $raw = is_array($row) ? ($row['value'] ?? '{}') : '{}';
    $cfg = is_array($raw) ? $raw : json_arr($raw);
    return ($cfg ?: []) + [
        'trade_name' => '',
        'email' => '',
        'phone' => '',
        'document_kind' => '',
        'document' => '',
        'cep' => '',
        'address' => '',
        'color' => '#0f2744',
        'logo' => '',
        'saved' => false,
        'active' => false,
    ];
}

function platform_letterhead_ready(?array $cfg = null): bool
{
    $cfg = $cfg ?? platform_letterhead_config();
    return !empty($cfg['saved']) && trim((string)($cfg['trade_name'] ?? '')) !== '';
}

function platform_letterhead_active(?array $cfg = null): bool
{
    $cfg = $cfg ?? platform_letterhead_config();
    return !empty($cfg['active']) && platform_letterhead_ready($cfg);
}

function platform_letterhead_save(array $cfg): void
{
    $encoded = json_encode($cfg, JSON_UNESCAPED_UNICODE);
    $now = now();
    if (is_pgsql()) {
        q("INSERT INTO platform_settings(key,value,updated_at) VALUES('letterhead', CAST(? AS jsonb), ?) ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=EXCLUDED.updated_at", [$encoded, $now]);
        return;
    }
    q("INSERT OR REPLACE INTO platform_settings(key,value,updated_at) VALUES('letterhead',?,?)", [$encoded, $now]);
}

function letterhead_preview_cfg(array $cfg): array
{
    $color = letterhead_hex((string)($cfg['color'] ?? '#0f2744'));
    $kind = strtoupper((string)($cfg['document_kind'] ?: 'doc'));
    return [
        'active' => true,
        'color' => $color,
        'ink' => letterhead_ink($color),
        'name' => (string)($cfg['trade_name'] ?? ''),
        'logo' => (string)($cfg['logo'] ?? ''),
        'lines' => array_values(array_filter([
            trim((string)($cfg['email'] ?? '').' · '.(string)($cfg['phone'] ?? ''), ' ·'),
            trim($kind.' '.(string)($cfg['document'] ?? '')),
            trim(((string)($cfg['cep'] ?? '') !== '' ? 'CEP '.$cfg['cep'].' · ' : '').(string)($cfg['address'] ?? '')),
        ])),
    ];
}

function letterhead_cfg_for_pdf(?array $tenant): ?array
{
    if ($tenant && letterhead_active($tenant)) {
        return letterhead_config($tenant);
    }
    $plat = platform_letterhead_config();
    if (platform_letterhead_active($plat)) {
        return $plat;
    }
    return null;
}

function letterhead_pdf_rgb(string $hex): array
{
    $hex = ltrim(letterhead_hex($hex), '#');
    return [
        round(hexdec(substr($hex, 0, 2)) / 255, 3),
        round(hexdec(substr($hex, 2, 2)) / 255, 3),
        round(hexdec(substr($hex, 4, 2)) / 255, 3),
    ];
}
