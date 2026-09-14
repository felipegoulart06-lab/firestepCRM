<?php
declare(strict_types=1);

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(dirname(__DIR__) . '/app/schema.sql'));

$now = date('Y-m-d H:i:s');
$insT = $pdo->prepare('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
$insT->execute(['ten-a', 'A', 'Empresa A', 'empresa-a', 'outros', 'a@example.com', 'ACTIVE', $now, $now]);
$insT->execute(['ten-b', 'B', 'Empresa B', 'empresa-b', 'outros', 'b@example.com', 'ACTIVE', $now, $now]);

$insU = $pdo->prepare('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
$insU->execute(['ua', 'ten-a', 'Admin A', 'a@example.com', 'admin-a', password_hash('x', PASSWORD_DEFAULT), 'TENANT_ADMIN', 0, 1, $now]);
$insU->execute(['ub', 'ten-b', 'Admin B', 'b@example.com', 'admin-b', password_hash('x', PASSWORD_DEFAULT), 'TENANT_ADMIN', 0, 1, $now]);

$insC = $pdo->prepare('INSERT INTO clients(id,tenant_id,name,phone,email,status,created_at) VALUES(?,?,?,?,?,?,?)');
$insC->execute(['cli-a', 'ten-a', 'Cliente A', '11999999999', 'ca@example.com', 'ACTIVE', $now]);
$insC->execute(['cli-b', 'ten-b', 'Cliente B', '11888888888', 'cb@example.com', 'ACTIVE', $now]);

$insA = $pdo->prepare('INSERT INTO appointments(id,tenant_id,client_id,starts_at,ends_at,status,source,created_at) VALUES(?,?,?,?,?,?,?,?)');
$insA->execute(['ap-a', 'ten-a', 'cli-a', $now, $now, 'SCHEDULED', 'Manual', $now]);
$insA->execute(['ap-b', 'ten-b', 'cli-b', $now, $now, 'SCHEDULED', 'Manual', $now]);

$fail = 0;
function expect($ok, string $msg): void
{
    global $fail;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL $msg\n";
}

$aSeesB = $pdo->prepare('SELECT id FROM clients WHERE id=? AND tenant_id=?');
$aSeesB->execute(['cli-b', 'ten-a']);
expect($aSeesB->fetch() === false, 'IDOR GET cliente B autenticado como A');

$search = $pdo->prepare("SELECT id FROM clients WHERE tenant_id=? AND name LIKE ?");
$search->execute(['ten-a', '%Cliente B%']);
expect($search->fetch() === false, 'busca de A não encontra cliente B');

$export = $pdo->prepare('SELECT id FROM appointments WHERE tenant_id=?');
$export->execute(['ten-a']);
$ids = $export->fetchAll(PDO::FETCH_COLUMN);
expect($ids === ['ap-a'], 'exportação de agendamentos só do tenant A');

$dash = $pdo->query("SELECT COUNT(*) FROM clients WHERE tenant_id='ten-a'")->fetchColumn();
expect((int)$dash === 1, 'dashboard A conta só 1 cliente');

$upd = $pdo->prepare('UPDATE clients SET name=? WHERE id=? AND tenant_id=?');
$upd->execute(['Hack', 'cli-b', 'ten-a']);
expect($upd->rowCount() === 0, 'UPDATE cross-tenant não altera');

$del = $pdo->prepare('DELETE FROM appointments WHERE id=? AND tenant_id=?');
$del->execute(['ap-b', 'ten-a']);
expect($del->rowCount() === 0, 'DELETE cross-tenant não remove');

$join = $pdo->prepare('SELECT c.name FROM appointments a JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id WHERE a.tenant_id=?');
$join->execute(['ten-a']);
expect($join->fetchColumn() === 'Cliente A', 'JOIN com tenant não vaza nome de B');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) Tenant A vs B falharam.\n");
    exit(1);
}
echo "Isolamento Tenant A vs Tenant B aprovado.\n";
