<?php
$items = $items ?? [];
$leads = $leads ?? [];
$edit = $edit ?? null;
$old = $old ?? [];
$isEdit = is_array($edit);
$titleVal = (string)($old['title'] ?? ($edit['title'] ?? ''));
$summaryVal = (string)($old['summary'] ?? ($edit['summary'] ?? ''));
$descVal = (string)($old['description'] ?? ($edit['description'] ?? ''));
$linkVal = (string)($old['link_url'] ?? ($edit['link_url'] ?? ''));
$sortVal = (string)($old['sort_order'] ?? ($edit['sort_order'] ?? '0'));
$imageVal = (string)($edit['image_url'] ?? '');
$activeVal = $isEdit ? products_is_on($edit) : true;
if (isset($old['active'])) {
    $activeVal = ($old['active'] ?? '') === '1';
}
?>
<div class="page-head">
  <div>
    <h1>Produtos</h1>
    <p class="subtitle">O que você cadastrar aqui aparece em cards no menu Produtos de todas as empresas.</p>
  </div>
</div>
<div class="grid dash" style="grid-template-columns:minmax(280px,380px) 1fr;align-items:start">
  <form method="post" action="/master/produtos/salvar" class="card" style="padding:20px" enctype="multipart/form-data">
    <h2 style="margin:0 0 6px;font-size:16px"><?= $isEdit ? 'Editar produto' : 'Novo produto' ?></h2>
    <p class="muted" style="margin:0 0 14px">Imagem opcional (JPEG, PNG ou WEBP, até 1 MB) guardada na Cloudflare.</p>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($isEdit): ?>
      <input type="hidden" name="id" value="<?= e((string)$edit['id']) ?>">
      <input type="hidden" name="image_url" value="<?= e($imageVal) ?>">
    <?php endif; ?>
    <label class="label">Nome</label>
    <input class="input" name="title" required maxlength="120" value="<?= e($titleVal) ?>" placeholder="Ex.: Sistema + site de agendamento">
    <label class="label" style="margin-top:12px">Resumo no card</label>
    <textarea class="textarea" name="summary" rows="3" maxlength="280"><?= e($summaryVal) ?></textarea>
    <label class="label" style="margin-top:12px">Descrição</label>
    <textarea class="textarea" name="description" rows="4" maxlength="2000"><?= e($descVal) ?></textarea>
    <label class="label" style="margin-top:12px">Imagem</label>
    <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
    <?php if ($imageVal !== '' && preg_match('#^https://#i', $imageVal)): ?>
      <img class="product-thumb" src="<?= e($imageVal) ?>" alt="">
      <label class="check-row" style="margin-top:8px">
        <input type="checkbox" name="remove_image" value="1">
        Remover imagem atual
      </label>
    <?php endif; ?>
    <label class="label" style="margin-top:12px">Link (opcional)</label>
    <input class="input" name="link_url" value="<?= e($linkVal) ?>" placeholder="https://">
    <label class="label" style="margin-top:12px">Ordem</label>
    <input class="input" name="sort_order" type="number" value="<?= e($sortVal) ?>">
    <label class="check-row" style="margin-top:12px">
      <input type="checkbox" name="active" value="1" <?= $activeVal ? 'checked' : '' ?>>
      Visível no menu Produtos
    </label>
    <p style="margin:16px 0 0;display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-primary"><?= $isEdit ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
      <?php if ($isEdit): ?><a class="btn btn-ghost" href="/master/produtos">Cancelar</a><?php endif; ?>
    </p>
  </form>
  <div>
    <?php if (!$items): ?>
      <div class="card"><div class="empty"><b>Nenhum produto cadastrado.</b><div>Use o formulário ao lado para publicar o primeiro card.</div></div></div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($items as $p): $on = products_is_on($p); $img = trim((string)($p['image_url'] ?? '')); ?>
          <article class="card product-card">
            <?php if ($img !== '' && preg_match('#^https://#i', $img)): ?>
              <img class="product-card-img" src="<?= e($img) ?>" alt="">
            <?php else: ?>
              <div class="product-card-ph"><?= e(strtoupper(substr((string)$p['title'], 0, 1))) ?></div>
            <?php endif; ?>
            <div class="product-card-body">
              <div class="product-card-kicker"><?= $on ? 'Visível' : 'Oculto' ?></div>
              <h2><?= e((string)$p['title']) ?></h2>
              <p><?= e((string)($p['summary'] ?: $p['description'] ?: 'Sem resumo.')) ?></p>
              <div class="row-actions" style="justify-content:flex-start;margin-top:10px">
                <a class="btn btn-ghost" href="/master/produtos?edit=<?= e((string)$p['id']) ?>">Editar</a>
                <form method="post" action="/master/produtos/status">
                  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                  <input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>">
                  <button class="btn btn-ghost"><?= $on ? 'Ocultar' : 'Exibir' ?></button>
                </form>
                <form method="post" action="/master/produtos/excluir" onsubmit="return confirm('Excluir este produto do catálogo?')">
                  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
                  <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                  <button class="btn btn-ghost">Excluir</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($leads): ?>
      <div class="card table-wrap" style="margin-top:16px">
        <h2 style="margin:0;padding:16px 16px 0;font-size:16px">Interesses recentes</h2>
        <table class="data">
          <thead><tr><th>Empresa</th><th>Produto</th><th>Quem pediu</th><th>Quando</th></tr></thead>
          <tbody>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td><?= e((string)($lead['business_name'] ?: $lead['tenant_name'] ?: '—')) ?></td>
              <td><?= e((string)($lead['product_title'] ?? '—')) ?></td>
              <td><?= e((string)($lead['user_name'] ?? '—')) ?></td>
              <td><?= e((string)($lead['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
