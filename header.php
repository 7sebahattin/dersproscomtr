<?php
// Header.php - V6: Payment Link Added (Desktop & Mobile)

// Türkiye saati
date_default_timezone_set('Europe/Istanbul');

// 1. OTURUM AYARLARI (Session her yerde geçerli olsun)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/');
    session_start();
}

// Sunucu/CDN/tarayıcı önbelleğini devre dışı bırak — deploy sonrası değişikliklerin
// (header/footer dahil) eski önbellek yüzünden gecikmeli görünmesini önler.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// 2. DB BAĞLANTISI (Mutlak yol ile hata önleme)
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}

// 2b. PSEUDO-CRON — cron_notifications.php'yi arka planda tetikle
// Sunucuda crontab izni olmayan hostingler için alternatif yöntem.
// Damga GLOBAL (dosya tabanlı): eskiden oturum bazlıydı; trafiğin az olduğu
// saatlerde hiç çalışmıyor, yoğun saatlerde her oturum ayrı tetikliyordu.
// Şimdi hangi kullanıcı gelirse gelsin 4 dakikada en fazla bir kez, flock
// kilidiyle eşzamanlı çift çalıştırmaya karşı korunarak çalışır.
if (isset($_SESSION['user_id'])) {
    $pseudoCronStamp = __DIR__ . '/.pseudo_cron_stamp';
    $pseudoCronLock  = __DIR__ . '/.pseudo_cron_lock';
    $cronInterval = 4 * 60; // 4 dk — saat başlarını kaçırmamak için 5'ten kısa
    $lastRun = (int)@file_get_contents($pseudoCronStamp);
    if ((time() - $lastRun) >= $cronInterval) {
        $cronFile = __DIR__ . '/cron_notifications.php';
        if (file_exists($cronFile)) {
            // Sayfa yüklemesini bloklamadan, çıktıyı SAYFAYA SIZDIRMADAN arka planda çalıştır.
            register_shutdown_function(function() use ($cronFile, $pseudoCronStamp, $pseudoCronLock, $cronInterval) {
                // Yanıtı kullanıcıya kapat (FastCGI veya LiteSpeed)
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    litespeed_finish_request();
                }
                // Kilidi al — alamazsak başka bir istek zaten çalıştırıyor demektir
                $fp = @fopen($pseudoCronLock, 'c');
                if (!$fp) return;
                if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return; }
                // Kilit beklerken başka istek damgayı tazelemiş olabilir: yeniden kontrol
                if ((time() - (int)@file_get_contents($pseudoCronStamp)) < $cronInterval) {
                    flock($fp, LOCK_UN); fclose($fp); return;
                }
                @file_put_contents($pseudoCronStamp, (string)time());
                if (!defined('CRON_RUN')) define('CRON_RUN', true);
                // $pdo bağlantısını cron'a aktar (shutdown kapsamında global'den al)
                global $pdo;
                // Cron'un log çıktısı sayfaya düşmesin diye tampona al ve at
                ob_start();
                try {
                    include $cronFile;
                } catch (Throwable $e) {
                    // Sessizce yut — arka plan görevi sayfayı etkilemesin
                }
                ob_end_clean();
                flock($fp, LOCK_UN);
                fclose($fp);
            });
        }
    }
}

// 2c. SON GÖRÜLME — "bugün sisteme girdi mi" tespiti gerçek aktiviteye dayansın.
// Oturumu günlerce açık kalan öğrenci yeniden şifre girmediği için last_login_at
// eski kalıyor ve haksız yere "hiç girmedin" (Durum A) bildirimi alıyordu.
// Girişli her kullanıcı için en fazla 30 dakikada bir tazelenir.
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $lastSeenPing = (int)($_SESSION['last_seen_ping'] ?? 0);
    if ((time() - $lastSeenPing) >= 1800) {
        $_SESSION['last_seen_ping'] = time();
        try {
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                ->execute([(int)$_SESSION['user_id']]);
        } catch (Throwable $e) { /* kolon yoksa sessiz geç */ }
    }
}

// 3. BASE_URL kontrolü (db.php yüklenemezse bile çalışsın)
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
$B = BASE_URL; // Kısa kullanım için

