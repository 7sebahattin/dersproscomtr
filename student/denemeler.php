<div id="content-exams" class="tab-content hidden animate-fadeIn">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const examDataRaw = <?php echo json_encode($exam_results); ?>;

        // --- GÜNCELLENEN DERS ŞABLONLARI ---
        const TEMPLATES = {
            'TYT': ['Türkçe', 'Tarih', 'Coğrafya', 'Felsefe', 'Din', 'Matematik', 'Fizik', 'Kimya', 'Biyoloji'],
            'AYT': ['Matematik', 'Fizik', 'Kimya', 'Biyoloji', 'Edebiyat', 'Tarih-1', 'Coğrafya-1', 'Tarih-2', 'Coğrafya-2', 'Felsefe', 'Din'],
            'LGS': ['Türkçe', 'Matematik', 'Fen Bilimleri', 'T.C. İnkılap', 'Din Kültürü', 'İngilizce'],
            'Ara Sınıf': ['Türkçe', 'Matematik', 'Fen Bilimleri', 'Sosyal Bilgiler'] // Yeni Eklendi
        };
    </script>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <div class="xl:col-span-4 bg-white rounded-2xl shadow-sm border border-slate-200 p-5 h-fit">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2"><span>🎯</span> Yeni Sonuç Ekle</h3>
            </div>

            <form method="POST" id="examForm" class="space-y-4">
                <input type="hidden" name="add_exam" value="1">

                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Deneme Adı</label>
                    <input type="text" name="name" required
                           class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Örn: Özdebir 1">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Tarih</label>
                        <input type="date" name="date" required value="<?php echo date('Y-m-d'); ?>"
                               class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase mb-1 block">Tür</label>
                        
                        <select name="category" id="examTypeSelector"
                                class="w-full border border-slate-300 rounded-lg p-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                onchange="loadCategoryTemplate(this.value)">
                            <option value="TYT">TYT</option>
                            <option value="AYT">AYT</option>
                            <option value="Ara Sınıf">Ara Sınıf</option> <?php if ($student_level !== 'Lise'): ?> 
                                <option value="LGS">LGS</option> <?php endif; ?>
                        </select>
                        
                    </div>
                </div>

                <hr class="border-slate-100">

                <div>
                    <div class="flex justify-between items-end mb-3">
                        <h4 class="font-bold text-slate-700 text-sm">Ders Bazlı Sonuçlar</h4>
                        <button type="button" onclick="addNewRow()" class="text-xs bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg font-bold hover:bg-blue-100 transition">+ Ders Ekle</button>
                    </div>
                    <div id="lessonContainer" class="space-y-2 max-h-[300px] overflow-y-auto custom-scrollbar p-1"></div>
                </div>

                <div class="bg-slate-900 text-white p-4 rounded-xl flex justify-between items-center shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">TOPLAM NET</span>
                    <div class="text-3xl font-black text-green-400" id="displayTotalNet">0.00</div>
                    <input type="hidden" name="total_net" id="inputTotalNet" value="0">
                </div>

                <button type="submit" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-blue-700 transition text-sm">
                    Kaydet
                </button>
            </form>
        </div>

        <div class="xl:col-span-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
                <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-3">
                    <div class="flex gap-2" id="chartTabContainer">
                        
                        <button type="button" onclick="handleCategoryChange('TYT', this)"
                                class="category-btn active-tab px-5 py-2 rounded-full text-xs font-bold bg-red-500 text-white shadow-md shadow-red-200 transition transform hover:scale-105" data-cat="TYT">
                            TYT
                        </button>
                        
                        <button type="button" onclick="handleCategoryChange('AYT', this)"
                                class="category-btn px-5 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition" data-cat="AYT">
                            AYT
                        </button>

                        <button type="button" onclick="handleCategoryChange('Ara Sınıf', this)"
                                class="category-btn px-5 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition" data-cat="Ara Sınıf">
                            Ara Sınıf
                        </button>

                        <?php if ($student_level !== 'Lise'): ?>
                            <button type="button" onclick="handleCategoryChange('LGS', this)"
                                    class="category-btn px-5 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition" data-cat="LGS">
                                LGS
                            </button>
                        <?php endif; ?>

                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 px-3 py-2 rounded-lg">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Deneme:</span>
                            <span id="stat-count" class="text-sm font-black text-slate-700">0</span>
                        </div>
                        <div class="flex items-center gap-2 bg-slate-900 border border-slate-900 px-3 py-2 rounded-lg shadow-lg shadow-slate-300">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">Ortalama:</span>
                            <span id="stat-avg" class="text-sm font-black text-green-400">0.00</span>
                        </div>
                    </div>
                </div>
                <div class="h-[300px] w-full relative">
                    <canvas id="examChart"></canvas>
                    <div id="chartEmptyState" class="absolute inset-0 hidden items-center justify-center text-center p-6 bg-white/80 backdrop-blur-sm z-10">
                        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-lg">
                            <div class="text-3xl mb-2">📉</div>
                            <div class="font-bold text-slate-700">Veri Bulunamadı</div>
                            <div class="text-xs text-slate-500 mt-1">Bu kategoride henüz deneme çözmedin.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 overflow-hidden">
                <div class="flex justify-between items-center mb-3 border-b border-slate-100 pb-3">
                    <h3 class="font-bold text-sm text-slate-700 uppercase tracking-wider">Sonuç Listesi</h3>
                    <span id="list-filter-label" class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded border border-slate-100">
                        TYT Filtresi Aktif
                    </span>
                </div>
                <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase font-bold sticky top-0 z-10">
                            <tr>
                                <th class="p-3">Tarih</th>
                                <th class="p-3">Deneme</th>
                                <th class="p-3 text-center">Tür</th>
                                <th class="p-3 text-center">Net</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="examResultsBody">
                            <?php foreach ($exam_results as $ex): ?>
                                <tr class="hover:bg-slate-50 transition group exam-row-item" data-category="<?php echo htmlspecialchars($ex['category']); ?>">
                                    <td class="p-3 text-slate-500"><?php echo date('d.m.Y', strtotime($ex['date_taken'])); ?></td>
                                    <td class="p-3 font-bold text-slate-800"><?php echo htmlspecialchars($ex['exam_name']); ?></td>
                                    <td class="p-3 text-center">
                                        <span class="px-2 py-1 rounded text-[10px] font-bold
                                            <?php echo ($ex['category']=='TYT') ? 'bg-blue-50 text-blue-600' : 
                                                      (($ex['category']=='AYT') ? 'bg-purple-50 text-purple-600' : 
                                                      (($ex['category']=='LGS') ? 'bg-orange-50 text-orange-600' : 'bg-sky-50 text-sky-600')); ?>">
                                            <?php echo htmlspecialchars($ex['category']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 font-black text-blue-600 text-center text-sm"><?php echo htmlspecialchars($ex['total_net']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($exam_results)): ?>
                                <tr><td colspan="4" class="p-6 text-center text-slate-400">Henüz deneme sonucu yok.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- GRAFİK DEĞİŞKENLERİ ---
let myChart = null;

// GÜNCELLENEN RENK PALETİ
const catColors = {
    'TYT': { border: '#4f46e5', bg: 'rgba(79, 70, 229, 0.1)' },
    'AYT': { border: '#db2777', bg: 'rgba(219, 39, 119, 0.1)' },
    'LGS': { border: '#ea580c', bg: 'rgba(234, 88, 12, 0.1)' },
    'Ara Sınıf': { border: '#0ea5e9', bg: 'rgba(14, 165, 233, 0.1)' }, // Mavi renk eklendi
    'default': { border: '#64748b', bg: 'rgba(100, 116, 139, 0.1)' }
};

document.addEventListener('DOMContentLoaded', function() {
    const firstBtn = document.querySelector('#chartTabContainer button');
    if(firstBtn) {
        const initialCat = firstBtn.getAttribute('data-cat');
        handleCategoryChange(initialCat, firstBtn);
    }

    const examTypeSelector = document.getElementById('examTypeSelector');
    if(examTypeSelector) {
        loadCategoryTemplate(examTypeSelector.value);
    }
});

function loadCategoryTemplate(cat) {
    const container = document.getElementById('lessonContainer');
    container.innerHTML = '';
    (TEMPLATES[cat] || ['Ders 1']).forEach(l => addNewRow(l));
    calculateTotal();
}

function addNewRow(name = '', d = '', y = '') {
    const container = document.getElementById('lessonContainer');
    const div = document.createElement('div');
    div.className = 'flex items-center gap-2 animate-fadeIn lesson-row mb-2';
    div.innerHTML = `
        <input type="text" name="lesson_name[]" value="${name}" placeholder="Ders Adı" required class="flex-1 min-w-0 border border-slate-300 rounded-lg p-2 text-xs font-bold text-slate-700 outline-none focus:border-blue-500">
        <div class="flex items-center gap-1">
            <input type="number" name="dogru[]" value="${d}" placeholder="D" min="0" step="1" oninput="calculateTotal()" class="w-12 border border-slate-300 rounded-lg p-2 text-center text-xs font-bold text-green-600 outline-none focus:border-green-500 input-d">
            <input type="number" name="yanlis[]" value="${y}" placeholder="Y" min="0" step="1" oninput="calculateTotal()" class="w-12 border border-slate-300 rounded-lg p-2 text-center text-xs font-bold text-red-500 outline-none focus:border-red-500 input-y">
        </div>
        <div class="w-10 text-center text-xs font-black text-slate-800 row-net">0.00</div>
        <button type="button" onclick="this.parentElement.remove(); calculateTotal();" class="text-slate-400 hover:text-red-500 p-1 transition">🗑️</button>
    `;
    container.appendChild(div);
    calculateTotal();
}

function calculateTotal() {
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

function handleCategoryChange(category, btnElement) {
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.className = 'category-btn px-5 py-2 rounded-full text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition';
    });
    if(btnElement) {
        btnElement.className = 'category-btn active-tab px-5 py-2 rounded-full text-xs font-bold bg-red-500 text-white shadow-md shadow-red-200 transition transform scale-105';
    }

    const filteredData = examDataRaw.filter(item => item.category === category);
    
    const count = filteredData.length;
    let totalNet = 0;
    filteredData.forEach(item => totalNet += parseFloat(item.total_net));
    const average = count > 0 ? (totalNet / count).toFixed(2) : '0.00';

    document.getElementById('stat-count').innerText = count;
    document.getElementById('stat-avg').innerText = average;
    document.getElementById('list-filter-label').innerText = category + ' Filtresi Aktif';

    const rows = document.querySelectorAll('.exam-row-item');
    rows.forEach(row => {
        if (row.getAttribute('data-category') === category) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    const emptyState = document.getElementById('chartEmptyState');
    const canvas = document.getElementById('examChart');
    
    if (count === 0) {
        emptyState.style.display = 'flex';
        canvas.style.opacity = '0.1';
        if(myChart) myChart.destroy();
    } else {
        emptyState.style.display = 'none';
        canvas.style.opacity = '1';
        renderStudentChart(category, filteredData);
    }
}

function renderStudentChart(catName, data) {
    const ctx = document.getElementById('examChart');
    const colors = catColors[catName] || catColors['default'];
    const chartData = [...data].reverse();

    const labels = chartData.map(item => {
        const d = new Date(item.date_taken);
        return d.getDate() + '.' + (d.getMonth() + 1);
    });
    
    const values = chartData.map(item => item.total_net);
    const names = chartData.map(item => item.exam_name);

    if(myChart) {
        myChart.destroy();
    }

    myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: catName + ' Net',
                data: values,
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
                    callbacks: {
                        title: function(context) {
                            return names[context[0].dataIndex] || '';
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
</script>