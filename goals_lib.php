<?php
/**
 * goals_lib.php — Hedef & Tahmin Motoru
 * Plan: docs/plan-v1-secili-moduller.md §B1–B2 (S4)
 *
 * student_goals : öğrencinin hedef üniversite/bölüm/net'i (kategori başına tek aktif)
 * rank_reference: admin bakımlı net→sıralama dönüşüm noktaları (yıl damgalı)
 *
 * Tahmin iki adım: (1) son 5 denemenin ağırlıklı ortalaması + doğrusal eğim →
 * net projeksiyonu; (2) rank_reference üzerinde lineer interpolasyon → sıralama.
 * Korumalar: en az 3 deneme şartı, ±%10 bant (nokta değil aralık), 12 aydan
 * eski referans verisine "güncel değil" işareti, kategori tavan clamp'i.
 */

require_once __DIR__ . '/app_settings_lib.php';

function goals_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_goals (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        student_id        INT NOT NULL,
        exam_category     ENUM('TYT','AYT','LGS') NOT NULL DEFAULT 'TYT',
        target_university VARCHAR(150) NULL,
        target_department VARCHAR(150) NULL,
        target_net        DECIMAL(6,2) NULL,
        target_rank       INT NULL,
        set_by            ENUM('student','teacher') NOT NULL DEFAULT 'student',
        show_prediction   TINYINT(1) NOT NULL DEFAULT 1,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_goal (student_id, exam_category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rank_reference (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        ref_year      SMALLINT NOT NULL,
        exam_category ENUM('TYT','AYT','LGS') NOT NULL,
        net_value     DECIMAL(6,2) NOT NULL,
        rank_estimate INT NOT NULL,
        UNIQUE KEY uq_ref (ref_year, exam_category, net_value)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

/** Kategori başına makul net tavanı (projeksiyon clamp'i). */
function goals_net_cap(string $cat): float
{
    return ['TYT' => 120.0, 'AYT' => 80.0, 'LGS' => 90.0][$cat] ?? 120.0;
}

function goals_get(PDO $pdo, int $studentId, string $cat): ?array
{
    goals_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT * FROM student_goals WHERE student_id = ? AND exam_category = ?");
    $st->execute([$studentId, $cat]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

function goals_save(PDO $pdo, int $studentId, string $cat, array $data, string $setBy = 'student'): void
{
    goals_ensure_schema($pdo);
    $cat = in_array($cat, ['TYT', 'AYT', 'LGS'], true) ? $cat : 'TYT';
    $uni = mb_substr(trim((string)($data['target_university'] ?? '')), 0, 150) ?: null;
    $dep = mb_substr(trim((string)($data['target_department'] ?? '')), 0, 150) ?: null;
    $net = ($data['target_net'] ?? '') !== '' ? max(0, min(goals_net_cap($cat), (float)$data['target_net'])) : null;
    $rank = ($data['target_rank'] ?? '') !== '' ? max(1, (int)$data['target_rank']) : null;

    $pdo->prepare("INSERT INTO student_goals
            (student_id, exam_category, target_university, target_department, target_net, target_rank, set_by)
        VALUES (?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            target_university=VALUES(target_university), target_department=VALUES(target_department),
            target_net=VALUES(target_net), target_rank=VALUES(target_rank), set_by=VALUES(set_by)")
        ->execute([$studentId, $cat, $uni, $dep, $net, $rank, $setBy === 'teacher' ? 'teacher' : 'student']);
}

/**
 * Net projeksiyonu: kategorideki son 5 deneme (kronolojik) üzerinden
 * ağırlıklı ortalama (yeni deneme ağır) + doğrusal eğim.
 * Dönüş: null (deneme < 3) veya
 *  ['projected','avg','slope','exam_count','last_net']
 */
function goals_net_projection(PDO $pdo, int $studentId, string $cat): ?array
{
    $st = $pdo->prepare("SELECT total_net FROM quiz_results
                         WHERE student_id = ? AND category = ?
                         ORDER BY date_taken DESC, id DESC LIMIT 5");
    $st->execute([$studentId, $cat]);
    $nets = array_reverse(array_map('floatval', $st->fetchAll(PDO::FETCH_COLUMN)));
    $n = count($nets);
    if ($n < 3) return null;

    // Ağırlıklı ortalama: ağırlık = sıra (en yeni en ağır)
    $wSum = 0; $acc = 0.0;
    foreach ($nets as $i => $v) { $w = $i + 1; $acc += $v * $w; $wSum += $w; }
    $avg = $acc / $wSum;

    // Doğrusal eğim (deneme başına net değişimi)
    $sx = $sy = $sxy = $sxx = 0.0;
    foreach ($nets as $i => $y) { $sx += $i; $sy += $y; $sxy += $i * $y; $sxx += $i * $i; }
    $den = $n * $sxx - $sx * $sx;
    $slope = $den != 0 ? ($n * $sxy - $sx * $sy) / $den : 0.0;

    $projected = max(0.0, min(goals_net_cap($cat), $avg + $slope));
    return ['projected' => round($projected, 2), 'avg' => round($avg, 2),
            'slope' => round($slope, 2), 'exam_count' => $n, 'last_net' => end($nets)];
}

/**
 * Net → sıralama: en güncel yıl verisi üzerinde lineer interpolasyon.
 * ±%10 net bandıyla aralık döner. Veri yoksa null.
 * Dönüş: ['rank_best','rank_worst','ref_year','stale']
 */
function goals_rank_estimate(PDO $pdo, string $cat, float $net): ?array
{
    goals_ensure_schema($pdo);
    $yr = $pdo->prepare("SELECT MAX(ref_year) FROM rank_reference WHERE exam_category = ?");
    $yr->execute([$cat]);
    $year = (int)$yr->fetchColumn();
    if (!$year) return null;

    $st = $pdo->prepare("SELECT net_value, rank_estimate FROM rank_reference
                         WHERE exam_category = ? AND ref_year = ? ORDER BY net_value");
    $st->execute([$cat, $year]);
    $points = $st->fetchAll(PDO::FETCH_ASSOC);
    if (count($points) < 2) return null;

    $interp = function (float $x) use ($points): int {
        $lo = $points[0]; $hi = end($points);
        if ($x <= (float)$lo['net_value']) return (int)$lo['rank_estimate'];
        if ($x >= (float)$hi['net_value']) return (int)$hi['rank_estimate'];
        for ($i = 1, $c = count($points); $i < $c; $i++) {
            $a = $points[$i - 1]; $b = $points[$i];
            $na = (float)$a['net_value']; $nb = (float)$b['net_value'];
            if ($x >= $na && $x <= $nb) {
                $t = $nb > $na ? ($x - $na) / ($nb - $na) : 0;
                return (int)round((int)$a['rank_estimate'] + $t * ((int)$b['rank_estimate'] - (int)$a['rank_estimate']));
            }
        }
        return (int)$hi['rank_estimate'];
    };

    return [
        'rank_best'  => $interp(min(goals_net_cap($cat), $net * 1.10)), // +%10 net → daha iyi sıralama
        'rank_worst' => $interp(max(0.0, $net * 0.90)),
        'ref_year'   => $year,
        'stale'      => $year < ((int)date('n') >= 7 ? (int)date('Y') : (int)date('Y') - 1),
    ];
}

/**
 * Öğrencinin hedef kartı verisi: hedef + projeksiyon + sıralama bandı,
 * tek çağrıda. exam_dates() geri sayımıyla birleşim ekranda yapılır.
 */
function goals_card_data(PDO $pdo, int $studentId, string $cat): array
{
    $goal = goals_get($pdo, $studentId, $cat);
    $proj = goals_net_projection($pdo, $studentId, $cat);
    $rank = null;
    if ($proj && (!$goal || (int)($goal['show_prediction'] ?? 1) === 1)) {
        $rank = goals_rank_estimate($pdo, $cat, (float)$proj['projected']);
    }
    return ['goal' => $goal, 'proj' => $proj, 'rank' => $rank];
}
