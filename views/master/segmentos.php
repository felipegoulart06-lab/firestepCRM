<h1>Segmentos</h1>
<p style="color:#667085">O mesmo sistema atende todos. O segmento só muda nomes, serviços iniciais e campos.</p>
<div class="card table-wrap"><table class="data">
<thead><tr><th>Segmento</th><th>Categoria</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($items as $s): ?>
<tr><td><?= e($s['name']) ?></td><td><?= e($s['category']) ?></td><td><?= $s['active']?'Ativo':'Inativo' ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
