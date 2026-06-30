<?php
/**
 * DERS PROS - KULLANICI OLARAK GİRİŞ (IMPERSONATION)
 * Veteran Developer Edition
 * * Bu modül, adminin seçilen kullanıcının yetkileriyle sisteme giriş yapmasını sağlar.
 */

session_start();
require_once '../db.php';

// 1. GÜVENLİK DUVARI: Sadece Admin ve Superuser bu dosyayı çalıştırabilir.
// Eğer normal bir kullanıcı bu URL'i denerse, hemen giriş sayfasına atılır.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

// 2. HEDEF KULLANICIYI AL
if (!isset($_GET['id'])) {
    die("Hata: Kullanıcı ID belirtilmedi.");
}

$target_user_id = intval($_GET['id']);

// 3. HEDEF KULLANICI BİLGİLERİNİ VERİTABANINDAN ÇEK
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Hata: Kullanıcı bulunamadı.");
}

// Kendi kendinize giriş yapmaya çalışıyorsanız uyaralım (Gerek yok ama temiz iş olsun)
if ($user['id'] == $_SESSION['user_id']) {
    echo "<script>alert('Zaten bu hesaptasınız!'); window.close();</script>";
    exit;
}

// 4. OTURUM DEĞİŞTİRME (SİHİR BURADA)
// Mevcut admin oturum verilerini temizlemiyoruz, üzerine yazıyoruz.
// İsterseniz buraya "gerçek_admin_id" diye bir session atıp "Admine Dön" butonu da yapılabilir
// ama şimdilik "Kullanıcı Olarak Giriş Yap" isteğine odaklanıyoruz.

// Session ID'yi güvenlik için yenile (Session Fixation koruması)
session_regenerate_id(true);

$_SESSION['user_id']      = $user['id'];
$_SESSION['username']     = $user['username'];
$_SESSION['role']         = $user['role'];
$_SESSION['first_name']   = $user['first_name'];
$_SESSION['last_name']    = $user['last_name'];
$_SESSION['school_level'] = $user['school_level']; // Öğrenciyse seviyesi önemli

// Admin olduğumuzu hatırlamak için bir iz bırakalım (İleride "Geri Dön" butonu yapmak istersen işe yarar)
$_SESSION['impersonated_by_admin'] = true;

// 5. YÖNLENDİRME
// Kullanıcının rolüne göre veya direkt ana sayfaya yönlendir.
header("Location: ../index.php");
exit;
?>
