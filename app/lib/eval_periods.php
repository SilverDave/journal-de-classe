<?php
/**
 * Périodes d'évaluation : un début et une fin (ex. « Période 1 : 01/09 → 20/12 »).
 * Les évaluations ne sont PAS liées à une période en base : elles tombent dans une
 * période d'après leur date. Aucune donnée existante n'est donc modifiée.
 * Les périodes sont propres à une école et à une année scolaire.
 */

/** Périodes d'une école pour une année, dans l'ordre chronologique. */
function eval_periods(int $yearId, int $schoolId): array
{
    return fetch_all('SELECT * FROM evaluation_periods WHERE school_year_id = ? AND school_id = ? ORDER BY start_date', [$yearId, $schoolId]);
}

/** Vérifie une période. Renvoie un message d'erreur, ou null si tout est correct. */
function eval_period_error(string $label, string $start, string $end, array $year, array $others): ?string
{
    if ($label === '' || !valid_date($start) || !valid_date($end)) {
        return 'Indiquez un nom, une date de début et une date de fin.';
    }
    if ($end < $start) {
        return 'La date de fin doit être après la date de début.';
    }
    if ($start < $year['start_date'] || $end > $year['end_date']) {
        return 'La période doit rester dans l\'année scolaire (' . date_short($year['start_date']) . ' → ' . date_short($year['end_date']) . ').';
    }
    foreach ($others as $o) {
        if ($start <= $o['end_date'] && $end >= $o['start_date']) {
            return 'Cette période chevauche « ' . $o['label'] . ' ».';
        }
    }
    return null;
}

/** Moyenne simple des valeurs non nulles, ou null s'il n'y en a aucune. */
function mean_of(array $values): ?float
{
    $values = array_values(array_filter($values, fn($v) => $v !== null));
    return $values ? round(array_sum($values) / count($values), 1) : null;
}

/**
 * Liste chronologique : évaluations + une ligne « moyenne » après la dernière
 * évaluation de chaque période (si la période contient au moins une évaluation).
 * Pure fonction (sans base de données) pour pouvoir la tester.
 * Retourne des lignes ['type' => 'eval', 'eval' => …] ou ['type' => 'period', 'period' => …, 'count' => n].
 */
function eval_timeline(array $evals, array $periods): array
{
    usort($evals, fn($a, $b) => [$a['eval_date'], $a['id']] <=> [$b['eval_date'], $b['id']]);
    $rows = [];
    $closed = [];
    foreach ($evals as $ev) {
        // Si cette évaluation est après la fin d'une période encore ouverte, on la clôture d'abord.
        foreach ($periods as $p) {
            if (!isset($closed[$p['id']]) && $ev['eval_date'] > $p['end_date']) {
                $closed[$p['id']] = true;
                if (($n = eval_count_in($evals, $p)) > 0) {
                    $rows[] = ['type' => 'period', 'period' => $p, 'count' => $n];
                }
            }
        }
        $rows[] = ['type' => 'eval', 'eval' => $ev];
    }
    foreach ($periods as $p) { // périodes encore ouvertes après la dernière évaluation
        if (!isset($closed[$p['id']]) && ($n = eval_count_in($evals, $p)) > 0) {
            $rows[] = ['type' => 'period', 'period' => $p, 'count' => $n];
        }
    }
    return $rows;
}

function eval_count_in(array $evals, array $period): int
{
    return count(array_filter($evals, fn($e) => $e['eval_date'] >= $period['start_date'] && $e['eval_date'] <= $period['end_date']));
}

/**
 * Moyennes /20 de chaque élève par période : [period_id => [student_id => ['on20'=>, 'on100'=>, 'count'=>]]]
 * Même règle de calcul que partout : total des points ÷ total possible, absences ignorées.
 */
function period_averages(int $classId, array $periods): array
{
    $out = [];
    foreach ($periods as $p) {
        $out[$p['id']] = evaluation_averages($classId, $p['start_date'], $p['end_date']);
    }
    return $out;
}
