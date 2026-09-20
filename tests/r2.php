<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root.'/app/helpers.php';

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

expect(is_file($root.'/app/r2.php'), 'módulo R2 presente');
$cfgView = file_get_contents($root.'/views/app/config.php');
$phrase = 'O login é o e-mail ou este usuário. O nome da empresa no menu não serve para entrar.';
$pos = strpos($cfgView, $phrase);
$backupPos = strpos($cfgView, 'acct-autobackup');
expect($pos !== false && $backupPos !== false && $backupPos > $pos, 'AUTO BACKUP fica abaixo da frase de login');
expect(str_contains($cfgView, '/app/configuracoes/auto-backup'), 'Conta envia o toggle de AUTO BACKUP');
$css = file_get_contents($root.'/public/assets/app.css');
expect(str_contains($css, '.acct-autobackup>summary') && str_contains($css, 'opacity:.14'), 'controle de backup permanece discreto');
$index = file_get_contents($root.'/public/index.php');
expect(str_contains($index, '/cron/r2-backup') && str_contains($index, 'r2_backup_all_tenants'), 'cron R2 registrado');
$vercel = file_get_contents($root.'/vercel.json');
expect(str_contains($vercel, '/cron/r2-backup'), 'Vercel agenda o backup R2');
$env = file_get_contents($root.'/.env.example');
expect(str_contains($env, 'R2_ACCESS_KEY_ID=') && !str_contains($env, 'da8d5df198d15cf9532abc6cb8aa9eca'), '.env.example tem R2 sem a chave real');
expect(r2_object_key('abc', '2026-09-20T12-00-00Z') === 'tenants/abc/crm-2026-09-20T12-00-00Z.json', 'objeto R2 fica isolado por tenant');
$redacted = r2_redact(['name' => 'Ana', 'password_hash' => 'secret']);
expect($redacted['password_hash'] === '[redacted]' && $redacted['name'] === 'Ana', 'snapshot omite hash de senha');
expect(!r2_enabled(['auto_backup' => 0]) && r2_enabled(['auto_backup' => 1]), 'flag AUTO BACKUP liga e desliga');
$canonical = r2_canonical_path('firestepcrm', 'tenants/x/crm.json');
expect($canonical === '/firestepcrm/tenants/x/crm.json', 'path S3 do bucket firestepcrm');

if ($fail) {
    fwrite(STDERR, "$fail verificação(ões) R2 falharam.\n");
    exit(1);
}
echo "AUTO BACKUP R2 ok.\n";
