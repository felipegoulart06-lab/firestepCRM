<?php
$page = $page ?? 'lancamentos';
$search = $search ?? '';
$items = $items ?? [];
$clients = $clients ?? [];
$meta = [
    'lancamentos' => ['Lançamentos', 'Entradas e saídas já realizadas no caixa.', 'entry', 'Novo lançamento'],
    'receber' => ['Contas a receber', 'Em aberto até o pagamento. Fatura só sai daqui quando você clica em Faturar.', 'receivable', 'Nova conta a receber'],
    'faturado' => ['Faturado', 'Faturas já confirmadas na data acordada com o cliente CPF ou CNPJ.', 'receivable', 'Nova conta a receber'],
    'pagar' => ['Contas a pagar', 'Despesas e boletos do negócio.', 'payable', 'Nova conta a pagar'],
];
$m = $meta[$page];
$novo = !empty($_GET['novo']);
$listPath = finance_pages()[$page][1];
?>
<div class="page-head">
  <div>
    <h1><?= e($m[0]) ?></h1>
    <p><?= e($m[1]) ?></p>
  </div>
  <?php if ($page !== 'faturado'): ?>
    <a class="btn btn-primary" href="<?= e($listPath) ?>?novo=1"><?= icon('plus') ?> <?= e($m[3]) ?></a>
  <?php endif; ?>
</div>
<form method="get" action="<?= e($listPath) ?>" class="card service-toolbar">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Buscar descrição ou cliente...">
  <button class="btn btn-ghost">Filtrar</button>
</form>
<div class="card table-wrap">
<?php if (!$items): ?>
  <div class="empty"><b>Nenhum registro nesta lista.</b><div>Use o botão acima para cadastrar o primeiro.</div></div>
<?php else: ?>
<table class="data">
  <thead>
    <tr>
      <th>Descrição</th>
      <th>Origem</th>
      <th>Atribuição</th>
      <th>Forma</th>
      <th>Valor</th>
      <th>Vencimento</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $row): ?>
    <tr>
      <td>
        <strong><?= e($row['description']) ?></strong>
        <?php if ($page === 'lancamentos'): ?>
          <div style="font-size:12px;color:#667085"><?= $row['flow']==='out' ? 'Saída' : 'Entrada' ?></div>
        <?php endif; ?>
      </td>
      <td><?= e(finance_source_label($row['source_type'] ?? null, $row['source_id'] ?? null)) ?></td>
      <td><?php
        if (($row['source_type'] ?? '') === 'appointment_commission') {
            echo 'Agente: '.e($row['agent_name'] ?: '—');
            if (!empty($row['client_name'])) echo '<div style="font-size:12px;color:#667085">Cliente: '.e($row['client_name']).'</div>';
        } else {
            echo e($row['client_name'] ?: '—');
            if (!empty($row['client_cpf'])) echo ' <span style="color:#667085;font-size:12px">'.e(format_br_document((string)$row['client_cpf'])).'</span>';
        }
      ?></td>
      <td><?= e(finance_pay_label($row['payment_method'] ?? '')) ?></td>
      <td><?= e(money((float)$row['amount'])) ?></td>
      <td><?= e($row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : '—') ?></td>
      <td><?= badge_finance($row['status']) ?></td>
      <td>
        <div class="row-actions">
          <?php $pay = (string)($row['payment_method'] ?? ''); ?>
          <?php if ($row['kind']==='receivable' && $row['status']==='open' && ($pay === 'fatura' || $pay === '')): ?>
            <form method="post" action="/app/financeiro/status"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="status" value="billed"><input type="hidden" name="back" value="<?= e($listPath) ?>"><button class="btn btn-ghost">Faturar</button></form>
          <?php endif; ?>
          <?php if ($row['kind']==='receivable' && $row['status']==='open' && $pay !== 'fatura'): ?>
            <form method="post" action="/app/financeiro/status"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="status" value="paid"><input type="hidden" name="back" value="<?= e($listPath) ?>"><button class="btn btn-ghost">Receber</button></form>
          <?php endif; ?>
          <?php if ($row['kind']==='payable' && $row['status']==='open'): ?>
            <form method="post" action="/app/financeiro/status"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="status" value="paid"><input type="hidden" name="back" value="<?= e($listPath) ?>"><button class="btn btn-ghost">Pagar</button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php if ($novo): ?>
<div class="overlay" role="presentation">
  <form method="post" action="/app/financeiro/salvar" class="card overlay-panel" style="max-width:480px;padding:18px" onclick="event.stopPropagation()" data-finance-pay>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px"><?= e($m[3]) ?></h2>
      <a class="btn btn-ghost" href="<?= e($listPath) ?>">Fechar</a>
    </div>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="kind" value="<?= e($m[2]) ?>">
    <input type="hidden" name="back" value="<?= e($listPath) ?>">
    <label class="label">Descrição</label>
    <input class="input" name="description" required maxlength="180" placeholder="Ex.: mensalidade, aluguel, material...">
    <div class="grid g2" style="margin-top:10px">
      <div>
        <label class="label">Valor</label>
        <input class="input" name="amount" type="number" min="0.01" step="0.01" required>
      </div>
      <div>
        <label class="label" data-due-label><?= $page==='lancamentos' ? 'Data' : 'Vencimento' ?></label>
        <input class="input" name="due_date" type="date" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <?php if ($page === 'lancamentos'): ?>
      <label class="label">Tipo</label>
      <select class="select" name="flow">
        <option value="in">Entrada</option>
        <option value="out">Saída</option>
      </select>
    <?php endif; ?>
    <?php if ($page === 'receber' || $page === 'pagar'): ?>
      <label class="label">Forma de pagamento</label>
      <select class="select" name="payment_method" <?= $page==='receber'?'required':'' ?>>
        <option value="">Selecionar</option>
        <?php foreach (finance_pay_methods() as $k => $lab): ?>
          <?php if ($page==='pagar' && $k==='fatura') continue; ?>
          <option value="<?= e($k) ?>"><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="muted" data-invoice-hint hidden style="margin:8px 0 0">Fatura: a empresa recebe no início do mês, na data combinada com o cliente CPF ou CNPJ. A conta permanece em A receber até o pagamento e o clique em Faturar.</p>
    <?php endif; ?>
    <label class="label">Cliente <span class="onboard-opt" data-client-opt>opcional</span></label>
    <select class="select" name="client_id">
      <option value="">Sem cliente</option>
      <?php foreach ($clients as $c):
        $kind = br_doc_kind_from_value($c['cpf'] ?? '') ?: '';
        $doc = $c['cpf'] ? format_br_document((string)$c['cpf'], $kind ?: null) : '';
      ?>
        <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?><?= $doc ? ' · '.$kind.' '.$doc : '' ?></option>
      <?php endforeach; ?>
    </select>
    <label class="label">Observações</label>
    <textarea class="textarea" name="notes"></textarea>
    <p style="margin:16px 0 0"><button class="btn btn-primary" style="width:100%">Salvar</button></p>
  </form>
</div>
<?php endif; ?>
