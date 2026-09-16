<?php
declare(strict_types=1);

$src = file_get_contents(dirname(__DIR__) . '/views/app/cliente.php');
$js = file_get_contents(dirname(__DIR__) . '/public/assets/app.js');
$index = file_get_contents(dirname(__DIR__) . '/public/index.php');
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
expect(str_contains($src, 'Cliente CPF') && str_contains($src, 'Cliente CNPJ'), 'formulário pede CPF ou CNPJ');
expect(str_contains($src, 'Razão social') && str_contains($src, 'Nome fantasia'), 'CNPJ tem dados de empresa');
expect(str_contains($src, 'data-req-cnpj') && str_contains($src, 'Endereço (obrigatório para CNPJ)'), 'endereço obrigatório no CNPJ');
expect(str_contains($src, 'data-geo-box') && str_contains($src, 'name="lat"'), 'CNPJ busca endereço no mapa');
expect(str_contains($index, 'Cliente CNPJ precisa de endereço completo'), 'servidor exige endereço no CNPJ');
expect(str_contains($js, 'data-req-cnpj') && str_contains($js, 'client-kind-body'), 'JS troca os campos conforme o tipo');
if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Cadastro de cliente CPF/CNPJ com campos distintos.\n";
