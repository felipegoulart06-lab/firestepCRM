<?php
declare(strict_types=1);

function metrics_period(): int
{
    $period = (int)($_GET['period'] ?? 30);
    return in_array($period, [1, 7, 30, 90, 365], true) ? $period : 30;
}

function metrics_period_label(int $period): string
{
    return [1 => 'Hoje', 7 => '7 dias', 30 => '30 dias', 90 => '90 dias', 365 => '12 meses'][$period] ?? '30 dias';
}

function metrics_delta(float $now, float $prev): array
{
    if ($prev <= 0 && $now <= 0) {
        return ['pct' => 0, 'up' => true, 'label' => '0%'];
    }
    if ($prev <= 0) {
        return ['pct' => 100, 'up' => true, 'label' => '+100%'];
    }
    $pct = (int)round((($now - $prev) / $prev) * 100);
    return ['pct' => $pct, 'up' => $pct >= 0, 'label' => ($pct > 0 ? '+' : '').$pct.'%'];
}

function metrics_month_sql(string $col): string
{
    return is_pgsql()
        ? "to_char({$col}::timestamp, 'YYYY-MM')"
        : "strftime('%Y-%m', {$col})";
}

function metrics_sparkline(array $values, string $color = '#2563eb'): string
{
    $vals = array_map('floatval', $values);
    if ($vals === []) {
        $vals = [0];
    }
    $max = max(1, max($vals));
    $n = count($vals);
    $w = 120;
    $h = 36;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n === 1 ? $w / 2 : ($i / ($n - 1)) * $w;
        $y = $h - ($v / $max) * ($h - 4) - 2;
        $pts[] = round($x, 1).','.round($y, 1);
    }
    $line = implode(' ', $pts);
    $fill = '0,'.$h.' '.$line.' '.$w.','.$h;
    $c = e($color);
    return '<svg class="mx-spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true"><polygon points="'.$fill.'" fill="'.$c.'" opacity=".12"/><polyline points="'.$line.'" fill="none" stroke="'.$c.'" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/></svg>';
}

function metrics_area_chart(array $labels, array $a, array $b, string $colorA, string $colorB): string
{
    $n = max(1, count($labels));
    $w = 640;
    $h = 220;
    $padL = 8;
    $padR = 8;
    $padT = 16;
    $padB = 28;
    $max = max(1, max(array_merge($a, $b, [0])));
    $plot = function (array $vals) use ($n, $w, $h, $padL, $padR, $padT, $padB, $max): array {
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $padL + ($n === 1 ? 0 : $i / ($n - 1)) * ($w - $padL - $padR);
            $y = $padT + (1 - ((float)$v / $max)) * ($h - $padT - $padB);
            $pts[] = [round($x, 1), round($y, 1)];
        }
        return $pts;
    };
    $toLine = static function (array $pts): string {
        return implode(' ', array_map(static fn($p) => $p[0].','.$p[1], $pts));
    };
    $pa = $plot($a);
    $pb = $plot($b);
    $fillA = $padL.','.($h - $padB).' '.$toLine($pa).' '.($w - $padR).','.($h - $padB);
    $ticks = '';
    $step = $n > 8 ? 2 : 1;
    foreach ($labels as $i => $lab) {
        if ($i % $step !== 0 && $i !== $n - 1) {
            continue;
        }
        $x = $padL + ($n === 1 ? 0 : $i / ($n - 1)) * ($w - $padL - $padR);
        $ticks .= '<text x="'.round($x, 1).'" y="'.($h - 8).'" text-anchor="middle" fill="#98a2b3" font-size="11">'.e((string)$lab).'</text>';
    }
    return '<svg class="mx-chart-svg" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" role="img" aria-label="Evolução mensal">'
        .'<polygon points="'.$fillA.'" fill="'.e($colorA).'" opacity=".14"/>'
        .'<polyline points="'.$toLine($pa).'" fill="none" stroke="'.e($colorA).'" stroke-width="2.4" stroke-linejoin="round"/>'
        .'<polyline points="'.$toLine($pb).'" fill="none" stroke="'.e($colorB).'" stroke-width="2" stroke-dasharray="5 4" stroke-linejoin="round"/>'
        .$ticks.'</svg>';
}

