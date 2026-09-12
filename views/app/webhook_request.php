<div class="page-head">
  <div>
    <h1>Webhooks</h1>
    <p>Conecte formulários, sites e ferramentas externas ao seu painel.</p>
  </div>
</div>

<section class="card integration-gate">
  <div class="integration-gate-icon"><?= icon('webhook') ?></div>
  <?php if (!empty($tenant['webhook_requested_at'])): ?>
    <span class="badge" style="background:#fffaeb;color:#b54708">Aguardando aprovação</span>
    <h2>Solicitação enviada</h2>
    <p>O Admin Master recebeu seu pedido. Assim que o acesso for aprovado, endpoints, tokens, configurações e logs ficarão disponíveis aqui.</p>
    <div class="integration-request-date">Solicitado em <?= e(date('d/m/Y \à\s H:i', strtotime($tenant['webhook_requested_at']))) ?></div>
  <?php else: ?>
    <span class="badge" style="background:#f2f4f7;color:#475467">Acesso restrito</span>
    <h2>Solicite acesso às integrações</h2>
    <p>Por segurança, endpoints, tokens e registros de Webhooks precisam ser liberados pelo Admin Master.</p>
    <form method="post" action="/app/webhooks/solicitar">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <button class="btn btn-primary"><?= icon('webhook') ?> Solicitar acesso ao Admin Master</button>
    </form>
  <?php endif; ?>
</section>
