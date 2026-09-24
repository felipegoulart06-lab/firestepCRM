<?php
$cycles = billing_cycles();
$platform = $platform ?? billing_platform();
?>
<div class="page-head">
  <div>
    <h1>Planos e pagamentos</h1>
    <p class="subtitle">Cada empresa ganha 30 dias grátis. Depois o painel dela só volta com o plano pago. O Comunicador soma R$ 40,00 na mensalidade.</p>
  </div>
</div>

<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><span>Em teste</span><b><?= (int)$stats['trial'] ?></b></div>
  <div class="card stat"><span>Pagos</span><b><?= (int)$stats['active'] ?></b></div>
  <div class="card stat"><span>Aguardando pagamento</span><b><?= (int)$stats['pending'] ?></b></div>
  <div class="card stat"><span>Bloqueados</span><b><?= (int)$stats['past_due'] ?></b></div>
  <div class="card stat"><span>MRR estimado</span><b><?= e(money((float)$stats['mrr'])) ?></b></div>
</div>

<?php if (!empty($stats['open'])): ?>
<section class="card table-wrap" style="margin-bottom:16px">
  <h3 style="padding:16px 16px 0;margin:0">Pedidos em aberto</h3>
  <table class="data">
    <thead><tr><th>Empresa</th><th>Plano</th><th>Valor</th><th>Pedido</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($stats['open'] as $inv):
      $c = $cycles[$inv['cycle']] ?? null;
    ?>
      <tr>
        <td><b><?= e($inv['business_name']) ?></b><div class="muted" style="font-size:12px"><?= e($inv['email']) ?></div></td>
        <td><?= e($c['name'] ?? $inv['cycle']) ?><?= !empty($inv['communicate_addon']) ? ' + Comunicador' : '' ?></td>
        <td><?= e(money((float)$inv['amount'])) ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime((string)$inv['created_at']))) ?></td>
        <td>
          <div class="table-actions">
            <form method="post" action="/master/planos/confirmar">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($inv['id']) ?>">
              <button class="btn btn-primary">Confirmar pagamento</button>
            </form>
            <form method="post" action="/master/planos/recusar">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($inv['id']) ?>">
              <button class="btn btn-ghost">Recusar</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<div class="plan-board" style="margin-bottom:18px">
<?php foreach ($cycles as $p): ?>
  <div class="card plan-card<?= $p['slug']==='semiannual' ? ' is-featured' : '' ?>">
    <div class="plan-card-top" style="background:<?= e($p['color']) ?>">
      <span class="plan-kicker"><?= e($p['kicker']) ?></span>
      <strong><?= e($p['name']) ?></strong>
      <em><?= e(money($p['monthly'])) ?>/mês</em>
    </div>
    <div class="plan-card-body">
      <p class="muted" style="margin:0"><?= e($p['hint']) ?> Sem Comunicador o período sai <?= e(money(billing_quote($p['slug'], false)['amount'])) ?>. Com Comunicador, <?= e(money(billing_quote($p['slug'], true)['amount'])) ?>.</p>
    </div>
  </div>
<?php endforeach; ?>
</div>

<section class="card" style="padding:16px;margin-bottom:16px;max-width:720px">
  <h3 style="margin:0 0 8px">Instruções de pagamento</h3>
  <p class="muted">Aparecem na tela de plano da empresa. PIX é opcional.</p>
  <form method="post" action="/master/planos/instrucoes">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">Chave PIX</label>
    <input class="input" name="pix" value="<?= e($platform['pix']) ?>" placeholder="CPF, e-mail, telefone ou chave aleatória">
    <label class="label" style="margin-top:12px">Texto de orientação</label>
    <textarea class="textarea" name="instructions" rows="3" maxlength="600"><?= e($platform['instructions']) ?></textarea>
    <p style="margin:14px 0 0"><button class="btn btn-primary">Salvar instruções</button></p>
  </form>
</section>

<div class="card table-wrap">
  <table class="data">
    <thead><tr><th>Empresa</th><th>Situação</th><th>Teste até</th><th>Pago até</th><th>Plano</th><th>Comunicador</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $t):
      $snap = billing_of($t);
      $lab = billing_status_label($snap['status']);
      $cycleName = $cycles[$snap['cycle']]['name'] ?? '—';
    ?>
      <tr>
        <td>
          <b><?= e($t['business_name']) ?></b>
          <div class="muted" style="font-size:12px"><?= e($t['login_email'] ?? $t['email']) ?></div>
        </td>
        <td><span class="badge" style="background:<?= e($lab[2]) ?>;color:<?= e($lab[1]) ?>"><?= e($lab[0]) ?></span></td>
        <td><?= e(date('d/m/Y', strtotime((string)$snap['trial_ends_at']))) ?><?php if ($snap['trial']): ?> <span class="muted">(<?= (int)$snap['trial_days'] ?>d)</span><?php endif; ?></td>
        <td><?= $snap['paid_until'] ? e(date('d/m/Y', strtotime((string)$snap['paid_until']))) : '—' ?></td>
        <td><?= e($cycleName) ?></td>
        <td><?= $snap['communicate'] ? 'Sim' : 'Não' ?></td>
        <td>
          <div class="table-actions">
            <a class="btn btn-ghost" href="/master/clientes/ficha?id=<?= e($t['id']) ?>">Ficha</a>
            <form method="post" action="/master/planos/liberar">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
              <input type="hidden" name="cycle" value="monthly">
              <input type="hidden" name="communicate" value="<?= !empty($t['communicate_addon']) || $snap['requested_addon'] ? '1' : '0' ?>">
              <button class="btn btn-ghost" title="Confirma 1 mês agora, como se o pagamento tivesse entrado">Liberar 1 mês</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
