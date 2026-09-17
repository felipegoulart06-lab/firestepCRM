<?php
$fil = $_GET['f'] ?? 'ALL';
$map = ['ALL'=>'Todas','NEW'=>'Novas','CONTACT'=>'Em atendimento','SCHEDULED'=>'Agendadas','DONE'=>'Finalizadas','ARCHIVED'=>'Arquivadas'];
$counts = ['NEW'=>0,'CONTACTED'=>0,'WAITING_CLIENT'=>0,'SCHEDULED'=>0,'DONE'=>0,'ARCHIVED'=>0];
foreach ($requests as $r) { if (isset($counts[$r['status']])) $counts[$r['status']]++; }
$detail = $detail ?? null;
$confirmConvert = !empty($confirmConvert);
$canConvert = static function (array $r): bool {
    return !in_array($r['status'], ['SCHEDULED','ARCHIVED'], true);
};
?>
<div class="page-head">
  <div>
    <h1>Solicitações</h1>
    <p class="subtitle">Pedidos recebidos pelo site, WhatsApp e integrações. Não se cadastram por aqui.</p>
  </div>
</div>
<div class="grid g4" style="margin:10px 0 14px">
  <div class="card stat"><span class="stat-label">Novas</span><b><?= $counts['NEW'] ?></b></div>
  <div class="card stat"><span class="stat-label">Em atendimento</span><b><?= $counts['CONTACTED']+$counts['WAITING_CLIENT'] ?></b></div>
  <div class="card stat"><span class="stat-label">Agendadas</span><b><?= $counts['SCHEDULED'] ?></b></div>
  <div class="card stat"><span class="stat-label">Finalizadas</span><b><?= $counts['DONE'] ?></b></div>
</div>
<div class="tabs" style="margin:0 0 12px">
  <?php foreach ($map as $k=>$l): ?>
    <a class="<?= $fil===$k?'active':'' ?>" href="/app/solicitacoes?f=<?= e($k) ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>
<div class="card table-wrap">
<?php
$show = array_filter($requests, function($r) use ($fil) {
    if ($fil==='ALL') return true;
    if ($fil==='CONTACT') return in_array($r['status'], ['CONTACTED','WAITING_CLIENT']);
    return $r['status']===$fil;
});
if (!$show): ?>
  <div class="empty"><b>Nenhuma solicitação recebida.</b><div>Solicitações enviadas pelo seu site ou integrações aparecerão aqui.</div></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Data</th><th>Cliente</th><th>Telefone</th><th>Serviço</th><th>Data desejada</th><th>Origem</th><th>Status</th><th>Ações</th></tr></thead>
    <tbody>
    <?php foreach ($show as $r):
      $desired = '—';
      if (!empty($r['desired_date'])) {
          $desired = date('d/m/Y', strtotime((string)$r['desired_date']));
          if (!empty($r['desired_time'])) $desired .= ' '.substr((string)$r['desired_time'], 0, 5);
      }
    ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
        <td><b><?= e($r['name']) ?></b></td>
        <td><?= e(phone_fmt($r['phone'] ?? null) ?: '—') ?></td>
        <td><?= e($r['service_name'] ?: '—') ?></td>
        <td><?= e($desired) ?></td>
        <td><?= e($r['source']) ?></td>
        <td><?= badge_req($r['status']) ?></td>
        <td>
          <div class="row-actions">
            <a class="btn btn-ghost" href="/app/solicitacoes?ver=<?= e($r['id']) ?><?= $fil!=='ALL'?'&f='.e($fil):'' ?>">Detalhes</a>
            <?php if ($canConvert($r)): ?>
            <a class="btn btn-primary" href="/app/solicitacoes?ver=<?= e($r['id']) ?>&amp;converter=1">Converter</a>
            <?php endif; ?>
            <?php if ($r['status'] !== 'ARCHIVED'): ?>
            <form method="post" action="/app/solicitacoes/status" onsubmit="return confirm('Arquivar esta solicitação? Ela sai do fluxo ativo.')">
              <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
              <input type="hidden" name="id" value="<?= e($r['id']) ?>">
              <input type="hidden" name="status" value="ARCHIVED">
              <input type="hidden" name="f" value="<?= e($fil) ?>">
              <button class="btn btn-danger" type="submit">Arquivar</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?php if ($detail && !$confirmConvert):
  $payload = [];
  if (!empty($detail['metadata'])) {
      $decoded = json_decode($detail['metadata'], true);
      $payload = is_array($decoded) ? $decoded : [];
  }
