<?php
declare(strict_types=1);

function pdf_text(string $value): string
{
    $ascii = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $ascii ?: $value);
}

function pdf_fill_dark(): string
{
    return "0.063 0.094 0.157 rg\n";
}

function pdf_labeled_line(string $line): string
{
    if (preg_match('/^([^:\-][^:]{0,48}:)(\s*)(.*)$/u', $line, $m)) {
        return '/F2 9 Tf ('.pdf_text($m[1]).") Tj /F1 9 Tf (".pdf_text($m[2].$m[3]).") Tj\n0 -16 Td\n";
    }
    return '/F1 9 Tf ('.pdf_text($line).") Tj\n0 -16 Td\n";
}

function build_pdf(string $title, array $lines, ?array $tenant = null): string
{
    $lh = null;
    $sig = null;
    if ($tenant && !empty($tenant['id']) && function_exists('letterhead_active')) {
        letterhead_ensure_schema();
        $row = one('SELECT letterhead_config, signature_config FROM tenants WHERE id=?', [$tenant['id']]);
        if ($row) {
            $tenant['letterhead_config'] = $row['letterhead_config'] ?? '{}';
            $tenant['signature_config'] = $row['signature_config'] ?? '{}';
        }
        if (function_exists('signature_active') && signature_active($tenant)) {
            $sig = signature_config($tenant);
        }
    }
    if (function_exists('letterhead_cfg_for_pdf')) {
        $lh = letterhead_cfg_for_pdf($tenant);
    } elseif ($tenant && function_exists('letterhead_active') && letterhead_active($tenant)) {
        $lh = letterhead_config($tenant);
    }
    if (!$lh) {
        $lh = [
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
    }
    $perPage = $lh ? 32 : 43;
    if ($sig) {
        $perPage -= 5;
    }
    $chunks = array_chunk($lines, $perPage);
    if (!$chunks) {
        $chunks = [[]];
    }
    $objects = [];
    $pageRefs = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $imgId = 0;
    $sigId = 0;
    $nextId = 5;
    $jpeg = $lh ? letterhead_jpeg($lh) : null;
    $sigJpeg = $sig ? contract_data_image((string)($sig['image'] ?? '')) : null;
    if ($jpeg) {
        $imgId = $nextId++;
        $objects[$imgId] = '<< /Type /XObject /Subtype /Image /Width '.$jpeg['w'].' /Height '.$jpeg['h']
            .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg['bytes'])
            ." >>\nstream\n".$jpeg['bytes']."\nendstream";
    }
    if ($sigJpeg) {
        $sigId = $nextId++;
        $objects[$sigId] = '<< /Type /XObject /Subtype /Image /Width '.$sigJpeg['w'].' /Height '.$sigJpeg['h']
            .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($sigJpeg['bytes'])
            ." >>\nstream\n".$sigJpeg['bytes']."\nendstream";
    }

    $total = count($chunks);
    foreach ($chunks as $index => $chunk) {
        $contentId = $nextId++;
        $pageId = $nextId++;
        $pageRefs[] = "$pageId 0 R";
        $stream = '';
        $titleY = 790;
        if ($lh) {
            [$r, $g, $b] = letterhead_pdf_rgb((string)$lh['color']);
            $ink = letterhead_ink((string)$lh['color']);
            [$ir, $ig, $ib] = letterhead_pdf_rgb($ink);
            $stream .= "q\n";
            $stream .= sprintf("%.3f %.3f %.3f rg\n0 758 595 84 re f\n", $r, $g, $b);
            $textX = 50;
            if ($imgId && $jpeg) {
                $ih = 36;
                $iw = $jpeg['w'] > 0 ? (int)round($ih * $jpeg['w'] / $jpeg['h']) : 36;
                $iw = max(24, min(72, $iw));
                $stream .= sprintf("q %d 0 0 %d 18 790 cm /ImH Do Q\n", $iw, $ih);
                $textX = 18 + $iw + 12;
            }
            $stream .= sprintf("%.3f %.3f %.3f rg\n", $ir, $ig, $ib);
            $stream .= "BT\n/F2 13 Tf\n$textX 818 Td\n(".pdf_text((string)$lh['trade_name']).") Tj\n";
            $stream .= "/F1 8 Tf\n0 -12 Td\n(".pdf_text(trim($lh['email'].'  ·  '.$lh['phone'])).") Tj\n";
            $kind = strtoupper((string)($lh['document_kind'] ?: ''));
            $stream .= "0 -10 Td\n(".pdf_text(trim($kind.' '.$lh['document'])).") Tj\n";
            $stream .= "0 -10 Td\n(".pdf_text('CEP '.$lh['cep'].'  ·  '.$lh['address']).") Tj\nET\n";
            $stream .= "Q\n";
            $titleY = 700;
        }
        $stream .= pdf_fill_dark();
        $stream .= "BT\n/F2 16 Tf\n50 $titleY Td\n(".pdf_text($title).") Tj\n";
        $stream .= "/F1 9 Tf\n0 -18 Td\n(Gerado em ".date('d/m/Y H:i').") Tj\n0 -22 Td\n";
        foreach ($chunk as $line) {
            $stream .= pdf_labeled_line((string)$line);
        }
        $stream .= "ET";
        if ($sigId && $sigJpeg && $index === $total - 1) {
            $sh = 56;
            $sw = $sigJpeg['w'] > 0 ? (int)round($sh * $sigJpeg['w'] / $sigJpeg['h']) : 110;
            $sw = max(64, min(180, $sw));
            $sx = 595 - 48 - $sw;
            $stream .= "0 0 0 rg\n";
            $stream .= sprintf("q %d 0 0 %d %d 48 cm /ImS Do Q\n", $sw, $sh, $sx);
            $stream .= "BT /F1 7 Tf $sx 36 Td (Assinatura) Tj ET\n";
        }
        $xparts = [];
        if ($imgId) {
            $xparts[] = '/ImH '.$imgId.' 0 R';
        }
        if ($sigId) {
            $xparts[] = '/ImS '.$sigId.' 0 R';
        }
        $xobj = $xparts ? '/XObject << '.implode(' ', $xparts).' >>' : '';
        $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> $xobj >> /Contents $contentId 0 R >>";
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
    return $pdf;
}

function download_pdf(string $title, array $lines, string $filename, ?array $tenant = null, bool $inline = false): never
{
    $pdf = build_pdf($title, $lines, $tenant);
    header('Content-Type: application/pdf');
    header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"');
    header('Cache-Control: private, no-store');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
}

function dossier_pdf_str(string $text): string
{
    return '('.pdf_text($text).') Tj';
}

function dossier_pdf_wrap(string $text, int $width = 70): array
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if ($text === '') {
        return ['—'];
    }
    $words = explode(' ', $text);
    $lines = [];
    $cur = '';
    foreach ($words as $w) {
        $try = $cur === '' ? $w : $cur.' '.$w;
        if (strlen(pdf_text($try)) > $width) {
            if ($cur !== '') {
                $lines[] = $cur;
            }
            $cur = $w;
        } else {
            $cur = $try;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines ?: ['—'];
}

function tenant_dossier_lines(array $tenant, ?array $admin, ?array $segment): array
{
    $when = $tenant['created_at'] ?? '';
    try {
        $when = $when ? (new DateTime((string)$when))->format('d/m/Y H:i') : '—';
    } catch (Throwable $e) {
        $when = (string)($tenant['created_at'] ?? '—');
    }
    $seg = trim(($segment['category'] ?? '').' · '.($segment['name'] ?? ''), ' ·');
    $city = trim((string)($tenant['city'] ?? '').' / '.(string)($tenant['state'] ?? ''), ' /');
    return [
        'Negocio: '.(string)($tenant['business_name'] ?? '—'),
        'Nome exibido: '.(string)($tenant['display_name'] ?? '—'),
        'Profissional: '.(string)($tenant['name'] ?? '—'),
        'Segmento: '.($seg !== '' ? $seg : (string)($tenant['segment'] ?? '—')),
        'Documento: '.(string)($tenant['document'] ?? '—'),
        'E-mail: '.(string)($tenant['email'] ?? ($admin['email'] ?? '—')),
        'Usuario de login: '.(string)($admin['username'] ?? '—'),
        'Telefone: '.(string)($tenant['phone'] ?? '—'),
        'WhatsApp: '.(string)($tenant['whatsapp'] ?: '—'),
        'Cidade: '.($city !== '' ? $city : '—'),
        'Status: '.(TENANT_STATUS[$tenant['status'] ?? ''] ?? (string)($tenant['status'] ?? '—')),
        'Identificador: '.(string)($tenant['slug'] ?? '—'),
        'Criado em: '.$when,
        str_repeat('-', 80),
        'Documento interno · sem senha nem token',
    ];
}

function tenant_dossier_pdf(array $tenant, ?array $admin, ?array $segment): string
{
    return build_pdf('Dossie do cliente SaaS', tenant_dossier_lines($tenant, $admin, $segment), [
        'id' => '',
        'letterhead_config' => json_encode(platform_letterhead_config(), JSON_UNESCAPED_UNICODE),
        'signature_config' => '{}',
    ]);
}

function send_appointment_pdf(array $tenant, array $a): never
{
    $status = APPT_STATUS[$a['status'] ?? ''][0] ?? (string)($a['status'] ?? '—');
    $start = $a['starts_at'] ?? '';
    $end = $a['ends_at'] ?? '';
    $lines = [
        'Empresa: '.($tenant['display_name'] ?: $tenant['business_name']),
        str_repeat('-', 80),
        'Cliente: '.($a['client_name'] ?? '—'),
        'Telefone: '.phone_fmt($a['client_phone'] ?: ($a['client_whatsapp'] ?? null)),
        'WhatsApp: '.phone_fmt($a['client_whatsapp'] ?? null),
        'E-mail: '.($a['client_email'] ?: 'Não informado'),
        str_repeat('-', 80),
    ];
    if (!empty($a['service_name'])) {
        $lines[] = 'Serviço: '.$a['service_name'];
        $lines[] = 'Duração: '.(((int)($a['duration_minutes'] ?? 0)) > 0 ? (int)$a['duration_minutes'].' min' : '—');
    }
    $lines[] = 'Data: '.($start ? date('d/m/Y', strtotime($start)) : '—');
    $lines[] = 'Horário: '.($start && $end ? substr($start, 11, 5).' – '.substr($end, 11, 5) : '—');
    $lines[] = 'Status: '.$status;
    $lines[] = 'Origem: '.($a['source'] ?: '—');
    $lines[] = 'Criado em: '.(!empty($a['created_at']) ? date('d/m/Y H:i', strtotime($a['created_at'])) : '—');
    if (!empty($a['notes'])) {
        $lines[] = 'Observações: '.$a['notes'];
    }
    if (!empty($a['request_message'])) {
        $lines[] = 'Mensagem da solicitação: '.$a['request_message'];
    }
    $utm = array_filter([$a['utm_source'] ?? '', $a['utm_medium'] ?? '', $a['utm_campaign'] ?? '']);
    if ($utm) {
        $lines[] = 'Campanha: '.implode(' · ', $utm);
    }
    $who = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)($a['client_name'] ?? 'reserva'))) ?: 'reserva';
    $when = $start ? date('Y-m-d-Hi', strtotime($start)) : date('Y-m-d');
    download_pdf('Agendamento: '.($a['client_name'] ?? 'cliente'), $lines, 'agendamento-'.$who.'-'.$when.'.pdf', $tenant);
}

function send_tenant_dossier_pdf(array $tenant, ?array $admin, ?array $segment): never
{
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', (string)($tenant['slug'] ?? 'cliente')) ?: 'cliente';
    download_pdf(
        'Dossie do cliente SaaS · '.((string)($tenant['business_name'] ?? 'Cliente')),
        tenant_dossier_lines($tenant, $admin, $segment),
        'dossie-'.$slug.'.pdf',
        [
            'id' => '',
            'letterhead_config' => json_encode(platform_letterhead_config(), JSON_UNESCAPED_UNICODE),
            'signature_config' => '{}',
        ],
        true
    );
}
