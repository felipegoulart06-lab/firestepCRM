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
    1 => 'Confirme o nome, o telefone e o WhatsApp.',
    2 => 'A agenda só libera horários neste expediente.',
    3 => 'Modelos do seu segmento. Você altera depois em Serviços.',
    4 => 'Clientes, agenda e solicitações já estão no CRM.',
];
$go = $step < 4 ? 'Avançar' : 'Entrar no painel';
$old = $old ?? [];
?>
<section class="card" style="padding:22px 22px 18px">
  <p class="lock-kicker">Passo <?= $step ?> de 4 · <?= e($labels[$step-1]) ?></p>
  <h1 style="margin:0 0 6px;font-size:22px"><?= e($titles[$step]) ?></h1>
  <p class="subtitle"><?= e($subs[$step]) ?></p>

  <form method="post" action="/app/onboarding" id="onboard-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <input type="hidden" name="step" value="<?= $step ?>">
    <input class="onboard-go" type="submit" value="<?= e($go) ?>">

    <?php if ($step === 1): ?>
      <label class="label" for="ob-name">Nome comercial</label>
      <input class="input" id="ob-name" name="business_name" required maxlength="120" value="<?= e(old_fill($old, 'business_name', $tenant['business_name'] ?: $tenant['display_name'])) ?>" placeholder="Como aparece no painel">

      <label class="label" for="ob-phone">Telefone</label>
      <input class="input" id="ob-phone" name="phone" required inputmode="tel" autocomplete="tel" value="<?= e(old_fill($old, 'phone', $tenant['phone'])) ?>" placeholder="(47) 99999-0000">

      <label class="label" for="ob-wa">WhatsApp <span style="color:#98a2b3;font-weight:500">opcional</span></label>
      <input class="input" id="ob-wa" name="whatsapp" inputmode="tel" value="<?= e(old_fill($old, 'whatsapp', $tenant['whatsapp'])) ?>" placeholder="Se vazio, usamos o telefone">
    <?php endif; ?>

    <?php if ($step === 2): ?>
      <div class="onboard-presets">
        <button type="button" class="btn btn-ghost" data-preset="weekdays">Seg–sex 8h–18h</button>
        <button type="button" class="btn btn-ghost" data-preset="sat">+ sábado de manhã</button>
      </div>
      <?php for ($d = 0; $d <= 6; $d++):
          $h = $hours[$d] ?? $hours[(string)$d] ?? ['closed' => false, 'start' => '08:00', 'end' => '18:00'];
          $closed = !empty($h['closed']);
      ?>
        <div class="onboard-hours-row" data-day="<?= $d ?>" style="margin-top:8px">
          <b><?= $days[$d] ?></b>
          <label class="check-row onboard-closed"><input type="checkbox" name="closed_<?= $d ?>" class="js-closed" <?= $closed ? 'checked' : '' ?>> Fechado</label>
          <input class="input js-start" type="time" name="start_<?= $d ?>" value="<?= e($h['start'] ?? '08:00') ?>" <?= $closed ? 'disabled' : '' ?>>
          <input class="input js-end" type="time" name="end_<?= $d ?>" value="<?= e($h['end'] ?? '18:00') ?>" <?= $closed ? 'disabled' : '' ?>>
        </div>
      <?php endfor; ?>
    <?php endif; ?>

    <?php if ($step === 3): ?>
      <?php if (!$services): ?>
        <p class="onboard-empty">Nenhum serviço modelo ainda. Cadastre depois em Serviços.</p>
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
    <?php endif; ?>

    <?php if ($step === 4): ?>
      <ul class="onboard-ready">
        <li>Agenda com duração do serviço e bloqueio de horário ocupado</li>
        <li>Clientes e dossiê em PDF</li>
        <li>Solicitações só de origem externa (site ou webhook)</li>
      </ul>
    <?php endif; ?>

    <div class="onboard-nav">
      <?php if ($step > 1): ?>
        <a class="btn btn-ghost" href="/app/onboarding?step=<?= $step - 1 ?>">Voltar</a>
      <?php endif; ?>
      <input class="onboard-go" type="submit" value="<?= e($go) ?>">
    </div>
  </form>

  <form method="post" action="/logout" style="margin-top:12px;text-align:center">
    <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
    <button class="btn btn-ghost" type="submit">Sair</button>
  </form>
</section>
<script>
(function () {
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
        row.querySelector('.js-closed').checked = closed;
        row.querySelector('.js-start').value = '08:00';
        row.querySelector('.js-end').value = (sat && d === 6) ? '12:00' : '18:00';
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