?>
<div class="overlay" role="presentation">
  <div class="card overlay-panel" style="max-width:560px;padding:18px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0;font-size:17px">Detalhes da solicitação</h2>
      <button class="btn btn-ghost" type="button" data-close-modal>Fechar</button>
    </div>
    <div class="detail-grid" style="margin-top:12px">
      <div class="detail-item"><small>Cliente</small><strong><?= e($detail['name']) ?></strong></div>
      <div class="detail-item"><small>Status</small><strong><?= badge_req($detail['status']) ?></strong></div>
      <div class="detail-item"><small>Telefone</small><strong><?= e(phone_fmt($detail['phone'] ?? null)) ?></strong></div>
      <div class="detail-item"><small>E-mail</small><strong><?= e($detail['email'] ?: 'Não informado') ?></strong></div>
      <div class="detail-item"><small>Serviço</small><strong><?= e($detail['service_name'] ?: 'A definir') ?></strong></div>
      <div class="detail-item"><small>Origem</small><strong><?= e($detail['source']) ?></strong></div>
      <div class="detail-item"><small>Data desejada</small><strong><?= e(!empty($detail['desired_date']) ? date('d/m/Y', strtotime($detail['desired_date'])) : 'Não informada') ?></strong></div>
      <div class="detail-item"><small>Horário desejado</small><strong><?= e($detail['desired_time'] ?: 'Não informado') ?></strong></div>
      <?php if (!empty($detail['message'])): ?><div class="detail-item detail-wide"><small>Mensagem</small><strong><?= nl2br(e($detail['message'])) ?></strong></div><?php endif; ?>
      <div class="detail-item detail-wide"><small>Recebido em</small><strong><?= e(date('d/m/Y H:i', strtotime($detail['created_at']))) ?></strong></div>
    </div>
    <?php if ($payload): ?>
      <details style="margin-top:10px">
        <summary class="btn btn-ghost">Ver dados recebidos</summary>
        <pre class="payload"><?= e(json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre>
      </details>
    <?php endif; ?>
    <div class="row-actions" style="margin-top:14px">
      <?php if ($canConvert($detail)): ?>
        <a class="btn btn-primary" href="/app/solicitacoes?ver=<?= e($detail['id']) ?>&amp;converter=1">Converter em agendamento</a>
      <?php endif; ?>
      <button class="btn btn-ghost" type="button" data-close-modal>Fechar</button>
    </div>
  </div>
</div>
<?php elseif ($confirmConvert && $detail): ?>
<div class="overlay" role="presentation">
  <div class="card overlay-panel" style="max-width:460px;padding:18px" onclick="event.stopPropagation()">
    <h2 style="margin-top:0;font-size:17px">Converter solicitação?</h2>
    <p style="color:#475467">Deseja realmente converter a solicitação de <b><?= e($detail['name']) ?></b> em agendamento?</p>
    <p style="color:#667085;font-size:13px">O horário <?= e(trim((!empty($detail['desired_date']) ? date('d/m/Y', strtotime($detail['desired_date'])) : date('d/m/Y')).' '.($detail['desired_time'] ?: '09:00'))) ?> será criado na agenda e o registro aparecerá em Agendamentos.</p>
    <form method="post" action="/app/solicitacoes/converter">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($detail['id']) ?>">
      <label class="label">Serviço da reserva</label>
      <?php if (!$services): ?>
        <p class="settings-hint">Nenhum serviço ativo no catálogo. Cadastre em <a href="/app/servicos">Serviços</a> para converter esta solicitação.</p>
      <?php else: ?>
        <select class="select" name="service_id" required>
          <option value="">Selecione</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= e($s['id']) ?>" <?= (($detail['service_id'] ?? '') === $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?> · <?= (int)$s['duration_minutes'] ?> min</option>
          <?php endforeach; ?>
        </select>
        <p class="settings-hint">A duração do serviço define quanto tempo o horário fica reservado.</p>
      <?php endif; ?>
      <div class="row-actions" style="margin-top:12px">
        <button class="btn btn-primary" type="submit" <?= $services ? '' : 'disabled' ?>>Sim, converter</button>
        <a class="btn btn-ghost" href="/app/solicitacoes?ver=<?= e($detail['id']) ?>">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
