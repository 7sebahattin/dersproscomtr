<?php
// /student/logout.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session içini boşalt
$_SESSION = [];

// Session cookie varsa temizle
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

// Session’ı öldür
session_destroy();

// Login’e gönder
header("Location: " . (defined('BASE_URL') ? BASE_URL : '') . "/login.php");
exit;
