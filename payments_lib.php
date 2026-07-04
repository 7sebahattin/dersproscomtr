<?php
/**
 * payments_lib.php — Ödeme yardımcıları (randevu -> ödeme köprüsü)
 *
 * İş kuralı: Bir randevunun GÜNÜ GEÇİNCE ve iptal edilmemişse, seans yapılmış
 * sayılır ve payments tablosuna 'odenmedi' (borç) olarak eklenir. Ödeme olarak
 * İŞARETLEMEZ — bunu yalnızca öğretmen "Ödendi" diyerek yapar (isteğe bağlı dekont ile).
 *
 * Eski ödeme sistemi ve mevcut kolonlar KORUNUR; yalnızca additive kolon eklenir.
 */

/** payments tablosuna gerekli additive kolonları idempotent ekler. */
function payments_ensure_schema(PDO $pdo): void
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('appointment_id', $cols, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN appointment_id INT NULL DEFAULT NULL");
            try { $pdo->exec("ALTER TABLE payments ADD KEY idx_pay_appt (appointment_id)"); } catch (Throwable $e) {}
        }
        if (!in_array('note', $cols, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN note VARCHAR(255) NULL DEFAULT NULL");
        }
        if (!in_array('receipt_path', $cols, true)) {
            $pdo->exec("ALTER TABLE payments ADD COLUMN receipt_path VARCHAR(255) NULL DEFAULT NULL");
        }
    } catch (Throwable $e) { /* yetki yoksa sessiz geç */ }
}

/**
 * Günü geçmiş, iptal edilmemiş randevular için 'odenmedi' borç kaydı üretir.
 * Ödeme olarak İŞARETLEMEZ. $teacherId null ise tüm öğretmenler (cron) için çalışır.
 * @return int üretilen kayıt sayısı
 */
function payments_generate_due(PDO $pdo, ?int $teacherId = null): int
{
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
        $hasApptCol = in_array('appointment_id', $cols, true);

        $where  = "a.appointment_date < CURDATE() AND a.status <> 'cancelled' AND cr.lesson_price > 0";
        $params = [];
        if ($teacherId) { $where .= " AND a.teacher_id = ?"; $params[] = $teacherId; }

        // Aynı randevu için ikinci kez ödeme üretme (appointment_id varsa onunla, yoksa tarihle)
        if ($hasApptCol) {
            $dedup = "NOT EXISTS (SELECT 1 FROM payments p WHERE p.appointment_id = a.id)
                      AND NOT EXISTS (SELECT 1 FROM payments p
                                      WHERE p.appointment_id IS NULL AND p.student_id = a.student_id
                                        AND p.teacher_id = a.teacher_id AND DATE(p.due_date) = DATE(a.appointment_date))";
        } else {
            $dedup = "NOT EXISTS (SELECT 1 FROM payments p
                                  WHERE p.student_id = a.student_id AND p.teacher_id = a.teacher_id
                                    AND DATE(p.due_date) = DATE(a.appointment_date))";
        }

        $sql = "SELECT a.id, a.student_id, a.teacher_id, a.appointment_date, cr.lesson_price
                FROM appointments a
                JOIN coaching_relationships cr ON (a.student_id = cr.student_id AND a.teacher_id = cr.teacher_id)
                WHERE $where AND $dedup";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) return 0;

        $insCols = "student_id, teacher_id, amount, description, due_date, status";
        $insVals = "?, ?, ?, ?, ?, 'odenmedi'";
        if ($hasApptCol) { $insCols .= ", appointment_id"; $insVals .= ", ?"; }
        $ins = $pdo->prepare("INSERT INTO payments ($insCols) VALUES ($insVals)");

        $n = 0;
        foreach ($rows as $r) {
            $vals = [
                (int)$r['student_id'], (int)$r['teacher_id'], (float)$r['lesson_price'],
                date('d.m.Y', strtotime($r['appointment_date'])) . ' Tarihli Seans',
                $r['appointment_date'],
            ];
            if ($hasApptCol) $vals[] = (int)$r['id'];
            $ins->execute($vals);
            $n++;
        }
        return $n;
    } catch (Throwable $e) {
        return 0;
    }
}

/** Telefonu WhatsApp (wa.me) için uluslararası formata çevirir. TR varsayımı. */
function wa_phone(?string $raw): string
{
    $d = preg_replace('/\D+/', '', (string)$raw);
    if ($d === '') return '';
    if (strpos($d, '90') === 0 && strlen($d) >= 12) return $d;     // zaten 90...
    $d = ltrim($d, '0');                                            // baştaki 0'ları at
    if (strlen($d) === 10 && $d[0] === '5') return '90' . $d;       // TR cep: 5XXXXXXXXX
    return $d;                                                      // aksi halde olduğu gibi
}

/** Öğretmen bu ödeme kaydının sahibi mi? (IDOR koruması) */
function payment_owned_by(PDO $pdo, int $paymentId, int $teacherId): bool
{
    try {
        $st = $pdo->prepare("SELECT 1 FROM payments WHERE id = ? AND teacher_id = ? LIMIT 1");
        $st->execute([$paymentId, $teacherId]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}
