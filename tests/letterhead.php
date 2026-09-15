<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'firestep-lh-' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__) . '/app/helpers.php';

db();
letterhead_ensure_schema();

$fail = 0;
function expect($ok, string $msg): void
{
    global $fail;
    if ($ok) { echo "OK  $msg\n"; return; }
    $fail++;
    echo "FAIL $msg\n";
}

$now = now();
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-lh', 'LH', 'Empresa LH', 'emp-lh', 'outros', 'lh@ex.com', 'ACTIVE', $now, $now,
]);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-lh']);
$empty = letterhead_config($tenant);
expect($empty['active'] === false, 'inativo por padrão');
expect(letterhead_complete($empty) === false, 'incompleto sem salvar');
expect(letterhead_preview($tenant) === null, 'prévia some se inativo');

$cfg = [
    'trade_name' => 'Studio Aura',
    'email' => 'contato@aura.com',
    'phone' => '11999999999',
    'document_kind' => 'cnpj',
    'document' => '12.345.678/0001-90',
    'cep' => '01310-100',
    'address' => 'Av. Paulista, 1000',
    'color' => '#0f766e',
    'logo' => '',
    'saved' => true,
    'active' => false,
];
letterhead_save('ten-lh', $cfg);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-lh']);
expect(letterhead_complete(letterhead_config($tenant)), 'completo após salvar');
expect(letterhead_preview($tenant) === null, 'ainda oculto nos contratos');

$cfg['active'] = true;
letterhead_save('ten-lh', $cfg);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-lh']);
$prev = letterhead_preview($tenant);
expect($prev !== null && $prev['name'] === 'Studio Aura', 'prévia aparece quando ativo');
expect($prev['color'] === '#0F766E', 'cor da paleta normalizada');
expect(letterhead_cep('01310100') === '01310-100', 'CEP formatado');
expect(letterhead_cep('123') === null, 'CEP inválido');

$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X6ZkAAAAASUVORK5CYII=';
$sigEmpty = signature_config($tenant);
expect($sigEmpty['active'] === false, 'assinatura inativa por padrão');
expect(signature_complete($sigEmpty) === false, 'assinatura incompleta sem imagem');
signature_save('ten-lh', ['image' => $png, 'saved' => true, 'active' => false]);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-lh']);
expect(signature_complete(signature_config($tenant)), 'assinatura completa após salvar');
expect(signature_preview($tenant) === null, 'assinatura oculta até ativar');
signature_save('ten-lh', ['image' => $png, 'saved' => true, 'active' => true]);
$tenant = one('SELECT * FROM tenants WHERE id=?', ['ten-lh']);
expect(signature_preview($tenant)['image'] === $png, 'assinatura visível nos contratos quando ativa');

@unlink($tmp);
exit($fail ? 1 : 0);
