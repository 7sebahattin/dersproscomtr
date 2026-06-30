<?php
require_once 'db.php';
include 'header.php';

// Aktif paketleri veritabanından çek
$stmt = $pdo->query("SELECT * FROM subscription_packages WHERE is_active = 1 ORDER BY price ASC");
$packages = $stmt->fetchAll();
?>

<div class="bg-slate-50 min-h-screen py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-16">
            <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">Fiyatlandırma</h2>
            <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Hedefine Uygun Paketi Seç
            </p>
            <p class="mt-4 max-w-2xl text-xl text-slate-500 mx-auto">
                İster sadece kaynaklara eriş, ister birebir koçluk al. Başarıya giden yolda sana en uygun planı belirle.
            </p>
        </div>

        <?php if (count($packages) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-start">
                
                <?php foreach ($packages as $pkg): ?>
                    <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition duration-300 flex flex-col h-full relative">
                        
                        <?php if ($pkg['price'] >= 2000): ?>
                            <div class="absolute top-0 right-0 bg-indigo-600 text-white text-xs font-bold px-3 py-1 rounded-bl-xl">
                                EN POPÜLER
                            </div>
                        <?php endif; ?>

                        <div class="p-8 bg-white border-b border-slate-50">
                            <h3 class="text-xl font-bold text-slate-800 mb-4"><?php echo htmlspecialchars($pkg['name']); ?></h3>
                            <div class="flex items-baseline">
                                <span class="text-4xl font-extrabold text-indigo-600">
                                    <?php echo number_format($pkg['price'], 0, ',', '.'); ?> ₺
                                </span>
                                <span class="ml-1 text-xl text-slate-500 font-medium">/yıllık</span>
                            </div>
                        </div>
                        
                        <div class="p-8 flex-grow bg-slate-50/50">
                            <ul class="space-y-4">
                                <?php 
                                // Veritabanındaki features metnini satır satır ayır
                                $features = explode("\n", $pkg['features']);
                                foreach ($features as $feature): 
                                    $feature = trim($feature);
                                    if(empty($feature)) continue;
                                ?>
                                    <li class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <p class="ml-3 text-base text-slate-600"><?php echo htmlspecialchars($feature); ?></p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="p-8 pt-0 bg-slate-50/50 mt-auto">
                            <a href="https://wa.me/905555555555?text=Merhaba, <?php echo urlencode($pkg['name']); ?> hakkında bilgi almak istiyorum." 
                               target="_blank"
                               class="w-full block bg-indigo-600 text-white text-center font-bold py-4 rounded-xl shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition transform hover:scale-[1.02]">
                                Satın Al / Bilgi Al
                            </a>
                            <p class="text-xs text-center text-slate-400 mt-3">Güvenli ödeme ve detaylar için WhatsApp'tan ulaşın.</p>
                        </div>

                    </div>
                <?php endforeach; ?>

            </div>
        <?php else: ?>
            
            <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-slate-200">
                <div class="text-6xl mb-4">📦</div>
                <h3 class="text-xl font-bold text-slate-700">Şu an aktif paket bulunmuyor.</h3>
                <p class="text-slate-500 mt-2">Lütfen daha sonra tekrar kontrol edin.</p>
            </div>

        <?php endif; ?>

        <div class="mt-20 border-t border-slate-200 pt-16">
            <h3 class="text-2xl font-bold text-slate-900 text-center mb-10">Sıkça Sorulan Sorular</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-bold text-lg text-slate-800">Ödeme yöntemleri nelerdir?</h4>
                    <p class="mt-2 text-slate-500">Havale/EFT ve kredi kartı ile güvenli ödeme yapabilirsiniz. Detaylar için bizimle iletişime geçin.</p>
                </div>
                <div>
                    <h4 class="font-bold text-lg text-slate-800">İade hakkım var mı?</h4>
                    <p class="mt-2 text-slate-500">Video paketlerinde dijital içerik olduğu için iade yapılamamaktadır. Koçluk hizmetlerinde ilk hafta iade garantimiz vardır.</p>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>