<h1>Planos</h1>
<div class="grid" style="max-width:640px">
<?php foreach ($plans as $p): ?>
  <form method="post" action="/master/planos/salvar" class="card" style="padding:16px">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
    <input class="input" name="name" value="<?= e($p['name']) ?>">
    <input class="input" name="description" value="<?= e($p['description']) ?>" style="margin-top:8px">
    <label><input type="checkbox" name="active" <?= $p['active']?'checked':'' ?>> Ativo</label>
    <p><button class="btn btn-primary">Salvar</button></p>
  </form>
<?php endforeach; ?>
</div>
