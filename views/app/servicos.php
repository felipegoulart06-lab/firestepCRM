<?php
$display = ($_GET['view'] ?? 'table') === 'cards' ? 'cards' : 'table';
$statusFilter = $_GET['status'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');
$visible = array_values(array_filter($services, function ($s) use ($statusFilter, $search) {
    if ($statusFilter !== 'ALL' && $s['status'] !== $statusFilter) return false;
    return $search === '' || stripos($s['name'].' '.($s['category'] ?? '').' '.($s['description'] ?? ''), $search) !== false;
}));
$activeCount = count(array_filter($services, fn($s) => $s['status'] === 'ACTIVE'));
?>
<div class="page-head">
  <div><h1>Serviços</h1><p>Organize preços e durações usados automaticamente na agenda.</p></div>
  <a class="btn btn-primary" href="/app/servicos?novo=1"><?= icon('plus') ?> Novo serviço</a>
</div>

<div class="service-summary">
  <span><strong><?= count($services) ?></strong> cadastrados</span>
  <span><strong><?= $activeCount ?></strong> ativos</span>
  <span><strong><?= count($services)-$activeCount ?></strong> inativos</span>
</div>

<form method="get" action="/app/servicos" class="card service-toolbar">
  <input type="hidden" name="view" value="<?= e($display) ?>">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Buscar serviço, categoria ou descrição...">
  <select class="select" name="status" aria-label="Filtrar por status">
    <option value="ALL">Todos os status</option>
    <option value="ACTIVE" <?= $statusFilter==='ACTIVE'?'selected':'' ?>>Ativos</option>
    <option value="INACTIVE" <?= $statusFilter==='INACTIVE'?'selected':'' ?>>Inativos</option>
  </select>
  <button class="btn btn-ghost">Filtrar</button>
  <div class="view-switch" aria-label="Modo de visualização">
    <a class="<?= $display==='table'?'active':'' ?>" href="/app/servicos?view=table&q=<?= urlencode($search) ?>&status=<?= e($statusFilter) ?>">Tabela</a>
    <a class="<?= $display==='cards'?'active':'' ?>" href="/app/servicos?view=cards&q=<?= urlencode($search) ?>&status=<?= e($statusFilter) ?>">Cartões</a>
  </div>
</form>

<?php if (!$visible): ?>
  <div class="card"><div class="empty"><b>Nenhum serviço encontrado.</b><div>Ajuste os filtros ou cadastre um novo serviço.</div></div></div>
<?php elseif ($display === 'table'): ?>
  <div class="card table-wrap">
    <table class="data service-table">
      <thead><tr><th>Serviço</th><th>Categoria</th><th>Duração</th><th>Preço</th><th>Local</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($visible as $s): ?>
        <tr>
          <td><div class="service-name"><i style="background:<?= e($s['color']) ?>"></i><div><strong><?= e($s['name']) ?></strong><small><?= e($s['description'] ?: 'Sem descrição') ?></small></div></div></td>
          <td><?= e($s['category'] ?: '—') ?></td>
          <td><?= (int)$s['duration_minutes'] ?> min<?= !empty($s['buffer_minutes']) ? ' + '.(int)$s['buffer_minutes'].' int.' : '' ?></td>
          <td>
            <strong><?= e(money((float)$s['price'])) ?></strong>
            <?php if (!empty($s['deposit'])): ?><small style="display:block;color:#667085">Sinal <?= e(money((float)$s['deposit'])) ?></small><?php endif; ?>
          </td>
          <td><?= e(service_location_label($s['location_type'] ?? null)) ?></td>
          <td><?= $s['status']==='ACTIVE' ? '<span class="badge" style="background:#dcfce7;color:#166534">Ativo</span>' : '<span class="badge" style="background:#e2e8f0;color:#475467">Inativo</span>' ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost" href="/app/servicos?edit=<?= e($s['id']) ?>&view=table">Editar</a>
              <form method="post" action="/app/servicos/excluir" onsubmit="return confirm('Excluir este serviço?')">
                <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                <button class="btn btn-ghost">Excluir</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="service-grid">
    <?php foreach ($visible as $s): ?>
      <article class="card service-card" style="--service-color:<?= e($s['color']) ?>">
        <div class="service-card-head">
          <span class="service-color" style="background:<?= e($s['color']) ?>"></span>
          <?= $s['status']==='ACTIVE' ? '<span class="badge" style="background:#dcfce7;color:#166534">Ativo</span>' : '<span class="badge" style="background:#e2e8f0;color:#475467">Inativo</span>' ?>
        </div>
        <h2><?= e($s['name']) ?></h2>
        <div class="service-category"><?= e($s['category'] ?: 'Sem categoria') ?></div>
        <p><?= e($s['description'] ?: 'Nenhuma descrição cadastrada.') ?></p>
        <div class="service-data"><span><?= (int)$s['duration_minutes'] ?> min · <?= e(service_location_label($s['location_type'] ?? null)) ?></span><strong><?= e(money((float)$s['price'])) ?></strong></div>
        <div class="row-actions" style="justify-content:flex-start;margin-top:8px">
          <a class="btn btn-ghost" href="/app/servicos?edit=<?= e($s['id']) ?>">Editar</a>
          <form method="post" action="/app/servicos/excluir" onsubmit="return confirm('Excluir este serviço?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <button class="btn btn-ghost">Excluir</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if (!empty($_GET['novo']) || !empty($_GET['edit'])):
  $s = !empty($_GET['edit']) ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$_GET['edit'], $tenant['id']]) : [];
