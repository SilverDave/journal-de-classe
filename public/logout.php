<?php
/** Déconnexion (uniquement via un formulaire POST protégé). */
require __DIR__ . '/../app/bootstrap.php';

if (is_post()) {
    logout();
    session_start();
    flash('success', 'Vous êtes déconnecté.');
}
redirect('login.php');
