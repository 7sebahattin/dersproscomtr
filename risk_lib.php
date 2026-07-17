<?php
/**
 * risk_lib.php — Öğrenci risk skoru: erken uyarı motoru
 * Plan: docs/plan-v1-secili-moduller.md §C2 (S3)
 *
 * Skor 0-100 (yüksek = iyi durumda). Bileşenler ve varsayılan ağırlıklar:
 *   tamamlama %40 · giriş düzeni %20 · seri %15 · net eğimi %15 · çalışma dk %10
 * Verisi olmayan bileşen hesaptan çıkar, kalan ağırlıklar yeniden normalize edilir.
 *
 * Kararlılık: EMA yumuşatma (0.4 yeni + 0.6 önceki) + kırmızı için histerezis
 * (giriş <40, çıkış >50). 7 günden az verisi olan öğrenci 'gray' (veri toplanıyor).
 * Görevsiz günler tamamlama bileşenine dahil edilmez (koç plan yapmadıysa
 * öğrenci cezalandırılmaz — streak ilkesiyle aynı).
 *
 * Hesap her gece metriklerden sonra koşar (cron_notifications tick'i).
 * Skor tarihi = dün (gün kapanmadan skor üretilmez).
 */

require_once __DIR__ . '/app_settings_lib.php';
require_once __DIR__ . '/metrics_lib.php';

