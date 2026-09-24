<?php
$display = ($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards';
$statusFilter = $_GET['status'] ?? 'ALL';
$search = trim($_GET['q'] ?? '');
$visible = array_values(array_filter($services, function ($s) use ($statusFilter, $search) {
    if ($statusFilter !== 'ALL' && $s['status'] !== $statusFilter) return false;
    return $search === '' || stripos($s['name'].' '.($s['category'] ?? '').' '.($s['description'] ?? ''), $search) !== false;
}));
$activeCount = count(array_filter($services, fn($s) => $s['status'] === 'ACTIVE'));
$listQs = http_build_query(array_filter([
    'view' => $display === 'table' ? 'table' : null,
    'q' => $search !== '' ? $search : null,
    'status' => $statusFilter !== 'ALL' ? $statusFilter : null,
]));
$listHref = '/app/servicos'.($listQs !== '' ? '?'.$listQs : '');
$flagOn = static fn($v) => !empty($v) && $v !== '0' && $v !== 'f' && $v !== 'false';
?>
<div class="page-head">
  <div><h1>Serviços</h1><p>Organize preços e durações usados automaticamente na agenda.</p></div>
  <a class="btn btn-primary" href="/app/servicos?novo=1<?= $listQs !== '' ? '&'.$listQs : '' ?>"><?= icon('plus') ?> Novo serviço</a>
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
    <a class="<?= $display==='cards'?'active':'' ?>" href="/app/servicos?view=cards&q=<?= urlencode($search) ?>&status=<?= e($statusFilter) ?>">Cartões</a>
    <a class="<?= $display==='table'?'active':'' ?>" href="/app/servicos?view=table&q=<?= urlencode($search) ?>&status=<?= e($statusFilter) ?>">Tabela</a>
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
          <td><?= e(service_duration_label((int)$s['duration_minutes'])) ?><?= !empty($s['buffer_minutes']) ? ' + '.(int)$s['buffer_minutes'].' int.' : '' ?></td>
          <td>
            <strong><?= e(service_price_label($s)) ?></strong>
            <?php if (service_price_kind($s)==='priced' && !empty($s['deposit'])): ?><small style="display:block;color:#667085">Sinal <?= e(money((float)$s['deposit'])) ?></small><?php endif; ?>
          </td>
          <td><?= e(service_location_label($s['location_type'] ?? null)) ?></td>
          <td><?= $s['status']==='ACTIVE' ? '<span class="badge" style="background:#dcfce7;color:#166534">Ativo</span>' : '<span class="badge" style="background:#e2e8f0;color:#475467">Inativo</span>' ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost" href="/app/servicos?ver=<?= e($s['id']) ?><?= $listQs !== '' ? '&'.$listQs : '' ?>">Ver detalhes</a>
              <a class="btn btn-ghost" href="/app/servicos?edit=<?= e($s['id']) ?><?= $listQs !== '' ? '&'.$listQs : '' ?>">Editar</a>
              <form method="post" action="/app/servicos/excluir" onsubmit="return confirm('Excluir este serviço? Os agendamentos continuam na agenda, mas ficam sem serviço atribuído.')">
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
        <div class="service-data"><span><?= e(service_duration_label((int)$s['duration_minutes'])) ?> · <?= e(service_location_label($s['location_type'] ?? null)) ?></span><strong><?= e(service_price_label($s)) ?></strong></div>
        <div class="row-actions" style="justify-content:flex-start;margin-top:8px">
          <a class="btn btn-ghost" href="/app/servicos?ver=<?= e($s['id']) ?><?= $listQs !== '' ? '&'.$listQs : '' ?>">Ver detalhes</a>
          <a class="btn btn-ghost" href="/app/servicos?edit=<?= e($s['id']) ?><?= $listQs !== '' ? '&'.$listQs : '' ?>">Editar</a>
          <form method="post" action="/app/servicos/excluir" onsubmit="return confirm('Excluir este serviço? Os agendamentos continuam na agenda, mas ficam sem serviço atribuído.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <button class="btn btn-ghost">Excluir</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
$viewing = null;
if (!empty($_GET['ver']) && empty($_GET['edit']) && empty($_GET['novo'])) {
    $viewing = one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$_GET['ver'], $tenant['id']]);
}
if ($viewing):
  $vk = service_price_kind($viewing);
