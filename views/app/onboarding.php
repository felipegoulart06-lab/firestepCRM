<?php $step = (int)($_GET['step'] ?? 1); $hours = json_arr($tenant['business_hours']?:'{}', default_hours()); $days=['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado']; ?>
<div class="card" style="max-width:640px;margin:24px auto;padding:28px">
  <div style="font-size:13px;color:#2563eb;font-weight:700">PASSO <?= $step ?> DE 4</div>
  <?php if ($step===1): ?><h1>Complete seus dados</h1><p>Confirme o nome que aparece no seu painel.</p>
  <?php elseif ($step===2): ?><h1>Horário de atendimento</h1><p>Isso evita agendamentos fora do expediente.</p>
  <?php elseif ($step===3): ?><h1>Seus serviços</h1><p>Já deixamos alguns modelos. Você pode alterar depois.</p>
    <ul><?php foreach ($services as $s): ?><li><?= e($s['name']) ?></li><?php endforeach; ?></ul>
  <?php else: ?><h1>Seu painel está pronto</h1><p>Clientes, agenda, solicitações e webhooks já estão disponíveis.</p><?php endif; ?>
  <form method="post" action="/app/onboarding" class="grid">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="step" value="<?= $step ?>">
    <?php if ($step===1): ?>
      <label class="label">Nome comercial</label><input class="input" name="business_name" value="<?= e($tenant['business_name']) ?>">
      <label class="label">Telefone</label><input class="input" name="phone" value="<?= e($tenant['phone']) ?>">
      <label class="label">WhatsApp</label><input class="input" name="whatsapp" value="<?= e($tenant['whatsapp']) ?>">
    <?php endif; ?>
    <?php if ($step===2): for ($d=0;$d<=6;$d++): $h=$hours[$d]??['closed'=>false,'start'=>'08:00','end'=>'18:00']; ?>
      <div class="grid" style="grid-template-columns:140px 1fr 1fr">
        <label><?= $days[$d] ?> <input type="checkbox" name="closed_<?= $d ?>" <?= !empty($h['closed'])?'checked':'' ?>> Fechado</label>
        <input class="input" type="time" name="start_<?= $d ?>" value="<?= e($h['start']??'08:00') ?>">
        <input class="input" type="time" name="end_<?= $d ?>" value="<?= e($h['end']??'18:00') ?>">
      </div>
    <?php endfor; endif; ?>
    <button class="btn btn-primary"><?= $step<4?'Continuar':'Começar a usar' ?></button>
  </form>
</div>
