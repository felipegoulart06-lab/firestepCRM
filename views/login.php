<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · FirestepCRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/app.css">
</head>
<body style="min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 10% 10%,#eff6ff 0,transparent 30%),#f8fafc">
<div style="width:100%;max-width:960px;display:grid;grid-template-columns:1.05fr .95fr;overflow:hidden" class="card dash">
<section style="padding:48px;background:#101828;color:#fff;display:flex;flex-direction:column;justify-content:space-between;min-height:540px">
  <div><div class="brand-name" style="margin-bottom:42px;font-size:20px">FirestepCRM</div>
    <div style="font-size:12px;color:#84adff;font-weight:700;letter-spacing:.12em">FIRESTEPCRM</div>
    <h1 style="font-size:34px;margin:12px 0;max-width:400px">Seu negócio organizado, sem complicação.</h1>
    <p style="color:#98a2b3;max-width:390px;font-size:15px">Clientes, agenda, solicitações, métricas e integrações em um painel simples para usar todos os dias.</p>
  </div>
  <div style="display:flex;gap:18px;color:#d0d5dd;font-size:12px"><span>✓ Multi-tenant</span><span>✓ Rápido</span><span>✓ Seguro</span></div>
</section>
<form method="post" action="/login" style="padding:48px;display:flex;flex-direction:column;justify-content:center">
  <div style="font-size:12px;letter-spacing:.12em;color:#2563eb;font-weight:700">BEM-VINDO</div>
  <h1 style="margin:9px 0 5px">Entrar no painel</h1>
  <p style="color:#667085;margin:0 0 28px">Use seu e-mail ou nome de usuário.</p>
  <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
  <label class="label">E-mail ou usuário</label>
  <input class="input" name="login" autocomplete="username" placeholder="voce@empresa.com" required>
  <div style="height:16px"></div>
  <label class="label">Senha</label>
  <input class="input" type="password" name="password" autocomplete="current-password" placeholder="Sua senha" required>
  <?php $f = flash(); $fk = flash_kind(); ?>
  <?php if ($f): ?><p class="flash<?= $fk==='error' ? ' flash-error' : '' ?>"><?= e($f) ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p style="color:#b42318;background:#fef3f2;border:1px solid #fecdca;padding:9px 11px;border-radius:8px"><?= e($error) ?></p><?php endif; ?>
  <button class="btn btn-primary" style="width:100%;margin-top:20px;min-height:43px">Entrar no sistema</button>
</form>
</div>
</body>
</html>
