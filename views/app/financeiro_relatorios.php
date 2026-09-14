<?php $monthStart = date('Y-m-01'); $today = date('Y-m-d'); ?>
<div class="page-head">
  <div>
    <h1>Relatórios financeiros</h1>
    <p>Baixe um resumo do caixa, contas a receber e a pagar.</p>
  </div>
</div>
<section class="card" style="padding:20px;max-width:480px">
  <h2 style="font-size:16px;margin:0 0 8px">Resumo do período</h2>
  <p style="color:#667085">Lista lançamentos, recebíveis e pagamentos no intervalo escolhido.</p>
  <form method="get" action="/app/financeiro/relatorio.pdf" class="grid">
    <div class="grid g2">
      <div><label class="label">De</label><input class="input" type="date" name="from" value="<?= e($monthStart) ?>"></div>
      <div><label class="label">Até</label><input class="input" type="date" name="to" value="<?= e($today) ?>"></div>
    </div>
    <button class="btn btn-primary"><?= icon('download') ?> Baixar PDF</button>
  </form>
</section>
