<div style="display:flex;justify-content:space-between"><h1>Clientes SaaS</h1><a class="btn btn-primary" href="/master/clientes/novo">+ Criar cliente</a></div>
<div class="card table-wrap">
<table class="data">
<thead><tr><th>Negócio</th><th>Profissional</th><th>Login</th><th>E-mail</th><th>Plano</th><th>Webhooks</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($tenants as $t): ?>
<tr>
  <td><?= e($t['business_name']) ?></td>
  <td><?= e($t['name']) ?></td>
  <td><code><?= e($t['login_username'] ?? '—') ?></code></td>
  <td><?= e($t['login_email'] ?? $t['email']) ?></td>
  <td><?= e($t['plan']) ?></td>
  <td>
    <?php if (!empty($t['webhook_access'])): ?>
      <span class="badge" style="background:#dcfce7;color:#166534">Liberado</span>
      <form method="post" action="/master/clientes/webhook-access" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($t['id']) ?>"><input type="hidden" name="action" value="revoke">
        <button class="btn btn-ghost">Remover</button>
      </form>
    <?php elseif (!empty($t['webhook_requested_at'])): ?>
      <span class="badge" style="background:#fffaeb;color:#b54708">Solicitado</span>
      <form method="post" action="/master/clientes/webhook-access" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($t['id']) ?>"><input type="hidden" name="action" value="approve">
        <button class="btn btn-primary">Aprovar</button>
      </form>
    <?php else: ?>
      <span style="color:#98a2b3;font-size:12px">Não solicitado</span>
    <?php endif; ?>
  </td>
  <td><span class="badge"><?= e(TENANT_STATUS[$t['status']] ?? $t['status']) ?></span></td>
  <td style="white-space:nowrap">
    <?php $locked = saas_access_confirmed($t); ?>
    <?php if ($locked): ?>
      <span class="badge" style="background:#ecfdf3;color:#166534">Acesso confirmado</span>
      <button type="button" class="btn btn-primary" disabled title="O cliente já entrou e trocou a senha">Acesso</button>
      <button type="button" class="btn btn-ghost" disabled title="O cliente já entrou e trocou a senha"><?= $t['status']==='ACTIVE'?'Suspender':'Ativar' ?></button>
    <?php else: ?>
      <a class="btn btn-primary" href="/master/clientes/acesso?id=<?= e($t['id']) ?>">Acesso</a>
      <form method="post" action="/master/clientes/status" style="display:inline">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($t['id']) ?>">
        <input type="hidden" name="status" value="<?= $t['status']==='ACTIVE'?'SUSPENDED':'ACTIVE' ?>">
        <button class="btn btn-ghost"><?= $t['status']==='ACTIVE'?'Suspender':'Ativar' ?></button>
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
