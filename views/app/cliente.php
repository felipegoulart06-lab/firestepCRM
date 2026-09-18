<?php
$c = $client ?? [];
$old = $old ?? [];
$fields = $fields ?? [];
$values = $values ?? [];
$appts = $appts ?? [];
$isNew = empty($c['id']);
$viewOnly = !$isNew && !empty($viewOnly);
$kind = old_fill($old, 'document_kind', br_doc_kind_from_value($c['cpf'] ?? '') ?: '');
$fill = static fn(string $key, ?string $fallback = '') => old_fill($old, $key, $c[$key] ?? $fallback);
$show = static fn($v) => ($v !== null && trim((string)$v) !== '') ? (string)$v : '—';
?>
<h1><?= $isNew ? 'Novo cadastro' : ($viewOnly ? e($c['name']) : 'Editar cadastro') ?></h1>
<?php if (!$isNew): ?>
<p style="color:#667085"><?= e(phone_fmt($c['phone'])) ?> · <?= e($c['email'] ?: 'sem e-mail') ?> · <?= e($c['source']) ?></p>
<div class="grid g4" style="margin:12px 0">
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Último</div><b><?= e($last ?: '—') ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Próximo</div><b><?= e($next ?: '—') ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Atendimentos</div><b><?= (int)$totalAp ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Solicitações</div><b><?= (int)$totalReq ?></b></div>
</div>
<p>
  <?php if ($viewOnly): ?>
    <a class="btn btn-primary" href="/app/clientes/editar?id=<?= e($c['id']) ?>">Editar</a>
  <?php else: ?>
    <a class="btn btn-ghost" href="/app/clientes/ver?id=<?= e($c['id']) ?>">Voltar à visualização</a>
  <?php endif; ?>
  <a class="btn btn-ghost" href="/app/agendamentos?new=1&amp;client_id=<?= e($c['id']) ?>&amp;from=clientes"><?= icon('plus') ?> Novo agendamento</a>
  <a class="btn btn-ghost" href="/app/clientes/resumo.pdf?id=<?= e($c['id']) ?>"><?= icon('download') ?> Baixar resumo em PDF</a>
</p>
<div class="card" style="padding:16px;margin-bottom:16px">
  <h3 style="margin-top:0">Agendamentos</h3>
  <?php foreach ($appts as $a): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9">
      <span><?= e(date('d/m/Y H:i', strtotime($a['starts_at']))) ?> · <?= e($a['service_name'] ?? '') ?></span>
      <?= badge_appt($a['status']) ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$appts): ?><p style="color:#667085">Nenhum atendimento ainda.</p><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($viewOnly):
  $docLabel = $kind === 'cnpj' ? 'CNPJ' : ($kind === 'cpf' ? 'CPF' : 'Documento');
  $nameLabel = $kind === 'cnpj' ? 'Razão social' : 'Nome';
