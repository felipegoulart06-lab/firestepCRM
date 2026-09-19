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
expect(count($folders) === 8, '8 pastas no Drive');
expect(array_values($folders) === ['AGENDAMENTOS', 'SOLICITAÇÕES', 'CLIENTES', 'AGENTES', 'FORNECEDORES', 'SERVIÇOS', 'FINANCEIRO', 'BACKUPS'], 'nomes das pastas');
expect(isset($folders['backups']) && $folders['backups'] === 'BACKUPS', 'pasta BACKUPS para JSON');
$fin = google_finance_files();
expect(count($fin) === 6, '6 arquivos na pasta Financeiro');
expect(array_values($fin) === ['Dashboard', 'Lançamentos', 'Contas a receber', 'Faturado', 'Contas a pagar', 'Relatórios'], 'arquivos do Financeiro');
expect(str_contains(google_oauth_scopes(), 'googleapis.com/auth/drive.file'), 'OAuth pede Drive');
expect(str_contains(google_oauth_scopes(), 'googleapis.com/auth/spreadsheets'), 'OAuth ainda permite gravar planilhas dentro do Drive');
$src = file_get_contents(dirname(__DIR__).'/views/app/config.php');
expect(str_contains($src, 'Google Drive') && str_contains($src, 'Abrir pasta no Drive'), 'tela de integrações fala em Drive');
expect(str_contains($src, '8 pastas') && str_contains($src, 'Financeiro') && str_contains($src, 'BACKUPS'), 'texto descreve a árvore com backup');
expect(str_contains($src, 'Integração indisponível'), 'tela avisa quando o OAuth do Master não está pronto');
expect(str_contains($src, 'googleReady'), 'botão Continuar só quando o OAuth está pronto');
$tec = file_get_contents(dirname(__DIR__).'/views/master/tecnico.php');
expect(str_contains($tec, 'Google Drive API') && str_contains($tec, 'Google Sheets API'), 'Master pede Drive API e Sheets API');
$index = file_get_contents(dirname(__DIR__).'/public/index.php');
expect(str_contains($index, '/cron/google-backup') && str_contains($index, 'cron_secret_ok'), 'rota de backup automático protegida');
$vercel = file_get_contents(dirname(__DIR__).'/vercel.json');
expect(str_contains($vercel, '/cron/google-backup'), 'Vercel dispara o cron do Drive');
$sources = google_backup_sources();
expect(isset($sources['clients'], $sources['appointments'], $sources['finance_entries'], $sources['users']), 'snapshot cobre dados principais');
expect(function_exists('google_backup_snapshot') && function_exists('google_backup_all_tenants'), 'funções de backup existem');

$_ENV['CRON_SECRET'] = 'teste-cron-secret';
$_SERVER['HTTP_AUTHORIZATION'] = '';
$_GET['token'] = '';
expect(cron_secret_ok() === false, 'cron recusa sem token');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer teste-cron-secret';
expect(cron_secret_ok() === true, 'cron aceita Bearer');
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer errado';
expect(cron_secret_ok() === false, 'cron recusa Bearer errado');

if ($fail) {
    fwrite(STDERR, "$fail teste(s) do Google Drive falharam.\n");
    exit(1);
}
echo "Árvore Google Drive da integração conferida.\n";
