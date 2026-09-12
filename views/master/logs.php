<h1>Logs</h1>
<div class="card table-wrap"><table class="data">
<thead><tr><th>Data</th><th>Ação</th><th>Entidade</th><th>Tenant</th></tr></thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr><td><?= e($l['created_at']) ?></td><td><?= e($l['action']) ?></td><td><?= e($l['entity']) ?></td><td><?= e($l['tenant_id'] ?: '—') ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
