<?php
/** Étape 2 de la connexion : code à 6 chiffres reçu par email. */
require __DIR__ . '/../app/bootstrap.php';

$user = pending_2fa_user();
if (!$user) {
    redirect('login.php');
}
$error = null;
if (is_post()) {
    if (input('action') === 'resend') {
        // Un nouvel envoi au maximum toutes les 60 secondes
        $wait = 60 - (time() - ($_SESSION['pending_2fa']['sent_at'] ?? 0));
        if ($wait > 0) {
            $error = "Patientez $wait secondes avant de demander un nouveau code.";
        } else {
            send_login_code($user);
            flash('info', 'Un nouveau code vient de vous être envoyé.');
            redirect('verify.php');
        }
    } else {
        $error = login_step_code(input('code'));
        if ($error === null) {
            redirect('index.php');
        }
    }
}
// Email masqué : j***@exemple.be
[$local, $domain] = explode('@', $user['email']) + [1 => ''];
$masked = mb_substr($local, 0, 1) . str_repeat('•', max(2, mb_strlen($local) - 1)) . '@' . $domain;

auth_page_start('Vérification');
?>
<div class="steps"><i class="on"></i><i class="on"></i></div>
<h1>Vérification</h1>
<p class="lead">Entrez le code à 6 chiffres envoyé à <b><?= e($masked) ?></b>.</p>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <input class="input-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" required autofocus aria-label="Code à 6 chiffres">
    <button class="btn btn-primary btn-lg btn-block" type="submit">Se connecter</button>
</form>
<form method="post" class="row between mt">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="resend">
    <a href="login.php" class="small">← Recommencer</a>
    <button class="link-btn small" type="submit">Renvoyer un code</button>
</form>
<p class="auth-foot"><?= icon('clock', 'icon icon-sm') ?> Le code expire après <?= (int)config('security.code_valid_minutes', 10) ?> minutes. Pensez à vérifier vos spams.</p>
<?php auth_page_end();
