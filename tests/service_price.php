<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';

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

expect(service_price_label(['price_kind' => 'reuniao', 'price' => 0]) === 'Reunião', 'reunião não mostra R$ 0,00');
expect(service_price_label(['price_kind' => 'convenio', 'price' => 0]) === 'Convênio', 'convênio não mostra R$ 0,00');
expect(service_price_label(['price_kind' => 'cortesia', 'price' => 0]) === money(0), 'cortesia mostra R$ 0,00');
expect(service_price_label(['price_kind' => 'priced', 'price' => 150.5]) === money(150.5), 'serviço com preço mostra o valor');
expect(service_price_label(['price' => 0]) === money(0), 'legado com preço 0 ainda mostra R$ 0,00');

$_POST = ['has_price' => '1', 'price' => '0,00'];
$bad = parse_service_pricing();
expect(empty($bad['ok']), 'preço 0,00 é recusado');

$_POST = ['has_price' => '1', 'price' => '0,01'];
$ok = parse_service_pricing();
expect(!empty($ok['ok']) && abs(($ok['price'] ?? 0) - 0.01) < 0.0001 && ($ok['price_kind'] ?? '') === 'priced', 'preço mínimo 0,01 é aceito');

$_POST = ['has_price' => '0', 'no_price_kind' => 'reuniao'];
$meet = parse_service_pricing();
expect(!empty($meet['ok']) && ($meet['price_kind'] ?? '') === 'reuniao' && (float)($meet['price'] ?? 1) === 0.0, 'reunião grava sem valor');

$_POST = ['has_price' => '0'];
$need = parse_service_pricing();
expect(empty($need['ok']), 'sem preço exige convênio, cortesia ou reunião');

$view = file_get_contents(dirname(__DIR__).'/views/app/servicos.php');
$js = file_get_contents(dirname(__DIR__).'/public/assets/app.js');
expect(str_contains($view, 'name="has_price"') && str_contains($view, 'no_price_kind'), 'formulário tem possui/não possui preço');
expect(str_contains($view, 'Reunião') && str_contains($view, 'Convênio') && str_contains($view, 'Cortesia'), 'opções sem preço no cadastro');
expect(str_contains($view, 'service_price_label($s)'), 'cards e tabela usam o rótulo de preço');
expect(str_contains($view, "\$_GET['view'] ?? 'cards'") && str_contains($view, 'service-grid'), 'lista abre em cards');
expect(str_contains($view, 'Ver detalhes') && str_contains($view, 'Detalhes do serviço'), 'serviço tem visualização só leitura');
expect(str_contains($js, 'bindServicePrice') && str_contains($js, 'maskReais'), 'máscara força reais x,xx');
expect(str_contains($js, 'bindServiceDuration') && str_contains($view, 'js-duration-preset'), 'atalhos de duração longa no cadastro');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) de preço de serviço falharam.\n");
    exit(1);
}
echo "Preço de serviço (reunião, convênio e cortesia) aprovado.\n";