function risk_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS risk_scores (
        student_id INT NOT NULL,
        score_date DATE NOT NULL,
        score      TINYINT UNSIGNED NOT NULL DEFAULT 0,
        level      ENUM('green','yellow','red','gray') NOT NULL DEFAULT 'gray',
        components TEXT NULL,
        computed_at DATETIME NULL,
        PRIMARY KEY (student_id, score_date),
        KEY idx_rs_date (score_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

/** Ağırlıklar app_settings'ten (risk_w_*), yoksa plan varsayılanları. */
function risk_weights(PDO $pdo): array
{
    $def = ['completion' => 40, 'login' => 20, 'streak' => 15, 'net' => 15, 'study' => 10];
    $w = [];
    foreach ($def as $k => $v) {
        $w[$k] = max(0, (int)app_setting_get($pdo, 'risk_w_' . $k, (string)$v));
    }
    return $w;
}

/**
 * Tek öğrencinin ham bileşenlerini hesaplar (score_date gününe kadar, o gün dahil).
 * Dönüş: ['components'=>[ad=>0-100|null], 'data_days'=>int]
 */
function risk_components(PDO $pdo, int $studentId, string $scoreDate): array
{
    $from = date('Y-m-d', strtotime($scoreDate . ' -13 days'));
    $rows = metrics_get_range($pdo, $studentId, $from, $scoreDate);
    $dataDays = count($rows);

    // Tamamlama: yalnız görevli günler
    $sumTotal = 0; $sumDone = 0.0; $loggedDays = 0; $studySum = 0; $studyDays = 0;
    foreach ($rows as $r) {
        if ((int)$r['tasks_total'] > 0) {
            $sumTotal += (int)$r['tasks_total'];
            $sumDone  += (int)$r['tasks_done'] + 0.5 * (int)$r['tasks_half'];
        }
        if ((int)$r['logged_in'] === 1) $loggedDays++;
        if ((int)$r['study_minutes'] > 0) { $studySum += (int)$r['study_minutes']; $studyDays++; }
    }
    $completion = $sumTotal > 0 ? (int)round(100 * $sumDone / $sumTotal) : null;
    $login      = $dataDays > 0 ? (int)round(100 * $loggedDays / $dataDays) : null;

    // Seri: 7+ gün = 100 (users.current_streak — streak motoru zaten günceller)
    $streak = null;
    try {
        $st = $pdo->prepare("SELECT current_streak FROM users WHERE id = ?");
        $st->execute([$studentId]);
        $cs = $st->fetchColumn();
        if ($cs !== false) $streak = (int)min(100, round((int)$cs / 7 * 100));
    } catch (Throwable $e) {}

    // Net eğimi: son 45 günde en az 3 deneme → deneme başına net değişimi.
    // +2 net/deneme = 100, -2 = 0, 0 = 50 (nötr).
    $net = null;
    try {
        $qn = $pdo->prepare("SELECT total_net FROM quiz_results
                             WHERE student_id = ? AND date_taken >= DATE_SUB(?, INTERVAL 45 DAY)
                               AND date_taken <= CONCAT(?, ' 23:59:59')
                             ORDER BY date_taken, id LIMIT 8");
        $qn->execute([$studentId, $scoreDate, $scoreDate]);
        $nets = array_map('floatval', $qn->fetchAll(PDO::FETCH_COLUMN));
        $n = count($nets);
        if ($n >= 3) {
            // Basit doğrusal regresyon eğimi (x = deneme sırası)
            $sx = $sy = $sxy = $sxx = 0.0;
            foreach ($nets as $i => $y) { $sx += $i; $sy += $y; $sxy += $i * $y; $sxx += $i * $i; }
            $den = $n * $sxx - $sx * $sx;
            $slope = $den != 0 ? ($n * $sxy - $sx * $sy) / $den : 0.0;
            $net = (int)max(0, min(100, round(50 + $slope * 25)));
        }
    } catch (Throwable $e) {}

    // Çalışma süresi: modül canlı değilse (hiç veri yok) hesaptan çıkar.
    // Günde 120 dk = 100 (çalışılan günlerin ortalaması).
    $study = $studyDays > 0 ? (int)min(100, round(($studySum / $studyDays) / 120 * 100)) : null;

    return [
        'components' => ['completion' => $completion, 'login' => $login,
                         'streak' => $streak, 'net' => $net, 'study' => $study],
        'data_days'  => $dataDays,
    ];
}

/**
 * Tüm aktif öğrenciler için dünün skorunu hesaplar ve yazar.
 * Dönüş: ['students'=>N, 'red'=>N, 'yellow'=>N]
 */
function risk_run(PDO $pdo): array
{
    risk_ensure_schema($pdo);
    $scoreDate = date('Y-m-d', strtotime('-1 day'));
    $weights   = risk_weights($pdo);

    $students = $pdo->query("SELECT id FROM users WHERE role='student' AND is_active=1")
                    ->fetchAll(PDO::FETCH_COLUMN);

    // Önceki skor + seviye (EMA ve histerezis için) tek sorguda
    $prev = [];
    try {
        foreach ($pdo->query("SELECT r.student_id, r.score, r.level
                              FROM risk_scores r
                              JOIN (SELECT student_id, MAX(score_date) md FROM risk_scores
                                    WHERE score_date < '$scoreDate' GROUP BY student_id) x
                                ON x.student_id = r.student_id AND x.md = r.score_date") as $r) {
            $prev[(int)$r['student_id']] = $r;
        }
    } catch (Throwable $e) {}

    $up = $pdo->prepare("INSERT INTO risk_scores (student_id, score_date, score, level, components, computed_at)
                         VALUES (?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE score=VALUES(score), level=VALUES(level),
                             components=VALUES(components), computed_at=NOW()");

    $counts = ['students' => 0, 'red' => 0, 'yellow' => 0];
    foreach ($students as $sid) {
        $sid = (int)$sid;
        $c = risk_components($pdo, $sid, $scoreDate);

        // Ağırlıklı ortalama — null bileşenler çıkar, kalan yeniden normalize
        $wSum = 0; $acc = 0.0;
        foreach ($c['components'] as $name => $val) {
            if ($val === null) continue;
            $acc  += $val * $weights[$name];
            $wSum += $weights[$name];
        }
        $raw = $wSum > 0 ? (int)round($acc / $wSum) : 50;

        // EMA yumuşatma
        $p = $prev[$sid] ?? null;
        $score = $p ? (int)round(0.4 * $raw + 0.6 * (int)$p['score']) : $raw;

        // Seviye: soğuk başlangıç + kırmızı histerezisi
        if ($c['data_days'] < 7) {
            $level = 'gray';
        } elseif (($p['level'] ?? '') === 'red' && $score <= 50) {
            $level = 'red';                       // kırmızıdan çıkış ancak >50
        } elseif ($score < 40) {
            $level = 'red';
        } elseif ($score < 65) {
            $level = 'yellow';
        } else {
            $level = 'green';
        }

        $up->execute([$sid, $scoreDate, $score, $level,
                      json_encode($c['components'] + ['raw' => $raw, 'data_days' => $c['data_days']],
                                  JSON_UNESCAPED_UNICODE)]);
        $counts['students']++;
        if ($level === 'red')    $counts['red']++;
        if ($level === 'yellow') $counts['yellow']++;
    }

    app_setting_set($pdo, 'risk_last_ymd', date('Y-m-d'));
    return $counts;
}

/** Günlük kapı: metrik tick'inden sonra çağrılır; günde bir kez koşar. */
function risk_daily_tick(PDO $pdo): ?array
{
    if ((int)date('G') < 3) return null;
    if (app_setting_get($pdo, 'risk_last_ymd') === date('Y-m-d')) return null;
    return risk_run($pdo);
}

/**
 * Bir koçun öğrencilerinin en güncel risk durumu (kart + push için).
 * Dönüş: her öğrenci için id, ad, score, level, components.
 */
function risk_get_for_teacher(PDO $pdo, int $teacherId): array
{
    try {
        $st = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, r.score, r.level, r.components, r.score_date
            FROM coaching_relationships cr
            JOIN users u ON u.id = cr.student_id AND u.is_active = 1
            JOIN risk_scores r ON r.student_id = u.id
            JOIN (SELECT student_id, MAX(score_date) md FROM risk_scores GROUP BY student_id) x
              ON x.student_id = r.student_id AND x.md = r.score_date
            WHERE cr.teacher_id = ?
            ORDER BY FIELD(r.level,'red','yellow','gray','green'), r.score");
        $st->execute([$teacherId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}
