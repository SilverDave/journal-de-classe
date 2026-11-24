<?php /** En-tête de toutes les pages privées. Variables : $title, $active, $user, $year, $years, $flashes */ ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#141B2D">
    <title><?= e($title) ?> · <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <script src="assets/app.js" defer></script>
</head>
<body class="app">
<aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php">
        <span class="brand-mark">J</span>
        <span class="brand-name"><?= e(config('app_name')) ?></span>
    </a>

    <?php if ($years): ?>
    <form class="year-switch" method="post" action="years.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="switch">
        <label for="year-select">Année scolaire</label>
        <select id="year-select" name="year_id" data-autosubmit>
            <?php foreach ($years as $y): ?>
                <option value="<?= (int)$y['id'] ?>" <?= $year && $year['id'] == $y['id'] ? 'selected' : '' ?>>
                    <?= e($y['label']) ?><?= $y['is_archived'] ? ' (archivée)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>

    <nav class="nav">
        <?php foreach (NAV as $file => [$label, $ic]): ?>
            <a href="<?= $file ?>" class="<?= $active === $file ? 'is-active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
        <div class="nav-sep"></div>
        <?php foreach (NAV_SECONDARY as $file => [$label, $ic]): ?>
            <a href="<?= $file ?>" class="<?= $active === $file ? 'is-active' : '' ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <div class="who"><?= icon('shield', 'icon icon-sm') ?> <?= e($user['display_name'] ?? '') ?></div>
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn btn-ghost-dark btn-sm" type="submit"><?= icon('logout', 'icon icon-sm') ?> Déconnexion</button>
        </form>
    </div>
</aside>
<div class="backdrop" data-close-nav></div>

<div class="main">
    <header class="topbar">
        <button class="icon-btn" type="button" data-toggle-nav aria-label="Ouvrir le menu"><?= icon('menu') ?></button>
        <span class="topbar-title"><?= e($title) ?></span>
        <?php if ($year): ?><span class="chip"><?= e($year['label']) ?></span><?php endif; ?>
    </header>

    <?php if (year_is_archived()): ?>
        <div class="banner-archive"><?= icon('archive', 'icon icon-sm') ?> Année <?= e($year['label']) ?> archivée — consultation uniquement.</div>
    <?php endif; ?>

    <main class="content">
        <?php foreach ($flashes as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?><button type="button" class="flash-close" data-dismiss aria-label="Fermer">×</button></div>
        <?php endforeach; ?>
