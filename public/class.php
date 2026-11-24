<?php
/** Une classe : ses élèves (ajout, ajout en liste, désactivation, modification). */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$class = find_class(input_int('id'));
if (!$class) {
    flash('error', 'Classe introuvable.');
    redirect('schools.php');
}
$classId = (int)$class['id'];
$readonly = (int)fetch_value('SELECT is_archived FROM school_years WHERE id = ?', [$class['school_year_id']]) === 1;
$self = "class.php?id=$classId";

/** "DUPONT Marie" ou "Dupont, Marie" -> [nom, prénom] */
function split_name(string $line): array
{
    $line = trim(preg_replace('/\s+/', ' ', $line));
    if (str_contains($line, ',')) {
        [$last, $first] = array_map('trim', explode(',', $line, 2));
        return [$last, $first];
    }
    if (str_contains($line, "\t")) {
        [$last, $first] = array_map('trim', explode("\t", $line, 2));
        return [$last, $first];
    }
    // Les mots en MAJUSCULES sont le nom de famille, le reste le prénom
    $words = explode(' ', $line);
    $last = [];
    while ($words && mb_strlen($words[0]) > 1 && mb_strtoupper($words[0]) === $words[0]) {
        $last[] = array_shift($words);
    }
    if (!$last) {
        $last[] = array_shift($words);
    }
    return [implode(' ', $last), implode(' ', $words)];
}

if (is_post()) {
    if ($readonly) {
        flash('error', 'Année archivée : modification impossible.');
        redirect($self);
    }
    $action = input('action');
    $owned = fn(int $sid) => (bool)fetch_value('SELECT 1 FROM students WHERE id = ? AND class_id = ?', [$sid, $classId]);

    if ($action === 'add_students') {
        $count = 0;
        foreach (preg_split('/\R/', (string)input('names')) as $line) {
            if (trim($line) === '') continue;
            [$last, $first] = split_name($line);
            q('INSERT INTO students (class_id, last_name, first_name, created_at) VALUES (?, ?, ?, ?)', [$classId, mb_substr($last, 0, 100), mb_substr($first, 0, 100), now()]);
            $count++;
        }
        flash('success', "$count élève(s) ajouté(s).");
    }
    if ($action === 'toggle_active' && $owned(input_int('student_id'))) {
        q('UPDATE students SET is_active = 1 - is_active WHERE id = ?', [input_int('student_id')]);
        flash('success', 'Statut de l\'élève modifié.');
    }
    if ($action === 'edit_student' && $owned(input_int('student_id')) && input('last_name') !== '') {
        q('UPDATE students SET last_name = ?, first_name = ?, notes = ? WHERE id = ?',
            [mb_substr(input('last_name'), 0, 100), mb_substr(input('first_name'), 0, 100), input('notes') ?: null, input_int('student_id')]);
        flash('success', 'Élève modifié.');
    }
    if ($action === 'delete_student' && $owned(input_int('student_id'))) {
        q('DELETE FROM students WHERE id = ?', [input_int('student_id')]);
        flash('success', 'Élève supprimé définitivement.');
    }
    if ($action === 'edit_class' && input('name') !== '') {
        $color = input_int('color', (int)$class['color']);
        q('UPDATE classes SET name = ?, subject = ?, color = ? WHERE id = ?',
            [mb_substr(input('name'), 0, 60), mb_substr(input('subject'), 0, 100) ?: null, isset(CLASS_COLORS[$color]) ? $color : 1, $classId]);
        flash('success', 'Classe modifiée.');
    }
    if ($action === 'delete_class' && input('confirm_name') === $class['name']) {
        q('DELETE FROM classes WHERE id = ?', [$classId]);
        flash('success', 'Classe supprimée.');
        redirect('schools.php');
    } elseif ($action === 'delete_class') {
        flash('error', 'Pour supprimer, recopiez exactement le nom de la classe.');
    }
    redirect($self);
}

$students = class_students($classId);
$yearRow = fetch_one('SELECT * FROM school_years WHERE id = ?', [$class['school_year_id']]);
$att = attendance_stats($classId, $yearRow['start_date'], $yearRow['end_date']);
$avg = evaluation_averages($classId);
$activeCount = count(array_filter($students, fn($s) => $s['is_active']));

