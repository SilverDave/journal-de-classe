<?php
/**
 * Horaire hebdomadaire. Un créneau = une classe + un jour + la première période
 * + le nombre de périodes d'affilée (1 à 4). Pas besoin d'encoder chaque heure.
 */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');
$classes = year_classes((int)$year['id']);
$classIds = array_map('intval', array_column($classes, 'id'));

if (is_post()) {
    require_writable('schedule.php');
    $action = input('action');

    if ($action === 'add') {
        $classId = input_int('class_id');
        $weekday = input_int('weekday');
        $start = input_int('start_period');
        $duration = input_int('duration', 1);
        if (!in_array($classId, $classIds, true) || $weekday < 1 || $weekday > 5 || !isset(PERIODS[$start]) || $duration < 1 || $duration > MAX_LESSON_PERIODS) {
            flash('error', 'Créneau invalide.');
        } elseif (!isset(PERIODS[$start + $duration - 1])) {
            flash('error', 'Le cours dépasse la dernière période (10). Réduisez la durée.');
        } elseif ($clash = slot_overlaps((int)$year['id'], $weekday, $start, $duration)) {
            flash('error', 'Ce créneau chevauche déjà un cours avec ' . $clash['class_name'] . '.');
        } else {
            q('INSERT INTO schedule_slots (class_id, weekday, start_period, duration, room) VALUES (?, ?, ?, ?, ?)',
                [$classId, $weekday, $start, $duration, mb_substr(input('room'), 0, 30) ?: null]);
            flash('success', 'Cours ajouté à l\'horaire : ' . JOURS[$weekday] . ', ' . tranche_label($start, $duration) . '.');
        }
    }
    if ($action === 'delete') {
        q('DELETE sl FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id WHERE sl.id = ? AND c.school_year_id = ?', [input_int('slot_id'), $year['id']]);
        flash('success', "Créneau retiré de l'horaire (les cours déjà donnés restent dans le journal).");
    }
    redirect('schedule.php');
}

$slots = fetch_all(
    'SELECT sl.*, c.name AS class_name, c.color, c.subject, s.name AS school_name
     FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id JOIN schools s ON s.id = c.school_id
     WHERE c.school_year_id = ? ORDER BY sl.weekday, sl.start_period', [$year['id']]);
$totalPeriods = array_sum(array_column($slots, 'duration'));
$readonly = year_is_archived();

page_start('Horaire', 'schedule.php');
?>
<div class="page-head">
    <div><div class="eyebrow">Horaire · <?= e($year['label']) ?></div><h1>Mon horaire de la semaine</h1>
        <p><?= $totalPeriods ?> période<?= $totalPeriods > 1 ? 's' : '' ?> par semaine · <?= count($slots) ?> cours</p></div>
</div>

<?php if (!$classes): ?>
    <?= empty_state('Aucune classe', 'Créez vos classes avant de construire votre horaire.', 'schools.php', 'Écoles & élèves') ?>
<?php else: ?>

<?php if (!$readonly): ?>
<form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="card-head"><h2>Ajouter un cours à l'horaire</h2></div>
    <div class="form-grid">
        <div class="field"><label for="class_id">Classe</label>
            <select id="class_id" name="class_id" required>
                <?php foreach ($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name'] . ($c['subject'] ? ' · ' . $c['subject'] : '') . ' — ' . $c['school_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label for="weekday">Jour</label>
            <select id="weekday" name="weekday"><?php for ($d = 1; $d <= 5; $d++): ?><option value="<?= $d ?>"><?= JOURS[$d] ?></option><?php endfor; ?></select></div>
        <div class="field"><label for="start_period">Commence à la période</label>
            <select id="start_period" name="start_period"><?php foreach (PERIODS as $p => $t): ?><option value="<?= $p ?>"><?= $p ?> — <?= e(period_time($p)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Durée (périodes d'affilée)</label>
            <div class="duration-picker">
                <?php for ($d = 1; $d <= MAX_LESSON_PERIODS; $d++): ?><label><input type="radio" name="duration" value="<?= $d ?>" <?= $d === 2 ? 'checked' : '' ?>><span><?= $d ?></span></label><?php endfor; ?>
            </div></div>
        <div class="field"><label for="room">Local <small>(facultatif)</small></label><input id="room" type="text" name="room" maxlength="30" placeholder="B12"></div>
        <div><button class="btn btn-primary btn-block" type="submit"><?= icon('plus') ?> Ajouter</button></div>
    </div>
</form>
<?php endif; ?>

<section class="card mt">
    <div class="table-wrap">
        <div class="week">
            <div></div>
            <?php for ($d = 1; $d <= 5; $d++): ?><div class="week-head"><?= JOURS[$d] ?></div><?php endfor; ?>
            <?php foreach (PERIODS as $p => [$from, $to]): ?>
                <div class="week-period row-<?= $p ?><?= in_array($p, [2, 7], true) ? ' break-after' : '' ?>"><b>P<?= $p ?></b><?= e(str_replace(':', 'h', $from)) ?> – <?= e(str_replace(':', 'h', $to)) ?></div>
                <?php for ($d = 1; $d <= 5; $d++): ?><div class="week-cell col-<?= $d ?> row-<?= $p ?>"></div><?php endfor; ?>
            <?php endforeach; ?>
            <?php foreach ($slots as $sl): ?>
                <div class="week-slot c-<?= (int)$sl['color'] ?> col-<?= (int)$sl['weekday'] ?> row-<?= (int)$sl['start_period'] ?> span-<?= (int)$sl['duration'] ?>">
                    <strong><?= e($sl['class_name']) ?></strong>
                    <span class="muted"><?= e($sl['subject'] ?: $sl['school_name']) ?><?= $sl['room'] ? ' · ' . e($sl['room']) : '' ?></span>
                    <span class="small"><?= $sl['duration'] ?> période<?= $sl['duration'] > 1 ? 's' : '' ?></span>
                    <?php if (!$readonly): ?>
                    <form method="post" data-confirm="Retirer ce cours de l'horaire ?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?= (int)$sl['id'] ?>">
                        <button class="link-btn danger small" type="submit">Retirer</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <p class="small muted mt">Le trait jaune marque les récréations (10h05 – 10h20 et 14h25 – 14h35). Les cours apparaissent automatiquement dans le journal, sauf pendant les congés.</p>
</section>
<?php endif; ?>
<?php page_end();
