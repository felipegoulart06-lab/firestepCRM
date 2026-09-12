<?php $terms = terms_of($tenant); $first = explode(' ', $user['name'])[0]; ?>
<div class="page-head">
  <div><h1><?= greeting() ?>, <?= e($first) ?></h1><p>Visão rápida do seu negócio em <?= date('d/m/Y') ?>.</p></div>
  <div class="quick-actions">
    <a class="btn btn-ghost" href="/app/clientes/novo"><?= icon('users') ?> Novo <?= e(lower($terms['client'])) ?></a>
    <a class="btn btn-primary" href="/app/agenda?new=1"><?= icon('plus') ?> Novo <?= e(lower($terms['appointment'])) ?></a>
  </div>
</div>
<div class="grid g4">
  <div class="card stat card-hover"><div class="stat-icon"><?= icon('calendar') ?></div><span class="stat-label"><?= e($terms['appointments']) ?> hoje</span><b><?= (int)$todayCount ?></b><div class="stat-foot">Horários não cancelados</div></div>
  <div class="card stat card-hover"><div class="stat-icon"><?= icon('list') ?></div><span class="stat-label">Próximos 7 dias</span><b><?= (int)$weekCount ?></b><div class="stat-foot">Planejamento da semana</div></div>
  <div class="card stat card-hover"><div class="stat-icon"><?= icon('inbox') ?></div><span class="stat-label">Novas solicitações</span><b><?= (int)$newReq ?></b><div class="stat-foot">Aguardando seu contato</div></div>
  <div class="card stat card-hover"><div class="stat-icon"><?= icon('users') ?></div><span class="stat-label"><?= e($terms['clients']) ?> cadastrados</span><b><?= (int)$cliCount ?></b><div class="stat-foot">Base total do negócio</div></div>
</div>
<div class="grid dash" style="grid-template-columns:1.35fr 1fr;margin-top:14px">
  <section class="card">
    <div class="section-head"><h2>Próximos <?= e(lower($terms['appointments'])) ?></h2><a class="btn btn-soft" href="/app/agenda">Abrir agenda</a></div>
    <div style="padding:5px 18px 12px">
    <?php if (!$upcoming): ?>
      <div class="empty"><div class="empty-icon"><?= icon('calendar',22) ?></div><b>Seu dia está livre por enquanto.</b><div>Quando houver horários, eles aparecerão aqui.</div><p><a class="btn btn-primary" href="/app/agenda?new=1"><?= icon('plus') ?> Criar <?= e(lower($terms['appointment'])) ?></a></p></div>
    <?php else: foreach ($upcoming as $a): ?>
      <div style="display:grid;grid-template-columns:58px 1fr auto;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #f0f2f5">
        <strong style="font-size:15px"><?= e(substr($a['starts_at'],11,5)) ?></strong>
        <div><b><?= e($a['client_name']) ?></b><div style="font-size:13px;color:#667085"><?= e($a['service_name'] ?: 'Atendimento') ?></div></div>
        <?= badge_appt($a['status']) ?>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="section-head"><h2>Solicitações recentes</h2><a class="btn btn-soft" href="/app/solicitacoes">Ver todas</a></div>
    <div style="padding:5px 18px 12px">
    <?php if (!$recentReq): ?>
      <div class="empty"><div class="empty-icon"><?= icon('inbox',22) ?></div><b>Nenhuma solicitação recebida.</b><div>Pedidos do seu site aparecerão aqui.</div></div>
    <?php else: foreach ($recentReq as $r): ?>
      <div style="display:flex;gap:10px;align-items:center;padding:12px 0;border-bottom:1px solid #f0f2f5">
        <div class="avatar"><?= e(strtoupper(substr($r['name'],0,1))) ?></div>
        <div style="flex:1"><b><?= e($r['name']) ?></b><div style="font-size:12px;color:#667085"><?= e($r['service_name'] ?: 'Serviço a definir') ?> · <?= e($r['source']) ?></div></div>
        <?= badge_req($r['status']) ?>
      </div>
    <?php endforeach; endif; ?>
    </div>
  </section>
</div>
<div class="grid g3" style="margin-top:14px">
  <a class="card card-hover insight" href="/app/metricas"><?= icon('chart') ?><div><b>Entenda seus resultados</b><div style="color:#667085">Origens, conversão e serviços mais procurados.</div></div></a>
  <a class="card card-hover insight" href="/app/relatorios"><?= icon('file') ?><div><b>Baixe seus relatórios</b><div style="color:#667085">Resumos organizados em PDF.</div></div></a>
  <a class="card card-hover insight" href="/app/webhooks"><?= icon('webhook') ?><div><b>Conecte seu site</b><div style="color:#667085">Receba contatos automaticamente no CRM.</div></div></a>
</div>
