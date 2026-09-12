<h1>Dashboard</h1>
<div class="grid g4">
  <div class="card stat"><span>Clientes cadastrados</span><b><?= (int)$total ?></b></div>
  <div class="card stat"><span>Ativos</span><b><?= (int)$active ?></b></div>
  <div class="card stat"><span>Suspensos</span><b><?= (int)$suspended ?></b></div>
  <div class="card stat"><span>Agendamentos</span><b><?= (int)$appts ?></b></div>
  <div class="card stat"><span>Solicitações</span><b><?= (int)$reqs ?></b></div>
  <div class="card stat"><span>Agendamentos hoje</span><b><?= (int)$today ?></b></div>
</div>
<div class="grid g2" style="margin-top:16px">
  <section class="card" style="padding:16px">
    <h3>Últimos clientes</h3>
    <?php foreach ($lastTenants as $t): ?>
      <div style="padding:8px 0;border-bottom:1px solid #f1f5f9"><b><?= e($t['business_name']) ?></b><div style="font-size:13px;color:#667085"><?= e($t['email']) ?> · <?= e($t['segment']) ?></div></div>
    <?php endforeach; ?>
  </section>
  <section class="card" style="padding:16px">
    <h3>Últimas atividades</h3>
    <?php foreach ($audits as $a): ?><div style="font-size:13px;padding:6px 0"><?= e($a['action']) ?> · <?= e($a['entity']) ?></div><?php endforeach; ?>
  </section>
</div>
<section class="card" style="padding:16px;margin-top:12px">
  <h3>Últimos erros de webhook</h3>
  <?php if (!$errors): ?><p style="color:#667085">Nenhum erro recente.</p><?php endif; ?>
  <?php foreach ($errors as $er): ?><div style="font-size:13px"><?= e($er['created_at']) ?> · <?= e($er['event']) ?> · <?= e($er['response']) ?></div><?php endforeach; ?>
</section>
