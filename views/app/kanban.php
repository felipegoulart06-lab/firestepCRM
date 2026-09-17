<div class="page-head">
  <div>
    <h1>Pipeline</h1>
    <p class="subtitle">Todos os agendamentos — manuais, do site, de webhook e de outros canais. Use as setas para mudar o status.</p>
  </div>
</div>
<?php
$cols = [];
foreach (APPT_STATUS as $key => $meta) {
    $cols[$key] = $meta[0];
}
$statuses = array_keys($cols);
$by = [];
foreach ($items as $i) {
    $by[$i['status']][] = $i;
}
?>
<div class="kanban">
<?php foreach ($cols as $st=>$title): $list = $by[$st] ?? []; $position = array_search($st, $statuses, true); ?>
  <section class="card kanban-col">
    <div class="kanban-col-head"><b><?= e($title) ?></b><span class="badge" style="background:#eef2ff;color:#3730a3"><?= count($list) ?></span></div>
    <?php foreach ($list as $i): ?>
      <article class="card kcard">
        <b><a href="/app/agendamentos?ver=<?= e($i['id']) ?>"><?= e($i['client_name']) ?></a></b>
        <p><?= e($i['service_name'] ?: 'Serviço a definir') ?></p>
        <p><?= e($i['source'] ?: 'Manual') ?> · <?= e(phone_fmt($i['client_phone'] ?: ($i['client_whatsapp'] ?? ''))) ?></p>
        <span><?= e(date('d/m H:i', strtotime($i['starts_at']))) ?></span>
        <div class="kanban-actions">
          <?php if ($position > 0): ?>
            <form method="post" action="/app/kanban">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($i['id']) ?>"><input type="hidden" name="status" value="<?= e($statuses[$position-1]) ?>">
              <button class="btn btn-ghost kanban-arrow" title="Voltar para <?= e($cols[$statuses[$position-1]]) ?>" aria-label="Voltar status">‹</button>
            </form>
          <?php else: ?><span></span><?php endif; ?>
          <small><?= e($title) ?></small>
          <?php if ($position < count($statuses)-1): ?>
            <form method="post" action="/app/kanban">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= e($i['id']) ?>"><input type="hidden" name="status" value="<?= e($statuses[$position+1]) ?>">
              <button class="btn btn-primary kanban-arrow" title="Avançar para <?= e($cols[$statuses[$position+1]]) ?>" aria-label="Avançar status">›</button>
            </form>
          <?php else: ?><span></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>