?>
<div class="card" style="padding:20px;max-width:760px">
  <h3 style="margin:0 0 12px">Dados do cadastro</h3>
  <div class="detail-grid">
    <div class="detail-item"><small>Tipo</small><strong><?= $kind==='cnpj' ? 'Cliente CNPJ' : ($kind==='cpf' ? 'Cliente CPF' : '—') ?></strong></div>
    <div class="detail-item"><small><?= e($docLabel) ?></small><strong><?= e($show($c['cpf'] ? format_br_document($c['cpf'], $kind ?: null) : '')) ?></strong></div>
    <div class="detail-item"><small><?= e($nameLabel) ?></small><strong><?= e($show($c['name'] ?? '')) ?></strong></div>
    <?php if ($kind === 'cpf'): ?>
      <div class="detail-item"><small>Nascimento</small><strong><?= e(!empty($c['birth_date']) ? date('d/m/Y', strtotime((string)$c['birth_date'])) : '—') ?></strong></div>
    <?php endif; ?>
    <?php if ($kind === 'cnpj'): ?>
      <div class="detail-item"><small>Nome fantasia</small><strong><?= e($show($c['trade_name'] ?? '')) ?></strong></div>
      <div class="detail-item"><small>Inscrição estadual</small><strong><?= e($show($c['state_registration'] ?? '')) ?></strong></div>
      <div class="detail-item"><small>Responsável / contato</small><strong><?= e($show($c['contact_name'] ?? '')) ?></strong></div>
    <?php endif; ?>
    <div class="detail-item"><small>Telefone</small><strong><?= e(phone_fmt($c['phone'] ?? null) ?: '—') ?></strong></div>
    <div class="detail-item"><small>WhatsApp</small><strong><?= e(phone_fmt($c['whatsapp'] ?? null) ?: '—') ?></strong></div>
    <div class="detail-item"><small>E-mail</small><strong><?= e($show($c['email'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Origem</small><strong><?= e($show($c['source'] ?? '')) ?></strong></div>
    <div class="detail-item"><small>Status</small><strong><?= ($c['status'] ?? '') === 'INACTIVE' ? 'Inativo' : 'Ativo' ?></strong></div>
    <?php if ($kind === 'cnpj'): ?>
      <div class="detail-item detail-wide"><small>Endereço</small><strong><?= e(trim(implode(' · ', array_filter([$c['address'] ?? '', $c['city'] ?? '', $c['state'] ?? '', $c['cep'] ?? '']))) ?: '—') ?></strong></div>
    <?php endif; ?>
    <?php foreach ($fields as $f): $val = $values[$f['key']] ?? ''; ?>
      <div class="detail-item"><small><?= e($f['label']) ?></small><strong><?= e($show($val)) ?></strong></div>
    <?php endforeach; ?>
    <?php if (!empty($c['notes'])): ?>
      <div class="detail-item detail-wide"><small>Observações</small><strong><?= nl2br(e($c['notes'])) ?></strong></div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<form method="post" action="/app/clientes/salvar" class="card" style="padding:20px;max-width:760px" data-client-kind>
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= e($c['id']) ?>"><?php endif; ?>
  <div data-doc-group>
    <label class="label">Este cadastro é de</label>
    <div class="choice-switch" role="radiogroup" aria-label="Tipo de cliente">
      <label>
        <input class="js-doc-kind" type="radio" name="document_kind" value="cpf" <?= $kind==='cpf'?'checked':'' ?> required>
        <span>Cliente CPF</span>
      </label>
      <label>
        <input class="js-doc-kind" type="radio" name="document_kind" value="cnpj" <?= $kind==='cnpj'?'checked':'' ?> required>
        <span>Cliente CNPJ</span>
      </label>
    </div>
  </div>
  <div id="client-kind-body" <?= $kind==='' ? 'hidden' : '' ?>>
    <div class="grid g2">
      <div>
        <label class="label" id="client-name-label"><?= $kind==='cnpj' ? 'Razão social' : 'Nome completo' ?></label>
        <input class="input" name="name" required value="<?= e($fill('name')) ?>">
      </div>
      <div data-doc-group-number>
        <label class="label js-doc-label"><?= $kind==='cnpj' ? 'CNPJ' : ($kind==='cpf' ? 'CPF' : 'Número do documento') ?></label>
        <input class="input js-doc-number" name="cpf" value="<?= e($fill('cpf') ? format_br_document($fill('cpf'), $kind ?: null) : '') ?>" required data-required="1" inputmode="numeric" autocomplete="off" maxlength="18" placeholder="<?= $kind==='cnpj' ? '00.000.000/0001-00' : '000.000.000-00' ?>">
      </div>
      <div class="js-cpf-only">
        <label class="label">Nascimento</label>
        <input class="input" type="date" name="birth_date" value="<?= e($fill('birth_date')) ?>">
      </div>
      <div class="js-cnpj-only">
        <label class="label">Nome fantasia</label>
        <input class="input" name="trade_name" value="<?= e($fill('trade_name')) ?>" placeholder="Como a empresa é conhecida">
      </div>
      <div class="js-cnpj-only">
        <label class="label">Inscrição estadual</label>
        <input class="input" name="state_registration" value="<?= e($fill('state_registration')) ?>" placeholder="Opcional">
      </div>
      <div class="js-cnpj-only">
        <label class="label">Responsável / contato</label>
        <input class="input" name="contact_name" value="<?= e($fill('contact_name')) ?>">
      </div>
      <div><label class="label">Telefone</label><input class="input" name="phone" value="<?= e($fill('phone')) ?>" required inputmode="tel"></div>
      <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" value="<?= e($fill('whatsapp')) ?>" inputmode="tel" placeholder="Opcional"></div>
      <div><label class="label">E-mail</label><input class="input" type="email" name="email" value="<?= e($fill('email')) ?>" required></div>
      <div><label class="label">Origem</label>
        <select class="select" name="source"><?php foreach (['Site','WhatsApp','Instagram','Google','Indicação','Telefone','Presencial','Webhook','Outro'] as $o): ?>
          <option <?= ($fill('source','Outro')===$o)?'selected':'' ?>><?= $o ?></option>
        <?php endforeach; ?></select>
      </div>
      <div><label class="label">Status</label>
        <select class="select" name="status">
          <option value="ACTIVE" <?= ($fill('status','ACTIVE')==='ACTIVE')?'selected':'' ?>>Ativo</option>
          <option value="INACTIVE" <?= ($fill('status')==='INACTIVE')?'selected':'' ?>>Inativo</option>
        </select>
      </div>
    </div>
    <div id="cnpj-geo" class="js-cnpj-only geo-box" data-geo-box style="margin-top:12px">
      <div class="fx-block-title">Endereço (obrigatório para CNPJ)</div>
      <p class="muted" style="margin:0 0 8px">Busque na API do mapa. O pin alimenta a Abrangência.</p>
      <div class="geo-search">
        <input class="input geo-q" type="search" autocomplete="off" placeholder="Buscar rua, CEP ou cidade no Brasil">
        <ul class="geo-suggest" hidden></ul>
      </div>
      <div class="geo-map" hidden></div>
      <input type="hidden" name="lat" value="<?= e($fill('lat')) ?>">
      <input type="hidden" name="lng" value="<?= e($fill('lng')) ?>">
      <div class="grid g2" style="margin-top:10px">
      <div><label class="label">Logradouro</label><input class="input" name="address" value="<?= e($fill('address')) ?>" data-req-cnpj placeholder="Rua, número, bairro"></div>
      <div><label class="label">Cidade</label><input class="input" name="city" value="<?= e($fill('city')) ?>" data-req-cnpj></div>
      <div><label class="label">UF</label><input class="input" name="state" maxlength="2" value="<?= e($fill('state')) ?>" data-req-cnpj placeholder="SP"></div>
      <div><label class="label">CEP</label><input class="input" name="cep" value="<?= e($fill('cep')) ?>" data-req-cnpj placeholder="00000-000" inputmode="numeric"></div>
      </div>
    </div>
    <?php if ($fields): ?>
    <div class="grid g2" style="margin-top:12px">
      <?php foreach ($fields as $f): $val = $values[$f['key']] ?? ''; $opts = $f['options'] ? json_decode($f['options'], true) : []; ?>
        <div><label class="label"><?= e($f['label']) ?></label>
          <?php if ($f['type']==='textarea'): ?><textarea class="textarea" name="cf_<?= e($f['key']) ?>"><?= e($val) ?></textarea>
          <?php elseif ($f['type']==='select'): ?>
            <select class="select" name="cf_<?= e($f['key']) ?>"><option value="">Selecionar</option><?php foreach ($opts as $o): ?><option <?= $val===$o?'selected':'' ?>><?= e($o) ?></option><?php endforeach; ?></select>
          <?php else: ?>
            <input class="input" type="<?= $f['type']==='number'?'number':($f['type']==='date'?'date':'text') ?>" name="cf_<?= e($f['key']) ?>" value="<?= e($val) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:12px"><label class="label">Observações</label><textarea class="textarea" name="notes"><?= e($fill('notes')) ?></textarea></div>
    <p>
      <button class="btn btn-primary">Salvar</button>
      <?php if (!$isNew): ?><a class="btn btn-ghost" href="/app/clientes/ver?id=<?= e($c['id']) ?>">Cancelar</a><?php endif; ?>
    </p>
  </div>
</form>
<?php endif; ?>
