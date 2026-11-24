<?php
/**
 * Statistiques d'une classe :
 *  - le mois choisi : présences (P + AT = présent) et appréciations par tranche ;
 *  - l'année : taux de présence par élève et par mois.
 */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');
$classes = year_classes((int)$year['id']);
$class = selected_class($classes);
$months = year_months($year);
$ym = default_month($year);
$from = "$ym-01";
$to = date('Y-m-t', strtotime($from));

if ($class) {
    $cid = (int)$class['id'];
    $students = class_students($cid);
    $att = attendance_stats($cid, $from, $to);
    $appr = appreciation_stats($cid, $from, $to);
    $byMonth = attendance_by_month($cid, $year['start_date'], $year['end_date']);
    $lessonsCount = (int)fetch_value('SELECT COUNT(*) FROM lessons WHERE class_id = ? AND cancelled = 0 AND attendance_saved_at IS NOT NULL AND lesson_date BETWEEN ? AND ?', [$cid, $from, $to]);
    $sumPresent = array_sum(array_column($att, 'present'));
    $sumTotal = array_sum(array_column($att, 'total'));
}

page_start('Statistiques', 'stats.php');
?>
<div class="page-head">
    <div><div class="eyebrow">Statistiques · <?= e($year['label']) ?></div><h1>Statistiques</h1><p>P et AT comptent comme présents ; A et R comme absents. Une tranche = un cours (1 à 4 périodes).</p></div>
    <?php if ($class): ?>
    <form method="get" class="actions">
        <input type="hidden" name="class" value="<?= (int)$class['id'] ?>">
        <select name="month" data-autosubmit aria-label="Mois">
            <?php foreach ($months as $m): ?><option value="<?= e($m) ?>" <?= $m === $ym ? 'selected' : '' ?>><?= e(ucfirst(month_fr($m))) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-ghost" type="button" data-print><?= icon('printer') ?></button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$classes): ?>
    <?= empty_state('Aucune classe', 'Les statistiques apparaîtront dès que vous aurez pris des présences.', 'schools.php', 'Écoles & élèves') ?>
<?php else: ?>
<?= class_tabs($classes, $class, 'stats.php', ['month' => $ym]) ?>

<div class="grid grid-3 mb">
    <div class="card kpi"><span>Tranches données en <?= e(month_fr($ym)) ?></span><strong><?= $lessonsCount ?></strong></div>
    <div class="card kpi"><span>Présence moyenne</span><strong><?= pct($sumPresent, $sumTotal) ?></strong><span><?= $sumPresent ?>/<?= $sumTotal ?> périodes-élèves</span></div>
    <div class="card kpi"><span>Élèves actifs</span><strong><?= (int)$class['active_count'] ?></strong></div>
</div>

<section class="card card-flush">
    <div class="card-head"><h2><?= e($class['name']) ?> — <?= e(month_fr($ym)) ?></h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Élève</th><th class="center">Présence</th><th class="num">Périodes</th><th class="num">P</th><th class="num">AT</th><th class="num">A</th><th class="num">R</th><th>Appréciations (tranches)</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): $a = $att[$s['id']] ?? null; if (!$s['is_active'] && !$a) continue; ?>
            <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>">
                <td><a class="student-name" href="student.php?id=<?= (int)$s['id'] ?>"><?= e(student_name($s)) ?></a></td>
                <td class="center"><?= rate_badge($a['present'] ?? null, $a['total'] ?? null) ?></td>
                <td class="num"><?= (int)($a['present'] ?? 0) ?>/<?= (int)($a['total'] ?? 0) ?></td>
                <td class="num"><?= (int)($a['P'] ?? 0) ?></td><td class="num"><?= (int)($a['AT'] ?? 0) ?></td>
                <td class="num"><?= (int)($a['A'] ?? 0) ?></td><td class="num"><?= (int)($a['R'] ?? 0) ?></td>
                <td><?= appreciation_counts($appr[$s['id']] ?? []) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<section class="card card-flush mt">
    <div class="card-head"><h2>Présence sur l'année, mois par mois</h2></div>
    <div class="table-wrap"><table class="heat">
        <thead><tr><th>Élève</th><?php foreach ($months as $m): ?><th class="center"><?= e(MOIS_COURTS[(int)substr($m, 5, 2)]) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($students as $s): if (!$s['is_active'] && empty($byMonth[$s['id']])) continue; ?>
            <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>"><td class="student-name"><?= e(student_name($s)) ?></td>
                <?php foreach ($months as $m): $c = $byMonth[$s['id']][$m] ?? null; ?>
                    <td class="heat-cell"><span class="rate <?= rate_class($c['present'] ?? null, $c['total'] ?? null) ?>"><?= $c ? pct($c['present'], $c['total']) : '·' ?></span></td>
                <?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
<?php page_end();
