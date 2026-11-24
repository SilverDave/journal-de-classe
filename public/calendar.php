<?php
/** Journal de classe : vue mensuelle (lundi → vendredi) avec congés FWB et cours. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');

$ym = input('month');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = date_in_year(date('Y-m-d'), $year) ? date('Y-m') : substr($year['start_date'], 0, 7);
}
$first = "$ym-01";
$last = date('Y-m-t', strtotime($first));
$prevMonth = date('Y-m', strtotime("$first -1 month"));
$nextMonth = date('Y-m', strtotime("$first +1 month"));

// On commence au lundi de la première semaine du mois
$gridStart = add_days($first, -((int)date('N', strtotime($first)) - 1));
$gridEnd = add_days($last, 7 - (int)date('N', strtotime($last)));
$holidays = holidays_between($gridStart, $gridEnd);
$today = date('Y-m-d');

page_start('Journal de classe', 'calendar.php');
?>
<div class="page-head">
    <div><div class="eyebrow">Journal de classe · <?= e($year['label']) ?></div><h1>Calendrier</h1></div>
    <div class="actions">
        <?php if (!year_is_archived()): ?><a class="btn" href="lesson_add.php"><?= icon('plus') ?> Cours exceptionnel</a><?php endif; ?>
    </div>
</div>

<div class="cal-toolbar">
    <a class="icon-btn" href="?month=<?= e($prevMonth) ?>" aria-label="Mois précédent"><?= icon('left') ?></a>
    <h2><?= e(month_fr($ym)) ?></h2>
    <a class="icon-btn" href="?month=<?= e($nextMonth) ?>" aria-label="Mois suivant"><?= icon('right') ?></a>
    <a class="btn btn-ghost btn-sm" href="calendar.php">Ce mois-ci</a>
</div>

<div class="cal">
    <?php foreach ([1, 2, 3, 4, 5] as $d): ?><div class="cal-head"><?= JOURS[$d] ?></div><?php endforeach; ?>
    <?php for ($d = $gridStart; $d <= $gridEnd; $d = add_days($d, 1)):
        if ((int)date('N', strtotime($d)) > 5) continue;
        $inMonth = substr($d, 0, 7) === $ym;
        $holiday = $holidays[$d] ?? null;
        $tranches = date_in_year($d, $year) ? day_tranches($d, $year, $holidays) : [];
        $classes = ['cal-day'];
        if (!$inMonth) $classes[] = 'is-out';
        if ($d === $today) $classes[] = 'is-today';
        if ($holiday) $classes[] = 'is-holiday'; ?>
        <div class="<?= implode(' ', $classes) ?>">
            <div class="cal-date">
                <a href="attendance.php?date=<?= e($d) ?>"><?= (int)date('j', strtotime($d)) ?><span class="sr-only"> <?= e(date_fr($d)) ?></span></a>
                <?php if (!$holiday && !year_is_archived() && date_in_year($d, $year)): ?><a class="cal-add" href="lesson_add.php?date=<?= e($d) ?>" title="Ajouter un cours"><?= icon('plus', 'icon icon-sm') ?></a><?php endif; ?>
            </div>
            <?php if ($holiday): ?><div class="cal-holiday">☀️ <?= e($holiday) ?></div><?php endif; ?>
            <?php foreach ($tranches as $t):
                $state = $t['cancelled'] ? ' is-cancelled' : ($t['attendance_saved_at'] ? ' is-done' : ($d < $today ? ' is-late' : '')); ?>
                <a class="cal-lesson c-<?= (int)$t['color'] . $state ?>" href="<?= e(tranche_url($t)) ?>">
                    <b>P<?= $t['start_period'] ?><?= $t['duration'] > 1 ? '-' . ($t['start_period'] + $t['duration'] - 1) : '' ?></b> <?= e($t['class_name']) ?>
                    <?php if ($t['title']): ?><span class="t"><?= e($t['title']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endfor; ?>
</div>
<p class="small muted mt">✓ = présences enregistrées · bordure rouge = présences manquantes · cliquez sur un jour pour la vue détaillée.</p>
<?php page_end();
