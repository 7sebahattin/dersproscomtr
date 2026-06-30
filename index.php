<?php
// index.php - ANA YÖNLENDİRİCİ (ROUTER) VE SÜRE KONTROLÜ
session_start();
require_once 'db.php';

// Değişkenleri başlat
$is_expired = false;
$no_membership = false;
$currentUser = null;

// 1. KULLANICI GİRİŞ KONTROLÜ VE VERİ DOĞRULAMA
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    
    // Veritabanından güncel durumu çek (Session eski kalmış olabilir, garantiye alıyoruz)
    $stmt = $pdo->prepare("SELECT membership_expires_at, is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if ($currentUser) {
        // A. HESAP PASİFSE (Admin sonradan engellediyse)
        if ($currentUser['is_active'] == 0) {
            session_destroy();
            header("Location: login.php");
            exit;
        }

        // B. SÜRE KONTROLÜ
        if (empty($currentUser['membership_expires_at'])) {
            $no_membership = true; // Tarih hiç girilmemiş
        } else {
            $expireDate = strtotime($currentUser['membership_expires_at']);
            if (time() > $expireDate) {
                $is_expired = true; // Süre dolmuş
            }
        }
    }

    // 2. YÖNLENDİRME MANTIĞI
    // Eğer kullanıcı ADMIN ise -> Her türlü girer.
    // Eğer kullanıcı NORMAL ise -> Süresi dolmamış ve Üyeliği tanımlı olmalı.
    
    if (($_SESSION['role'] === 'admin') || (!$is_expired && !$no_membership)) {
        
        // A. ÖĞRETMEN GİRİŞİ
        if ($_SESSION['role'] === 'teacher') {
            require_once 'teacher_dashboard.php';
            exit;
        }
        
        // B. ÖĞRENCİ GİRİŞİ
        if ($_SESSION['role'] === 'student') {
            require_once 'student_dashboard.php'; // veya kocluk.php dosya adına göre
            exit;
        }

        // C. VELİ GİRİŞİ
        if ($_SESSION['role'] === 'parent') {
            require_once 'veli_paneli.php';
            exit;
        }

        // D. ADMIN GİRİŞİ
        if ($_SESSION['role'] === 'admin') {
            header("Location: /derspros/admin/");
            exit;
        }
    }
}

// 3. GİRİŞ YAPILMAMIŞSA VEYA SÜRE BİTMİŞSE LANDING PAGE GÖSTERİLİR
$pageTitle = "DersPROS - Eğitim Koçluğu";
include 'header.php'; 
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<?php if(isset($_SESSION['user_id']) && ($is_expired || $no_membership)): ?>
    <div class="bg-red-600 text-white text-center py-6 px-4 font-bold shadow-lg relative z-50">
        <div class="max-w-7xl mx-auto flex flex-col items-center justify-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-3xl mb-2"></i>
            <span class="text-lg">
                <?php if($no_membership): ?>
                    Hesabınız aktif ancak üyelik süreniz tanımlanmamıştır. Lütfen kurumla iletişime geçiniz.
                <?php else: ?>
                    Üyelik süreniz dolmuştur (<?php echo date('d.m.Y', strtotime($currentUser['membership_expires_at'])); ?>). Panellere erişim için lütfen yenileyiniz.
                <?php endif; ?>
            </span>
            <a href="logout.php" class="mt-2 bg-white text-red-600 px-4 py-1 rounded-full text-sm font-bold hover:bg-red-50 transition">Çıkış Yap</a>
        </div>
    </div>
