<?php
/** Calculs statistiques (présences, appréciations, moyennes). */

/**
 * Présences par élève d'une classe entre deux dates.
 * Retourne [student_id => ['total'=>, 'present'=>, 'P'=>, 'A'=>, 'AT'=>, 'R'=>]]
 * "present" = P + AT (voir PRESENT_STATUSES).
 */
function attendance_stats(int $classId, string $from, string $to, ?int $studentId = null): array
{
    $sql = "SELECT a.student_id, a.status, COUNT(*) AS n
            FROM attendance a JOIN lessons l ON l.id = a.lesson_id
            WHERE l.class_id = ? AND l.cancelled = 0 AND l.lesson_date BETWEEN ? AND ?"
        . ($studentId ? ' AND a.student_id = ' . (int)$studentId : '')
        . ' GROUP BY a.student_id, a.status';
    $stats = [];
    foreach (fetch_all($sql, [$classId, $from, $to]) as $r) {
        $s = &$stats[$r['student_id']];
        $s ??= ['total' => 0, 'present' => 0, 'P' => 0, 'A' => 0, 'AT' => 0, 'R' => 0];
        $s[$r['status']] = (int)$r['n'];
        $s['total'] += (int)$r['n'];
        if (in_array($r['status'], PRESENT_STATUSES, true)) {
            $s['present'] += (int)$r['n'];
        }
        unset($s);
    }
    return $stats;
}

/** Présences par élève et par mois : [student_id][AAAA-MM] => ['present'=>, 'total'=>] */
function attendance_by_month(int $classId, string $from, string $to): array
{
    $in = "'" . implode("','", PRESENT_STATUSES) . "'";
    $rows = fetch_all(
        "SELECT a.student_id, DATE_FORMAT(l.lesson_date, '%Y-%m') AS ym,
                SUM(a.status IN ($in)) AS present, COUNT(*) AS total
         FROM attendance a JOIN lessons l ON l.id = a.lesson_id
         WHERE l.class_id = ? AND l.cancelled = 0 AND l.lesson_date BETWEEN ? AND ?
         GROUP BY a.student_id, ym", [$classId, $from, $to]);
    $out = [];
    foreach ($rows as $r) {
        $out[$r['student_id']][$r['ym']] = ['present' => (int)$r['present'], 'total' => (int)$r['total']];
    }
    return $out;
}

/** Nombre de tranches par couleur d'appréciation : [student_id => ['vert'=>2, …]] */
function appreciation_stats(int $classId, string $from, string $to, ?int $studentId = null): array
{
    $sql = 'SELECT n.student_id, n.appreciation, COUNT(*) AS n
            FROM lesson_notes n JOIN lessons l ON l.id = n.lesson_id
            WHERE l.class_id = ? AND l.cancelled = 0 AND n.appreciation IS NOT NULL AND l.lesson_date BETWEEN ? AND ?'
        . ($studentId ? ' AND n.student_id = ' . (int)$studentId : '')
        . ' GROUP BY n.student_id, n.appreciation';
    $out = [];
    foreach (fetch_all($sql, [$classId, $from, $to]) as $r) {
        $out[$r['student_id']][$r['appreciation']] = (int)$r['n'];
    }
    return $out;
}

/** Statistiques d'une tranche, par période : [période => ['present'=>, 'total'=>]] */
function lesson_period_stats(int $lessonId): array
{
    $in = "'" . implode("','", PRESENT_STATUSES) . "'";
    $out = [];
    foreach (fetch_all("SELECT period, SUM(status IN ($in)) AS present, COUNT(*) AS total FROM attendance WHERE lesson_id = ? GROUP BY period", [$lessonId]) as $r) {
        $out[(int)$r['period']] = ['present' => (int)$r['present'], 'total' => (int)$r['total']];
    }
    return $out;
}

/**
 * Moyenne des évaluations par élève (somme des points / somme des maxima).
 * Les absences et notes vides ne comptent pas.
 * Retourne [student_id => ['on20'=>, 'on100'=>, 'count'=>]]
 */
function evaluation_averages(int $classId, string $from = '1000-01-01', string $to = '9999-12-31'): array
{
    $rows = fetch_all(
        'SELECT sc.student_id, SUM(sc.score) AS pts, SUM(e.max_points) AS maxi, COUNT(*) AS n
         FROM evaluation_scores sc JOIN evaluations e ON e.id = sc.evaluation_id
         WHERE e.class_id = ? AND sc.score IS NOT NULL AND sc.is_absent = 0 AND e.eval_date BETWEEN ? AND ?
         GROUP BY sc.student_id', [$classId, $from, $to]);
    $out = [];
    foreach ($rows as $r) {
        $out[$r['student_id']] = [
            'on20' => score_on((float)$r['pts'], (float)$r['maxi'], 20),
            'on100' => score_on((float)$r['pts'], (float)$r['maxi'], 100),
            'count' => (int)$r['n'],
        ];
    }
    return $out;
}

/** Couleur d'un pourcentage de présence (pour les badges). */
function rate_class(?float $present, ?float $total): string
{
    if (!$total) {
        return 'rate-none';
    }
    $r = $present / $total;
    return $r >= 0.9 ? 'rate-good' : ($r >= 0.75 ? 'rate-mid' : 'rate-bad');
}

function grade_class(?float $on20): string
{
    if ($on20 === null) {
        return 'rate-none';
    }
    return $on20 >= 14 ? 'rate-good' : ($on20 >= 10 ? 'rate-mid' : 'rate-bad');
}
