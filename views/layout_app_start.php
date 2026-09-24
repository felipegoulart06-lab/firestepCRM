<?php
$terms = terms_of($tenant);
$notifs = all('SELECT id,title,body FROM notifications WHERE tenant_id=? AND '.sql_false('read_flag').' ORDER BY created_at DESC LIMIT 8', [$tenant['id']]);
$qsearch = trim($_GET['q'] ?? '');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<?php head_viewport(); ?>
<meta name="csrf" content="<?= e(csrf()) ?>">
<title>FirestepCRM · <?= e($tenant['display_name'] ?: $tenant['business_name']) ?></title>
<style>html,body{margin:0;background:#f6f7f9}</style>
<script>window.MAPBOX_TOKEN=<?= json_encode(mapbox_public_token(), JSON_UNESCAPED_SLASHES) ?>;</script>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="/assets/app.css?v=r46">
<style>:root{--primary:<?= e($tenant['primary_color'] ?: '#2563eb') ?>;}</style>
</head>
<body>
<div class="wrap">
<aside class="sidebar">
  <a class="brand" href="<?= e(app_home_path($tenant, $user)) ?>">
    <div>
      <div class="brand-name">FirestepCRM</div>
      <div class="brand-sub"><?= e($tenant['business_name'] ?: $tenant['name'] ?: $tenant['display_name']) ?></div>
      <?php if (is_user_agent($user)): ?>
        <div class="brand-agent"><?= e($user['name']) ?></div>
      <?php endif; ?>
    </div>
  </a>
  <nav class="nav">
    <a href="/app/agenda" class="<?= $path==='/app/agenda'?'active':'' ?>"><?= icon('calendar') ?> Agenda</a>
    <a href="/app/anotacoes" class="<?= str_starts_with($path,'/app/anotacoes')?'active':'' ?>"><?= icon('note') ?> Anotações</a>
    <?php if (!is_user_agent($user)): ?>
    <a href="/app/metricas" class="<?= $path==='/app/metricas'?'active':'' ?>"><?= icon('chart') ?> Métricas</a>
    <?php endif; ?>
    <a href="/app/kanban" class="<?= $path==='/app/kanban'?'active':'' ?>"><?= icon('kanban') ?> Pipeline</a>
    <div class="nav-cat">ATENDIMENTO</div>
    <a href="/app/agendamentos" class="<?= $path==='/app/agendamentos'?'active':'' ?>"><?= icon('list') ?> Agendamentos</a>
    <a href="/app/solicitacoes" class="<?= $path==='/app/solicitacoes'?'active':'' ?>"><?= icon('inbox') ?> <?= e($terms['requests']) ?></a>
    <div class="nav-cat">RELACIONAMENTO</div>
    <a href="/app/clientes" class="<?= str_starts_with($path,'/app/clientes')?'active':'' ?>"><?= icon('users') ?> <?= e($terms['clients']) ?></a>
    <?php if (is_user_crm($user)): ?>
    <a href="/app/agentes" class="<?= str_starts_with($path,'/app/agentes')?'active':'' ?>"><?= icon('briefcase') ?> Agentes</a>
    <?php endif; ?>
    <?php if (!is_user_agent($user)): ?>
    <a href="/app/abrangencia" class="<?= $path==='/app/abrangencia'?'active':'' ?>"><?= icon('map') ?> Abrangência</a>
    <a href="/app/fornecedores" class="<?= str_starts_with($path,'/app/fornecedores')?'active':'' ?>"><?= icon('briefcase') ?> Fornecedores</a>
    <a href="/app/servicos" class="<?= $path==='/app/servicos'?'active':'' ?>"><?= icon('briefcase') ?> Serviços</a>
    <a href="/app/produtos" class="<?= $path==='/app/produtos'?'active':'' ?>"><?= icon('tag') ?> Produtos</a>
    <a href="/app/relatorios" class="<?= $path==='/app/relatorios'?'active':'' ?>"><?= icon('file') ?> Relatórios</a>
    <details class="nav-group<?= str_starts_with($path,'/app/financeiro')?' nav-on':'' ?>" <?= str_starts_with($path,'/app/financeiro')?'open':'' ?>>
      <summary><?= icon('wallet') ?> Financeiro</summary>
      <div class="nav-sub">
        <?php foreach (finance_pages() as $href): ?>
          <a href="<?= e($href[1]) ?>" class="<?= $path===$href[1] ? 'active' : '' ?>"><?= e($href[0]) ?></a>
        <?php endforeach; ?>
      </div>
    </details>
    <div class="nav-cat">INTEGRAÇÕES</div>
    <a href="/app/webhooks" class="<?= $path==='/app/webhooks'?'active':'' ?>"><?= icon('webhook') ?> Webhooks</a>
    <a href="/app/configuracoes" class="<?= $path==='/app/configuracoes'?'active':'' ?>"><?= icon('settings') ?> Configurações</a>
    <a href="/app/assinatura" class="<?= str_starts_with($path,'/app/assinatura')?'active':'' ?>"><?= icon('wallet') ?> Plano</a>
    <?php endif; ?>
    <div class="sidebar-foot"><form method="post" action="/logout">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <button class="link"><?= icon('logout') ?> Sair da conta</button>
    </form></div>
  </nav>
</aside>
<div class="sidebar-scrim" onclick="closeSide()" aria-hidden="true"></div>
<div class="main">
  <header class="card top">
    <button class="btn btn-ghost hamb" type="button" onclick="toggleSide()"><?= icon('menu') ?></button>
    <form method="get" action="/app/clientes" class="search-wrap">
      <?= icon('search') ?><input class="input" name="q" value="<?= e($qsearch) ?>" placeholder="Buscar nome, telefone ou e-mail...">
    </form>
    <div style="flex:1"></div>
    <details>
      <summary class="btn btn-ghost" style="list-style:none"><?= icon('bell') ?> <?= $notifs ? '<span class="badge" style="background:#dbeafe;color:#1d4ed8">'.count($notifs).'</span>' : '' ?></summary>
      <div class="card notif-pop">
        <?php if (!$notifs): ?><div style="padding:10px;color:#667085;font-size:13px">Sem notificações novas.</div><?php endif; ?>
        <?php foreach ($notifs as $n): ?>
          <div style="padding:8px;border-bottom:1px solid #f1f5f9"><b><?= e($n['title']) ?></b><div style="font-size:12px;color:#667085"><?= e($n['body']) ?></div></div>
        <?php endforeach; ?>
        <form method="post" action="/app/notificacoes/ler"><input type="hidden" name="_csrf" value="<?= e(csrf()) ?>"><button class="btn btn-ghost" style="width:100%">Marcar como lidas</button></form>
      </div>
    </details>
    <div style="display:flex;gap:8px;align-items:center">
      <div class="avatar"><?= e(strtoupper(substr($user['name'],0,1))) ?></div>
      <div class="user-info"><b><?= e($user['name']) ?></b><span><?= e($tenant['business_name']) ?> · <?= is_user_agent($user) ? 'Agente' : 'Administrador' ?></span></div>
    </div>
  </header>
  <main class="content">
    <?php
      $billBanner = billing_of($tenant);
      if (!empty($billBanner['trial']) && (int)$billBanner['trial_days'] <= 7):
    ?>
    <a class="flash" href="/app/assinatura" style="display:block;margin-bottom:12px;background:#fffaeb;color:#b54708;border-color:#fedf89;text-decoration:none">Teste grátis: restam <?= (int)$billBanner['trial_days'] ?> dia<?= (int)$billBanner['trial_days']===1?'':'s' ?>. Depois a plataforma pede o plano pago.</a>
    <?php elseif (!empty($billBanner['paid']) && (int)$billBanner['paid_days'] <= 7): ?>
    <a class="flash" href="/app/assinatura" style="display:block;margin-bottom:12px;background:#fffaeb;color:#b54708;border-color:#fedf89;text-decoration:none">Assinatura vence em <?= (int)$billBanner['paid_days'] ?> dia<?= (int)$billBanner['paid_days']===1?'':'s' ?>. Renove o plano para não perder o acesso.</a>
    <?php endif; ?>
    <?php $f = flash(); $fk = flash_kind(); ?>
    <?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>" data-fs-log="<?= $fk==='error' ? 'error' : 'info' ?>"><?= e($f) ?></div><?php endif; ?>
