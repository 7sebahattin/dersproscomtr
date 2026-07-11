<?php
// save_temp_pdf.php — Geçici PDF kaydet, 48 saat sonra sil
require_once 'db.php';
// Oturumu diğer AJAX uçlarıyla (ajax/push_*.php, header.php) AYNI parametrelerle
// başlat — çerez yolu farkında oturum bulunamayıp "Yetkisiz erişim" dönmesin.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Yetki kontrolü
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'superuser'])) {
    echo json_encode(['ok' => false, 'error' => 'Yetkisiz erişim.']);
    exit;
}

$dir = __DIR__ . '/temp_pdfs/';

// Klasör yoksa oluştur
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    // Dizin listelemesini engelle
    file_put_contents($dir . 'index.php', '<?php // no direct access');
}

// 48 saat = 172800 saniye — eski PDF'leri temizle
foreach (glob($dir . '*.pdf') as $oldFile) {
    if (filemtime($oldFile) < time() - 172800) {
        @unlink($oldFile);
    }
}

// Yükleme kontrolü
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'Dosya yüklenemedi. Hata kodu: ' . ($_FILES['pdf']['error'] ?? 'yok')]);
    exit;
}

$mime = mime_content_type($_FILES['pdf']['tmp_name']);
if ($mime !== 'application/pdf') {
    echo json_encode(['ok' => false, 'error' => 'Geçersiz dosya türü.']);
    exit;
}

if ($_FILES['pdf']['size'] > 15 * 1024 * 1024) { // 15 MB limit
    echo json_encode(['ok' => false, 'error' => 'Dosya çok büyük (max 15 MB).']);
    exit;
}

// Rastgele dosya adı — tahmin edilemez
$filename = bin2hex(random_bytes(16)) . '.pdf';
$dest = $dir . $filename;

if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'error' => 'Dosya sunucuya kaydedilemedi.']);
    exit;
}

// Tam URL oluştur
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$url      = $protocol . '://' . $host . BASE_URL . '/temp_pdfs/' . $filename;

echo json_encode(['ok' => true, 'url' => $url]);
