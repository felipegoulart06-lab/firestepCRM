<?php $categories = $categories ?? []; ?>
<div class="page-head">
  <div>
    <h1>Segmentos</h1>
    <p class="subtitle">O mesmo CRM para todos. O segmento só muda o nome do negócio, serviços iniciais e campos.</p>
  </div>
</div>
<div class="grid dash" style="grid-template-columns:minmax(280px,340px) 1fr;align-items:start">
  <form method="post" action="/master/segmentos/criar" class="card" style="padding:20px">
    <h2 style="margin:0 0 6px;font-size:16px">Novo segmento</h2>
    <p class="muted" style="margin:0 0 14px">Depois de salvar, ele entra na lista de Criar cliente.</p>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <label class="label">Nome do segmento</label>
    <input class="input" name="name" required maxlength="80" placeholder="Ex.: Studio de unhas">
    <label class="label" style="margin-top:12px">Categoria</label>
    <input class="input" name="category" required maxlength="80" list="segment-categories" placeholder="Ex.: Beleza e Estética">
    <datalist id="segment-categories">
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <p style="margin:16px 0 0"><button class="btn btn-primary">Criar segmento</button></p>
  </form>
  <div class="card table-wrap">
    <table class="data">
      <thead><tr><th>Segmento</th><th>Categoria</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $s): $on = !empty($s['active']); ?>
        <tr>
          <td><?= e($s['name']) ?></td>
          <td><?= e($s['category']) ?></td>
          <td>
            <?php if ($on): ?>
              <span class="badge" style="background:#ecfdf3;color:#166534">Ativo</span>
            <?php else: ?>
              <span class="badge" style="background:#f2f4f7;color:#475467">Oculto</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="/master/segmentos/status">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($s['id']) ?>">
              <input type="hidden" name="active" value="<?= $on ? '0' : '1' ?>">
              <button class="btn btn-ghost"><?= $on ? 'Ocultar' : 'Ativar' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
