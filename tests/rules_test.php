<?php
/**
 * Tests des règles de calcul. Lancer : php tests/rules_test.php
 * (n'a pas besoin de base de données)
 */
declare(strict_types=1);
define('ROOT', dirname(__DIR__));
require ROOT . '/app/lib/helpers.php';
require ROOT . '/app/lib/school.php';

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

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent.\n";
exit($failures ? 1 : 0);
