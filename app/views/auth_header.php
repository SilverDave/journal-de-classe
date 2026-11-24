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
</head>
<body class="auth">
<main class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand"><span class="brand-mark">J</span><span><?= e(config('app_name')) ?></span></div>
        <?php foreach ($flashes as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
