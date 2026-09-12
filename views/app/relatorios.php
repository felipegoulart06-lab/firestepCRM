<?php $terms = terms_of($tenant); ?>
<div class="page-head">
  <div>
    <h1>Relatórios</h1>
    <p>Baixe resumos simples para organização e acompanhamento do negócio.</p>
  </div>
</div>

<div class="grid g3">
  <section class="card card-hover" style="padding:20px">
    <div class="stat-icon" style="position:static"><?= icon('calendar') ?></div>
    <h2 style="font-size:16px;margin:14px 0 5px">Resumo de atendimentos</h2>
    <p style="color:#667085;min-height:42px">Lista horários, <?= e(lower($terms['clients'])) ?>, serviços e status do período.</p>
    <form method="get" action="/app/relatorios/atendimentos.pdf" class="grid">
      <div class="grid g2">
        <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= date('Y-m-01') ?>"></div>
        <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
    </form>
  </section>

  <section class="card card-hover" style="padding:20px">
    <div class="stat-icon" style="position:static"><?= icon('users') ?></div>
    <h2 style="font-size:16px;margin:14px 0 5px">Resumo de <?= e(lower($terms['clients'])) ?></h2>
    <p style="color:#667085;min-height:42px">Cadastros, contatos, origem e quantidade de atendimentos.</p>
    <form method="get" action="/app/relatorios/clientes.pdf" class="grid">
      <div><label class="label">Status</label>
        <select class="select" name="status"><option value="ALL">Todos</option><option value="ACTIVE">Ativos</option><option value="INACTIVE">Inativos</option></select>
      </div>
      <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
    </form>
  </section>

  <section class="card card-hover" style="padding:20px">
    <div class="stat-icon" style="position:static"><?= icon('chart') ?></div>
    <h2 style="font-size:16px;margin:14px 0 5px">Resumo de origens</h2>
    <p style="color:#667085;min-height:42px">Mostra quais canais geraram mais cadastros e solicitações.</p>
    <form method="get" action="/app/relatorios/origens.pdf" class="grid">
      <div><label class="label">Período</label>
        <select class="select" name="period"><option value="30">Últimos 30 dias</option><option value="90">Últimos 90 dias</option><option value="365">Último ano</option></select>
      </div>
      <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
    </form>
  </section>
</div>

<div class="card" style="padding:18px;margin-top:14px">
  <div class="insight">
    <?= icon('file') ?>
    <div><b>Relatórios administrativos</b><div style="color:#667085">Os PDFs contêm somente dados operacionais do CRM. Não incluem informações clínicas ou prontuário.</div></div>
  </div>
</div>
