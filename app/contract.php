<?php
declare(strict_types=1);

function contract_items(array $tenant, array $filters): array
{
    $tid = $tenant['id'];
    $listId = (string)($filters['contract_id'] ?? '');
    $list = $listId !== '' ? clauses_list($tenant, $listId) : null;
    $sql = "SELECT a.starts_at, a.status, c.name client_name,
            s.name service_name, s.price, s.deposit, s.duration_minutes, s.price_kind
        FROM appointments a
        JOIN clients c ON c.id=a.client_id AND c.tenant_id=a.tenant_id
        LEFT JOIN services s ON s.id=a.service_id AND s.tenant_id=a.tenant_id
        WHERE a.tenant_id=? AND a.status!='CANCELLED'";
    $params = [$tid];
    $ids = $list['appointment_ids'] ?? [];
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND a.id IN ($ph)";
        $params = array_merge($params, $ids);
    } else {
        $sql .= ' AND a.starts_at>=? AND a.starts_at<=?';
        $params[] = $filters['from'].' 00:00:00';
        $params[] = $filters['to'].' 23:59:59';
        if (($filters['appt_status'] ?? 'ALL') !== 'ALL') {
            $sql .= ' AND a.status=?';
            $params[] = $filters['appt_status'];
        }
        if (!empty($filters['service_ids'])) {
            $ph = implode(',', array_fill(0, count($filters['service_ids']), '?'));
            $sql .= " AND a.service_id IN ($ph)";
            $params = array_merge($params, $filters['service_ids']);
        }
    }
    $sql .= ' ORDER BY a.starts_at';
    $rows = all($sql, $params);
    $items = [];
    $sum = 0.0;
    $fees = 0.0;
    foreach ($rows as $r) {
        $price = (float)($r['price'] ?? 0);
        $deposit = (float)($r['deposit'] ?? 0);
        $sum += $price;
        $fees += $deposit;
        $items[] = [
            'when' => date('d/m/Y H:i', strtotime((string)$r['starts_at'])),
            'client' => (string)$r['client_name'],
            'service' => (string)($r['service_name'] ?: 'Serviço'),
            'duration' => (int)($r['duration_minutes'] ?? 0),
            'price' => $price,
            'price_kind' => (string)($r['price_kind'] ?? 'priced'),
            'deposit' => $deposit,
            'status' => APPT_STATUS[$r['status']][0] ?? $r['status'],
        ];
    }
    $seg = one('SELECT name, category FROM segments WHERE slug=?', [(string)($tenant['segment'] ?? '')]);
    return [
        'items' => $items,
        'subtotal' => $sum,
        'fees' => $fees,
        'total' => $sum,
        'segment' => (string)($seg['name'] ?? $tenant['segment'] ?? ''),
        'category' => (string)($seg['category'] ?? ''),
        'list_name' => (string)($list['name'] ?? ''),
        'clauses_html' => clauses_html_for($tenant, $listId !== '' ? $listId : null),
    ];
}

function contract_send_pdf(array $tenant, array $doc): never
{
    $cfg = letterhead_cfg_for_pdf($tenant) ?: [
        'trade_name' => 'FirestepCRM',
        'email' => '',
        'phone' => '',
        'document_kind' => '',
        'document' => '',
        'cep' => '',
        'address' => '',
        'color' => '#0f2744',
        'logo' => '',
    ];
    $lh = letterhead_preview_cfg($cfg);
    $sig = signature_preview($tenant);
    $jpeg = $lh && !empty($lh['logo']) ? contract_data_image((string)$lh['logo']) : null;
    $sigJpeg = $sig && !empty($sig['image']) ? contract_data_image((string)$sig['image']) : null;
    $ct = $doc['contract'] ?? [];
    $pages = contract_pdf_pages($doc, $lh, $jpeg, $sigJpeg, $ct);
    $objects = [];
    $pageRefs = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
    $objects[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>';
    $next = 7;
    $imgId = 0;
    $sigId = 0;
    if ($jpeg) {
        $imgId = $next++;
        $objects[$imgId] = contract_pdf_image_obj($jpeg);
    }
    if ($sigJpeg) {
        $sigId = $next++;
        $objects[$sigId] = contract_pdf_image_obj($sigJpeg);
    }
    $total = count($pages);
    foreach ($pages as $i => $body) {
        $stream = $body;
        $stream .= contract_pdf_footer($i + 1, $total, (string)$doc['company']);
        if ($sigId && $sigJpeg && $i === $total - 1) {
            $sh = 38;
            $sw = $sigJpeg['w'] > 0 ? (int)round($sh * $sigJpeg['w'] / $sigJpeg['h']) : 90;
            $sw = max(48, min(150, $sw));
            $sx = 545 - $sw;
            $stream .= sprintf("q %d 0 0 %d %d 48 cm /ImS Do Q\n", $sw, $sh, $sx);
            $stream .= "0 0 0 rg BT /F1 7 Tf $sx 40 Td (Assinatura eletronica) Tj ET\n";
        }
        $contentId = $next++;
        $pageId = $next++;
        $pageRefs[] = "$pageId 0 R";
        $xparts = [];
        if ($imgId) $xparts[] = '/ImH '.$imgId.' 0 R';
        if ($sigId) $xparts[] = '/ImS '.$sigId.' 0 R';
        $xobj = $xparts ? '/XObject << '.implode(' ', $xparts).' >>' : '';
        $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R /F4 6 0 R >> $xobj >> /Contents $contentId 0 R >>";
    }
    $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageRefs).'] /Count '.count($pageRefs).' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size ".($max + 1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.($doc['filename'] ?? 'contrato.pdf').'"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
}

