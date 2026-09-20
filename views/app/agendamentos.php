<?php
$search = $search ?? '';
$statusFilter = $statusFilter ?? 'ALL';
$sourceFilter = $sourceFilter ?? 'ALL';
$sources = $sources ?? [];
$total = count($items);
$communicateItems = communicate_items('appointment', $items, $tenant);
$communicateBack = '/app/agendamentos'.(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '');
?>
<div class="page-head">
  <div>
    <h1>Agendamentos</h1>
    <p>Todos os horários, de qualquer origem: manual, site, webhook ou outros canais.</p>
  </div>
  <a class="btn btn-primary" href="/app/agendamentos?new=1"><?= icon('plus') ?> Novo agendamento</a>
</div>
<?php if (!empty($edit) || !empty($viewing) || !empty($creating)):
  $listQs = http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null]));
  $modalClose = '/app/agendamentos'.($listQs !== '' ? '?'.$listQs : '');
  $forcedClient = $forcedClient ?? null;
  if ($forcedClient && ($_GET['from'] ?? '') === 'clientes') {
      $modalClose = '/app/clientes/ver?id='.$forcedClient['id'];
  }
  $hideCalendarSwitch = true;
  $modalInline = true;
  $viewOnly = !empty($viewing);
  $allowEditFromDetails = $viewOnly;
  if ($viewOnly) {
      $edit = $viewing;
      $editHref = '/app/agendamentos?'.http_build_query(array_filter(['q'=>$search ?: null, 's'=>$statusFilter !== 'ALL' ? $statusFilter : null, 'origem'=>$sourceFilter !== 'ALL' ? $sourceFilter : null, 'edit'=>$viewing['id']]));
  }
  include VIEWS . '/app/modal_appointment.php';
endif; ?>

<form method="get" action="/app/agendamentos" class="card service-toolbar">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Buscar n° reserva, cliente, agente, serviço...">
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
      <th>N° reserva</th>
      <th>Data</th>
      <th>Horário</th>
      <th>Cliente</th>
      <th>Agente</th>
      <th>Serviço</th>
      <th>Valor</th>
      <th>Tipo</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($items as $a): ?>
    <tr>
      <td><strong><?= e(appointment_reserva_label($a)) ?></strong></td>
      <td><?= e(date('d/m/Y', strtotime($a['starts_at']))) ?></td>
      <td><?= e(substr($a['starts_at'],11,5)) ?> – <?= e(substr($a['ends_at'],11,5)) ?></td>
      <td>
        <strong><?= e($a['client_name']) ?></strong>
        <div style="font-size:12px;color:#667085"><?= e(phone_fmt($a['client_phone'] ?: $a['client_whatsapp'])) ?></div>
      </td>
      <td><?= e($a['agent_name'] ?: '—') ?></td>
      <td><?= e($a['service_name'] ?: '—') ?></td>
      <td><?= e(appointment_value_label($a)) ?></td>
      <td><?= e(appointment_type_label($a)) ?></td>
      <td><?= badge_appt($a['status']) ?></td>
      <td class="row-actions-cell">
        <div class="row-actions">
          <button class="btn btn-ghost js-communicate" type="button" data-communicate-key="appointment:<?= e($a['id']) ?>" title="Comunicar pelo WhatsApp"><?= icon('message', 13) ?> Comunicar</button>
          <a class="btn btn-ghost" href="/app/agendamentos?ver=<?= e($a['id']) ?>">Ver detalhes</a>
          <a class="btn btn-ghost" href="/app/agendamentos?edit=<?= e($a['id']) ?>">Editar</a>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
</div>
<?php include VIEWS . '/app/communicate_modal.php'; ?>
