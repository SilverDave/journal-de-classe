<?php
/**
 * CONFIGURATION DU SITE
 * ---------------------------------------------------------------
 * 1. Copiez ce fichier en "config.php" (dans le même dossier).
 * 2. Remplissez les informations de votre base MySQL OVH.
 * 3. Ne partagez JAMAIS le fichier config.php (il contient vos mots de passe).
 */
return [
    // Nom affiché en haut du site
    'app_name' => 'Journal de classe',

    // Adresse complète du site, sans "/" final (ex : https://journal.mondomaine.be)
    'app_url' => 'https://www.votre-domaine.be',

    // Base de données (Espace client OVH > Hébergement > Bases de données)
    'db' => [
        'host' => 'xxxxxx.mysql.db',
        'port' => 3306,
        'name' => 'nom_de_la_base',
        'user' => 'utilisateur',
        'pass' => 'mot_de_passe',
    ],

    // Clé secrète : remplacez par une longue phrase aléatoire (64 caractères minimum).
    // Elle sert à protéger les codes de connexion envoyés par email.
    'secret_key' => 'REMPLACEZ-MOI-par-une-longue-chaine-aleatoire-de-64-caracteres-min',

    // Code demandé une seule fois lors de l'installation (install.php)
    'install_token' => 'REMPLACEZ-MOI-code-installation',

    // Envoi des emails (code de double authentification)
    'mail' => [
        // L'adresse d'expédition DOIT appartenir à votre domaine OVH
        'from' => 'no-reply@votre-domaine.be',
        'from_name' => 'Journal de classe',
        // true = le code est écrit dans storage/logs/mail.log au lieu d'être envoyé.
        // À utiliser UNIQUEMENT pour tester en local. Toujours false en ligne.
        'debug' => false,
    ],

    // Sécurité
    'security' => [
        'session_idle_minutes' => 30,   // déconnexion après 30 min d'inactivité
        'session_max_hours' => 12,      // déconnexion forcée après 12 h
        'code_valid_minutes' => 10,     // durée de validité du code email
        'max_code_attempts' => 5,       // essais autorisés par code
        'max_login_failures' => 5,      // échecs autorisés…
        'lockout_minutes' => 15,        // …sur cette durée avant blocage
    ],

    // false en ligne : les erreurs sont écrites dans storage/logs/php-errors.log
    'debug' => false,
];