function contract_pdf_image_obj(array $jpeg): string
{
    return '<< /Type /XObject /Subtype /Image /Width '.$jpeg['w'].' /Height '.$jpeg['h']
        .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg['bytes'])
        ." >>\nstream\n".$jpeg['bytes']."\nendstream";
}

function contract_pdf_footer(int $page, int $total, string $company): string
{
    $s = "0.70 0.73 0.77 RG 0.6 w 50 36 495 0 m S\n";
    $s .= "0.35 0.38 0.42 rg BT /F1 8 Tf 50 22 Td (".pdf_text($company.' · Contrato de prestacao de servicos').") Tj ET\n";
    $s .= "BT /F1 8 Tf 430 22 Td (".pdf_text('Pagina '.$page.' de '.$total).") Tj ET\n";
    return $s;
}

function contract_pdf_pages(array $doc, ?array $lh, ?array $jpeg, ?array $sigJpeg, array $ct): array
{
    $blocks = [];
    $blocks[] = ['type' => 'title', 'text' => 'Contrato de prestação de serviços'];
    $blocks[] = ['type' => 'p', 'text' => 'Período: '.($doc['period'] ?? '').' · Gerado em '.($doc['generated'] ?? '')];
    if (!empty($ct['category']) || !empty($ct['segment'])) {
        $blocks[] = ['type' => 'p', 'text' => 'Categoria: '.trim(($ct['category'] ?? '').' · '.($ct['segment'] ?? ''), ' ·')];
    }
    $blocks[] = ['type' => 'h', 'text' => 'Serviços prestados / agendados'];
    if (empty($ct['items'])) {
        $blocks[] = ['type' => 'p', 'text' => 'Nenhum serviço no período filtrado.'];
    } else {
        foreach ($ct['items'] as $it) {
            $line = $it['when'].' · '.$it['client'].' · '.$it['service'];
            if ($it['duration']) $line .= ' · '.$it['duration'].' min';
            $line .= ' · '.service_price_label($it);
            if ($it['deposit'] > 0) $line .= ' · taxa/sinal '.money($it['deposit']);
            $blocks[] = ['type' => 'p', 'text' => $line];
        }
        $blocks[] = ['type' => 'p', 'text' => 'Valores: subtotal '.money((float)$ct['subtotal']).' · taxas/sinal '.money((float)$ct['fees']).' · total '.money((float)$ct['total'])];
    }
    $blocks[] = ['type' => 'h', 'text' => 'Cláusulas básicas da categoria'];
    $html = (string)($ct['clauses_html'] ?? '');
    if ($html === '') {
        $blocks[] = ['type' => 'p', 'text' => 'Nenhuma cláusula cadastrada para esta categoria.'];
    } else {
        foreach (clauses_to_paragraphs($html) as $para) {
            $blocks[] = ['type' => 'rich', 'runs' => $para];
        }
    }
    $pages = [];
    $yStart = $lh ? 700 : 760;
    $page = contract_pdf_header($lh, $jpeg);
    $y = $yStart;
    $flush = static function () use (&$pages, &$page) {
        $pages[] = $page;
        $page = '';
    };
    foreach ($blocks as $b) {
        $need = $b['type'] === 'h' ? 28 : 18;
        if ($y < 70 + $need) {
            $flush();
            $page = contract_pdf_header($lh, $jpeg);
            $y = $yStart;
        }
        if ($b['type'] === 'title') {
            $page .= '0.10 0.16 0.25 rg BT /F2 16 Tf 50 '.$y.' Td ('.pdf_text($b['text']).") Tj ET\n";
            $y -= 22;
        } elseif ($b['type'] === 'h') {
            $page .= '0.15 0.20 0.28 rg BT /F2 11 Tf 50 '.$y.' Td ('.pdf_text($b['text']).") Tj ET\n";
            $y -= 8;
            $page .= "0.85 0.87 0.90 RG 0.4 w 50 ".($y+2)." 495 0 m S\n";
            $y -= 14;
        } elseif ($b['type'] === 'rich') {
            foreach (contract_wrap_runs($b['runs'], 92) as $lineRuns) {
                if ($y < 70) {
                    $flush();
                    $page = contract_pdf_header($lh, $jpeg);
                    $y = $yStart;
                }
                $page .= contract_pdf_runs($lineRuns, 50, $y);
                $y -= 14;
            }
            $y -= 4;
        } else {
            foreach (dossier_pdf_wrap($b['text'], 92) as $line) {
                if ($y < 70) {
                    $flush();
                    $page = contract_pdf_header($lh, $jpeg);
                    $y = $yStart;
                }
                $page .= '0.16 0.20 0.26 rg BT /F1 9 Tf 50 '.$y.' Td ('.pdf_text($line).") Tj ET\n";
                $y -= 13;
            }
        }
    }
    if ($sigJpeg) {
        if ($y < 110) {
            $flush();
            $page = contract_pdf_header($lh, $jpeg);
        }
    }
    $flush();
    return $pages ?: [contract_pdf_header($lh, $jpeg)];
}

