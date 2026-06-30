<?php
// Sadece yetkili yönetici önbellek temizleyebilir
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: index.php");
    exit;
}
opcache_reset();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Önbellek Temizleme</title>
</head>
<body style="font-family:Arial, sans-serif; text-align:center; padding-top:50px;">

    <p style="font-size:18px; color:green; font-weight:bold;">
        Sunucu önbelleği (OPcache) temizlendi!
    </p>

    <a href="index.php" style="
        display:inline-block;
        margin-top:15px;
        padding:10px 20px;
        background:#0D6EFD;
        color:#fff;
        text-decoration:none;
        border-radius:8px;
        font-weight:bold;
    ">
        Anasayfa
    </a>

</body>
</html>