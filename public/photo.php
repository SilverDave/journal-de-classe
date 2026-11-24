<?php
/** Affiche une photo stockée hors du dossier public, uniquement si l'on est connecté. */
require __DIR__ . '/../app/bootstrap.php';
require_login();

$path = (string)($_GET['f'] ?? '');
$file = ROOT . '/storage/uploads/' . $path;
if (!valid_photo_path($path) || !is_file($file)) {
    http_response_code(404);
    exit('Photo introuvable.');
}
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: inline; filename="copie.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
readfile($file);
