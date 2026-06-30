<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// GÜVENLİK KONTROLÜ:
// Sadece 'is_superuser' değeri 1 olanlar girebilir.
// Normal öğretmenler buraya giremez.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_superuser']) || $_SESSION['is_superuser'] != 1) {
    // Yetkisiz giriş denemesi yapanı ana sayfaya at
    header("Location: ../index.php");
    exit;
}
?>
