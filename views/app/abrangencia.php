<?php
$pins = $pins ?? [];
$land = $land ?? brazil_svg_path();
$suppliers = $suppliers ?? [];
$counts = ['supplier'=>0,'client'=>0,'visit'=>0];
foreach ($pins as $p) {
    $counts[$p['kind']] = ($counts[$p['kind']] ?? 0) + 1;
}
?>
<div class="page-head">
  <div>
    <h1>Abrangência</h1>
    <p>Mapa do Brasil com fornecedores CNPJ, clientes CNPJ e atendimentos externos feitos manualmente.</p>
  </div>
</div>

<div class="cv-shell">
  <div class="cv-map-box" role="img" aria-label="Mapa do Brasil com pins de abrangência">
    <svg class="cv-land" viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid meet">
      <defs>
        <linearGradient id="cvOcean" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#dbeafe"/>
          <stop offset="100%" stop-color="#bfdbfe"/>
        </linearGradient>
        <linearGradient id="cvLand" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#86efac"/>
          <stop offset="100%" stop-color="#16a34a"/>
        </linearGradient>
      </defs>
      <rect width="1000" height="1000" fill="url(#cvOcean)"/>
      <path d="<?= e($land) ?>" fill="url(#cvLand)" stroke="#166534" stroke-width="4" stroke-linejoin="round"/>
    </svg>
    <?php foreach ($pins as $p): ?>
      <span class="cv-pin cv-<?= e($p['kind']) ?>" style="left:<?= e((string)($p['x']/10)) ?>%;top:<?= e((string)($p['y']/10)) ?>%" title="<?= e($p['label'].' — '.$p['detail']) ?>">
        <i></i>
        <em><?= e($p['label']) ?></em>
      </span>
    <?php endforeach; ?>
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
