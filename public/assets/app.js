/**
 * Journal de classe — petites interactions (aucune bibliothèque externe).
 * Chaque bloc est indépendant et activé par un attribut data-… dans le HTML.
 */
(function () {
    'use strict';
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // Menu mobile
    $$('[data-toggle-nav]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('nav-open')));
    $$('[data-close-nav]').forEach(b => b.addEventListener('click', () => document.body.classList.remove('nav-open')));

    // Fermer un message
    $$('[data-dismiss]').forEach(b => b.addEventListener('click', () => b.parentElement.remove()));

    // Envoi automatique d'un formulaire quand on change un champ (ex : choix d'année, photo)
    $$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => {
        if (el.type === 'file' && el.files.length) {
            const hint = document.querySelector('[data-upload-hint]');
            if (hint) hint.textContent = 'Envoi de la photo…';
        }
        if (el.form) { window.__saving = true; el.form.submit(); }
    }));

    // Confirmation avant une action (suppression…)
    $$('[data-confirm]').forEach(el => el.addEventListener('submit', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    }));

    // Imprimer
    $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));

    // Avertir si on quitte une page avec des modifications non enregistrées
    $$('form[data-track-changes]').forEach(form => {
        const bar = document.querySelector('.sticky-save');
        const hint = bar && bar.querySelector('.dirty-hint');
        let dirty = false;
        const mark = () => {
            if (dirty) return;
            dirty = true;
            if (bar) bar.classList.add('is-dirty');
            if (hint) hint.textContent = 'Modifications non enregistrées';
        };
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
        form.addEventListener('submit', () => { window.__saving = true; });
        window.addEventListener('beforeunload', e => {
            if (dirty && !window.__saving) { e.preventDefault(); e.returnValue = ''; }
        });
    });

    /* ---------- Présences ---------- */
    const grid = document.querySelector('[data-attendance]');
    if (grid) {
        const periods = JSON.parse(grid.dataset.periods || '[]');
        const follow = document.getElementById('follow-periods');

        // Quand on change le statut d'une période, les périodes suivantes suivent
        // (si la case "appliquer aux périodes suivantes" est cochée).
        // Une arrivée tardive devient "présent" pour les périodes suivantes.
        grid.addEventListener('change', e => {
            const input = e.target;
            if (!input.matches('input[type=radio][data-student]') || !follow || !follow.checked) return;
            const student = input.dataset.student;
            const from = parseInt(input.dataset.period, 10);
            const next = input.value === 'AT' ? 'P' : input.value;
            periods.filter(p => p > from).forEach(p => {
                const target = grid.querySelector(`input[data-student="${student}"][data-period="${p}"][value="${next}"]`);
                if (target) target.checked = true;
            });
            updateStats();
        });

        // Bouton "tous présents" d'une période
        $$('[data-all-present]', grid).forEach(btn => btn.addEventListener('click', () => {
            const p = btn.dataset.allPresent;
            $$(`input[data-period="${p}"][value="P"]`, grid).forEach(i => { i.checked = true; });
            grid.dispatchEvent(new Event('input', { bubbles: true }));
            updateStats();
        }));

        // Statistiques en direct (P et AT = présent)
        function updateStats() {
            periods.forEach(p => {
                const checked = $$(`input[data-period="${p}"]:checked`, grid);
                const present = checked.filter(i => i.value === 'P' || i.value === 'AT').length;
                const cell = grid.querySelector(`[data-stat="${p}"]`);
                if (!cell) return;
                const total = checked.length;
                cell.querySelector('b').textContent = `${present}/${total}`;
                const pctv = total ? Math.round(100 * present / total) : 0;
                cell.querySelector('small').textContent = total ? `${pctv} %` : '–';
                const bar = cell.querySelector('.stat-bar');
                bar.className = 'stat-bar w-' + Math.round(pctv / 10) * 10;
            });
            $$('[data-row-stat]', grid).forEach(cell => {
                const s = cell.dataset.rowStat;
                const checked = $$(`input[data-student="${s}"]:checked`, grid);
                const present = checked.filter(i => i.value === 'P' || i.value === 'AT').length;
                cell.textContent = `${present}/${checked.length}`;
            });
        }
        grid.addEventListener('change', updateStats);
        updateStats();
    }

    /* ---------- Évaluations : conversion en /20 et /100 en direct ---------- */
    const evalForm = document.querySelector('[data-evaluation]');
    if (evalForm) {
        const maxInput = evalForm.querySelector('[name=max_points]');
        const fmt = n => (Math.round(n * 10) / 10).toString().replace('.', ',');
        const refresh = () => {
            const max = parseFloat((maxInput.value || '0').replace(',', '.'));
            let sum = 0, count = 0;
            $$('[data-score]', evalForm).forEach(input => {
                const row = input.closest('tr');
                const absent = row.querySelector('[data-absent]');
                const v = parseFloat(input.value.replace(',', '.'));
                const ok = !isNaN(v) && max > 0 && !(absent && absent.checked);
                row.querySelector('[data-on20]').textContent = ok ? fmt(v / max * 20) : '–';
                row.querySelector('[data-on100]').textContent = ok ? fmt(v / max * 100) : '–';
                input.classList.toggle('is-over', ok && v > max);
                if (ok) { sum += v / max; count++; }
            });
            const avg20 = evalForm.querySelector('[data-avg20]');
            const avg100 = evalForm.querySelector('[data-avg100]');
            if (avg20) avg20.textContent = count ? fmt(sum / count * 20) : '–';
            if (avg100) avg100.textContent = count ? fmt(sum / count * 100) : '–';
        };
        evalForm.addEventListener('input', refresh);
        evalForm.addEventListener('change', refresh);
        refresh();
    }
})();
