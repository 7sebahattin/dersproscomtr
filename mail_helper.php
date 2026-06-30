<?php
// mail_helper.php
// Tüm sistemin mail gönderim merkezi (LOGLU + GİZLİLİK + ANTI-CLIPPING)

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once __DIR__ . '/secrets.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail_notification($toEmail, $toName, $subject, $bodyContent, $link = '') {
    $mail = new PHPMailer(true);

    try {
        // --- SUNUCU AYARLARI (Çalışan En Son Ayarların) ---
        // $mail->SMTPDebug = 2; 
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

        // Gönderen ve Alıcı
        $mail->setFrom('noreply@derspros.com.tr', 'DersPROS Bildirim');
        $mail->addAddress($toEmail, $toName);

        // İçerik
        $mail->isHTML(true);
        $mail->Subject = $subject;

        // Benzersizlik Kodu (Gmail'in maili gizlemesini engeller)
        $uniqueId = md5(uniqid(rand(), true));
        $currentDate = date("d.m.Y H:i");

        $finalBody = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f3f4f6; padding: 40px 20px;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                
                <h2 style='color: #4f46e5; margin-top: 0; margin-bottom: 24px; font-size: 22px; font-weight: 800;'>👋 Yeni Bir Mesajınız Var!</h2>
                
                <p style='color: #1f2937; font-size: 16px; line-height: 1.6; margin-bottom: 16px;'>
                    Merhaba <strong>$toName</strong>,
                </p>
                
                <p style='color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 32px;'>
                    $bodyContent
                </p>

                " . ($link ? "
                <div style='text-align: center; margin-bottom: 32px;'>
                    <a href='$link' style='background-color: #4f46e5; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 50px; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2); transition: background-color 0.2s;'>
                        Mesajı Görüntüle
                    </a>
                </div>
                " : "") . "
                
                <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;'>
                
                <div style='text-align: center;'>
                    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>DersPROS Eğitim Platformu</p>
                    <p style='color: #d1d5db; font-size: 10px; margin-top: 8px;'>Bildirim ID: #$uniqueId • $currentDate</p>
                    
                    <span style='opacity: 0; display: none; font-size: 1px; color: #ffffff;'>$uniqueId - Her maili benzersiz yap ki gmail gizlemesin.</span>
                </div>
            </div>
        </div>";

        $mail->Body    = $finalBody;
        $mail->AltBody = strip_tags($bodyContent) . " Link: $link";
        $mail->Timeout = 10; 

        $mail->send();
        return true;

    } catch (Exception $e) {
        $errorMsg = date("Y-m-d H:i:s") . " - HATA: " . $mail->ErrorInfo . " | ALICI: $toEmail\n";
        file_put_contents('mail_hatalari.txt', $errorMsg, FILE_APPEND);
        return false;
    }
}
?>