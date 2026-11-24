<?php
/** Liste des conseils de classe d'une classe. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');
$classes = year_classes((int)$year['id']);
$class = selected_class($classes);

if (is_post() && $class) {
    require_writable('councils.php?class=' . $class['id']);
    $date = input('council_date');
    $from = input('period_start');
    $to = input('period_end');
    if (input('title') === '' || !valid_date($date) || !valid_date($from) || !valid_date($to) || $from > $to) {
        flash('error', 'Vérifiez le titre et les dates (début de période avant la fin).');
        redirect('councils.php?class=' . $class['id']);
    }
    q('INSERT INTO councils (class_id, council_date, title, period_start, period_end, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$class['id'], $date, mb_substr(input('title'), 0, 200), $from, $to, now()]);
    redirect('council.php?id=' . last_id());
}

$councils = $class ? fetch_all(
    'SELECT c.*, COUNT(ce.student_id) AS filled FROM councils c LEFT JOIN council_entries ce ON ce.council_id = c.id
     WHERE c.class_id = ? GROUP BY c.id ORDER BY c.council_date DESC', [$class['id']]) : [];
$lastEnd = $councils ? add_days($councils[0]['period_end'], 1) : $year['start_date'];

page_start('Conseils de classe', 'councils.php');
?>
<div class="page-head"><div><div class="eyebrow">Conseils de classe · <?= e($year['label']) ?></div><h1>Conseils de classe</h1>
    <p>Pour chaque élève : présences, implication et moyenne de la période sous les yeux, puis votre avis.</p></div></div>

<?php if (!$classes): ?>
    <?= empty_state('Aucune classe', 'Créez une classe pour préparer un conseil.', 'schools.php', 'Écoles & élèves') ?>
<?php else: ?>
<?= class_tabs($classes, $class, 'councils.php') ?>
<div class="grid grid-2">
    <section class="card card-flush">
        <div class="card-head"><h2><?= e($class['name']) ?></h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Conseil</th><th>Période analysée</th><th class="num">Avis</th></tr></thead>
            <tbody>
            <?php foreach ($councils as $c): ?>
                <tr><td><?= e(date_short($c['council_date'])) ?></td><td><a href="council.php?id=<?= (int)$c['id'] ?>"><b><?= e($c['title']) ?></b></a></td>
                    <td class="small"><?= e(date_short($c['period_start'])) ?> → <?= e(date_short($c['period_end'])) ?></td><td class="num"><?= (int)$c['filled'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$councils): ?><tr><td colspan="4" class="muted">Aucun conseil pour cette classe.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <?php if (!year_is_archived()): ?>
    <form method="post" class="card">
        <?= csrf_field() ?>
        <div class="card-head"><h2>Préparer un conseil</h2></div>
        <div class="stack">
            <div class="field"><label for="title">Titre</label><input id="title" type="text" name="title" required maxlength="200" placeholder="Conseil de classe de décembre"></div>
            <div class="form-grid">
                <div class="field"><label for="council_date">Date du conseil</label><input id="council_date" type="date" name="council_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="field"><label for="period_start">Période analysée : du</label><input id="period_start" type="date" name="period_start" value="<?= e($lastEnd) ?>" required></div>
                <div class="field"><label for="period_end">au</label><input id="period_end" type="date" name="period_end" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Créer</button></div>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php page_end();
