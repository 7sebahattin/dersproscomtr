<?php
/**
 * suggest_lib.php — Birleşik görev öneri kuyruğu
 * Plan: docs/plan-v1-secili-moduller.md §0.2 + §C1 (S2), §B3 (S5)
 *
 * Analiz→aksiyon, aralıklı tekrar ve risk müdahalesi aynı kuyruğa yazar;
 * hiçbir öneri plana doğrudan girmez — koç onaylayınca schedule_items'a
 * kopyalanır (koçun otoritesi korunur).
 *
 * Sel önleme: öğrenci başına günde en çok N otomatik öneri (vars. 3);
 * aynı konuya bekleyen öneri veya önümüzdeki 7 günde planlı görev varsa
 * üretilmez; 14 gün dokunulmayan öneri 'expired' olur.
 *
 * Not (plandan sapma): edu_topic_id'ye ek custom_subject/custom_topic
 * kolonları eklendi — eski müfredat/manuel görevlerden türeyen öneriler de
 * taşınabilsin diye. Onay ekranı v1'de planlayıcı sekmesi yerine ayrı
 * sayfadır (koc/oneriler.php) — büyük JS dosyasına müdahale riski alınmadı.
 */

require_once __DIR__ . '/app_settings_lib.php';

function suggest_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_suggestions (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        student_id     INT NOT NULL,
        teacher_id     INT NOT NULL,
        source         ENUM('analiz','tekrar','risk','manuel') NOT NULL DEFAULT 'manuel',
        edu_topic_id   INT NULL,
        custom_subject VARCHAR(100) NULL,
        custom_topic   VARCHAR(200) NULL,
        action_type    ENUM('soru','konu','video') NOT NULL DEFAULT 'soru',
        amount         INT NOT NULL DEFAULT 20,
        reason         VARCHAR(255) NULL,
        suggested_date DATE NULL,
        status         ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        decided_at     DATETIME NULL,
        KEY idx_ts_teacher (teacher_id, status),
        KEY idx_ts_student (student_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

/** Öğrenci başına günlük otomatik öneri tavanı (app_settings: suggest_daily_cap). */
function suggest_daily_cap(PDO $pdo): int
{
    return max(1, (int)app_setting_get($pdo, 'suggest_daily_cap', '3'));
}

/**
 * Öneri oluşturur. Dönüş: ['ok'=>bool, 'id'|'skip'=>neden]
 * $draft: student_id, source, edu_topic_id?, custom_subject?, custom_topic?,
 *         action_type, amount, reason?, suggested_date?
 * Koruma sırası: aynı konuya bekleyen öneri → skip; önümüzdeki 7 günde aynı
 * konuya planlı görev → skip; otomatik kaynaklarda günlük tavan → skip.
 */
function suggest_create(PDO $pdo, int $teacherId, array $draft): array
{
    suggest_ensure_schema($pdo);
    $sid    = (int)($draft['student_id'] ?? 0);
    $source = in_array(($draft['source'] ?? ''), ['analiz','tekrar','risk','manuel'], true) ? $draft['source'] : 'manuel';
    $eduTid = !empty($draft['edu_topic_id']) ? (int)$draft['edu_topic_id'] : null;
    $csub   = mb_substr(trim((string)($draft['custom_subject'] ?? '')), 0, 100);
    $ctop   = mb_substr(trim((string)($draft['custom_topic'] ?? '')), 0, 200);
    $act    = in_array(($draft['action_type'] ?? ''), ['soru','konu','video'], true) ? $draft['action_type'] : 'soru';
    $amount = max(1, (int)($draft['amount'] ?? 20));
    $reason = mb_substr(trim((string)($draft['reason'] ?? '')), 0, 255);
    $sdate  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($draft['suggested_date'] ?? '')) ? $draft['suggested_date'] : null;

    if ($sid <= 0) return ['ok' => false, 'skip' => 'Öğrenci gerekli.'];
    if (!$eduTid && $csub === '' && $ctop === '') return ['ok' => false, 'skip' => 'Konu bilgisi gerekli.'];

    // Öğrenci bu koça bağlı mı?
    $chk = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
    $chk->execute([$teacherId, $sid]);
    if (!$chk->fetchColumn()) return ['ok' => false, 'skip' => 'Öğrenci size bağlı değil.'];

    // Aynı konuya bekleyen öneri var mı?
    if ($eduTid) {
        $q = $pdo->prepare("SELECT 1 FROM task_suggestions
                            WHERE student_id = ? AND edu_topic_id = ? AND status = 'pending' LIMIT 1");
        $q->execute([$sid, $eduTid]);
    } else {
        $q = $pdo->prepare("SELECT 1 FROM task_suggestions
                            WHERE student_id = ? AND custom_subject = ? AND custom_topic = ?
                              AND status = 'pending' LIMIT 1");
        $q->execute([$sid, $csub, $ctop]);
    }
    if ($q->fetchColumn()) return ['ok' => false, 'skip' => 'Aynı konuya bekleyen öneri zaten var.'];

    // Önümüzdeki 7 günde aynı konuya planlı görev var mı? (yalnız edu konu bilinirse)
    if ($eduTid) {
        $q = $pdo->prepare("SELECT 1 FROM schedule_items
                            WHERE student_id = ? AND edu_topic_id = ?
                              AND `date` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                            LIMIT 1");
        $q->execute([$sid, $eduTid]);
        if ($q->fetchColumn()) return ['ok' => false, 'skip' => 'Bu konu önümüzdeki 7 günde zaten planlı.'];
    }

    // Otomatik kaynaklarda (tekrar/risk) günlük tavan — koçun elle tetiklediği
    // analiz/manuel önerileri tavana takılmaz.
    if (in_array($source, ['tekrar', 'risk'], true)) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM task_suggestions
                            WHERE student_id = ? AND source IN ('tekrar','risk')
                              AND DATE(created_at) = CURDATE()");
        $q->execute([$sid]);
        if ((int)$q->fetchColumn() >= suggest_daily_cap($pdo)) {
            return ['ok' => false, 'skip' => 'Günlük öneri tavanı doldu.'];
        }
    }

    $ins = $pdo->prepare("INSERT INTO task_suggestions
        (student_id, teacher_id, source, edu_topic_id, custom_subject, custom_topic,
         action_type, amount, reason, suggested_date)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([$sid, $teacherId, $source, $eduTid, $csub ?: null, $ctop ?: null,
                   $act, $amount, $reason ?: null, $sdate]);
    return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
}

/** Koçun bekleyen önerileri (öğrenci + konu adlarıyla, en yeni önce). */
function suggest_pending_for_teacher(PDO $pdo, int $teacherId): array
{
    suggest_ensure_schema($pdo);
    $st = $pdo->prepare("
        SELECT ts.*, u.first_name, u.last_name,
               et.topic_name AS edu_topic_name, es.lesson_name AS edu_subject_name
        FROM task_suggestions ts
        JOIN users u ON u.id = ts.student_id
        LEFT JOIN education_topics   et ON et.id = ts.edu_topic_id
        LEFT JOIN education_subjects es ON es.id = et.subject_id
        WHERE ts.teacher_id = ? AND ts.status = 'pending'
        ORDER BY u.first_name, ts.created_at DESC");
    $st->execute([$teacherId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Bekleyen öneri sayısı (menü rozeti). */
function suggest_count_pending(PDO $pdo, int $teacherId): int
{
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM task_suggestions WHERE teacher_id = ? AND status = 'pending'");
        $st->execute([$teacherId]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/**
 * Öneriyi karara bağlar. Onayda schedule_items'a kopyalar (koc_paneli'deki
 * plan_apply ile aynı opsiyonel kolon tespiti). Dönüş: ['ok'=>bool, 'error'?].
 */
function suggest_decide(PDO $pdo, int $teacherId, int $suggestionId, bool $approve,
                        ?string $date = null, ?int $amount = null): array
{
    suggest_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT * FROM task_suggestions WHERE id = ? AND teacher_id = ? AND status = 'pending'");
    $st->execute([$suggestionId, $teacherId]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) return ['ok' => false, 'error' => 'Öneri bulunamadı veya zaten karara bağlandı.'];

    if (!$approve) {
        $pdo->prepare("UPDATE task_suggestions SET status='rejected', decided_at=NOW() WHERE id = ?")
            ->execute([$suggestionId]);
        return ['ok' => true];
    }

    $date = ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
        ? $date
        : ($s['suggested_date'] ?: date('Y-m-d', strtotime('+1 day')));
    $amount = max(1, (int)($amount ?? $s['amount']));

    // Opsiyonel kolon tespiti (edu_topic_id şeması sürüme göre değişebilir)
    $hasEdu = false;
    try { $hasEdu = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'edu_topic_id'")->rowCount() > 0; }
    catch (Throwable $e) {}

    $cols = ['student_id','date','amount','action_type','status','topic_id','custom_subject','custom_topic','item_order'];
    $vals = [(int)$s['student_id'], $date, $amount, $s['action_type'], 'bekliyor', null,
             (string)($s['custom_subject'] ?? ''), (string)($s['custom_topic'] ?? ''), 0];
    if ($hasEdu) { $cols[] = 'edu_topic_id'; $vals[] = $s['edu_topic_id'] ? (int)$s['edu_topic_id'] : null; }

    $pdo->beginTransaction();
    try {
        $qm = implode(', ', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO schedule_items (" . implode(', ', $cols) . ") VALUES ($qm)")->execute($vals);
        $pdo->prepare("UPDATE task_suggestions SET status='approved', decided_at=NOW(), suggested_date=? WHERE id = ?")
            ->execute([$date, $suggestionId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Görev eklenemedi: ' . $e->getMessage()];
    }
    return ['ok' => true];
}

/** 14 gün dokunulmayan bekleyen önerileri düşürür (gece cron tick'i). */
function suggest_expire(PDO $pdo): int
{
    try {
        suggest_ensure_schema($pdo);
        $st = $pdo->prepare("UPDATE task_suggestions SET status='expired', decided_at=NOW()
                             WHERE status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 14 DAY)");
        $st->execute();
        return $st->rowCount();
    } catch (Throwable $e) { return 0; }
}
