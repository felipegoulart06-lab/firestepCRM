<?php
$old = $old ?? [];
$edit = $edit ?? null;
$viewing = $viewing ?? null;
$showForm = !empty($novo) || $edit;
$src = $edit ?: [];
$search = $search ?? '';
$items = $items ?? [];
$kindFill = ['document_kind' => old_fill($old, 'document_kind', $src['document_kind'] ?? br_doc_kind_from_value($src['cnpj'] ?? ''))];
$show = static fn($v) => ($v !== null && trim((string)$v) !== '') ? (string)$v : '—';
?>
<div class="page-head">
  <div>
    <h1>Fornecedores</h1>
    <p>Cadastro administrativo de quem abastece a empresa: documento, produto e contato. Não altera agenda nem financeiro.</p>
  </div>
  <?php if (!$showForm && !$viewing): ?>
    <a class="btn btn-primary" href="/app/fornecedores?novo=1"><?= icon('plus') ?> Novo fornecedor</a>
  <?php endif; ?>
</div>

<?php if ($viewing):
  $kind = strtolower((string)($viewing['document_kind'] ?? '')) ?: br_doc_kind_from_value($viewing['cnpj'] ?? '');
?>
<div class="card" style="margin-bottom:16px;padding:18px;max-width:760px">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
    <h2 style="margin:0">Detalhes do fornecedor</h2>
    <div>
      <a class="btn btn-primary" href="/app/fornecedores?edit=<?= e($viewing['id']) ?>">Editar</a>
      <a class="btn btn-ghost" href="/app/fornecedores">Fechar</a>
    </div>
  </div>
  <div class="detail-grid" style="margin-top:14px">
    <div class="detail-item"><small>Nome / razão social</small><strong><?= e($show($viewing['name'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Documento</small><strong><?= e($kind === 'cpf' || $kind === 'cnpj' ? format_br_document($viewing['cnpj'] ?? '', $kind) : ($viewing['cnpj'] ?: '—')) ?> <?= $kind === 'cpf' || $kind === 'cnpj' ? '· '.strtoupper($kind) : '' ?></strong></div>
    <div class="detail-item"><small>Tipo de produto</small><strong><?= e($show($viewing['product_type'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Contato responsável</small><strong><?= e($show($viewing['contact_name'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Telefone</small><strong><?= e(phone_fmt($viewing['phone'] ?? '') ?: '—') ?></strong></div>
    <div class="detail-item"><small>E-mail</small><strong><?= e($show($viewing['email'] ?? '')) ?></strong></div>
    <div class="detail-item detail-wide"><small>Endereço</small><strong><?= e(trim(implode(' · ', array_filter([$viewing['address'] ?? '', $viewing['city'] ?? '', $viewing['state'] ?? '', $viewing['cep'] ?? '']))) ?: '—') ?></strong></div>
    <?php if (!empty($viewing['notes'])): ?>
      <div class="detail-item detail-wide"><small>Observações</small><strong><?= nl2br(e($viewing['notes'])) ?></strong></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="card" style="margin-bottom:16px;padding:18px">
  <h2 style="margin:0 0 12px"><?= $edit ? 'Editar fornecedor' : 'Novo fornecedor' ?></h2>
  <form method="post" action="/app/fornecedores/salvar" class="grid g2">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= e($edit['id']) ?>"><?php endif; ?>
    <div><label class="label">Nome / razão social</label><input class="input" name="name" required value="<?= e(old_fill($old, 'name', $src['name'] ?? '')) ?>"></div>
    <div><label class="label">Tipo de produto fornecido</label><input class="input" name="product_type" value="<?= e(old_fill($old, 'product_type', $src['product_type'] ?? '')) ?>" placeholder="Ex.: material de limpeza, peças, serviços"></div>
    <?php br_document_fields('cnpj', old_fill($old, 'cnpj', $src['cnpj'] ?? ''), true, 'document_kind', $kindFill + ['cnpj' => old_fill($old, 'cnpj', $src['cnpj'] ?? '')]); ?>
    <div><label class="label">Contato responsável</label><input class="input" name="contact_name" value="<?= e(old_fill($old, 'contact_name', $src['contact_name'] ?? '')) ?>"></div>
    <div><label class="label">Telefone</label><input class="input" name="phone" inputmode="tel" value="<?= e(old_fill($old, 'phone', $src['phone'] ?? '')) ?>"></div>
    <div><label class="label">E-mail</label><input class="input" type="email" name="email" value="<?= e(old_fill($old, 'email', $src['email'] ?? '')) ?>"></div>
    <div class="geo-box" data-geo-box style="grid-column:1/-1">
      <label class="label">Endereço no mapa</label>
      <p class="muted" style="margin:0 0 8px">Busque o local. CNPJ com coordenada aparece na Abrangência.</p>
      <div class="geo-search">
        <input class="input geo-q" type="search" autocomplete="off" placeholder="Buscar rua, CEP ou cidade no Brasil">
        <ul class="geo-suggest" hidden></ul>
      </div>
      <div class="geo-map" hidden></div>
      <input type="hidden" name="lat" value="<?= e(old_fill($old, 'lat', (string)($src['lat'] ?? ''))) ?>">
      <input type="hidden" name="lng" value="<?= e(old_fill($old, 'lng', (string)($src['lng'] ?? ''))) ?>">
      <div class="grid g2" style="margin-top:10px">
    <div><label class="label">Logradouro</label><input class="input" name="address" value="<?= e(old_fill($old, 'address', $src['address'] ?? '')) ?>"></div>
    <div><label class="label">Cidade</label><input class="input" name="city" value="<?= e(old_fill($old, 'city', $src['city'] ?? '')) ?>"></div>
    <div><label class="label">UF</label><input class="input" name="state" maxlength="2" value="<?= e(old_fill($old, 'state', $src['state'] ?? '')) ?>"></div>
    <div><label class="label">CEP</label><input class="input" name="cep" inputmode="numeric" value="<?= e(old_fill($old, 'cep', $src['cep'] ?? '')) ?>"></div>
      </div>
    </div>
    <div style="grid-column:1/-1"><label class="label">Observações</label><textarea class="textarea" name="notes"><?= e(old_fill($old, 'notes', $src['notes'] ?? '')) ?></textarea></div>
    <p class="muted" style="grid-column:1/-1;margin:0">Fornecedor com CNPJ e endereço aparece no mapa de Abrangência. CPF fica só nesta lista.</p>
    <div style="grid-column:1/-1;display:flex;gap:8px">
      <button class="btn btn-primary"><?= $edit ? 'Salvar' : 'Cadastrar' ?></button>
      <a class="btn btn-ghost" href="<?= $edit ? '/app/fornecedores?ver='.e($edit['id']) : '/app/fornecedores' ?>">Cancelar</a>
    </div>
  </form>
</div>
<?php endif; ?>

<form method="get" action="/app/fornecedores" class="card service-toolbar">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Buscar nome, documento, produto, contato ou cidade...">
  <button class="btn btn-ghost">Filtrar</button>
</form>

<div class="card table-wrap">
<?php if (!$items): ?>
  <div class="empty">
    <b>Nenhum fornecedor cadastrado.</b>
    <div>Use esta tabela só para organização interna da empresa.</div>
    <p><a class="btn btn-primary" href="/app/fornecedores?novo=1"><?= icon('plus') ?> Cadastrar fornecedor</a></p>
  </div>
<?php else: ?>
<table class="data">
  <thead>
    <tr>
      <th>Nome</th>
      <th>Documento</th>
      <th>Produto</th>
      <th>Contato</th>
      <th>Telefone</th>
      <th>E-mail</th>
      <th>Cidade</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $r):
    $kind = strtolower((string)($r['document_kind'] ?? '')) ?: br_doc_kind_from_value($r['cnpj'] ?? '');
  ?>
    <tr>
      <td><b><?= e($r['name']) ?></b></td>
      <td><?= e($kind === 'cpf' || $kind === 'cnpj' ? format_br_document($r['cnpj'], $kind) : ($r['cnpj'] ?: '—')) ?> <small><?= $kind === 'cpf' ? 'CPF' : ($kind === 'cnpj' ? 'CNPJ' : '') ?></small></td>
      <td><?= e($r['product_type'] ?: '—') ?></td>
      <td><?= e($r['contact_name'] ?: '—') ?></td>
      <td><?= e(phone_fmt($r['phone'] ?? '') ?: '—') ?></td>
      <td><?= e($r['email'] ?: '—') ?></td>
      <td><?= e(trim(($r['city'] ?? '').' '.($r['state'] ?? '')) ?: '—') ?></td>
      <td>
        <div class="row-actions">
          <a class="btn btn-ghost" href="/app/fornecedores?ver=<?= e($r['id']) ?>">Ver detalhes</a>
          <a class="btn btn-ghost" href="/app/fornecedores?edit=<?= e($r['id']) ?>">Editar</a>
          <form method="post" action="/app/fornecedores/excluir" onsubmit="return confirm('Remover este fornecedor?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($r['id']) ?>">
            <button class="btn btn-ghost">Excluir</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