?>
<div class="overlay" role="presentation">
  <form method="post" action="/app/servicos/salvar" class="card service-form overlay-panel" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px"><?= $s ? 'Editar serviço' : 'Novo serviço' ?></h2>
      <a class="btn btn-ghost" href="/app/servicos">Fechar</a>
    </div>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($s): ?><input type="hidden" name="id" value="<?= e($s['id']) ?>"><?php endif; ?>

    <h3 class="form-section">Identificação</h3>
    <label class="label">Nome</label>
    <input class="input" name="name" value="<?= e($s['name'] ?? '') ?>" required>
    <div class="grid g2">
      <div>
        <label class="label">Categoria</label>
        <input class="input" name="category" value="<?= e($s['category'] ?? '') ?>" placeholder="Avaliação, corte, sessão...">
      </div>
      <div>
        <label class="label">Status</label>
        <select class="select" name="status">
          <option value="ACTIVE">Ativo</option>
          <option value="INACTIVE" <?= (($s['status']??'')==='INACTIVE')?'selected':'' ?>>Inativo</option>
        </select>
      </div>
    </div>
    <label class="label">Descrição</label>
    <textarea class="textarea" name="description" rows="3"><?= e($s['description'] ?? '') ?></textarea>

    <h3 class="form-section">Agenda e valor</h3>
    <div class="grid g4">
      <div><label class="label">Duração (min)</label><input class="input" type="number" min="5" name="duration_minutes" value="<?= e((string)($s['duration_minutes'] ?? 60)) ?>"></div>
      <div><label class="label">Intervalo (min)</label><input class="input" type="number" min="0" name="buffer_minutes" value="<?= e((string)($s['buffer_minutes'] ?? 0)) ?>"></div>
      <div><label class="label">Preço</label><input class="input" type="number" step="0.01" min="0" name="price" value="<?= e((string)($s['price'] ?? 0)) ?>"></div>
      <div><label class="label">Sinal / depósito</label><input class="input" type="number" step="0.01" min="0" name="deposit" value="<?= e((string)($s['deposit'] ?? 0)) ?>"></div>
    </div>
    <div class="grid g2">
      <div>
        <label class="label">Cor na agenda</label>
        <input class="input" type="color" name="color" value="<?= e($s['color'] ?? '#2563eb') ?>">
      </div>
      <div>
        <label class="label">Capacidade simultânea</label>
        <input class="input" type="number" min="1" name="capacity" value="<?= e((string)($s['capacity'] ?? 1)) ?>">
      </div>
    </div>

    <h3 class="form-section">Atendimento</h3>
    <div class="grid g2">
      <div>
        <label class="label">Local</label>
        <select class="select" name="location_type">
          <?php foreach (['presencial'=>'Presencial','online'=>'Online','domicilio'=>'A domicílio','ambos'=>'Presencial ou online'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= (($s['location_type'] ?? 'presencial')===$k)?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label">Endereço, sala ou link</label>
        <input class="input" name="location_note" value="<?= e($s['location_note'] ?? '') ?>" placeholder="Sala 2, Zoom, endereço...">
      </div>
    </div>
    <div class="grid g2">
      <div><label class="label">Antecedência mínima (horas)</label><input class="input" type="number" min="0" name="min_notice_hours" value="<?= e((string)($s['min_notice_hours'] ?? 0)) ?>"></div>
      <div><label class="label">Pode marcar até (dias)</label><input class="input" type="number" min="0" name="max_advance_days" value="<?= e((string)($s['max_advance_days'] ?? 60)) ?>"></div>
    </div>
    <label class="check-row"><input type="checkbox" name="bookable_online" <?= (!isset($s['bookable_online']) || !empty($s['bookable_online']))?'checked':'' ?>> Disponível para agendamento pelo site / webhook</label>
    <label class="check-row"><input type="checkbox" name="requires_confirmation" <?= !empty($s['requires_confirmation'])?'checked':'' ?>> Exige confirmação manual</label>

    <h3 class="form-section">Instruções</h3>
    <label class="label">Orientações para o cliente</label>
    <textarea class="textarea" name="client_instructions" rows="3" placeholder="Chegar 10 minutos antes, trazer documentos..."><?= e($s['client_instructions'] ?? '') ?></textarea>
    <label class="label">Observações internas</label>
    <textarea class="textarea" name="internal_notes" rows="3" placeholder="Visível só para a equipe."><?= e($s['internal_notes'] ?? '') ?></textarea>

    <p style="margin-top:12px"><button class="btn btn-primary">Salvar serviço</button> <a class="btn btn-ghost" href="/app/servicos">Cancelar</a></p>
  </form>
</div>
<?php endif; ?>
