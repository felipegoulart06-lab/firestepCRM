<?php
$search = $search ?? '';
$statusFilter = $statusFilter ?? 'ALL';
$sourceFilter = $sourceFilter ?? 'ALL';
$sources = $sources ?? [];
$total = count($items);
?>
<div class="page-head">
  <div>
    <h1>Agendamentos</h1>
    <p>Todos os horários, de qualquer origem: manual, site, webhook ou outros canais.</p>
  </div>
  <a class="btn btn-primary" href="/app/agendamentos?new=1"><?= icon('plus') ?> Novo agendamento</a>
</div>

<form method="get" action="/app/agendamentos" class="card service-toolbar">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Buscar cliente, telefone, e-mail, serviço ou origem...">
  <select class="select" name="s" aria-label="Filtrar por status">
    <option value="ALL">Todos os status</option>
    <?php foreach (APPT_STATUS as $k=>$v): ?>
      <option value="<?= e($k) ?>" <?= $statusFilter===$k?'selected':'' ?>><?= e($v[0]) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="select" name="origem" aria-label="Filtrar por origem">
    <option value="ALL">Todas as origens</option>
    <?php foreach ($sources as $src): ?>
      <option value="<?= e($src['source']) ?>" <?= $sourceFilter===$src['source']?'selected':'' ?>><?= e($src['source']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-ghost">Filtrar</button>
</form>

<p style="color:#667085;font-size:12px;margin:-4px 0 10px"><?= $total ?> registro<?= $total===1?'':'s' ?> encontrado<?= $total===1?'':'s' ?></p>

<div class="card table-wrap">
<?php if (!$items): ?>
  <div class="empty">
    <div class="empty-icon"><?= icon('calendar',22) ?></div>
    <b>Nenhum agendamento encontrado.</b>
    <div>Horários criados na agenda, convertidos de solicitações ou recebidos por webhook aparecerão nesta tabela.</div>
    <p><a class="btn btn-primary" href="/app/agendamentos?new=1"><?= icon('plus') ?> Criar agendamento</a></p>
  </div>
<?php else: ?>
<table class="data">
  <thead>
    <tr>
      <th>Data</th>
      <th>Horário</th>
      <th>Cliente</th>
      <th>Contato</th>
      <th>Serviço</th>
      <th>Origem</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $a): ?>
    <tr>
      <td><?= e(date('d/m/Y', strtotime($a['starts_at']))) ?></td>
      <td><?= e(substr($a['starts_at'],11,5)) ?> – <?= e(substr($a['ends_at'],11,5)) ?></td>
      <td><strong><?= e($a['client_name']) ?></strong></td>
      <td><?= e(phone_fmt($a['client_phone'] ?: $a['client_whatsapp'])) ?></td>
      <td><?= e($a['service_name'] ?: '—') ?></td>
      <td><?= e($a['source'] ?: '—') ?></td>
      <td><?= badge_appt($a['status']) ?></td>
      <td>
        <div class="row-actions">
          <a class="btn btn-ghost" href="/app/agendamentos?<?= e(http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null, 'ver'=>$a['id']]))) ?>">Ver</a>
          <a class="btn btn-ghost" href="/app/agendamentos?<?= e(http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null, 'edit'=>$a['id']]))) ?>">Editar</a>
          <a class="btn btn-ghost" href="/app/agendamentos/reserva.pdf?id=<?= e($a['id']) ?>"><?= icon('download') ?> PDF</a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php if (!empty($edit) || !empty($viewing) || !empty($creating)):
  $listQs = http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null]));
  $modalClose = '/app/agendamentos'.($listQs !== '' ? '?'.$listQs : '');
  $forcedClient = $forcedClient ?? null;
  if ($forcedClient && ($_GET['from'] ?? '') === 'clientes') {
      $modalClose = '/app/clientes/ver?id='.$forcedClient['id'];
  }
  $hideCalendarSwitch = true;
  $viewOnly = !empty($viewing);
  $allowEditFromDetails = $viewOnly;
  if ($viewOnly) {
      $edit = $viewing;
      $editHref = '/app/agendamentos?'.http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null, 'edit'=>$viewing['id']]));
  }
  include VIEWS . '/app/modal_appointment.php';
endif; ?>
