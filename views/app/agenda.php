<?php
$terms = terms_of($tenant);
$view = $_GET['view'] ?? 'week';
$cursor = $_GET['date'] ?? date('Y-m-d');
$ts = strtotime($cursor);
$label = date('d/m/Y', $ts);
$dow = (int)date('N', $ts); // 1-7
$weekStart = strtotime('-'.($dow-1).' days', $ts);
$days = $view === 'day' ? [$ts] : array_map(fn($i) => strtotime("+$i days", $weekStart), range(0,6));
$hours = range(7,20);
if (!function_exists('ev_color')) {
function ev_color($kind, $st) {
    if ($kind === 'block') return ['#f3e8ff','#7c3aed','#6b21a8'];
    if ($kind === 'request') return ['#dbeafe','#60a5fa','#1d4ed8'];
    return match($st) {
        'WAITING' => ['#fef9c3','#ca8a04','#854d0e'],
        'CONFIRMED' => ['#dcfce7','#16a34a','#166534'],
        'CANCELLED' => ['#fee2e2','#dc2626','#991b1b'],
        'DONE' => ['#e2e8f0','#64748b','#334155'],
        default => ['#dbeafe','#2563eb','#1e3a8a'],
    };
}
function agenda_busy_level(int $n): int
{
    if ($n >= 18) {
        return 4;
    }
    if ($n >= 12) {
        return 3;
    }
    if ($n >= 6) {
        return 2;
    }
    return 1;
}
}
$prev = date('Y-m-d', strtotime($view==='month'?'-1 month':($view==='day'?'-1 day':'-7 days'), $ts));
$next = date('Y-m-d', strtotime($view==='month'?'+1 month':($view==='day'?'+1 day':'+7 days'), $ts));
$months = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$periodLabel = $view === 'week'
    ? date('d/m', $weekStart).' – '.date('d/m/Y', strtotime('+6 days', $weekStart))
    : ($view === 'month' ? $months[(int)date('n',$ts)].' '.date('Y',$ts) : date('d/m/Y', $ts));
$dayCounts = [];
foreach ($events as $ev) {
    $dayKey = substr((string)$ev['start'], 0, 10);
    $dayCounts[$dayKey] = ($dayCounts[$dayKey] ?? 0) + 1;
}
$busyWeek = 1;
foreach ($days ?? [] as $d) {
    $busyWeek = max($busyWeek, agenda_busy_level($dayCounts[date('Y-m-d', $d)] ?? 0));
}
?>
<div class="page-head" style="margin-bottom:12px">
  <div><h1>Agenda</h1><p>Visualize, crie e gerencie todos os horários.</p></div>
  <a class="btn btn-primary" href="/app/agenda?new=1&date=<?= e($cursor) ?>&start=09:00"><?= icon('plus') ?> Novo agendamento</a>
</div>
<div class="agenda-toolbar">
  <a class="btn btn-ghost" href="/app/agenda?view=<?= e($view) ?>&date=<?= date('Y-m-d') ?>">Hoje</a>
  <a class="btn btn-ghost" aria-label="Anterior" href="/app/agenda?view=<?= e($view) ?>&date=<?= $prev ?>">‹</a>
  <a class="btn btn-ghost" aria-label="Próximo" href="/app/agenda?view=<?= e($view) ?>&date=<?= $next ?>">›</a>
  <b class="agenda-date"><?= e($periodLabel) ?></b>
  <div style="flex:1"></div>
  <a class="btn <?= $view==='day'?'btn-primary':'btn-ghost' ?>" href="/app/agenda?view=day&date=<?= e($cursor) ?>">Dia</a>
  <a class="btn <?= $view==='week'?'btn-primary':'btn-ghost' ?>" href="/app/agenda?view=week&date=<?= e($cursor) ?>">Semana</a>
  <a class="btn <?= $view==='month'?'btn-primary':'btn-ghost' ?>" href="/app/agenda?view=month&date=<?= e($cursor) ?>">Mês</a>