function contract_pdf_header(?array $lh, ?array $jpeg): string
{
    if (!$lh) {
        return '';
    }
    [$r, $g, $b] = letterhead_pdf_rgb((string)$lh['color']);
    [$ir, $ig, $ib] = letterhead_pdf_rgb(letterhead_ink((string)$lh['color']));
    $s = "q\n";
    $s .= sprintf("%.3f %.3f %.3f rg\n0 742 595 100 re f\n", $r, $g, $b);
    $textX = 50;
    if ($jpeg) {
        $ih = 56;
        $iw = $jpeg['w'] > 0 ? (int)round($ih * $jpeg['w'] / $jpeg['h']) : 56;
        $iw = max(28, min(120, $iw));
        $s .= sprintf("q %d 0 0 %d 22 768 cm /ImH Do Q\n", $iw, $ih);
        $textX = 22 + $iw + 14;
    }
    $s .= sprintf("%.3f %.3f %.3f rg\n", $ir, $ig, $ib);
    $s .= "BT /F2 14 Tf $textX 812 Td (".pdf_text((string)$lh['name']).") Tj ET\n";
    $yy = 796;
    foreach (($lh['lines'] ?? []) as $line) {
        $s .= "BT /F1 8 Tf $textX $yy Td (".pdf_text((string)$line).") Tj ET\n";
        $yy -= 11;
    }
    $s .= "Q\n".pdf_fill_dark();
    return $s;
}

function contract_pdf_runs(array $runs, float $x, float $y): string
{
    $s = "BT $x $y Td\n";
    foreach ($runs as $run) {
        $bold = !empty($run['b']);
        $ital = !empty($run['i']);
        $font = $bold && $ital ? '/F4' : ($bold ? '/F2' : ($ital ? '/F3' : '/F1'));
        $s .= "0.16 0.20 0.26 rg $font 9 Tf (".pdf_text((string)$run['t']).") Tj\n";
    }
    $s .= "ET\n";
    return $s;
}

function contract_wrap_runs(array $runs, int $width): array
{
    $lines = [];
    $cur = [];
    $len = 0;
    foreach ($runs as $run) {
        $words = preg_split('/(\s+)/u', (string)$run['t'], -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($words as $w) {
            if ($w === '') continue;
            $add = strlen(pdf_text($w));
            if ($len + $add > $width && $cur) {
                $lines[] = $cur;
                $cur = [];
                $len = 0;
            }
            $cur[] = ['t' => $w, 'b' => $run['b'], 'i' => $run['i']];
            $len += $add;
        }
    }
    if ($cur) $lines[] = $cur;
    return $lines ?: [[['t' => ' ', 'b' => false, 'i' => false]]];
}

function clauses_to_paragraphs(string $html): array
{
    $html = preg_replace('/<\/p>|<br>|<\/li>|<\/h\d>/i', "\n", $html) ?? $html;
    $html = preg_replace('/<li[^>]*>/i', '• ', $html) ?? $html;
    $parts = preg_split("/\n+/", $html) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $out[] = clauses_parse_runs($part);
    }
    return $out;
}

function clauses_parse_runs(string $html): array
{
    $runs = [];
    $bold = false;
    $ital = false;
    $tokens = preg_split('/(<\/?(?:b|strong|i|em)\/?>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    foreach ($tokens as $tok) {
        $low = strtolower($tok);
        if ($low === '<b>' || $low === '<strong>') { $bold = true; continue; }
        if ($low === '</b>' || $low === '</strong>') { $bold = false; continue; }
        if ($low === '<i>' || $low === '<em>') { $ital = true; continue; }
        if ($low === '</i>' || $low === '</em>') { $ital = false; continue; }
        $text = trim(html_entity_decode(strip_tags($tok), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '') continue;
        $runs[] = ['t' => $text, 'b' => $bold, 'i' => $ital];
    }
    return $runs ?: [['t' => strip_tags($html), 'b' => false, 'i' => false]];
}
