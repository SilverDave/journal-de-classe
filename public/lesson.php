<?php
/**
 * UNE tranche de cours : intitulé, lien vers le document, présences par période,
 * appréciation (vert/orange/rouge/noir) + implication + commentaire par élève.
 */
require __DIR__ . '/../app/bootstrap.php';
require_login();

// 1) Ouverture depuis l'horaire : on crée la tranche si elle n'existe pas encore.
if (!input_int('id') && input_int('class')) {
    $class = find_class(input_int('class'));
    $date = input('date');
    $start = input_int('start');
    $duration = input_int('duration', 1);
    if (!$class || !valid_date($date) || !isset(PERIODS[$start]) || $duration < 1 || $duration > MAX_LESSON_PERIODS || !isset(PERIODS[$start + $duration - 1])) {
        flash('error', 'Cours introuvable.');
        redirect('calendar.php');
    }
    require_writable('calendar.php');
    redirect('lesson.php?id=' . ensure_lesson((int)$class['id'], $date, $start, $duration));
}

$lesson = fetch_one('SELECT * FROM lessons WHERE id = ?', [input_int('id')]);
if (!$lesson) {
    flash('error', 'Cours introuvable.');
    redirect('calendar.php');
}
$class = find_class((int)$lesson['class_id']);
$readonly = (int)fetch_value('SELECT is_archived FROM school_years WHERE id = ?', [$class['school_year_id']]) === 1;
$lessonId = (int)$lesson['id'];
$start = (int)$lesson['start_period'];
$periods = range($start, $start + (int)$lesson['duration'] - 1);
$students = class_students((int)$class['id']);
$active = array_values(array_filter($students, fn($s) => $s['is_active']));
$inactive = array_values(array_filter($students, fn($s) => !$s['is_active']));
$back = 'attendance.php?date=' . $lesson['lesson_date'];

// 2) Enregistrement
if (is_post()) {
    if ($readonly) {
        flash('error', 'Année archivée : modification impossible.');
        redirect("lesson.php?id=$lessonId");
    }
    $action = input('action', 'save');

    if ($action === 'delete') {
        q('DELETE FROM lessons WHERE id = ?', [$lessonId]);
        flash('success', 'Cours supprimé du journal.');
        redirect($back);
    }
    if ($action === 'toggle_cancel') {
        q('UPDATE lessons SET cancelled = 1 - cancelled WHERE id = ?', [$lessonId]);
        flash('success', $lesson['cancelled'] ? 'Cours rétabli.' : 'Cours marqué comme annulé (exclu des statistiques).');
        redirect("lesson.php?id=$lessonId");
    }

    // Durée modifiable (ex : cours prolongé exceptionnellement)
    $duration = input_int('duration', (int)$lesson['duration']);
    if ($duration < 1 || $duration > MAX_LESSON_PERIODS || !isset(PERIODS[$start + $duration - 1])) {
        $duration = (int)$lesson['duration'];
    }
    $periods = range($start, $start + $duration - 1);

    db()->beginTransaction();
    q('UPDATE lessons SET title = ?, document_url = ?, notes = ?, duration = ?, attendance_saved_at = ? WHERE id = ?', [
        mb_substr(input('title'), 0, 200) ?: null,
        clean_url((string)input('document_url')),
        input('notes') ?: null,
        $duration,
        now(),
        $lessonId,
    ]);
    q('DELETE FROM attendance WHERE lesson_id = ? AND period > ?', [$lessonId, $start + $duration - 1]);

    $statuses = $_POST['st'] ?? [];
    $apprs = $_POST['ap'] ?? [];
    $apprTexts = $_POST['ap_text'] ?? [];
    $comments = $_POST['comment'] ?? [];
    foreach ($active as $s) {
        $sid = (int)$s['id'];
        foreach ($periods as $p) {
            $status = $statuses[$sid][$p] ?? null;
            if (is_string($status) && isset(STATUSES[$status])) {
                q('REPLACE INTO attendance (lesson_id, student_id, period, status) VALUES (?, ?, ?, ?)', [$lessonId, $sid, $p, $status]);
            }
        }
        $ap = is_string($apprs[$sid] ?? null) && isset(APPRECIATIONS[$apprs[$sid]]) ? $apprs[$sid] : null;
        $apText = trim((string)($apprTexts[$sid] ?? ''));
        $comment = trim((string)($comments[$sid] ?? ''));
        if ($ap || $apText !== '' || $comment !== '') {
            q('INSERT INTO lesson_notes (lesson_id, student_id, appreciation, appreciation_text, comment) VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE appreciation = VALUES(appreciation), appreciation_text = VALUES(appreciation_text), comment = VALUES(comment)',
                [$lessonId, $sid, $ap, $apText ?: null, $comment ?: null]);
        } else {
            q('DELETE FROM lesson_notes WHERE lesson_id = ? AND student_id = ?', [$lessonId, $sid]);
        }
    }
    db()->commit();
    flash('success', 'Cours enregistré ✓');
    redirect("lesson.php?id=$lessonId");
}

