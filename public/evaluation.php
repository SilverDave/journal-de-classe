<?php
/** Encodage d'une évaluation : points, absent, commentaire et photo de la copie par élève. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$ev = fetch_one('SELECT * FROM evaluations WHERE id = ?', [input_int('id')]);
if (!$ev) {
    flash('error', 'Évaluation introuvable.');
    redirect('evaluations.php');
}
$evId = (int)$ev['id'];
$class = find_class((int)$ev['class_id']);
$readonly = (int)fetch_value('SELECT is_archived FROM school_years WHERE id = ?', [$class['school_year_id']]) === 1;
$students = class_students((int)$class['id']);
$existing = [];
foreach (fetch_all('SELECT * FROM evaluation_scores WHERE evaluation_id = ?', [$evId]) as $r) {
    $existing[$r['student_id']] = $r;
}

if (is_post() && !$readonly) {
    if (input('action') === 'delete') {
        foreach ($existing as $r) delete_photo($r['photo']);
        q('DELETE FROM evaluations WHERE id = ?', [$evId]);
        flash('success', 'Évaluation supprimée.');
        redirect('evaluations.php?class=' . $class['id']);
    }

    $max = (float)str_replace(',', '.', (string)input('max_points'));
    if ($max > 0 && input('title') !== '' && valid_date(input('eval_date'))) {
        q('UPDATE evaluations SET title = ?, eval_date = ?, max_points = ? WHERE id = ?', [mb_substr(input('title'), 0, 200), input('eval_date'), $max, $evId]);
    }
    $errors = [];
    foreach ($students as $s) {
        $sid = (int)$s['id'];
        $raw = trim(str_replace(',', '.', (string)($_POST['score'][$sid] ?? '')));
        $score = is_numeric($raw) ? max(0, (float)$raw) : null;
        $absent = !empty($_POST['absent'][$sid]) ? 1 : 0;
        $comment = trim((string)($_POST['comment'][$sid] ?? '')) ?: null;
        $photo = $existing[$sid]['photo'] ?? null;

        if (!empty($_POST['remove_photo'][$sid])) {
            delete_photo($photo);
            $photo = null;
        }
        $file = isset($_FILES['photo']['name'][$sid]) ? [
            'name' => $_FILES['photo']['name'][$sid], 'type' => $_FILES['photo']['type'][$sid],
            'tmp_name' => $_FILES['photo']['tmp_name'][$sid], 'error' => $_FILES['photo']['error'][$sid], 'size' => $_FILES['photo']['size'][$sid],
        ] : null;
        try {
            if ($new = save_photo($file, 'eval')) {
                delete_photo($photo);
                $photo = $new;
            }
        } catch (RuntimeException $e) {
            $errors[] = student_name($s) . ' : ' . $e->getMessage();
        }

        if ($score === null && !$absent && !$comment && !$photo) {
            q('DELETE FROM evaluation_scores WHERE evaluation_id = ? AND student_id = ?', [$evId, $sid]);
            continue;
        }
        q('INSERT INTO evaluation_scores (evaluation_id, student_id, score, is_absent, comment, photo) VALUES (?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE score = VALUES(score), is_absent = VALUES(is_absent), comment = VALUES(comment), photo = VALUES(photo)',
            [$evId, $sid, $score, $absent, $comment, $photo]);
    }
    foreach ($errors as $err) flash('error', $err);
    if (!$errors) flash('success', 'Évaluation enregistrée ✓');
    redirect("evaluation.php?id=$evId");
}

$max = (float)$ev['max_points'];
$dis = $readonly ? ' disabled' : '';
page_start($ev['title'], 'evaluations.php');
?>
<form method="post" enctype="multipart/form-data" data-evaluation data-track-changes>
<?= csrf_field() ?>
<div class="page-head c-<?= (int)$class['color'] ?>">
    <div><div class="eyebrow"><a href="evaluations.php?class=<?= (int)$class['id'] ?>">← Évaluations · <?= e($class['name']) ?></a></div>
        <h1><?= e($ev['title']) ?></h1>
        <?php if ($ev['description']): ?><p><?= e($ev['description']) ?></p><?php endif; ?></div>
    <div class="actions">
        <div class="card kpi"><span>Moyenne de classe</span><strong><span data-avg20>–</span><small>/20</small></strong><span><span data-avg100>–</span> /100</span></div>
    </div>
</div>

<section class="card">
    <div class="form-grid">
        <div class="field"><label for="title">Titre</label><input id="title" type="text" name="title" value="<?= e($ev['title']) ?>" required<?= $dis ?>></div>
        <div class="field"><label for="eval_date">Date</label><input id="eval_date" type="date" name="eval_date" value="<?= e($ev['eval_date']) ?>" required<?= $dis ?>></div>
        <div class="field"><label for="max_points">Points sur</label><input id="max_points" type="number" name="max_points" step="0.25" min="0.25" value="<?= e((float)$max) ?>" required<?= $dis ?>></div>
    </div>
</section>

<section class="card card-flush mt">
    <div class="card-head"><h2>Résultats</h2><span class="small muted" data-upload-hint>📷 Sur tablette ou smartphone, le bouton photo ouvre directement l'appareil photo.</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Élève</th><th class="num">Points</th><th class="center">Absent</th><th class="num">/20</th><th class="num">/100</th><th>Commentaire</th><th>Copie</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): $sid = (int)$s['id']; $r = $existing[$sid] ?? []; if (!$s['is_active'] && !$r) continue; ?>
            <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>">
                <td><a class="student-name" href="student.php?id=<?= $sid ?>"><?= e(student_name($s)) ?></a></td>
                <td class="num"><input class="score-input" type="text" inputmode="decimal" name="score[<?= $sid ?>]" value="<?= isset($r['score']) && $r['score'] !== null ? e(rtrim(rtrim(number_format((float)$r['score'], 2, ',', ''), '0'), ',')) : '' ?>" data-score aria-label="Points de <?= e(student_name($s)) ?>"<?= $dis ?>></td>
                <td class="center"><input type="checkbox" class="check" name="absent[<?= $sid ?>]" value="1" data-absent <?= !empty($r['is_absent']) ? 'checked' : '' ?> aria-label="Absent"<?= $dis ?>></td>
                <td class="num score-out" data-on20>–</td>
                <td class="num score-out" data-on100>–</td>
                <td><input type="text" name="comment[<?= $sid ?>]" value="<?= e($r['comment'] ?? '') ?>" placeholder="Remarque…"<?= $dis ?>></td>
                <td>
                    <div class="row">
                        <?php if (!empty($r['photo'])): ?>
                            <a href="photo.php?f=<?= e($r['photo']) ?>" target="_blank"><img class="thumb" src="photo.php?f=<?= e($r['photo']) ?>" alt="Copie de <?= e(student_name($s)) ?>"></a>
                            <?php if (!$readonly): ?><label class="check small"><input type="checkbox" name="remove_photo[<?= $sid ?>]" value="1"> retirer</label><?php endif; ?>
                        <?php endif; ?>
                        <?php if (!$readonly): ?>
                        <label class="btn btn-sm photo-btn"><?= icon('camera', 'icon icon-sm') ?> <?= empty($r['photo']) ? 'Photo' : 'Changer' ?>
                            <input type="file" name="photo[<?= $sid ?>]" accept="image/*" capture="environment" data-autosubmit></label>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<?php if (!$readonly): ?>
<div class="sticky-save">
    <span class="dirty-hint">Les points sont enregistrés aussi quand vous ajoutez une photo.</span>
    <button class="btn btn-accent" type="submit">Enregistrer</button>
</div>
<?php endif; ?>
</form>

<?php if (!$readonly): ?>
<form method="post" class="mt" data-confirm="Supprimer cette évaluation, ses points et ses photos ?">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button class="link-btn danger small" type="submit">Supprimer l'évaluation</button>
</form>
<?php endif; ?>
<?php page_end();
