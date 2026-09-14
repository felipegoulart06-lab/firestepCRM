<?php
declare(strict_types=1);

function harden_request(): void
{
    foreach (['tenant_id', 'company_id', 'role', 'is_admin', 'admin', 'permissions', 'webhook_access', 'plan', 'password_hash', 'must_change_password'] as $k) {
        unset($_POST[$k], $_GET[$k], $_REQUEST[$k]);
    }
}

function safe_internal_path(string $to): string
{
    if ($to === '' || str_contains($to, "\r") || str_contains($to, "\n") || str_contains($to, '\\')) {
        return '/';
    }
    $to = trim($to);
    if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '://')) {
        return '/';
    }
    return $to;
}

function request_origin_ok(): bool
{
    $ref = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
    if ($ref === '') {
        return true;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $from = strtolower((string)(parse_url($ref, PHP_URL_HOST) ?? ''));
    if ($host === '' || $from === '') {
        return false;
    }
    return hash_equals($host, $from);
}

function webhook_url_allowed(?string $url): bool
{
    $url = trim((string)$url);
    if ($url === '') {
        return true;
    }
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
        return false;
    }
    $host = strtolower((string)$p['host']);
    if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'metadata.google.internal'], true)) {
        return false;
    }
    if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return false;
    }
    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

function owned(string $table, string $id, string $tenantId): bool
{
    if ($id === '' || $tenantId === '') {
        return false;
    }
    $ok = ['clients', 'services', 'appointments', 'requests', 'webhooks', 'calendar_blocks', 'custom_fields', 'custom_field_values', 'finance_entries', 'notifications'];
    if (!in_array($table, $ok, true)) {
        return false;
    }
    return (bool)one("SELECT id FROM {$table} WHERE id=? AND tenant_id=?", [$id, $tenantId]);
}
