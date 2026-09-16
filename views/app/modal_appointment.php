<?php
$date = $edit ? substr($edit['starts_at'],0,10) : ($_GET['date'] ?? date('Y-m-d'));
$start = $edit ? substr($edit['starts_at'],11,5) : ($_GET['start'] ?? '09:00');
$mode = $_GET['block'] ?? '';
$modalClose = $modalClose ?? '/app/agenda';
$forcedClient = $forcedClient ?? null;
$clients = $clients ?? [];
$services = $services ?? [];
$viewOnly = !empty($viewOnly);
$showEditForm = $edit && !$viewOnly;
$allowEditFromDetails = !empty($allowEditFromDetails);
?>
        <div class="overlay" role="presentation">
  <div class="card overlay-panel" style="max-width:<?= $edit?'720':'500' ?>px;padding:18px" onclick="event.stopPropagation()">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2 style="margin:0;font-size:17px"><?php
        if ($viewOnly) echo 'Detalhes da reserva';
        elseif ($edit) echo 'Editar agendamento';
        elseif ($mode) echo 'Bloquear horário';
        else echo 'Novo agendamento';
      ?></h2>
      <a class="btn btn-ghost" href="<?= e($modalClose) ?>">Fechar</a>
    </div>
    <?php if ($edit):
      $metadata = [];
      if (!empty($edit['metadata'])) {
          $decoded = json_decode($edit['metadata'], true);
          $metadata = is_array($decoded) ? $decoded : [];
      }
    ?>
      <div style="display:flex;align-items:center;gap:10px;margin:14px 0 10px">
        <div class="avatar"><?= e(strtoupper(substr($edit['client_name'],0,1))) ?></div>
        <div style="flex:1"><b style="font-size:15px"><?= e($edit['client_name']) ?></b><div style="font-size:12px;color:#667085"><?= e($edit['service_name'] ?: 'Serviço não informado') ?></div></div>
        <?= badge_appt($edit['status']) ?>
      </div>
      <div class="detail-grid">
        <div class="detail-item"><small>Data</small><strong><?= e(date('d/m/Y',strtotime($edit['starts_at']))) ?></strong></div>
        <div class="detail-item"><small>Horário</small><strong><?= e(substr($edit['starts_at'],11,5)) ?> – <?= e(substr($edit['ends_at'],11,5)) ?></strong></div>
        <div class="detail-item"><small>Telefone</small><strong><?= e(phone_fmt($edit['client_phone'] ?: $edit['client_whatsapp'])) ?></strong></div>
        <div class="detail-item"><small>E-mail</small><strong><?= e($edit['client_email'] ?: 'Não informado') ?></strong></div>
        <div class="detail-item"><small>Origem</small><strong><?= e($edit['source']) ?></strong></div>
        <div class="detail-item"><small>Criado em</small><strong><?= e(date('d/m/Y H:i',strtotime($edit['created_at']))) ?></strong></div>
        <?php if ($edit['notes']): ?><div class="detail-item detail-wide"><small>Observações</small><strong><?= nl2br(e($edit['notes'])) ?></strong></div><?php endif; ?>
        <?php if ($edit['request_message']): ?><div class="detail-item detail-wide"><small>Mensagem da solicitação</small><strong><?= nl2br(e($edit['request_message'])) ?></strong></div><?php endif; ?>
        <?php if ($edit['utm_source'] || $edit['utm_medium'] || $edit['utm_campaign']): ?>
          <div class="detail-item detail-wide"><small>Campanha</small><strong><?= e(implode(' · ',array_filter([$edit['utm_source'],$edit['utm_medium'],$edit['utm_campaign']]))) ?></strong></div>
        <?php endif; ?>
        <?php if (($edit['visit_type'] ?? '') === 'externo' && !empty($edit['stops'])): ?>
          <div class="detail-item detail-wide"><small>Atendimento externo</small><strong><?php foreach ($edit['stops'] as $stop): ?><?= e($stop['address']) ?><br><?php endforeach; ?></strong></div>
        <?php endif; ?>
      </div>
      <?php if ($metadata): ?>
        <details style="margin-top:10px">
          <summary class="btn btn-ghost">Ver dados recebidos pelo webhook</summary>
          <pre class="payload"><?= e(json_encode($metadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
      <?php endif; ?>
      <?php if ($viewOnly): ?>
        <div class="row-actions" style="margin-top:14px;justify-content:flex-start">
          <a class="btn btn-ghost" href="/app/agendamentos/reserva.pdf?id=<?= e($edit['id']) ?>"><?= icon('download') ?> PDF</a>
          <?php if ($allowEditFromDetails): ?>
            <a class="btn btn-primary" href="<?= e($editHref ?? '/app/agendamentos?edit='.urlencode($edit['id'])) ?>">Editar</a>
          <?php endif; ?>
        </div>
      <?php elseif ($showEditForm): ?>
      <div style="border-top:1px solid #e4e7ec;margin:14px 0 12px"></div>
      <h3 style="font-size:13px;margin:0 0 8px">Alterar dados</h3>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!$edit && empty($forcedClient) && empty($hideCalendarSwitch)): ?>
      <p><a href="/app/agenda?new=1&date=<?= e($date) ?>&start=<?= e($start) ?>">Agendar</a> · <a href="/app/agenda?new=1&block=1&date=<?= e($date) ?>&start=<?= e($start) ?>">Bloquear horário</a></p>
    <?php endif; ?>
    <?php if ($mode): ?>
      <form method="post" action="/app/agenda/bloquear" class="grid" style="margin-top:10px">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <div><label class="label">Data</label><input class="input" type="date" name="date" value="<?= e($date) ?>" required></div>
        <label><input type="checkbox" name="all_day"> Bloquear dia inteiro</label>
        <div class="grid g2">
          <div><label class="label">Início</label><input class="input" type="time" name="start" value="<?= e($start) ?>"></div>
          <div><label class="label">Fim</label><input class="input" type="time" name="end" value="13:00"></div>
        </div>
        <div><label class="label">Motivo</label><input class="input" name="reason" placeholder="Almoço, compromisso pessoal..."></div>
        <button class="btn btn-primary">Bloquear</button>
      </form>
    <?php elseif (!$viewOnly): ?>
      <form method="post" action="/app/agenda/salvar" class="grid" style="margin-top:10px">
        <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
        <input type="hidden" name="return_to" value="<?= e($modalClose) ?>">
        <?php if ($edit): ?>
          <input type="hidden" name="id" value="<?= e($edit['id']) ?>">
          <input type="hidden" name="allow_edit" value="1">
        <?php endif; ?>
        <?php if (!empty($_GET['request_id'])): ?><input type="hidden" name="request_id" value="<?= e($_GET['request_id']) ?>"><?php endif; ?>
        <?php if (!empty($_GET['from'])): ?><input type="hidden" name="from" value="<?= e((string)$_GET['from']) ?>"><?php endif; ?>
        <?php if (!$edit && !empty($forcedClient)): ?>
          <input type="hidden" name="lock_client_id" value="<?= e($forcedClient['id']) ?>">
          <input type="hidden" name="client_id" value="<?= e($forcedClient['id']) ?>">
          <div class="forced-client">
            <small>Cliente deste agendamento</small>
            <strong><?= e($forcedClient['name']) ?></strong>
            <span><?= e(phone_fmt($forcedClient['phone'] ?? $forcedClient['whatsapp'] ?? null)) ?><?= !empty($forcedClient['email']) ? ' · '.e($forcedClient['email']) : '' ?></span>
            <em>Escolhido no cadastro. Não é possível trocar aqui.</em>
          </div>
        <?php elseif ($edit): ?>
        <div><label class="label">Cliente</label>
          <select class="select" name="client_id" required>
            <option value="">Selecionar...</option>
            <?php foreach ($clients as $c): ?>
              <option value="<?= e($c['id']) ?>" <?= ($edit['client_id']===$c['id'])?'selected':'' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <div>
          <label class="label">Quem vai ser atendido?</label>
          <div class="choice-switch" role="radiogroup" aria-label="Tipo de cliente">
            <label>
              <input type="radio" name="client_mode" value="existing" required onchange="syncClientMode(this.form)">
              <span>Cliente cadastrado</span>
            </label>
            <label>
              <input type="radio" name="client_mode" value="new" required onchange="syncClientMode(this.form)">
              <span>Cliente novo</span>
            </label>
          </div>
          <div id="client-existing" hidden>
            <label class="label">Selecionar na lista</label>
            <?php if (!$clients): ?>
              <p class="settings-hint">Nenhum cliente ativo cadastrado. Escolha Cliente novo.</p>
            <?php else: ?>
            <select class="select" name="client_id">
              <option value="">Escolha o cliente...</option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?><?= !empty($c['phone']) ? ' · '.e($c['phone']) : '' ?></option>
              <?php endforeach; ?>
            </select>
            <?php endif; ?>
          </div>
          <div id="client-new" hidden>
            <p class="settings-hint" style="margin:0 0 8px">Preencha os dados para criar o cadastro junto com o horário.</p>
            <label class="label">Nome completo</label>
            <input class="input" name="new_name" data-req autocomplete="name">
            <div class="grid g2" style="margin-top:8px">
              <div>
                <label class="label">Telefone</label>
                <input class="input" name="new_phone" data-req autocomplete="tel">
              </div>
              <div>
                <label class="label">WhatsApp</label>
                <input class="input" name="new_whatsapp" autocomplete="tel">
              </div>
            </div>
            <label class="label">E-mail</label>
            <input class="input" type="email" name="new_email" autocomplete="email">
          </div>
        </div>
        <?php endif; ?>
        <div>
          <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
            <label class="label" style="margin:0">Serviço</label>
            <?php if (!is_user_agent($user ?? null)): ?>
              <a class="muted" href="/app/servicos" style="text-decoration:underline;text-underline-offset:2px">editar serviços</a>
            <?php endif; ?>
          </div>
          <select class="select" name="service_id" required style="margin-top:6px">
            <option value="">Selecione</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= e($s['id']) ?>" <?= ($edit && ($edit['service_id']??'')===$s['id'])?'selected':'' ?>><?= e($s['name']) ?> · <?= (int)$s['duration_minutes'] ?> min</option>
            <?php endforeach; ?>
          </select>
          <?php if (!$services): ?><p class="muted">Cadastre um serviço em Serviços para definir a duração do atendimento.</p><?php endif; ?>
        </div>
        <div class="grid g2">
          <div><label class="label">Data</label><input class="input" type="date" name="date" value="<?= e($date) ?>" required></div>
          <div><label class="label">Início</label><input class="input" type="time" name="start" value="<?= e($start) ?>" required></div>
        </div>
        <p class="muted" style="margin:0">O término usa a duração do serviço (Serviços → Agenda e valor). Se esse período cruzar outro agendamento, não será possível salvar até excluir ou alterar o horário ocupado.</p>
        <div><label class="label">Status</label>
          <select class="select" name="status">
            <?php foreach (APPT_STATUS as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ((is_array($edit) ? ($edit['status'] ?? '') : 'SCHEDULED')===$k)?'selected':'' ?>><?= e($v[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (is_user_crm($user ?? null)):
          $isExternal = is_array($edit) && (($edit['visit_type'] ?? '') === 'externo');
          $stopLines = is_array($edit) ? array_values(array_filter(array_map(static fn($s) => trim((string)($s['address'] ?? '')), $edit['stops'] ?? []))) : [];
          if (!$stopLines) $stopLines = [''];
        ?>
        <div data-external-visit>
          <label class="fx-check" style="margin:0">
            <input type="checkbox" name="external_visit" value="1" <?= $isExternal ? 'checked' : '' ?>>
            <span>Atendimento externo (fora do ambiente de atendimento)</span>
          </label>
          <p class="muted" style="margin:6px 0 0">Marque para informar um ou mais endereços. Cada endereço vira um pin verde no mapa de Abrangência.</p>
          <div data-visit-fields <?= $isExternal ? '' : 'hidden' ?> style="margin-top:10px">
            <div data-visit-list class="grid" style="gap:8px">
              <?php foreach ($stopLines as $i => $line):
                $stop = is_array($edit) ? (($edit['stops'] ?? [])[$i] ?? []) : [];
              ?>
                <div class="geo-line" data-geo-line>
                  <input class="input geo-q" name="visit_addresses[]" value="<?= e($line) ?>" placeholder="Buscar endereço no mapa" autocomplete="off">
                  <ul class="geo-suggest" hidden></ul>
                  <input type="hidden" name="visit_lats[]" value="<?= e((string)($stop['lat'] ?? '')) ?>">
                  <input type="hidden" name="visit_lngs[]" value="<?= e((string)($stop['lng'] ?? '')) ?>">
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-ghost" data-visit-add style="margin-top:8px"><?= icon('plus') ?> Outro endereço</button>
          </div>
        </div>
        <?php endif; ?>
        <div><label class="label">Observações</label><textarea class="textarea" name="notes"><?= e(is_array($edit) ? ($edit['notes'] ?? '') : '') ?></textarea></div>
        <button class="btn btn-primary"><?= $edit ? 'Salvar' : 'Criar agendamento' ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php if (!$edit && empty($forcedClient) && empty($mode)): ?>
<script>
function syncClientMode(form){
  if(!form) return;
  const mode = (form.querySelector('input[name="client_mode"]:checked')||{}).value;
  const existingBox = form.querySelector('#client-existing');
  const newBox = form.querySelector('#client-new');
  if(!existingBox || !newBox) return;
  existingBox.hidden = mode !== 'existing';
  newBox.hidden = mode !== 'new';
  const sel = existingBox.querySelector('select[name="client_id"]');
  if(sel) sel.required = mode === 'existing';
  newBox.querySelectorAll('[data-req]').forEach(function(el){ el.required = mode === 'new'; });
}
</script>
<?php endif; ?>
