<?php
$terms = terms_of($tenant);
$period = (int)($_GET['period'] ?? 30);
$period = in_array($period, [7, 30, 90], true) ? $period : 30;
$maxSource = max(1, ...array_map(fn($row) => (int)$row['total'], $sources));
$conversion = $requestTotal > 0 ? round(($scheduledRequests / $requestTotal) * 100) : 0;
$attendance = $appointmentTotal > 0 ? round(($doneAppointments / $appointmentTotal) * 100) : 0;
?>
<div class="page-head">
  <div>
    <h1>Métricas do negócio</h1>
    <p>Entenda de onde vêm seus contatos e como eles avançam até o atendimento.</p>
  </div>
  <div class="tabs">
    <?php foreach ([7=>'7 dias',30=>'30 dias',90=>'90 dias'] as $days=>$label): ?>
      <a class="<?= $period===$days?'active':'' ?>" href="/app/metricas?period=<?= $days ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="grid g4">
  <div class="card stat card-hover">
    <div class="stat-icon"><?= icon('users') ?></div>
    <span class="stat-label">Novos <?= e(lower($terms['clients'])) ?></span>
    <b><?= $newClients ?></b>
    <div class="stat-foot">Cadastros nos últimos <?= $period ?> dias</div>
  </div>
  <div class="card stat card-hover">
    <div class="stat-icon"><?= icon('inbox') ?></div>
    <span class="stat-label">Solicitações recebidas</span>
    <b><?= $requestTotal ?></b>
    <div class="stat-foot">Pedidos vindos de todos os canais</div>
  </div>
  <div class="card stat card-hover">
    <div class="stat-icon"><?= icon('arrow-up') ?></div>
    <span class="stat-label">Conversão em agenda</span>
    <b><?= $conversion ?>%</b>
    <div class="stat-foot"><?= $scheduledRequests ?> solicitações agendadas</div>
  </div>
  <div class="card stat card-hover">
    <div class="stat-icon"><?= icon('calendar') ?></div>
    <span class="stat-label">Taxa de conclusão</span>
    <b><?= $attendance ?>%</b>
    <div class="stat-foot"><?= $doneAppointments ?> de <?= $appointmentTotal ?> finalizados</div>
  </div>
</div>

<div class="grid g2" style="margin-top:14px">
  <section class="card">
    <div class="section-head"><h2>Origem dos contatos</h2><span class="badge" style="background:#f2f4f7;color:#475467"><?= $period ?> dias</span></div>
    <div style="padding:14px 18px 18px">
      <?php if (!$sources): ?>
        <div class="empty"><div class="empty-icon"><?= icon('chart',22) ?></div><b>Ainda não há dados de origem</b><div>Novos contatos aparecerão aqui.</div></div>
      <?php else: foreach ($sources as $source): $pct = round(((int)$source['total']/$maxSource)*100); ?>
        <div class="metric-row">
          <span><?= e($source['source'] ?: 'Não informado') ?></span>
          <div class="progress"><i style="width:<?= $pct ?>%"></i></div>
          <b style="text-align:right"><?= (int)$source['total'] ?></b>
        </div>
      <?php endforeach; endif; ?>
      <p style="font-size:12px;color:#667085;margin:14px 0 0">A origem mostra o canal informado no cadastro ou recebido pela integração: site, WhatsApp, Instagram, indicação e outros.</p>
    </div>
  </section>

  <section class="card">
    <div class="section-head"><h2>Funil de solicitações</h2><span class="badge" style="background:#ecfdf3;color:#027a48"><?= $conversion ?>% convertidas</span></div>
    <div style="padding:16px 18px">
      <?php
      $funnel = [
          ['Recebidas', $requestTotal, '#2563eb'],
          ['Em contato', $contactRequests, '#7f56d9'],
          ['Agendadas', $scheduledRequests, '#12b76a'],
          ['Finalizadas', $finishedRequests, '#667085'],
      ];
      $maxFunnel = max(1, $requestTotal);
      foreach ($funnel as [$label,$value,$color]): ?>
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px"><span><?= $label ?></span><b><?= $value ?></b></div>
          <div class="progress"><i style="width:<?= round(($value/$maxFunnel)*100) ?>%;background:<?= $color ?>"></i></div>
        </div>
      <?php endforeach; ?>
      <p style="font-size:12px;color:#667085;margin:5px 0 0">Conversão = solicitações que chegaram ao status “Agendada” ÷ total recebido no período.</p>
    </div>
  </section>
</div>

<div class="grid g2" style="margin-top:14px">
  <section class="card">
    <div class="section-head"><h2>Serviços mais procurados</h2></div>
    <div style="padding:10px 18px">
      <?php if (!$topServices): ?><div class="empty">Sem serviços no período.</div><?php endif; ?>
      <?php foreach ($topServices as $index=>$service): ?>
        <div style="display:flex;gap:12px;align-items:center;padding:10px 0;border-bottom:1px solid #f2f4f7">
          <span class="badge" style="background:#f2f4f7;color:#475467"><?= $index+1 ?></span>
          <span style="flex:1"><?= e($service['name'] ?: 'Sem serviço definido') ?></span>
          <b><?= (int)$service['total'] ?></b>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <div class="section-head"><h2>Leitura rápida</h2></div>
    <div class="grid" style="padding:16px 18px">
      <div class="insight"><?= icon('chart') ?><div><b>Canal principal</b><div style="color:#667085"><?= e($sources[0]['source'] ?? 'Ainda sem dados suficientes') ?><?= isset($sources[0]) ? ' trouxe '.$sources[0]['total'].' contatos.' : '.' ?></div></div></div>
      <div class="insight"><?= icon('calendar') ?><div><b>Agenda</b><div style="color:#667085"><?= $appointmentTotal ?> <?= e(lower($terms['appointments'])) ?> no período; <?= $doneAppointments ?> foram finalizados.</div></div></div>
      <div class="insight"><?= icon('tag') ?><div><b>Atribuição de campanhas</b><div style="color:#667085"><?= $utmCampaigns ?> campanhas UTM identificadas nas integrações.</div></div></div>
    </div>
  </section>
</div>