// 3) Données existantes
$attendance = [];
foreach (fetch_all('SELECT * FROM attendance WHERE lesson_id = ?', [$lessonId]) as $a) {
    $attendance[$a['student_id']][$a['period']] = $a['status'];
}
$notes = [];
foreach (fetch_all('SELECT * FROM lesson_notes WHERE lesson_id = ?', [$lessonId]) as $n) {
    $notes[$n['student_id']] = $n;
}
$neverSaved = !$lesson['attendance_saved_at'];
$dis = $readonly ? ' disabled' : '';

page_start($class['name'] . ' · ' . date_short($lesson['lesson_date']), 'attendance.php');
?>
<div class="page-head c-<?= (int)$class['color'] ?>">
    <div>
        <div class="eyebrow"><a href="<?= e($back) ?>">← <?= e(ucfirst(date_fr($lesson['lesson_date']))) ?></a></div>
        <h1><span class="swatch"></span> <?= e($class['name']) ?><?= $class['subject'] ? ' · ' . e($class['subject']) : '' ?></h1>
        <p><?= e($class['school_name']) ?> — <?= e(tranche_label($start, (int)$lesson['duration'])) ?></p>
    </div>
    <?php if (!$readonly): ?>
    <div class="actions no-print">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_cancel">
            <button class="btn btn-sm" type="submit"><?= $lesson['cancelled'] ? 'Rétablir le cours' : 'Cours annulé / pas cours' ?></button></form>
        <form method="post" data-confirm="Supprimer ce cours et toutes ses présences ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <button class="btn btn-sm btn-danger" type="submit">Supprimer</button></form>
    </div>
    <?php endif; ?>
</div>

<?php if ($lesson['cancelled']): ?>
    <div class="flash flash-info">Ce cours est marqué comme annulé : il n'est pas compté dans les statistiques.</div>
<?php endif; ?>

