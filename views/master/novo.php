<?php
$old = $old ?? [];
$sel = fn(string $k, string $v) => old_fill($old, $k) === $v ? ' selected' : '';
?>
<div class="page-head">
  <div>
    <h1>Criar cliente</h1>
    <p class="subtitle">O Master cadastra o negócio. A senha não é definida aqui — depois você gera o token de acesso uma única vez.</p>
  </div>
  <a class="btn btn-ghost" href="/master/clientes">Voltar</a>
</div>
<form method="post" action="/master/clientes/criar" class="card" style="padding:20px;max-width:760px" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <div class="grid g2">
    <div><label class="label">Nome do profissional</label><input class="input" name="name" required value="<?= e(old_fill($old, 'name')) ?>"></div>
    <div><label class="label">Nome da empresa</label><input class="input" name="business_name" required value="<?= e(old_fill($old, 'business_name')) ?>"></div>
    <div><label class="label">Nome exibido</label><input class="input" name="display_name" required value="<?= e(old_fill($old, 'display_name')) ?>"></div>
    <div><label class="label">Segmento</label>
      <select class="select" name="segment" required>
        <option value="">Selecione</option>
        <?php foreach (all('SELECT slug,name,category FROM segments WHERE '.sql_true('active').' ORDER BY category, name') as $s): ?>
          <option value="<?= e($s['slug']) ?>"<?= $sel('segment', $s['slug']) ?>><?= e(($s['category'] ?? '').' · '.$s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php br_document_fields('document', old_fill($old, 'document') ?: null, true, 'document_kind', $old); ?>
    <div><label class="label">E-mail de acesso</label><input class="input" type="email" name="email" required placeholder="contato@empresa.com" value="<?= e(old_fill($old, 'email')) ?>"></div>
    <div><label class="label">Telefone</label><input class="input" name="phone" required inputmode="tel" value="<?= e(old_fill($old, 'phone')) ?>"></div>
    <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" inputmode="tel" placeholder="Opcional" value="<?= e(old_fill($old, 'whatsapp')) ?>"></div>
    <div><label class="label">Cidade</label><input class="input" name="city" required value="<?= e(old_fill($old, 'city')) ?>"></div>
    <div><label class="label">Estado</label><input class="input" name="state" required maxlength="2" placeholder="UF" style="text-transform:uppercase" value="<?= e(old_fill($old, 'state')) ?>"></div>
    <div>
      <label class="label">Usuário de login</label>
      <input class="input" name="username" required pattern="[a-zA-Z0-9._-]{3,40}" placeholder="ex.: advocacia.santos" value="<?= e(old_fill($old, 'username')) ?>">
      <small style="color:#667085">Não use e-mail. Não use o login do Admin Master.</small>
    </div>
    <div><label class="label">Status</label>
      <select class="select" name="status" required>
        <?php foreach (TENANT_STATUS as $code=>$label): ?>
          <option value="<?= e($code) ?>"<?= $sel('status', $code) ?: ($code==='ACTIVE' && old_fill($old,'status')==='' ? ' selected' : '') ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label class="label">Cor principal</label><input class="input" type="color" name="primary_color" value="<?= e(old_fill($old, 'primary_color', '#2563eb')) ?>"></div>
  </div>
  <p style="color:#667085;font-size:13px">WhatsApp e cor principal são opcionais. Os demais campos são obrigatórios. Não cadastre senha nesta tela.</p>
  <p><button class="btn btn-primary">Criar cliente</button></p>
</form>
