<?php
$old = $old ?? [];
$showForm = !empty($novo);
$src = $edit ?: [];
$search = $search ?? '';
$items = $items ?? [];
$kindFill = ['document_kind' => old_fill($old, 'document_kind', $src['document_kind'] ?? br_doc_kind_from_value($src['cnpj'] ?? ''))];
?>
<div class="page-head">
  <div>
    <h1>Fornecedores</h1>
    <p>Cadastro administrativo de quem abastece a empresa: documento, produto e contato. Não altera agenda nem financeiro.</p>
  </div>
  <?php if (!$showForm): ?>
    <a class="btn btn-primary" href="/app/fornecedores?novo=1"><?= icon('plus') ?> Novo fornecedor</a>
  <?php endif; ?>
</div>

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
    <div><label class="label">Endereço</label><input class="input" name="address" value="<?= e(old_fill($old, 'address', $src['address'] ?? '')) ?>"></div>
    <div><label class="label">Cidade</label><input class="input" name="city" value="<?= e(old_fill($old, 'city', $src['city'] ?? '')) ?>"></div>
    <div><label class="label">UF</label><input class="input" name="state" maxlength="2" value="<?= e(old_fill($old, 'state', $src['state'] ?? '')) ?>"></div>
    <div><label class="label">CEP</label><input class="input" name="cep" inputmode="numeric" value="<?= e(old_fill($old, 'cep', $src['cep'] ?? '')) ?>"></div>
    <div style="grid-column:1/-1"><label class="label">Observações</label><textarea class="textarea" name="notes"><?= e(old_fill($old, 'notes', $src['notes'] ?? '')) ?></textarea></div>
    <p class="muted" style="grid-column:1/-1;margin:0">Fornecedor com CNPJ e endereço aparece no mapa de Abrangência. CPF fica só nesta lista.</p>
    <div style="grid-column:1/-1;display:flex;gap:8px">
      <button class="btn btn-primary"><?= $edit ? 'Salvar' : 'Cadastrar' ?></button>
      <a class="btn btn-ghost" href="/app/fornecedores">Cancelar</a>
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
          <a class="btn btn-ghost" href="/app/fornecedores?id=<?= e($r['id']) ?>">Editar</a>
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
