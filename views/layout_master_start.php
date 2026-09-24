<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<?php head_viewport(); ?>
<title>FirestepCRM · Admin Master</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<style>html,body{margin:0;background:#f6f7f9}</style>
<link rel="stylesheet" href="/assets/app.css?v=r46">
</head>
<body>
<div class="wrap">
<aside class="sidebar">
  <div class="brand"><div><div class="brand-name">FirestepCRM</div><div class="brand-sub">Admin Master</div></div></div>
  <nav class="nav">
    <div class="nav-cat">PLATAFORMA</div>
    <a href="/master" class="<?= $path==='/master'?'active':'' ?>"><?= icon('home') ?> Dashboard</a>
    <a href="/master/objetivo" class="<?= $path==='/master/objetivo'?'active':'' ?>"><?= icon('file') ?> Objetivo</a>
    <a href="/master/clientes" class="<?= str_starts_with($path,'/master/clientes')?'active':'' ?>"><?= icon('users') ?> Clientes SaaS</a>
    <a href="/master/planos" class="<?= $path==='/master/planos'?'active':'' ?>"><?= icon('wallet') ?> Planos<?php
      $pendBill = 0;
      try {
        $pendBill = (int)(one("SELECT COUNT(*) c FROM saas_invoices WHERE status='open'")['c'] ?? 0);
      } catch (Throwable $e) {
      }
      if ($pendBill > 0) echo ' <span class="badge" style="background:#fffaeb;color:#b54708">'.$pendBill.'</span>';
    ?></a>
    <a href="/master/segmentos" class="<?= $path==='/master/segmentos'?'active':'' ?>"><?= icon('tag') ?> Segmentos</a>
    <a href="/master/produtos" class="<?= str_starts_with($path,'/master/produtos')?'active':'' ?>"><?= icon('briefcase') ?> Produtos</a>
    <div class="nav-cat">SISTEMA</div>
    <a href="/master/integracoes" class="<?= $path==='/master/integracoes'?'active':'' ?>"><?= icon('webhook') ?> Integrações<?php
      $pendHook = (int)(one("SELECT COUNT(*) c FROM tenants WHERE ".sql_not_blank('webhook_requested_at')." AND ".sql_false('webhook_access'))['c'] ?? 0);
      if ($pendHook > 0) echo ' <span class="badge" style="background:#fffaeb;color:#b54708">'.$pendHook.'</span>';
    ?></a>
    <a href="/master/assistente" class="<?= $path==='/master/assistente'?'active':'' ?>"><?= icon('message') ?> Assistente<?php
      $pendAssist = function_exists('assistant_open_count') ? assistant_open_count() : 0;
      if ($pendAssist > 0) echo ' <span class="badge" style="background:#dbeafe;color:#1d4ed8">'.$pendAssist.'</span>';
    ?></a>
    <a href="/master/logs" class="<?= $path==='/master/logs'?'active':'' ?>"><?= icon('list') ?> Logs</a>
    <a href="/master/configuracoes" class="<?= $path==='/master/configuracoes'?'active':'' ?>"><?= icon('settings') ?> Configurações</a>
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
    <b>Painel da plataforma</b>
    <span class="top-user" style="margin-left:auto;color:#667085"><?= e($user['name']) ?></span>
  </header>
  <main class="content">
  <?php $f = flash(); $fk = flash_kind(); ?>
  <?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>" data-fs-log="<?= $fk==='error' ? 'error' : 'info' ?>"><?= e($f) ?></div><?php endif; ?>
