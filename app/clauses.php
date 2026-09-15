<?php
declare(strict_types=1);

function clauses_config(array $tenant): array
{
    letterhead_ensure_schema();
    $cfg = json_arr($tenant['clauses_config'] ?? '{}');
    return is_array($cfg) ? $cfg : [];
}

function clauses_lists(array $tenant): array
{
    $cfg = clauses_config($tenant);
    $out = [];
    if (isset($cfg['lists']) && is_array($cfg['lists'])) {
        foreach ($cfg['lists'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = preg_replace('#[^A-Za-z0-9._-]#', '', (string)($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $ids = [];
            foreach ((array)($row['appointment_ids'] ?? []) as $aid) {
                $aid = preg_replace('#[^A-Za-z0-9._-]#', '', (string)$aid);
                if ($aid !== '') {
                    $ids[] = $aid;
                }
            }
            $out[] = [
                'id' => $id,
                'name' => trim((string)($row['name'] ?? 'Contrato')) ?: 'Contrato',
                'appointment_ids' => array_values(array_unique($ids)),
                'html' => clauses_sanitize((string)($row['html'] ?? '')),
            ];
        }
    }
    return $out;
}

function clauses_list(array $tenant, string $id): ?array
{
    foreach (clauses_lists($tenant) as $row) {
        if ($row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function clauses_html_for(array $tenant, ?string $listId = null): string
{
    if ($listId) {
        $row = clauses_list($tenant, $listId);
        return $row ? (string)$row['html'] : '';
    }
    $cfg = clauses_config($tenant);
    if (isset($cfg['lists'])) {
        return '';
    }
    $slug = (string)($tenant['segment'] ?? '');
    $row = $cfg[$slug] ?? [];
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
    if (isset($map['lists']) && is_array($map['lists'])) {
        $allowed = [];
        foreach (all('SELECT id FROM appointments WHERE tenant_id=? AND status!=?', [$tenantId, 'CANCELLED']) as $row) {
            $allowed[(string)$row['id']] = true;
        }
        $lists = [];
        foreach ($map['lists'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = preg_replace('#[^A-Za-z0-9._-]#', '', (string)($row['id'] ?? ''));
            if ($id === '' || strlen($id) > 80) {
                $id = uid();
            }
            $ids = [];
            foreach ((array)($row['appointment_ids'] ?? []) as $aid) {
                $aid = preg_replace('#[^A-Za-z0-9._-]#', '', (string)$aid);
                if ($aid !== '' && isset($allowed[$aid])) {
                    $ids[] = $aid;
                }
            }
            $html = clauses_sanitize((string)($row['html'] ?? ''));
            if (strlen($html) > 20000) {
                $html = substr($html, 0, 20000);
            }
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Contrato';
            }
            if (strlen($name) > 120) {
                $name = substr($name, 0, 120);
            }
            if (!$ids && $html === '') {
                continue;
            }
            $lists[] = [
                'id' => $id,
                'name' => $name,
                'appointment_ids' => array_values(array_unique($ids)),
                'html' => $html,
            ];
        }
        $clean = ['lists' => $lists];
    } else {
        $clean = [];
        foreach ($map as $slug => $html) {
            $slug = strtolower(trim((string)$slug));
            if (!preg_match('/^[a-z0-9_-]{1,80}$/', $slug)) {
                continue;
            }
            $html = clauses_sanitize(is_array($html) ? (string)($html['html'] ?? '') : (string)$html);
            if (strlen($html) > 20000) {
                $html = substr($html, 0, 20000);
            }
            $clean[$slug] = ['html' => $html];
        }
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
        throw new RuntimeException('Não foi possível ler os contratos.');
    }
    return $decoded;
}

function clauses_appointments(string $tenantId): array
{
    return all(
        "SELECT a.id, a.starts_at, a.status, c.name client_name, s.name service_name
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        WHERE a.tenant_id=? AND a.status!='CANCELLED'
        ORDER BY a.starts_at DESC
        LIMIT 250",
        [$tenantId]
    );
}
