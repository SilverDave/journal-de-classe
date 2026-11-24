<?php
/** Fiche élève : présences et appréciations par mois, commentaires, évaluations, conseils. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$student = fetch_one('SELECT * FROM students WHERE id = ?', [input_int('id')]);
if (!$student) {
    flash('error', 'Élève introuvable.');
    redirect('schools.php');
}
$sid = (int)$student['id'];
$class = find_class((int)$student['class_id']);
$yearRow = fetch_one('SELECT * FROM school_years WHERE id = ?', [$class['school_year_id']]);
$readonly = (int)$yearRow['is_archived'] === 1;

if (is_post() && !$readonly) {
    if (input('action') === 'edit' && input('last_name') !== '') {
        q('UPDATE students SET last_name = ?, first_name = ?, notes = ? WHERE id = ?',
            [mb_substr(input('last_name'), 0, 100), mb_substr(input('first_name'), 0, 100), input('notes') ?: null, $sid]);
        flash('success', 'Fiche enregistrée.');
    }
    if (input('action') === 'toggle_active') {
        q('UPDATE students SET is_active = 1 - is_active WHERE id = ?', [$sid]);
        flash('success', $student['is_active'] ? 'Élève désactivé : il n\'apparaît plus dans les présences.' : 'Élève réactivé.');
    }
    if (input('action') === 'delete' && input('confirm') === 'SUPPRIMER') {
        q('DELETE FROM students WHERE id = ?', [$sid]);
        flash('success', 'Élève supprimé définitivement.');
        redirect('class.php?id=' . $class['id']);
    }
    redirect("student.php?id=$sid");
}

$from = $yearRow['start_date'];
$to = $yearRow['end_date'];
$total = attendance_stats((int)$class['id'], $from, $to, $sid)[$sid] ?? ['total' => 0, 'present' => 0, 'P' => 0, 'A' => 0, 'AT' => 0, 'R' => 0];
$apprTotal = appreciation_stats((int)$class['id'], $from, $to, $sid)[$sid] ?? [];

// Détail par mois
$months = [];
$in = "'" . implode("','", PRESENT_STATUSES) . "'";
foreach (fetch_all(
    "SELECT DATE_FORMAT(l.lesson_date, '%Y-%m') AS ym, COUNT(*) AS total, SUM(a.status IN ($in)) AS present,
            SUM(a.status = 'A') AS A, SUM(a.status = 'AT') AS AT, SUM(a.status = 'R') AS R
     FROM attendance a JOIN lessons l ON l.id = a.lesson_id
     WHERE a.student_id = ? AND l.cancelled = 0 GROUP BY ym ORDER BY ym", [$sid]) as $r) {
    $months[$r['ym']]['att'] = $r;
}
foreach (fetch_all(
    "SELECT DATE_FORMAT(l.lesson_date, '%Y-%m') AS ym, n.appreciation, COUNT(*) AS n
     FROM lesson_notes n JOIN lessons l ON l.id = n.lesson_id
     WHERE n.student_id = ? AND n.appreciation IS NOT NULL AND l.cancelled = 0 GROUP BY ym, n.appreciation", [$sid]) as $r) {
    $months[$r['ym']]['appr'][$r['appreciation']] = (int)$r['n'];
}
ksort($months);

$notes = fetch_all(
    'SELECT n.*, l.lesson_date, l.start_period, l.duration, l.title, l.id AS lesson_id
     FROM lesson_notes n JOIN lessons l ON l.id = n.lesson_id
     WHERE n.student_id = ? ORDER BY l.lesson_date DESC, l.start_period DESC LIMIT 40', [$sid]);
$absences = fetch_all(
    "SELECT l.id, l.lesson_date, a.period, a.status FROM attendance a JOIN lessons l ON l.id = a.lesson_id
     WHERE a.student_id = ? AND a.status <> 'P' AND l.cancelled = 0 ORDER BY l.lesson_date DESC, a.period LIMIT 40", [$sid]);
$evals = fetch_all(
    'SELECT e.*, sc.score, sc.is_absent, sc.comment, sc.photo FROM evaluations e
     LEFT JOIN evaluation_scores sc ON sc.evaluation_id = e.id AND sc.student_id = ?
     WHERE e.class_id = ? ORDER BY e.eval_date DESC', [$sid, $class['id']]);
$avg = evaluation_averages((int)$class['id'])[$sid] ?? null;
$councils = fetch_all(
    'SELECT c.*, ce.appreciation, ce.comment, ce.decision FROM councils c
     JOIN council_entries ce ON ce.council_id = c.id AND ce.student_id = ? ORDER BY c.council_date DESC', [$sid]);

page_start(student_name($student), 'schools.php');
?>
<div class="page-head c-<?= (int)$class['color'] ?>">
    <div><div class="eyebrow"><a href="class.php?id=<?= (int)$class['id'] ?>">← <?= e($class['name']) ?> · <?= e($class['school_name']) ?></a></div>
        <h1><?= e(student_name($student)) ?> <?php if (!$student['is_active']): ?><span class="chip chip-muted">désactivé</span><?php endif; ?></h1>
        <?php if ($student['notes']): ?><p><?= nl2br(e($student['notes'])) ?></p><?php endif; ?></div>
    <?php if (!$readonly): ?>
    <form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_active">
        <button class="btn" type="submit"><?= $student['is_active'] ? 'Désactiver l\'élève' : 'Réactiver l\'élève' ?></button></form>
    <?php endif; ?>
</div>

<div class="grid grid-3">
    <div class="card kpi"><span>Présence sur l'année</span><strong><?= pct($total['present'], $total['total']) ?></strong><span><?= $total['present'] ?>/<?= $total['total'] ?> périodes</span></div>
    <div class="card kpi"><span>Absences · retards · renvois</span><strong><?= $total['A'] ?> · <?= $total['AT'] ?> · <?= $total['R'] ?></strong><span>périodes</span></div>
    <div class="card kpi"><span>Moyenne des évaluations</span><strong><?= num($avg['on20'] ?? null) ?><small>/20</small></strong><span><?= num($avg['on100'] ?? null) ?> /100 · <?= $avg['count'] ?? 0 ?> évaluation(s)</span></div>
    <div class="card kpi"><span>Appréciations (tranches)</span><strong><?= appreciation_counts($apprTotal) ?></strong><span>vert · orange · rouge · noir</span></div>
</div>

<section class="card card-flush mt">
    <div class="card-head"><h2>Par mois</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Mois</th><th class="center">Présence</th><th class="num">Périodes</th><th class="num">A</th><th class="num">AT</th><th class="num">R</th><th>Appréciations</th></tr></thead>
        <tbody>
        <?php foreach ($months as $ym => $m): $a = $m['att'] ?? null; ?>
            <tr><td><?= e(ucfirst(month_fr($ym))) ?></td>
                <td class="center"><?= rate_badge($a['present'] ?? null, $a['total'] ?? null) ?></td>
                <td class="num"><?= (int)($a['present'] ?? 0) ?>/<?= (int)($a['total'] ?? 0) ?></td>
                <td class="num"><?= (int)($a['A'] ?? 0) ?></td><td class="num"><?= (int)($a['AT'] ?? 0) ?></td><td class="num"><?= (int)($a['R'] ?? 0) ?></td>
                <td><?= appreciation_counts($m['appr'] ?? []) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$months): ?><tr><td colspan="7" class="muted">Pas encore de données.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<div class="grid grid-2 mt">
    <section class="card card-flush">
        <div class="card-head"><h2>Appréciations & commentaires</h2></div>
        <div class="table-wrap"><table>
            <tbody>
            <?php foreach ($notes as $n): ?>
                <tr><td><a href="lesson.php?id=<?= (int)$n['lesson_id'] ?>"><?= e(date_short($n['lesson_date'])) ?></a><br><small class="muted">P<?= (int)$n['start_period'] ?></small></td>
                    <td><?php if ($n['appreciation']): ?><span class="appr-mark appr-<?= e($n['appreciation']) ?>" title="<?= e(APPRECIATIONS[$n['appreciation']]) ?>"></span><?php endif; ?></td>
                    <td><?= $n['appreciation_text'] ? '<b>' . e($n['appreciation_text']) . '</b><br>' : '' ?><?= e($n['comment']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$notes): ?><tr><td class="muted">Aucune appréciation pour l'instant.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <section class="card card-flush">
        <div class="card-head"><h2>Absences, retards, renvois</h2></div>
        <div class="table-wrap"><table>
            <tbody>
            <?php foreach ($absences as $a): ?>
                <tr><td><a href="lesson.php?id=<?= (int)$a['id'] ?>"><?= e(date_fr($a['lesson_date'])) ?></a></td><td>P<?= (int)$a['period'] ?></td>
                    <td><span class="status-pill status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span> <?= e(STATUSES[$a['status']]) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$absences): ?><tr><td class="muted">Aucune absence. 👏</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
</div>

<section class="card card-flush mt">
    <div class="card-head"><h2>Évaluations</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Évaluation</th><th class="num">Points</th><th class="num">/20</th><th class="num">/100</th><th>Commentaire</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($evals as $ev): $score = $ev['is_absent'] || $ev['score'] === null ? null : (float)$ev['score']; ?>
            <tr><td><?= e(date_short($ev['eval_date'])) ?></td><td><a href="evaluation.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></td>
                <td class="num"><?= $ev['is_absent'] ? '<span class="chip chip-muted">absent</span>' : num($score) . ' / ' . num($ev['max_points']) ?></td>
                <td class="num"><span class="rate <?= grade_class(score_on($score, (float)$ev['max_points'], 20)) ?>"><?= num(score_on($score, (float)$ev['max_points'], 20)) ?></span></td>
                <td class="num"><?= num(score_on($score, (float)$ev['max_points'], 100)) ?></td>
                <td><?= e($ev['comment']) ?></td>
                <td><?php if ($ev['photo']): ?><a href="photo.php?f=<?= e($ev['photo']) ?>" target="_blank"><img class="thumb" src="photo.php?f=<?= e($ev['photo']) ?>" alt="Copie"></a><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$evals): ?><tr><td colspan="7" class="muted">Aucune évaluation.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</section>

<?php if ($councils): ?>
<section class="card mt">
    <h2>Conseils de classe</h2>
    <div class="stack">
    <?php foreach ($councils as $c): ?>
        <div class="row"><?php if ($c['appreciation']): ?><span class="appr-mark appr-<?= e($c['appreciation']) ?>"></span><?php endif; ?>
            <b><a href="council.php?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a></b> <span class="muted small"><?= e(date_short($c['council_date'])) ?></span>
            <span><?= e($c['decision']) ?></span> <span class="muted"><?= e($c['comment']) ?></span></div>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!$readonly): ?>
<details class="card mt" id="edit">
    <summary>Modifier la fiche de l'élève</summary>
    <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="edit">
        <div class="form-grid">
            <div class="field"><label>Nom</label><input type="text" name="last_name" value="<?= e($student['last_name']) ?>" required></div>
            <div class="field"><label>Prénom</label><input type="text" name="first_name" value="<?= e($student['first_name']) ?>"></div>
        </div>
        <div class="field"><label>Remarques (PIA, aménagements, contacts…)</label><textarea name="notes" rows="3"><?= e($student['notes']) ?></textarea></div>
        <div><button class="btn btn-primary btn-sm" type="submit">Enregistrer</button></div>
    </form>
    <hr>
    <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <p class="small">Préférez <b>désactiver</b> l'élève : ses données restent disponibles. La suppression efface tout (présences, notes…). Tapez SUPPRIMER pour confirmer.</p>
        <div class="row"><input type="text" name="confirm" autocomplete="off" placeholder="SUPPRIMER"><button class="btn btn-sm btn-danger" type="submit">Supprimer définitivement</button></div>
    </form>
</details>
<?php endif; ?>
<?php page_end();