// Badge sayaçları
$pendingReqCount  = 0;
$unreadMsgCount   = 0;
$liveChatUnread   = 0;
$unreadPushCount  = 0; // Okunmamış push bildirimi
$canliDersLink = "$B/canli_dersler.php";

// ROL VE LİNK MANTIĞI
$myRole = $_SESSION['role'] ?? 'guest';
$isLoggedIn = isset($_SESSION['user_id']);

// Varsayılan Linkler
$linkKocluk = "$B/login.php";
$linkRandevu = "$B/login.php"; 

try {
    if ($isLoggedIn && isset($pdo) && $pdo instanceof PDO) {
        $uid  = (int)$_SESSION['user_id'];
        
        // --- LİNKLERİ BURADA DÜZELTİYORUZ ---
        if ($myRole === 'teacher') {
            $canliDersLink = "$B/online_dersler.php";
            $linkKocluk = "$B/koc_paneli.php";
            $linkRandevu = "$B/koc/randevu.php";
        } elseif ($myRole === 'student') {
            $linkKocluk = "$B/kocluk.php";
            $linkRandevu = "$B/student/randevu.php";
        } elseif ($myRole === 'parent') {
            $linkKocluk = "$B/student/kocluk.php";
            $linkRandevu = "$B/student/randevu.php";
        } else {
             $linkKocluk = "$B/kocluk.php";
             $linkRandevu = "$B/randevu.php";
        }

        // --- Profil Resmi ---
        $profilePic = null;
        try {
            $ppStmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $ppStmt->execute([$uid]);
            $ppRow = $ppStmt->fetch(PDO::FETCH_ASSOC);
            if ($ppRow && !empty($ppRow['profile_pic'])) {
                $profilePic = $ppRow['profile_pic'];
            }
        } catch (Throwable $e) {}

        // --- Bildirim Sorguları ---
        $lcStmt = $pdo->prepare("SELECT COUNT(*) FROM live_chat_messages WHERE receiver_id = ? AND is_read = 0");
        $lcStmt->execute([$uid]);
        $liveChatUnread = (int)$lcStmt->fetchColumn();

        if ($myRole === 'student') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM appointment_requests r JOIN appointments a ON a.id = r.appointment_id WHERE a.student_id = ? AND r.status = 'pending'");
            $st->execute([$uid]);
            $pendingReqCount = (int)$st->fetchColumn();

            $stMsg = $pdo->prepare("SELECT COUNT(*) FROM appointment_messages m JOIN appointments a ON a.id = m.appointment_id WHERE a.student_id = ? AND m.sender_role = 'teacher' AND m.is_read_by_student = 0");
            $stMsg->execute([$uid]);
            $unreadMsgCount = (int)$stMsg->fetchColumn();

            // Okunmamış push bildirimi sayısı
            try {
                $pushBadge = $pdo->prepare("SELECT COUNT(*) FROM push_notification_log WHERE student_id=? AND is_read=0");
                $pushBadge->execute([$uid]);
                $unreadPushCount = (int)$pushBadge->fetchColumn();
            } catch (Throwable $e) { $unreadPushCount = 0; }

        } elseif ($myRole === 'parent') {
            // Veli sorguları...
        } elseif ($myRole === 'teacher') {
            $stMsg = $pdo->prepare("SELECT COUNT(*) FROM appointment_messages m JOIN appointments a ON a.id = m.appointment_id WHERE a.teacher_id = ? AND m.sender_role != 'teacher' AND m.is_read_by_teacher = 0");
            $stMsg->execute([$uid]);
            $unreadMsgCount = (int)$stMsg->fetchColumn();
        }
    }
} catch (Throwable $e) {
    // Hata olursa sessiz kal
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>DersPROS | Yeni Nesil Eğitim Platformu</title>
    <link rel="icon" type="image/png" href="<?php echo $B; ?>/assets/images/icon-192.png?v=2">

    <!-- PWA: Ana ekrana ekleme / uygulama hissi -->
    <link rel="manifest" href="<?php echo $B; ?>/manifest.json?v=2">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <!-- iOS için ayrı destek (Android manifest'i kullanmaz) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DersPROS">
    <link rel="apple-touch-icon" href="<?php echo $B; ?>/assets/images/apple-touch-icon.png?v=2">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="bg-indigo-50 text-slate-700 flex flex-col min-h-screen <?php echo $isLoggedIn ? 'pb-20' : ''; ?> lg:pb-0"> 

<nav class="bg-white/95 backdrop-blur-md sticky top-0 z-50 shadow-sm border-b border-indigo-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 md:h-20 items-center">

            <a href="<?php echo $B; ?>/index.php" class="flex items-center gap-2 group flex-shrink-0">
                <div class="h-9 w-9 md:h-12 md:w-12 rounded-full flex items-center justify-center shadow-sm border border-slate-50 overflow-hidden transition group-hover:scale-105">
                    <img src="<?php echo $B; ?>/assets/images/icon-192.png?v=2" alt="DersPROS" class="w-full h-full object-cover">
                </div>
                <span class="text-lg md:text-xl font-bold text-slate-800 tracking-tight flex items-center">
                    Ders<span class="text-red-600 ml-0.5">PROS</span>
                </span>
            </a>

            <div class="hidden lg:flex items-center space-x-1 text-sm font-medium">
                <a href="<?php echo $B; ?>/index.php" class="text-slate-600 hover:text-blue-600 transition px-3 py-2 rounded-lg hover:bg-slate-50">🏠 Anasayfa</a>
                <a href="<?php echo $B; ?>/denemeler.php" class="text-slate-600 hover:text-blue-600 transition px-3 py-2 rounded-lg hover:bg-slate-50">📝 Denemeler</a>
                
                <?php if (false): // Geçici olarak gizlendi ?>
    <a href="<?php echo $canliDersLink; ?>" class="text-slate-600 hover:text-blue-600 transition px-3 py-2 rounded-lg hover:bg-slate-50 relative">
        🔴 Özel Ders
        <?php if ($liveChatUnread > 0): ?>
            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[9px] text-white font-bold animate-pulse"><?php echo $liveChatUnread; ?></span>
        <?php endif; ?>
    </a>
<?php endif; ?>

                <?php if ($isLoggedIn && $myRole === 'teacher'): ?>
                    <a href="<?php echo $B; ?>/koc/mufredat_v2.php" class="text-slate-600 hover:text-blue-600 transition px-3 py-2 rounded-lg hover:bg-slate-50">📚 Müfredat Yükle</a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <a href="<?php echo $linkRandevu; ?>" class="text-slate-600 hover:text-blue-600 px-3 py-2 rounded-lg hover:bg-slate-50 relative">📅 Randevu</a>
                    <a href="<?php echo $linkKocluk; ?>" class="relative inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg shadow-md transition ml-2 font-bold">
                        <svg class="w-5 h-5 drop-shadow-[0_0_6px_rgba(236,151,49,1)]" style="fill:#ec9731;color:#ec9731" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        <span class="text-white">Program</span>
                    </a>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <div class="relative group ml-4">
                        <button class="flex items-center gap-2 focus:outline-none relative">
                            <?php if (!empty($profilePic)): ?>
                                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profil" class="w-10 h-10 rounded-full object-cover border-2 border-indigo-200 shadow-sm">
                            <?php else: ?>
                                <img src="<?php echo $B; ?>/assets/images/favicon.png" alt="Profil" class="w-10 h-10 rounded-full object-cover border-2 border-indigo-200 shadow-sm bg-indigo-50 p-1">
                            <?php endif; ?>
                            <?php if ($unreadPushCount > 0): ?>
                            <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] text-white font-bold border-2 border-white">
                                <?php echo $unreadPushCount > 9 ? '9+' : $unreadPushCount; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <div class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-indigo-50 transform opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-2">
                                <div class="px-3 py-2 border-b border-indigo-50 mb-1">
                                    <p class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı'); ?></p>
                                </div>
                                <?php if ($myRole === 'teacher'): ?>
                                    <a href="<?php echo $B; ?>/koc/ogrencilerim.php" class="block px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 font-medium text-xs transition-colors">👥 Öğrencilerim</a>
                                    <a href="<?php echo $B; ?>/koc/bildirim_ayarlari.php" class="block px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 font-medium text-xs transition-colors">🔔 Bildirim Ayarları</a>
                                    <a href="<?php echo $B; ?>/koc/odemeler.php" class="block px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 font-medium text-xs transition-colors">💰 Ödeme Yönetimi</a>
                                    <a href="<?php echo $B; ?>/koc/mufredat_v2.php" class="block px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 font-medium text-xs transition-colors">📝 Müfredat Yükle</a>
                                    <button type="button" onclick="openFeedbackModal()" class="w-full text-left block px-3 py-2 rounded-lg hover:bg-orange-50 text-orange-600 font-medium text-xs transition-colors">🐛 Hata Bildir</button>
                                    <div class="h-px bg-slate-100 my-1"></div>
                                <?php endif; ?>
                                <?php if ($myRole === 'student'): ?>
                                <a href="<?php echo $B; ?>/student/bildirimler.php" class="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 text-xs">
                                    <span>🔔 Bildirimler</span>
                                    <?php if ($unreadPushCount > 0): ?>
                                    <span class="bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none"><?php echo $unreadPushCount > 9 ? '9+' : $unreadPushCount; ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo $B; ?>/profil.php" class="block px-3 py-2 rounded-lg hover:bg-slate-50 text-slate-600 text-xs">⚙️ Ayarlar</a>
                                <a href="<?php echo $B; ?>/logout.php" class="flex items-center gap-2 w-full px-3 py-2 rounded-lg hover:bg-red-50 text-red-500 text-xs font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    Çıkış
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $B; ?>/login.php" class="text-blue-600 font-bold text-xs px-3 ml-2">Giriş</a>
                    <a href="<?php echo $B; ?>/register.php" class="bg-blue-600 text-white px-4 py-2 rounded-full font-bold text-xs shadow-md hover:bg-blue-700">Kayıt</a>
                <?php endif; ?>
            </div>

            <div class="lg:hidden flex items-center gap-2">
                <?php if ($isLoggedIn): ?>
                    <div class="relative flex-shrink-0">
                        <a href="<?php echo $B; ?><?php echo ($myRole === 'student') ? '/student/bildirimler.php' : '/profil.php'; ?>" class="block w-8 h-8 rounded-full border border-indigo-100 overflow-hidden">
                            <?php if (!empty($profilePic)): ?>
                                <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profil" class="w-full h-full object-cover">
                            <?php else: ?>
                                <img src="<?php echo $B; ?>/assets/images/favicon.png" alt="Profil" class="w-full h-full object-cover bg-indigo-50 p-0.5">
                            <?php endif; ?>
                        </a>
                        <?php if ($unreadPushCount > 0): ?>
                        <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[8px] text-white font-bold border-2 border-white pointer-events-none">
                            <?php echo $unreadPushCount > 9 ? '9+' : $unreadPushCount; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $B; ?>/login.php" class="text-blue-600 font-bold text-xs px-2 py-1">Giriş</a>
                    <a href="<?php echo $B; ?>/register.php" class="bg-blue-600 text-white px-3 py-1.5 rounded-full font-bold text-xs shadow-sm hover:bg-blue-700 transition">Kayıt</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<?php if ($isLoggedIn): ?>
<div class="lg:hidden fixed bottom-0 left-0 w-full z-[100] bg-white border-t border-slate-100 shadow-[0_-4px_20px_rgba(0,0,0,0.05)] pb-safe">
    <div class="flex justify-between items-center px-6 py-3">
        
        <a href="<?php echo $B; ?>/index.php" class="flex flex-col items-center justify-center w-1/5 group">
            <svg class="w-7 h-7 text-slate-400 group-hover:text-blue-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        </a>

        <a href="<?php echo $linkRandevu; ?>" class="flex flex-col items-center justify-center w-1/5 -mt-8 group relative">
            <div class="w-12 h-12 bg-green-500 rounded-2xl flex items-center justify-center shadow-lg shadow-green-200 transform transition group-active:scale-95 border-4 border-white">
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <?php if ($pendingReqCount > 0 || $unreadMsgCount > 0): ?>
                <span class="absolute top-0 right-0 translate-x-1 -translate-y-1 bg-red-600 text-white text-[9px] font-bold h-4 w-4 flex items-center justify-center rounded-full border border-white">
                    <?php echo $pendingReqCount + $unreadMsgCount; ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="<?php echo $linkKocluk; ?>" class="flex flex-col items-center justify-center w-1/5 -mt-8 group">
            <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200 transform transition group-active:scale-95 border-4 border-white">
                <svg class="h-6 w-6 text-white fill-current" viewBox="0 0 24 24">
                    <path d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
        </a>

        <button type="button" onclick="toggleMobileMenu()" class="flex flex-col items-center justify-center w-1/5 group focus:outline-none relative">
            <svg class="w-7 h-7 text-slate-400 group-hover:text-slate-600 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
            </svg>
             <?php if ($liveChatUnread > 0): ?>
                <span class="absolute top-0 right-3 h-2.5 w-2.5 rounded-full bg-red-600 border border-white"></span>
            <?php endif; ?>
        </button>

    </div>
</div>
<?php endif; ?>

<div id="mobile-menu-overlay" class="fixed inset-0 bg-slate-900/50 z-[110] hidden backdrop-blur-sm" onclick="toggleMobileMenu()"></div>
<div id="mobile-menu-drawer" class="fixed bottom-0 left-0 w-full bg-white rounded-t-3xl shadow-2xl z-[120] transform translate-y-full transition-transform duration-300 max-h-[85vh] overflow-y-auto pb-8">
    <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto my-3"></div>
    <div class="px-6 py-4">
        <h3 class="text-lg font-bold text-slate-800 mb-4">Hızlı Menü</h3>
        
        <div class="grid grid-cols-2 gap-3 mb-6">
            <a href="<?php echo $B; ?>/denemeler.php" class="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-xl border border-slate-100">
                <span class="text-2xl mb-1">📝</span><span class="text-xs font-bold text-slate-600">Denemeler</span>
            </a>
            <?php if (false): // Geçici olarak gizlendi ?>
    <a href="<?php echo $canliDersLink; ?>" class="flex flex-col items-center justify-center p-4 bg-red-50 rounded-xl border border-red-100 relative">
        <span class="text-2xl mb-1">🔴</span><span class="text-xs font-bold text-red-600">Özel Ders</span>
    </a>
<?php endif; ?>
            <?php if ($isLoggedIn): ?>
            <?php if ($myRole === 'student'): ?>
            <a href="<?php echo $B; ?>/student/bildirimler.php" class="relative flex flex-col items-center justify-center p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                <span class="text-2xl mb-1">🔔</span>
                <span class="text-xs font-bold text-indigo-600">Bildirimler</span>
                <?php if ($unreadPushCount > 0): ?>
                <span class="absolute -top-1 -right-1 flex items-center justify-center h-5 w-5 rounded-full bg-red-500 text-white text-[9px] font-bold border-2 border-white">
                    <?php echo $unreadPushCount > 9 ? '9+' : $unreadPushCount; ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="<?php echo $B; ?>/profil.php" class="flex flex-col items-center justify-center p-4 bg-blue-50 rounded-xl border border-blue-100">
                <span class="text-2xl mb-1">⚙️</span><span class="text-xs font-bold text-blue-600">Ayarlar</span>
            </a>
            <?php endif; ?>
        </div>

        <?php if ($myRole === 'teacher'): ?>
        <div class="mb-6 border-t border-slate-100 pt-4">
            <h4 class="text-xs font-bold text-slate-400 uppercase mb-3 tracking-wider">Eğitmen İşlemleri</h4>
            <div class="space-y-2">
                <a href="<?php echo $B; ?>/koc/ogrencilerim.php" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-medium text-sm transition-colors">
                    <span class="text-lg">👥</span> Öğrencilerim
                </a>
                <a href="<?php echo $B; ?>/koc/bildirim_ayarlari.php" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-medium text-sm transition-colors">
                    <span class="text-lg">🔔</span> Bildirim Ayarları
                </a>
                <a href="<?php echo $B; ?>/koc/odemeler.php" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-medium text-sm transition-colors">
                    <span class="text-lg">💰</span> Ödeme Yönetimi
                </a>
                <a href="<?php echo $B; ?>/koc/mufredat_v2.php" class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 font-medium text-sm transition-colors">
                    <span class="text-lg">📝</span> Müfredat Yükle
                </a>
                <button type="button" onclick="closeMobileMenu(); setTimeout(openFeedbackModal,200)" class="w-full flex items-center gap-3 p-3 rounded-xl bg-orange-50 hover:bg-orange-100 text-orange-600 font-medium text-sm transition-colors">
                    <span class="text-lg">🐛</span> Hata Bildir
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="space-y-2">
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo $B; ?>/logout.php" class="flex items-center justify-center gap-2 w-full py-3 text-red-600 font-bold bg-red-50 rounded-xl hover:bg-red-100 transition text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Çıkış Yap
                </a>
            <?php else: ?>
                <a href="<?php echo $B; ?>/login.php" class="block w-full text-center py-3 bg-blue-600 text-white rounded-xl font-bold shadow-md">Giriş Yap</a>
            <?php endif; ?>
        </div>
        <button onclick="toggleMobileMenu()" class="w-full mt-4 py-3 text-sm text-slate-400">Kapat</button>
    </div>
</div>

<script>
function toggleMobileMenu() {
    const overlay = document.getElementById('mobile-menu-overlay');
    const drawer = document.getElementById('mobile-menu-drawer');
    if (drawer.classList.contains('translate-y-full')) {
        overlay.classList.remove('hidden');
        setTimeout(() => { drawer.classList.remove('translate-y-full'); }, 10);
    } else {
        drawer.classList.add('translate-y-full');
        setTimeout(() => { overlay.classList.add('hidden'); }, 300);
    }
}
</script>

<?php if ($isLoggedIn && $myRole === 'teacher'): ?>
<!-- HATA BİLDİR MODALI -->
<div id="feedbackModal" class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden animate-fadeIn border border-slate-100">
        <div class="bg-gradient-to-r from-[#223488] to-[#314595] p-5 flex justify-between items-center text-white">
            <div>
                <h3 class="font-black text-lg">Geri Bildirim Gönder</h3>
                <p class="text-blue-200 text-xs mt-0.5">Hata bildirimi, öneri veya şikayetlerinizi iletin</p>
            </div>
            <button type="button" onclick="closeFeedbackModal()" class="bg-white/20 hover:bg-white/30 rounded-full p-2 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="feedbackForm" class="p-6 space-y-4">
            <!-- Kategori -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-2">Konu Başlığı</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer group">
                        <input type="radio" name="fb_category" value="hata_bildir" class="peer sr-only" checked>
                        <div class="border-2 border-slate-200 rounded-xl py-2.5 px-3 text-center text-xs font-bold text-slate-500 peer-checked:bg-red-500 peer-checked:text-white peer-checked:border-red-500 transition-all group-hover:border-red-300 flex items-center justify-center gap-1.5">
                            🐛 Hata Bildir
                        </div>
                    </label>
                    <label class="cursor-pointer group">
                        <input type="radio" name="fb_category" value="gorusoner" class="peer sr-only">
                        <div class="border-2 border-slate-200 rounded-xl py-2.5 px-3 text-center text-xs font-bold text-slate-500 peer-checked:bg-[#223488] peer-checked:text-white peer-checked:border-[#223488] transition-all group-hover:border-blue-300 flex items-center justify-center gap-1.5">
                            💡 Görüş & Öneri
                        </div>
                    </label>
                    <label class="cursor-pointer group">
                        <input type="radio" name="fb_category" value="sikayet" class="peer sr-only">
                        <div class="border-2 border-slate-200 rounded-xl py-2.5 px-3 text-center text-xs font-bold text-slate-500 peer-checked:bg-orange-500 peer-checked:text-white peer-checked:border-orange-500 transition-all group-hover:border-orange-300 flex items-center justify-center gap-1.5">
                            😤 Şikayet
                        </div>
                    </label>
                    <label class="cursor-pointer group">
                        <input type="radio" name="fb_category" value="diger" class="peer sr-only">
                        <div class="border-2 border-slate-200 rounded-xl py-2.5 px-3 text-center text-xs font-bold text-slate-500 peer-checked:bg-slate-600 peer-checked:text-white peer-checked:border-slate-600 transition-all group-hover:border-slate-400 flex items-center justify-center gap-1.5">
                            📋 Diğer
                        </div>
                    </label>
                </div>
            </div>
            <!-- Konu -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">Kısa Konu</label>
                <input type="text" id="fb_subject" maxlength="200" placeholder="Bildirimin kısa başlığı..."
                       class="js-upper w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all" required>
            </div>
            <!-- Mesaj -->
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1.5">Detaylı Açıklama</label>
                <textarea id="fb_message" rows="4" placeholder="Sorunu veya önerinizi detaylıca açıklayın..."
                          class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all resize-none" required></textarea>
            </div>
            <!-- Butonlar -->
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeFeedbackModal()" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold text-sm hover:bg-slate-200 transition">İptal</button>
                <button type="submit" id="fbSubmitBtn" class="flex-[2] bg-[#223488] hover:bg-[#314595] text-white py-3 rounded-xl font-bold text-sm shadow-lg shadow-[#223488]/20 transition active:scale-[0.98]">
                    Gönder
                </button>
            </div>
            <div id="fbSuccessMsg" class="hidden bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 text-sm font-medium text-center">
                ✅ Bildiriminiz alındı. Teşekkür ederiz!
            </div>
            <div id="fbErrorMsg" class="hidden bg-red-50 border border-red-200 text-red-600 rounded-xl p-3 text-sm font-medium text-center"></div>
        </form>
    </div>
</div>
<script>
function openFeedbackModal() {
    document.getElementById('feedbackModal').classList.remove('hidden');
    document.getElementById('feedbackForm').reset();
    document.getElementById('fbSuccessMsg').classList.add('hidden');
    document.getElementById('fbErrorMsg').classList.add('hidden');
    // Mobile menü açıksa kapat
    const drawer = document.getElementById('mobile-menu-drawer');
    if (drawer && !drawer.classList.contains('translate-y-full')) toggleMobileMenu();
}
function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.add('hidden');
}
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('fbSubmitBtn');
    const cat = document.querySelector('input[name="fb_category"]:checked')?.value || 'hata_bildir';
    const subject = document.getElementById('fb_subject').value.trim();
    const message = document.getElementById('fb_message').value.trim();
    if (!subject || !message) return;
    btn.disabled = true;
    btn.textContent = 'Gönderiliyor...';
    fetch('<?php echo $B; ?>/feedback_submit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ category: cat, subject, message })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('fbSuccessMsg').classList.remove('hidden');
            document.getElementById('fbErrorMsg').classList.add('hidden');
            setTimeout(() => closeFeedbackModal(), 2000);
        } else {
            document.getElementById('fbErrorMsg').textContent = data.error || 'Bir hata oluştu.';
            document.getElementById('fbErrorMsg').classList.remove('hidden');
        }
    })
    .catch(() => {
        document.getElementById('fbErrorMsg').textContent = 'Bağlantı hatası.';
        document.getElementById('fbErrorMsg').classList.remove('hidden');
    })
    .finally(() => { btn.disabled = false; btn.textContent = 'Gönder'; });
});
</script>
<?php endif; ?>

