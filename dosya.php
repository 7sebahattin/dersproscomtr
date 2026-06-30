<?php
/**
 * dosya.php — Yetkili sınav PDF indirme/görüntüleme proxy'si
 *
 * Sınav PDF'leri artık doğrudan URL ile erişilemez (.htaccess engelliyor).
 * Bu dosya, yalnızca giriş yapmış kullanıcılara, veritabanındaki kayıt
 * üzerinden dosyayı sunar. Böylece sınav PDF'leri (öğrenci kişisel verisi)
 * dışarıdan tahminle indirilemez.
 */

require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Sadece giriş yapmış kullanıcılar erişebilir
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Erişim engellendi. Lütfen giriş yapın.');
}

// 2. Geçerli sınav ID'si
$exam_id = isset($_GET['exam']) ? (int) $_GET['exam'] : 0;
if ($exam_id <= 0) {
    http_response_code(400);
    exit('Geçersiz istek.');
}

// 3. Dosya yolunu veritabanından al
$stmt = $pdo->prepare("SELECT file_path FROM teacher_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$row = $stmt->fetch();
if (!$row || empty($row['file_path'])) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

// 4. Güvenlik: yalnızca uploads/exams altındaki gerçek bir dosyaya izin ver
//    (path traversal / dizin dışına çıkma engellenir)
$real = realpath(__DIR__ . '/' . $row['file_path']);
$base = realpath(__DIR__ . '/uploads/exams');
if ($real === false || $base === false
    || strncmp($real, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0
    || !is_file($real)) {
    http_response_code(404);
    exit('Dosya bulunamadı.');
}

// 5. PDF'i tarayıcıda göster (object/iframe içinde de çalışır)
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="sinav.pdf"');
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($real);
exit;