page_start($class['name'], 'schools.php');
?>
<div class="page-head c-<?= (int)$class['color'] ?>">
    <div><div class="eyebrow"><a href="schools.php">← <?= e($class['school_name']) ?></a></div>
        <h1><span class="swatch"></span> <?= e($class['name']) ?></h1>
        <p><?= e($class['subject'] ?: 'Sans matière') ?> · <?= $activeCount ?> élève<?= $activeCount > 1 ? 's' : '' ?> actif<?= $activeCount > 1 ? 's' : '' ?></p></div>
    <div class="actions">
        <a class="btn" href="stats.php?class=<?= $classId ?>"><?= icon('chart') ?> Statistiques</a>
        <a class="btn" href="evaluations.php?class=<?= $classId ?>"><?= icon('file') ?> Évaluations</a>
    </div>
</div>

<div class="grid grid-2">
<section class="card card-flush">
    <div class="card-head"><h2>Élèves</h2></div>
    <?php if (!$students): ?>
        <div class="pad"><p class="muted">Aucun élève. Collez votre liste à droite.</p></div>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Élève</th><th class="center">Présence</th><th class="center">Moy. /20</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): $sid = (int)$s['id']; $a = $att[$sid] ?? null; ?>
            <tr class="<?= $s['is_active'] ? '' : 'is-inactive' ?>">
                <td><a class="student-name" href="student.php?id=<?= $sid ?>"><?= e(student_name($s)) ?></a>
                    <?php if (!$s['is_active']): ?><span class="chip chip-muted">désactivé</span><?php endif; ?></td>
                <td class="center"><?= rate_badge($a['present'] ?? null, $a['total'] ?? null) ?></td>
                <td class="center"><span class="rate <?= grade_class($avg[$sid]['on20'] ?? null) ?>"><?= num($avg[$sid]['on20'] ?? null) ?></span></td>
                <td class="num">
                    <?php if (!$readonly): ?>
                    <form method="post" class="actions">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="student_id" value="<?= $sid ?>">
                        <button class="btn btn-sm btn-ghost" type="submit"><?= $s['is_active'] ? 'Désactiver' : 'Réactiver' ?></button>
                        <a class="btn btn-sm btn-ghost" href="student.php?id=<?= $sid ?>#edit" aria-label="Modifier"><?= icon('edit', 'icon icon-sm') ?></a>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<?php if (!$readonly): ?>
<div class="stack">
    <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_students">
        <div class="card-head"><h2>Ajouter des élèves</h2></div>
        <div class="field">
            <label for="names">Un élève par ligne</label>
            <textarea id="names" name="names" rows="8" placeholder="DUPONT Marie&#10;MARTIN Lucas&#10;Janssens, Emma" required></textarea>
            <small>Format « NOM Prénom » ou « Nom, Prénom ». Vous pouvez coller une colonne depuis Excel.</small>
        </div>
        <button class="btn btn-primary mt" type="submit"><?= icon('plus') ?> Ajouter</button>
    </form>

    <details class="card">
        <summary>Modifier la classe</summary>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="edit_class">
            <div class="form-grid">
                <div class="field"><label>Nom</label><input type="text" name="name" value="<?= e($class['name']) ?>" required></div>
                <div class="field"><label>Matière</label><input type="text" name="subject" value="<?= e($class['subject']) ?>"></div>
            </div>
            <div class="color-picker">
                <?php foreach (CLASS_COLORS as $k => $label): ?>
                    <label class="c-<?= $k ?>" title="<?= e($label) ?>"><input type="radio" name="color" value="<?= $k ?>" <?= $k == $class['color'] ? 'checked' : '' ?>><span class="swatch"></span></label>
                <?php endforeach; ?>
            </div>
            <div><button class="btn btn-sm" type="submit">Enregistrer</button></div>
        </form>
        <hr>
        <form method="post" class="stack">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_class">
            <p class="small"><b>Supprimer la classe</b> efface aussi ses élèves, présences, évaluations et conseils. Pour confirmer, recopiez « <?= e($class['name']) ?> ».</p>
            <div class="row"><input type="text" name="confirm_name" autocomplete="off"><button class="btn btn-sm btn-danger" type="submit">Supprimer</button></div>
        </form>
    </details>
</div>
<?php endif; ?>
</div>
<?php page_end();