<?php
// VAPID public key'i JS'e güvenli aktar
$vapidPublicKey = '';
try {
    if (file_exists(__DIR__ . '/push_config.php')) {
        require_once __DIR__ . '/push_config.php';
        if (function_exists('vapid_configured') && vapid_configured()) {
            $vapidPublicKey = VAPID_PUBLIC_KEY;
        }
    }
} catch (Throwable $_e) {}
?>
<?php if ($isLoggedIn && !empty($vapidPublicKey)): ?>
<script>
window.__VAPID_PUB__ = '<?php echo htmlspecialchars($vapidPublicKey, ENT_QUOTES); ?>';
(function() {
    'use strict';
    const BASE        = '<?php echo $B; ?>';
    const VAPID_KEY   = '<?php echo htmlspecialchars($vapidPublicKey, ENT_QUOTES); ?>';
    const MY_ROLE     = '<?php echo $myRole; ?>';
    const PUSH_ENABLED = <?php
        $pushPref = 1;
        if ($isLoggedIn && isset($pdo)) {
            try {
                $r = $pdo->prepare("SELECT push_notifications_enabled FROM users WHERE id=?");
                $r->execute([$_SESSION['user_id']]);
                $pushPref = (int)($r->fetchColumn() ?? 1);
            } catch (Throwable $_e) {}
        }
        echo $pushPref;
    ?>;

    // ── Yardımcı: base64url → Uint8Array ────────────────────────────────────
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw     = window.atob(base64);
        return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
    }

    // ── Aboneliği sunucuya kaydet ────────────────────────────────────────────
    async function saveSubscription(sub) {
        try {
            await fetch(BASE + '/ajax/push_subscribe.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ subscription: sub.toJSON(), action: 'subscribe' })
            });
        } catch(e) { /* sessiz geç */ }
    }

    // ── İzin reddedilmişse bildir ────────────────────────────────────────────
    async function notifyPermissionDenied() {
        try {
            await fetch(BASE + '/ajax/push_permission_denied.php', { method: 'POST' });
        } catch(e) {}
    }

    // ── Ana akış ────────────────────────────────────────────────────────────
    async function initPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        // Öğrenci/öğretmen bildirimleri DB'de kapalıysa hiçbir şey yapma
        const PUSH_ROLE_OK = (MY_ROLE === 'student' || MY_ROLE === 'teacher');
        if (PUSH_ROLE_OK && PUSH_ENABLED === 0) return;

        // Tarayıcı izin durumunu kontrol et
        if (Notification.permission === 'denied') {
            await notifyPermissionDenied();
            return;
        }

        let reg;
        try {
            reg = await navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' });
        } catch(e) { return; }

        await navigator.serviceWorker.ready;

        // Mevcut aboneliği kontrol et
        let sub = await reg.pushManager.getSubscription();

        // Öğrenci/öğretmen değilse sadece mevcut aboneliği yenile, yeni izin isteme
        if (!PUSH_ROLE_OK) {
            if (sub) await saveSubscription(sub);
            return;
        }

        // İzin henüz istenmemişse → sor (yalnızca öğrenciye otomatik sorulur;
        // öğretmen izni Bildirim Ayarları sayfasındaki butonla verir)
        if (Notification.permission === 'default') {
            if (MY_ROLE !== 'student') return;
            // Küçük bir gecikme: kullanıcı sayfaya yerleşsin
            setTimeout(async () => {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    if (!sub) {
                        sub = await reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(VAPID_KEY)
                        });
                    }
                    await saveSubscription(sub);
                } else if (permission === 'denied') {
                    await notifyPermissionDenied();
                }
            }, 3000);
            return;
        }

        // İzin granted ama abonelik yoksa oluştur
        if (Notification.permission === 'granted' && !sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_KEY)
            });
            await saveSubscription(sub);
            return;
        }

        // Abonelik varsa yenile (endpoint değişmiş olabilir)
        if (sub) await saveSubscription(sub);
    }

    // DOMContentLoaded'da başlat
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPush);
    } else {
        initPush();
    }
})();
</script>
<?php endif; ?>
<script>
// ── Genel Türkçe büyük harf dönüşümü ────────────────────────────────────────
// Türkçe karakter (İ/I, ı/i) eşleşme sorunlarını azaltmak için, işaretlenen
// serbest metin alanları yazılırken otomatik olarak büyük harfe çevrilir.
// Kullanım: ilgili <input>/<textarea> öğesine class="js-upper" ekle.
(function() {
    'use strict';
    function turkishUpper(el) {
        if (!el || typeof el.value !== 'string') return;
        var start = el.selectionStart, end = el.selectionEnd;
        var upper = el.value.toLocaleUpperCase('tr-TR');
        if (upper !== el.value) {
            el.value = upper;
            if (typeof start === 'number') {
                try { el.setSelectionRange(start, end); } catch (e) {}
            }
        }
    }
    window.turkishUpper = turkishUpper;
    document.addEventListener('input', function(e) {
        var el = e.target;
        if (el && el.classList && el.classList.contains('js-upper')) {
            turkishUpper(el);
        }
    }, true);
})();
</script>

<main class="flex-grow">