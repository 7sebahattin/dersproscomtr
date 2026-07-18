<?php
/**
 * tags_lib.php — Öğrenci etiketleri (kohort) + toplu işlem geri alma
 * Plan: docs/plan-v1-secili-moduller.md §C3 (S6)
 *
 * Etiketler koça özeldir (teacher_id sahipli). Toplu plan uygulaması
 * (planlayıcıdaki "Başka Öğrencilere Uygula") her partiye ortak bulk_id
 * damgası vurur — randevulardaki grp_ deseninin aynısı. Geri alma yalnız
 * hâlâ 'bekliyor' durumundaki görevleri siler: öğrencinin dokunduğu
 * (yapildi/yarim/yapilmadi) görev asla geri alınmaz.
 */

function tags_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_tags (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        name       VARCHAR(50) NOT NULL,
        color      VARCHAR(7) NOT NULL DEFAULT '#223488',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tag (teacher_id, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_tag_map (
        tag_id     INT NOT NULL,
        student_id INT NOT NULL,
        PRIMARY KEY (tag_id, student_id),
        KEY idx_stm_student (student_id),
        CONSTRAINT fk_stm_tag FOREIGN KEY (tag_id)
            REFERENCES student_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    // Toplu işlem damgası (geri alma için) — idempotent kolon yükseltmesi
    try {
        if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'bulk_id'")->rowCount() === 0) {
            $pdo->exec("ALTER TABLE schedule_items ADD COLUMN bulk_id VARCHAR(40) NULL DEFAULT NULL");
            $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_bulk (bulk_id)");
        }
    } catch (Throwable $e) {}
    $done = true;
}

/** Koçun etiketleri, üye id listesi ve sayısıyla. */
function tags_for_teacher(PDO $pdo, int $teacherId): array
{
    tags_ensure_schema($pdo);
    $tags = [];
    $st = $pdo->prepare("SELECT * FROM student_tags WHERE teacher_id = ? ORDER BY name");
    $st->execute([$teacherId]);
    foreach ($st as $t) { $t['members'] = []; $tags[(int)$t['id']] = $t; }
    if (!$tags) return [];

    $in = implode(',', array_map('intval', array_keys($tags)));
    foreach ($pdo->query("SELECT tag_id, student_id FROM student_tag_map WHERE tag_id IN ($in)") as $m) {
        $tags[(int)$m['tag_id']]['members'][] = (int)$m['student_id'];
    }
    return array_values($tags);
}

function tags_create(PDO $pdo, int $teacherId, string $name, string $color): array
{
    tags_ensure_schema($pdo);
    $name = mb_substr(trim($name), 0, 50);
    if ($name === '') return ['ok' => false, 'error' => 'Etiket adı gerekli.'];
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#223488';
    try {
        $pdo->prepare("INSERT INTO student_tags (teacher_id, name, color) VALUES (?,?,?)")
            ->execute([$teacherId, $name, $color]);
        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Bu adla etiket zaten var.'];
    }
}

function tags_delete(PDO $pdo, int $teacherId, int $tagId): bool
{
    tags_ensure_schema($pdo);
    $st = $pdo->prepare("DELETE FROM student_tags WHERE id = ? AND teacher_id = ?");
    $st->execute([$tagId, $teacherId]);
    return (bool)$st->rowCount(); // üyelikler FK CASCADE ile temizlenir
}

/** Etiket üyelerini topluca değiştirir (yalnız koçun kendi öğrencileri kabul edilir). */
function tags_set_members(PDO $pdo, int $teacherId, int $tagId, array $studentIds): array
{
    tags_ensure_schema($pdo);
    $chk = $pdo->prepare("SELECT 1 FROM student_tags WHERE id = ? AND teacher_id = ?");
    $chk->execute([$tagId, $teacherId]);
    if (!$chk->fetchColumn()) return ['ok' => false, 'error' => 'Etiket bulunamadı.'];

    $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn($v) => $v > 0)));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $own = $pdo->prepare("SELECT student_id FROM coaching_relationships
                              WHERE teacher_id = ? AND student_id IN ($in)");
        $own->execute(array_merge([$teacherId], $ids));
        $ids = array_map('intval', $own->fetchAll(PDO::FETCH_COLUMN));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM student_tag_map WHERE tag_id = ?")->execute([$tagId]);
        if ($ids) {
            $ins = $pdo->prepare("INSERT IGNORE INTO student_tag_map (tag_id, student_id) VALUES (?,?)");
            foreach ($ids as $sid) $ins->execute([$tagId, $sid]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Üyelik kaydedilemedi.'];
    }
    return ['ok' => true, 'count' => count($ids)];
}

/**
 * Koçun son toplu işlemleri: parti başına görev/öğrenci sayısı, tarih
 * aralığı, kaç görevin hâlâ geri alınabilir (bekliyor) olduğu.
 */
function tags_recent_bulks(PDO $pdo, int $teacherId, int $limit = 10): array
{
    tags_ensure_schema($pdo);
    try {
        $st = $pdo->prepare("
            SELECT si.bulk_id,
                   COUNT(*)                                    total,
                   COUNT(DISTINCT si.student_id)               students,
                   MIN(si.`date`) d1, MAX(si.`date`) d2,
                   SUM(si.status = 'bekliyor')                 undoable,
                   MAX(si.id)                                  last_id
            FROM schedule_items si
            JOIN coaching_relationships cr
              ON cr.student_id = si.student_id AND cr.teacher_id = ?
            WHERE si.bulk_id IS NOT NULL
            GROUP BY si.bulk_id
            ORDER BY last_id DESC
            LIMIT " . (int)$limit);
        $st->execute([$teacherId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

/**
 * Toplu işlemi geri alır: partide hâlâ 'bekliyor' olan görevler silinir.
 * Öğrencinin işaretlediği görevlere dokunulmaz. Dönüş: silinen adet.
 */
function tags_undo_bulk(PDO $pdo, int $teacherId, string $bulkId): int
{
    tags_ensure_schema($pdo);
    if (!preg_match('/^blk_[a-z0-9.]+$/i', $bulkId)) return 0;
    $st = $pdo->prepare("
        DELETE si FROM schedule_items si
        JOIN coaching_relationships cr
          ON cr.student_id = si.student_id AND cr.teacher_id = ?
        WHERE si.bulk_id = ? AND si.status = 'bekliyor'");
    $st->execute([$teacherId, $bulkId]);
    return $st->rowCount();
}
