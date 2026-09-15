<?php
declare(strict_types=1);

function clauses_config(array $tenant): array
{
    letterhead_ensure_schema();
    $cfg = json_arr($tenant['clauses_config'] ?? '{}');
    return is_array($cfg) ? $cfg : [];
}

function clauses_html_for(array $tenant, ?string $slug = null): string
{
    $slug = $slug ?: (string)($tenant['segment'] ?? '');
    $all = clauses_config($tenant);
    $row = $all[$slug] ?? [];
    if (is_string($row)) {
        return clauses_sanitize($row);
    }
    return clauses_sanitize((string)($row['html'] ?? ''));
}

function clauses_sanitize(string $html): string
{
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/<\s*div[^>]*>/i', '<p>', $html) ?? $html;
    $html = preg_replace('/<\/\s*div\s*>/i', '</p>', $html) ?? $html;
    $html = preg_replace('/<br\s*\/?>/i', '<br>', $html) ?? $html;
    $html = strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li>');
    $html = preg_replace('/\son\w+="[^"]*"/i', '', $html) ?? $html;
    $html = preg_replace("/\son\w+='[^']*'/i", '', $html) ?? $html;
    return trim($html);
}

function clauses_save(string $tenantId, array $map): void
{
    letterhead_ensure_schema();
    $clean = [];
    foreach ($map as $slug => $html) {
        $slug = strtolower(trim((string)$slug));
        if (!preg_match('/^[a-z0-9_-]{1,80}$/', $slug)) {
            continue;
        }
        $html = clauses_sanitize((string)$html);
        if (strlen($html) > 20000) {
            $html = substr($html, 0, 20000);
        }
        $clean[$slug] = ['html' => $html];
    }
    q('UPDATE tenants SET clauses_config=?, updated_at=? WHERE id=?', [
        json_encode($clean, JSON_UNESCAPED_UNICODE), now(), $tenantId,
    ]);
}

function clauses_from_post(): array
{
    $raw = post('templates', '');
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Não foi possível ler as cláusulas.');
    }
    return $decoded;
}

function clauses_segments(): array
{
    $sql = 'SELECT slug, name, category FROM segments';
    try {
        $rows = all($sql.' WHERE '.sql_true('active').' ORDER BY category, name');
    } catch (Throwable $e) {
        $rows = all($sql.' ORDER BY category, name');
    }
    return $rows ?: [];
}
