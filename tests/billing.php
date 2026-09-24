<?php
declare(strict_types=1);

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'firestep-bill-'.bin2hex(random_bytes(4)).'.sqlite';
putenv('FIRESTEP_SQLITE='.$tmp);
$_ENV['FIRESTEP_SQLITE'] = $tmp;
putenv('DATABASE_URL');
unset($_ENV['DATABASE_URL'], $_SERVER['DATABASE_URL']);

require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/core.php';

db()->exec(file_get_contents(dirname(__DIR__).'/app/schema.sql'));
billing_ensure_schema();

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

$now = now();
q('INSERT INTO tenants(id,name,business_name,slug,segment,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)', [
    'ten-bill', 'Ana', 'Studio Ana', 'studio-ana-bill', 'outros', 'ana-bill@ex.com', 'ACTIVE', $now, $now,
]);
billing_start_trial('ten-bill', $now);
$tenant = billing_sync(one('SELECT * FROM tenants WHERE id=?', ['ten-bill']));
$snap = billing_of($tenant);
expect($snap['trial'] === true && $snap['locked'] === false, 'cliente novo entra em teste de 30 dias');
expect((int)$snap['trial_days'] >= 29 && (int)$snap['trial_days'] <= 31, 'restam cerca de 30 dias');
expect($snap['communicate'] === true, 'Comunicador liberado no teste');

$monthly = billing_quote('monthly', false);
$semi = billing_quote('semiannual', false);
$year = billing_quote('annual', false);
$monthlyAdd = billing_quote('monthly', true);
expect(abs($monthly['amount'] - 39.90) < 0.001, 'mensal 39,90');
expect(abs($semi['monthly'] - 34.90) < 0.001 && abs($semi['amount'] - 209.40) < 0.001, 'semestral 34,90/mês cobrado no período');
expect(abs($year['monthly'] - 29.90) < 0.001 && abs($year['amount'] - 358.80) < 0.001, 'anual 29,90/mês cobrado no período');
expect(abs($monthlyAdd['monthly'] - 79.90) < 0.001, 'Comunicador soma 40,00 na mensalidade');

q('UPDATE tenants SET trial_ends_at=? WHERE id=?', [date('Y-m-d H:i:s', time() - 3600), 'ten-bill']);
$expired = billing_sync(one('SELECT * FROM tenants WHERE id=?', ['ten-bill']));
expect(billing_locked($expired) === true, 'depois de 30 dias o painel trava até o pagamento');
expect(billing_communicate_ok($expired) === false, 'sem plano pago o Comunicador também para');

$req = billing_request($expired, 'semiannual', true, 'usr-master');
expect(!empty($req['ok']) && abs($req['quote']['amount'] - 449.40) < 0.001, 'pedido semestral com Comunicador');
$open = billing_open_invoice('ten-bill');
expect($open && $open['status'] === 'open', 'Admin Master vê cobrança em aberto');

$pay = billing_confirm((string)$open['id'], 'usr-master');
expect(!empty($pay['ok']), 'confirmação libera o período');
$paid = billing_sync(one('SELECT * FROM tenants WHERE id=?', ['ten-bill']));
expect(billing_locked($paid) === false && billing_of($paid)['paid'] === true, 'empresa volta a usar o CRM');
expect(billing_communicate_ok($paid) === true, 'Comunicador incluso no adicional pago');

$index = file_get_contents(dirname(__DIR__).'/public/index.php');
$appNav = file_get_contents(dirname(__DIR__).'/views/layout_app_start.php');
$masterNav = file_get_contents(dirname(__DIR__).'/views/layout_master_start.php');
expect(str_contains($appNav, '/app/assinatura') && str_contains($appNav, 'Plano'), 'empresa vê o menu Plano');
expect(str_contains($masterNav, '/master/planos') && str_contains($masterNav, 'Planos'), 'Admin Master acompanha os planos');
expect(str_contains($index, '/app/assinatura/solicitar') && str_contains($index, '/master/planos/confirmar'), 'pedido e confirmação de pagamento');
expect(isset(app_home_choices()['/app/assinatura']), 'Plano entra na tela inicial');

@unlink($tmp);
if ($fail) {
    fwrite(STDERR, "{$fail} teste(s) de planos SaaS falharam.\n");
    exit(1);
}
echo "Trial de 30 dias, planos pagos e adicional do Comunicador.\n";
