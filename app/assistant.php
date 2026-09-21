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