?>
<div class="overlay" role="presentation">
  <div class="card overlay-panel service-form" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px">Detalhes do serviço</h2>
      <a class="btn btn-ghost" href="<?= e($listHref) ?>">Fechar</a>
    </div>
    <div class="detail-grid" style="margin-top:14px">
      <div class="detail-item"><small>Nome</small><strong><?= e($viewing['name']) ?></strong></div>
      <div class="detail-item"><small>Status</small><strong><?= ($viewing['status'] ?? '')==='INACTIVE' ? 'Inativo' : 'Ativo' ?></strong></div>
      <div class="detail-item"><small>Categoria</small><strong><?= e($viewing['category'] ?: '—') ?></strong></div>
      <div class="detail-item"><small>Preço</small><strong><?= e(service_price_label($viewing)) ?><?= $vk==='priced' && !empty($viewing['deposit']) ? ' · sinal '.e(money((float)$viewing['deposit'])) : '' ?></strong></div>
      <div class="detail-item"><small>Duração</small><strong><?= e(service_duration_label((int)$viewing['duration_minutes'])) ?><?= !empty($viewing['buffer_minutes']) ? ' + '.(int)$viewing['buffer_minutes'].' int.' : '' ?></strong></div>
      <div class="detail-item"><small>Local</small><strong><?= e(service_location_label($viewing['location_type'] ?? null)) ?></strong></div>
      <div class="detail-item"><small>Capacidade</small><strong><?= (int)($viewing['capacity'] ?? 1) ?></strong></div>
      <div class="detail-item"><small>Antecedência / agenda</small><strong><?= (int)($viewing['min_notice_hours'] ?? 0) ?> h mín. · até <?= (int)($viewing['max_advance_days'] ?? 60) ?> dias</strong></div>
      <div class="detail-item"><small>Site / webhook</small><strong><?= $flagOn($viewing['bookable_online'] ?? 1) ? 'Disponível' : 'Indisponível' ?></strong></div>
      <div class="detail-item"><small>Confirmação</small><strong><?= $flagOn($viewing['requires_confirmation'] ?? 0) ? 'Exige confirmação manual' : 'Não exige' ?></strong></div>
      <?php if (!empty($viewing['location_note'])): ?>
        <div class="detail-item detail-wide"><small>Endereço, sala ou link</small><strong><?= e($viewing['location_note']) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($viewing['description'])): ?>
        <div class="detail-item detail-wide"><small>Descrição</small><strong><?= nl2br(e($viewing['description'])) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($viewing['client_instructions'])): ?>
        <div class="detail-item detail-wide"><small>Orientações para o cliente</small><strong><?= nl2br(e($viewing['client_instructions'])) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($viewing['internal_notes'])): ?>
        <div class="detail-item detail-wide"><small>Observações internas</small><strong><?= nl2br(e($viewing['internal_notes'])) ?></strong></div>
      <?php endif; ?>
    </div>
    <p style="margin-top:14px">
      <a class="btn btn-primary" href="/app/servicos?edit=<?= e($viewing['id']) ?><?= $listQs !== '' ? '&'.$listQs : '' ?>">Editar</a>
      <a class="btn btn-ghost" href="<?= e($listHref) ?>">Voltar</a>
    </p>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($_GET['novo']) || !empty($_GET['edit'])):
  $oldSvc = take_old_form();
  $s = !empty($_GET['edit']) ? one('SELECT * FROM services WHERE id=? AND tenant_id=?', [$_GET['edit'], $tenant['id']]) : [];
  $s = is_array($s) ? $s : [];
  $kindNow = service_price_kind($s);
  $hasPrice = old_fill($oldSvc, 'has_price', ($s && $kindNow !== 'priced') ? '0' : '1');
  $freeKind = old_fill($oldSvc, 'no_price_kind', in_array($kindNow, ['convenio','cortesia','reuniao'], true) ? $kindNow : '');
  $priceShow = old_fill($oldSvc, 'price', ($kindNow === 'priced' && isset($s['price'])) ? number_format((float)$s['price'], 2, ',', '.') : '');
  $depositShow = old_fill($oldSvc, 'deposit', isset($s['deposit']) ? number_format((float)$s['deposit'], 2, ',', '.') : '0,00');
