<?php
/** Congés scolaires (calendrier FWB pré-rempli) + vos journées sans cours. */
require __DIR__ . '/../app/bootstrap.php';
require_login();
$year = current_year();
if (!$year) redirect('years.php');

if (is_post()) {
    if (input('action') === 'add') {
        $from = input('start_date');
        $to = input('end_date') ?: $from;
        if (input('label') === '' || !valid_date($from) || !valid_date($to) || $from > $to) {
            flash('error', 'Indiquez un nom et des dates valides.');
        } else {
            q('INSERT INTO holidays (label, start_date, end_date) VALUES (?, ?, ?)', [mb_substr(input('label'), 0, 120), $from, $to]);
            flash('success', 'Congé ajouté : les cours de ces jours disparaissent du journal.');
        }
    }
    if (input('action') === 'delete') {
        q('DELETE FROM holidays WHERE id = ?', [input_int('holiday_id')]);
        flash('success', 'Congé supprimé.');
    }
    redirect('holidays.php');
}

$holidays = fetch_all('SELECT * FROM holidays WHERE end_date >= ? AND start_date <= ? ORDER BY start_date', [$year['start_date'], $year['end_date']]);

page_start('Congés scolaires', 'holidays.php');
?>
<div class="page-head"><div><div class="eyebrow">Calendrier FWB · <?= e($year['label']) ?></div><h1>Congés scolaires</h1>
    <p>Rentrée le <?= e(date_fr($year['start_date'], false)) ?> — fin le <?= e(date_fr($year['end_date'], false)) ?>. Aucun cours n'est proposé pendant ces jours.</p></div></div>

<div class="grid grid-2">
    <section class="card card-flush">
        <div class="table-wrap"><table>
            <thead><tr><th>Congé</th><th>Du</th><th>Au</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($holidays as $h): ?>
                <tr><td><b><?= e($h['label']) ?></b></td><td><?= e(date_fr($h['start_date'])) ?></td><td><?= $h['end_date'] !== $h['start_date'] ? e(date_fr($h['end_date'])) : '' ?></td>
                    <td class="num"><form method="post" data-confirm="Supprimer ce congé ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="holiday_id" value="<?= (int)$h['id'] ?>">
                        <button class="btn btn-sm btn-ghost" type="submit" aria-label="Supprimer"><?= icon('x', 'icon icon-sm') ?></button></form></td></tr>
            <?php endforeach; ?>
            <?php if (!$holidays): ?><tr><td colspan="4" class="muted">Aucun congé encodé pour cette année.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>
    <form method="post" class="card">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="card-head"><h2>Ajouter un jour sans cours</h2></div>
        <div class="stack">
            <div class="field"><label for="label">Nom</label><input id="label" type="text" name="label" required maxlength="120" placeholder="Journée pédagogique, voyage scolaire…"></div>
            <div class="form-grid">
                <div class="field"><label for="start_date">Du</label><input id="start_date" type="date" name="start_date" required></div>
                <div class="field"><label for="end_date">Au <small>(facultatif)</small></label><input id="end_date" type="date" name="end_date"></div>
            </div>
            <div><button class="btn btn-primary" type="submit"><?= icon('plus') ?> Ajouter</button></div>
        </div>
    </form>
</div>
<p class="small muted mt">Source : <a href="https://www.enseignement.be/calendrier-scolaire" target="_blank" rel="noopener noreferrer">enseignement.be — calendrier scolaire</a>. Les années suivantes sont dans app/lib/fwb_calendar.php.</p>
<?php page_end();
