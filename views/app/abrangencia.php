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
  if (!window.L || !document.getElementById('map')) return;
  const map = L.map('map').setView([-15.7801, -47.9292], 4);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  let pins = [];
  try { pins = JSON.parse(document.getElementById('coverage-pins').textContent || '[]'); } catch (e) { pins = []; }
  const bounds = [];
    pins.forEach(function (p) {
    if (!p || isNaN(p.lat) || isNaN(p.lng)) return;
    const icon = L.divIcon({
      className: 'cv-pin cv-' + p.kind,
      html: '<i></i>',
      iconSize: [18, 18],
      iconAnchor: [9, 18],
      popupAnchor: [0, -16]
    });
    const name = p.name || 'Local';
    const phone = p.phone && p.phone !== '—' ? p.phone : 'Telefone não informado';
    const info = p.info || '';
    const html = '<strong>' + name.replace(/</g,'') + '</strong><br>' +
      (p.kindLabel ? p.kindLabel.replace(/</g,'') + '<br>' : '') +
      'Tel.: ' + phone.replace(/</g,'') +
      (info ? '<br>' + info.replace(/</g,'') : '');
    L.marker([p.lat, p.lng], { icon: icon, title: name })
      .addTo(map)
      .bindPopup(html);
    bounds.push([p.lat, p.lng]);
  });
  if (bounds.length === 1) map.setView(bounds[0], 13);
  else if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
  setTimeout(function () { map.invalidateSize(); }, 80);
});
</script>
