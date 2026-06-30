<div id="content-exams" class="tab-content hidden animate-fadeIn space-y-6">

    <?php
    // --- 1. VERİ HAZIRLIĞI VE GRUPLAMA ---
    $exams_data = isset($exam_results) && is_array($exam_results) ? $exam_results : [];
    $current_sid = isset($sid) ? $sid : 0;
    
    // Grafik için verileri kategorilere göre grupla
    $chart_master_data = [];
    $available_categories = [];

    // Veritabanından gelen veriyi Eskiden -> Yeniye sırala (Grafik için)
    $reversed_exams = array_reverse($exams_data);

    foreach ($reversed_exams as $row) {
        $cat = $row['category'] ?: 'Diğer';
        
        // Bu kategori daha önce listeye eklenmediyse yapısını kur
        if (!isset($chart_master_data[$cat])) {
            $chart_master_data[$cat] = [
                'labels' => [], // Tarihler
                'data'   => [], // Netler
                'names'  => []  // Sınav İsimleri (Tooltip için)
            ];
            if (!in_array($cat, $available_categories)) {
                $available_categories[] = $cat;
            }
        }

        // Verileri ilgili kategoriye ekle
        $chart_master_data[$cat]['labels'][] = date('d.m', strtotime($row['date_taken']));
        $chart_master_data[$cat]['data'][] = (float)$row['total_net'];
        $chart_master_data[$cat]['names'][] = $row['exam_name'];
    }

    // İlk açılışta hangi kategori seçili gelsin? (Varsa TYT, yoksa ilk kategori)
    $default_category = in_array('TYT', $available_categories) ? 'TYT' : ($available_categories[0] ?? '');

    // PHP Dizilerini JS JSON formatına çevir
    $js_master_data = json_encode($chart_master_data);
    
    // Tarih formatlama fonksiyonu
    function formatDateTR($dateStr) {
        if (!$dateStr) return '-';
        return date('d.m.Y', strtotime($dateStr));
    }
    ?>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <span>📈</span> Deneme Analizi
            </h2>
            <p class="text-xs text-slate-400">Öğrencinin kategori bazlı net gelişim grafiği.</p>
        </div>

        <?php if ($current_sid > 0): ?>
        <button type="button" onclick="openExamModal()" 
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-lg shadow-indigo-200 transition transform active:scale-95 flex items-center justify-center gap-2 text-sm w-full md:w-auto">
            <span>➕</span> <span class="hidden md:inline">Yeni Deneme Ekle</span>
            <span class="md:hidden">Ekle</span>
        </button>
        <?php endif; ?>
    </div>

    <?php if (empty($exams_data)): ?>
        <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="text-4xl mb-3 opacity-50">📊</div>
            <h3 class="text-lg font-bold text-slate-700">Henüz Deneme Kaydı Yok</h3>
            <p class="text-slate-400 text-sm mt-1">Analiz grafiğini görmek için ilk denemeyi ekleyin.</p>
        </div>
    <?php else: ?>

        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 relative">
            
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-4 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap gap-2" id="chartFilterContainer">
                    <?php foreach ($available_categories as $cat): 
                        $btnClass = ($cat === $default_category) ? 'bg-slate-800 text-white ring-2 ring-slate-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200';
                    ?>
                    <button type="button" 
                            onclick="switchChartCategory('<?php echo $cat; ?>')" 
                            id="btn-cat-<?php echo $cat; ?>"
                            class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all transform active:scale-95 <?php echo $btnClass; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                    <div class="bg-gray-100 text-slate-600 px-3 py-1.5 rounded-lg text-xs font-bold border border-slate-200">
                        Deneme Sayısı: <span id="stat-count" class="text-slate-900 ml-1">0</span>
                    </div>
                    <div class="bg-black text-white px-3 py-1.5 rounded-lg text-xs font-bold shadow-md">
                        Ortalama Net: <span id="stat-avg" class="ml-1 text-green-400">0.00</span>
                    </div>
                </div>
            </div>

            <div class="w-full h-[250px] md:h-[300px]">
                <canvas id="netGelisimChart"></canvas>
            </div>
        </div>
        
        <div class="bg-slate-50 rounded-2xl border border-slate-200 overflow-hidden">
            
            <div class="hidden md:block">
                <table class="w-full text-sm text-left text-slate-500">
                    <thead class="text-xs text-slate-700 uppercase bg-slate-100 border-b border-slate-200">
                        <tr>
                            <th scope="col" class="px-6 py-4 font-extrabold">Tarih</th>
                            <th scope="col" class="px-6 py-4 font-extrabold">Sınav Adı</th>
                            <th scope="col" class="px-6 py-4 font-extrabold">Kategori</th>
                            <th scope="col" class="px-6 py-4 font-extrabold text-center">Toplam Net</th>
                            <th scope="col" class="px-6 py-4 font-extrabold text-right">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($exams_data as $exam): 
                            $exam_data_json = htmlspecialchars(json_encode($exam), ENT_QUOTES, 'UTF-8');
                            $cat_color = ($exam['category'] == 'TYT') ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 
                                        (($exam['category'] == 'AYT') ? 'bg-pink-50 text-pink-700 border-pink-100' : 
                                        (($exam['category'] == 'LGS') ? 'bg-orange-50 text-orange-700 border-orange-100' : 'bg-gray-50 text-gray-700 border-gray-100'));
                        ?>
                        <tr class="hover:bg-slate-50 transition exam-list-item" data-category="<?php echo $exam['category']; ?>">
                            <td class="px-6 py-4 font-medium text-slate-900 whitespace-nowrap">
                                <?php echo formatDateTR($exam['date_taken']); ?>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-800">
                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="<?php echo $cat_color; ?> text-[10px] font-black px-2 py-0.5 rounded border uppercase tracking-wider">
                                    <?php echo htmlspecialchars($exam['category']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-lg font-black text-slate-700 bg-slate-100 px-3 py-1 rounded-lg border border-slate-200">
                                    <?php echo number_format($exam['total_net'], 2); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right flex justify-end gap-2">
                                <button type="button" 
                                        onclick='openExamModal(<?php echo $exam_data_json; ?>)'
                                        class="text-slate-500 hover:text-indigo-600 p-2 transition">
                                    ✏️
                                </button>
                                <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');" class="inline">
                                    <input type="hidden" name="delete_exam" value="1">
                                    <input type="hidden" name="delete_exam_id" value="<?php echo $exam['id']; ?>">
                                    <button type="submit" class="text-slate-500 hover:text-red-600 p-2 transition">
                                        🗑️
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-3">
                <?php foreach ($exams_data as $exam): 
                    $exam_data_json = htmlspecialchars(json_encode($exam), ENT_QUOTES, 'UTF-8');
                    $cat_color = ($exam['category'] == 'TYT') ? 'bg-indigo-50 text-indigo-600' : 
                                (($exam['category'] == 'AYT') ? 'bg-pink-50 text-pink-600' : 'bg-gray-50 text-gray-600');
                ?>
                <div class="bg-white rounded-xl p-4 shadow-sm border border-slate-200 relative exam-list-item" data-category="<?php echo $exam['category']; ?>">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <span class="<?php echo $cat_color; ?> text-[10px] font-black px-2 py-0.5 rounded mb-1 inline-block">
                                <?php echo htmlspecialchars($exam['category']); ?>
                            </span>
                            <h4 class="font-bold text-slate-800 text-sm">
                                <?php echo htmlspecialchars($exam['exam_name']); ?>
                            </h4>
                            <div class="text-xs text-slate-400 mt-0.5">
                                <?php echo formatDateTR($exam['date_taken']); ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-slate-800"><?php echo number_format($exam['total_net'], 2); ?></div>
                            <div class="text-[9px] font-bold text-slate-400 uppercase">NET</div>
                        </div>
                    </div>

                    <div class="flex border-t border-slate-100 pt-3 mt-2 gap-2">
                        <button type="button" 
                                onclick='openExamModal(<?php echo $exam_data_json; ?>)'
                                class="flex-1 bg-slate-50 text-slate-600 py-2 rounded-lg text-xs font-bold hover:bg-slate-100 transition">
                            Düzenle
                        </button>
                        <form method="POST" onsubmit="return confirm('Silinsin mi?');" class="flex-1">
                            <input type="hidden" name="delete_exam" value="1">
                            <input type="hidden" name="delete_exam_id" value="<?php echo $exam['id']; ?>">
                            <button type="submit" class="w-full bg-red-50 text-red-600 py-2 rounded-lg text-xs font-bold hover:bg-red-100 transition">
                                Sil
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>

    <div id="examModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" id="examModalBackdrop" onclick="closeExamModal()"></div>
        <div class="flex min-h-full items-end justify-center md:items-center p-0 md:p-4 pointer-events-none">
            <div id="examModalPanel" class="pointer-events-auto relative w-full md:max-w-2xl bg-white rounded-t-2xl md:rounded-2xl shadow-2xl transform translate-y-full md:translate-y-0 opacity-0 transition-all duration-300 flex flex-col max-h-[90vh]">
                <div class="bg-slate-800 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center flex-shrink-0">
                    <h3 class="font-bold text-lg" id="examModalTitle">Deneme İşlemleri</h3>
                    <button onclick="closeExamModal()" class="text-slate-400 hover:text-white text-2xl leading-none">&times;</button>
                </div>
                <form method="POST" class="flex flex-col flex-grow overflow-hidden">
                    <input type="hidden" name="add_exam" value="1">
                    <input type="hidden" name="exam_result_id" id="modalExamId" value="">
                    <div class="p-6 overflow-y-auto custom-scrollbar flex-grow space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-1 md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Deneme Adı</label>
                                <input type="text" name="name" id="modalExamName" required class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tarih</label>
                                <input type="date" name="date" id="modalExamDate" required value="<?php echo date('Y-m-d'); ?>" class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Kategori</label>
                                <select name="category" id="modalExamCategory" onchange="loadCategoryTemplate(this.value)" class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                    <option value="TYT">TYT</option>
                                    <option value="AYT">AYT</option>
                                    <option value="LGS">LGS</option>
                                    <option value="Diger">Diğer</option>
                                </select>
                            </div>
                        </div>
                        <hr class="border-slate-100">
                        <div>
                            <div class="flex justify-between items-end mb-3">
                                <h4 class="font-bold text-slate-700">Ders Bazlı Sonuçlar</h4>
                                <button type="button" onclick="addNewRow()" class="text-xs bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-lg font-bold hover:bg-indigo-100 transition">+ Ders Ekle</button>
                            </div>
                            <div id="lessonContainer" class="space-y-2"></div>
                        </div>
                        <div class="bg-slate-900 text-white p-4 rounded-xl flex justify-between items-center shadow-lg">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">TOPLAM NET</span>
                            <div class="text-3xl font-black text-green-400" id="displayTotalNet">0.00</div>
                            <input type="hidden" name="total_net" id="inputTotalNet" value="0">
                        </div>
                    </div>
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex gap-3 flex-shrink-0">
                        <button type="button" onclick="closeExamModal()" class="flex-1 py-3 bg-white border border-slate-300 text-slate-600 rounded-xl font-bold text-sm hover:bg-slate-100 transition">Vazgeç</button>
                        <button type="submit" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // --- GRAFİK KODLARI ---
    let myChart = null;
    // PHP'den gelen kategorize edilmiş veri
    const masterData = <?php echo $js_master_data ?: '{}'; ?>;
    
    // Kategoriye göre renkler
    const catColors = {
        'TYT': { border: '#4f46e5', bg: 'rgba(79, 70, 229, 0.1)' }, // Indigo
        'AYT': { border: '#db2777', bg: 'rgba(219, 39, 119, 0.1)' }, // Pink
        'LGS': { border: '#ea580c', bg: 'rgba(234, 88, 12, 0.1)' },  // Orange
        'default': { border: '#64748b', bg: 'rgba(100, 116, 139, 0.1)' } // Slate
    };

    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('netGelisimChart');
        if (ctx) {
            // Varsayılan kategoriyi yükle
            const defaultCat = '<?php echo $default_category; ?>';
            if(defaultCat && masterData[defaultCat]) {
                switchChartCategory(defaultCat); // renderChart yerine switchChartCategory çağırdım ki stats da yüklensin
            }
        }
    });

    // Kategori Değiştirme Fonksiyonu
    window.switchChartCategory = function(catName) {
        // 1. Buton stillerini güncelle
        document.querySelectorAll('#chartFilterContainer button').forEach(btn => {
            btn.className = 'px-4 py-1.5 rounded-lg text-xs font-bold transition-all transform active:scale-95 bg-slate-100 text-slate-600 hover:bg-slate-200';
        });
        
        const activeBtn = document.getElementById('btn-cat-' + catName);
        if(activeBtn) {
            activeBtn.className = 'px-4 py-1.5 rounded-lg text-xs font-bold transition-all transform active:scale-95 bg-slate-800 text-white ring-2 ring-slate-300';
        }

        // 2. İstatistikleri Güncelle (Yeni Eklenen Kısım)
        updateStats(catName);

        // 3. Listeyi Filtrele (Yeni Eklenen Kısım)
        filterExamList(catName);

        // 4. Grafiği Çiz
        renderChart(catName);
    };

    // İstatistik Güncelleme
    function updateStats(catName) {
        const dataSet = masterData[catName];
        if(!dataSet) return;

        const count = dataSet.data.length;
        // Toplam neti hesapla
        const sum = dataSet.data.reduce((a, b) => a + b, 0);
        // Ortalamayı hesapla (sıfıra bölme hatasını önle)
        const avg = count > 0 ? (sum / count).toFixed(2) : '0.00';

        // DOM'a yaz
        document.getElementById('stat-count').innerText = count;
        document.getElementById('stat-avg').innerText = avg;
    }

    // Liste Filtreleme
    function filterExamList(catName) {
        // Tüm satırları (hem masaüstü tr hem mobil div) seç
        const items = document.querySelectorAll('.exam-list-item');
        
        items.forEach(item => {
            // data-category attribute'unu kontrol et
            if (item.getAttribute('data-category') === catName) {
                item.style.display = ''; // Göster
            } else {
                item.style.display = 'none'; // Gizle
            }
        });
    }

    function renderChart(catName) {
        const ctx = document.getElementById('netGelisimChart');
        const dataSet = masterData[catName];
        if(!dataSet) return;

        // Renk seçim
        const colors = catColors[catName] || catColors['default'];

        // Varsa eski grafiği yok et
        if(myChart) {
            myChart.destroy();
        }

        myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dataSet.labels,
                datasets: [{
                    label: catName + ' Net',
                    data: dataSet.data,
                    borderColor: colors.border,
                    backgroundColor: colors.bg,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: colors.border,
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    tension: 0.3, 
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                const index = context[0].dataIndex;
                                return dataSet.names[index] || '';
                            },
                            label: function(context) {
                                return 'Net: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // --- MODAL İŞLEMLERİ (Aynen korundu) ---
    const TEMPLATES = {
        'TYT': ['Türkçe', 'Sosyal', 'Matematik', 'Fen'],
        'AYT': ['Matematik', 'Fizik', 'Kimya', 'Biyoloji', 'Edebiyat', 'Tarih-1', 'Coğrafya-1'],
        'LGS': ['Türkçe', 'Matematik', 'Fen Bilimleri', 'İnkılap', 'Din', 'İngilizce']
    };

    window.openExamModal = function(data = null) {
        const modal = document.getElementById('examModal');
        const backdrop = document.getElementById('examModalBackdrop');
        const panel = document.getElementById('examModalPanel');
        
        document.getElementById('lessonContainer').innerHTML = '';
        
        if (data) {
            document.getElementById('examModalTitle').innerText = 'Deneme Düzenle';
            document.getElementById('modalExamId').value = data.id;
            document.getElementById('modalExamName').value = data.exam_name;
            document.getElementById('modalExamDate').value = data.date_taken.split(' ')[0];
            document.getElementById('modalExamCategory').value = data.category;
            
            try {
                let details = typeof data.details === 'string' ? JSON.parse(data.details) : data.details;
                if (details && Object.keys(details).length > 0) {
                    Object.keys(details).forEach(key => addNewRow(key, details[key].d || 0, details[key].y || 0));
                } else {
                    loadCategoryTemplate(data.category);
                }
            } catch (e) { loadCategoryTemplate(data.category); }
        } else {
            document.getElementById('examModalTitle').innerText = 'Yeni Deneme Ekle';
            document.getElementById('modalExamId').value = '';
            document.getElementById('modalExamName').value = '';
            document.getElementById('modalExamCategory').value = 'TYT';
            loadCategoryTemplate('TYT');
        }
        calculateTotal();
        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('translate-y-full', 'opacity-0');
        }, 10);
    }

    window.closeExamModal = function() {
        const modal = document.getElementById('examModal');
        const backdrop = document.getElementById('examModalBackdrop');
        const panel = document.getElementById('examModalPanel');
        backdrop.classList.add('opacity-0');
        panel.classList.add('translate-y-full', 'opacity-0');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    window.loadCategoryTemplate = function(cat) {
        const container = document.getElementById('lessonContainer');
        container.innerHTML = '';
        (TEMPLATES[cat] || ['Ders 1']).forEach(l => addNewRow(l));
        calculateTotal();
    }

    window.addNewRow = function(name = '', d = '', y = '') {
        const container = document.getElementById('lessonContainer');
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 animate-fadeIn lesson-row';
        div.innerHTML = `
            <input type="text" name="lesson_name[]" value="${name}" placeholder="Ders Adı" required class="flex-1 min-w-0 border border-slate-300 rounded-lg p-2 text-xs font-bold text-slate-700 outline-none focus:border-indigo-500">
            <div class="flex items-center gap-1">
                <input type="number" name="dogru[]" value="${d}" placeholder="D" min="0" step="1" oninput="calculateTotal()" class="w-12 border border-slate-300 rounded-lg p-2 text-center text-xs font-bold text-green-600 outline-none focus:border-green-500 input-d">
                <input type="number" name="yanlis[]" value="${y}" placeholder="Y" min="0" step="1" oninput="calculateTotal()" class="w-12 border border-slate-300 rounded-lg p-2 text-center text-xs font-bold text-red-500 outline-none focus:border-red-500 input-y">
            </div>
            <div class="w-10 text-center text-xs font-black text-slate-800 row-net">0</div>
            <button type="button" onclick="this.parentElement.remove(); calculateTotal();" class="text-slate-400 hover:text-red-500 p-1">🗑️</button>
        `;
        container.appendChild(div);
        calculateTotal();
    }

    window.calculateTotal = function() {
        let total = 0;
        document.querySelectorAll('.lesson-row').forEach(row => {
            let d = parseFloat(row.querySelector('.input-d').value) || 0;
            let y = parseFloat(row.querySelector('.input-y').value) || 0;
            let net = d - (y * 0.25);
            row.querySelector('.row-net').innerText = net.toFixed(2);
            total += net;
        });
        document.getElementById('displayTotalNet').innerText = total.toFixed(2);
        document.getElementById('inputTotalNet').value = total.toFixed(2);
    }
    </script>
</div>