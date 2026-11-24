<?php
/** Mise en page commune (menu, en-tête) et petits composants HTML réutilisables. */

/** Menu principal : fichier => [libellé, icône] */
const NAV = [
    'index.php' => ["Aujourd'hui", 'home'],
    'calendar.php' => ['Journal de classe', 'calendar'],
    'attendance.php' => ['Présences', 'check'],
    'schedule.php' => ['Horaire', 'clock'],
    'schools.php' => ['Écoles & élèves', 'users'],
    'evaluations.php' => ['Évaluations', 'file'],
    'councils.php' => ['Conseils de classe', 'chat'],
    'stats.php' => ['Statistiques', 'chart'],
];
const NAV_SECONDARY = [
    'years.php' => ['Années & archives', 'archive'],
    'holidays.php' => ['Congés scolaires', 'sun'],
    'account.php' => ['Mon compte', 'user'],
];

function page_start(string $title, string $active = ''): void
{
    $user = current_user();
    $year = current_year();
    $years = all_years();
    $flashes = take_flashes();
    require ROOT . '/app/views/header.php';
}

function page_end(): void
{
    require ROOT . '/app/views/footer.php';
}

/** Page simple centrée (connexion, installation). */
function auth_page_start(string $title): void
{
    $flashes = take_flashes();
    require ROOT . '/app/views/auth_header.php';
}

function auth_page_end(): void
{
    echo '</div></main><script src="assets/app.js"></script></body></html>';
}

/** Icônes SVG (style "trait"), utilisées dans le menu et les boutons. */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'check' => '<path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l2 2 4-4"/>',
        'chat' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'archive' => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'left' => '<path d="m15 18-6-6 6-6"/>',
        'right' => '<path d="m9 18 6-6-6-6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'printer' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'alert' => '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Pastille de pourcentage colorée. */
function rate_badge(?float $present, ?float $total): string
{
    return '<span class="rate ' . rate_class($present, $total) . '">' . pct($present, $total) . '</span>';
}

/** Petits compteurs vert/orange/rouge/noir. */
function appreciation_counts(array $counts): string
{
    $html = '<span class="appr-counts">';
    foreach (APPRECIATIONS as $key => $label) {
        $n = $counts[$key] ?? 0;
        $html .= '<span class="appr-count appr-' . $key . ($n ? '' : ' is-zero') . '" title="' . e($label) . '">' . $n . '</span>';
    }
    return $html . '</span>';
}

/** Sélecteur de couleur d'appréciation (boutons radio ronds). */
function appreciation_picker(string $name, ?string $value, bool $disabled = false): string
{
    $html = '<div class="appr-picker" role="radiogroup">';
    $html .= '<label class="appr-dot appr-none" title="Aucune"><input type="radio" name="' . e($name) . '" value=""' . (!$value ? ' checked' : '') . ($disabled ? ' disabled' : '') . '><span>–</span></label>';
    foreach (APPRECIATIONS as $key => $label) {
        $html .= '<label class="appr-dot appr-' . $key . '" title="' . e($label) . '"><input type="radio" name="' . e($name) . '" value="' . $key . '"'
            . ($value === $key ? ' checked' : '') . ($disabled ? ' disabled' : '') . '><span class="sr-only">' . e($label) . '</span></label>';
    }
    return $html . '</div>';
}

/** Onglets de sélection de classe (pages Évaluations, Conseils, Statistiques). */
function class_tabs(array $classes, ?array $selected, string $page, array $extra = []): string
{
    $html = '<nav class="class-tabs">';
    foreach ($classes as $c) {
        $url = $page . '?' . http_build_query(['class' => $c['id']] + $extra);
        $html .= '<a class="class-tab c-' . (int)$c['color'] . ($selected && $selected['id'] == $c['id'] ? ' is-active' : '') . '" href="' . e($url) . '">'
            . '<span class="swatch"></span>' . e($c['name']) . ' <small>' . e($c['school_name']) . '</small></a>';
    }
    return $html . '</nav>';
}

function empty_state(string $title, string $text, ?string $url = null, ?string $button = null): string
{
    return '<div class="empty"><h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
        . ($url ? '<a class="btn btn-primary" href="' . e($url) . '">' . e($button) . '</a>' : '') . '</div>';
}
