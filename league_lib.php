<?php
/**
 * league_lib.php — Koç-içi haftalık liderlik ligi
 * Plan: docs/plan-v1-secili-moduller.md §D3 (S9)
 *
 * KVKK/mahremiyet ilkeleri (kullanıcıların çoğu reşit değil):
 *  - Varsayılan KAPALI (opt-in); öğrenci profilinden değil, lig kartından
 *    kendi isteğiyle katılır ve istediği an ayrılır.
 *  - Listede gerçek ad değil RUMUZ görünür.
 *  - Yalnız AYNI KOÇUN opt-in öğrencileri birbirini görür; platform geneli
 *    lig yok.
 *  - Alt sıradakiler sıralama numarası yerine yüzdelik dilim görür; ilk 3
 *    madalya alır. Küme düşme/yükselme yok.
 *  - Opt-out eden öğrenci "kendine karşı" kartıyla döngüde kalır
 *    (bu hafta vs geçen hafta XP).
 *
 * Plandan sapma: haftalık tablolar (league_weeks/league_members) v1'de yok —
 * sıralama xp_events'ten canlı hesaplanır (koç başına tek SUM sorgusu).
 * Sezon arşivi gerekirse v2'de tablo eklenir.
 */

require_once __DIR__ . '/app_settings_lib.php';
require_once __DIR__ . '/gamify_lib.php';

function league_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('league_optin', $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN league_optin TINYINT(1) NOT NULL DEFAULT 0");
        if (!in_array('nickname', $cols))     $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(30) NULL DEFAULT NULL");
    } catch (Throwable $e) {}
    $done = true;
}

/** Lige katıl (rumuz zorunlu) / ayrıl. */
function league_set_optin(PDO $pdo, int $studentId, bool $optin, string $nickname = ''): array
{
    league_ensure_schema($pdo);
    if ($optin) {
        $nickname = mb_substr(trim($nickname), 0, 30);
        if (mb_strlen($nickname) < 2) return ['ok' => false, 'error' => 'Rumuz en az 2 karakter olmalı.'];
        $pdo->prepare("UPDATE users SET league_optin = 1, nickname = ? WHERE id = ?")
            ->execute([$nickname, $studentId]);
    } else {
        $pdo->prepare("UPDATE users SET league_optin = 0 WHERE id = ?")->execute([$studentId]);
    }
    return ['ok' => true];
}

/** Öğrencinin koçu (ilk koç). */
function league_coach_of(PDO $pdo, int $studentId): ?int
{
    $st = $pdo->prepare("SELECT MIN(teacher_id) FROM coaching_relationships WHERE student_id = ?");
    $st->execute([$studentId]);
    $t = $st->fetchColumn();
    return $t ? (int)$t : null;
}

/**
 * Haftalık sıralama: koçun opt-in öğrencileri, bu haftanın XP toplamıyla.
 * Dönüş: her satır ['id','nickname','xp','rank'] — XP azalan sırada.
 */
function league_standings(PDO $pdo, int $teacherId, ?string $weekStart = null): array
{
    league_ensure_schema($pdo);
    $weekStart = $weekStart ?: date('Y-m-d', strtotime('monday this week'));

    $st = $pdo->prepare("SELECT u.id, COALESCE(NULLIF(u.nickname,''), 'İsimsiz') nickname
                         FROM users u
                         JOIN coaching_relationships cr ON cr.student_id = u.id
                         WHERE cr.teacher_id = ? AND u.is_active = 1 AND u.league_optin = 1");
    $st->execute([$teacherId]);
    $members = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$members) return [];

    $xp = gamify_week_xp($pdo, array_column($members, 'id'), $weekStart);
    foreach ($members as &$m) { $m['xp'] = $xp[(int)$m['id']] ?? 0; }
    unset($m);

    usort($members, fn($a, $b) => $b['xp'] <=> $a['xp'] ?: strcmp($a['nickname'], $b['nickname']));
    foreach ($members as $i => &$m) { $m['rank'] = $i + 1; }
    unset($m);
    return $members;
}

/** "Kendine karşı": bu hafta vs geçen hafta XP (opt-in şartı yok). */
function league_self_compare(PDO $pdo, int $studentId): array
{
    $thisMon = date('Y-m-d', strtotime('monday this week'));
    $lastMon = date('Y-m-d', strtotime($thisMon . ' -7 days'));
    $cur  = gamify_week_xp($pdo, [$studentId], $thisMon);
    $prev = gamify_week_xp($pdo, [$studentId], $lastMon);
    return ['this_week' => $cur[$studentId] ?? 0, 'last_week' => $prev[$studentId] ?? 0];
}

/**
 * Öğrencinin lig kartı verisi: durum + sıralama + kendi satırı + yüzdelik.
 */
function league_card_data(PDO $pdo, int $studentId): array
{
    league_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT league_optin, nickname FROM users WHERE id = ?");
    $st->execute([$studentId]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: ['league_optin' => 0, 'nickname' => null];

    $out = [
        'optin'    => (int)$u['league_optin'] === 1,
        'nickname' => (string)($u['nickname'] ?? ''),
        'self'     => league_self_compare($pdo, $studentId),
        'rows'     => [], 'me' => null, 'percentile' => null,
    ];
    if (!$out['optin']) return $out;

    $tid = league_coach_of($pdo, $studentId);
    if (!$tid) return $out;

    $rows = league_standings($pdo, $tid);
    $out['rows'] = $rows;
    foreach ($rows as $r) {
        if ((int)$r['id'] === $studentId) {
            $out['me'] = $r;
            $n = count($rows);
            $out['percentile'] = $n > 0 ? (int)ceil(100 * $r['rank'] / $n) : null;
            break;
        }
    }
    return $out;
}
