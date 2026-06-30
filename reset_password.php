<?php
// reset_password.php
require_once 'db.php';

$message = '';
$msgType = '';
$showForm = false;

// Token Kontrolü (URL'den gelen)
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

if (!$token || !$email) {
    $message = "Geçersiz veya eksik bağlantı.";
    $msgType = "error";
} else {
    // Token geçerli mi ve süresi dolmamış mı?
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$email, $token]);
    $user = $stmt->fetch();

    if ($user) {
        $showForm = true;
    } else {
        $message = "Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş.";
        $msgType = "error";
    }
}

// Şifre Güncelleme İşlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $showForm) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    if (empty($pass1) || strlen($pass1) < 6) {
        $message = "Şifre en az 6 karakter olmalıdır.";
        $msgType = "error";
    } elseif ($pass1 !== $pass2) {
        $message = "Şifreler eşleşmiyor.";
        $msgType = "error";
    } else {
        // Yeni şifreyi hashle
        $newHash = password_hash($pass1, PASSWORD_DEFAULT);

        // Şifreyi güncelle ve token'ı temizle (tek kullanımlık olsun)
        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($update->execute([$newHash, $user['id']])) {
            $message = "Şifreniz başarıyla güncellendi! Giriş yapabilirsiniz.";
            $msgType = "success";
            $showForm = false; // Formu gizle
        } else {
            $message = "Bir hata oluştu.";
            $msgType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Şifre Belirle | DersPROS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-indigo-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-md w-full bg-white p-10 rounded-3xl shadow-xl border border-indigo-50">
        <div class="text-center mb-8">
            <div class="mx-auto h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 text-3xl font-bold mb-4">
                🔄
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Yeni Şifre Belirle</h2>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $msgType == 'success' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-50 text-red-600 border-red-200'; ?> border px-4 py-3 rounded-xl mb-6 text-sm">
                <?php echo $message; ?>
            </div>
            <?php if($msgType == 'success'): ?>
                <div class="text-center mt-4">
                    <a href="login.php" class="bg-indigo-600 text-white px-6 py-2 rounded-full font-bold shadow-lg hover:bg-indigo-700 transition">Giriş Yap</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($showForm): ?>
        <form class="space-y-6" method="POST">
            <div>
                <label class="text-sm font-medium text-slate-700">Yeni Şifre</label>
                <input name="password" type="password" required class="appearance-none rounded-xl block w-full px-4 py-3 border border-slate-300 focus:ring-indigo-500 mt-1 bg-slate-50">
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Yeni Şifre (Tekrar)</label>
                <input name="confirm_password" type="password" required class="appearance-none rounded-xl block w-full px-4 py-3 border border-slate-300 focus:ring-indigo-500 mt-1 bg-slate-50">
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-full text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none shadow-lg transition transform hover:-translate-y-0.5">
                    Şifreyi Güncelle
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <?php if (!$showForm && $msgType != 'success'): ?>
             <div class="mt-6 text-center">
                <a href="forgot_password.php" class="text-indigo-600 font-bold hover:underline">Yeni link talep et</a>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>