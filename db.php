<?php
// Bu dosya sitenin veritabanına bağlanmasını sağlar.

// Tek gün tanımı: PHP tarafı da MySQL (+03:00) ile aynı saat diliminde çalışır.
// Streak, metrik ve cron'lardaki tüm date() çağrıları İstanbul gününü kullanır.
date_default_timezone_set('Europe/Istanbul');

// =============================================
// BASE URL AYARI
// Canlı sunucu (ana dizin) için: ''
// =============================================
define('BASE_URL', '');

require_once __DIR__ . '/secrets.php';

// secrets.php içinde DB_HOST/DB_NAME/DB_USER tanımlanmazsa (canlı sunucudaki
// mevcut secrets.php gibi) mevcut değerler kullanılır — geriye dönük uyumlu.
// Test ortamı (staging), kendi secrets.php dosyasında bunları farklı
// tanımlayarak ayrı bir veritabanına bağlanabilir.
$host     = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbname   = defined('DB_NAME') ? DB_NAME : 'derspros_db';
$username = defined('DB_USER') ? DB_USER : 'derspros_sebo';
$password = DB_PASSWORD;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Türkçe karakterlerin (ğ, ş, ı, ç) bozulmaması için garanti komutlar:
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARSET 'utf8mb4'");
    $pdo->exec("SET COLLATION_CONNECTION = 'utf8mb4_turkish_ci'");
    $pdo->exec("SET time_zone = '+03:00'"); // Türkiye saati (UTC+3)

} catch (PDOException $e) {
    header('Content-Type: text/html; charset=utf-8');
    die("Bağlantı hatası: " . $e->getMessage());
}
?>
