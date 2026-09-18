<?php
$items = $items ?? [];
$old = $old ?? [];
$edit = $edit ?? null;
$viewing = $viewing ?? null;
$creating = !empty($creating);
$showForm = $creating || !empty($edit);
$form = is_array($edit) ? $edit : [];
$agendaChecked = old_fill($old, 'show_on_agenda', !empty($form['show_on_agenda']) && $form['show_on_agenda'] !== '0' && $form['show_on_agenda'] !== 'f' ? '1' : '');
?>
<div class="page-head">
  <div>
    <h1>Anotações</h1>
    <p>Livro de plantão da equipe: observações do dia ficam salvas até alguém excluir.</p>
  </div>
  <a class="btn btn-primary" href="/app/anotacoes?novo=1"><?= icon('plus') ?> Nova anotação</a>
</div>

<?php if (!$items): ?>
  <div class="card note-card">
    <div class="empty">
      <b>Nenhuma anotação ainda.</b>
      <div>Registre o que aconteceu no turno para o próximo plantão lembrar.</div>
      <p><a class="btn btn-primary" href="/app/anotacoes?novo=1"><?= icon('plus') ?> Escrever anotação</a></p>
    </div>
  </div>
<?php else: ?>
  <div class="notes-grid">
    <?php foreach ($items as $n):
      $onAgenda = !empty($n['show_on_agenda']) && $n['show_on_agenda'] !== '0' && $n['show_on_agenda'] !== 'f' && $n['show_on_agenda'] !== 'false';
      $can = note_can_manage($user, $n);
      $preview = (string)($n['body'] ?? '');
      if (strlen($preview) > 280) {
          $preview = substr($preview, 0, 277).'...';
      }
    ?>
      <article class="card note-card">
        <div class="note-card-meta">
          <time datetime="<?= e(substr((string)$n['note_date'], 0, 10)) ?>"><?= e(date('d/m/Y', strtotime((string)$n['note_date']))) ?></time>
          <?php if ($onAgenda): ?>
            <span class="badge" style="background:#fff7ed;color:#9a3412">Na agenda</span>
          <?php endif; ?>
        </div>
        <h2><?= e($n['title']) ?></h2>
        <div class="note-body"><?= e($preview) ?></div>
        <div class="note-card-foot">
          <small><?= e($n['author_name'] ?: 'Equipe') ?></small>
          <div class="row-actions">
            <a class="btn btn-ghost" href="/app/anotacoes?ver=<?= e($n['id']) ?>">Ver</a>
            <?php if ($can): ?>
              <a class="btn btn-ghost" href="/app/anotacoes?edit=<?= e($n['id']) ?>">Editar</a>
              <form method="post" action="/app/anotacoes/excluir" style="display:inline" onsubmit="return confirm('Excluir esta anotação?')">
                <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($n['id']) ?>">
                <button class="btn btn-ghost" type="submit">Excluir</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($showForm): ?>
<div class="overlay" role="presentation">
  <form method="post" action="/app/anotacoes/salvar" class="card overlay-panel note-form" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px"><?= $form ? 'Editar anotação' : 'Nova anotação' ?></h2>
      <a class="btn btn-ghost" href="/app/anotacoes">Fechar</a>
    </div>
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <?php if ($form): ?><input type="hidden" name="id" value="<?= e($form['id']) ?>"><?php endif; ?>
    <label class="label">Título</label>
    <input class="input" name="title" maxlength="140" required value="<?= e(old_fill($old, 'title', (string)($form['title'] ?? ''))) ?>" placeholder="Ex.: Plantão 18/09 — troca de chave">
    <div class="grid g2">
      <div>
        <label class="label">Data</label>
        <input class="input" type="date" name="note_date" required value="<?= e(old_fill($old, 'note_date', substr((string)($form['note_date'] ?? date('Y-m-d')), 0, 10))) ?>">
      </div>
      <div>
        <label class="label" style="visibility:hidden">Agenda</label>
        <label class="check-row" style="margin-top:8px">
          <input type="checkbox" name="show_on_agenda" value="1" <?= $agendaChecked === '1' || strcasecmp($agendaChecked, 'on') === 0 ? 'checked' : '' ?>>
          SALVAR NA AGENDA
        </label>
      </div>
    </div>
    <label class="label">O que aconteceu</label>
    <textarea class="textarea" name="body" rows="8" maxlength="8000" required placeholder="Observações do turno, ocorrências e recados para o próximo plantão."><?= e(old_fill($old, 'body', (string)($form['body'] ?? ''))) ?></textarea>
    <p style="margin-top:12px"><button class="btn btn-primary">Salvar anotação</button> <a class="btn btn-ghost" href="/app/anotacoes">Cancelar</a></p>
  </form>
</div>
<?php endif; ?>

<?php if (!$showForm && $viewing):
  $onAgendaV = !empty($viewing['show_on_agenda']) && $viewing['show_on_agenda'] !== '0' && $viewing['show_on_agenda'] !== 'f';
  $canV = note_can_manage($user, $viewing);
?>
<div class="overlay" role="presentation">
  <div class="card overlay-panel note-form" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
      <h2 style="margin:0;font-size:17px"><?= e($viewing['title']) ?></h2>
      <a class="btn btn-ghost" href="/app/anotacoes">Fechar</a>
    </div>
    <p style="color:#667085;font-size:13px;margin:8px 0 12px">
      <?= e(date('d/m/Y', strtotime((string)$viewing['note_date']))) ?>
      · <?= e($viewing['author_name'] ?: 'Equipe') ?>
      <?php if ($onAgendaV): ?> · Na agenda<?php endif; ?>
    </p>
    <div class="note-body" style="white-space:pre-wrap"><?= e((string)$viewing['body']) ?></div>
    <div class="note-card-foot" style="margin-top:14px">
      <div class="row-actions">
        <?php if ($canV): ?>
          <a class="btn btn-primary" href="/app/anotacoes?edit=<?= e($viewing['id']) ?>">Editar</a>
          <form method="post" action="/app/anotacoes/excluir" style="display:inline" onsubmit="return confirm('Excluir esta anotação?')">
            <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($viewing['id']) ?>">
            <button class="btn btn-ghost" type="submit">Excluir</button>
          </form>
        <?php endif; ?>
        <a class="btn btn-ghost" href="/app/anotacoes">Voltar</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
