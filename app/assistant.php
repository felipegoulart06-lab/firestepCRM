<?php
declare(strict_types=1);

function assistant_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS assistant_threads (
          id TEXT PRIMARY KEY,
          tenant_id TEXT NOT NULL,
          user_id TEXT NOT NULL,
          question TEXT NOT NULL,
          answer TEXT,
          status TEXT NOT NULL DEFAULT 'open',
          created_at TEXT NOT NULL,
          answered_at TEXT,
          answered_by TEXT
        )");
        db()->exec('CREATE INDEX IF NOT EXISTS assistant_threads_tenant ON assistant_threads(tenant_id, created_at)');
        db()->exec('CREATE INDEX IF NOT EXISTS assistant_threads_status ON assistant_threads(status, created_at)');
    } catch (Throwable $e) {
        $done = false;
    }
}

function assistant_open_count(): int
{
    assistant_ensure_schema();
    try {
        return (int)(one("SELECT COUNT(*) c FROM assistant_threads WHERE status='open'")['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function assistant_unread_for_user(string $tenantId, string $userId): int
{
    assistant_ensure_schema();
    try {
        return (int)(one(
            "SELECT COUNT(*) c FROM assistant_threads WHERE tenant_id=? AND user_id=? AND status='answered' AND ".sql_not_blank('answer'),
            [$tenantId, $userId]
        )['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function assistant_threads_for_user(string $tenantId, string $userId): array
{
    assistant_ensure_schema();
    return all(
        'SELECT id, question, answer, status, created_at, answered_at FROM assistant_threads WHERE tenant_id=? AND user_id=? ORDER BY created_at DESC LIMIT 40',
        [$tenantId, $userId]
    );
}

function assistant_submit(string $tenantId, string $userId, string $question): array
{
    assistant_ensure_schema();
    $question = trim($question);
    if ($question === '' || strlen($question) > 2000) {
        return ['ok' => false, 'error' => 'Escreva sua dúvida (até 2000 caracteres).'];
    }
    $id = uid();
    q(
        'INSERT INTO assistant_threads(id, tenant_id, user_id, question, status, created_at) VALUES(?,?,?,?,?,?)',
        [$id, $tenantId, $userId, $question, 'open', now()]
    );
    audit($tenantId, $userId, 'assistant.question', 'assistant_thread', $id);
    return ['ok' => true, 'id' => $id];
}

function assistant_reply(string $threadId, string $masterId, string $answer): bool
{
    assistant_ensure_schema();
    $answer = trim($answer);
    if ($answer === '' || strlen($answer) > 4000) {
        return false;
    }
    $row = one('SELECT id FROM assistant_threads WHERE id=?', [$threadId]);
    if (!$row) {
        return false;
    }
    q(
        "UPDATE assistant_threads SET answer=?, status='answered', answered_at=?, answered_by=? WHERE id=?",
        [$answer, now(), $masterId, $threadId]
    );
    return true;
}

function assistant_wa_digits(?string $raw): string
{
    return preg_replace('/\D+/', '', (string)$raw) ?? '';
}

function assistant_wa_format(?string $raw): string
{
    $d = assistant_wa_digits($raw);
    if (strlen($d) !== 11) {
        return '';
    }
    return sprintf('(%s) %s %s-%s', substr($d, 0, 2), substr($d, 2, 1), substr($d, 3, 4), substr($d, 7, 4));
}

function assistant_wa_valid(?string $raw): bool
{
    return assistant_wa_format($raw) !== '';
}

function assistant_handoff(array $tenant, array $user, string $phone): array
{
    assistant_ensure_schema();
    $formatted = assistant_wa_format($phone);
    if ($formatted === '') {
        return ['ok' => false, 'error' => 'Informe o WhatsApp no formato (00) 0 0000-0000.'];
    }
    $number = uazapi_wa_number($formatted);
    if ($number === '') {
        return ['ok' => false, 'error' => 'Informe o WhatsApp no formato (00) 0 0000-0000.'];
    }
    $cfg = uazapi_platform_config();
    if (!uazapi_ready($cfg)) {
        return ['ok' => false, 'error' => 'O atendimento está indisponível no momento. Tente de novo em instantes.'];
    }
    $who = trim((string)($user['name'] ?? $user['username'] ?? 'Cliente'));
    $company = trim((string)($tenant['display_name'] ?: $tenant['business_name'] ?: 'FirestepCRM'));
    $attendant = trim((string)($cfg['attendant'] ?? 'Felipe')) ?: 'Felipe';
    $text = "Olá! Sou o {$attendant}, do atendimento FirestepCRM.\n\nA Priscila me transferiu o seu atendimento ({$who} · {$company}). Pode me dizer como posso ajudar?";
    $sent = uazapi_send_text($cfg, $number, $text);
    $question = 'Atendimento WhatsApp: '.$formatted;
    $id = uid();
    q(
        'INSERT INTO assistant_threads(id, tenant_id, user_id, question, status, created_at) VALUES(?,?,?,?,?,?)',
        [$id, (string)$tenant['id'], (string)$user['id'], $question, 'open', now()]
    );
    audit((string)$tenant['id'], (string)$user['id'], 'assistant.handoff', 'assistant_thread', $id);
    if (empty($sent['ok'])) {
        return ['ok' => false, 'error' => 'Não consegui disparar o WhatsApp agora. Confira o número e tente de novo.'];
    }
    return ['ok' => true, 'id' => $id, 'attendant' => $attendant];
}

function assistant_master_list(): array
{
    assistant_ensure_schema();
    return all(
        "SELECT a.*, t.business_name, t.display_name, t.email tenant_email, u.name user_name, u.email user_email, u.username
         FROM assistant_threads a
         JOIN tenants t ON t.id=a.tenant_id
         JOIN users u ON u.id=a.user_id
         ORDER BY CASE WHEN a.status='open' THEN 0 ELSE 1 END, a.created_at DESC
         LIMIT 120"
    );
}
