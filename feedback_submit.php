<?php
// feedback_submit.php - Geri Bildirim AJAX Handler
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

// Sadece giriş yapmış öğretmenler
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek.']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$user_email = $_SESSION['email'] ?? '';

$allowed_cats = ['hata_bildir', 'gorusoner', 'sikayet', 'diger'];
$category = in_array($_POST['category'] ?? '', $allowed_cats) ? $_POST['category'] : 'hata_bildir';
$subject  = trim(substr($_POST['subject']  ?? '', 0, 255));
$message  = trim($_POST['message'] ?? '');

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Konu ve açıklama alanları zorunludur.']);
    exit;
}

// E-posta çek (session'da yoksa db'den)
if (!$user_email) {
    try {
        $s = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $s->execute([$user_id]);
        $user_email = $s->fetchColumn() ?: '';
    } catch (Exception $e) {}
}

try {
    $ins = $pdo->prepare("INSERT INTO feedback (user_id, user_name, user_email, category, subject, message) VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$user_id, $user_name, $user_email, $category, $subject, $message]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Veritabanı hatası: ' . $e->getMessage()]);
}