<form method="post" data-track-changes>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="card">
        <div class="form-grid">
            <div class="field"><label for="title">Intitulé du cours</label>
                <input id="title" type="text" name="title" value="<?= e($lesson['title']) ?>" placeholder="Ex : Les fractions — exercices" maxlength="200"<?= $dis ?>></div>
            <div class="field"><label for="document_url">Lien vers le document</label>
                <input id="document_url" type="text" inputmode="url" name="document_url" value="<?= e($lesson['document_url']) ?>" placeholder="https://drive.google.com/…"<?= $dis ?>>
            </div>
            <div class="field"><label>Durée</label>
                <div class="duration-picker">
                    <?php for ($d = 1; $d <= MAX_LESSON_PERIODS; $d++): if (!isset(PERIODS[$start + $d - 1])) break; ?>
                        <label><input type="radio" name="duration" value="<?= $d ?>" <?= $d == $lesson['duration'] ? 'checked' : '' ?><?= $dis ?>><span><?= $d ?></span></label>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php if ($lesson['document_url']): ?>
            <p class="mt"><a href="<?= e($lesson['document_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm"><?= icon('link', 'icon icon-sm') ?> Ouvrir le document</a></p>
        <?php endif; ?>
        <details class="mt"<?= $lesson['notes'] ? ' open' : '' ?>>
            <summary>Notes du cours (matière vue, devoirs…)</summary>
            <textarea name="notes" rows="3"<?= $dis ?>><?= e($lesson['notes']) ?></textarea>
        </details>
    </section>

    <section class="card card-flush mt">
        <div class="card-head">
            <h2>Présences & implication</h2>
            <div class="legend">
                <?php foreach (STATUSES as $k => $label): ?><span><b class="status-pill status-<?= $k ?>"><?= $k ?></b><?= e($label) ?></span><?php endforeach; ?>
            </div>
        </div>
        <div class="row pad">
            <?php if ($neverSaved): ?><span class="chip chip-warn">Pas encore enregistré · tout le monde est « présent » par défaut</span><?php endif; ?>
            <?php if (count($periods) > 1 && !$readonly): ?>
                <label class="check small"><input type="checkbox" id="follow-periods" checked> Recopier le statut sur les périodes suivantes</label>
            <?php endif; ?>
        </div>

        <?php if (!$active): ?>
            <div class="pad"><?= empty_state('Aucun élève actif', 'Ajoutez des élèves à cette classe.', 'class.php?id=' . $class['id'], 'Gérer les élèves') ?></div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="attendance-table" data-attendance data-periods="<?= e(json_encode($periods)) ?>">
                <thead>
                <tr>
                    <th>Élève</th>
                    <?php foreach ($periods as $p): ?>
                        <th class="period-head">P<?= $p ?><small><?= e(period_time($p)) ?></small>
                            <?php if (!$readonly): ?><div class="period-tools"><button type="button" class="btn btn-sm btn-ghost" data-all-present="<?= $p ?>">Tous P</button></div><?php endif; ?>
                        </th>
                    <?php endforeach; ?>
                    <th class="center">Prés.</th>
                    <th>Appréciation</th>
                    <th>Implication</th>
                    <th>Commentaire</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($active as $s): $sid = (int)$s['id']; $n = $notes[$sid] ?? []; ?>
                    <tr>
                        <td class="student-cell"><a class="student-name" href="student.php?id=<?= $sid ?>"><?= e(student_name($s)) ?></a></td>
                        <?php foreach ($periods as $p): $current = $attendance[$sid][$p] ?? 'P'; ?>
                            <td class="center" data-label="Période <?= $p ?> · <?= e(period_time($p)) ?>">
                                <div class="seg" role="radiogroup" aria-label="<?= e(student_name($s)) ?> période <?= $p ?>">
                                    <?php foreach (STATUSES as $k => $label): ?>
                                        <label title="<?= e($label) ?>"><input type="radio" name="st[<?= $sid ?>][<?= $p ?>]" value="<?= $k ?>" data-student="<?= $sid ?>" data-period="<?= $p ?>" <?= $current === $k ? 'checked' : '' ?><?= $dis ?>><span><?= $k ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        <?php endforeach; ?>
                        <td class="stat-cell" data-row-stat="<?= $sid ?>"></td>
                        <td data-label="Appréciation"><?= appreciation_picker("ap[$sid]", $n['appreciation'] ?? null, $readonly) ?></td>
                        <td data-label="Implication"><textarea name="ap_text[<?= $sid ?>]" placeholder="Implication…" aria-label="Implication"<?= $dis ?>><?= e($n['appreciation_text'] ?? '') ?></textarea></td>
                        <td data-label="Commentaire"><textarea name="comment[<?= $sid ?>]" placeholder="Commentaire…" aria-label="Commentaire"<?= $dis ?>><?= e($n['comment'] ?? '') ?></textarea></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr>
                    <td>Présents (P + AT)</td>
                    <?php foreach ($periods as $p): ?>
                        <td class="stat-cell" data-stat="<?= $p ?>" data-label="P<?= $p ?>"><b></b> <small></small><div class="stat-bar w-0"><i></i></div></td>
                    <?php endforeach; ?>
                    <td colspan="4"></td>
                </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($inactive): ?>
            <div class="pad small muted">
                Élèves désactivés (non repris) : <?= e(implode(', ', array_map('student_name', $inactive))) ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!$readonly): ?>
    <div class="sticky-save">
        <span class="dirty-hint"><?= $lesson['attendance_saved_at'] ? 'Enregistré le ' . e(date('d/m à H:i', strtotime($lesson['attendance_saved_at']))) : 'Pas encore enregistré' ?></span>
        <button class="btn btn-accent" type="submit">Enregistrer le cours</button>
    </div>
    <?php endif; ?>
</form>
<?php page_end();
