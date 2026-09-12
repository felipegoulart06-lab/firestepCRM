<h1>Acesso do cliente SaaS</h1>
<p style="color:#667085"><?= e($tenant['business_name']) ?> · <?= e($tenant['name']) ?></p>

<?php if ($issued): ?>
<div class="card" style="padding:20px;max-width:640px;margin-bottom:16px;background:#ecfdf3;border:1px solid #bbf7d0">
  <b>Login pronto. Copie agora — a senha não será mostrada de novo.</b>
  <p style="margin:12px 0 4px"><span class="label">Usuário</span><br><code><?= e($issued['username']) ?></code></p>
  <p style="margin:12px 0 4px"><span class="label">E-mail</span><br><code><?= e($issued['email']) ?></code></p>
  <p style="margin:12px 0 4px"><span class="label">Senha temporária</span><br><code><?= e($issued['password']) ?></code></p>
  <p style="color:#166534;font-size:13px">No primeiro acesso, o usuário deve trocar a senha em Configurações → Conta.</p>
</div>
<?php endif; ?>

<div class="card" style="padding:20px;max-width:640px">
  <h2 style="margin-top:0">Redefinir acesso</h2>
  <p style="color:#667085">Use isto se o cliente esqueceu a senha ou o usuário ficou inválido.</p>
  <form method="post" action="/master/clientes/acesso" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" value="<?= e($tenant['id']) ?>">
    <label class="label">Usuário</label>
    <input class="input" name="username" required pattern="[a-zA-Z0-9._-]{3,40}" value="<?= e($admin['username'] ?? '') ?>">
    <div style="height:12px"></div>
    <label class="label">E-mail</label>
    <input class="input" type="email" name="email" required value="<?= e($admin['email'] ?? $tenant['email']) ?>">
    <div style="height:12px"></div>
    <label class="label">Nova senha temporária</label>
    <input class="input" type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Deixe em branco para gerar automaticamente">
    <p><button class="btn btn-primary">Salvar novo acesso</button>
      <a class="btn btn-ghost" href="/master/clientes">Voltar</a></p>
  </form>
</div>
