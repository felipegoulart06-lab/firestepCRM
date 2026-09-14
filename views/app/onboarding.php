<?php
$step = max(1, min(4, (int)($_GET['step'] ?? 1)));
$hours = json_arr($tenant['business_hours'] ?: '{}', default_hours());
$days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
$labels = ['Dados', 'Horários', 'Serviços', 'Pronto'];
$titles = [
    1 => 'Como o cliente te encontra',
    2 => 'Quando você atende',
    3 => 'Serviços do seu painel',
    4 => 'Tudo pronto para começar',
];
$subs = [
    1 => 'Esse nome aparece no menu, nos agendamentos e no dossiê. Confirme telefone e WhatsApp para contato.',
    2 => 'A agenda só libera horários dentro deste expediente. Você pode mudar depois em Configurações.',
    3 => 'Já deixamos modelos do seu segmento. Preço e duração entram automaticamente ao marcar um horário.',
    4 => 'Clientes, agenda e solicitações já estão no seu CRM. Solicitações entram só de fora — pelo site ou webhook.',
];
$pct = (int) round(($step / 4) * 100);
$old = $old ?? [];
?>
<section class="lock-card card onboard-card">
  <p class="lock-kicker">Primeiro acesso · <?= $step ?> de 4</p>
  <ol class="onboard-steps" aria-label="Progresso">
    <?php foreach ($labels as $i => $lab): $n = $i + 1; $cls = $n < $step ? 'is-done' : ($n === $step ? 'is-current' : ''); ?>
      <li class="<?= $cls ?>"><span><?= $n ?></span><?= e($lab) ?></li>
    <?php endforeach; ?>
  </ol>
  <div class="onboard-bar" aria-hidden="true"><i style="width:<?= $pct ?>%"></i></div>
  <h1><?= e($titles[$step]) ?></h1>
  <p class="subtitle"><?= e($subs[$step]) ?></p>

  <form method="post" action="/app/onboarding" class="onboard-form" id="onboard-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="step" value="<?= $step ?>">

    <?php if ($step === 1): ?>
      <label class="label" for="ob-name">Nome comercial</label>
      <input class="input" id="ob-name" name="business_name" required maxlength="120" value="<?= e(old_fill($old, 'business_name', $tenant['business_name'] ?: $tenant['display_name'])) ?>" placeholder="Como aparece no painel">

      <label class="label" for="ob-phone">Telefone</label>
      <input class="input" id="ob-phone" name="phone" required inputmode="tel" autocomplete="tel" value="<?= e(old_fill($old, 'phone', $tenant['phone'])) ?>" placeholder="(47) 99999-0000">

      <label class="label" for="ob-wa">WhatsApp <span class="onboard-opt">opcional</span></label>
      <input class="input" id="ob-wa" name="whatsapp" inputmode="tel" value="<?= e(old_fill($old, 'whatsapp', $tenant['whatsapp'])) ?>" placeholder="Se vazio, usamos o telefone">
      <button type="button" class="onboard-link" id="copy-wa">Usar o mesmo número do telefone</button>
    <?php endif; ?>

    <?php if ($step === 2): ?>
      <div class="onboard-presets">
        <span>Atalho</span>
        <button type="button" class="btn btn-ghost" data-preset="weekdays">Seg–sex 8h–18h</button>
        <button type="button" class="btn btn-ghost" data-preset="sat">+ sábado de manhã</button>
      </div>
      <div class="onboard-hours-head"><span>Dia</span><span>Fecha</span><span>Abre</span><span>Fecha</span></div>
      <?php for ($d = 0; $d <= 6; $d++):
          $h = $hours[$d] ?? $hours[(string)$d] ?? ['closed' => false, 'start' => '08:00', 'end' => '18:00'];
          $closed = !empty($h['closed']);
      ?>
        <div class="onboard-hours-row" data-day="<?= $d ?>">
          <b><?= $days[$d] ?></b>
          <label class="check-row onboard-closed"><input type="checkbox" name="closed_<?= $d ?>" class="js-closed" <?= $closed ? 'checked' : '' ?>> Fechado</label>
          <input class="input js-start" type="time" name="start_<?= $d ?>" value="<?= e($h['start'] ?? '08:00') ?>" <?= $closed ? 'disabled' : '' ?>>
          <input class="input js-end" type="time" name="end_<?= $d ?>" value="<?= e($h['end'] ?? '18:00') ?>" <?= $closed ? 'disabled' : '' ?>>
        </div>
      <?php endfor; ?>
    <?php endif; ?>

    <?php if ($step === 3): ?>
      <?php if (!$services): ?>
        <p class="onboard-empty">Nenhum serviço modelo ainda. Você cadastra depois em <strong>Serviços</strong>.</p>
      <?php else: ?>
        <ul class="onboard-services">
          <?php foreach ($services as $s): ?>
            <li>
              <strong><?= e($s['name']) ?></strong>
              <span><?= (int)($s['duration_minutes'] ?? 60) ?> min · <?= e(money((float)($s['price'] ?? 0))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="onboard-hint">Nada é cobrado daqui. É só a lista usada na agenda.</p>
    <?php endif; ?>

    <?php if ($step === 4): ?>
      <ul class="onboard-ready">
        <li>Agenda com duração do serviço e bloqueio de horário ocupado</li>
        <li>Clientes e dossiê em PDF</li>
        <li>Solicitações só de origem externa (site ou webhook)</li>
      </ul>
    <?php endif; ?>

    <div class="onboard-actions">
      <?php if ($step > 1): ?>
        <a class="btn btn-ghost" href="/app/onboarding?step=<?= $step - 1 ?>">Voltar</a>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= $step < 4 ? 'Continuar' : 'Entrar no painel' ?></button>
    </div>
  </form>

  <form method="post" action="/logout" class="onboard-out">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <button class="btn btn-ghost">Sair</button>
  </form>
</section>
<script>
(function () {
  var copy = document.getElementById('copy-wa');
  if (copy) {
    copy.addEventListener('click', function () {
      var p = document.getElementById('ob-phone');
      var w = document.getElementById('ob-wa');
      if (p && w) w.value = p.value;
    });
  }
  document.querySelectorAll('.js-closed').forEach(function (box) {
    box.addEventListener('change', function () {
      var row = box.closest('.onboard-hours-row');
      row.querySelectorAll('.js-start, .js-end').forEach(function (el) { el.disabled = box.checked; });
    });
  });
  document.querySelectorAll('[data-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sat = btn.getAttribute('data-preset') === 'sat';
      document.querySelectorAll('.onboard-hours-row').forEach(function (row) {
        var d = parseInt(row.getAttribute('data-day'), 10);
        var closed = d === 0 || (!sat && d === 6);
        var start = (sat && d === 6) ? '08:00' : '08:00';
        var end = (sat && d === 6) ? '12:00' : '18:00';
        row.querySelector('.js-closed').checked = closed;
        row.querySelector('.js-start').value = start;
        row.querySelector('.js-end').value = end;
        row.querySelectorAll('.js-start, .js-end').forEach(function (el) { el.disabled = closed; });
      });
    });
  });
  var form = document.getElementById('onboard-form');
  if (form) {
    form.addEventListener('submit', function () {
      form.querySelectorAll('.js-start[disabled], .js-end[disabled]').forEach(function (el) { el.disabled = false; });
    });
  }
})();
</script>
