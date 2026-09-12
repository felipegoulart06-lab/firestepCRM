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
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

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
