<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-home-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';

db();
app_home_ensure_schema();

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
    'ten-h', 'H', 'Empresa H', 'emp-h-home', 'outros', 'h@ex.com', 'ACTIVE', $now, $now,
]);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-h']);
$crm = ['role' => 'user_crm'];
$agent = ['role' => 'user_agent'];

expect(app_home_path($tenant, $crm) === '/app/agenda', 'padrão é Agenda');
expect(isset(app_home_choices(null, $tenant)['/app/clientes']), 'lista inclui Clientes');

q('UPDATE tenants SET home_path=? WHERE id=?', ['/app/kanban', 'ten-h']);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-h']);
expect(app_home_path($tenant, $crm) === '/app/kanban', 'admin abre o Pipeline escolhido');
expect(app_home_path($tenant, $agent) === '/app/kanban', 'agente também abre Pipeline');

q('UPDATE tenants SET home_path=? WHERE id=?', ['/app/servicos', 'ten-h']);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-h']);
expect(app_home_path($tenant, $crm) === '/app/servicos', 'admin abre Serviços');
expect(app_home_path($tenant, $agent) === '/app/agenda', 'agente cai na Agenda se o menu for exclusivo');

q('UPDATE tenants SET home_path=? WHERE id=?', ['/nao-existe', 'ten-h']);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-h']);
expect(app_home_stored($tenant) === '/app/agenda', 'valor inválido volta para Agenda');

$cfg = file_get_contents(dirname(__DIR__) . '/views/app/config.php');
expect(str_contains($cfg, '/app/configuracoes/inicio') && str_contains($cfg, 'Tela inicial'), 'Avançado tem seletor de tela inicial');
$idx = file_get_contents(dirname(__DIR__) . '/public/index.php');
expect(str_contains($idx, 'redirect_app_home') && str_contains($idx, 'app_home_path($tenant, $user)'), 'login e /app usam a tela inicial');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Tela inicial do CRM persiste e respeita o papel do usuário.\n";
