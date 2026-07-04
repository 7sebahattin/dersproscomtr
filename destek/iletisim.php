<?php
// Oturum kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header'ı çağır
include '../header.php'; 

// --- PHPMAILER KÜTÜPHANESİ ---
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../secrets.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- İŞLEVSELLİK KISMI ---
$durum_mesaji = "";
$durum_tipi = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini temizle
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $message = htmlspecialchars(trim($_POST['message']));

    if (!empty($name) && !empty($email) && !empty($message)) {
        
        $mail = new PHPMailer(true);

        try {
            // --- AYARLAR ---
            $mail->isSMTP();
            $mail->Host       = 'mail.derspros.com.tr'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'noreply@derspros.com.tr'; 
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
            $mail->Port       = 587;
            
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->CharSet    = 'UTF-8';
            $mail->setLanguage('tr');

            // --- GÖNDERİM AYARLARI ---
            $mail->setFrom('noreply@derspros.com.tr', 'DersPROS İletişim');
            $mail->addAddress('info@derspros.com', 'Yönetici'); 
            $mail->addReplyTo($email, $name);

            // --- İÇERİK ---
            $mail->isHTML(true); 
            $mail->Subject = "İletişim Formu: " . $subject;
            $uniqueId = md5(uniqid(rand(), true));
            
            $mail->Body    = "
                <div style='background-color: #f3f4f6; padding: 20px; font-family: sans-serif;'>
                    <div style='background: #fff; padding: 30px; border-radius: 10px; max-width: 600px; margin: 0 auto; border-top: 5px solid #4f46e5;'>
                        <h2 style='color: #333; margin-top: 0;'>📩 Yeni İletişim Mesajı</h2>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; width: 100px; font-weight: bold; color: #555;'>Gönderen:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>$name</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; color: #555;'>E-Posta:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>$email</td></tr>
                            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; color: #555;'>Konu:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>$subject</td></tr>
                        </table>
                        <p style='margin-top: 20px; font-weight: bold; color: #555;'>Mesaj İçeriği:</p>
                        <div style='background: #f9f9f9; padding: 15px; border-radius: 5px; color: #333; line-height: 1.6;'>$message</div>
                        <br><small style='color: #aaa; font-size: 11px;'>ID: $uniqueId</small>
                    </div>
                </div>
            ";
            
            $mail->AltBody = "Gönderen: $name\nE-Posta: $email\nKonu: $subject\n\nMesaj:\n$message";

            $mail->send();
            $durum_mesaji = "Mesajınız başarıyla gönderildi! En kısa sürede dönüş yapacağız.";
            $durum_tipi = "success";

        } catch (Exception $e) {
            $durum_mesaji = "Mesaj gönderilemedi. Hata: {$mail->ErrorInfo}";
            $durum_tipi = "error";
        }

    } else {
        $durum_mesaji = "Lütfen tüm alanları doldurunuz.";
        $durum_tipi = "error";
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    /* --- DÜZELTME BURADA --- */
    
    /* 1. Tüm linklerin alt çizgisini kaldır */
    a {
        text-decoration: none !important;
    }

    /* 2. Footer içindeki linklerin rengini 'Koyu Gri' yap (Maviyi engeller) */
    footer a {
        color: #555 !important; 
        transition: color 0.3s ease;
    }

    /* 3. Footer linklerinin üzerine gelince renk değiştirsin (Şık durur) */
    footer a:hover {
        color: #000 !important; /* Siyah olsun */
    }

    /* Sayfa Geneli Stiller */
    body { background-color: #f4f7f6; }
    .dp-section-wrapper { padding: 60px 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .dp-contact-box { background: #fff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); overflow: hidden; display: flex; flex-wrap: wrap; }
    
    /* SOL TARAF */
    .dp-left-side { background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); color: #fff; padding: 50px 40px; width: 40%; }
    .dp-left-side h3 { font-weight: 700; margin-bottom: 30px; font-size: 24px; color: #fff; }
    .dp-info-row { display: flex; align-items: flex-start; margin-bottom: 35px; }
    .dp-icon-circle { width: 45px; height: 45px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px; flex-shrink: 0; }
    .dp-social-links { margin-top: 50px; }
    .dp-social-btn { display: inline-flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 30px; color: #fff; text-decoration: none; transition: 0.3s; border: 1px solid rgba(255,255,255,0.2); }
    .dp-social-btn:hover { background: #fff; color: #d6249f; }

    /* SAĞ TARAF */
    .dp-right-side { padding: 50px 40px; width: 60%; background: #fff; }
    .dp-right-side h3 { color: #2c3e50; font-weight: 700; margin-bottom: 20px; }
    .dp-form-group { margin-bottom: 20px; }
    .dp-input { width: 100%; padding: 12px 15px; border: 1px solid #e1e1e1; border-radius: 8px; font-size: 14px; transition: 0.3s; background-color: #f9f9f9; }
    .dp-input:focus { border-color: #3498db; background-color: #fff; outline: none; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1); }
    .dp-submit-btn { background: #2c3e50; color: #fff; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .dp-submit-btn:hover { background: #34495e; transform: translateY(-2px); }

    @media (max-width: 768px) { .dp-left-side, .dp-right-side { width: 100%; } .dp-contact-box { flex-direction: column; } }
</style>

<div class="dp-section-wrapper">
    <div class="container">
        
        <?php if(!empty($durum_mesaji)): ?>
            <div class="alert alert-<?php echo ($durum_tipi == 'success') ? 'success' : 'danger'; ?> mb-4">
                <?php echo $durum_mesaji; ?>
            </div>
        <?php endif; ?>

        <div class="dp-contact-box">
            <div class="dp-left-side">
                <h3>İletişim Bilgileri</h3>
                <p style="opacity: 0.8; margin-bottom: 40px;">Sorularınız veya iş birlikleri için bize ulaşın.</p>
                <div class="dp-info-row">
                    <div class="dp-icon-circle"><i class="fas fa-phone-alt"></i></div>
                    <div class="dp-info-text"><h6>Telefon</h6><p>+90 539 502 52 14</p></div>
                </div>
                <div class="dp-info-row">
                    <div class="dp-icon-circle"><i class="fas fa-envelope"></i></div>
                    <div class="dp-info-text"><h6>E-Posta</h6><p>sebahattin@derspros.com.tr</p></div>
                </div>
                <div class="dp-info-row">
                    <div class="dp-icon-circle"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="dp-info-text"><h6>Adres</h6><p>Antalya, Türkiye</p></div>
                </div>
                <div class="dp-social-links">
                    <p style="margin-bottom: 10px; font-size: 14px;">Bizi Takip Edin:</p>
                    <a href="https://instagram.com/derspros" target="_blank" class="dp-social-btn">
                        <i class="fab fa-instagram" style="font-size: 20px;"></i><span>@derspros</span>
                    </a>
                </div>
            </div>

            <div class="dp-right-side">
                <h3>Bize Yazın</h3>
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 dp-form-group">
                            <label class="form-label text-muted small">Adınız Soyadınız</label>
                            <input type="text" name="name" class="dp-input js-upper" required>
                        </div>
                        <div class="col-md-6 dp-form-group">
                            <label class="form-label text-muted small">E-Posta Adresiniz</label>
                            <input type="email" name="email" class="dp-input" required>
                        </div>
                    </div>
                    <div class="dp-form-group">
                        <label class="form-label text-muted small">Konu</label>
                        <input type="text" name="subject" class="dp-input js-upper" required>
                    </div>
                    <div class="dp-form-group">
                        <label class="form-label text-muted small">Mesajınız</label>
                        <textarea name="message" rows="5" class="dp-input" required></textarea>
                    </div>
                    <button type="submit" class="dp-submit-btn">Mesajı Gönder <i class="fas fa-paper-plane ms-2"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>