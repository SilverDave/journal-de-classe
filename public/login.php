<?php
/** Étape 1 de la connexion : email + mot de passe. */
require __DIR__ . '/../app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}
$error = null;
if (is_post()) {
    $error = login_step_password(input('email'), (string)($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('verify.php');
    }
}
auth_page_start('Connexion');
?>
<div class="steps"><i class="on"></i><i></i></div>
<h1>Bonjour 👋</h1>
<p class="lead">Connectez-vous à votre journal de classe.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="<?= e(input('email')) ?>" autocomplete="username" required autofocus></div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" type="password" name="password" autocomplete="current-password" required></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Continuer</button>
</form>
<p class="auth-foot"><?= icon('shield', 'icon icon-sm') ?> Un code de vérification vous sera envoyé par email.</p>
<?php auth_page_end();
