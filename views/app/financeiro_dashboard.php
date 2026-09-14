<?php
$page = $page ?? 'dashboard';
$pages = finance_pages();
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$receber = finance_sum($tenant['id'], 'receivable', ['open', 'billed']);
$faturado = finance_sum($tenant['id'], 'receivable', ['billed']);
$pagar = finance_sum($tenant['id'], 'payable', ['open']);
$entradas = finance_sum($tenant['id'], 'entry', ['paid'], 'in', $monthStart, $monthEnd)
    + finance_sum($tenant['id'], 'receivable', ['paid'], 'in', $monthStart, $monthEnd);
$saidas = finance_sum($tenant['id'], 'entry', ['paid'], 'out', $monthStart, $monthEnd)
    + finance_sum($tenant['id'], 'payable', ['paid'], 'out', $monthStart, $monthEnd);
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
  <div class="card stat"><span class="stat-label">A receber</span><b><?= e(money($receber)) ?></b><div class="stat-foot">Em aberto e faturado</div></div>
  <div class="card stat"><span class="stat-label">Faturado</span><b><?= e(money($faturado)) ?></b><div class="stat-foot">Aguardando recebimento</div></div>
  <div class="card stat"><span class="stat-label">A pagar</span><b><?= e(money($pagar)) ?></b><div class="stat-foot">Contas em aberto</div></div>
  <div class="card stat"><span class="stat-label">Saldo do mês</span><b><?= e(money($entradas - $saidas)) ?></b><div class="stat-foot">Entradas <?= e(money($entradas)) ?> · Saídas <?= e(money($saidas)) ?></div></div>
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
