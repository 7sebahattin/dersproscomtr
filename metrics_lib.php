<?php
/**
 * metrics_lib.php — Günlük öğrenci metrikleri: tek doğruluk kaynağı
 * Plan: docs/plan-v1-secili-moduller.md §0.1 (S0)
 *
 * student_daily_metrics: öğrenci × gün başına görev/soru/süre/giriş özeti.
 * Gece hesaplanır (cron_metrics.php veya cron_notifications içindeki tick);
 * ekranlar "düne kadar tablodan, bugün canlı" ilkesiyle okur.
 *
 * Şema kurulumu (metrics_ensure_schema) yalnızca cron ve admin bağlamında
 * çağrılır; kullanıcı sayfaları tabloyu hazır varsayar (yoksa sessiz geçer).
 */

require_once __DIR__ . '/app_settings_lib.php';

function metrics_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_daily_metrics (
        student_id     INT NOT NULL,
        metric_date    DATE NOT NULL,
        tasks_total    SMALLINT NOT NULL DEFAULT 0,
        tasks_done     SMALLINT NOT NULL DEFAULT 0,
        tasks_half     SMALLINT NOT NULL DEFAULT 0,
        questions_done INT NOT NULL DEFAULT 0,
        correct_sum    INT NOT NULL DEFAULT 0,
        wrong_sum      INT NOT NULL DEFAULT 0,
        study_minutes  INT NOT NULL DEFAULT 0,
        logged_in      TINYINT(1) NOT NULL DEFAULT 0,
        last_net       FLOAT NULL,
        computed_at    DATETIME NULL,
        PRIMARY KEY (student_id, metric_date),
        KEY idx_sdm_date (metric_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

/**
 * Öğrenci bugün sisteme girdi: günün satırına logged_in=1 damgası.
 * Gün ortasında last_login_at ezilse bile iz kalıcıdır (gece yarısı sorunu çözümü).
 * Tablo henüz kurulmadıysa sessiz geçer — sayfa akışını asla bozmaz.
 */
function metrics_mark_login(PDO $pdo, int $studentId): void
{
    try {
        $pdo->prepare("INSERT INTO student_daily_metrics (student_id, metric_date, logged_in)
                       VALUES (?, CURDATE(), 1)
                       ON DUPLICATE KEY UPDATE logged_in = 1")
            ->execute([$studentId]);
    } catch (Throwable $e) { /* tablo yoksa ilk cron kurulumuna kadar sessiz */ }
}

/**
 * Tek bir günün metriklerini tüm aktif öğrenciler için hesaplar ve upsert eder.
 * logged_in asla 1→0 düşürülmez (GREATEST); backfill'de last_login_at izi de sayılır.
 * Dönüş: yazılan satır sayısı.
 */
function metrics_compute_day(PDO $pdo, string $date): int
{
    // Aktif öğrenciler + backfill için last_login_at izi
    $students = [];
    foreach ($pdo->query("SELECT id, last_login_at FROM users WHERE role='student' AND is_active=1") as $r) {
        $students[(int)$r['id']] = ['login' => $r['last_login_at'] && substr((string)$r['last_login_at'], 0, 10) === $date];
    }
    if (!$students) return 0;

    // Görev + soru özeti (tek sorgu). amount, işaretlenmiş görevde "yapılan"dır;
    // 'bekliyor'/'yapilmadi' durumunda hedeftir → soru sayımına girmez.
    $tasks = [];
    $st = $pdo->prepare("
        SELECT student_id,
               COUNT(*)                                              t,
               SUM(status='yapildi')                                 d,
               SUM(status='yarim')                                   h,
               SUM(CASE WHEN action_type='soru' AND status IN ('yapildi','yarim')
                        THEN amount ELSE 0 END)                      q,
               SUM(CASE WHEN status IN ('yapildi','yarim')
                        THEN COALESCE(correct_count,0) ELSE 0 END)   c,
               SUM(CASE WHEN status IN ('yapildi','yarim')
                        THEN COALESCE(wrong_count,0) ELSE 0 END)     w
        FROM schedule_items WHERE `date` = ? GROUP BY student_id");
    $st->execute([$date]);
    foreach ($st as $r) { $tasks[(int)$r['student_id']] = $r; }

    // Çalışma süresi (S1'den sonra dolar; tablo yoksa 0)
    $minutes = [];
    try {
        if ($pdo->query("SHOW TABLES LIKE 'study_sessions'")->rowCount() > 0) {
            $sm = $pdo->prepare("SELECT student_id, ROUND(SUM(duration_sec)/60) m
                                 FROM study_sessions
                                 WHERE DATE(started_at) = ? AND status IN ('done','abandoned')
                                 GROUP BY student_id");
            $sm->execute([$date]);
            foreach ($sm as $r) { $minutes[(int)$r['student_id']] = (int)$r['m']; }
        }
    } catch (Throwable $e) {}

    // O gün girilen son deneme neti
    $nets = [];
    try {
        $sn = $pdo->prepare("SELECT qr.student_id, qr.total_net
                             FROM quiz_results qr
                             JOIN (SELECT student_id, MAX(id) mid FROM quiz_results
                                   WHERE DATE(date_taken) = ? GROUP BY student_id) x ON x.mid = qr.id");
        $sn->execute([$date]);
        foreach ($sn as $r) { $nets[(int)$r['student_id']] = (float)$r['total_net']; }
    } catch (Throwable $e) {}

    $up = $pdo->prepare("
        INSERT INTO student_daily_metrics
            (student_id, metric_date, tasks_total, tasks_done, tasks_half,
             questions_done, correct_sum, wrong_sum, study_minutes, logged_in, last_net, computed_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE
            tasks_total=VALUES(tasks_total), tasks_done=VALUES(tasks_done), tasks_half=VALUES(tasks_half),
            questions_done=VALUES(questions_done), correct_sum=VALUES(correct_sum), wrong_sum=VALUES(wrong_sum),
            study_minutes=VALUES(study_minutes),
            logged_in=GREATEST(logged_in, VALUES(logged_in)),
            last_net=COALESCE(VALUES(last_net), last_net),
            computed_at=NOW()");

    $written = 0;
    foreach ($students as $sid => $info) {
        $tk = $tasks[$sid] ?? null;
        $up->execute([
            $sid, $date,
            (int)($tk['t'] ?? 0), (int)($tk['d'] ?? 0), (int)($tk['h'] ?? 0),
            (int)($tk['q'] ?? 0), (int)($tk['c'] ?? 0), (int)($tk['w'] ?? 0),
            $minutes[$sid] ?? 0,
            $info['login'] ? 1 : 0,
            $nets[$sid] ?? null,
        ]);
        $written++;
    }
    return $written;
}

/**
 * Günlük çalıştırma: ilk kurulumda 90 gün backfill, sonrasında son 3 günü
 * yeniden hesaplar (öğrenci dünün görevini bugün işaretleyebilir → kayan pencere).
 * Dönüş: ['days' => hesaplanan gün sayısı, 'rows' => yazılan satır]
 */
function metrics_run(PDO $pdo): array
{
    metrics_ensure_schema($pdo);
    $isEmpty = ((int)$pdo->query("SELECT COUNT(*) FROM student_daily_metrics")->fetchColumn()) === 0;
    $window  = $isEmpty ? 90 : 3;

    $rows = 0;
    for ($i = $window; $i >= 0; $i--) {
        $rows += metrics_compute_day($pdo, date('Y-m-d', strtotime("-$i day")));
    }

    // Watchdog damgaları (admin/features.php gösterir; 26 saati aşarsa uyarı)
    app_setting_set($pdo, 'metrics_last_ymd', date('Y-m-d'));
    app_setting_set($pdo, 'metrics_last_run', date('Y-m-d H:i:s'));

    return ['days' => $window + 1, 'rows' => $rows];
}

/**
 * Ucuz günlük kapı: pseudo-cron her 4 dk çağırır; gece 03:00'ten önce ve
 * bugün zaten koştuysa tek SELECT ile çıkar. Ağır iş günde bir kez döner.
 */
function metrics_daily_tick(PDO $pdo): ?array
{
    if ((int)date('G') < 3) return null;
    if (app_setting_get($pdo, 'metrics_last_ymd') === date('Y-m-d')) return null;
    return metrics_run($pdo);
}

/**
 * Bir öğrencinin gün aralığı metriklerini döndürür (risk/rapor/XP tüketicileri).
 * Bugün dahilse ve satır cron'dan eskiyse çağıran taraf bugünü canlı hesaplamalı.
 */
function metrics_get_range(PDO $pdo, int $studentId, string $from, string $to): array
{
    try {
        $st = $pdo->prepare("SELECT * FROM student_daily_metrics
                             WHERE student_id = ? AND metric_date BETWEEN ? AND ?
                             ORDER BY metric_date");
        $st->execute([$studentId, $from, $to]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}
