<?php
// Oturum kontrolü
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Header'ı çağır
include '../header.php'; 
?>

<div class="faq-header">
    <div class="container text-center">
        <h1 class="fw-bold mb-3" style="color: #2c3e50;">Sıkça Sorulan Sorular</h1>
        <p class="lead text-muted">Aklınıza takılan soruların cevaplarını burada bulabilirsiniz.</p>
        <hr class="mx-auto" style="width: 50px; border-top: 3px solid #3498db; opacity: 1;">
    </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
    /* --- SORUNU ÇÖZEN KRİTİK KOD --- */
    /* Açılmış bir akordiyonun (show sınıfı olanın) gizlenmesini zorla engelliyoruz */
    .accordion-collapse.show {
        display: block !important;
        visibility: visible !important;
        height: auto !important;
        opacity: 1 !important;
    }
    
    /* Animasyon sırasında titremeyi engelle */
    .collapsing {
        visibility: visible !important; 
    }

    /* --- DİĞER TASARIM AYARLARI --- */
    a { text-decoration: none !important; }
    footer a { color: #555 !important; transition: color 0.3s ease; }
    footer a:hover { color: #000 !important; }

    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .faq-header { padding: 60px 0 40px 0; }
    
    .accordion-item {
        border: none;
        border-radius: 10px;
        margin-bottom: 15px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        background-color: #fff !important;
    }
    
    .accordion-button {
        font-weight: 600;
        color: #2c3e50 !important;
        background-color: #fff;
        padding: 20px 25px;
        font-size: 1.1rem;
    }
    
    .accordion-button:not(.collapsed) {
        color: #3498db !important;
        background-color: #f1f8ff !important;
        box-shadow: none;
    }
    
    .accordion-body {
        font-size: 0.95rem;
        line-height: 1.7;
        color: #333 !important;
        background-color: #fff !important;
        padding: 25px;
    }
    
    /* İletişim Kutusu */
    .contact-redirect {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        color: #fff !important;
        border-radius: 15px;
        padding: 40px;
        margin-top: 50px;
        text-align: center;
    }
    .contact-redirect h4, .contact-redirect p { color: #fff !important; }
    
    .btn-white {
        background: #fff;
        color: #3498db !important;
        padding: 12px 30px;
        border-radius: 30px;
        font-weight: 600;
        transition: 0.3s;
        display: inline-block;
        margin-top: 15px;
    }
    .btn-white:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        color: #2c3e50 !important;
    }
</style>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            
            <div class="accordion" id="accordionFAQ">

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            <i class="fas fa-question-circle me-3 text-primary"></i> DersPROS sistemi tam olarak nedir?
                        </button>
                    </h2>
                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            DersPROS, öğrencilerin akademik başarılarını artırmak amacıyla geliştirilmiş kapsamlı bir <strong>Eğitim Koçluğu ve Takip Sistemidir</strong>. Platformumuz üzerinden öğrenciler kendilerine özel hazırlanan ders programlarını takip edebilir, deneme sınavı analizlerini görüntüleyebilir ve eğitim koçlarıyla birebir iletişim kurabilirler.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingTwo">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                            <i class="fas fa-calendar-check me-3 text-primary"></i> Ders programları nasıl hazırlanıyor?
                        </button>
                    </h2>
                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            Haftalık ders programlarınız, size atanan <strong>Eğitim Koçunuz</strong> tarafından hazırlanır. Koçunuz, deneme sınavı sonuçlarınızı ve konu eksiklerinizi analiz ederek size en uygun "nokta atışı" programı oluşturur. Siz programı uyguladıkça sistem üzerinden "Tamamlandı" olarak işaretlersiniz, koçunuz da bu ilerlemeyi anlık olarak takip eder.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingThree">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                            <i class="fas fa-chart-pie me-3 text-primary"></i> Deneme analizleri neleri kapsıyor?
                        </button>
                    </h2>
                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            Sisteme yüklenen deneme sonuçlarınız detaylı bir şekilde analiz edilir. Sadece doğru/yanlış sayılarını değil; <strong>hangi konularda eksiğiniz olduğunu</strong>, zaman yönetimi performansınızı ve haftalık gelişim grafiğinizi panelinizden görebilirsiniz.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFour">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                            <i class="fas fa-video me-3 text-primary"></i> Online derslere/görüşmelere nasıl katılırım?
                        </button>
                    </h2>
                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            Eğitim koçunuz veya öğretmenleriniz tarafından planlanan Zoom görüşmeleri ve online dersler, panelinizdeki <strong>"Randevularım"</strong> veya <strong>"Canlı Dersler"</strong> sekmesinde görünür. Ders saati geldiğinde ilgili butona tıklayarak doğrudan derse bağlanabilirsiniz.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingFive">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                            <i class="fas fa-user-friends me-3 text-primary"></i> Veliler sistemi kullanabiliyor mu?
                        </button>
                    </h2>
                    <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            Evet! Velilerimiz kendilerine özel tanımlanan hesaplarla sisteme giriş yapabilir. Öğrencinin ders programına uyumunu, deneme sonuçlarını, koçunun yorumlarını ve genel gelişim raporlarını anlık olarak takip edebilirler.
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingSix">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix" aria-expanded="false" aria-controls="collapseSix">
                            <i class="fas fa-lock me-3 text-primary"></i> Şifremi unuttum, ne yapmalıyım?
                        </button>
                    </h2>
                    <div id="collapseSix" class="accordion-collapse collapse" aria-labelledby="headingSix" data-bs-parent="#accordionFAQ">
                        <div class="accordion-body">
                            Giriş ekranında bulunan "Şifremi Unuttum" bağlantısına tıklayarak kayıtlı e-posta adresinize sıfırlama linki gönderebilirsiniz. Eğer e-posta adresinize erişiminiz yoksa, lütfen doğrudan eğitim koçunuzla veya destek ekibimizle iletişime geçiniz.
                        </div>
                    </div>
                </div>

            </div>

            <div class="contact-redirect">
    <h4>Aradığınız cevabı bulamadınız mı?</h4>
    <p style="opacity: 0.9;">Ekibimiz sorularınızı yanıtlamak için hazır. Bize dilediğiniz zaman yazabilirsiniz.</p>
    
    <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/destek/iletisim.php" class="btn-white">
        <i class="fas fa-paper-plane me-2"></i> İletişime Geç
    </a>
</div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include '../footer.php'; ?>