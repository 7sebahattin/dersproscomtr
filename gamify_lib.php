<?php
/**
 * gamify_lib.php — XP / Seviye / Başarımlar / Kalkan Dükkânı
 * Plan: docs/plan-v1-secili-moduller.md §D1–D2 (S8)
 *
 * İlkeler:
 *  - Olaylar GECE toplu üretilir (kayan 3 gün penceresi); UNIQUE anahtar
 *    (student_id, event_type, ref_type, ref_id) çift sayımı imkânsız kılar —
 *    görev yapildi→bekliyor→yapildi gezinse bile XP bir kez yazılır.
 *  - Geri alınan görevde XP geri alınmaz (v1 kararı: negatif XP moral/istismar
 *    dengesinde daha riskli).
 *  - Günlük XP tavanı (vars. 300) enflasyonu keser; tavana takılan olaylar
 *    kaydedilmez (ertesi gece UNIQUE izin verdiği için telafi edilebilir).
 *  - Retroaktif backfill YOK — herkes "Sezon 1"den başlar (adil + basit).
 *  - Kalkan dükkânı: 500 XP = 1 kalkan (users.freeze_count köprüsü).
 *
 * Plandan sapma: başarım kataloğu DB yerine PHP dizisinde (criteria JSON
 * tablosu v2) — kazanımlar student_achievements'ta saklanır; mevcut
 * dashboard $tierBadge eşiklerine dokunulmadı (çalışan kod korundu).
 */

require_once __DIR__ . '/app_settings_lib.php';

// Olay kataloğu: tип => [xp, açıklama]
const GAMIFY_EVENTS = [
    'task_done'    => [10, 'Görev tamamlama'],
    'day_full'     => [25, 'Günün tüm görevlerini bitirme'],
    'exam_entry'   => [30, 'Deneme girişi'],
    'study_20'     => [15, 'Sayaçla 20+ dk çalışma'],
    'study_manual' => [5,  'Manuel süre girişi'],
    'streak_ms'    => [50, 'Seri kilometre taşı'],
    'repeat_done'  => [20, 'Tekrar görevini tamamlama'],
    'achievement'  => [40, 'Başarım kazanımı'],
];

