<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/helpers.php';
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/finance.php';

$fail = 0;
function expect($ok, string $msg): void
{
    global $fail;
    if ($ok) {
        echo "OK  $msg\n";
        return;
    }
    $fail++;
    echo "FAIL $msg\n";
}

$folders = google_drive_folders();
expect(count($folders) === 7, '7 pastas no Drive');
expect(array_values($folders) === ['AGENDAMENTOS', 'SOLICITAÇÕES', 'CLIENTES', 'AGENTES', 'FORNECEDORES', 'SERVIÇOS', 'FINANCEIRO'], 'nomes das pastas');
$fin = google_finance_files();
expect(count($fin) === 6, '6 arquivos na pasta Financeiro');
expect(array_values($fin) === ['Dashboard', 'Lançamentos', 'Contas a receber', 'Faturado', 'Contas a pagar', 'Relatórios'], 'arquivos do Financeiro');
expect(str_contains(google_oauth_scopes(), 'googleapis.com/auth/drive.file'), 'OAuth pede Drive');
expect(str_contains(google_oauth_scopes(), 'googleapis.com/auth/spreadsheets'), 'OAuth ainda permite gravar planilhas dentro do Drive');
$src = file_get_contents(dirname(__DIR__).'/views/app/config.php');
expect(str_contains($src, 'Google Drive') && str_contains($src, 'Abrir pasta no Drive'), 'tela de integrações fala em Drive');
expect(str_contains($src, '7 pastas') && str_contains($src, 'Financeiro'), 'texto descreve a árvore');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) do Google Drive falharam.\n");
    exit(1);
}
echo "Árvore Google Drive da integração conferida.\n";
