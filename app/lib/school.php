<?php
/**
 * Réglages "métier" : périodes, statuts de présence, appréciations, couleurs.
 * Modifiez ici si votre école change d'horaire.
 */

/** Périodes de cours de la journée : numéro => [début, fin] */
const PERIODS = [
    1 => ['08:25', '09:15'],
    2 => ['09:15', '10:05'],
    3 => ['10:20', '11:10'],
    4 => ['11:10', '12:00'],
    5 => ['12:00', '12:50'],
    6 => ['12:50', '13:40'],
    7 => ['13:40', '14:25'],
    8 => ['14:35', '15:20'],
    9 => ['15:20', '16:10'],
    10 => ['16:10', '17:00'],
];
const MAX_LESSON_PERIODS = 4; // un cours dure de 1 à 4 périodes d'affilée

/** Statuts de présence. Ceux de PRESENT_STATUSES comptent comme "présent" dans les statistiques. */
const STATUSES = ['P' => 'Présent', 'A' => 'Absent', 'AT' => 'Arrivée tardive', 'R' => 'Renvoyé'];
const PRESENT_STATUSES = ['P', 'AT'];

/** Appréciation de l'implication, par tranche de cours. */
const APPRECIATIONS = [
    'vert' => 'Très bonne implication',
    'orange' => 'Implication moyenne',
    'rouge' => 'Faible implication',
    'noir' => 'Problème grave / refus',
];

/** Couleurs disponibles pour repérer les classes (voir .c-1 … .c-8 dans app.css) */
const CLASS_COLORS = [1 => 'Bleu', 2 => 'Vert', 3 => 'Corail', 4 => 'Ambre', 5 => 'Turquoise', 6 => 'Rose', 7 => 'Ardoise', 8 => 'Olive'];

function period_time(int $p): string
{
    return str_replace(':', 'h', PERIODS[$p][0]) . ' – ' . str_replace(':', 'h', PERIODS[$p][1]);
}

/** "P3-4 · 10h20 – 12h00" */
function tranche_label(int $start, int $duration): string
{
    $end = $start + $duration - 1;
    $p = $duration > 1 ? "P$start-$end" : "P$start";
    return $p . ' · ' . str_replace(':', 'h', PERIODS[$start][0]) . ' – ' . str_replace(':', 'h', PERIODS[$end][1]);
}

/* ---------- Écoles / classes / élèves ---------- */

function schools(): array
{
    return fetch_all('SELECT * FROM schools ORDER BY name');
}

/** Classes de l'année affichée, avec le nom de l'école. */
function year_classes(int $yearId): array
{
    return fetch_all(
        'SELECT c.*, s.name AS school_name,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.is_active = 1) AS active_count
         FROM classes c JOIN schools s ON s.id = c.school_id
         WHERE c.school_year_id = ? ORDER BY s.name, c.name', [$yearId]);
}

function find_class(int $id): ?array
{
    return fetch_one('SELECT c.*, s.name AS school_name FROM classes c JOIN schools s ON s.id = c.school_id WHERE c.id = ?', [$id]);
}

/** Élèves d'une classe (actifs d'abord, puis désactivés). */
function class_students(int $classId, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM students WHERE class_id = ?' . ($activeOnly ? ' AND is_active = 1' : '')
        . ' ORDER BY is_active DESC, last_name, first_name';
    return fetch_all($sql, [$classId]);
}

function student_name(array $s): string
{
    return trim($s['last_name'] . ' ' . $s['first_name']);
}

/** Choix d'une classe via ?class=ID, sinon la première de l'année. */
function selected_class(array $classes): ?array
{
    $id = input_int('class');
    foreach ($classes as $c) {
        if ((int)$c['id'] === $id) {
            return $c;
        }
    }
    return $classes[0] ?? null;
}

/** Convertit une note en /20 et /100. */
function score_on(?float $score, float $max, int $scale): ?float
{
    return ($score === null || $max <= 0) ? null : round($score / $max * $scale, 1);
}