</div>
<?php if ($view !== 'month'): ?>
<div class="card calendar-shell">
  <div class="cal cal-busy-<?= (int)$busyWeek ?>" style="grid-template-columns:72px repeat(<?= count($days) ?>,minmax(130px,1fr))">
    <div class="cal-head"></div>
    <?php foreach ($days as $d): ?>
      <div class="cal-head <?= date('Y-m-d',$d)===date('Y-m-d')?'today':'' ?>"><?= ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'][(int)date('N',$d)-1] ?> <?= date('d',$d) ?></div>
    <?php endforeach; ?>
    <?php foreach ($hours as $h): ?>
      <div class="cal-time"><?= sprintf('%02d:00',$h) ?></div>
      <?php foreach ($days as $d):
        $ymd = date('Y-m-d', $d);
        $cell = array_values(array_filter($events, function($ev) use ($ymd,$h) {
            return substr($ev['start'],0,10)===$ymd && (int)substr($ev['start'],11,2)===$h;
        }));
        $slotN = count($cell);
        $level = max(agenda_busy_level($dayCounts[$ymd] ?? 0), $slotN >= 6 ? 4 : ($slotN >= 4 ? 3 : ($slotN >= 2 ? 2 : 1)));
      ?>
        <div class="slot" data-density="<?= (int)$level ?>" data-n="<?= (int)$slotN ?>">
          <?php foreach ($cell as $ev): $c = ev_color($ev['kind'], $ev['status'] ?? ''); ?>
            <a class="ev <?= $ev['kind']==='request'?'ev-request':'' ?>" style="background:<?= $c[0] ?>;border-left:3px solid <?= $c[1] ?>;color:<?= $c[2] ?>"
               href="<?= $ev['kind']==='block' ? '/app/agenda?delblock='.$ev['id'] : ($ev['kind']==='request' ? '/app/solicitacoes?ver='.$ev['id'] : '/app/agenda?view='.e($view).'&date='.$ymd.'&ver='.$ev['id']) ?>">
              <b><?= e(substr($ev['start'],11,5)) ?> · <?= e($ev['title']) ?></b>
              <div><?= $ev['kind']==='request'?'Solicitação · ':'' ?><?= e($ev['subtitle'] ?? ($ev['kind']==='block'?'Bloqueio':'')) ?><?= !empty($ev['source'])?' · '.e($ev['source']):'' ?></div>
            </a>
          <?php endforeach; ?>
          <?php if (!$cell): ?>
            <a class="slot-add" href="/app/agenda?new=1&date=<?= $ymd ?>&start=<?= sprintf('%02d:00',$h) ?>&view=<?= e($view) ?>"></a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php else:
  $startM = strtotime(date('Y-m-01', $ts));
  $fill = ((int)date('N', $startM)) - 1;
  $count = (int)date('t', $ts);
?>
<div class="card calendar-month" style="display:grid;grid-template-columns:repeat(7,1fr)">
  <?php foreach (['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $n): ?><div style="padding:8px;font-size:12px;color:#667085;font-weight:700"><?= $n ?></div><?php endforeach; ?>
  <?php for ($i=0;$i<$fill+$count;$i++):
    if ($i < $fill) { echo '<div style="min-height:88px;border-top:1px solid #f1f5f9"></div>'; continue; }
    $day = $i - $fill + 1;
    $ymd = date('Y-m-', $ts) . sprintf('%02d',$day);
    $dayEv = array_values(array_filter($events, fn($ev) => substr($ev['start'],0,10)===$ymd));
    $dayN = count($dayEv);
    $level = agenda_busy_level($dayN);
    $show = $level >= 4 ? 8 : ($level >= 3 ? 6 : 4);
  ?>
    <div class="month-cell" data-density="<?= (int)$level ?>" data-n="<?= (int)$dayN ?>">
      <a href="/app/agenda?new=1&date=<?= $ymd ?>" style="display:block"><b style="font-size:12px"><?= $day ?></b></a>
      <?php foreach (array_slice($dayEv, 0, $show) as $ev): $c = ev_color($ev['kind'], $ev['status']??'');
        $chipHref = $ev['kind']==='block' ? '/app/agenda?delblock='.$ev['id'] : ($ev['kind']==='request' ? '/app/solicitacoes?ver='.$ev['id'] : '/app/agenda?view=month&date='.$ymd.'&ver='.$ev['id']);
      ?>
        <a class="ev ev-month" href="<?= e($chipHref) ?>" style="background:<?= $c[0] ?>;color:<?= $c[2] ?>"><?= e(substr($ev['start'],11,5).' '.$ev['title']) ?></a>
      <?php endforeach; ?>
      <?php if ($dayN > $show): ?><span class="month-more">+<?= $dayN - $show ?></span><?php endif; ?>
    </div>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$detailId = trim((string)($_GET['ver'] ?? $_GET['edit'] ?? ''));
if (!empty($_GET['new']) || $detailId !== ''):
  $edit = $detailId !== '' ? appointment_detail($tenant['id'], $detailId) : null;
  $viewOnly = (bool)$edit;
  $allowEditFromDetails = false;
  $forcedClient = null;
  if (!$edit && !empty($_GET['client_id'])) {
      $forcedClient = one('SELECT * FROM clients WHERE id=? AND tenant_id=?', [$_GET['client_id'], $tenant['id']]);
  }
  $modalClose = '/app/agenda?view='.urlencode((string)$view).'&date='.urlencode((string)$cursor);
  if ($forcedClient && ($_GET['from'] ?? '') === 'clientes') {
      $modalClose = '/app/clientes/ver?id='.$forcedClient['id'];
  }
  include VIEWS . '/app/modal_appointment.php';
endif; ?>
