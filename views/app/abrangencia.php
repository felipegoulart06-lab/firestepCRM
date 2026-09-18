<?php
$pins = $pins ?? [];
$counts = ['supplier'=>0,'client'=>0,'visit'=>0];
foreach ($pins as $p) {
    $counts[$p['kind']] = ($counts[$p['kind']] ?? 0) + 1;
}
$kinds = [
    'supplier' => 'Fornecedor CNPJ',
    'client' => 'Cliente CNPJ',
    'visit' => 'Agendamento externo',
];
$mapPins = [];
foreach ($pins as $p) {
    $mapPins[] = [
        'kind' => (string)($p['kind'] ?? ''),
        'kindLabel' => $kinds[$p['kind'] ?? ''] ?? 'Pin',
        'name' => (string)($p['name'] ?? $p['label'] ?? ''),
        'phone' => (string)($p['phone'] ?? ''),
        'info' => (string)($p['info'] ?? $p['detail'] ?? ''),
        'lat' => (float)($p['lat'] ?? 0),
        'lng' => (float)($p['lng'] ?? 0),
    ];
}
?>
<div class="page-head">
  <div>
    <h1>Abrangência</h1>
    <p>Mapa interativo com fornecedores CNPJ, clientes CNPJ e atendimentos externos feitos manualmente.</p>
  </div>
</div>

<div class="cv-shell">
  <div class="cv-map-box">
    <div id="map" role="application" aria-label="Mapa de abrangência"></div>
    <?php if (!$pins): ?>
      <div class="cv-empty">Nenhum pin ainda. Cadastre um fornecedor CNPJ, um cliente CNPJ com endereço, ou crie um agendamento manual com atendimento externo.</div>
    <?php endif; ?>
  </div>

  <div class="cv-legend card">
    <h2>Legenda do mapa</h2>
    <ul>
      <li><span class="cv-dot cv-supplier"></span> <b>Fornecedores CNPJ</b> <small><?= (int)$counts['supplier'] ?> pin<?= $counts['supplier']===1?'':'s' ?> · laranja</small></li>
      <li><span class="cv-dot cv-client"></span> <b>Clientes CNPJ</b> <small><?= (int)$counts['client'] ?> pin<?= $counts['client']===1?'':'s' ?> · azul</small></li>
      <li><span class="cv-dot cv-visit"></span> <b>Agendamentos externos (manual)</b> <small><?= (int)$counts['visit'] ?> pin<?= $counts['visit']===1?'':'s' ?> · verde</small></li>
    </ul>
    <p class="muted">O pin de agendamento só aparece quando o administrador da empresa marca <b>atendimento externo</b> e informa um ou mais endereços no formulário de Novo agendamento. Fornecedores CNPJ com endereço vêm do menu <a href="/app/fornecedores">Fornecedores</a>.</p>
  </div>
</div>
<script type="application/json" id="coverage-pins"><?= json_encode($mapPins, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const el = document.getElementById('map');
  if (!el || typeof window.loadMapbox !== 'function') return;
  window.loadMapbox().then(function (mapboxgl) {
    const map = new mapboxgl.Map({
      container: el,
      style: 'mapbox://styles/mapbox/streets-v12',
      center: [-47.9292, -15.7801],
      zoom: 4
    });
    let pins = [];
    try { pins = JSON.parse(document.getElementById('coverage-pins').textContent || '[]'); } catch (e) { pins = []; }
    const bounds = [];
    pins.forEach(function (p) {
      if (!p || isNaN(p.lat) || isNaN(p.lng)) return;
      const name = p.name || 'Local';
      const phone = p.phone && p.phone !== '—' ? p.phone : 'Telefone não informado';
      const info = p.info || '';
      const html = '<strong>' + name.replace(/</g,'') + '</strong><br>' +
        (p.kindLabel ? p.kindLabel.replace(/</g,'') + '<br>' : '') +
        'Tel.: ' + phone.replace(/</g,'') +
        (info ? '<br>' + info.replace(/</g,'') : '');
      const pin = document.createElement('div');
      pin.className = 'cv-pin cv-' + p.kind;
      pin.innerHTML = '<i></i>';
      new mapboxgl.Marker({ element: pin, anchor: 'bottom' })
        .setLngLat([p.lng, p.lat])
        .setPopup(new mapboxgl.Popup({ offset: 16 }).setHTML(html))
        .addTo(map);
      bounds.push([p.lng, p.lat]);
    });
    if (bounds.length === 1) map.setCenter(bounds[0]).setZoom(13);
    else if (bounds.length > 1) {
      const box = bounds.reduce(function (b, ll) { return b.extend(ll); }, new mapboxgl.LngLatBounds(bounds[0], bounds[0]));
      map.fitBounds(box, { padding: 40, maxZoom: 13 });
    }
    map.on('load', function () { map.resize(); });
    setTimeout(function () { map.resize(); }, 80);
  }).catch(function () {});
});
</script>
