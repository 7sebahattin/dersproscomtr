<?php
// Bu dosya sitenin veritabanına bağlanmasını sağlar.

// =============================================
// BASE URL AYARI
// Canlı sunucu (ana dizin) için: ''
// =============================================
define('BASE_URL', '');

require_once __DIR__ . '/secrets.php';

$host = 'localhost';
$dbname = 'derspros_db';
$username = 'derspros_sebo';
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
