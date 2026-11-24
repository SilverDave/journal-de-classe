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
        $from = input('valid_from') ?: null;
        $to = input('valid_to') ?: null;
        $step = input_int('rhythm', 1) === 2 ? 2 : 1;
        $validity = ['valid_from' => $from, 'valid_to' => $to, 'month_step' => $step];
        $hasValidity = $from || $to || $step > 1;
        if (!in_array($classId, $classIds, true) || $weekday < 1 || $weekday > 5 || !isset(PERIODS[$start]) || $duration < 1 || $duration > MAX_LESSON_PERIODS) {
            flash('error', 'Créneau invalide.');
        } elseif (!isset(PERIODS[$start + $duration - 1])) {
            flash('error', 'Le cours dépasse la dernière période (10). Réduisez la durée.');
        } elseif (($from !== null && !valid_date($from)) || ($to !== null && !valid_date($to))) {
            flash('error', 'Dates invalides.');
        } elseif ($from && $to && $to < $from) {
            flash('error', 'La date de fin doit être après la date de début.');
        } elseif ($step === 2 && !$from) {
            flash('error', 'Pour « un mois sur deux », indiquez la date de début : elle fixe le premier mois de cours.');
        } elseif ($hasValidity && !slot_validity_ready()) {
            flash('error', 'Impossible de créer la table des dates (droits MySQL ?). Importez database/migrations/002_schedule_validity.sql dans phpMyAdmin.');
        } elseif ($clash = slot_overlaps((int)$year['id'], $weekday, $start, $duration, 0, $validity)) {
            flash('error', 'Ce créneau chevauche déjà un cours avec ' . $clash['class_name'] . '.');
        } else {
            q('INSERT INTO schedule_slots (class_id, weekday, start_period, duration, room) VALUES (?, ?, ?, ?, ?)',
                [$classId, $weekday, $start, $duration, mb_substr(input('room'), 0, 30) ?: null]);
            if ($hasValidity) {
                q('INSERT INTO schedule_slot_validity (slot_id, valid_from, valid_to, month_step) VALUES (?, ?, ?, ?)', [last_id(), $from, $to, $step]);
            }
            flash('success', 'Cours ajouté à l\'horaire : ' . JOURS[$weekday] . ', ' . tranche_label($start, $duration) . ($hasValidity ? ' (' . slot_validity_label($validity) . ')' : '') . '.');
        }
    }
    if ($action === 'delete') {
        q('DELETE sl FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id WHERE sl.id = ? AND c.school_year_id = ?', [input_int('slot_id'), $year['id']]);
        flash('success', "Créneau retiré de l'horaire (les cours déjà donnés restent dans le journal).");
    }
    redirect('schedule.php');
}

$ready = slot_validity_ready();
$allSlots = fetch_all(
    'SELECT sl.*, c.name AS class_name, c.color, c.subject, s.name AS school_name' . ($ready ? ', v.valid_from, v.valid_to, v.month_step' : '') . '
     FROM schedule_slots sl JOIN classes c ON c.id = sl.class_id JOIN schools s ON s.id = c.school_id'
    . ($ready ? ' LEFT JOIN schedule_slot_validity v ON v.slot_id = sl.id' : '') . '
     WHERE c.school_year_id = ? ORDER BY sl.weekday, sl.start_period', [$year['id']]);
// Horaire affiché à une date donnée (par défaut aujourd'hui, ou le début d'année si l'année n'est pas en cours)
$viewDate = valid_date(input('date')) ? input('date') : (date_in_year(date('Y-m-d'), $year) ? date('Y-m-d') : $year['start_date']);
$slots = array_values(array_filter($allSlots, fn($sl) => slot_active_on($sl, $viewDate)));
$otherSlots = array_values(array_filter($allSlots, fn($sl) => !slot_active_on($sl, $viewDate)));
$totalPeriods = array_sum(array_column($slots, 'duration'));
$readonly = year_is_archived();

page_start('Horaire', 'schedule.php');
?>
<div class="page-head">
    <div><div class="eyebrow">Horaire · <?= e($year['label']) ?></div><h1>Mon horaire de la semaine</h1>
        <p><?= $totalPeriods ?> période<?= $totalPeriods > 1 ? 's' : '' ?> par semaine · <?= count($slots) ?> cours · horaire valable le <?= e(date_fr($viewDate)) ?></p></div>
    <form method="get" class="row"><label class="small muted" for="view_date">Voir l'horaire du</label>
        <input id="view_date" type="date" name="date" value="<?= e($viewDate) ?>"><button class="btn btn-sm" type="submit">Afficher</button></form>
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
        <div class="field"><label for="valid_from">Premier jour <small>(facultatif)</small></label><input id="valid_from" type="date" name="valid_from"></div>
        <div class="field"><label for="valid_to">Dernier jour <small>(facultatif)</small></label><input id="valid_to" type="date" name="valid_to"></div>
        
        <div><button class="btn btn-primary btn-block" type="submit"><?= icon('plus') ?> Ajouter</button></div>
    </div>
    <p class="small muted mt">Remplacement ou cours qui s'arrête en cours d'année : indiquez le premier et le dernier jour. Sans dates, le cours vaut toute l'année. Les cours déjà donnés restent dans le journal quand un cours se termine.</p>
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
                    <?php if ($lbl = slot_validity_label($sl)): ?><span class="small muted"><?= e($lbl) ?></span><?php endif; ?>
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
<?php if ($otherSlots): ?>
<section class="card mt">
    <div class="card-head"><h2>Autres cours (pas actifs le <?= e(date_fr($viewDate)) ?>)</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Classe</th><th>Jour</th><th>Périodes</th><th>Dates</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($otherSlots as $sl): ?>
            <tr><td><b><?= e($sl['class_name']) ?></b> <small class="muted"><?= e($sl['school_name']) ?></small></td>
                <td><?= e(JOURS[(int)$sl['weekday']]) ?></td>
                <td><?= e(tranche_label((int)$sl['start_period'], (int)$sl['duration'])) ?></td>
                <td><?= e(slot_validity_label($sl) ?: 'toute l\'année') ?></td>
                <td><?php if (!$readonly): ?>
                    <form method="post" data-confirm="Retirer ce cours de l'horaire ?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slot_id" value="<?= (int)$sl['id'] ?>">
                        <button class="link-btn danger small" type="submit">Retirer</button>
                    </form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php page_end();
