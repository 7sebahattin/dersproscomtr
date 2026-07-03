<?php
/**
 * koc/dekont.php — Yetkili dekont (ödeme makbuzu) görüntüleme proxy'si
 *
 * Dekontlar mali/kişisel veri olduğundan doğrudan URL ile erişilemez
 * (.htaccess uploads/receipts'i engeller). Bu dosya yalnızca ödemenin
 * sahibi öğretmene, veritabanı kaydı üzerinden dosyayı sunar.
 */

require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    exit('Erişim engellendi.');
}
$teacher_id = (int)$_SESSION['user_id'];
$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid <= 0) { http_response_code(400); exit('Geçersiz istek.'); }

// Yalnızca ödemenin sahibi öğretmen erişebilir
$stmt = $pdo->prepare("SELECT receipt_path FROM payments WHERE id = ? AND teacher_id = ?");
$stmt->execute([$pid, $teacher_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || empty($row['receipt_path'])) { http_response_code(404); exit('Dekont bulunamadı.'); }

// Güvenlik: yalnızca uploads/receipts altındaki gerçek dosyaya izin ver (path traversal engeli)
$real = realpath(__DIR__ . '/../' . $row['receipt_path']);
$base = realpath(__DIR__ . '/../uploads/receipts');
if ($real === false || $base === false
    || strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0
    || !is_file($real)) {
    http_response_code(404);
    exit('Dekont bulunamadı.');
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('Content-Disposition: inline; filename="dekont.' . $ext . '"');
header('Cache-Control: private, max-age=0, no-cache');
readfile($real);
exit;
