<div class="page-head">
  <div>
    <h1>Pipeline de solicitações</h1>
    <p class="subtitle">Use as setas de cada card para avançar ou retornar o status.</p>
  </div>
</div>
<?php
$cols = ['NEW'=>'Novas','CONTACTED'=>'Em contato','WAITING_CLIENT'=>'Aguardando','SCHEDULED'=>'Agendadas','DONE'=>'Finalizadas'];
$statuses = array_keys($cols);
$by = [];
foreach ($items as $i) { $by[$i['status']][] = $i; }
?>
<div class="kanban">
<?php foreach ($cols as $st=>$title): $list = $by[$st] ?? []; $position = array_search($st, $statuses, true); ?>
  <section class="card kanban-col">
    <div class="kanban-col-head"><b><?= $title ?></b><span class="badge" style="background:#eef2ff;color:#3730a3"><?= count($list) ?></span></div>
    <?php foreach ($list as $i): ?>
      <article class="card kcard">
        <b><?= e($i['name']) ?></b>
        <p><?= e($i['service_name'] ?: 'Serviço a definir') ?></p>
        <p><?= e($i['source']) ?> · <?= e($i['phone'] ?: 'sem telefone') ?></p>
        <span><?= e(date('d/m H:i', strtotime($i['created_at']))) ?></span>
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
