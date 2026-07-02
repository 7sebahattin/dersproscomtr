<?php
// Öğretmen hesaplarında footer tamamen gizlenir (öğrenci/veli sayfaları etkilenmez).
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$hideFooterForTeacher = ($_SESSION['role'] ?? '') === 'teacher';
if (!$hideFooterForTeacher):
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<footer class="bg-gray-50 border-t border-gray-200 pt-16 pb-8 font-sans">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
            
            <div class="col-span-1 md:col-span-1">
                <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/index.php" class="flex items-center gap-2 mb-4 group text-decoration-none">
                    <div class="h-10 w-10 bg-blue-600 text-white rounded-lg flex items-center justify-center font-bold text-lg shadow-md transition transform group-hover:scale-110">
                        DP
                    </div>
                    <span class="text-2xl font-bold text-gray-800">
                        Ders<span class="text-red-600">PROS</span>
                    </span>
                </a>
                <p class="text-gray-500 text-sm leading-relaxed">
                    Hayallerine Giden Yolda Pusulan Biziz! Uzman eğitim kadrosu ile Canlı dersler, denemeler, analizler, haftalık plan ve program tek adreste.
                </p>
            </div>

            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg">Hızlı Erişim</h4>
                <ul class="space-y-3 text-sm text-gray-500">
                    <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/index.php" class="hover:text-red-600 transition-colors duration-200 flex items-center gap-1"><i class="fas fa-angle-right text-xs"></i> Anasayfa</a></li>
                    <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/denemeler.php" class="hover:text-red-600 transition-colors duration-200 flex items-center gap-1"><i class="fas fa-angle-right text-xs"></i> Deneme Sınavları</a></li>
                    
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg">Destek</h4>
                <ul class="space-y-3 text-sm text-gray-500">
                    <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/destek/sss.php" class="hover:text-red-600 transition-colors duration-200 flex items-center gap-1"><i class="fas fa-angle-right text-xs"></i> Sıkça Sorulan Sorular</a></li>

                    <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/destek/kullanim-kosullari.php" class="hover:text-red-600 transition-colors duration-200 flex items-center gap-1"><i class="fas fa-angle-right text-xs"></i> Kullanım Koşulları</a></li>
                    <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/destek/gizlilik.php" class="hover:text-red-600 transition-colors duration-200 flex items-center gap-1"><i class="fas fa-angle-right text-xs"></i> Gizlilik Politikası</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-gray-800 mb-4 text-lg">İletişim</h4>
                <ul class="space-y-4 text-sm text-gray-500">
                    
                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-full bg-red-50 flex items-center justify-center text-red-600 transition group-hover:bg-red-600 group-hover:text-white">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <a href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/destek/iletisim.php" class="hover:text-red-600 font-medium transition-colors">
                            Bize Ulaşın
                        </a>
                    </li>

                    <li class="flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-full bg-pink-50 flex items-center justify-center text-pink-600 transition group-hover:bg-pink-600 group-hover:text-white">
                            <i class="fab fa-instagram"></i>
                        </div>
                        <a href="https://instagram.com/derspros" target="_blank" class="hover:text-pink-600 font-medium transition-colors">
                            @derspros
                        </a>
                    </li>

                    <li class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="flex flex-col">
                            <span>sebahattin@derspros.com.tr</span>
                        </div>
                    </li>

                    <li class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <span>+90 539 502 52 14</span>
                    </li>

                    <li class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center text-orange-500">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <span>Antalya, Türkiye</span>
                    </li>

                </ul>
            </div>
        </div>

        <div class="border-t border-gray-200 pt-8 flex justify-center items-center text-center w-full">
            <p class="text-sm text-gray-400">
                &copy; <?php echo date("Y"); ?> <strong class="text-gray-600">DersPROS</strong>. Tüm hakları saklıdır.
            </p>
        </div>
    </div>
</footer>
<?php endif; ?>