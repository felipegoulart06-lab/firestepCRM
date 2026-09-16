<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-sup-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('FIRESTEP_NO_GEO=1');
$_ENV['FIRESTEP_NO_GEO'] = '1';
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';

db();
coverage_ensure_schema();

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
    'ten-a', 'A', 'Empresa A', 'emp-a-sup', 'outros', 'a@ex.com', 'ACTIVE', $now, $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,contact_name,phone,email,city,state,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    's1', 'ten-a', 'Peças Silva', '529.982.247-25', 'cpf', 'Peças automotivas', 'João Silva', '11988887777', 'js@ex.com', 'São Paulo', 'SP', $now,
]);
q('INSERT INTO suppliers(id,tenant_id,name,cnpj,document_kind,product_type,address,city,state,lat,lng,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)', [
    's2', 'ten-a', 'Atacado Ltda', '11.222.333/0001-81', 'cnpj', 'Material de limpeza', 'Rua A 10', 'Campinas', 'SP', -22.9, -47.0, $now,
]);

$row = one('SELECT * FROM suppliers WHERE id=? AND tenant_id=?', ['s1', 'ten-a']);
expect(($row['document_kind'] ?? '') === 'cpf' && ($row['product_type'] ?? '') === 'Peças automotivas', 'fornecedor CPF com tipo de produto');

$pins = coverage_pins('ten-a');
$labels = array_column($pins, 'label');
expect(in_array('Atacado Ltda', $labels, true), 'CNPJ com endereço entra no mapa');
expect(!in_array('Peças Silva', $labels, true), 'CPF não vira pin de abrangência');
expect(agent_route_forbidden('/app/fornecedores'), 'agente não acessa Fornecedores');

$view = file_get_contents(dirname(__DIR__) . '/views/app/fornecedores.php');
expect(str_contains($view, '<table class="data">') && str_contains($view, 'product_type'), 'tela é tabela de gestão');
$nav = file_get_contents(dirname(__DIR__) . '/views/layout_app_start.php');
expect(str_contains($nav, '/app/fornecedores') && str_contains($nav, 'Fornecedores'), 'menu Fornecedores no painel');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Fornecedores: lista administrativa com CPF/CNPJ e produto.\n";
@unlink($tmp);
