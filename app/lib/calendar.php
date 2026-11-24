<?php
/**
 * Calendrier : congés, horaire hebdomadaire et "tranches" de cours.
 * Une tranche = un cours de 1 à 4 périodes d'affilée avec une classe, un jour donné.
 * Les tranches viennent de l'horaire ; elles ne sont enregistrées (table lessons)
 * qu'à l'ouverture, ou quand on ajoute un cours exceptionnel.
 */

function fwb_calendar(): array
{
    return require ROOT . '/app/lib/fwb_calendar.php';
}

/** Congés entre deux dates : ['2026-10-19' => 'Vacances d'automne', …] */
function holidays_between(string $from, string $to): array
{
    $map = [];
    $rows = fetch_all('SELECT * FROM holidays WHERE end_date >= ? AND start_date <= ? ORDER BY start_date', [$from, $to]);
    foreach ($rows as $h) {
        for ($d = max($h['start_date'], $from); $d <= min($h['end_date'], $to); $d = add_days($d, 1)) {
            $map[$d] = $h['label'];
        }
    }
    return $map;
}

function holiday_label(string $date): ?string
{
    return holidays_between($date, $date)[$date] ?? null;
}

function next_holiday(string $date): ?array
{
    return fetch_one('SELECT * FROM holidays WHERE end_date >= ? ORDER BY start_date LIMIT 1', [$date]);
}

/** Jour de cours ? (lundi-vendredi, dans l'année, pas en congé) */
function is_school_day(string $date, array $year, ?array $holidays = null): bool
{
    $holidays ??= holidays_between($date, $date);
    return (int)date('N', strtotime($date)) <= 5 && date_in_year($date, $year) && !isset($holidays[$date]);
}

/**
 * Toutes les tranches d'un jour : celles de l'horaire + les cours déjà enregistrés
 * (ajouts exceptionnels, cours annulés…). Triées par période.
 */
function day_tranches(string $date, array $year, ?array $holidays = null): array
{
    $tranches = [];
    $base = 'c.name AS class_name, c.color, c.subject, s.name AS school_name';

    if (is_school_day($date, $year, $holidays)) {
        $ready = slot_validity_ready();
        $slots = fetch_all(
            "SELECT sl.class_id, sl.start_period, sl.duration, sl.room, $base" . ($ready ? ', v.valid_from, v.valid_to, v.month_step' : '') . "
             FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id JOIN schools s ON s.id = c.school_id"
            . ($ready ? ' LEFT JOIN schedule_slot_validity v ON v.slot_id = sl.id' : '') . "
             WHERE c.school_year_id = ? AND sl.weekday = ?",
            [$year['id'], (int)date('N', strtotime($date))]);
        foreach ($slots as $sl) {
            if (!slot_active_on($sl, $date)) {
                continue; // remplacement terminé / pas encore commencé / mois « off »
            }
            $sl += ['lesson_id' => null, 'title' => null, 'document_url' => null, 'attendance_saved_at' => null, 'cancelled' => 0];
            $tranches[$sl['class_id'] . '-' . $sl['start_period']] = $sl;
        }
    }

    $lessons = fetch_all(
        "SELECT l.id AS lesson_id, l.class_id, l.start_period, l.duration, l.title, l.document_url,
                l.attendance_saved_at, l.cancelled, NULL AS room, $base
         FROM lessons l JOIN classes c ON c.id = l.class_id JOIN schools s ON s.id = c.school_id
         WHERE c.school_year_id = ? AND l.lesson_date = ?",
        [$year['id'], $date]);
    foreach ($lessons as $l) {
        $key = $l['class_id'] . '-' . $l['start_period'];
        $l['room'] = $tranches[$key]['room'] ?? null;
        $tranches[$key] = $l;
    }

    usort($tranches, fn($a, $b) => $a['start_period'] <=> $b['start_period']);
    foreach ($tranches as &$t) {
        $t['date'] = $date;
    }
    return $tranches;
}

/** Lien vers la page d'une tranche (créée à la volée si besoin). */
function tranche_url(array $t): string
{
    return $t['lesson_id']
        ? 'lesson.php?id=' . $t['lesson_id']
        : 'lesson.php?' . http_build_query(['class' => $t['class_id'], 'date' => $t['date'], 'start' => $t['start_period'], 'duration' => $t['duration']]);
}

/** Crée la tranche en base si elle n'existe pas encore et renvoie son id. */
function ensure_lesson(int $classId, string $date, int $start, int $duration): int
{
    q('INSERT IGNORE INTO lessons (class_id, lesson_date, start_period, duration, created_at) VALUES (?, ?, ?, ?, ?)',
        [$classId, $date, $start, $duration, now()]);
    return (int)fetch_value('SELECT id FROM lessons WHERE class_id = ? AND lesson_date = ? AND start_period = ?', [$classId, $date, $start]);
}

/** Tranches passées (N derniers jours) dont les présences n'ont pas été prises. */
function missing_attendance(array $year, int $days = 14): array
{
    $today = date('Y-m-d');
    $from = max($year['start_date'], add_days($today, -$days));
    $holidays = holidays_between($from, $today);
    $missing = [];
    for ($d = $from; $d < $today; $d = add_days($d, 1)) {
        foreach (day_tranches($d, $year, $holidays) as $t) {
            if (!$t['attendance_saved_at'] && !$t['cancelled']) {
                $missing[] = $t;
            }
        }
    }
    return array_reverse($missing);
}

/** Vérifie qu'un créneau d'horaire ne chevauche pas un autre cours le même jour (aux mêmes dates). */
function slot_overlaps(int $yearId, int $weekday, int $start, int $duration, int $ignoreId = 0, array $validity = []): ?array
{
    $end = $start + $duration - 1;
    $ready = slot_validity_ready();
    $rows = fetch_all(
        'SELECT sl.*, c.name AS class_name' . ($ready ? ', v.valid_from, v.valid_to, v.month_step' : '') . '
         FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id'
        . ($ready ? ' LEFT JOIN schedule_slot_validity v ON v.slot_id = sl.id' : '') . '
         WHERE c.school_year_id = ? AND sl.weekday = ? AND sl.id <> ?
           AND sl.start_period <= ? AND (sl.start_period + sl.duration - 1) >= ?',
        [$yearId, $weekday, $ignoreId, $end, $start]);
    foreach ($rows as $r) {
        if (slot_ranges_conflict($r, $validity)) {
            return $r;
        }
    }
    return null;
}
