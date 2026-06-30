<?php
require_once 'db.php';
include 'header.php';

// 1. SINIFLARI ÇEK
$grades_stmt = $pdo->query("SELECT * FROM grades ORDER BY name ASC");
$grades = $grades_stmt->fetchAll();

// 2. MATERYALLERİ ÇEK (Hepsini çekip PHP tarafında gruplayacağız)
// Not: Django'da material_type 'video' veya 'pdf' olarak tanımlıydı.
$materials_stmt = $pdo->query("SELECT * FROM materials ORDER BY created_at DESC");
$all_materials = $materials_stmt->fetchAll();

// Materyalleri Sınıf ID'sine göre grupla
$materials_by_grade = [];
foreach ($all_materials as $m) {
    $materials_by_grade[$m['grade_id']][] = $m;
}

// Varsayılan olarak ilk sınıfın ID'sini seçili yap (Eğer sınıf varsa)
$active_grade_id = (count($grades) > 0) ? $grades[0]['id'] : 0;
?>

<div class="bg-slate-50 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-10">
            <h1 class="text-3xl font-extrabold text-slate-800">Ders Kaynakları</h1>
            <p class="mt-2 text-slate-500">
                Ders videoları, konu özetleri ve PDF testlere buradan ulaşabilirsin.
            </p>
        </div>

        <?php if (count($grades) > 0): ?>
            
            <div class="flex flex-wrap justify-center gap-2 mb-8" role="tablist">
                <?php foreach ($grades as $index => $grade): ?>
                    <button onclick="openTab('grade-<?php echo $grade['id']; ?>')" 
                            class="tab-btn px-6 py-3 rounded-full font-bold text-sm transition shadow-sm border
                            <?php echo ($index === 0) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; ?>"
                            data-target="grade-<?php echo $grade['id']; ?>">
                        <?php echo htmlspecialchars($grade['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="relative min-h-[400px]">
                <?php foreach ($grades as $index => $grade): ?>
                    <div id="grade-<?php echo $grade['id']; ?>" class="tab-content <?php echo ($index === 0) ? 'block animate-fadeIn' : 'hidden'; ?>">
                        
                        <?php if (isset($materials_by_grade[$grade['id']]) && count($materials_by_grade[$grade['id']]) > 0): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($materials_by_grade[$grade['id']] as $material): ?>
                                    
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition group">
                                        <div class="p-6">
                                            <div class="flex justify-between items-start mb-4">
                                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl
                                                    <?php echo ($material['material_type'] == 'video') ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'; ?>">
                                                    <?php echo ($material['material_type'] == 'video') ? '▶️' : '📄'; ?>
                                                </div>
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50 px-2 py-1 rounded">
                                                    <?php echo strtoupper($material['material_type']); ?>
                                                </span>
                                            </div>

                                            <h3 class="font-bold text-lg text-slate-800 mb-2 group-hover:text-indigo-600 transition">
                                                <?php echo htmlspecialchars($material['title']); ?>
                                            </h3>
                                            
                                            <?php if (!empty($material['description'])): ?>
                                                <p class="text-sm text-slate-500 line-clamp-2 mb-4">
                                                    <?php echo htmlspecialchars($material['description']); ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="mt-auto pt-4 border-t border-slate-50">
                                                <?php if ($material['material_type'] == 'video' && !empty($material['video_url'])): ?>
                                                    <a href="<?php echo $material['video_url']; ?>" target="_blank" class="flex items-center justify-center w-full py-2 bg-red-50 text-red-600 font-bold rounded-lg hover:bg-red-100 transition text-sm">
                                                        İzle
                                                    </a>
                                                <?php elseif ($material['material_type'] == 'pdf' && !empty($material['pdf_file'])): ?>
                                                    <a href="assets/uploads/<?php echo $material['pdf_file']; ?>" target="_blank" download class="flex items-center justify-center w-full py-2 bg-blue-50 text-blue-600 font-bold rounded-lg hover:bg-blue-100 transition text-sm">
                                                        İndir
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center py-20 bg-white rounded-3xl border border-dashed border-slate-200">
                                <div class="text-4xl mb-4">📂</div>
                                <h3 class="font-bold text-slate-600">Bu sınıfa ait kaynak bulunamadı.</h3>
                                <p class="text-slate-400 text-sm">Yakında eklenecektir.</p>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="text-center py-20">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-xl font-bold text-slate-700">Henüz Sınıf/Kategori Eklenmemiş</h3>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
    function openTab(targetId) {
        // Tüm içerikleri gizle
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Hedef içeriği göster
        const target = document.getElementById(targetId);
        if(target) {
            target.classList.remove('hidden');
            target.classList.add('animate-fadeIn');
        }

        // Buton stillerini güncelle
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if(btn.dataset.target === targetId) {
                // Aktif Buton
                btn.classList.remove('bg-white', 'text-slate-600', 'border-slate-200', 'hover:bg-slate-50');
                btn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
            } else {
                // Pasif Buton
                btn.classList.add('bg-white', 'text-slate-600', 'border-slate-200', 'hover:bg-slate-50');
                btn.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
            }
        });
    }
</script>

<?php include 'footer.php'; ?>