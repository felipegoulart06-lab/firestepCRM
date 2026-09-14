<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurar painel · FirestepCRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="stylesheet" href="/assets/app.css?v=onboard5">
</head>
<body class="onboard-page">
<main class="onboard-wrap">
<?php $f = flash(); $fk = flash_kind(); ?>
<?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>"><?= e($f) ?></div><?php endif; ?>
