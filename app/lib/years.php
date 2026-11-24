<?php
/**
 * Années scolaires & archivage.
 * Les classes appartiennent à une année. Une année archivée est en lecture seule.
 */

function all_years(): array
{
    return fetch_all('SELECT * FROM school_years ORDER BY start_date DESC');
}

/** Année affichée : celle choisie dans le menu, sinon l'année en cours (non archivée). */
function current_year(): ?array
{
    static $year = false;
    if ($year !== false) {
        return $year;
    }
    $year = null;
    if (!empty($_SESSION['year_id'])) {
        $year = fetch_one('SELECT * FROM school_years WHERE id = ?', [$_SESSION['year_id']]);
    }
    if (!$year) {
        $today = date('Y-m-d');
        $year = fetch_one('SELECT * FROM school_years WHERE is_archived = 0 ORDER BY (? BETWEEN start_date AND end_date) DESC, start_date DESC LIMIT 1', [$today])
            ?? fetch_one('SELECT * FROM school_years ORDER BY start_date DESC LIMIT 1');
    }
    return $year;
}

function year_is_archived(): bool
{
    $y = current_year();
    return $y && (int)$y['is_archived'] === 1;
}

/** Bloque toute modification quand l'année affichée est archivée. */
function require_writable(string $back = 'index.php'): void
{
    if (year_is_archived()) {
        flash('error', 'Cette année est archivée : elle est consultable mais plus modifiable.');
        redirect($back);
    }
}

function date_in_year(string $date, array $year): bool
{
    return $date >= $year['start_date'] && $date <= $year['end_date'];
}

/** Mois couverts par l'année : ['2026-08', '2026-09', …] */
function year_months(array $year): array
{
    $months = [];
    $d = substr($year['start_date'], 0, 7) . '-01';
    while ($d <= $year['end_date']) {
        $months[] = substr($d, 0, 7);
        $d = date('Y-m-d', strtotime("$d +1 month"));
    }
    return $months;
}

/** Mois par défaut : le mois actuel s'il est dans l'année, sinon le dernier. */
function default_month(array $year): string
{
    $months = year_months($year);
    $ym = input('month', date('Y-m'));
    return in_array($ym, $months, true) ? $ym : (in_array(date('Y-m'), $months, true) ? date('Y-m') : end($months));
}
