<?php
/** Années scolaires : changer d'année affichée, créer une nouvelle année, archiver. */
require __DIR__ . '/../app/bootstrap.php';
require_login();

if (is_post()) {
    $action = input('action');

    if ($action === 'switch') {
        if (fetch_one('SELECT id FROM school_years WHERE id = ?', [input_int('year_id')])) {
            $_SESSION['year_id'] = input_int('year_id');
        }
        // Retour à la page d'où l'on vient (uniquement une page de ce site)
        $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '');
        $page = basename((string)($ref['path'] ?? ''));
        // Les pages de détail (un cours, un élève…) appartiennent à une année précise : retour à l'accueil.
        $detailPages = ['lesson.php', 'evaluation.php', 'council.php', 'student.php', 'class.php'];
        $ok = preg_match('/^[a-z_]+\.php$/', $page) && is_file(__DIR__ . '/' . $page)
            && ($ref['host'] ?? '') . (isset($ref['port']) ? ':' . $ref['port'] : '') === ($_SERVER['HTTP_HOST'] ?? '')
            && !in_array($page, $detailPages, true);
        redirect($ok ? $page : 'index.php');
    }

    if ($action === 'create') {
        $label = input('label');
        $start = input('start_date');
        $end = input('end_date');
        if (!preg_match('/^\d{4}-\d{4}$/', $label) || !valid_date($start) || !valid_date($end) || $start >= $end) {
            flash('error', 'Indiquez un nom du type 2027-2028 et des dates valides.');
            redirect('years.php');
        }
        if (fetch_one('SELECT id FROM school_years WHERE label = ?', [$label])) {
            flash('error', 'Cette année existe déjà.');
            redirect('years.php');
        }
        db()->beginTransaction();
        q('INSERT INTO school_years (label, start_date, end_date) VALUES (?, ?, ?)', [$label, $start, $end]);
        $newId = last_id();

        // Congés FWB connus pour cette année (s'ils ne sont pas déjà encodés)
        $fwb = fwb_calendar()[$label] ?? null;
        foreach ($fwb['holidays'] ?? [] as [$hLabel, $from, $to]) {
            if (!fetch_one('SELECT id FROM holidays WHERE start_date = ? AND end_date = ?', [$from, $to])) {
                q('INSERT INTO holidays (label, start_date, end_date) VALUES (?, ?, ?)', [$hLabel, $from, $to]);
            }
        }

        // Reprise des classes d'une année précédente (optionnel)
        $copyFrom = input_int('copy_from');
        $copied = 0;
        if ($copyFrom) {
            foreach (fetch_all('SELECT * FROM classes WHERE school_year_id = ?', [$copyFrom]) as $c) {
                q('INSERT INTO classes (school_year_id, school_id, name, subject, color) VALUES (?, ?, ?, ?, ?)',
                    [$newId, $c['school_id'], $c['name'], $c['subject'], $c['color']]);
                $newClass = last_id();
                $copied++;
                if (input('copy_students')) {
                    q('INSERT INTO students (class_id, last_name, first_name, notes, created_at)
                       SELECT ?, last_name, first_name, notes, ? FROM students WHERE class_id = ? AND is_active = 1', [$newClass, now(), $c['id']]);
                }
                if (input('copy_schedule')) {
                    q('INSERT INTO schedule_slots (class_id, weekday, start_period, duration, room)
                       SELECT ?, weekday, start_period, duration, room FROM schedule_slots WHERE class_id = ?', [$newClass, $c['id']]);
                }
            }
        }
        db()->commit();
        $_SESSION['year_id'] = $newId;
        flash('success', "Année $label créée" . ($copied ? " avec $copied classe(s) reprise(s)" : '') . '.');
        redirect('years.php');
    }

    if ($action === 'toggle_archive') {
        q('UPDATE school_years SET is_archived = 1 - is_archived WHERE id = ?', [input_int('year_id')]);
        audit('year_archive_toggle', (string)input_int('year_id'));
        flash('success', "Statut d'archivage modifié.");
        redirect('years.php');
    }
    redirect('years.php');
}

$years = fetch_all(
    'SELECT y.*, (SELECT COUNT(*) FROM classes c WHERE c.school_year_id = y.id) AS classes,
            (SELECT COUNT(*) FROM lessons l JOIN classes c ON c.id = l.class_id WHERE c.school_year_id = y.id AND l.attendance_saved_at IS NOT NULL) AS lessons
     FROM school_years y ORDER BY y.start_date DESC');
$current = current_year();

// Proposition : la première année du calendrier FWB qui suit la dernière année créée
$lastStart = $years[0]['start_date'] ?? '0000-00-00';
$suggest = null;
foreach (fwb_calendar() as $label => $y) {
    if ($y['start'] > $lastStart) { $suggest = ['label' => $label] + $y; break; }
}
if (!$suggest) {
    $last = $years[0] ?? null;
    $startYear = $last ? (int)substr($last['label'], 5, 4) : (int)date('Y');
    $suggest = ['label' => $startYear . '-' . ($startYear + 1), 'start' => "$startYear-08-25", 'end' => ($startYear + 1) . '-07-03'];
}

page_start('Années & archives', 'years.php');
?>
<div class="page-head"><div><div class="eyebrow">Archivage</div><h1>Années scolaires</h1>
    <p>Chaque année garde ses classes, élèves, présences et évaluations. Une année archivée reste consultable mais n'est plus modifiable.</p></div></div>

<div class="grid grid-2">
    <section class="card card-flush">
        <div class="table-wrap"><table>
            <thead><tr><th>Année</th><th>Dates</th><th class="num">Classes</th><th class="num">Cours</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($years as $y): ?>
                <tr><td><b><?= e($y['label']) ?></b>
                        <?php if ($current && $current['id'] == $y['id']): ?><span class="chip">affichée</span><?php endif; ?>
                        <?php if ($y['is_archived']): ?><span class="chip chip-muted">archivée</span><?php endif; ?></td>
                    <td class="small"><?= e(date_short($y['start_date'])) ?>/<?= substr($y['start_date'], 0, 4) ?> → <?= e(date_short($y['end_date'])) ?>/<?= substr($y['end_date'], 0, 4) ?></td>
                    <td class="num"><?= (int)$y['classes'] ?></td><td class="num"><?= (int)$y['lessons'] ?></td>
                    <td class="num"><div class="actions">
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="switch"><input type="hidden" name="year_id" value="<?= (int)$y['id'] ?>">
                            <button class="btn btn-sm btn-ghost" type="submit">Afficher</button></form>
                        <form method="post" data-confirm="<?= $y['is_archived'] ? 'Désarchiver cette année ?' : 'Archiver cette année ? Elle passera en lecture seule.' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_archive"><input type="hidden" name="year_id" value="<?= (int)$y['id'] ?>">
                            <button class="btn btn-sm" type="submit"><?= $y['is_archived'] ? 'Désarchiver' : 'Archiver' ?></button></form>
                    </div></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>

    <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="card-head"><h2>Nouvelle année scolaire</h2></div>
        <div class="stack">
            <div class="form-grid">
                <div class="field"><label for="label">Année</label><input id="label" type="text" name="label" value="<?= e($suggest['label']) ?>" pattern="\d{4}-\d{4}" required></div>
                <div class="field"><label for="start_date">Rentrée</label><input id="start_date" type="date" name="start_date" value="<?= e($suggest['start']) ?>" required></div>
                <div class="field"><label for="end_date">Dernier jour</label><input id="end_date" type="date" name="end_date" value="<?= e($suggest['end']) ?>" required></div>
            </div>
            <p class="small muted">Dates proposées d'après le calendrier officiel de la Fédération Wallonie-Bruxelles, congés inclus.</p>
            <?php if ($years): ?>
            <div class="field"><label for="copy_from">Reprendre les classes de</label>
                <select id="copy_from" name="copy_from"><option value="0">— Ne rien reprendre —</option>
                    <?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>"><?= e($y['label']) ?></option><?php endforeach; ?>
                </select></div>
            <label class="check"><input type="checkbox" name="copy_students" value="1"> avec les élèves actifs</label>
            <label class="check"><input type="checkbox" name="copy_schedule" value="1" checked> avec l'horaire</label>
            <?php endif; ?>
            <div><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Créer l'année</button></div>
        </div>
    </form>
</div>
<?php page_end();
