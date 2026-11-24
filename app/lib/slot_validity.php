<?php
/**
 * Dates de validité d'un cours de l'horaire : début, fin, et rythme (chaque mois / un mois sur deux).
 * Stockées dans une table à part (schedule_slot_validity) : l'horaire existant n'est pas modifié.
 * Un créneau sans ligne = valable toute l'année.
 */

/** Table prête ? La crée au besoin ; en cas d'échec le site continue de marcher sans cette fonction. */
function slot_validity_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        fetch_value('SELECT 1 FROM schedule_slot_validity LIMIT 1');
        return $ready = true;
    } catch (PDOException $e) {
        try {
            db()->exec(file_get_contents(ROOT . '/database/migrations/002_schedule_validity.sql'));
            fetch_value('SELECT 1 FROM schedule_slot_validity LIMIT 1');
            return $ready = true;
        } catch (Throwable $e2) {
            error_log('schedule_slot_validity indisponible : ' . $e2->getMessage());
            return $ready = false;
        }
    }
}

/** Le créneau est-il actif à cette date ? $v = ['valid_from'=>, 'valid_to'=>, 'month_step'=>] (clés facultatives). */
function slot_active_on(array $v, string $date): bool
{
    $from = $v['valid_from'] ?? null;
    $to = $v['valid_to'] ?? null;
    $step = max(1, (int)($v['month_step'] ?? 1));
    if (($from && $date < $from) || ($to && $date > $to)) {
        return false;
    }
    if ($step > 1) {
        if (!$from) {
            return false;
        }
        $diff = ((int)substr($date, 0, 4) - (int)substr($from, 0, 4)) * 12 + (int)substr($date, 5, 2) - (int)substr($from, 5, 2);
        return $diff % $step === 0;
    }
    return true;
}

/** « du 01/10 au 31/01 · un mois sur deux » (vide si valable toute l'année). */
function slot_validity_label(array $v): string
{
    $from = $v['valid_from'] ?? null;
    $to = $v['valid_to'] ?? null;
    $parts = [];
    if ($from && $to) {
        $parts[] = 'du ' . date_short($from) . ' au ' . date_short($to);
    } elseif ($from) {
        $parts[] = 'à partir du ' . date_short($from);
    } elseif ($to) {
        $parts[] = 'jusqu\'au ' . date_short($to);
    }
    if ((int)($v['month_step'] ?? 1) > 1) {
        $parts[] = 'un mois sur deux';
    }
    return implode(' · ', $parts);
}

/** Deux créneaux peuvent-ils tomber le même jour ? (sert à détecter un vrai chevauchement d'horaire) */
function slot_ranges_conflict(array $a, array $b): bool
{
    $froms = array_filter([$a['valid_from'] ?? null, $b['valid_from'] ?? null]);
    $tos = array_filter([$a['valid_to'] ?? null, $b['valid_to'] ?? null]);
    $lo = $froms ? max($froms) : null;
    $hi = $tos ? min($tos) : null;
    if ($lo && $hi && $lo > $hi) {
        return false;
    }
    if (max(1, (int)($a['month_step'] ?? 1)) === 1 && max(1, (int)($b['month_step'] ?? 1)) === 1) {
        return true;
    }
    $probe = $lo ?? date('Y-m-01');
    for ($i = 0; $i < 36; $i++) {
        if ($hi && $probe > $hi) {
            return false;
        }
        if (slot_active_on($a, $probe) && slot_active_on($b, $probe)) {
            return true;
        }
        $probe = date('Y-m-01', strtotime(substr($probe, 0, 7) . '-01 +1 month'));
    }
    return false;
}
