<?php
declare(strict_types=1);

$root = dirname(__DIR__);
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

expect(!is_file($root.'/app/sheets.php'), 'módulo Google Drive/Sheets removido');
$src = file_get_contents($root.'/views/app/config.php');
expect(!str_contains($src, 'Google Drive') && !str_contains($src, '/app/google/connect'), 'configurações sem Drive');
$index = file_get_contents($root.'/public/index.php');
expect(!str_contains($index, '/cron/google-backup') && !str_contains($index, 'google_backup_all_tenants'), 'cron de backup Drive removido');
expect(!str_contains($index, 'push_google_sheets') && !str_contains($index, 'sync_google_sheets_all'), 'sincronização Google removida das rotas');
expect(!str_contains($index, '/app/google/connect') && !str_contains($index, '/master/configuracoes/google'), 'OAuth Google removido');
$vercel = file_get_contents($root.'/vercel.json');
expect(!str_contains($vercel, 'google-backup'), 'Vercel sem cron do Drive');
$env = file_get_contents($root.'/.env.example');
expect(!str_contains($env, 'GOOGLE_CLIENT'), '.env.example sem credenciais Google');
$tec = file_get_contents($root.'/views/master/tecnico.php');
expect(!str_contains($tec, 'google_client') && !str_contains($tec, 'Google Drive API'), 'Master sem credenciais OAuth Google');
$core = file_get_contents($root.'/app/core.php');
expect(!str_contains($core, 'sheets.php') && !str_contains($core, 'push_google_sheets'), 'núcleo sem Drive');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) da remoção do Google Drive falharam.\n");
    exit(1);
}
echo "Integração Google Drive ausente do sistema.\n";
