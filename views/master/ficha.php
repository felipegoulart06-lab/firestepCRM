<?php
$viewed = !empty($tenant['access_token_viewed_at']);
$confirmed = saas_access_confirmed($admin);
$seg = trim(($segment['category'] ?? '').' · '.($segment['name'] ?? ''), ' ·');
?>
<div class="page-head">
  <div>
    <h1><?= e($tenant['business_name']) ?></h1>
    <p class="subtitle"><?= e($tenant['name']) ?> · <?= e($seg !== '' ? $seg : ($tenant['segment'] ?? '')) ?></p>
  </div>
  <div class="table-actions">
    <a class="btn btn-ghost" href="/master/clientes">Voltar</a>
    <a class="btn btn-primary" href="/master/clientes/dossie?id=<?= e($tenant['id']) ?>" target="_blank" rel="noopener">Dossiê</a>
  </div>
</div>

<?php if ($issued): ?>
<div class="card token-once">
  <b>Token gerado. Copie agora — esta é a única vez.</b>
  <p class="muted" style="margin:8px 0 14px">Se sair desta tela, o token some. O cliente entra com estes dados e é obrigado a trocar a senha.</p>
  <div class="detail-grid">
    <div class="detail-item"><small>Usuário</small><strong><code id="tok-user"><?= e($issued['username']) ?></code></strong></div>
    <div class="detail-item"><small>E-mail</small><strong><code><?= e($issued['email']) ?></code></strong></div>
    <div class="detail-item detail-wide"><small>Token / senha temporária</small><strong><code id="tok-pass"><?= e($issued['password']) ?></code></strong></div>
  </div>
</div>
<?php endif; ?>

<div class="grid dash" style="grid-template-columns:1.3fr .9fr;align-items:start;gap:16px">
  <div class="card" style="padding:20px">
    <h2 style="margin:0 0 12px;font-size:16px">Dados do cliente</h2>
    <div class="detail-grid">
      <div class="detail-item"><small>Profissional</small><strong><?= e($tenant['name']) ?></strong></div>
      <div class="detail-item"><small>Nome exibido</small><strong><?= e($tenant['display_name']) ?></strong></div>
      <div class="detail-item"><small>Documento</small><strong><?= e($tenant['document'] ?: '—') ?></strong></div>
      <div class="detail-item"><small>Status</small><strong><?= e(TENANT_STATUS[$tenant['status']] ?? $tenant['status']) ?></strong></div>
      <div class="detail-item"><small>E-mail</small><strong><?= e($tenant['email']) ?></strong></div>
      <div class="detail-item"><small>Usuário</small><strong><?= e($admin['username'] ?? '—') ?></strong></div>
      <div class="detail-item"><small>Telefone</small><strong><?= e($tenant['phone'] ?: '—') ?></strong></div>
      <div class="detail-item"><small>WhatsApp</small><strong><?= e($tenant['whatsapp'] ?: '—') ?></strong></div>
      <div class="detail-item detail-wide"><small>Cidade</small><strong><?= e(trim(($tenant['city'] ?? '').' / '.($tenant['state'] ?? ''), ' /') ?: '—') ?></strong></div>
    </div>
  </div>
  <div class="card" style="padding:20px">
    <h2 style="margin:0 0 8px;font-size:16px">Token de acesso</h2>
    <?php if ($confirmed): ?>
      <p class="muted">O cliente já entrou e definiu a senha permanente. O token não pode ser gerado de novo.</p>
    <?php elseif ($issued): ?>
      <p class="muted">O token está visível acima. Ao recarregar ou sair, ele não aparece mais.</p>
    <?php elseif ($viewed): ?>
      <p class="muted">O token já foi revelado uma vez e não pode ser visto novamente.</p>
      <button class="btn btn-primary" disabled>Token já visto</button>
    <?php else: ?>
      <p class="muted">Gera o login temporário. É preciso confirmar. Depois da primeira visualização, some para sempre.</p>
      <button type="button" class="btn btn-primary" id="open-token">Gerar token</button>
    <?php endif; ?>
  </div>
</div>

<?php if (!$viewed && !$confirmed): ?>
<div class="token-modal" id="token-modal" hidden>
  <div class="card" style="max-width:420px;width:100%;padding:22px;margin-top:12vh">
    <h2 style="margin:0 0 8px;font-size:18px">Gerar token?</h2>
    <p class="muted">Isso cria o acesso temporário. Você verá usuário e senha só agora. Depois não será possível mostrar de novo.</p>
    <form method="post" action="/master/clientes/token">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($tenant['id']) ?>">
      <label class="token-confirm">
        <input type="checkbox" name="confirm" value="1" required>
        <span>Confirmo que vou copiar o token agora e entregar ao cliente.</span>
      </label>
      <div class="table-actions" style="margin-top:16px">
        <button type="button" class="btn btn-ghost" id="close-token">Cancelar</button>
        <button class="btn btn-primary">Sim, gerar token</button>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  const modal = document.getElementById('token-modal');
  const open = document.getElementById('open-token');
  const close = document.getElementById('close-token');
  if (!modal || !open) return;
  open.addEventListener('click', ()=> { modal.hidden = false; });
  close?.addEventListener('click', ()=> { modal.hidden = true; });
  modal.addEventListener('click', (e)=> { if (e.target === modal) modal.hidden = true; });
})();
</script>
<?php endif; ?>