<?php endif; ?>
<style>
    /* ATLA Palette - Landing Page Specific */
    :root {
        --atla-blue-dark: #223488;
        --atla-blue-light: #314595;
        --atla-orange: #ec9731;
        --atla-orange-hover: #d68625;
    }

    .bg-atla-gradient { background: linear-gradient(135deg, var(--atla-blue-dark) 0%, var(--atla-blue-light) 100%); }
    .bg-atla-dark { background-color: var(--atla-blue-dark); }
    .bg-atla-orange { background-color: var(--atla-orange); }
    .text-atla-blue { color: var(--atla-blue-dark); }
    .text-atla-orange { color: var(--atla-orange); }
    .border-atla-orange { border-color: var(--atla-orange); }
    
    /* Button Hovers */
    .btn-orange:hover { background-color: var(--atla-orange-hover); transform: translateY(-2px); }
    .btn-white:hover { background-color: #f8fafc; color: var(--atla-orange); transform: translateY(-2px); }
    
    /* Card Hovers */
    .feature-card:hover { transform: translateY(-5px); border-color: var(--atla-blue-light); }
</style>

<div class="relative bg-atla-gradient overflow-hidden">
    <div class="absolute inset-0">
        <img class="w-full h-full object-cover opacity-10" src="https://images.unsplash.com/photo-1517048676732-d65bc937f952?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80" alt="Ders Çalışma">
        <div class="absolute inset-0 bg-[#223488] mix-blend-multiply"></div>
    </div>
    <div class="relative max-w-7xl mx-auto py-24 px-4 sm:py-32 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl lg:text-6xl drop-shadow-lg">
            Hayallerine Giden Yolda <br class="hidden sm:block"> 
            <span class="text-atla-orange">Pusulan Biziz!</span>
        </h1>
        <p class="mt-6 max-w-2xl mx-auto text-xl text-blue-100/90 leading-relaxed">
            Masaya oturduğunda nereden başlayacağını bilemiyor musun? Çok çalışıyorsun ama netlerin artmıyor mu? 
            DersPROS ile çalışma düzenini baştan yaratıyoruz.
        </p>
        
        <?php if(!isset($_SESSION['user_id'])): ?>
        <div class="mt-10 flex flex-col sm:flex-row justify-center gap-4">
            <a href="register.php" class="btn-orange px-8 py-4 border border-transparent text-lg font-bold rounded-xl text-white bg-atla-orange transition-all duration-300 shadow-lg shadow-orange-900/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-user-plus"></i> Hemen Üye Ol
            </a>
            <a href="login.php" class="btn-white px-8 py-4 border border-transparent text-lg font-bold rounded-xl text-atla-blue bg-white transition-all duration-300 shadow-lg flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Giriş Yap
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h2 class="text-base text-atla-orange font-bold tracking-wide uppercase">Neden DersPROS?</h2>
            <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Başarı Tesadüf Değil, <span class="text-atla-blue">Stratejidir</span>
            </p>
        </div>

        <div class="mt-16">
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div class="pt-6">
                    <div class="feature-card flow-root bg-slate-50 rounded-2xl px-6 pb-8 h-full border border-slate-100 shadow-sm transition duration-300">
                        <div class="-mt-6">
                            <div class="inline-flex items-center justify-center p-3 bg-atla-dark rounded-xl shadow-lg shadow-blue-900/20">
                                <i class="fa-solid fa-bullseye text-atla-orange text-2xl"></i>
                            </div>
                            <h3 class="mt-8 text-lg font-bold text-slate-900 tracking-tight">Kişiye Özel Planlama</h3>
                            <p class="mt-5 text-base text-slate-500">
                                Ezbere dayalı değil, öğrencinin seviyesine ve hedeflerine uygun, gerçekçi haftalık programlar hazırlarız.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <div class="feature-card flow-root bg-slate-50 rounded-2xl px-6 pb-8 h-full border border-slate-100 shadow-sm transition duration-300">
                        <div class="-mt-6">
                            <div class="inline-flex items-center justify-center p-3 bg-atla-dark rounded-xl shadow-lg shadow-blue-900/20">
                                <i class="fa-solid fa-chart-line text-atla-orange text-2xl"></i>
                            </div>
                            <h3 class="mt-8 text-lg font-bold text-slate-900 tracking-tight">Performans Analizi</h3>
                            <p class="mt-5 text-base text-slate-500">
                                Deneme sonuçlarına göre detaylı analizler yapar, eksiklerinizi nokta atışı tespit ederiz.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <div class="feature-card flow-root bg-slate-50 rounded-2xl px-6 pb-8 h-full border border-slate-100 shadow-sm transition duration-300">
                        <div class="-mt-6">
                            <div class="inline-flex items-center justify-center p-3 bg-atla-dark rounded-xl shadow-lg shadow-blue-900/20">
                                <i class="fa-solid fa-brain text-atla-orange text-2xl"></i>
                            </div>
                            <h3 class="mt-8 text-lg font-bold text-slate-900 tracking-tight">Stres Yönetimi</h3>
                            <p class="mt-5 text-base text-slate-500">
                                Sınav kaygısını yönetmek ve motivasyonu her zaman yüksek tutmak için psikolojik rehberlik sağlarız.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="bg-atla-dark py-16 relative overflow-hidden">
    <div class="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
        <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#grid)" />
        </svg>
    </div>
    
    <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
        <p class="text-2xl md:text-3xl font-medium text-white italic">
            "Sadece çalışmak yetmez, <span class="text-atla-orange not-italic font-bold">'akıllı'</span> çalışmak gerekir. <br class="hidden sm:block"> Profesyonel eğitim koçluğu ile tanışın."
        </p>
    </div>
</div>

<div id="nasil-isler" class="py-16 bg-slate-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-extrabold text-atla-blue">Sistemimiz Nasıl İşler?</h2>
            <div class="w-24 h-1 bg-atla-orange mx-auto mt-4 rounded-full"></div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="relative bg-white p-6 rounded-2xl shadow-sm border border-slate-100 text-center group hover:-translate-y-2 transition duration-300">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-atla-orange rounded-full flex items-center justify-center text-white font-bold text-xl ring-4 ring-slate-50 shadow-md">1</div>
                <h3 class="mt-8 text-lg font-bold text-slate-900 group-hover:text-atla-blue transition">Tanıma</h3>
                <p class="mt-2 text-sm text-slate-500">Öğrencinin akademik ve bilişsel seviyesini tespit ederiz.</p>
            </div>
            <div class="relative bg-white p-6 rounded-2xl shadow-sm border border-slate-100 text-center group hover:-translate-y-2 transition duration-300">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-atla-orange rounded-full flex items-center justify-center text-white font-bold text-xl ring-4 ring-slate-50 shadow-md">2</div>
                <h3 class="mt-8 text-lg font-bold text-slate-900 group-hover:text-atla-blue transition">Planlama</h3>
                <p class="mt-2 text-sm text-slate-500">Hedefe yönelik, gerçekçi ve verimli bir çalışma takvimi oluştururuz.</p>
            </div>
            <div class="relative bg-white p-6 rounded-2xl shadow-sm border border-slate-100 text-center group hover:-translate-y-2 transition duration-300">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-atla-orange rounded-full flex items-center justify-center text-white font-bold text-xl ring-4 ring-slate-50 shadow-md">3</div>
                <h3 class="mt-8 text-lg font-bold text-slate-900 group-hover:text-atla-blue transition">Takip</h3>
                <p class="mt-2 text-sm text-slate-500">Günlük ve haftalık takiplerle öğrenciyi disiplinde tutarız.</p>
            </div>
            <div class="relative bg-white p-6 rounded-2xl shadow-sm border border-slate-100 text-center group hover:-translate-y-2 transition duration-300">
                <div class="absolute -top-6 left-1/2 transform -translate-x-1/2 w-12 h-12 bg-atla-orange rounded-full flex items-center justify-center text-white font-bold text-xl ring-4 ring-slate-50 shadow-md">4</div>
                <h3 class="mt-8 text-lg font-bold text-slate-900 group-hover:text-atla-blue transition">Raporlama</h3>
                <p class="mt-2 text-sm text-slate-500">Gelişim raporlarını düzenli olarak sizinle paylaşırız.</p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>