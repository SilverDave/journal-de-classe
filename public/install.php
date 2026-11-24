<?php
/**
 * INSTALLATION (à lancer une seule fois)
 * Crée les tables, charge le calendrier FWB et crée votre compte.
 * Ensuite : SUPPRIMEZ ce fichier de votre hébergement.
 */
require __DIR__ . '/../app/bootstrap.php';

$lock = ROOT . '/storage/installed.lock';
$installed = is_file($lock);
if (!$installed) {
    try {
        $installed = (int)fetch_value('SELECT COUNT(*) FROM users') > 0;
    } catch (Throwable $e) {
        $installed = false; // les tables n'existent pas encore
    }
}
if ($installed) {
    http_response_code(403);
    auth_page_start('Installation');
    echo '<h1>Déjà installé</h1><p class="lead">Le site est installé. Supprimez le fichier <b>public/install.php</b> de votre hébergement.</p><a class="btn btn-primary" href="login.php">Aller à la connexion</a>';
    auth_page_end();
    exit;
}

$errors = [];
if (is_post()) {
    $name = input('display_name');
    $email = mb_strtolower(input('email'));
    $password = (string)($_POST['password'] ?? '');

    if (!hash_equals((string)config('install_token'), (string)($_POST['install_token'] ?? '')) || str_starts_with((string)config('install_token'), 'REMPLACEZ')) {
        $errors[] = "Code d'installation incorrect (voir install_token dans config/config.php).";
    }
    if (str_starts_with((string)config('secret_key'), 'REMPLACEZ') || strlen((string)config('secret_key')) < 32) {
        $errors[] = 'Choisissez une vraie clé secrète (secret_key) dans config/config.php.';
    }
    if ($name === '') $errors[] = 'Indiquez votre nom.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
    if ($p = password_problem($password)) $errors[] = $p;
    if ($password !== ($_POST['password_confirm'] ?? '')) $errors[] = 'Les deux mots de passe sont différents.';

    if (!$errors) {
        try {
            // 1. Tables
            $sql = file_get_contents(ROOT . '/database/schema.sql');
            $sql = preg_replace('/^--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                db()->exec($statement);
            }
            // 2. Calendrier FWB : congés + année scolaire en cours
            $today = date('Y-m-d');
            $currentLabel = null;
            foreach (fwb_calendar() as $label => $y) {
                foreach ($y['holidays'] as [$hLabel, $from, $to]) {
                    q('INSERT INTO holidays (label, start_date, end_date) VALUES (?, ?, ?)', [$hLabel, $from, $to]);
                }
                if ($currentLabel === null && $today <= $y['end']) {
                    $currentLabel = $label;
                }
            }
            $currentLabel ??= array_key_last(fwb_calendar());
            $y = fwb_calendar()[$currentLabel];
            q('INSERT INTO school_years (label, start_date, end_date) VALUES (?, ?, ?)', [$currentLabel, $y['start'], $y['end']]);
            // 3. Compte
            q('INSERT INTO users (email, display_name, password_hash, created_at) VALUES (?, ?, ?, ?)',
                [$email, $name, password_hash($password, PASSWORD_DEFAULT), now()]);
            file_put_contents($lock, now());
            audit('install', '', last_id());
            flash('success', 'Installation terminée ! Connectez-vous. Pensez à supprimer public/install.php.');
            redirect('login.php');
        } catch (Throwable $e) {
            error_log('install: ' . $e->getMessage());
            $errors[] = 'Erreur de base de données : vérifiez les accès MySQL dans config/config.php. (' . $e->getMessage() . ')';
        }
    }
}

auth_page_start('Installation');
?>
<h1>Installation</h1>
<p class="lead">Création de la base de données et de votre compte enseignant.</p>
<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" class="stack" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field"><label for="install_token">Code d'installation</label><input id="install_token" type="password" name="install_token" required><small>La valeur « install_token » de config.php</small></div>
    <div class="field"><label for="display_name">Votre nom</label><input id="display_name" type="text" name="display_name" value="<?= e(input('display_name')) ?>" required></div>
    <div class="field"><label for="email">Email (recevra les codes de connexion)</label><input id="email" type="email" name="email" value="<?= e(input('email')) ?>" required></div>
    <div class="field"><label for="password">Mot de passe</label><input id="password" type="password" name="password" minlength="12" autocomplete="new-password" required><small>12 caractères minimum, lettres et chiffres.</small></div>
    <div class="field"><label for="password_confirm">Confirmer le mot de passe</label><input id="password_confirm" type="password" name="password_confirm" minlength="12" autocomplete="new-password" required></div>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Installer</button>
</form>
<?php auth_page_end();