?>
<div class="overlay" role="presentation">
  <form method="post" action="/app/servicos/salvar" class="card service-form overlay-panel" data-service-price onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px"><?= $s ? 'Editar serviço' : 'Novo serviço' ?></h2>
      <a class="btn btn-ghost" href="<?= $s ? '/app/servicos?ver='.e($s['id']).($listQs !== '' ? '&'.$listQs : '') : e($listHref) ?>">Fechar</a>
    </div>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($s): ?><input type="hidden" name="id" value="<?= e($s['id']) ?>"><?php endif; ?>

    <h3 class="form-section">Identificação</h3>
    <label class="label">Nome</label>
    <input class="input" name="name" value="<?= e(old_fill($oldSvc, 'name', $s['name'] ?? '')) ?>" required>
    <div class="grid g2">
      <div>
        <label class="label">Categoria</label>
        <input class="input" name="category" value="<?= e(old_fill($oldSvc, 'category', $s['category'] ?? '')) ?>" placeholder="Avaliação, corte, sessão...">
      </div>
      <div>
        <label class="label">Status</label>
        <select class="select" name="status">
          <option value="ACTIVE">Ativo</option>
          <option value="INACTIVE" <?= old_fill($oldSvc, 'status', $s['status'] ?? '')==='INACTIVE'?'selected':'' ?>>Inativo</option>
        </select>
      </div>
    </div>
    <label class="label">Descrição</label>
    <textarea class="textarea" name="description" rows="3"><?= e(old_fill($oldSvc, 'description', $s['description'] ?? '')) ?></textarea>

    <h3 class="form-section">Agenda e valor</h3>
    <?php [$durH, $durM] = service_duration_parts($s ?: ['duration_minutes' => old_fill($oldSvc, 'duration_minutes', '60')]);
          $durH = (int)old_fill($oldSvc, 'duration_hours', (string)$durH);
          $durM = (int)old_fill($oldSvc, 'duration_mins', (string)$durM);
    ?>
    <div class="grid g2">
      <div>
        <label class="label">Duração</label>
        <div class="duration-inputs">
          <label><input class="input" type="number" min="0" max="24" name="duration_hours" value="<?= e((string)$durH) ?>" inputmode="numeric"> <span>horas</span></label>
          <label><input class="input" type="number" min="0" max="59" name="duration_mins" value="<?= e((string)$durM) ?>" inputmode="numeric"> <span>min</span></label>
        </div>
        <div class="duration-picks" role="group" aria-label="Durações rápidas">
          <?php foreach ([30 => '30 min', 60 => '1 h', 120 => '2 h', 240 => '4 h', 480 => '8 h'] as $mins => $lab): ?>
            <button type="button" class="btn btn-ghost js-duration-preset" data-min="<?= (int)$mins ?>"><?= e($lab) ?></button>
          <?php endforeach; ?>
        </div>
        <p class="settings-hint">Pode ser longo, por exemplo 8 horas. Na agenda do dia esse tempo inteiro fica ocupado.</p>
      </div>
      <div><label class="label">Intervalo (min)</label><input class="input" type="number" min="0" name="buffer_minutes" value="<?= e(old_fill($oldSvc, 'buffer_minutes', (string)($s['buffer_minutes'] ?? 0))) ?>"></div>
    </div>
    <p class="label" style="margin-top:10px">Este serviço possui preço?</p>
    <div class="price-kind-picks">
      <label class="check-row" style="margin:0"><input type="radio" name="has_price" value="1" <?= $hasPrice!=='0'?'checked':'' ?>> Possui preço</label>
      <label class="check-row" style="margin:0"><input type="radio" name="has_price" value="0" <?= $hasPrice==='0'?'checked':'' ?>> Não possui preço</label>
    </div>
    <div class="grid g2" data-price-paid <?= $hasPrice==='0'?'hidden':'' ?> style="margin-top:10px">
      <div><label class="label">Preço (R$)</label><input class="input" name="price" inputmode="decimal" autocomplete="off" placeholder="0,01" value="<?= e($priceShow) ?>" <?= $hasPrice!=='0'?'required':'' ?>></div>
      <div><label class="label">Sinal / depósito</label><input class="input" name="deposit" inputmode="decimal" autocomplete="off" placeholder="0,00" value="<?= e($depositShow) ?>"></div>
    </div>
    <div data-price-free <?= $hasPrice==='0'?'':'hidden' ?> style="margin-top:10px">
      <p class="label">Tipo sem preço</p>
      <div class="price-kind-picks">
        <label class="check-row" style="margin:0"><input type="radio" name="no_price_kind" value="convenio" <?= $freeKind==='convenio'?'checked':'' ?>> Convênio</label>
        <label class="check-row" style="margin:0"><input type="radio" name="no_price_kind" value="cortesia" <?= $freeKind==='cortesia'?'checked':'' ?>> Cortesia</label>
        <label class="check-row" style="margin:0"><input type="radio" name="no_price_kind" value="reuniao" <?= $freeKind==='reuniao'?'checked':'' ?>> Reunião</label>
      </div>
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

    <p style="margin-top:12px"><button class="btn btn-primary">Salvar serviço</button> <a class="btn btn-ghost" href="<?= $s ? '/app/servicos?ver='.e($s['id']).($listQs !== '' ? '&'.$listQs : '') : e($listHref) ?>">Cancelar</a></p>
  </form>
</div>
<?php endif; ?>
