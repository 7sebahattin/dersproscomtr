<?php
// Oturum başlatılmamışsa başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header dosyasını bir üst dizinden çağırıyoruz
include '../header.php'; 
?>

<div class="container" style="padding: 50px 15px; max-width: 900px; margin: 0 auto; font-family: sans-serif; line-height: 1.8; color: #333;">
    
    <div class="text-center mb-5">
        <h1 style="font-weight: bold; color: #2c3e50; margin-bottom: 10px;">Kullanım Koşulları</h1>
        <p style="color: #7f8c8d;">Son Güncelleme: <?php echo date("d.m.Y"); ?></p>
        <hr style="border-top: 2px solid #3498db; width: 50px; margin: 20px auto;">
    </div>

    <div class="terms-content" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.05);">
        
        <p>Lütfen <strong>DersPROS</strong> ("Platform") web sitesini ve hizmetlerini kullanmadan önce aşağıdaki kullanım koşullarını dikkatlice okuyunuz. Platforma üye olan veya hizmetlerden yararlanan herkes, bu koşulları kabul etmiş sayılır.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">1. Hesap Güvenliği ve Üyelik</h3>
        <p>Platforma üye olurken verdiğiniz bilgilerin (Ad, Soyad, Sınıf vb.) doğru ve güncel olması sizin sorumluluğunuzdadır. Üyelik şifrenizin güvenliğini sağlamak tamamen kullanıcının yükümlülüğündedir. Hesabınız üzerinden yapılan tüm işlemlerden siz sorumlu tutulursunuz. Şüpheli bir durum fark ettiğinizde derhal bize bildirmelisiniz.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">2. Hizmet Kullanımı</h3>
        <p>DersPROS, öğrencilere koçluk, sınav takibi ve ders programı yönetimi hizmetleri sunar. Kullanıcılar:</p>
        <ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
            <li>Sistemi sadece eğitim ve kişisel gelişim amacıyla kullanabilir.</li>
            <li>Sistemin işleyişini bozacak, sunuculara zarar verecek veya diğer kullanıcıların verilerine erişmeye çalışacak teknik müdahalelerde bulunamaz.</li>
            <li>Platformdaki içerikleri (sorular, analizler, videolar) izinsiz kopyalayamaz veya ticari amaçla dağıtamaz.</li>
        </ul>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">3. Fikri Mülkiyet Hakları</h3>
        <p>DersPROS üzerindeki tüm tasarımlar, logolar, yazılımlar, soru bankaları ve eğitim materyalleri firmamıza aittir ve telif hakları yasalarıyla korunmaktadır. İzinsiz kullanımı yasal işlem gerektirebilir.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">4. İçerik ve Sorumluluk Reddi</h3>
        <p>DersPROS, öğrencilerin başarısını artırmak için araçlar ve rehberlik sunar; ancak sınav başarısını %100 garanti etmez. Başarı, öğrencinin kişisel çabasına bağlıdır. Sistemdeki olası teknik aksaklıklar veya veri kayıplarından (yedekleme yapılsa dahi) doğabilecek mağduriyetlerden platform sorumlu tutulamaz.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">5. Üyeliğin Sonlandırılması</h3>
        <p>Genel ahlak kurallarına aykırı davranan, sistemde hile yapmaya çalışan veya diğer kullanıcıları (öğretmen/öğrenci) taciz eden kullanıcıların hesapları, önceden uyarı yapılmaksızın askıya alınabilir veya tamamen silinebilir.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">6. Koşullarda Değişiklik</h3>
        <p>DersPROS, bu kullanım koşullarını dilediği zaman güncelleme hakkını saklı tutar. Güncellenen koşullar sitede yayınlandığı andan itibaren geçerli sayılır.</p>

        <h3 style="color: #2c3e50; margin-top: 30px; font-weight: 600;">7. İletişim</h3>
        <p>Kullanım koşulları ile ilgili sorularınız için bizimle iletişime geçebilirsiniz.</p>
        <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #3498db; margin-top: 15px;">
            <p style="margin: 0;"><strong>E-posta:</strong> sebahattin@derspros.com.tr</p>
        </div>

    </div>
</div>
<?php 
// Footer dosyasını bir üst dizinden çağırıyoruz
include '../footer.php'; 
?>