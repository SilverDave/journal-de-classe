<?php
/** Petites fonctions utilitaires utilisées partout. */

/** Lit une valeur de configuration. Ex : config('mail.from') */
function config(string $key, $default = null)
{
    $value = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Échappe un texte avant de l'afficher dans le HTML (protection XSS). */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Message affiché une seule fois sur la page suivante. type : success | error | info */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Lecture sécurisée des paramètres GET/POST. */
function input(string $key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function input_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;
    return (is_string($value) && preg_match('/^-?\d+$/', trim($value))) ? (int)$value : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Vérifie qu'une date est au format AAAA-MM-JJ et valide. */
function valid_date(?string $date): bool
{
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    return checkdate($m, $d, $y);
}

/** N'accepte que les liens http(s) (évite les liens "javascript:" dangereux). */
function clean_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 500) : null;
}

const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
const MOIS_COURTS = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** "mercredi 18 novembre 2026" */
function date_fr(string $date, bool $withDay = true): string
{
    $ts = strtotime($date);
    $txt = (int)date('j', $ts) . ' ' . MOIS[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withDay ? mb_strtolower(JOURS[(int)date('N', $ts)]) . ' ' . $txt : $txt;
}

/** "18/11" */
function date_short(string $date): string
{
    return date('d/m', strtotime($date));
}

/** "2026-11" -> "novembre 2026" */
function month_fr(string $ym): string
{
    [$y, $m] = explode('-', $ym);
    return MOIS[(int)$m] . ' ' . $y;
}

function add_days(string $date, int $days): string
{
    return date('Y-m-d', strtotime("$date $days day"));
}

/** Formate un nombre à la belge : 14,5 */
function num($value, int $decimals = 1): string
{
    if ($value === null) {
        return '–';
    }
    $txt = number_format((float)$value, $decimals, ',', ' ');
    return $decimals > 0 ? rtrim(rtrim($txt, '0'), ',') : $txt;
}

/** Pourcentage arrondi ou tiret si pas de données. */
function pct(?float $part, ?float $total): string
{
    return ($total ?? 0) > 0 ? (string)round(100 * $part / $total) . ' %' : '–';
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
