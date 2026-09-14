<?php
$pending = $pending ?? [];
$approved = $approved ?? [];
?>
<div class="page-head">
  <div>
    <h1>Integrações</h1>
    <p class="subtitle">Pedidos de Webhooks dos clientes SaaS. Só o Admin Master libera o acesso ao painel de cada um.</p>
  </div>
</div>

<section class="card" style="padding:18px;margin-bottom:16px">
  <h2 style="margin:0 0 6px;font-size:16px">Aguardando aprovação</h2>
  <p class="muted" style="margin:0 0 12px">O cliente já pediu. Enquanto você não aprovar, o painel dele permanece bloqueado nesta área.</p>
  <?php if (!$pending): ?>
    <p class="muted" style="margin:0">Nenhuma solicitação pendente.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Negócio</th><th>Profissional</th><th>Login</th><th>Pedido em</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pending as $t): ?>
        <tr>
          <td><b><?= e($t['business_name']) ?></b></td>
          <td><?= e($t['name']) ?></td>
          <td><code><?= e($t['login_username'] ?? '—') ?></code></td>
          <td><?= e(date('d/m/Y H:i', strtotime((string)$t['webhook_requested_at']))) ?></td>
          <td>
            <form method="post" action="/master/clientes/webhook-access" class="row-actions">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($t['id']) ?>">
              <input type="hidden" name="action" value="approve">
              <button class="btn btn-primary">Aprovar integração</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<section class="card" style="padding:18px">
  <h2 style="margin:0 0 6px;font-size:16px">Já liberadas</h2>
  <p class="muted" style="margin:0 0 12px">Cada token e endpoint vale só para aquele cliente. Remover corta o acesso imediatamente.</p>
  <?php if (!$approved): ?>
    <p class="muted" style="margin:0">Nenhuma integração liberada ainda.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Negócio</th><th>Login</th><th>Liberado em</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($approved as $t): ?>
        <tr>
          <td><b><?= e($t['business_name']) ?></b></td>
          <td><code><?= e($t['login_username'] ?? '—') ?></code></td>
          <td><?= !empty($t['webhook_approved_at']) ? e(date('d/m/Y H:i', strtotime((string)$t['webhook_approved_at']))) : '—' ?></td>
          <td>
            <form method="post" action="/master/clientes/webhook-access" class="row-actions" onsubmit="return confirm('Remover o acesso a Webhooks deste cliente?')">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($t['id']) ?>">
              <input type="hidden" name="action" value="revoke">
              <button class="btn btn-danger">Remover acesso</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
