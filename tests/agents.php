<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-ag-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';

db();

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

$now = now();
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-a', 'A', 'Empresa A', 'emp-a-ag', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-b', 'B', 'Empresa B', 'emp-b-ag', 'outros', 'b@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'crm-a', 'ten-a', 'Dono A', 'crm-a@ex.com', 'crm-a', 'x', 'user_crm', 0, 1, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-a', 'ten-a', 'Funcionario A', 'ag-a@ex.com', 'ag-a', 'x', 'user_agent', 0, 1, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'ag-b', 'ten-b', 'Funcionario B', 'ag-b@ex.com', 'ag-b', 'x', 'user_agent', 0, 1, $now,
]);

$a = all("SELECT id FROM users WHERE tenant_id=? AND role='user_agent'", ['ten-a']);
expect(count($a) === 1 && $a[0]['id'] === 'ag-a', 'empresa A lista só o próprio agente');

$cross = one("SELECT id FROM users WHERE id=? AND tenant_id=? AND role='user_agent'", ['ag-b', 'ten-a']);
expect($cross === null, 'empresa A não vê agente de B');

$admin = tenant_admin('ten-a');
expect($admin && $admin['id'] === 'crm-a', 'tenant_admin aponta para user_crm');

$master = one('SELECT role FROM users WHERE '.sql_is_platform_admin().' LIMIT 1');
expect($master && $master['role'] === 'user_admin', 'seed Master virou user_admin');

@unlink($tmp);
exit($fail ? 1 : 0);
