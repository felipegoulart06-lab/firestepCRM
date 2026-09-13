<?php
$accents = [
    'starter' => ['#2563eb', 'Operação essencial'],
    'pro' => ['#101828', 'Mais usado'],
    'business' => ['#0f766e', 'Alto volume'],
];
?>
<div class="page-head">
  <div>
    <h1>Planos</h1>
    <p class="subtitle">Nomes e descrições internas. O valor com cada cliente continua combinado à parte — não é cobrado automaticamente.</p>
  </div>
</div>
<div class="plan-board">
<?php foreach ($plans as $p):
    $slug = $p['slug'] ?? '';
    $meta = $accents[$slug] ?? ['#344054', 'Personalizado'];
    $on = !empty($p['active']);
    $n = (int)($counts[$slug] ?? 0);
?>
  <form method="post" action="/master/planos/salvar" class="card plan-card<?= $slug==='pro' ? ' is-featured' : '' ?>">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="id" value="<?= e($p['id']) ?>">
    <div class="plan-card-top" style="background:<?= e($meta[0]) ?>">
      <span class="plan-kicker"><?= e($meta[1]) ?></span>
      <strong><?= e($p['name']) ?></strong>
      <em><?= $n === 1 ? '1 cliente neste perfil' : $n.' clientes neste perfil' ?></em>
    </div>
    <div class="plan-card-body">
      <label class="label">Nome interno</label>
      <input class="input" name="name" required value="<?= e($p['name']) ?>">
      <label class="label" style="margin-top:12px">O que cobre</label>
      <textarea class="textarea" name="description" rows="3"><?= e($p['description']) ?></textarea>
      <label class="plan-switch">
        <input type="checkbox" name="active" <?= $on ? 'checked' : '' ?>>
        <span><?= $on ? 'Disponível' : 'Oculto' ?></span>
      </label>
    </div>
    <div class="plan-card-foot">
      <button class="btn btn-primary" style="width:100%">Salvar alterações</button>
    </div>
  </form>
<?php endforeach; ?>
</div>
