<?php
/**
 * Tests des règles de calcul. Lancer : php tests/rules_test.php
 * (n'a pas besoin de base de données)
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
require ROOT . '/app/lib/helpers.php';
require ROOT . '/app/lib/school.php';
require ROOT . '/app/lib/eval_periods.php';
require ROOT . '/app/lib/slot_validity.php';

$failures = 0;
function check(string $name, $expected, $actual): void
{
    global $failures;
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    echo ($ok ? '✓ ' : '✗ ') . $name . ($ok ? '' : ' — attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true)) . "\n";
}

// Horaire des périodes
check('période 1 = 8h25 > 9h15', ['08:25', '09:15'], PERIODS[1]);
check('période 3 = 10h20 > 11h10', ['10:20', '11:10'], PERIODS[3]);
check('période 8 = 14h35 > 15h20', ['14:35', '15:20'], PERIODS[8]);
check('période 10 = 16h10 > 17h00', ['16:10', '17:00'], PERIODS[10]);
check('10 périodes par jour', 10, count(PERIODS));
check('un cours dure 4 périodes maximum', 4, MAX_LESSON_PERIODS);
check('tranche de 2 périodes à partir de P3', 'P3-4 · 10h20 – 12h00', tranche_label(3, 2));

// Présences : P et AT = présent, A et R = absent
check('statuts disponibles', ['P', 'A', 'AT', 'R'], array_keys(STATUSES));
check('P et AT comptent comme présents', ['P', 'AT'], PRESENT_STATUSES);
check('R compte comme absent', false, in_array('R', PRESENT_STATUSES, true));

// Appréciations
check('4 couleurs', ['vert', 'orange', 'rouge', 'noir'], array_keys(APPRECIATIONS));

// Notes sur 20 et sur 100
check('21,5/25 = 17,2/20', 17.2, score_on(21.5, 25, 20));
check('21,5/25 = 86/100', 86.0, score_on(21.5, 25, 100));
check('pas de note = pas de moyenne', null, score_on(null, 25, 20));

// Périodes d'évaluation
$year = ['start_date' => '2026-09-01', 'end_date' => '2027-06-30'];
$p1 = ['id' => 1, 'label' => 'P1', 'start_date' => '2026-09-01', 'end_date' => '2026-12-20'];
$p2 = ['id' => 2, 'label' => 'P2', 'start_date' => '2026-12-21', 'end_date' => '2027-03-31'];
check('période valide', null, eval_period_error('P3', '2027-04-01', '2027-06-30', $year, [$p1, $p2]));
check('fin avant début refusée', true, eval_period_error('P', '2026-10-10', '2026-10-01', $year, []) !== null);
check('hors année refusée', true, eval_period_error('P', '2026-08-01', '2026-10-01', $year, []) !== null);
check('chevauchement refusé', true, eval_period_error('P', '2026-12-01', '2027-01-15', $year, [$p1]) !== null);
check('jour de fin = jour de début autorisé', null, eval_period_error('P', '2026-10-01', '2026-10-01', $year, []));
check('moyenne simple ignore les vides', 12.0, mean_of([10.0, null, 14.0]));
check('aucune valeur = pas de moyenne', null, mean_of([null]));

$evals = [
    ['id' => 3, 'eval_date' => '2027-01-10', 'title' => 'c'],
    ['id' => 1, 'eval_date' => '2026-10-05', 'title' => 'a'],
    ['id' => 2, 'eval_date' => '2026-12-01', 'title' => 'b'],
    ['id' => 4, 'eval_date' => '2027-05-01', 'title' => 'hors période'],
];
$kinds = array_map(fn($r) => $r['type'] === 'eval' ? 'e' . $r['eval']['id'] : 'M' . $r['period']['id'], eval_timeline($evals, [$p1, $p2]));
check('ligne de moyenne après la dernière éval de chaque période', ['e1', 'e2', 'M1', 'e3', 'M2', 'e4'], $kinds);
$kinds = array_map(fn($r) => $r['type'] === 'eval' ? 'e' . $r['eval']['id'] : 'M' . $r['period']['id'], eval_timeline([$evals[1]], [$p1, $p2]));
check('période sans évaluation = pas de ligne', ['e1', 'M1'], $kinds);
check('sans période, simple liste chronologique', ['e1', 'e2', 'e3', 'e4'], array_map(fn($r) => 'e' . $r['eval']['id'], eval_timeline($evals, [])));

// Dates de début / fin d'un cours (remplacements, un mois sur deux)
check('sans dates = toujours actif', true, slot_active_on([], '2026-11-24'));
$rempl = ['valid_from' => '2026-10-01', 'valid_to' => '2026-12-18'];
check('remplacement : avant le début', false, slot_active_on($rempl, '2026-09-30'));
check('remplacement : premier jour', true, slot_active_on($rempl, '2026-10-01'));
check('remplacement : dernier jour', true, slot_active_on($rempl, '2026-12-18'));
check('remplacement : après la fin', false, slot_active_on($rempl, '2026-12-19'));
$deux = ['valid_from' => '2026-09-01', 'valid_to' => null, 'month_step' => 2];
check('un mois sur deux : septembre oui', true, slot_active_on($deux, '2026-09-15'));
check('un mois sur deux : octobre non', false, slot_active_on($deux, '2026-10-15'));
check('un mois sur deux : novembre oui', true, slot_active_on($deux, '2026-11-24'));
check('un mois sur deux : janvier (autre année) oui', true, slot_active_on($deux, '2027-01-12'));
check('un mois sur deux : février non', false, slot_active_on($deux, '2027-02-12'));
check('étiquette remplacement', 'du 01/10 au 18/12', slot_validity_label($rempl));
check('étiquette un mois sur deux', 'à partir du 01/09 · un mois sur deux', slot_validity_label($deux));
check('étiquette vide si toute l\'année', '', slot_validity_label([]));
$oct = ['valid_from' => '2026-10-01', 'valid_to' => null, 'month_step' => 2];
check('conflit : deux cours toute l\'année', true, slot_ranges_conflict([], []));
check('pas de conflit : remplacements successifs', false, slot_ranges_conflict($rempl, ['valid_from' => '2026-12-19', 'valid_to' => '2027-06-30']));
check('pas de conflit : mois alternés opposés', false, slot_ranges_conflict($deux, $oct));
check('conflit : un mois sur deux vs toute l\'année', true, slot_ranges_conflict($deux, []));

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
