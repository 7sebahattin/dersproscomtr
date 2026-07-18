<?php
// login.php (ATLA PALETİ) - TAM SÜRÜM
require_once 'db.php';

$error = '';
$success = '';

// Eğer zaten giriş yapmışsa index'e at
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Kayıttan geldiyse başarı mesajı
if (isset($_GET['kayit']) && $_GET['kayit'] == 'basarili') {
    $success = "Kayıt başarıyla oluşturuldu. Yönetici onayından sonra giriş yapabilirsiniz.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Kullanıcı adı ve şifre gereklidir.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            // 1. ONAY KONTROLÜ
            if ($user['is_active'] == 0) {
                $error = "Hesabınız henüz yönetici tarafından onaylanmamıştır. Lütfen daha sonra tekrar deneyin.";
            } else {
                // Oturumu başlat
                if (session_status() === PHP_SESSION_NONE) session_start();

                // Güvenlik: Giriş başarılı olunca oturum kimliğini yenile
                // (Session Fixation saldırısına karşı koruma)
                session_regenerate_id(true);

                // Session değişkenlerini ata
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['is_superuser'] = $user['is_superuser']; 
                // Tarihi kaydet (index.php'de kontrol edilecek)
                $_SESSION['membership_expires_at'] = $user['membership_expires_at'];

                // --- BENİ HATIRLA ---
                if (isset($_POST['remember_me'])) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), session_id(), time() + (30 * 24 * 60 * 60), $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
                }

                // Son giriş zamanını güncelle (push bildirim cron'u için)
                try {
                    $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                        ->execute([$user['id']]);
                } catch (Throwable $_e) {}

                // Günlük metrik: giriş izi kalıcı damgalanır (gece yarısı
                // last_login_at ezilse bile o günün logged_in kaydı kalır)
                if ($user['role'] === 'student') {
                    require_once __DIR__ . '/metrics_lib.php';
                    metrics_mark_login($pdo, (int)$user['id']);
                }

                // Yönlendirme
                if ($user['role'] == 'admin') {
                    header("Location: " . BASE_URL . "/admin/");
                } else {
                    header("Location: index.php");
                }
                exit;
            }

        } else {
            $error = "Kullanıcı adı veya şifre hatalı.";
        }
    }
}
include 'header.php';
?>

<style>
    :root {
        --atla-blue-dark: #223488;
        --atla-blue-light: #314595;
        --atla-orange: #ec9731;
        --atla-orange-hover: #d68625;
    }
    
    .focus-atla-blue:focus {
        border-color: var(--atla-blue-light);
        --tw-ring-color: var(--atla-blue-light); 
        --tw-ring-opacity: 0.5;
        box-shadow: 0 0 0 3px var(--tw-ring-color);
    }

    .checkbox-atla:checked {
        background-color: var(--atla-blue-dark);
        border-color: var(--atla-blue-dark);
    }
    
    .btn-atla-orange {
        background-color: var(--atla-orange);
    }
    .btn-atla-orange:hover {
        background-color: var(--atla-orange-hover);
    }
</style>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50">
    <div class="max-w-md w-full bg-white p-10 rounded-3xl shadow-xl border border-slate-100">
        
        <div class="text-center mb-8">
            <div class="mx-auto h-16 w-16 bg-[#223488] bg-opacity-10 rounded-full flex items-center justify-center text-[#223488] text-2xl font-bold mb-4 shadow-sm">
                N
            </div>
            <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Tekrar Hoş Geldiniz</h2>
            <p class="mt-2 text-sm text-slate-500">
                Hesabınız yok mu? <a href="register.php" class="font-bold text-[#ec9731] hover:text-[#d68625] hover:underline transition">Hemen Kayıt Olun</a>
            </p>
        </div>

        <?php if($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4 text-sm font-medium">
                <i class="fa-solid fa-circle-check mr-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl mb-6 text-sm flex items-start shadow-sm">
            <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div>
                <p class="font-bold">Giriş Başarısız!</p>
                <p><?php echo $error; ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <form class="space-y-6" method="POST">
            <div class="space-y-4">
                <div>
                    <label for="username" class="text-sm font-bold text-slate-700 ml-1">Kullanıcı Adı</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none rounded-xl relative block w-full px-4 py-3.5 border border-slate-300 placeholder-slate-400 text-slate-900 focus:outline-none focus-atla-blue sm:text-sm mt-1 bg-slate-50 focus:bg-white transition" 
                           placeholder="Kullanıcı adınız">
                </div>

                <div>
                    <label for="password" class="text-sm font-bold text-slate-700 ml-1">Şifre</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-xl relative block w-full px-4 py-3.5 border border-slate-300 placeholder-slate-400 text-slate-900 focus:outline-none focus-atla-blue sm:text-sm mt-1 bg-slate-50 focus:bg-white transition" 
                           placeholder="••••••••">
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="remember_me" name="remember_me" type="checkbox" class="h-4 w-4 checkbox-atla border-gray-300 rounded cursor-pointer transition">
                    <label for="remember_me" class="ml-2 block text-sm text-slate-600 cursor-pointer select-none font-medium">Beni Hatırla</label>
                </div>
                
                <div class="text-sm">
                    <a href="forgot_password.php" class="font-medium text-slate-500 hover:text-[#223488] transition">Şifremi unuttum?</a>
                </div>
            </div>
            
            <div>
                <button type="submit" class="group relative w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-xl text-white btn-atla-orange focus:outline-none shadow-lg shadow-orange-200 transition transform hover:-translate-y-0.5 active:scale-95">
                    Giriş Yap
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>