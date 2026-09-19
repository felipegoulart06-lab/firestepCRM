<?php
$terms = terms_of($tenant);
$period = (int)($period ?? 30);
$accent = (string)($accent ?? '#2563eb');
$periodOpts = [1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '12 meses'];
$deltaHtml = static function (array $d): string {
    $cls = !empty($d['up']) ? 'up' : 'down';
    return '<span class="mx-delta '.$cls.'">'.e((string)($d['label'] ?? '0%')).'</span>';
};
$months = $months ?? [];
$monthLabels = array_column($months, 'label');
$monthAppts = array_column($months, 'appts');
$monthReqs = array_column($months, 'reqs');
$statusMap = $statusMap ?? [];
$activeAppts = (int)($statusMap['CONFIRMED'] ?? 0) + (int)($statusMap['IN_PROGRESS'] ?? 0) + (int)($statusMap['SCHEDULED'] ?? 0);
?>
<div class="page-head mx-head">
  <div>
    <h1>Métricas</h1>
    <p>Indicadores do seu negócio. Recorte: <?= e($periodLabel ?? metrics_period_label($period)) ?>.</p>
  </div>
  <div class="mx-toolbar">
    <div class="tabs">
      <?php foreach ($periodOpts as $days => $label): ?>
        <a class="<?= $period === $days ? 'active' : '' ?>" href="/app/metricas?period=<?= $days ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-ghost mx-refresh" href="/app/metricas?period=<?= $period ?>" title="Atualizar"><?= icon('refresh', 16) ?></a>
  </div>
</div>

<div class="mx-kpis">
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span>Novos <?= e(lower($terms['clients'])) ?></span><?= $deltaHtml($newClientsDelta ?? []) ?></div>
    <b><?= (int)$newClients ?></b>
    <small>Cadastros no recorte</small>
    <?= metrics_sparkline($sparkReqs ?? [0], $accent) ?>
  </article>
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span><?= e($terms['appointments']) ?></span><?= $deltaHtml($appointmentDelta ?? []) ?></div>
    <b><?= (int)$appointmentTotal ?></b>
    <small><?= (int)$doneAppointments ?> finalizados · <?= (int)$activeAppts ?> em aberto</small>
    <?= metrics_sparkline($sparkAppts ?? [0], $accent) ?>
  </article>
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span>Taxa de conclusão</span><?= $deltaHtml($attendanceDelta ?? []) ?></div>
    <b><?= (int)$attendance ?>%</b>
    <small><?= (int)$doneAppointments ?> de <?= (int)$appointmentTotal ?> no período</small>
    <?= metrics_sparkline($sparkDone ?? [0], '#12b76a') ?>
  </article>
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span>Receita</span><?= $deltaHtml($revenueDelta ?? []) ?></div>
    <b><?= e(money((float)($revenue ?? 0))) ?></b>
    <small>Entradas recebidas e faturadas</small>
    <?= metrics_sparkline($sparkAppts ?? [0], '#12b76a') ?>
  </article>
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span>Ticket médio</span></div>
    <b><?= e(money((float)($ticket ?? 0))) ?></b>
    <small>Receita ÷ atendimentos do recorte</small>
  </article>
  <article class="card mx-kpi">
    <div class="mx-kpi-top"><span>Solicitações</span><?= $deltaHtml($requestDelta ?? []) ?></div>
    <b><?= (int)$requestTotal ?></b>
    <small><?= (int)$scheduledRequests ?> convertidas em agenda</small>
    <?= metrics_sparkline($sparkReqs ?? [0], '#7f56d9') ?>
  </article>
</div>

<div class="mx-status card">
  <span>Status da agenda</span>
  <div class="mx-pills">
    <em class="ok"><?= (int)($statusMap['DONE'] ?? 0) ?> finalizados</em>
    <em class="go"><?= (int)$activeAppts ?> ativos</em>
    <em class="wait"><?= (int)($statusMap['WAITING'] ?? 0) ?> aguardando</em>
    <em class="off"><?= (int)($statusMap['CANCELLED'] ?? 0) ?> cancelados</em>
  </div>
</div>

