<?php
/** Présences d'une journée : la liste des tranches du jour, avec leurs statistiques. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');

$date = valid_date(input('date')) ? input('date') : date('Y-m-d');
if (!date_in_year($date, $year)) {
    $date = date('Y-m-d') < $year['start_date'] ? $year['start_date'] : (date('Y-m-d') > $year['end_date'] ? $year['end_date'] : date('Y-m-d'));
}
$tranches = day_tranches($date, $year);
$holiday = holiday_label($date);

// Jours de cours précédent / suivant (on saute week-ends et congés)
$prev = add_days($date, -1);
while ((int)date('N', strtotime($prev)) > 5 && $prev > $year['start_date']) $prev = add_days($prev, -1);
$next = add_days($date, 1);
while ((int)date('N', strtotime($next)) > 5 && $next < $year['end_date']) $next = add_days($next, 1);

page_start('Présences', 'attendance.php');
?>
<div class="page-head">
    <div>
        <div class="eyebrow">Présences</div>
        <h1><?= e(ucfirst(date_fr($date))) ?></h1>
    </div>
    <form class="actions" method="get">
        <a class="icon-btn" href="?date=<?= e($prev) ?>" aria-label="Jour précédent"><?= icon('left') ?></a>
        <input type="date" name="date" value="<?= e($date) ?>" min="<?= e($year['start_date']) ?>" max="<?= e($year['end_date']) ?>" data-autosubmit aria-label="Choisir une date">
        <a class="icon-btn" href="?date=<?= e($next) ?>" aria-label="Jour suivant"><?= icon('right') ?></a>
        <a class="btn btn-ghost" href="?date=<?= date('Y-m-d') ?>">Aujourd'hui</a>
    </form>
</div>

<?php if ($holiday): ?>
    <div class="flash flash-info">☀️ <?= e($holiday) ?> — jour de congé.</div>
<?php endif; ?>

<?php if (!$tranches): ?>
    <?= empty_state('Aucun cours ce jour-là', 'Rien n\'est prévu dans votre horaire. Vous pouvez ajouter un cours exceptionnel.', 'lesson_add.php?date=' . $date, 'Ajouter un cours') ?>
<?php else: ?>
    <div class="stack">
    <?php foreach ($tranches as $t):
        $stats = $t['lesson_id'] ? lesson_period_stats((int)$t['lesson_id']) : []; ?>
        <div class="card c-<?= (int)$t['color'] ?>">
            <div class="card-head">
                <div class="row">
                    <span class="swatch"></span>
                    <div>
                        <h3><?= e($t['class_name']) ?><?= $t['subject'] ? ' · ' . e($t['subject']) : '' ?> <small class="muted"><?= e($t['school_name']) ?></small></h3>
                        <div class="small muted"><?= e(tranche_label((int)$t['start_period'], (int)$t['duration'])) ?><?= $t['title'] ? ' — ' . e($t['title']) : '' ?></div>
                    </div>
                </div>
                <div class="actions">
                    <?php if ($t['cancelled']): ?><span class="chip chip-muted">Cours annulé</span>
                    <?php elseif ($t['attendance_saved_at']): ?><span class="chip chip-ok">✓ Enregistré</span>
                    <?php else: ?><span class="chip chip-warn">Présences à prendre</span><?php endif; ?>
                    <a class="btn btn-primary btn-sm" href="<?= e(tranche_url($t)) ?>"><?= $t['attendance_saved_at'] ? 'Modifier' : 'Prendre les présences' ?></a>
                </div>
            </div>
            <?php if ($stats): ?>
                <div class="row">
                    <?php for ($p = (int)$t['start_period']; $p < $t['start_period'] + $t['duration']; $p++): $s = $stats[$p] ?? ['present' => 0, 'total' => 0]; ?>
                        <div class="kpi card">
                            <span>Période <?= $p ?> · <?= e(period_time($p)) ?></span>
                            <strong><?= $s['present'] ?>/<?= $s['total'] ?></strong>
                            <?= rate_badge($s['present'], $s['total']) ?>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
    <?php if (!year_is_archived()): ?><p class="mt"><a href="lesson_add.php?date=<?= e($date) ?>"><?= icon('plus', 'icon icon-sm') ?> Ajouter un cours exceptionnel ce jour</a></p><?php endif; ?>
<?php endif; ?>
<?php page_end();
