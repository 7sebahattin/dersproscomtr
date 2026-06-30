<?php
// Oturum başlatılmamışsa başlat (Header'da zaten varsa burayı kaldırabilirsin)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header dosyasını bir üst dizinden çağırıyoruz
// Eğer header dosyanın yolu farklıysa burayı güncellemelisin (örn: ../includes/header.php)
include '../header.php'; 
?>

<div class="container" style="padding: 50px 15px; max-width: 900px; margin: 0 auto; font-family: sans-serif; line-height: 1.8; color: #333;">
    
    <div class="text-center mb-5">
        <h1 style="font-weight: bold; color: #2c3e50; margin-bottom: 10px;">Gizlilik Politikası</h1>
        <p style="color: #7f8c8d;">Son Güncelleme: <?php echo date("d.m.Y"); ?></p>
        <hr style="border-top: 2px solid #3498db; width: 50px; margin: 20px auto;">
    </div>

    <div class="policy-content" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.05);">
        
        <p><strong>DersPROS</strong> ("biz", "sistem" veya "platform") olarak, kullanıcılarımızın (öğrenciler, veliler ve eğitmenler) gizliliğine ve kişisel verilerinin korunmasına büyük önem veriyoruz. Bu Gizlilik Politikası, hizmetlerimizi kullanırken topladığımız bilgileri, bu bilgileri nasıl kullandığımızı ve nasıl koruduğumuzu açıklar.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">1. Toplanan Bilgiler</h3>
        <p>Hizmetlerimizi etkili bir şekilde sunabilmek için aşağıdaki veri türlerini toplayabiliriz:</p>
        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
            <li><strong>Kimlik Bilgileri:</strong> Ad, soyad, T.C. kimlik numarası (gerekli durumlarda).</li>
            <li><strong>İletişim Bilgileri:</strong> E-posta adresi, telefon numarası, adres.</li>
            <li><strong>Eğitim Verileri:</strong> Deneme sınavı sonuçları, konu analizleri, ödev takipleri, ders programları ve ilerleme raporları.</li>
            <li><strong>Sistem Kayıtları:</strong> Giriş zamanları, IP adresleri ve platform kullanım istatistikleri.</li>
        </ul>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">2. Bilgilerin Kullanım Amacı</h3>
        <p>Toplanan veriler şu amaçlarla işlenmektedir:</p>
        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
            <li>Öğrenci koçluğu ve akademik takip hizmetlerinin sağlanması.</li>
            <li>Kişiye özel ders çalışma programlarının oluşturulması.</li>
            <li>Öğrenci, veli ve öğretmen arasındaki iletişimin sürdürülmesi.</li>
            <li>Platform güvenliğinin sağlanması ve teknik sorunların giderilmesi.</li>
            <li>Yasal yükümlülüklerin yerine getirilmesi.</li>
        </ul>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">3. Verilerin Paylaşımı</h3>
        <p>Kişisel verileriniz, yasal zorunluluklar (resmi makamların talebi) haricinde, izniniz olmaksızın üçüncü şahıslarla pazarlama amacıyla <strong>paylaşılmamaktadır</strong>. Eğitim verileri sadece ilgili öğrencinin velisi ve atanan koç/öğretmen tarafından görüntülenebilir.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">4. Veri Güvenliği</h3>
        <p>Verilerinizi yetkisiz erişime, değiştirmeye veya ifşaya karşı korumak için endüstri standardı güvenlik önlemleri (SSL şifreleme, güvenli veritabanı yapıları) uyguluyoruz. Ancak, internet üzerinden yapılan hiçbir veri iletiminin %100 güvenli garanti edilemeyeceğini hatırlatırız.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">5. Çerezler (Cookies)</h3>
        <p>Web sitemiz, kullanıcı deneyimini iyileştirmek ve oturumunuzu açık tutmak için çerezler kullanabilir. Tarayıcı ayarlarınızdan çerezleri dilediğiniz zaman engelleyebilirsiniz, ancak bu durumda platformun bazı özellikleri çalışmayabilir.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">6. Haklarınız</h3>
        <p>KVKK kapsamında, verilerinizin işlenip işlenmediğini öğrenme, yanlış verilerin düzeltilmesini isteme ve verilerinizin silinmesini talep etme hakkına sahipsiniz. Bu talepleriniz için bizimle iletişime geçebilirsiniz.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">7. İletişim</h3>
        <p>Gizlilik politikamızla ilgili her türlü soru ve görüşünüz için bize ulaşabilirsiniz:</p>
        <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; margin-top: 15px;">
            <p style="margin: 0;"><strong>E-posta:</strong> sebahattin@derspros.com.tr</p>
            <p style="margin: 0;"><strong>Adres:</strong> Antalya, Türkiye</p>
        </div>

    </div>
</div>
<?php 
// Footer dosyasını bir üst dizinden çağırıyoruz
include '../footer.php'; 
?>