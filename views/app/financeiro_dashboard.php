<?php
$page = $page ?? 'dashboard';
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$ov = finance_overview($tenant['id'], $monthStart, $monthEnd);
$recent = all("SELECT f.*, c.name client_name FROM finance_entries f
    LEFT JOIN clients c ON c.id=f.client_id AND c.tenant_id=f.tenant_id
    WHERE f.tenant_id=? AND f.status!='cancelled'
    ORDER BY f.created_at DESC LIMIT 8", [$tenant['id']]);
?>
<div class="page-head">
  <div>
    <h1>Financeiro</h1>
    <p>Acompanhe entradas, saídas e o que ainda está em aberto.</p>
  </div>
  <a class="btn btn-primary" href="/app/financeiro/lancamentos?novo=1"><?= icon('plus') ?> Novo lançamento</a>
</div>
<div class="grid g4">
  <div class="card stat"><span class="stat-label">Previsto</span><b><?= e(money($ov['previsto'])) ?></b><div class="stat-foot">Recebíveis (abertos + recebidos)</div></div>
  <div class="card stat"><span class="stat-label">A receber</span><b><?= e(money($ov['receber'])) ?></b><div class="stat-foot">Ainda não entrou no caixa<?= $ov['vencido']>0 ? ' · vencido '.e(money($ov['vencido'])) : '' ?></div></div>
  <div class="card stat"><span class="stat-label">Recebido no mês</span><b><?= e(money($ov['recebido'])) ?></b><div class="stat-foot">Somente valores pagos</div></div>
  <div class="card stat"><span class="stat-label">Saldo do mês</span><b><?= e(money($ov['saldo'])) ?></b><div class="stat-foot">A pagar <?= e(money($ov['pagar'])) ?> · Saídas <?= e(money($ov['saidas'])) ?></div></div>
</div>
<div class="grid g3" style="margin-top:14px">
  <a class="card card-hover insight" href="/app/financeiro/receber"><?= icon('inbox') ?><div><b>Contas a receber</b><div style="color:#667085">O que os clientes ainda devem.</div></div></a>
  <a class="card card-hover insight" href="/app/financeiro/pagar"><?= icon('list') ?><div><b>Contas a pagar</b><div style="color:#667085">Despesas e boletos em aberto.</div></div></a>
  <a class="card card-hover insight" href="/app/financeiro/relatorios"><?= icon('file') ?><div><b>Relatórios</b><div style="color:#667085">Resumo do caixa em PDF.</div></div></a>
</div>
<section class="card" style="margin-top:14px">
  <div class="section-head"><h2>Últimos movimentos</h2><a class="btn btn-soft" href="/app/financeiro/lancamentos">Ver lançamentos</a></div>
  <?php if (!$recent): ?>
    <div class="empty"><b>Nenhum movimento ainda.</b><div>Cadastre um lançamento, uma conta a receber ou a pagar.</div></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Descrição</th><th>Cliente</th><th>Valor</th><th>Status</th><th>Data</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td><strong><?= e($row['description']) ?></strong></td>
            <td><?= e($row['client_name'] ?: '—') ?></td>
            <td><?= $row['flow']==='out' ? '−' : '+' ?><?= e(money((float)$row['amount'])) ?></td>
            <td><?= badge_finance($row['status']) ?></td>
            <td><?= e(date('d/m/Y', strtotime($row['due_date'] ?: $row['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
