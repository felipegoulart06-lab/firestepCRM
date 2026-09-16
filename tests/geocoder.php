<?php
declare(strict_types=1);

$root = dirname(__DIR__);
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

putenv('FIRESTEP_NO_GEO=1');
$_ENV['FIRESTEP_NO_GEO'] = '1';
require $root . '/app/helpers.php';

$hit = geocode_parse_nominatim([
    'lat' => '-23.5614',
    'lon' => '-46.6558',
    'address' => [
        'road' => 'Avenida Paulista',
        'house_number' => '1000',
        'city' => 'São Paulo',
        'state' => 'São Paulo',
        'postcode' => '01310-100',
    ],
], 'Avenida Paulista, São Paulo - SP');
expect(is_array($hit) && abs($hit['lat'] + 23.5614) < 0.001, 'parse Nominatim no Brasil');
expect(($hit['city'] ?? '') === 'São Paulo' && ($hit['state'] ?? '') === 'SP', 'cidade e UF extraídas');
expect(geo_posted_point('-23.56', '-46.65') !== null, 'ponto POST válido');
expect(geo_posted_point('51.5', '-0.1') === null, 'ponto fora do Brasil rejeitado');
expect(geocode_search('Paulista') === [], 'sem chamada remota quando FIRESTEP_NO_GEO');

$index = file_get_contents($root . '/public/index.php');
$geojs = file_get_contents($root . '/public/assets/geo.js');
$cliente = file_get_contents($root . '/views/app/cliente.php');
$forn = file_get_contents($root . '/views/app/fornecedores.php');
$modal = file_get_contents($root . '/views/app/modal_appointment.php');
$layout = file_get_contents($root . '/views/layout_app_end.php');
$cov = file_get_contents($root . '/app/coverage.php');

expect(str_contains($index, "/app/geo/search"), 'proxy JSON de busca');
expect(str_contains($geojs, '/app/geo/search') && str_contains($geojs, 'bindGeoLine'), 'autocomplete no cliente');
expect(str_contains($cliente, 'data-geo-box') && str_contains($forn, 'data-geo-box'), 'cliente e fornecedor usam o mapa');
expect(str_contains($modal, 'visit_lats[]') && str_contains($modal, 'data-geo-line'), 'paradas do atendimento externo');
expect(str_contains($layout, 'geo.js'), 'geo.js no layout');
expect(str_contains($cov, 'LEAFLET_TOKEN') && str_contains($cov, 'maptiler'), 'token Leaflet/MapTiler no servidor');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) falhou(ram).\n");
    exit(1);
}
echo "Geocoder Leaflet/Nominatim ligado aos formulários de Abrangência.\n";
