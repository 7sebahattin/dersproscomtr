<?php
/**
 * notes_lib.php — Yapılandırılmış seans/görüşme notları
 * Plan: docs/plan-v1-secili-moduller.md §C4 (S7)
 *
 * Öğrenci başına zaman çizgili görüşme kaydı: ne konuşuldu, ne kararlaştırıldı,
 * seans sonrası ödev, bir sonraki adım. Yeni not formu bir önceki notun
 * next_step'ini gündem olarak taşır — süreklilik kendiliğinden oluşur.
 *
 * Gizlilik: varsayılan 'private' (yalnız koç görür). 'student' seçilirse
 * öğrenci ana sayfasındaki "Koçundan" kartında görünür; 'parent' veli
 * panelinde (yalnız parent_relationships kaydı olan veliye) görünür.
 */

function notes_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS session_notes (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id     INT NOT NULL,
        student_id     INT NOT NULL,
        appointment_id INT NULL,
        note_date      DATE NOT NULL,
        discussed      TEXT NULL,
        decisions      TEXT NULL,
        homework       TEXT NULL,
        next_step      VARCHAR(255) NULL,
        visibility     ENUM('private','student','parent') NOT NULL DEFAULT 'private',
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_sn_student (student_id, note_date),
        KEY idx_sn_teacher (teacher_id, note_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
    $done = true;
}

function notes_save(PDO $pdo, int $teacherId, array $d): array
{
    notes_ensure_schema($pdo);
    $sid = (int)($d['student_id'] ?? 0);
    if ($sid <= 0) return ['ok' => false, 'error' => 'Öğrenci gerekli.'];

    // Öğrenci bu koça bağlı olmalı
    $chk = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
    $chk->execute([$teacherId, $sid]);
    if (!$chk->fetchColumn()) return ['ok' => false, 'error' => 'Öğrenci size bağlı değil.'];

    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['note_date'] ?? '')) ? $d['note_date'] : date('Y-m-d');
    $vis  = in_array(($d['visibility'] ?? ''), ['private', 'student', 'parent'], true) ? $d['visibility'] : 'private';

    $fields = [
        'discussed' => mb_substr(trim((string)($d['discussed'] ?? '')), 0, 5000),
        'decisions' => mb_substr(trim((string)($d['decisions'] ?? '')), 0, 5000),
        'homework'  => mb_substr(trim((string)($d['homework'] ?? '')), 0, 5000),
        'next_step' => mb_substr(trim((string)($d['next_step'] ?? '')), 0, 255),
    ];
    if ($fields['discussed'] === '' && $fields['decisions'] === ''
        && $fields['homework'] === '' && $fields['next_step'] === '') {
        return ['ok' => false, 'error' => 'En az bir alan doldurulmalı.'];
    }

    $noteId = (int)($d['note_id'] ?? 0);
    if ($noteId > 0) {
        // Güncelleme: yalnız kendi notu
        $st = $pdo->prepare("UPDATE session_notes
            SET note_date=?, discussed=?, decisions=?, homework=?, next_step=?, visibility=?
            WHERE id=? AND teacher_id=?");
        $st->execute([$date, $fields['discussed'] ?: null, $fields['decisions'] ?: null,
                      $fields['homework'] ?: null, $fields['next_step'] ?: null, $vis, $noteId, $teacherId]);
        return ['ok' => (bool)$st->rowCount() || true];
    }

    $pdo->prepare("INSERT INTO session_notes
        (teacher_id, student_id, appointment_id, note_date, discussed, decisions, homework, next_step, visibility)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$teacherId, $sid,
                   !empty($d['appointment_id']) ? (int)$d['appointment_id'] : null,
                   $date, $fields['discussed'] ?: null, $fields['decisions'] ?: null,
                   $fields['homework'] ?: null, $fields['next_step'] ?: null, $vis]);
    return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
}

function notes_delete(PDO $pdo, int $teacherId, int $noteId): bool
{
    notes_ensure_schema($pdo);
    $st = $pdo->prepare("DELETE FROM session_notes WHERE id = ? AND teacher_id = ?");
    $st->execute([$noteId, $teacherId]);
    return (bool)$st->rowCount();
}

/** Öğrencinin zaman çizgisi (koç görünümü — tüm görünürlükler). */
function notes_for_student(PDO $pdo, int $teacherId, int $studentId, int $limit = 50): array
{
    notes_ensure_schema($pdo);
    $st = $pdo->prepare("SELECT * FROM session_notes
                         WHERE teacher_id = ? AND student_id = ?
                         ORDER BY note_date DESC, id DESC LIMIT " . (int)$limit);
    $st->execute([$teacherId, $studentId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Son notun next_step'i — yeni not formunun gündem satırı. */
function notes_last_next_step(PDO $pdo, int $teacherId, int $studentId): ?string
{
    try {
        $st = $pdo->prepare("SELECT next_step FROM session_notes
                             WHERE teacher_id = ? AND student_id = ? AND next_step IS NOT NULL AND next_step <> ''
                             ORDER BY note_date DESC, id DESC LIMIT 1");
        $st->execute([$teacherId, $studentId]);
        $v = $st->fetchColumn();
        return $v !== false ? (string)$v : null;
    } catch (Throwable $e) { return null; }
}

/**
 * Öğrenciye görünür son not ("Koçundan" kartı).
 * 'student' VE 'parent' görünürlüklü notlar öğrenciye açıktır
 * (veliyle paylaşılan bir not öğrenciden gizlenmez — sürpriz yaratmaz).
 */
function notes_latest_for_student_card(PDO $pdo, int $studentId): ?array
{
    try {
        notes_ensure_schema($pdo);
        $st = $pdo->prepare("SELECT sn.*, u.first_name AS coach_name
                             FROM session_notes sn
                             JOIN users u ON u.id = sn.teacher_id
                             WHERE sn.student_id = ? AND sn.visibility IN ('student','parent')
                             ORDER BY sn.note_date DESC, sn.id DESC LIMIT 1");
        $st->execute([$studentId]);
        $n = $st->fetch(PDO::FETCH_ASSOC);
        return $n ?: null;
    } catch (Throwable $e) { return null; }
}
