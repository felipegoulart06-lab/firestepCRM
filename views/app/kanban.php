<div class="page-head">
  <div>
    <h1>Pipeline</h1>
    <p class="subtitle">Todos os agendamentos — manuais, do site, de webhook e de outros canais. Altere o status no card e confirme com Sim.</p>
  </div>
</div>
<?php
$cols = [];
foreach (APPT_STATUS as $key => $meta) {
    $cols[$key] = $meta[0];
}
$by = [];
foreach ($items as $i) {
    $by[$i['status']][] = $i;
}
?>
<div class="kanban">
<?php foreach ($cols as $st=>$title): $list = $by[$st] ?? []; ?>
  <section class="card kanban-col">
    <div class="kanban-col-head"><b><?= e($title) ?></b><span class="badge" style="background:#eef2ff;color:#3730a3"><?= count($list) ?></span></div>
    <?php foreach ($list as $i): ?>
      <article class="card kcard">
        <b><a href="/app/agendamentos?ver=<?= e($i['id']) ?>"><?= e($i['client_name']) ?></a></b>
        <p><?= e($i['service_name'] ?: 'Serviço a definir') ?></p>
        <p><?= e($i['source'] ?: 'Manual') ?> · <?= e(phone_fmt($i['client_phone'] ?: ($i['client_whatsapp'] ?? ''))) ?></p>
        <span><?= e(date('d/m H:i', strtotime($i['starts_at']))) ?></span>
        <div class="kanban-actions">
          <label class="label" for="kanban-st-<?= e($i['id']) ?>">Status</label>
          <select class="select js-kanban-status" id="kanban-st-<?= e($i['id']) ?>" data-id="<?= e($i['id']) ?>" data-current="<?= e($i['status']) ?>" data-who="<?= e($i['client_name']) ?>">
            <?php foreach ($cols as $key=>$lab): ?>
              <option value="<?= e($key) ?>" <?= $key===$i['status'] ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>

<form id="kanban-status-form" method="post" action="/app/kanban" hidden>
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <input type="hidden" name="id" id="kanban-status-id" value="">
  <input type="hidden" name="status" id="kanban-status-value" value="">
  <input type="hidden" name="confirm" value="1">
</form>
<div class="overlay" id="kanban-confirm" hidden>
  <div class="card overlay-panel" style="max-width:420px;padding:18px" onclick="event.stopPropagation()">
    <h2 style="margin:0 0 8px;font-size:17px">Alterar status</h2>
    <p id="kanban-confirm-text" style="margin:0;color:#475467">Deseja realmente alterar o status deste agendamento?</p>
    <p style="margin:16px 0 0;display:flex;gap:8px;justify-content:flex-end">
      <button type="button" class="btn btn-ghost" id="kanban-no">Não</button>
      <button type="button" class="btn btn-primary" id="kanban-yes">Sim</button>
    </p>
  </div>
</div>
