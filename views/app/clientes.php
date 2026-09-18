<?php $terms = terms_of($tenant); $q = trim($_GET['q'] ?? ''); $st = $_GET['s'] ?? 'ALL'; ?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <div><h1><?= e($terms['clients']) ?></h1><p style="color:#667085">Base de relacionamento do seu negócio.</p></div>
  <a class="btn btn-primary" href="/app/clientes/novo"><?= icon('plus') ?> Novo <?= e(lower($terms['client'])) ?></a>
</div>
<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0">
  <input class="input" name="q" value="<?= e($q) ?>" placeholder="Buscar <?= e(lower($terms['client'])) ?>..." style="max-width:360px">
  <a class="btn <?= $st==='ALL'?'btn-primary':'btn-ghost' ?>" href="/app/clientes">Todos</a>
  <a class="btn <?= $st==='ACTIVE'?'btn-primary':'btn-ghost' ?>" href="/app/clientes?s=ACTIVE">Ativos</a>
  <a class="btn <?= $st==='INACTIVE'?'btn-primary':'btn-ghost' ?>" href="/app/clientes?s=INACTIVE">Inativos</a>
</form>
<div class="card table-wrap">
<?php if (!$rows): ?>
  <div class="empty"><b>Nenhum cadastro encontrado.</b><p><a class="btn btn-primary" href="/app/clientes/novo">+ Novo</a></p></div>
<?php else: ?>
<table class="data">
<thead><tr><th>Nome</th><th>Telefone</th><th>WhatsApp</th><th>E-mail</th><th>Último</th><th>Próximo</th><th>Status</th><th>Ações</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><a href="/app/clientes/ver?id=<?= e($r['id']) ?>"><b><?= e($r['name']) ?></b></a></td>
  <td><?= e(phone_fmt($r['phone'])) ?></td>
  <td><?= e(phone_fmt($r['whatsapp'])) ?></td>
  <td><?= e($r['email'] ?: '—') ?></td>
  <td><?= e($r['last'] ?: '—') ?></td>
  <td><?= e($r['next'] ?: '—') ?></td>
  <td><?= $r['status']==='ACTIVE' ? badge_appt('CONFIRMED') : badge_appt('DONE') ?></td>
  <td style="white-space:nowrap">
    <a class="btn btn-ghost" href="/app/clientes/ver?id=<?= e($r['id']) ?>">Ver</a>
    <a class="btn btn-ghost" href="/app/clientes/editar?id=<?= e($r['id']) ?>">Editar</a>
    <a class="btn btn-ghost" href="/app/agenda?new=1&amp;client_id=<?= e($r['id']) ?>&amp;from=clientes">Agendar</a>
    <form method="post" action="/app/clientes/excluir" style="display:inline" onsubmit="return confirm('Excluir este cadastro?')">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
      <button class="btn btn-ghost">Excluir</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
