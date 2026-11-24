<?php
/** Mon compte : nom, mot de passe et historique des connexions. */
require __DIR__ . '/../app/bootstrap.php';
$user = require_login();

if (is_post()) {
    if (input('action') === 'profile' && input('display_name') !== '') {
        q('UPDATE users SET display_name = ? WHERE id = ?', [mb_substr(input('display_name'), 0, 100), $user['id']]);
        flash('success', 'Nom modifié.');
    }
    if (input('action') === 'password') {
        $row = fetch_one('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) {
            flash('error', 'Mot de passe actuel incorrect.');
        } elseif ($problem = password_problem($new)) {
            flash('error', $problem);
        } elseif ($new !== ($_POST['new_password_confirm'] ?? '')) {
            flash('error', 'Les deux nouveaux mots de passe sont différents.');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            audit('password_changed');
            send_mail($user['email'], 'Votre mot de passe a été modifié',
                "Bonjour,\n\nLe mot de passe de votre journal de classe a été modifié le " . date('d/m/Y à H:i') . ".\nSi ce n'est pas vous, contactez immédiatement votre hébergeur.\n");
            flash('success', 'Mot de passe modifié.');
        }
    }
    redirect('account.php');
}

$log = fetch_all('SELECT * FROM audit_log ORDER BY id DESC LIMIT 25');
$labels = ['login_success' => 'Connexion réussie', 'login_failed' => 'Échec de connexion', 'logout' => 'Déconnexion',
    'password_changed' => 'Mot de passe modifié', 'install' => 'Installation', 'year_archive_toggle' => 'Archivage d\'une année'];

page_start('Mon compte', 'account.php');
?>
<div class="page-head"><div><div class="eyebrow">Sécurité</div><h1>Mon compte</h1><p><?= e($user['email']) ?> · double authentification par email activée</p></div></div>

<div class="grid grid-2">
    <div class="stack">
        <form method="post" class="card">
            <?= csrf_field() ?><input type="hidden" name="action" value="profile">
            <div class="card-head"><h2>Nom affiché</h2></div>
            <div class="row"><input type="text" name="display_name" value="<?= e($user['display_name']) ?>" required><button class="btn" type="submit">Enregistrer</button></div>
        </form>
        <form method="post" class="card">
            <?= csrf_field() ?><input type="hidden" name="action" value="password">
            <div class="card-head"><h2>Changer le mot de passe</h2></div>
            <div class="stack">
                <div class="field"><label for="current_password">Mot de passe actuel</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div>
                <div class="field"><label for="new_password">Nouveau mot de passe</label><input id="new_password" type="password" name="new_password" minlength="12" autocomplete="new-password" required><small>12 caractères minimum, lettres et chiffres.</small></div>
                <div class="field"><label for="new_password_confirm">Confirmer</label><input id="new_password_confirm" type="password" name="new_password_confirm" minlength="12" autocomplete="new-password" required></div>
                <div><button class="btn btn-primary" type="submit">Modifier</button></div>
            </div>
        </form>
    </div>
    <section class="card card-flush">
        <div class="card-head"><h2>Activité récente</h2></div>
        <div class="table-wrap"><table>
            <tbody>
            <?php foreach ($log as $l): ?>
                <tr><td class="small"><?= e(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><?= $l['action'] === 'login_failed' ? '<span class="chip chip-danger">' . e($labels[$l['action']]) . '</span>' : e($labels[$l['action']] ?? $l['action']) ?></td>
                    <td class="small muted"><?= e($l['ip']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <p class="pad small muted">Un échec que vous ne reconnaissez pas ? Changez votre mot de passe.</p>
    </section>
</div>
<?php page_end();