<div class="mx-split">
  <section class="card mx-panel">
    <div class="section-head">
      <h2>Evolução mensal</h2>
      <span class="mx-legend"><i style="background:<?= e($accent) ?>"></i> <?= e($terms['appointments']) ?> <i class="dash" style="background:#7f56d9"></i> Solicitações</span>
    </div>
    <div class="mx-chart-wrap">
      <?= metrics_area_chart($monthLabels, $monthAppts, $monthReqs, $accent, '#7f56d9') ?>
    </div>
  </section>
  <section class="card mx-panel">
    <div class="section-head">
      <h2>Origem dos contatos</h2>
      <span class="badge" style="background:#f2f4f7;color:#475467"><?= e($periodLabel ?? '') ?></span>
    </div>
    <div class="mx-chart-wrap mx-bars">
      <?php if (!$sources): ?>
        <div class="empty"><div class="empty-icon"><?= icon('chart', 22) ?></div><b>Ainda não há dados de origem</b><div>Novos cadastros aparecem aqui.</div></div>
      <?php else: ?>
        <?= metrics_bars($sources, $accent) ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="mx-split">
  <section class="card mx-panel">
    <div class="section-head">
      <h2>Últimos <?= e(lower($terms['appointments'])) ?></h2>
      <span class="badge" style="background:#eff6ff;color:#1d4ed8"><?= count($recentAppts ?? []) ?></span>
    </div>
    <div class="mx-feed">
      <?php if (empty($recentAppts)): ?>
        <div class="empty"><b>Nenhum horário neste recorte.</b></div>
      <?php else: foreach ($recentAppts as $a): ?>
        <a class="mx-feed-row" href="/app/agendamentos?ver=<?= e($a['id']) ?>">
          <div>
            <b><?= e($a['client_name'] ?: 'Cliente') ?></b>
            <small><?= e($a['service_name'] ?: 'Serviço') ?> · <?= e(date('d/m H:i', strtotime($a['starts_at']))) ?></small>
          </div>
          <?= badge_appt((string)$a['status']) ?>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </section>
  <section class="card mx-panel">
    <div class="section-head">
      <h2>Últimas solicitações</h2>
      <span class="badge" style="background:#f2f4f7;color:#475467"><?= count($recentReqs ?? []) ?></span>
    </div>
    <div class="mx-feed">
      <?php if (empty($recentReqs)): ?>
        <div class="empty"><b>Nenhuma solicitação neste recorte.</b></div>
      <?php else: foreach ($recentReqs as $r): ?>
        <a class="mx-feed-row" href="/app/solicitacoes?ver=<?= e($r['id']) ?>">
          <div>
            <b><?= e($r['name']) ?></b>
            <small><?= e(normalize_source($r['source'] ?? '')) ?> · <?= e(date('d/m H:i', strtotime($r['created_at']))) ?></small>
          </div>
          <?= badge_req((string)$r['status']) ?>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </section>
</div>

<div class="mx-split mx-split-bottom">
  <section class="card mx-panel">
    <div class="section-head"><h2>Serviços mais procurados</h2></div>
    <ol class="mx-rank">
      <?php if (empty($topServices)): ?>
        <li class="empty" style="list-style:none">Sem serviços no período.</li>
      <?php else: foreach ($topServices as $i => $service): ?>
        <li>
          <span class="mx-rank-n"><?= $i + 1 ?></span>
          <span class="mx-rank-name"><?= e($service['name'] ?: 'Sem serviço definido') ?></span>
          <b><?= (int)$service['total'] ?></b>
        </li>
      <?php endforeach; endif; ?>
    </ol>
  </section>
  <section class="card mx-panel">
    <div class="section-head">
      <h2>Funil de conversão</h2>
      <span class="badge" style="background:#ecfdf3;color:#027a48"><?= (int)$conversion ?>% convertidas</span>
    </div>
    <div class="mx-funnel">
      <?php
      $funnel = [
          ['Solicitações', (int)$requestTotal, $accent],
          ['Em contato', (int)$contactRequests, '#7f56d9'],
          ['Agendadas', (int)$scheduledRequests, '#2563eb'],
          ['Finalizadas', (int)$finishedRequests, '#12b76a'],
      ];
      $maxFunnel = max(1, (int)$requestTotal);
      foreach ($funnel as [$label, $value, $color]):
          $w = round(($value / $maxFunnel) * 100);
      ?>
        <div class="mx-funnel-row">
          <div class="mx-funnel-lab"><span><?= e($label) ?></span><b><?= $value ?></b></div>
          <div class="progress"><i style="width:<?= $w ?>%;background:<?= e($color) ?>"></i></div>
        </div>
      <?php endforeach; ?>
      <p class="muted" style="margin:10px 0 0">Conversão = solicitações agendadas ÷ total recebido. <?= (int)($utmCampaigns ?? 0) ?> campanhas UTM no período.</p>
    </div>
  </section>
</div>
