<?php
/** Liste des évaluations d'une classe + moyennes générales /20 et /100. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');
$classes = year_classes((int)$year['id']);
$class = selected_class($classes);

if (is_post() && $class) {
    require_writable('evaluations.php?class=' . $class['id']);
    $back = 'evaluations.php?class=' . $class['id'];
    $action = input('action');

    if (in_array($action, ['period_add', 'period_save', 'period_delete'], true)) {
        $yearId = (int)$year['id'];
        $schoolId = (int)$class['school_id'];
        if ($action === 'period_delete') {
            q('DELETE FROM evaluation_periods WHERE id = ? AND school_year_id = ? AND school_id = ?', [input_int('period_id'), $yearId, $schoolId]);
            flash('success', 'Période supprimée (vos évaluations sont conservées).');
            redirect($back);
        }
        $pid = $action === 'period_save' ? input_int('period_id') : 0;
        $others = array_values(array_filter(eval_periods($yearId, $schoolId), fn($o) => (int)$o['id'] !== $pid));
        $label = mb_substr(input('label'), 0, 60);
        $error = eval_period_error($label, input('start_date'), input('end_date'), $year, $others);
        if ($error) {
            flash('error', $error);
        } elseif ($action === 'period_add') {
            q('INSERT INTO evaluation_periods (school_year_id, school_id, label, start_date, end_date) VALUES (?, ?, ?, ?, ?)',
                [$yearId, $schoolId, $label, input('start_date'), input('end_date')]);
            flash('success', 'Période ajoutée.');
        } else {
            q('UPDATE evaluation_periods SET label = ?, start_date = ?, end_date = ? WHERE id = ? AND school_year_id = ? AND school_id = ?',
                [$label, input('start_date'), input('end_date'), $pid, $yearId, $schoolId]);
            flash('success', 'Période modifiée.');
        }
        redirect($back);
    }

    $max = (float)str_replace(',', '.', (string)input('max_points'));
    $date = input('eval_date');
    if (input('title') === '' || $max <= 0 || !valid_date($date)) {
        flash('error', 'Indiquez un titre, une date et un total de points supérieur à 0.');
        redirect($back);
    }
    q('INSERT INTO evaluations (class_id, eval_date, title, max_points, description, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$class['id'], $date, mb_substr(input('title'), 0, 200), $max, input('description') ?: null, now()]);
    redirect('evaluation.php?id=' . last_id());
}

$evals = $class ? fetch_all(
    'SELECT e.*, COUNT(sc.score) AS graded, AVG(CASE WHEN sc.is_absent = 0 THEN sc.score END) AS avg_score
     FROM evaluations e LEFT JOIN evaluation_scores sc ON sc.evaluation_id = e.id
     WHERE e.class_id = ? GROUP BY e.id ORDER BY e.eval_date, e.id', [$class['id']]) : [];
$averages = $class ? evaluation_averages((int)$class['id']) : [];
$students = $class ? class_students((int)$class['id']) : [];
$periods = $class ? eval_periods((int)$year['id'], (int)$class['school_id']) : [];
$periodAvg = $class ? period_averages((int)$class['id'], $periods) : [];
$timeline = eval_timeline($evals, $periods);
$today = date('Y-m-d');

page_start('Évaluations', 'evaluations.php');
?>
<div class="page-head"><div><div class="eyebrow">Évaluations · <?= e($year['label']) ?></div><h1>Évaluations</h1><p>Encodez les points, prenez la copie en photo, les moyennes /20 et /100 se calculent seules.</p></div></div>

<?php if (!$classes): ?>
    <?= empty_state('Aucune classe', 'Créez une classe pour ajouter des évaluations.', 'schools.php', 'Écoles & élèves') ?>
<?php else: ?>
<?= class_tabs($classes, $class, 'evaluations.php') ?>

<div class="grid grid-2">
    <section class="card card-flush">
        <div class="card-head"><h2><?= e($class['name']) ?> — <?= count($evals) ?> évaluation<?= count($evals) > 1 ? 's' : '' ?></h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Titre</th><th class="num">Sur</th><th class="num">Moy. classe /20</th><th class="num">Encodés</th></tr></thead>
            <tbody>
            <?php foreach ($timeline as $row): ?>
                <?php if ($row['type'] === 'period'): $p = $row['period']; $vals = array_column($periodAvg[$p['id']] ?? [], 'on20'); $cls = mean_of($vals); ?>
                <tr class="row-period-avg">
                    <td><?= e(date_short($p['end_date'])) ?></td>
                    <td><b>Moyenne · <?= e($p['label']) ?></b><br><small class="muted"><?= e(date_short($p['start_date'])) ?> → <?= e(date_short($p['end_date'])) ?><?= ($p['start_date'] <= $today && $today <= $p['end_date']) ? ' · en cours' : '' ?></small></td>
                    <td class="num">20</td>
                    <td class="num"><span class="rate <?= grade_class($cls) ?>"><?= num($cls) ?></span></td>
                    <td class="num"><?= count($vals) ?> élève<?= count($vals) > 1 ? 's' : '' ?></td></tr>
                <?php else: $ev = $row['eval']; $a20 = $ev['avg_score'] !== null ? score_on((float)$ev['avg_score'], (float)$ev['max_points'], 20) : null; ?>
                <tr><td><?= e(date_short($ev['eval_date'])) ?></td><td><a href="evaluation.php?id=<?= (int)$ev['id'] ?>"><b><?= e($ev['title']) ?></b></a></td>
                    <td class="num"><?= num($ev['max_points']) ?></td>
                    <td class="num"><span class="rate <?= grade_class($a20) ?>"><?= num($a20) ?></span></td>
                    <td class="num"><?= (int)$ev['graded'] ?></td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$evals): ?><tr><td colspan="5" class="muted">Aucune évaluation pour cette classe.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <div class="stack">
        <?php if (!year_is_archived()): ?>
        <form method="post" class="card">
            <?= csrf_field() ?>
            <div class="card-head"><h2>Nouvelle évaluation</h2></div>
            <div class="form-grid">
                <div class="field"><label for="title">Titre</label><input id="title" type="text" name="title" required maxlength="200" placeholder="Interro chapitre 3"></div>
                <div class="field"><label for="eval_date">Date</label><input id="eval_date" type="date" name="eval_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="field"><label for="max_points">Total des points</label><input id="max_points" type="number" name="max_points" min="0.5" step="0.5" value="20" required></div>
            </div>
            <div class="field mt"><label for="description">Description <small>(facultatif)</small></label><textarea id="description" name="description" rows="2"></textarea></div>
            <button class="btn btn-primary mt" type="submit"><?= icon('plus') ?> Créer et encoder les points</button>
        </form>
        <?php endif; ?>

        <section class="card">
            <div class="card-head"><h2>Périodes d'évaluation · <?= e($class['school_name']) ?></h2></div>
            <p class="small muted">Donnez un début et une fin : la moyenne de chaque période apparaît en surbrillance dans la liste des évaluations. Les évaluations sont classées d'après leur date.</p>
            <?php foreach ($periods as $p): ?>
                <div class="period-row">
                    <form method="post" class="period-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="period_save"><input type="hidden" name="period_id" value="<?= (int)$p['id'] ?>">
                        <input type="text" name="label" value="<?= e($p['label']) ?>" maxlength="60" required aria-label="Nom de la période"<?= year_is_archived() ? ' disabled' : '' ?>>
                        <input type="date" name="start_date" value="<?= e($p['start_date']) ?>" required aria-label="Début"<?= year_is_archived() ? ' disabled' : '' ?>>
                        <input type="date" name="end_date" value="<?= e($p['end_date']) ?>" required aria-label="Fin"<?= year_is_archived() ? ' disabled' : '' ?>>
                        <?php if (!year_is_archived()): ?><button class="btn btn-sm" type="submit">Enregistrer</button><?php endif; ?>
                    </form>
                    <?php if (!year_is_archived()): ?>
                    <form method="post" data-confirm="Supprimer cette période ? Les évaluations ne sont pas supprimées.">
                        <?= csrf_field() ?><input type="hidden" name="action" value="period_delete"><input type="hidden" name="period_id" value="<?= (int)$p['id'] ?>">
                        <button class="link-btn danger small" type="submit">Supprimer</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$periods): ?><p class="small muted">Aucune période définie pour cette école.</p><?php endif; ?>
            <?php if (!year_is_archived()): ?>
            <form method="post" class="period-form mt">
                <?= csrf_field() ?><input type="hidden" name="action" value="period_add">
                <input type="text" name="label" maxlength="60" required placeholder="Période <?= count($periods) + 1 ?>" aria-label="Nom de la période">
                <input type="date" name="start_date" required aria-label="Début" value="<?= $periods ? e(add_days(end($periods)['end_date'], 1)) : e($year['start_date']) ?>">
                <input type="date" name="end_date" required aria-label="Fin">
                <button class="btn btn-primary btn-sm" type="submit"><?= icon('plus', 'icon icon-sm') ?> Ajouter</button>
            </form>
            <?php endif; ?>
        </section>

        <section class="card card-flush">
            <div class="card-head"><h2>Moyennes des élèves</h2></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Élève</th><th class="num">/20</th><th class="num">/100</th><th class="num">Éval.</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $a = $averages[$s['id']] ?? null; ?>
                    <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>"><td><a class="student-name" href="student.php?id=<?= (int)$s['id'] ?>"><?= e(student_name($s)) ?></a></td>
                        <td class="num"><span class="rate <?= grade_class($a['on20'] ?? null) ?>"><?= num($a['on20'] ?? null) ?></span></td>
                        <td class="num"><?= num($a['on100'] ?? null) ?></td><td class="num"><?= $a['count'] ?? 0 ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <p class="pad small muted">Moyenne = total des points obtenus ÷ total des points possibles (les absences ne comptent pas).</p>
        </section>
    </div>
</div>

<?php if ($periods): ?>
<section class="card card-flush mt">
    <div class="card-head"><h2>Moyennes par période · <?= e($class['name']) ?></h2></div>
    <div class="table-wrap"><table class="avg-table">
        <thead><tr><th>Élève</th>
            <?php foreach ($periods as $p): ?><th class="num"><?= e($p['label']) ?><small><?= e(date_short($p['start_date'])) ?> → <?= e(date_short($p['end_date'])) ?></small></th><?php endforeach; ?>
            <th class="num total-col">Année<small>/20</small></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): $sid = (int)$s['id']; $yr = $averages[$sid] ?? null;
            $has = $yr !== null; foreach ($periodAvg as $pa) { $has = $has || isset($pa[$sid]); }
            if (!$s['is_active'] && !$has) continue; ?>
            <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>"><td><a class="student-name" href="student.php?id=<?= $sid ?>"><?= e(student_name($s)) ?></a></td>
                <?php foreach ($periods as $p): $v = $periodAvg[$p['id']][$sid]['on20'] ?? null; ?>
                    <td class="num"><span class="rate <?= grade_class($v) ?>"><?= num($v) ?></span></td>
                <?php endforeach; ?>
                <td class="num total-col"><span class="rate <?= grade_class($yr['on20'] ?? null) ?>"><?= num($yr['on20'] ?? null) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Moyenne de la classe</td>
            <?php foreach ($periods as $p): ?><td class="num"><?= num(mean_of(array_column($periodAvg[$p['id']] ?? [], 'on20'))) ?></td><?php endforeach; ?>
            <td class="num total-col"><?= num(mean_of(array_column($averages, 'on20'))) ?></td></tr></tfoot>
    </table></div>
    <p class="pad small muted">Moyenne = total des points obtenus ÷ total des points possibles de la période, ramené sur 20 (absences ignorées). « Année » = toutes les évaluations de l'année, y compris celles hors période.</p>
</section>
<?php endif; ?>
<?php endif; ?>
<?php page_end();
