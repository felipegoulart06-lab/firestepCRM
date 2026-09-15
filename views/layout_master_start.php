<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>FirestepCRM · Admin Master</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
<link rel="stylesheet" href="/assets/app.css?v=ct2">
</head>
<body>
<div class="wrap">
<aside class="sidebar">
  <div class="brand"><div><div class="brand-name">FirestepCRM</div><div class="brand-sub">Admin Master</div></div></div>
  <nav class="nav">
    <div class="nav-cat">PLATAFORMA</div>
    <a href="/master" class="<?= $path==='/master'?'active':'' ?>"><?= icon('home') ?> Dashboard</a>
    <a href="/master/clientes" class="<?= $path==='/master/clientes'?'active':'' ?>"><?= icon('users') ?> Clientes SaaS</a>
    <a href="/master/clientes/novo" class="<?= $path==='/master/clientes/novo'?'active':'' ?>"><?= icon('plus') ?> Criar cliente</a>
    <a href="/master/segmentos" class="<?= $path==='/master/segmentos'?'active':'' ?>"><?= icon('tag') ?> Segmentos</a>
    <div class="nav-cat">SISTEMA</div>
    <a href="/master/integracoes" class="<?= $path==='/master/integracoes'?'active':'' ?>"><?= icon('webhook') ?> Integrações<?php
      $pendHook = (int)(one("SELECT COUNT(*) c FROM tenants WHERE ".sql_not_blank('webhook_requested_at')." AND ".sql_false('webhook_access'))['c'] ?? 0);
      if ($pendHook > 0) echo ' <span class="badge" style="background:#fffaeb;color:#b54708">'.$pendHook.'</span>';
    ?></a>
    <a href="/master/logs" class="<?= $path==='/master/logs'?'active':'' ?>"><?= icon('list') ?> Logs</a>
    <a href="/master/configuracoes" class="<?= $path==='/master/configuracoes'?'active':'' ?>"><?= icon('settings') ?> Configurações</a>
    <div class="sidebar-foot"><form method="post" action="/logout">
      <input type="hidden" name="_csrf" value="<?= e(csrf()) ?>">
      <button class="link"><?= icon('logout') ?> Sair da conta</button>
    </form></div>
  </nav>
</aside>
<div class="main">
  <header class="card top"><b>Painel da plataforma</b><span style="margin-left:auto;color:#667085"><?= e($user['name']) ?></span></header>
  <main class="content">
  <?php $f = flash(); $fk = flash_kind(); ?>
  <?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>"><?= e($f) ?></div><?php endif; ?>
