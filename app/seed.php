<?php
declare(strict_types=1);

function nexo_seed(PDO $pdo): void
{
    $segments = require __DIR__ . '/segments.php';
    $tnow = date('Y-m-d H:i:s');
    $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $on = $pg ? 'TRUE' : '1';
    $off = $pg ? 'FALSE' : '0';

    foreach ([['starter','Starter','Agenda, clientes e solicitações'],['pro','Pro','Webhooks e personalização'],['business','Business','Para operações com mais volume']] as $p) {
        $sql = $pg
            ? 'INSERT INTO plans(id,name,slug,description,active) VALUES(?,?,?,?,'.$on.') ON CONFLICT (slug) DO NOTHING'
            : 'INSERT OR IGNORE INTO plans(id,name,slug,description,active) VALUES(?,?,?,?,'.$on.')';
        $pdo->prepare($sql)->execute([bin2hex(random_bytes(8)), $p[1], $p[0], $p[2]]);
    }
    foreach ($segments as $slug => $s) {
        $sql = $pg
            ? 'INSERT INTO segments(id,name,slug,category,active) VALUES(?,?,?,?,'.$on.') ON CONFLICT (slug) DO NOTHING'
            : 'INSERT OR IGNORE INTO segments(id,name,slug,category,active) VALUES(?,?,?,?,'.$on.')';
        $pdo->prepare($sql)->execute([bin2hex(random_bytes(8)), $s['name'], $slug, $s['category']]);
    }

    $email = strtolower(env_str('MASTER_EMAIL', 'nathan.k@example.net'));
    $username = strtolower(env_str('MASTER_USERNAME', 'admin'));
    $exists = $pdo->prepare('SELECT id FROM users WHERE lower(email)=? OR lower(username)=?');
    $exists->execute([$email, $username]);
    if ($exists->fetch()) {
        return;
    }

    $password = env_str('MASTER_PASSWORD');
    if (!$password) {
        if (is_vercel()) {
            throw new RuntimeException('Defina MASTER_PASSWORD no ambiente da Vercel.');
        }
        $password = 'Admin@123';
    }

    $pdo->prepare('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,'.$off.','.$on.',?)')
        ->execute([bin2hex(random_bytes(12)), null, 'Admin Master', $email, $username, password_hash($password, PASSWORD_DEFAULT), 'MASTER', $tnow]);
}
