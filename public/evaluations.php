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
    $max = (float)str_replace(',', '.', (string)input('max_points'));
    $date = input('eval_date');
    if (input('title') === '' || $max <= 0 || !valid_date($date)) {
        flash('error', 'Indiquez un titre, une date et un total de points supérieur à 0.');
        redirect('evaluations.php?class=' . $class['id']);
    }
    q('INSERT INTO evaluations (class_id, eval_date, title, max_points, description, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$class['id'], $date, mb_substr(input('title'), 0, 200), $max, input('description') ?: null, now()]);
    redirect('evaluation.php?id=' . last_id());
}

$evals = $class ? fetch_all(
    'SELECT e.*, COUNT(sc.score) AS graded, AVG(CASE WHEN sc.is_absent = 0 THEN sc.score END) AS avg_score
     FROM evaluations e LEFT JOIN evaluation_scores sc ON sc.evaluation_id = e.id
     WHERE e.class_id = ? GROUP BY e.id ORDER BY e.eval_date DESC', [$class['id']]) : [];
$averages = $class ? evaluation_averages((int)$class['id']) : [];
$students = $class ? class_students((int)$class['id']) : [];

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
            <?php foreach ($evals as $ev): $a20 = $ev['avg_score'] !== null ? score_on((float)$ev['avg_score'], (float)$ev['max_points'], 20) : null; ?>
                <tr><td><?= e(date_short($ev['eval_date'])) ?></td><td><a href="evaluation.php?id=<?= (int)$ev['id'] ?>"><b><?= e($ev['title']) ?></b></a></td>
                    <td class="num"><?= num($ev['max_points']) ?></td>
                    <td class="num"><span class="rate <?= grade_class($a20) ?>"><?= num($a20) ?></span></td>
                    <td class="num"><?= (int)$ev['graded'] ?></td></tr>
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
<?php endif; ?>
<?php page_end();
