<?php
declare(strict_types=1);

function pdf_text(string $value): string
{
    $ascii = iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $ascii ?: $value);
}

function download_pdf(string $title, array $lines, string $filename): never
{
    $chunks = array_chunk($lines, 43);
    if (!$chunks) $chunks = [[]];
    $objects = [];
    $pageRefs = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    foreach ($chunks as $index => $chunk) {
        $contentId = 4 + ($index * 2);
        $pageId = $contentId + 1;
        $pageRefs[] = "$pageId 0 R";
        $stream = "BT\n/F1 18 Tf\n50 790 Td\n(".pdf_text($title).") Tj\n";
        $stream .= "/F1 9 Tf\n0 -20 Td\n(Gerado em ".date('d/m/Y H:i').") Tj\n0 -22 Td\n";
        foreach ($chunk as $line) {
            $stream .= '('.pdf_text((string)$line).") Tj\n0 -16 Td\n";
        }
        $stream .= "ET";
        $objects[$contentId] = "<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
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
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
}

function dossier_pdf_str(string $text): string
{
    return '('.pdf_text($text).')';
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

function tenant_dossier_pdf(array $tenant, ?array $admin, ?array $segment): string
{
    $when = $tenant['created_at'] ?? '';
    try {
        $when = $when ? (new DateTime((string)$when))->format('d/m/Y H:i') : '—';
    } catch (Throwable $e) {
        $when = (string)($tenant['created_at'] ?? '—');
    }
    $seg = trim(($segment['category'] ?? '').' · '.($segment['name'] ?? ''), ' ·');
    $rows = [
        ['Negócio', (string)($tenant['business_name'] ?? '—')],
        ['Nome exibido', (string)($tenant['display_name'] ?? '—')],
        ['Profissional', (string)($tenant['name'] ?? '—')],
        ['Segmento', $seg !== '' ? $seg : (string)($tenant['segment'] ?? '—')],
        ['Documento', (string)($tenant['document'] ?? '—')],
        ['E-mail', (string)($tenant['email'] ?? ($admin['email'] ?? '—'))],
        ['Usuário de login', (string)($admin['username'] ?? '—')],
        ['Telefone', (string)($tenant['phone'] ?? '—')],
        ['WhatsApp', (string)($tenant['whatsapp'] ?: '—')],
        ['Cidade', trim((string)($tenant['city'] ?? '').' / '.(string)($tenant['state'] ?? ''), ' /')],
        ['Status', TENANT_STATUS[$tenant['status'] ?? ''] ?? (string)($tenant['status'] ?? '—')],
        ['Identificador', (string)($tenant['slug'] ?? '—')],
        ['Criado em', $when],
    ];

    $stream = "0.06 0.09 0.16 rg\n0 780 595 62 re f\n";
    $stream .= "1 1 1 rg\nBT /F2 18 Tf 40 812 Td ".dossier_pdf_str('FirestepCRM')." ET\n";
    $stream .= "0.75 0.82 0.94 rg\nBT /F1 10 Tf 40 794 Td ".dossier_pdf_str('Dossie do cliente SaaS')." ET\n";
    $stream .= "0.10 0.16 0.25 rg\nBT /F2 20 Tf 40 748 Td ".dossier_pdf_str((string)($tenant['business_name'] ?? 'Cliente'))." ET\n";
    $y = 720;
    $stream .= "0.90 0.92 0.95 rg\n40 ".$y." 515 1 re f\n";
    $y -= 28;

    foreach ($rows as $row) {
        $lines = dossier_pdf_wrap((string)$row[1], 70);
        $stream .= "0.42 0.45 0.50 rg\nBT /F1 9 Tf 40 $y Td ".dossier_pdf_str(strtoupper((string)$row[0]))." ET\n";
        $stream .= "0.06 0.09 0.16 rg\n";
        $yy = $y - 14;
        foreach ($lines as $line) {
            $stream .= "BT /F2 11 Tf 40 $yy Td ".dossier_pdf_str($line)." ET\n";
            $yy -= 14;
        }
        $y = $yy - 10;
        $stream .= "0.93 0.94 0.96 rg\n40 ".($y + 8)." 515 0.6 re f\n";
        if ($y < 80) {
            break;
        }
    }

    $stream .= "0.42 0.45 0.50 rg\nBT /F1 8 Tf 40 36 Td ".dossier_pdf_str('Documento interno · sem senha nem token · gerado em '.date('d/m/Y H:i'))." ET\n";

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objects[6] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."endstream";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    for ($i = 1; $i <= 6; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}

function send_tenant_dossier_pdf(array $tenant, ?array $admin, ?array $segment): never
{
    $pdf = tenant_dossier_pdf($tenant, $admin, $segment);
    $slug = preg_replace('/[^a-z0-9-]+/i', '-', (string)($tenant['slug'] ?? 'cliente')) ?: 'cliente';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="dossie-'.$slug.'.pdf"');
    header('Cache-Control: private, no-store');
    header('Content-Length: '.strlen($pdf));
    echo $pdf;
    exit;
}
