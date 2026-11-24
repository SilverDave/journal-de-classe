<?php
/** Ajouter un cours exceptionnel (remplacement, cours déplacé…) à une date. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');
require_writable('calendar.php');

$classes = year_classes((int)$year['id']);
$date = valid_date(input('date')) ? input('date') : date('Y-m-d');

if (is_post()) {
    $classId = input_int('class_id');
    $start = input_int('start_period');
    $duration = input_int('duration', 1);
    $validClass = in_array($classId, array_map('intval', array_column($classes, 'id')), true);
    if (!$validClass || !valid_date($date) || !isset(PERIODS[$start]) || $duration < 1 || $duration > MAX_LESSON_PERIODS || !isset(PERIODS[$start + $duration - 1])) {
        flash('error', 'Vérifiez la classe, la période et la durée (le cours doit finir au plus tard en période 10).');
    } elseif (!date_in_year($date, $year)) {
        flash('error', "Cette date n'est pas dans l'année scolaire " . $year['label'] . '.');
    } else {
        redirect('lesson.php?id=' . ensure_lesson($classId, $date, $start, $duration));
    }
}

page_start('Ajouter un cours', 'calendar.php');
?>
<div class="page-head"><div><div class="eyebrow">Journal de classe</div><h1>Ajouter un cours</h1><p>Pour un cours qui n'est pas dans votre horaire habituel.</p></div></div>
<?php if (!$classes): ?>
    <?= empty_state('Aucune classe', "Créez d'abord une classe.", 'schools.php', 'Écoles & élèves') ?>
<?php else: ?>
<form method="post" class="card stack">
    <?= csrf_field() ?>
    <div class="form-grid">
        <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="<?= e($date) ?>" min="<?= e($year['start_date']) ?>" max="<?= e($year['end_date']) ?>" required></div>
        <div class="field"><label for="class_id">Classe</label>
            <select id="class_id" name="class_id" required>
                <?php foreach ($classes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name'] . ' — ' . $c['school_name']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="field"><label for="start_period">Première période</label>
            <select id="start_period" name="start_period">
                <?php foreach (PERIODS as $p => $t): ?><option value="<?= $p ?>">Période <?= $p ?> (<?= e(period_time($p)) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="field"><label>Nombre de périodes</label>
            <div class="duration-picker">
                <?php for ($d = 1; $d <= MAX_LESSON_PERIODS; $d++): ?><label><input type="radio" name="duration" value="<?= $d ?>" <?= $d === 1 ? 'checked' : '' ?>><span><?= $d ?></span></label><?php endfor; ?>
            </div></div>
    </div>
    <div><button class="btn btn-primary" type="submit">Créer le cours</button></div>
</form>
<?php endif; ?>
<?php page_end();