function metrics_bars(array $rows, string $color = '#2563eb'): string
{
    if (!$rows) {
        return '';
    }
    $max = max(1, max(array_map(static fn($r) => (int)$r['total'], $rows)));
    $h = max(160, count($rows) * 36);
    $w = 520;
    $barH = 18;
    $gap = 16;
    $html = '<svg class="mx-chart-svg" viewBox="0 0 '.$w.' '.$h.'" role="img" aria-label="Origem dos contatos">';
    foreach (array_values($rows) as $i => $row) {
        $y = 10 + $i * ($barH + $gap);
        $bw = round(((int)$row['total'] / $max) * 340);
        $html .= '<text x="0" y="'.($y + 14).'" fill="#344054" font-size="12">'.e((string)($row['source'] ?: 'Não informado')).'</text>';
        $html .= '<rect x="148" y="'.$y.'" width="'.$bw.'" height="'.$barH.'" rx="4" fill="'.e($color).'" opacity="'.($i === 0 ? '1' : '.72').'"/>';
        $html .= '<text x="'.(156 + $bw).'" y="'.($y + 14).'" fill="#667085" font-size="12">'.(int)$row['total'].'</text>';
    }
    return $html.'</svg>';
}

function metrics_build(array $tenant, int $period): array
{
    $tid = (string)$tenant['id'];
    if (function_exists('appointment_backfill_reserva')) {
        appointment_backfill_reserva($tid);
    }
    if (function_exists('ensure_finance_schema')) {
        ensure_finance_schema();
    }
    $now = time();
    if ($period === 1) {
        $from = date('Y-m-d 00:00:00', $now);
        $prevFrom = date('Y-m-d 00:00:00', strtotime('-1 day', $now));
        $prevTo = date('Y-m-d 23:59:59', strtotime('-1 day', $now));
    } else {
        $from = date('Y-m-d 00:00:00', strtotime('-'.$period.' days', $now));
        $prevFrom = date('Y-m-d 00:00:00', strtotime('-'.($period * 2).' days', $now));
        $prevTo = date('Y-m-d 23:59:59', strtotime('-'.$period.' days -1 second', $now));
    }
    $to = date('Y-m-d 23:59:59', $now);
    $count = static function (string $sql, array $p): int {
        return (int)(one($sql, $p)['c'] ?? 0);
    };
    $newClients = $count('SELECT COUNT(*) c FROM clients WHERE tenant_id=? AND created_at>=?', [$tid, $from]);
    $newClientsPrev = $count('SELECT COUNT(*) c FROM clients WHERE tenant_id=? AND created_at>=? AND created_at<=?', [$tid, $prevFrom, $prevTo]);
    $requestTotal = $count('SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=?', [$tid, $from]);
    $requestPrev = $count('SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND created_at<=?', [$tid, $prevFrom, $prevTo]);
    $scheduledRequests = $count("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status IN ('SCHEDULED','DONE')", [$tid, $from]);
    $contactRequests = $count("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status IN ('CONTACTED','WAITING_CLIENT')", [$tid, $from]);
    $finishedRequests = $count("SELECT COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? AND status='DONE'", [$tid, $from]);
    $appointmentTotal = $count("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND status!='CANCELLED'", [$tid, $from]);
    $appointmentPrev = $count("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<=? AND status!='CANCELLED'", [$tid, $prevFrom, $prevTo]);
    $doneAppointments = $count("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND status='DONE'", [$tid, $from]);
    $donePrev = $count("SELECT COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND starts_at<=? AND status='DONE'", [$tid, $prevFrom, $prevTo]);
    $conversion = $requestTotal > 0 ? (int)round(($scheduledRequests / $requestTotal) * 100) : 0;
    $attendance = $appointmentTotal > 0 ? (int)round(($doneAppointments / $appointmentTotal) * 100) : 0;
    $attendancePrev = $appointmentPrev > 0 ? (int)round(($donePrev / $appointmentPrev) * 100) : 0;
    $revenue = 0.0;
    $revenuePrev = 0.0;
    if (function_exists('ensure_finance_schema') && function_exists('finance_sum')) {
        try {
            ensure_finance_schema();
            $revenue = finance_sum($tid, 'receivable', ['paid', 'billed'], 'in', $from, $to)
                + finance_sum($tid, 'entry', ['paid'], 'in', $from, $to);
            $revenuePrev = finance_sum($tid, 'receivable', ['paid', 'billed'], 'in', $prevFrom, $prevTo)
                + finance_sum($tid, 'entry', ['paid'], 'in', $prevFrom, $prevTo);
        } catch (Throwable $e) {
            $revenue = 0.0;
            $revenuePrev = 0.0;
        }
    }
    $ticketBase = max(1, $doneAppointments ?: $appointmentTotal);
    $ticket = $appointmentTotal > 0 ? $revenue / $ticketBase : 0.0;
    $monthSql = metrics_month_sql('starts_at');
    $monthFrom = date('Y-m-01 00:00:00', strtotime('-11 months', $now));
    $monthKeys = [];
    $pt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    for ($i = 11; $i >= 0; $i--) {
        $ts = strtotime('-'.$i.' months', $now);
        $key = date('Y-m', $ts);
        $monthKeys[$key] = ['label' => $pt[(int)date('n', $ts) - 1], 'appts' => 0, 'reqs' => 0];
    }
    foreach (all("SELECT {$monthSql} m, COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? AND status!='CANCELLED' GROUP BY {$monthSql}", [$tid, $monthFrom]) as $row) {
        $k = (string)($row['m'] ?? '');
        if (isset($monthKeys[$k])) {
            $monthKeys[$k]['appts'] = (int)$row['c'];
        }
    }
    $reqMonthSql = metrics_month_sql('created_at');
    foreach (all("SELECT {$reqMonthSql} m, COUNT(*) c FROM requests WHERE tenant_id=? AND created_at>=? GROUP BY {$reqMonthSql}", [$tid, $monthFrom]) as $row) {
        $k = (string)($row['m'] ?? '');
        if (isset($monthKeys[$k])) {
            $monthKeys[$k]['reqs'] = (int)$row['c'];
        }
    }
    $sparkAppts = [];
    $sparkReqs = [];
    $sparkDone = [];
    foreach (array_slice($monthKeys, -8, 8, true) as $row) {
        $sparkAppts[] = $row['appts'];
        $sparkReqs[] = $row['reqs'];
        $sparkDone[] = $row['appts'];
    }
    $sources = all("SELECT COALESCE(NULLIF(utm_source,''),source,'Não informado') source, COUNT(*) total FROM clients WHERE tenant_id=? AND created_at>=? GROUP BY COALESCE(NULLIF(utm_source,''),source,'Não informado') ORDER BY total DESC LIMIT 8", [$tid, $from]);
    foreach ($sources as &$src) {
        $src['source'] = normalize_source((string)$src['source']);
    }
    unset($src);
    $statusRows = all("SELECT status, COUNT(*) c FROM appointments WHERE tenant_id=? AND starts_at>=? GROUP BY status", [$tid, $from]);
    $statusMap = ['DONE' => 0, 'CONFIRMED' => 0, 'SCHEDULED' => 0, 'CANCELLED' => 0, 'WAITING' => 0];
    foreach ($statusRows as $row) {
        $st = (string)$row['status'];
        $statusMap[$st] = (int)$row['c'];
    }

    return [
        'period' => $period,
        'periodLabel' => metrics_period_label($period),
        'from' => $from,
        'newClients' => $newClients,
        'newClientsDelta' => metrics_delta($newClients, $newClientsPrev),
        'requestTotal' => $requestTotal,
        'requestDelta' => metrics_delta($requestTotal, $requestPrev),
        'scheduledRequests' => $scheduledRequests,
        'contactRequests' => $contactRequests,
        'finishedRequests' => $finishedRequests,
        'appointmentTotal' => $appointmentTotal,
        'appointmentDelta' => metrics_delta($appointmentTotal, $appointmentPrev),
        'doneAppointments' => $doneAppointments,
        'conversion' => $conversion,
        'attendance' => $attendance,
        'attendanceDelta' => metrics_delta($attendance, $attendancePrev),
        'revenue' => $revenue,
        'revenueDelta' => metrics_delta($revenue, $revenuePrev),
        'ticket' => $ticket,
        'sources' => $sources,
        'topServices' => all("SELECT s.name, COUNT(a.id) total FROM appointments a LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id WHERE a.tenant_id=? AND a.starts_at>=? AND a.status!='CANCELLED' GROUP BY s.name ORDER BY total DESC LIMIT 5", [$tid, $from]),
        'utmCampaigns' => $count("SELECT COUNT(DISTINCT utm_campaign) c FROM requests WHERE tenant_id=? AND created_at>=? AND utm_campaign IS NOT NULL AND utm_campaign!=''", [$tid, $from]),
        'recentAppts' => all("SELECT a.id,a.reserva_n,a.starts_at,a.status,c.name client_name,s.name service_name,s.price service_price,s.price_kind,ag.name agent_name,f.status finance_status,f.payment_method,f.amount finance_amount
            FROM appointments a
            LEFT JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
            LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
            LEFT JOIN users ag ON ag.id=a.commission_agent_id AND ag.tenant_id=a.tenant_id
            LEFT JOIN finance_entries f ON f.id=(
                SELECT id FROM finance_entries WHERE tenant_id=a.tenant_id AND source_type='appointment' AND source_id=a.id ORDER BY created_at DESC LIMIT 1
            )
            WHERE a.tenant_id=? AND a.starts_at>=? ORDER BY a.starts_at DESC LIMIT 8", [$tid, $from]),
        'recentReqs' => all("SELECT id,name,source,status,created_at FROM requests WHERE tenant_id=? AND created_at>=? ORDER BY created_at DESC LIMIT 8", [$tid, $from]),
        'statusMap' => $statusMap,
        'months' => $monthKeys,
        'sparkAppts' => $sparkAppts,
        'sparkReqs' => $sparkReqs,
        'sparkDone' => $sparkDone,
        'accent' => (string)($tenant['primary_color'] ?: '#2563eb'),
    ];
}
