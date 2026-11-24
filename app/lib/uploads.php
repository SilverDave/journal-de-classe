<?php
/**
 * Photos (copies d'évaluation, documents de conseil).
 * - stockées HORS du dossier public (storage/uploads), servies par photo.php après connexion ;
 * - type vérifié par le contenu réel du fichier ;
 * - ré-enregistrées en JPEG (supprime les métadonnées GPS des smartphones) ;
 * - nom aléatoire impossible à deviner.
 */

const UPLOAD_MAX_BYTES = 12 * 1024 * 1024;
const UPLOAD_FOLDERS = ['eval', 'council'];

/** Retourne le chemin relatif ("eval/ab12….jpg"), null si pas de fichier, ou lève une exception. */
function save_photo(?array $file, string $folder): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!in_array($folder, UPLOAD_FOLDERS, true)) {
        throw new RuntimeException('Dossier invalide.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("La photo n'a pas pu être envoyée (fichier trop lourd ?).");
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Photo trop lourde (12 Mo maximum).');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format non accepté : utilisez une photo JPEG, PNG ou WEBP.');
    }

    $name = bin2hex(random_bytes(16));
    $dir = ROOT . '/storage/uploads/' . $folder;

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
        if (!$img) {
            throw new RuntimeException('Image illisible.');
        }
        $img = fix_orientation($img, $file['tmp_name'], $mime);
        $img = limit_size($img, 2000);
        imagejpeg($img, "$dir/$name.jpg", 82);
        imagedestroy($img);
        return "$folder/$name.jpg";
    }
    // Sans l'extension GD : on garde le fichier tel quel.
    move_uploaded_file($file['tmp_name'], "$dir/$name.{$allowed[$mime]}");
    return "$folder/$name.{$allowed[$mime]}";
}

/** Redresse les photos de smartphone prises "de travers". */
function fix_orientation($img, string $path, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
    return $angle ? imagerotate($img, $angle, 0) : $img;
}

function limit_size($img, int $max)
{
    $w = imagesx($img);
    $h = imagesy($img);
    if (max($w, $h) <= $max) {
        return $img;
    }
    $ratio = $max / max($w, $h);
    $resized = imagescale($img, (int)($w * $ratio), (int)($h * $ratio));
    imagedestroy($img);
    return $resized;
}

function valid_photo_path(?string $path): bool
{
    return is_string($path) && preg_match('#^(eval|council)/[a-f0-9]{32}\.(jpg|png|webp)$#', $path) === 1;
}

function delete_photo(?string $path): void
{
    if (valid_photo_path($path) && is_file(ROOT . '/storage/uploads/' . $path)) {
        unlink(ROOT . '/storage/uploads/' . $path);
    }
}
