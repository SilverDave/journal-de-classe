<?php
/** Tableau de bord : les cours du jour et les présences en retard. */
require __DIR__ . '/../app/bootstrap.php';
$user = require_login();
$year = current_year();
$today = date('Y-m-d');

$classes = $year ? year_classes((int)$year['id']) : [];
$hasSlots = $year && (int)fetch_value('SELECT COUNT(*) FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id WHERE c.school_year_id = ?', [$year['id']]) > 0;
$todayTranches = $year ? day_tranches($today, $year) : [];
$missing = $year && !year_is_archived() ? missing_attendance($year, 21) : [];
$holidayToday = holiday_label($today);
$nextHoliday = next_holiday($today);
$hour = (int)date('G');

page_start("Aujourd'hui", 'index.php');
?>
<div class="page-head">
    <div>
        <div class="eyebrow"><?= e(date_fr($today)) ?></div>
        <h1><?= $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir') ?>, <?= e(explode(' ', $user['display_name'])[0]) ?></h1>
        <?php if (!empty($_SESSION['previous_login'])): ?>
            <p class="small muted">Dernière connexion : <?= e(date('d/m/Y à H:i', strtotime($_SESSION['previous_login']))) ?></p>
        <?php endif; ?>
    </div>
    <div class="actions">
        <a class="btn" href="calendar.php"><?= icon('calendar') ?> Journal de classe</a>
        <a class="btn btn-primary" href="attendance.php"><?= icon('check') ?> Présences du jour</a>
    </div>
</div>

<?php if (!$year): ?>
    <?= empty_state('Aucune année scolaire', 'Commencez par créer votre année scolaire.', 'years.php', 'Créer une année') ?>
<?php elseif (!$classes || !$hasSlots): ?>
    <div class="card">
        <h2>Bienvenue ! 3 étapes pour démarrer</h2>
        <div class="grid grid-3 mt">
            <a class="class-row c-1" href="schools.php"><span class="chip"><?= $classes ? '✓' : '1' ?></span><span class="grow">Ajouter vos écoles et classes</span></a>
            <a class="class-row c-2" href="schools.php"><span class="chip">2</span><span class="grow">Ajouter les élèves (copier-coller une liste)</span></a>
            <a class="class-row c-3" href="schedule.php"><span class="chip"><?= $hasSlots ? '✓' : '3' ?></span><span class="grow">Encoder votre horaire</span></a>
        </div>
    </div>
<?php endif; ?>

<?php if ($year): ?>
<div class="grid grid-2 mt">
    <section class="card">
        <div class="card-head">
            <h2>Cours d'aujourd'hui</h2>
            <span class="chip chip-muted"><?= count($todayTranches) ?> tranche<?= count($todayTranches) > 1 ? 's' : '' ?></span>
        </div>
        <?php if ($holidayToday): ?>
            <div class="empty"><h3>☀️ <?= e($holidayToday) ?></h3><p>Pas de cours aujourd'hui.</p></div>
        <?php elseif (!$todayTranches): ?>
            <div class="empty"><h3>Pas de cours</h3><p>Rien à votre horaire aujourd'hui.</p></div>
        <?php else: ?>
            <div class="stack">
            <?php foreach ($todayTranches as $t): ?>
                <a class="tranche c-<?= (int)$t['color'] ?><?= $t['cancelled'] ? ' is-cancelled' : '' ?>" href="<?= e(tranche_url($t)) ?>">
                    <div class="tranche-time"><strong><?= e(str_replace(':', 'h', PERIODS[$t['start_period']][0])) ?></strong><span>P<?= $t['start_period'] ?><?= $t['duration'] > 1 ? '-' . ($t['start_period'] + $t['duration'] - 1) : '' ?></span></div>
                    <div>
                        <div class="tranche-title"><?= e($t['class_name']) ?><?= $t['subject'] ? ' · ' . e($t['subject']) : '' ?></div>
                        <div class="tranche-sub"><?= e($t['school_name']) ?><?= $t['title'] ? ' — ' . e($t['title']) : '' ?></div>
                    </div>
                    <?php if ($t['cancelled']): ?><span class="chip chip-muted">Annulé</span>
                    <?php elseif ($t['attendance_saved_at']): ?><span class="chip chip-ok">✓ Présences</span>
                    <?php else: ?><span class="chip chip-warn">À prendre</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card-head"><h2>Présences en attente</h2><?php if ($missing): ?><span class="chip chip-danger"><?= count($missing) ?></span><?php endif; ?></div>
            <?php if (!$missing): ?>
                <p class="muted">Tout est à jour pour les 3 dernières semaines. 👏</p>
            <?php else: ?>
                <div class="stack">
                <?php foreach (array_slice($missing, 0, 6) as $t): ?>
                    <a class="class-row c-<?= (int)$t['color'] ?>" href="<?= e(tranche_url($t)) ?>">
                        <span class="swatch"></span>
                        <span class="grow"><?= e($t['class_name']) ?> <small class="muted">· <?= e(date_fr($t['date'])) ?>, P<?= $t['start_period'] ?></small></span>
                        <?= icon('right', 'icon icon-sm') ?>
                    </a>
                <?php endforeach; ?>
                <?php if (count($missing) > 6): ?><a class="small" href="calendar.php">Voir tout dans le journal →</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="grid grid-kpi">
                <div class="kpi"><strong><?= count($classes) ?></strong><span>classes</span></div>
                <div class="kpi"><strong><?= array_sum(array_column($classes, 'active_count')) ?></strong><span>élèves actifs</span></div>
                <?php if ($nextHoliday): ?>
                    <?php $days = (int)ceil((strtotime($nextHoliday['start_date']) - strtotime($today)) / 86400); ?>
                    <div class="kpi"><strong><?= max(0, $days) ?> j</strong><span>avant : <?= e($nextHoliday['label']) ?></span></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php endif; ?>
<?php page_end();
