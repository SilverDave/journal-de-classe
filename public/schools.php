<?php
/** Écoles et classes de l'année affichée (École > Classe > Élève). */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');

if (is_post()) {
    require_writable('schools.php');
    $action = input('action');

    if ($action === 'add_school' && input('name') !== '') {
        q('INSERT INTO schools (name, city, created_at) VALUES (?, ?, ?)', [mb_substr(input('name'), 0, 150), mb_substr(input('city'), 0, 100) ?: null, now()]);
        flash('success', 'École ajoutée.');
    }
    if ($action === 'edit_school' && input('name') !== '') {
        q('UPDATE schools SET name = ?, city = ? WHERE id = ?', [mb_substr(input('name'), 0, 150), mb_substr(input('city'), 0, 100) ?: null, input_int('school_id')]);
        flash('success', 'École modifiée.');
    }
    if ($action === 'delete_school') {
        if ((int)fetch_value('SELECT COUNT(*) FROM classes WHERE school_id = ?', [input_int('school_id')]) > 0) {
            flash('error', 'Cette école a encore des classes (cette année ou dans les archives) : impossible de la supprimer.');
        } else {
            q('DELETE FROM schools WHERE id = ?', [input_int('school_id')]);
            flash('success', 'École supprimée.');
        }
    }
    if ($action === 'add_class' && input('name') !== '' && fetch_one('SELECT id FROM schools WHERE id = ?', [input_int('school_id')])) {
        $color = input_int('color', 1);
        q('INSERT INTO classes (school_year_id, school_id, name, subject, color) VALUES (?, ?, ?, ?, ?)', [
            $year['id'], input_int('school_id'), mb_substr(input('name'), 0, 60), mb_substr(input('subject'), 0, 100) ?: null,
            isset(CLASS_COLORS[$color]) ? $color : 1,
        ]);
        flash('success', 'Classe créée. Ajoutez maintenant ses élèves.');
        redirect('class.php?id=' . last_id());
    }
    redirect('schools.php');
}

$schools = schools();
$classesBySchool = [];
foreach (year_classes((int)$year['id']) as $c) {
    $classesBySchool[$c['school_id']][] = $c;
}
$readonly = year_is_archived();
$nextColor = (count(array_merge([], ...array_values($classesBySchool))) % count(CLASS_COLORS)) + 1;

page_start('Écoles & élèves', 'schools.php');
?>
<div class="page-head">
    <div><div class="eyebrow">Organisation · <?= e($year['label']) ?></div><h1>Écoles & classes</h1><p>École › Classe › Élèves. Cliquez sur une classe pour gérer ses élèves.</p></div>
</div>

<?php if (!$schools): ?>
    <div class="mb"><?= empty_state('Aucune école', 'Ajoutez la ou les écoles dans lesquelles vous enseignez.') ?></div>
<?php endif; ?>

<div class="grid grid-2">
    <?php foreach ($schools as $s): $list = $classesBySchool[$s['id']] ?? []; ?>
    <section class="card school-card">
        <div class="card-head">
            <div><h2><?= e($s['name']) ?></h2><?php if ($s['city']): ?><span class="small muted"><?= e($s['city']) ?></span><?php endif; ?></div>
            <span class="chip chip-muted"><?= count($list) ?> classe<?= count($list) > 1 ? 's' : '' ?></span>
        </div>
        <div class="class-list">
            <?php foreach ($list as $c): ?>
                <a class="class-row c-<?= (int)$c['color'] ?>" href="class.php?id=<?= (int)$c['id'] ?>">
                    <span class="swatch"></span>
                    <span class="grow"><?= e($c['name']) ?> <small class="muted"><?= e($c['subject']) ?></small></span>
                    <span class="small muted"><?= (int)$c['active_count'] ?> élèves</span>
                    <?= icon('right', 'icon icon-sm') ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$list): ?><p class="muted small">Aucune classe cette année.</p><?php endif; ?>
        </div>
        <?php if (!$readonly): ?>
        <details class="mt">
            <summary><?= icon('plus', 'icon icon-sm') ?> Nouvelle classe</summary>
            <form method="post" class="stack">
                <?= csrf_field() ?><input type="hidden" name="action" value="add_class"><input type="hidden" name="school_id" value="<?= (int)$s['id'] ?>">
                <div class="form-grid">
                    <div class="field"><label>Nom de la classe</label><input type="text" name="name" placeholder="3A, 5TQ…" maxlength="60" required></div>
                    <div class="field"><label>Cours / matière</label><input type="text" name="subject" placeholder="Mathématiques" maxlength="100"></div>
                </div>
                <div class="field"><label>Couleur</label>
                    <div class="color-picker">
                        <?php foreach (CLASS_COLORS as $k => $label): ?>
                            <label class="c-<?= $k ?>" title="<?= e($label) ?>"><input type="radio" name="color" value="<?= $k ?>" <?= $k === $nextColor ? 'checked' : '' ?>><span class="swatch"></span></label>
                        <?php endforeach; ?>
                    </div></div>
                <div><button class="btn btn-primary btn-sm" type="submit">Créer la classe</button></div>
            </form>
        </details>
        <details class="mt">
            <summary class="small">Modifier l'école</summary>
            <form method="post" class="form-grid">
                <?= csrf_field() ?><input type="hidden" name="action" value="edit_school"><input type="hidden" name="school_id" value="<?= (int)$s['id'] ?>">
                <div class="field"><label>Nom</label><input type="text" name="name" value="<?= e($s['name']) ?>" required></div>
                <div class="field"><label>Localité</label><input type="text" name="city" value="<?= e($s['city']) ?>"></div>
                <div><button class="btn btn-sm" type="submit">Enregistrer</button></div>
            </form>
            <form method="post" class="mt" data-confirm="Supprimer cette école ?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_school"><input type="hidden" name="school_id" value="<?= (int)$s['id'] ?>">
                <button class="link-btn danger small" type="submit">Supprimer l'école</button>
            </form>
        </details>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php if (!$readonly): ?>
    <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_school">
        <div class="card-head"><h2>Ajouter une école</h2></div>
        <div class="stack">
            <div class="field"><label for="school-name">Nom de l'école</label><input id="school-name" type="text" name="name" maxlength="150" required placeholder="Athénée royal…"></div>
            <div class="field"><label for="school-city">Localité <small>(facultatif)</small></label><input id="school-city" type="text" name="city" maxlength="100"></div>
            <div><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Ajouter l'école</button></div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php page_end();
