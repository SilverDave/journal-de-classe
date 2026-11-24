<?php
/**
 * Point de départ commun à toutes les pages.
 * Chaque page commence par : require __DIR__ . '/../app/bootstrap.php';
 */
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

$configFile = ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copiez config/config.example.php en config/config.php puis remplissez-le.');
}
$GLOBALS['CONFIG'] = require $configFile;

date_default_timezone_set('Europe/Brussels');
mb_internal_encoding('UTF-8');

// Les erreurs ne sont jamais affichées aux visiteurs en production.
$debug = (bool)($GLOBALS['CONFIG']['debug'] ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-errors.log');

// Dossiers privés nécessaires (créés automatiquement s'ils manquent après un envoi FTP)
foreach (['storage/logs', 'storage/sessions', 'storage/uploads/eval', 'storage/uploads/council'] as $dir) {
    if (!is_dir(ROOT . '/' . $dir)) {
        @mkdir(ROOT . '/' . $dir, 0750, true);
    }
}

require ROOT . '/app/lib/helpers.php';
require ROOT . '/app/lib/db.php';
require ROOT . '/app/lib/security.php';
require ROOT . '/app/lib/mailer.php';
require ROOT . '/app/lib/auth.php';
require ROOT . '/app/lib/school.php';
require ROOT . '/app/lib/years.php';
require ROOT . '/app/lib/slot_validity.php';
require ROOT . '/app/lib/calendar.php';
require ROOT . '/app/lib/stats.php';
require ROOT . '/app/lib/eval_periods.php';
require ROOT . '/app/lib/uploads.php';
require ROOT . '/app/lib/view.php';

send_security_headers();
start_secure_session();

// Toute requête POST doit contenir le jeton anti-CSRF.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_check();
}