function gamify_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS xp_events (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        event_type VARCHAR(40) NOT NULL,
        xp         SMALLINT NOT NULL,
        ref_type   VARCHAR(20) NOT NULL DEFAULT '',
        ref_id     INT NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_xp (student_id, event_type, ref_type, ref_id),
        KEY idx_xp_student_date (student_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_achievements (
        student_id INT NOT NULL,
        code       VARCHAR(40) NOT NULL,
        earned_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (student_id, code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    // users önbellek kolonları (idempotent)
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('xp_total', $cols)) $pdo->exec("ALTER TABLE users ADD COLUMN xp_total INT NOT NULL DEFAULT 0");
        if (!in_array('level', $cols))    $pdo->exec("ALTER TABLE users ADD COLUMN level SMALLINT NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
    $done = true;
}

/** Seviye eğrisi: xp(L) = 100·L^1.5 → L(xp) = (xp/100)^(2/3). */
function gamify_level_from_xp(int $xp): int
{
    return $xp <= 0 ? 0 : (int)floor(pow($xp / 100, 2 / 3));
}

/** Bir sonraki seviyenin gerektirdiği toplam XP. */
function gamify_next_level_xp(int $level): int
{
    return (int)ceil(100 * pow($level + 1, 1.5));
}

function gamify_daily_xp_cap(PDO $pdo): int
{
    return max(50, (int)app_setting_get($pdo, 'xp_daily_cap', '300'));
}

/**
 * XP olayı yazar (idempotent). Tavan kontrolü pozitif olaylara uygulanır.
 * Dönüş: yazıldıysa true (mükerrer/tavan → false).
 */
function gamify_award(PDO $pdo, int $studentId, string $type, string $refType, int $refId, ?int $xpOverride = null): bool
{
    $xp = $xpOverride ?? (GAMIFY_EVENTS[$type][0] ?? 0);
    if ($xp === 0) return false;

    if ($xp > 0) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(xp),0) FROM xp_events
                             WHERE student_id = ? AND xp > 0 AND DATE(created_at) = CURDATE()");
        $st->execute([$studentId]);
        if ((int)$st->fetchColumn() + $xp > gamify_daily_xp_cap($pdo)) return false;
    }

    $ins = $pdo->prepare("INSERT IGNORE INTO xp_events (student_id, event_type, xp, ref_type, ref_id)
                          VALUES (?,?,?,?,?)");
    $ins->execute([$studentId, $type, $xp, $refType, $refId]);
    if ($ins->rowCount() === 0) return false; // mükerrer — UNIQUE engelledi

    // Önbelleği güncelle (kaynak daima xp_events; sapma gece tick'inde düzelir)
    $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total + ?),
                                    level = ? WHERE id = ?")
        ->execute([$xp, 0, $studentId]); // level aşağıda tazelenir
    $tq = $pdo->prepare("SELECT xp_total FROM users WHERE id = ?");
    $tq->execute([$studentId]);
    $tot = (int)$tq->fetchColumn();
    $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")
        ->execute([gamify_level_from_xp($tot), $studentId]);
    return true;
}

/**
 * Gece toplu üretim: kayan 3 gün penceresindeki olaylar taranır.
 * UNIQUE anahtar sayesinde her gece güvenle yeniden koşar.
 */
function gamify_generate(PDO $pdo): array
{
    gamify_ensure_schema($pdo);
    $from = date('Y-m-d', strtotime('-3 days'));
    $to   = date('Y-m-d', strtotime('-1 day'));
    $n = 0;

    // 1) Görev tamamlama (+ tekrar görevi bonusu)
    $repeatItems = [];
    try {
        foreach ($pdo->query("SELECT applied_item_id FROM task_suggestions
                              WHERE source='tekrar' AND applied_item_id IS NOT NULL") as $r) {
            $repeatItems[(int)$r['applied_item_id']] = true;
        }
    } catch (Throwable $e) {}

    foreach ($pdo->query("SELECT id, student_id FROM schedule_items
                          WHERE status = 'yapildi' AND `date` BETWEEN '$from' AND '$to'") as $r) {
        $sid = (int)$r['student_id']; $iid = (int)$r['id'];
        if (gamify_award($pdo, $sid, 'task_done', 'si', $iid)) $n++;
        if (isset($repeatItems[$iid]) && gamify_award($pdo, $sid, 'repeat_done', 'si', $iid)) $n++;
    }

    // 2) Günü %100 bitirme (metrik tablosundan)
    try {
        foreach ($pdo->query("SELECT student_id, metric_date FROM student_daily_metrics
                              WHERE metric_date BETWEEN '$from' AND '$to'
                                AND tasks_total > 0 AND tasks_done = tasks_total") as $r) {
            if (gamify_award($pdo, (int)$r['student_id'], 'day_full', 'day',
                             (int)date('Ymd', strtotime($r['metric_date'])))) $n++;
        }
    } catch (Throwable $e) {}

    // 3) Deneme girişi
    try {
        foreach ($pdo->query("SELECT id, student_id FROM quiz_results
                              WHERE DATE(date_taken) BETWEEN '$from' AND '$to'") as $r) {
            if (gamify_award($pdo, (int)$r['student_id'], 'exam_entry', 'qr', (int)$r['id'])) $n++;
        }
    } catch (Throwable $e) {}

    // 4) Çalışma oturumları (sayaç 20+ dk / manuel)
    try {
        foreach ($pdo->query("SELECT id, student_id, duration_sec, source FROM study_sessions
                              WHERE status IN ('done','abandoned')
                                AND DATE(started_at) BETWEEN '$from' AND '$to'
                                AND duration_sec >= 1200") as $r) {
            $type = $r['source'] === 'manual' ? 'study_manual' : 'study_20';
            if (gamify_award($pdo, (int)$r['student_id'], $type, 'ss', (int)$r['id'])) $n++;
        }
    } catch (Throwable $e) {}

    // 5) Seri kilometre taşları (7/30/100 gün) — taş başına bir kez
    try {
        foreach ($pdo->query("SELECT id, current_streak FROM users
                              WHERE role='student' AND is_active=1 AND current_streak >= 7") as $r) {
            foreach ([7, 30, 100] as $ms) {
                if ((int)$r['current_streak'] >= $ms
                    && gamify_award($pdo, (int)$r['id'], 'streak_ms', 'stk', $ms)) $n++;
            }
        }
    } catch (Throwable $e) {}

    // 6) Başarımlar
    $n += gamify_check_achievements($pdo);
    return ['events' => $n];
}

/**
 * Başarım kataloğu (v1 — kod tabanlı). Her giriş:
 * code => [ad, ikon, açıklama, SQL var-mı sorgusu (öğrenci başına 1 satır dönerse kazanıldı)]
 */
function gamify_achievement_catalog(): array
{
    return [
        'first_goal' => ['İlk Hedef', '🎯', 'Hedef üniversite/net belirledin',
            "SELECT student_id FROM student_goals
             WHERE target_university IS NOT NULL OR target_net IS NOT NULL"],
        'pomo_3day' => ['Odak Serisi', '🍅', '3 farklı günde sayaçla 20+ dk çalıştın',
            "SELECT student_id FROM study_sessions
             WHERE source='timer' AND duration_sec >= 1200 AND status IN ('done','abandoned')
             GROUP BY student_id HAVING COUNT(DISTINCT DATE(started_at)) >= 3"],
        'weekend_hero' => ['Hafta Sonu Kahramanı', '🌞', 'Aynı hafta sonunda hem Cmt hem Paz görev tamamladın',
            "SELECT a.student_id FROM student_daily_metrics a
             JOIN student_daily_metrics b
               ON b.student_id = a.student_id AND b.metric_date = DATE_ADD(a.metric_date, INTERVAL 1 DAY)
             WHERE DAYOFWEEK(a.metric_date) = 7 AND a.tasks_done > 0 AND b.tasks_done > 0
             GROUP BY a.student_id"],
        'repeat_10' => ['Tekrar Ustası', '🔁', '10 aralıklı tekrar görevi tamamladın',
            "SELECT student_id FROM xp_events WHERE event_type='repeat_done'
             GROUP BY student_id HAVING COUNT(*) >= 10"],
        'exam_streak' => ['Deneme Düzenlisi', '📝', 'Son 4 haftanın her birinde deneme girdin',
            "SELECT student_id FROM quiz_results
             WHERE date_taken >= DATE_SUB(CURDATE(), INTERVAL 28 DAY)
             GROUP BY student_id HAVING COUNT(DISTINCT YEARWEEK(date_taken, 3)) >= 4"],
    ];
}

/** Katalogdaki başarımları değerlendirir; yeni kazanımlara XP yazar. */
function gamify_check_achievements(PDO $pdo): int
{
    $n = 0;
    foreach (gamify_achievement_catalog() as $code => [$name, $icon, $desc, $sql]) {
        try {
            $earnedIds = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) { continue; }
        if (!$earnedIds) continue;
        $ins = $pdo->prepare("INSERT IGNORE INTO student_achievements (student_id, code) VALUES (?,?)");
        foreach (array_unique(array_map('intval', $earnedIds)) as $sid) {
            $ins->execute([$sid, $code]);
            if ($ins->rowCount() > 0) {
                // Başarım XP'si: ref = başarım kodu hash'i (idempotent)
                gamify_award($pdo, $sid, 'achievement', 'ach', crc32($code));
                $n++;
            }
        }
    }
    return $n;
}

/** Günlük kapı — ff_xp açıkken, 03:00 sonrası günde bir. */
function gamify_daily_tick(PDO $pdo): ?array
{
    if (!ff_enabled($pdo, 'xp')) return null;
    if ((int)date('G') < 3) return null;
    if (app_setting_get($pdo, 'xp_last_ymd') === date('Y-m-d')) return null;
    $res = gamify_generate($pdo);
    app_setting_set($pdo, 'xp_last_ymd', date('Y-m-d'));
    return $res;
}

/** Kalkan dükkânı: 500 XP = 1 kalkan (mevcut freeze/onarım sistemine köprü). */
function gamify_buy_freeze(PDO $pdo, int $studentId): array
{
    gamify_ensure_schema($pdo);
    $cost = max(100, (int)app_setting_get($pdo, 'xp_freeze_cost', '500'));

    $st = $pdo->prepare("SELECT xp_total FROM users WHERE id = ?");
    $st->execute([$studentId]);
    $tot = (int)$st->fetchColumn();
    if ($tot < $cost) return ['ok' => false, 'error' => "Yetersiz XP ($tot/$cost)."];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO xp_events (student_id, event_type, xp, ref_type, ref_id)
                       VALUES (?, 'spend_freeze', ?, 'spend', ?)")
            ->execute([$studentId, -$cost, time()]);
        $pdo->prepare("UPDATE users SET xp_total = GREATEST(0, xp_total - ?),
                                        freeze_count = freeze_count + 1 WHERE id = ?")
            ->execute([$cost, $studentId]);
        $tq = $pdo->prepare("SELECT xp_total FROM users WHERE id = ?");
        $tq->execute([$studentId]);
        $pdo->prepare("UPDATE users SET level = ? WHERE id = ?")
            ->execute([gamify_level_from_xp((int)$tq->fetchColumn()), $studentId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'İşlem tamamlanamadı.'];
    }
    return ['ok' => true];
}

/** Öğrencinin XP kartı verisi: seviye, ilerleme, kazanılan başarımlar. */
function gamify_student_summary(PDO $pdo, int $studentId): array
{
    gamify_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT xp_total, level, freeze_count FROM users WHERE id = ?");
    $st->execute([$studentId]);
    $u = $st->fetch(PDO::FETCH_ASSOC) ?: ['xp_total' => 0, 'level' => 0, 'freeze_count' => 0];

    $xp    = (int)$u['xp_total'];
    $level = gamify_level_from_xp($xp);
    $cur   = (int)ceil(100 * pow(max(0, $level), 1.5));
    $next  = gamify_next_level_xp($level);

    $earned = [];
    try {
        $aq = $pdo->prepare("SELECT code, earned_at FROM student_achievements WHERE student_id = ? ORDER BY earned_at DESC");
        $aq->execute([$studentId]);
        $cat = gamify_achievement_catalog();
        foreach ($aq as $a) {
            if (isset($cat[$a['code']])) {
                [$nm, $ic, $ds] = $cat[$a['code']];
                $earned[] = ['code' => $a['code'], 'name' => $nm, 'icon' => $ic, 'desc' => $ds, 'earned_at' => $a['earned_at']];
            }
        }
    } catch (Throwable $e) {}

    return ['xp' => $xp, 'level' => $level,
            'progress_pct' => $next > $cur ? (int)round(100 * ($xp - $cur) / ($next - $cur)) : 100,
            'next_level_xp' => $next, 'freeze_count' => (int)$u['freeze_count'],
            'freeze_cost' => max(100, (int)app_setting_get($pdo, 'xp_freeze_cost', '500')),
            'achievements' => $earned];
}

/** Haftalık XP toplamları (S9 lig tüketicisi). $weekStart = Pazartesi Y-m-d. */
function gamify_week_xp(PDO $pdo, array $studentIds, string $weekStart): array
{
    if (!$studentIds) return [];
    $in = implode(',', array_map('intval', $studentIds));
    $end = date('Y-m-d', strtotime($weekStart . ' +6 days'));
    $out = [];
    try {
        foreach ($pdo->query("SELECT student_id, COALESCE(SUM(xp),0) x FROM xp_events
                              WHERE student_id IN ($in) AND xp > 0
                                AND DATE(created_at) BETWEEN '$weekStart' AND '$end'
                              GROUP BY student_id") as $r) {
            $out[(int)$r['student_id']] = (int)$r['x'];
        }
    } catch (Throwable $e) {}
    return $out;
}
