<?php
$bill = billing_of($tenant);
$cycles = billing_cycles();
$platform = billing_platform();
$locked = !empty($bill['locked']);
$addonOn = $bill['requested_addon'] || (!empty($tenant['communicate_addon']) && !$locked);
$selected = $bill['requested_cycle'] ?: ($bill['cycle'] ?: 'semiannual');
$open = $bill['open_invoice'] ?? null;
?>
<div class="page-head">
  <div>
    <h1><?= $locked ? 'Assine para continuar' : 'Plano e pagamento' ?></h1>
    <p class="subtitle">
      <?php if ($bill['trial']): ?>
        Teste grátis: restam <?= (int)$bill['trial_days'] ?> dia<?= (int)$bill['trial_days'] === 1 ? '' : 's' ?> (até <?= e(date('d/m/Y', strtotime((string)$bill['trial_ends_at']))) ?>). Depois a plataforma pede o plano pago.
      <?php elseif ($bill['paid']): ?>
        Assinatura ativa até <?= e(date('d/m/Y', strtotime((string)$bill['paid_until']))) ?><?= $bill['cycle'] && isset($cycles[$bill['cycle']]) ? ' · '.$cycles[$bill['cycle']]['name'] : '' ?><?= !empty($tenant['communicate_addon']) ? ' · Comunicador incluso' : '' ?>.
      <?php else: ?>
        Os 30 dias grátis acabaram. Escolha o plano para voltar a usar o painel.
      <?php endif; ?>
    </p>
  </div>
</div>

<?php if ($open): ?>
<div class="card" style="padding:16px;margin-bottom:14px;border-color:#f79009">
  <b>Pedido enviado · <?= e(money((float)$open['amount'])) ?></b>
  <p class="muted" style="margin:6px 0 0">O Admin Master vê esta cobrança no painel dele. Quando o pagamento for confirmado, o acesso é liberado automaticamente para o período contratado.</p>
</div>
<?php endif; ?>

<form method="post" action="/app/assinatura/solicitar">
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <div class="plan-board">
    <?php foreach ($cycles as $slug => $p):
      $quote = billing_quote($slug, false);
      $quoteAdd = billing_quote($slug, true);
    ?>
    <label class="card plan-card<?= $slug==='semiannual' ? ' is-featured' : '' ?>">
      <div class="plan-card-top" style="background:<?= e($p['color']) ?>">
        <span class="plan-kicker"><?= e($p['kicker']) ?></span>
        <strong><?= e($p['name']) ?></strong>
        <em><?= e($p['hint']) ?></em>
      </div>
      <div class="plan-card-body">
        <div class="billing-price"><?= e(money($p['monthly'])) ?><small>/mês</small></div>
        <p class="muted" style="margin:8px 0 0"><?= (int)$p['months'] === 1 ? 'Cobrado agora: '.money($quote['amount']) : 'Cobrado agora: '.money($quote['amount']).' ('.(int)$p['months'].' meses)' ?></p>
        <p class="muted" style="margin:6px 0 0">Com Comunicador: <?= e(money($quoteAdd['monthly'])) ?>/mês · <?= e(money($quoteAdd['amount'])) ?> no período</p>
        <label class="plan-switch">
          <input type="radio" name="cycle" value="<?= e($slug) ?>" <?= $selected === $slug ? 'checked' : '' ?> required>
          <span>Quero este plano</span>
        </label>
      </div>
    </label>
    <?php endforeach; ?>
  </div>
  <div class="card" style="padding:16px;margin:16px 0;max-width:1100px">
    <label class="plan-switch" style="margin:0">
      <input type="checkbox" name="communicate" value="1" <?= $addonOn ? 'checked' : '' ?>>
      <span>Incluir Comunicador (+ <?= e(money(BILLING_ADDON_COMMUNICATE)) ?> na mensalidade)</span>
    </label>
    <p class="muted" style="margin:8px 0 0">WhatsApp do botão Comunicar, automações e cobrança no chat. No teste grátis o Comunicador já vem liberado; depois dos 30 dias ele só continua com este adicional.</p>
  </div>
  <?php if ($platform['pix'] !== '' || $platform['instructions'] !== ''): ?>
  <div class="card" style="padding:16px;margin-bottom:16px;max-width:1100px">
    <b>Como pagar</b>
    <?php if ($platform['pix'] !== ''): ?>
      <p style="margin:8px 0 0">PIX: <code><?= e($platform['pix']) ?></code></p>
    <?php endif; ?>
    <?php if ($platform['instructions'] !== ''): ?>
      <p class="muted" style="margin:8px 0 0"><?= e($platform['instructions']) ?></p>
    <?php else: ?>
      <p class="muted" style="margin:8px 0 0">Envie o comprovante para o Admin Master. A liberação é automática no painel assim que ele confirmar.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <button class="btn btn-primary"><?= $open ? 'Atualizar pedido de plano' : 'Enviar pedido de pagamento' ?></button>
</form>
