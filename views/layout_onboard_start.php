<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurar painel · FirestepCRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="stylesheet" href="/assets/app.css?v=onboard6">
<style>
  html,body{height:auto!important;overflow:auto!important;min-height:100%}
  .onboard-page{margin:0;padding:20px 14px 32px;background:#f6f7f9}
  .onboard-wrap{width:100%;max-width:540px;margin:0 auto}
  .onboard-go{display:block!important;visibility:visible!important;width:100%!important;height:48px!important;margin:16px 0 0!important;border:0!important;border-radius:8px!important;background:#2563eb!important;color:#fff!important;font-size:16px!important;font-weight:700!important;cursor:pointer!important;position:static!important}
  .onboard-nav{display:flex;gap:8px;margin-top:12px}
  .onboard-nav .onboard-go{margin:0!important;flex:1}
</style>
</head>
<body class="onboard-page">
<main class="onboard-wrap">
<?php $f = flash(); $fk = flash_kind(); ?>
<?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>"><?= e($f) ?></div><?php endif; ?>
