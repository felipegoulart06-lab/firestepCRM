<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<?php head_viewport(); ?>
<title>Definir senha · FirestepCRM</title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="icon" href="/assets/favicon.png" type="image/png" sizes="32x32">
<link rel="stylesheet" href="/assets/app.css?v=r5">
</head>
<body class="lock-body">
<main class="lock-wrap">
<?php $f = flash(); $fk = flash_kind(); ?>
<?php if ($f): ?><div class="flash<?= $fk==='error' ? ' flash-error' : '' ?>" data-fs-log="<?= $fk==='error' ? 'error' : 'info' ?>"><?= e($f) ?></div><?php endif; ?>
