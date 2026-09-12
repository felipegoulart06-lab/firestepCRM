<?php $c = $client ?? []; $isNew = empty($c['id']); ?>
<h1><?= $isNew ? 'Novo cadastro' : e($c['name']) ?></h1>
<?php if (!$isNew): ?>
<p style="color:#667085"><?= e(phone_fmt($c['phone'])) ?> · <?= e($c['email'] ?: 'sem e-mail') ?> · <?= e($c['source']) ?></p>
<div class="grid g4" style="margin:12px 0">
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Último</div><b><?= e($last ?: '—') ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Próximo</div><b><?= e($next ?: '—') ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Atendimentos</div><b><?= (int)$totalAp ?></b></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#667085">Solicitações</div><b><?= (int)$totalReq ?></b></div>
</div>
<p>
  <a class="btn btn-primary" href="/app/agenda?new=1&amp;client_id=<?= e($c['id']) ?>&amp;from=clientes"><?= icon('plus') ?> Novo agendamento</a>
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
<form method="post" action="/app/clientes/salvar" class="card" style="padding:20px;max-width:760px" >
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= e($c['id']) ?>"><?php endif; ?>
  <div class="grid g2">
    <div><label class="label">Nome completo</label><input class="input" name="name" value="<?= e($c['name'] ?? '') ?>" required></div>
    <div><label class="label">Origem</label>
      <select class="select" name="source"><?php foreach (['Site','WhatsApp','Instagram','Google','Indicação','Telefone','Presencial','Webhook','Outro'] as $o): ?>
        <option <?= (($c['source']??'')===$o)?'selected':'' ?>><?= $o ?></option>
      <?php endforeach; ?></select>
    </div>
    <div><label class="label">Telefone</label><input class="input" name="phone" value="<?= e($c['phone'] ?? '') ?>"></div>
    <div><label class="label">WhatsApp</label><input class="input" name="whatsapp" value="<?= e($c['whatsapp'] ?? '') ?>"></div>
    <div><label class="label">E-mail</label><input class="input" name="email" value="<?= e($c['email'] ?? '') ?>"></div>
    <div><label class="label">CPF (opcional)</label><input class="input" name="cpf" value="<?= e($c['cpf'] ?? '') ?>"></div>
    <div><label class="label">Nascimento</label><input class="input" type="date" name="birth_date" value="<?= e($c['birth_date'] ?? '') ?>"></div>
    <div><label class="label">Status</label>
      <select class="select" name="status">
        <option value="ACTIVE" <?= (($c['status']??'ACTIVE')==='ACTIVE')?'selected':'' ?>>Ativo</option>
        <option value="INACTIVE" <?= (($c['status']??'')==='INACTIVE')?'selected':'' ?>>Inativo</option>
      </select>
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
  <div style="margin-top:12px"><label class="label">Observações</label><textarea class="textarea" name="notes"><?= e($c['notes'] ?? '') ?></textarea></div>
  <p><button class="btn btn-primary">Salvar</button></p>
</form>
