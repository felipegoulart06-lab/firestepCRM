<?php
$items = $items ?? [];
?>
<div class="page-head">
  <div>
    <h1>Produtos</h1>
    <p>Soluções digitais da plataforma para a sua empresa. Escolha um card e registre interesse.</p>
  </div>
</div>
<?php if (!$items): ?>
  <div class="card"><div class="empty"><b>Nenhum produto disponível no momento.</b><div>Quando a plataforma publicar ofertas, elas aparecem aqui.</div></div></div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($items as $p):
      $img = trim((string)($p['image_url'] ?? ''));
      $link = trim((string)($p['link_url'] ?? ''));
    ?>
      <article class="card product-card">
        <?php if ($img !== '' && preg_match('#^https://#i', $img)): ?>
          <img class="product-card-img" src="<?= e($img) ?>" alt="">
        <?php else: ?>
          <div class="product-card-ph"><?= e(strtoupper(substr((string)$p['title'], 0, 1))) ?></div>
        <?php endif; ?>
        <div class="product-card-body">
          <h2><?= e((string)$p['title']) ?></h2>
          <p><?= e((string)($p['summary'] ?: $p['description'] ?: '')) ?></p>
          <?php if (trim((string)($p['description'] ?? '')) !== '' && trim((string)($p['description'] ?? '')) !== trim((string)($p['summary'] ?? ''))): ?>
            <p class="product-card-more"><?= e((string)$p['description']) ?></p>
          <?php endif; ?>
          <div class="product-card-actions">
            <form method="post" action="/app/produtos/interesse">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
              <button class="btn btn-primary" style="width:100%">Tenho interesse</button>
            </form>
            <?php if ($link !== '' && preg_match('#^https://#i', $link)): ?>
              <a class="btn btn-ghost" style="width:100%" href="<?= e($link) ?>" target="_blank" rel="noopener">Saiba mais</a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
