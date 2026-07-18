<?php
/**
 * study_lib.php — Zaman Motoru: pomodoro / kronometre oturumları
 * Plan: docs/plan-v1-secili-moduller.md §A (S1)
 *
 * Kurallar:
 *  - Süre DAİMA sunucu zamanından türetilir; istemci sayacı yalnızca görseldir.
 *    İstemci her kalp atışında kendi aktif saniyesini bildirir; sunucu bunu
 *    duvar saatiyle sınırlar: duration = min(istemci_aktif, now - started_at).
 *  - Öğrenci başına tek 'active' oturum. İkinci başlatma reddedilir.
 *  - Kalp atışı 10+ dk kesilen oturumu gece cron'u 'abandoned' kapatır
 *    (süre son kalp atışına kadar sayılır — sekme kapanması veri kaybetmez).
 *  - Günlük sayılan tavan 600 dk (app_settings: study_daily_cap_min).
 *  - Manuel telafi girişi source='manual' ile ayrı damgalanır.
 */

require_once __DIR__ . '/app_settings_lib.php';

function study_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS study_sessions (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        student_id       INT NOT NULL,
        schedule_item_id INT NULL,
        edu_topic_id     INT NULL,
        mode             ENUM('pomodoro','stopwatch') NOT NULL DEFAULT 'stopwatch',
        status           ENUM('active','done','abandoned') NOT NULL DEFAULT 'active',
        source           ENUM('timer','manual') NOT NULL DEFAULT 'timer',
        started_at       DATETIME NOT NULL,
        last_heartbeat   DATETIME NOT NULL,
        ended_at         DATETIME NULL,
        duration_sec     INT NOT NULL DEFAULT 0,
        pause_sec        INT NOT NULL DEFAULT 0,
        KEY idx_ss_student_date (student_id, started_at),
        KEY idx_ss_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

function study_daily_cap_min(PDO $pdo): int
{
    return max(30, (int)app_setting_get($pdo, 'study_daily_cap_min', '600'));
}

/** Öğrencinin bugünkü sayılan toplam dakikası. */
function study_today_minutes(PDO $pdo, int $studentId): int
{
    try {
        $st = $pdo->prepare("SELECT COALESCE(SUM(duration_sec),0) FROM study_sessions
                             WHERE student_id = ? AND DATE(started_at) = CURDATE()");
        $st->execute([$studentId]);
        return (int)round(((int)$st->fetchColumn()) / 60);
    } catch (Throwable $e) { return 0; }
}

/** Aktif oturumu döndürür (yoksa null). */
function study_active_session(PDO $pdo, int $studentId): ?array
{
    $st = $pdo->prepare("SELECT * FROM study_sessions WHERE student_id = ? AND status = 'active'
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$studentId]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    return $s ?: null;
}

/**
 * Oturum başlatır. Aktif oturum varsa onu döndürür (yenisini açmaz) —
 * "başka sekmede/cihazda sayaç açık" durumu istemciye bildirilir.
 */
function study_start(PDO $pdo, int $studentId, ?int $scheduleItemId, ?int $eduTopicId, string $mode): array
{
    study_ensure_schema($pdo);

    if (study_today_minutes($pdo, $studentId) >= study_daily_cap_min($pdo)) {
        return ['ok' => false, 'error' => 'Bugünkü sayılan çalışma tavanına ulaştın — dinlenmek de çalışmanın parçası! 🌙'];
    }

    $active = study_active_session($pdo, $studentId);
    if ($active) {
        return ['ok' => true, 'resumed' => true, 'session' => study_session_public($active)];
    }

    // Görev bağıysa öğrenciye ait olduğunu doğrula + edu konusunu devral
    if ($scheduleItemId) {
        $chk = $pdo->prepare("SELECT id, edu_topic_id FROM schedule_items WHERE id = ? AND student_id = ?");
        $chk->execute([$scheduleItemId, $studentId]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['ok' => false, 'error' => 'Görev bulunamadı.'];
        if (!$eduTopicId && !empty($row['edu_topic_id'])) $eduTopicId = (int)$row['edu_topic_id'];
    }

    $mode = $mode === 'pomodoro' ? 'pomodoro' : 'stopwatch';
    $pdo->prepare("INSERT INTO study_sessions
        (student_id, schedule_item_id, edu_topic_id, mode, status, source, started_at, last_heartbeat)
        VALUES (?,?,?,?, 'active', 'timer', NOW(), NOW())")
        ->execute([$studentId, $scheduleItemId ?: null, $eduTopicId ?: null, $mode]);

    $s = study_active_session($pdo, $studentId);
    return ['ok' => true, 'resumed' => false, 'session' => study_session_public($s)];
}

/**
 * Kalp atışı: istemcinin bildirdiği aktif saniye duvar saatiyle sınırlanır.
 * Dönüş: güncel süre — istemci görsel sayacını buna eşitler (hile/sapma düzelir).
 */
function study_heartbeat(PDO $pdo, int $studentId, int $sessionId, int $clientActiveSec): array
{
    $st = $pdo->prepare("SELECT * FROM study_sessions
                         WHERE id = ? AND student_id = ? AND status = 'active'");
    $st->execute([$sessionId, $studentId]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) return ['ok' => false, 'error' => 'Aktif oturum yok.', 'gone' => true];

    $wall     = max(0, time() - strtotime($s['started_at']));
    $duration = max((int)$s['duration_sec'], min(max(0, $clientActiveSec), $wall));
    $pause    = max(0, $wall - $duration);

    $pdo->prepare("UPDATE study_sessions SET duration_sec = ?, pause_sec = ?, last_heartbeat = NOW() WHERE id = ?")
        ->execute([$duration, $pause, $sessionId]);

    return ['ok' => true, 'duration_sec' => $duration];
}

/** Oturumu bitirir; dakika + bağlı görev id döner (UI görev modalını açabilir). */
function study_finish(PDO $pdo, int $studentId, int $sessionId, int $clientActiveSec): array
{
    $hb = study_heartbeat($pdo, $studentId, $sessionId, $clientActiveSec);
    if (!$hb['ok']) return $hb;

    $pdo->prepare("UPDATE study_sessions SET status = 'done', ended_at = NOW() WHERE id = ? AND student_id = ?")
        ->execute([$sessionId, $studentId]);

    $st = $pdo->prepare("SELECT duration_sec, schedule_item_id FROM study_sessions WHERE id = ?");
    $st->execute([$sessionId]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    return ['ok' => true,
            'minutes' => (int)round((int)$s['duration_sec'] / 60),
            'schedule_item_id' => $s['schedule_item_id'] ? (int)$s['schedule_item_id'] : null];
}

/** Manuel telafi girişi (timer'sız): ayrı damgalanır, tavana tabidir. */
function study_manual(PDO $pdo, int $studentId, int $minutes, ?int $eduTopicId): array
{
    study_ensure_schema($pdo);
    $minutes = max(1, min(600, $minutes));
    $todayMin = study_today_minutes($pdo, $studentId);
    $cap = study_daily_cap_min($pdo);
    if ($todayMin + $minutes > $cap) {
        return ['ok' => false, 'error' => 'Günlük sayılan tavanı aşıyor (' . $todayMin . '/' . $cap . ' dk).'];
    }
    $pdo->prepare("INSERT INTO study_sessions
        (student_id, edu_topic_id, mode, status, source, started_at, last_heartbeat, ended_at, duration_sec)
        VALUES (?,?, 'stopwatch', 'done', 'manual', DATE_SUB(NOW(), INTERVAL ? MINUTE), NOW(), NOW(), ?)")
        ->execute([$studentId, $eduTopicId ?: null, $minutes, $minutes * 60]);
    return ['ok' => true, 'minutes' => $minutes];
}

/** Kalp atışı 10+ dk kesilen aktif oturumları kapatır (gece cron'u). */
function study_cleanup(PDO $pdo): int
{
    try {
        study_ensure_schema($pdo);
        $st = $pdo->prepare("UPDATE study_sessions
                             SET status = 'abandoned', ended_at = last_heartbeat
                             WHERE status = 'active' AND last_heartbeat < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { return 0; }
}

/** İstemciye dönen güvenli alan alt kümesi. */
function study_session_public(?array $s): ?array
{
    if (!$s) return null;
    return [
        'id'               => (int)$s['id'],
        'mode'             => $s['mode'],
        'schedule_item_id' => $s['schedule_item_id'] ? (int)$s['schedule_item_id'] : null,
        'duration_sec'     => (int)$s['duration_sec'],
        'started_at'       => $s['started_at'],
    ];
}
