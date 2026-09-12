<?php $segments = require ROOT . '/app/segments.php'; ?>
<h1>Criar cliente</h1>
<p style="color:#667085">O Master cria o tenant e o login do painel SaaS. A senha temporária aparece uma vez depois de salvar.</p>
<form method="post" action="/master/clientes/criar" class="card" style="padding:20px;max-width:760px" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <div class="grid g2">
    <div><label class="label">Nome do profissional</label><input class="input" name="name" required></div>
    <div><label class="label">Nome da empresa</label><input class="input" name="business_name" required></div>
    <div><label class="label">Segmento</label>
      <select class="select" name="segment">
        <?php foreach ($segments as $slug=>$s): ?><option value="<?= e($slug) ?>"><?= e($s['category'].' · '.$s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div><label class="label">CPF/CNPJ</label><input class="input" name="document"></div>
    <div><label class="label">E-mail de acesso</label><input class="input" type="email" name="email" required placeholder="contato@empresa.com"></div>
    <div><label class="label">Telefone</label><input class="input" name="phone"></div>
    <div><label class="label">WhatsApp</label><input class="input" name="whatsapp"></div>
    <div><label class="label">Cidade</label><input class="input" name="city"></div>
    <div><label class="label">Estado</label><input class="input" name="state"></div>
    <div>
      <label class="label">Usuário de login</label>
      <input class="input" name="username" required pattern="[a-zA-Z0-9._-]{3,40}" placeholder="ex.: advocacia.santos">
      <small style="color:#667085">Não use e-mail. Não use o login do Admin Master.</small>
    </div>
    <div><label class="label">Plano</label>
      <select class="select" name="plan"><option value="starter">Starter</option><option value="pro">Pro</option><option value="business">Business</option></select>
    </div>
    <div>
      <label class="label">Senha temporária</label>
      <input class="input" type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Mínimo 10 caracteres">
    </div>
    <div>
      <label class="label">Confirmar senha</label>
      <input class="input" type="password" name="password_confirm" minlength="10" autocomplete="new-password">
    </div>
    <div><label class="label">Status</label>
      <select class="select" name="status">
        <option value="ACTIVE">Ativo</option>
        <option value="SUSPENDED">Suspenso</option>
        <option value="OVERDUE">Inadimplente</option>
        <option value="CANCELLED">Cancelado</option>
      </select>
    </div>
    <div><label class="label">Nome exibido</label><input class="input" name="display_name"></div>
    <div><label class="label">Cor principal</label><input class="input" type="color" name="primary_color" value="#2563eb"></div>
  </div>
  <p style="color:#667085;font-size:13px">Se deixar a senha em branco, o sistema gera uma senha forte automaticamente.</p>
  <p><button class="btn btn-primary">Criar acesso SaaS</button></p>
</form>
