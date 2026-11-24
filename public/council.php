<?php
/** Un conseil de classe : une fiche par élève avec ses statistiques de la période et votre avis. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$council = fetch_one('SELECT * FROM councils WHERE id = ?', [input_int('id')]);
if (!$council) {
    flash('error', 'Conseil introuvable.');
    redirect('councils.php');
}
$cid = (int)$council['id'];
$class = find_class((int)$council['class_id']);
$readonly = (int)fetch_value('SELECT is_archived FROM school_years WHERE id = ?', [$class['school_year_id']]) === 1;
$students = class_students((int)$class['id']);
$entries = [];
foreach (fetch_all('SELECT * FROM council_entries WHERE council_id = ?', [$cid]) as $r) {
    $entries[$r['student_id']] = $r;
}

if (is_post() && !$readonly) {
    if (input('action') === 'delete') {
        foreach ($entries as $r) delete_photo($r['photo']);
        q('DELETE FROM councils WHERE id = ?', [$cid]);
        flash('success', 'Conseil supprimé.');
        redirect('councils.php?class=' . $class['id']);
    }
    if (input('title') !== '' && valid_date(input('council_date')) && valid_date(input('period_start')) && valid_date(input('period_end'))) {
        q('UPDATE councils SET title = ?, council_date = ?, period_start = ?, period_end = ? WHERE id = ?',
            [mb_substr(input('title'), 0, 200), input('council_date'), input('period_start'), input('period_end'), $cid]);
    }
    $errors = [];
    foreach ($students as $s) {
        $sid = (int)$s['id'];
        $ap = $_POST['ap'][$sid] ?? '';
        $ap = is_string($ap) && isset(APPRECIATIONS[$ap]) ? $ap : null;
        $comment = trim((string)($_POST['comment'][$sid] ?? '')) ?: null;
        $decision = mb_substr(trim((string)($_POST['decision'][$sid] ?? '')), 0, 255) ?: null;
        $photo = $entries[$sid]['photo'] ?? null;
        if (!empty($_POST['remove_photo'][$sid])) {
            delete_photo($photo);
            $photo = null;
        }
        $file = isset($_FILES['photo']['name'][$sid]) ? [
            'name' => $_FILES['photo']['name'][$sid], 'type' => $_FILES['photo']['type'][$sid],
            'tmp_name' => $_FILES['photo']['tmp_name'][$sid], 'error' => $_FILES['photo']['error'][$sid], 'size' => $_FILES['photo']['size'][$sid],
        ] : null;
        try {
            if ($new = save_photo($file, 'council')) {
                delete_photo($photo);
                $photo = $new;
            }
        } catch (RuntimeException $e) {
            $errors[] = student_name($s) . ' : ' . $e->getMessage();
        }
        if (!$ap && !$comment && !$decision && !$photo) {
            q('DELETE FROM council_entries WHERE council_id = ? AND student_id = ?', [$cid, $sid]);
            continue;
        }
        q('INSERT INTO council_entries (council_id, student_id, appreciation, comment, decision, photo) VALUES (?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE appreciation = VALUES(appreciation), comment = VALUES(comment), decision = VALUES(decision), photo = VALUES(photo)',
            [$cid, $sid, $ap, $comment, $decision, $photo]);
    }
    foreach ($errors as $err) flash('error', $err);
    if (!$errors) flash('success', 'Conseil enregistré ✓');
    redirect("council.php?id=$cid");
}

$from = $council['period_start'];
$to = $council['period_end'];
$att = attendance_stats((int)$class['id'], $from, $to);
$appr = appreciation_stats((int)$class['id'], $from, $to);
$avg = evaluation_averages((int)$class['id'], $from, $to);
$dis = $readonly ? ' disabled' : '';

page_start($council['title'], 'councils.php');
?>
<form method="post" enctype="multipart/form-data" data-track-changes>
<?= csrf_field() ?>
<div class="page-head c-<?= (int)$class['color'] ?>">
    <div><div class="eyebrow no-print"><a href="councils.php?class=<?= (int)$class['id'] ?>">← Conseils · <?= e($class['name']) ?></a></div>
        <h1><?= e($council['title']) ?></h1>
        <p><?= e($class['name']) ?> · <?= e($class['school_name']) ?> — période du <?= e(date_fr($from, false)) ?> au <?= e(date_fr($to, false)) ?></p></div>
    <div class="actions no-print"><button class="btn" type="button" data-print><?= icon('printer') ?> Imprimer</button></div>
</div>

<details class="card no-print">
    <summary>Modifier le titre et les dates</summary>
    <div class="form-grid">
        <div class="field"><label>Titre</label><input type="text" name="title" value="<?= e($council['title']) ?>" required<?= $dis ?>></div>
        <div class="field"><label>Date du conseil</label><input type="date" name="council_date" value="<?= e($council['council_date']) ?>"<?= $dis ?>></div>
        <div class="field"><label>Du</label><input type="date" name="period_start" value="<?= e($from) ?>"<?= $dis ?>></div>
        <div class="field"><label>Au</label><input type="date" name="period_end" value="<?= e($to) ?>"<?= $dis ?>></div>
    </div>
</details>

<div class="council-grid mt">
<?php foreach ($students as $s): $sid = (int)$s['id']; $en = $entries[$sid] ?? []; $a = $att[$sid] ?? null; if (!$s['is_active'] && !$en) continue; ?>
    <section class="card council-card<?= $s['is_active'] ? '' : ' is-inactive' ?>">
        <div class="mini-stats">
            <h3><a class="student-name" href="student.php?id=<?= $sid ?>"><?= e(student_name($s)) ?></a></h3>
            <div><span>Présence</span><?= rate_badge($a['present'] ?? null, $a['total'] ?? null) ?></div>
            <div><span>Abs. · Ret. · Renv.</span><b><?= (int)($a['A'] ?? 0) ?> · <?= (int)($a['AT'] ?? 0) ?> · <?= (int)($a['R'] ?? 0) ?></b></div>
            <div><span>Moyenne</span><span class="rate <?= grade_class($avg[$sid]['on20'] ?? null) ?>"><?= num($avg[$sid]['on20'] ?? null) ?>/20</span></div>
            <div><span>Implication</span><?= appreciation_counts($appr[$sid] ?? []) ?></div>
        </div>
        <div class="stack">
            <div class="row between">
                <?= appreciation_picker("ap[$sid]", $en['appreciation'] ?? null, $readonly) ?>
                <input type="text" name="decision[<?= $sid ?>]" value="<?= e($en['decision'] ?? '') ?>" placeholder="Décision / conseil (ex : remédiation)" maxlength="255" aria-label="Décision"<?= $dis ?>>
            </div>
            <textarea name="comment[<?= $sid ?>]" rows="3" placeholder="Avis pour le conseil de classe…" aria-label="Avis"<?= $dis ?>><?= e($en['comment'] ?? '') ?></textarea>
            <div class="row no-print">
                <?php if (!empty($en['photo'])): ?>
                    <a href="photo.php?f=<?= e($en['photo']) ?>" target="_blank"><img class="thumb" src="photo.php?f=<?= e($en['photo']) ?>" alt="Document"></a>
                    <?php if (!$readonly): ?><label class="check small"><input type="checkbox" name="remove_photo[<?= $sid ?>]" value="1"> retirer</label><?php endif; ?>
                <?php endif; ?>
                <?php if (!$readonly): ?>
                <label class="btn btn-sm photo-btn"><?= icon('camera', 'icon icon-sm') ?> Photo d'un document
                    <input type="file" name="photo[<?= $sid ?>]" accept="image/*" capture="environment" data-autosubmit></label>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endforeach; ?>
</div>

<?php if (!$readonly): ?>
<div class="sticky-save">
    <span class="dirty-hint">Pensez à enregistrer avant de quitter la page.</span>
    <button class="btn btn-accent" type="submit">Enregistrer le conseil</button>
</div>
<?php endif; ?>
</form>
<?php if (!$readonly): ?>
<form method="post" class="mt no-print" data-confirm="Supprimer ce conseil et tous les avis ?">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button class="link-btn danger small" type="submit">Supprimer ce conseil</button>
</form>
<?php endif; ?>
<?php page_end();
