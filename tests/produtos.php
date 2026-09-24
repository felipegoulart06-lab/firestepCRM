<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firestep-prod-'.bin2hex(random_bytes(4)).'.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__).'/app/helpers.php';

db()->exec(file_get_contents(dirname(__DIR__).'/app/schema.sql'));
products_ensure_schema();

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

$all = products_all();
$titles = array_map(static fn(array $p): string => (string)$p['title'], $all);
expect(count($all) === 5, 'catálogo inicial tem 5 produtos');
expect(in_array('Sistema + site de agendamento', $titles, true), 'sistema + site de agendamento');
expect(in_array('Landing page + campanha de marketing', $titles, true), 'landing + campanha');
expect(in_array('Chatbot para atendimento automático no WhatsApp + sistema', $titles, true), 'chatbot + sistema');
expect(in_array('Automatize tarefas do dia a dia', $titles, true), 'automatize tarefas');
expect(in_array('Imagens personalizadas para a sua empresa', $titles, true), 'imagens personalizadas');
expect(count(products_public()) === 5, 'os 5 começam visíveis');

$hidden = products_one('prod-imagens');
expect($hidden && products_set_active('prod-imagens', false), 'oculta um produto');
expect(count(products_public()) === 4, 'oculto some do menu das empresas');

$now = now();
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-p', 'P', 'Empresa P', 'emp-p-prod', 'outros', 'p@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO users(id,tenant_id,name,email,username,password_hash,role,must_change_password,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)', [
    'usr-p', 'ten-p', 'Ana', 'ana@ex.com', 'ana-p', 'x', 'user_crm', 1, 1, $now,
]);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-p']);
$user = one('SELECT * FROM users WHERE id=?', ['usr-p']);
$out = products_interest($tenant, $user, 'prod-sistema-site');
expect(!empty($out['ok']), 'interesse registra o pedido');
$leads = products_leads();
expect(($leads[0]['product_title'] ?? '') === 'Sistema + site de agendamento', 'Admin Master vê o interesse');

$index = file_get_contents(dirname(__DIR__).'/public/index.php');
$appNav = file_get_contents(dirname(__DIR__).'/views/layout_app_start.php');
$masterNav = file_get_contents(dirname(__DIR__).'/views/layout_master_start.php');
$css = file_get_contents(dirname(__DIR__).'/public/assets/app.css');
expect(str_contains($appNav, '/app/produtos') && str_contains($appNav, 'Produtos'), 'menu do CRM tem Produtos');
expect(str_contains($masterNav, '/master/produtos') && str_contains($masterNav, 'Produtos'), 'Admin Master cadastra produtos');
expect(str_contains($index, '/master/produtos/salvar') && str_contains($index, '/app/produtos/interesse'), 'rotas de cadastro e interesse');
expect(str_contains($css, '.product-card') && str_contains($css, '.product-grid'), 'cards do catálogo');
expect(str_contains(file_get_contents(dirname(__DIR__).'/views/app/produtos.php'), 'Tenho interesse'), 'card da empresa registra interesse');
expect(isset(app_home_choices(null, $tenant)['/app/produtos']), 'Produtos entra na tela inicial');
expect(agent_route_forbidden('/app/produtos'), 'agente não acessa o catálogo');

products_ensure_schema();
expect(count(products_all()) === 5, 'segunda carga não duplica o catálogo inicial');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "{$fail} teste(s) de produtos falharam.\n");
    exit(1);
}
echo "Catálogo de produtos digitais no Admin Master e no menu Produtos.\n";
