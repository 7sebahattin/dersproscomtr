<?php
// forgot_password.php
require_once 'db.php';

// PHPMailer Dosyalarını Dahil Et
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Lütfen e-posta adresinizi girin.";
        $msgType = "error";
    } else {
        // E-posta kontrolü
        $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Güvenli Token Oluştur
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Token'ı veritabanına kaydet
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update->execute([$token, $expires, $user['id']]);

            // Link Oluştur
            $domain = $_SERVER['HTTP_HOST']; 
            $resetLink = "http://$domain/reset_password.php?token=$token&email=$email";

            // --- PHPMAILER İLE MAIL GÖNDERİMİ ---
            $mail = new PHPMailer(true);

try {
                // Hata ayıklama (Canlıda 0, test ederken sorun olursa 2 yap)
                $mail->SMTPDebug = 0;

                $mail->isSMTP();
                
                // >>>>> RESİMDEKİ MAVİ KUTU AYARLARI <<<<<
                $mail->Host       = 'mail.derspros.com.tr'; // Resimde 'Giden Sunucu' kısmında yazan adres
                $mail->SMTPAuth   = true;
                $mail->Username   = 'noreply@derspros.com.tr'; // Oluşturduğun mail adresi
                $mail->Password   = '***REMOVED***';  // E-posta şifreni buraya yazmayı unutma!
                
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // SSL kullanacağız
                $mail->Port       = 587; // Resimde yazan SMTP Portu
                // >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>

                // Hosting kaynaklı SSL sertifika hatalarını görmezden gel (Bağlantıyı garantiye alır)
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->CharSet    = 'UTF-8';
                $mail->setLanguage('tr');

                // Alıcı ve Gönderen Ayarları
                $mail->setFrom('noreply@derspros.com.tr', 'DersPROS Güvenlik');
                $mail->addAddress($email, $user['first_name']);

                // İçerik Ayarları
                $mail->isHTML(true);
                $mail->Subject = 'DersPROS Şifre Sıfırlama';
                
                // Mail İçeriği (HTML Şablon)
                $mail->Body    = "
                <div style='font-family: sans-serif; padding: 20px; background-color: #f3f4f6;'>
                    <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                        <h2 style='color: #4f46e5; margin-bottom: 20px;'>Şifre Sıfırlama Talebi</h2>
                        <p style='color: #374151; font-size: 16px;'>Merhaba <strong>{$user['first_name']}</strong>,</p>
                        <p style='color: #374151; font-size: 16px;'>Hesabınız için bir şifre sıfırlama talebi aldık. Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz.</p>
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='$resetLink' style='background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 50px; font-weight: bold; display: inline-block;'>Şifremi Sıfırla</a>
                        </div>
                        <p style='color: #6b7280; font-size: 14px;'>Linke tıklayamıyorsanız, şu adresi tarayıcınıza yapıştırın:<br>$resetLink</p>
                        <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;'>
                        <p style='color: #9ca3af; font-size: 12px; text-align: center;'>Bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
                    </div>
                </div>";
                
                $mail->AltBody = "Merhaba {$user['first_name']}, şifrenizi sıfırlamak için şu linke tıklayın: $resetLink";

                $mail->send();
                
                $message = "Şifre sıfırlama bağlantısı e-posta adresinize gönderildi. Lütfen gereksiz/spam kutunuzu da kontrol edin.";
                $msgType = "success";

            } catch (Exception $e) {
                $message = "Mail gönderilemedi. Hata: {$mail->ErrorInfo}";
                $msgType = "error";
            }
            // -------------------------------------

        } else {
            $message = "Bu e-posta adresiyle kayıtlı kullanıcı bulunamadı.";
            $msgType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifremi Unuttum | DersPROS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-indigo-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-md w-full bg-white p-10 rounded-3xl shadow-xl border border-indigo-50">
        <div class="text-center mb-8">
            <div class="mx-auto h-16 w-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600 text-3xl font-bold mb-4">
                🔑
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Şifremi Unuttum</h2>
            <p class="mt-2 text-sm text-slate-500">
                Kayıtlı e-posta adresinizi girin, size sıfırlama bağlantısı gönderelim.
            </p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $msgType == 'success' ? 'bg-green-100 text-green-700 border-green-200' : 'bg-red-50 text-red-600 border-red-200'; ?> border px-4 py-3 rounded-xl mb-6 text-sm">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form class="space-y-6" method="POST">
            <div>
                <label for="email" class="text-sm font-medium text-slate-700">E-posta Adresi</label>
                <input id="email" name="email" type="email" required 
                       class="appearance-none rounded-xl relative block w-full px-4 py-3 border border-slate-300 placeholder-slate-400 text-slate-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 mt-1 bg-slate-50 focus:bg-white transition" 
                       placeholder="ornek@email.com">
            </div>

            <div>
                <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-full text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                    Bağlantı Gönder
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <a href="login.php" class="text-sm font-medium text-slate-500 hover:text-indigo-600 transition">
                ← Giriş ekranına dön
            </a>
        </div>
    </div>

</body>
</html>