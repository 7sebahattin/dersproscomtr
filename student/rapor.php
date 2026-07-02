<div id="content-topics" class="tab-content hidden animate-fadeIn font-sans">

<?php
// --- 1. AYARLAR VE VERİTABANI BAĞLANTISI ---
global $pdo; 

// Öğrenci verisi kontrolü
if (!isset($selected_student['id'])) {
    echo '<div class="p-8 text-center text-slate-500 font-bold bg-white rounded-xl shadow-sm border border-slate-200">Verileri görmek için lütfen öğrenci verisinin yüklendiğinden emin olun.</div></div>';
    return;
}

$sid = $selected_student['id'];
$lvl = $selected_student['school_level'] ?? 'Lise';
$studentName = $selected_student['first_name'] . ' ' . $selected_student['last_name'];

// --- 2. ANALİZ SORGULARI ---
$today = date('Y-m-d');
$start_week = date('Y-m-d', strtotime('monday this week'));
$start_month = date('Y-m-01');

// İstatistikleri başlat
$stats = [
    'today_q' => 0, 'week_q' => 0, 'month_q' => 0, 'total_q' => 0,
    'today_t' => 0, 'week_t' => 0, 'month_t' => 0, 'total_t' => 0
];
$subject_data = [];
$graph_dates = [];
$graph_data = [];
$success_rate = 0;
$comp_raw = [];

if ($sid > 0 && isset($pdo)) {
    try {
        // A. GENEL İSTATİSTİKLER
        $sql_stats = "
            SELECT 
                SUM(CASE WHEN action_type='soru' AND date = '$today' AND (status='yapildi' OR status='1') THEN amount ELSE 0 END) as today_q,
                SUM(CASE WHEN action_type='soru' AND date >= '$start_week' AND (status='yapildi' OR status='1') THEN amount ELSE 0 END) as week_q,
                SUM(CASE WHEN action_type='soru' AND date >= '$start_month' AND (status='yapildi' OR status='1') THEN amount ELSE 0 END) as month_q,
                SUM(CASE WHEN action_type='soru' AND (status='yapildi' OR status='1') THEN amount ELSE 0 END) as total_q,
                
                COUNT(CASE WHEN action_type='konu' AND date = '$today' AND (status='yapildi' OR status='1') THEN 1 ELSE NULL END) as today_t,
                COUNT(CASE WHEN action_type='konu' AND date >= '$start_week' AND (status='yapildi' OR status='1') THEN 1 ELSE NULL END) as week_t,
                COUNT(CASE WHEN action_type='konu' AND date >= '$start_month' AND (status='yapildi' OR status='1') THEN 1 ELSE NULL END) as month_t,
                COUNT(CASE WHEN action_type='konu' AND (status='yapildi' OR status='1') THEN 1 ELSE NULL END) as total_t
            FROM schedule_items 
            WHERE student_id = ?
        ";
        $stmt_stats = $pdo->prepare($sql_stats);
        $stmt_stats->execute([$sid]);
        $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

        // B. DERS BAZLI DAĞILIM
        $sql_subjects = "
            SELECT
                COALESCE(es.lesson_name, s.name, si.custom_subject) as subject_name,
                SUM(si.amount) as total_amount
            FROM schedule_items si
            LEFT JOIN education_topics    et ON si.edu_topic_id = et.id
            LEFT JOIN education_subjects  es ON et.subject_id   = es.id
            LEFT JOIN coaching_topics t ON si.topic_id = t.id
            LEFT JOIN coaching_subjects s ON t.subject_id = s.id
            WHERE si.student_id = ?
              AND si.action_type = 'soru'
              AND (si.status = 'yapildi' OR si.status = '1')
              AND (es.lesson_name IS NOT NULL OR s.name IS NOT NULL OR si.custom_subject IS NOT NULL)
            GROUP BY subject_name
            ORDER BY total_amount DESC
        ";
        $stmt_sub = $pdo->prepare($sql_subjects);
        $stmt_sub->execute([$sid]);
        $subject_data = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

        // C. SON 7 GÜNLÜK GRAFİK VERİSİ
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $graph_dates[] = date('d.m', strtotime($d));
            
            $q = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND date = ? AND action_type='soru' AND (status='yapildi' OR status='1')");
            $q->execute([$sid, $d]);
            $val = $q->fetchColumn() ?: 0;
            $graph_data[] = $val;
        }

        // D. GÖREV TAMAMLANMA ORANLARI
        $sql_compliance = "SELECT status, COUNT(*) as count FROM schedule_items WHERE student_id = ? GROUP BY status";
        $stmt_comp = $pdo->prepare($sql_compliance);
        $stmt_comp->execute([$sid]);
        $comp_raw = $stmt_comp->fetchAll(PDO::FETCH_KEY_PAIR);

        $done_tasks = ($comp_raw['yapildi'] ?? 0) + ($comp_raw['1'] ?? 0);
        $total_tasks = array_sum($comp_raw);
        $success_rate = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;

    } catch (Exception $e) {}
}
?>

<style>
    :root {
        --atla-blue-dark: #223488;
        --atla-blue-light: #314595;
        --atla-orange: #ec9731;
        --atla-orange-hover: #d68625;
    }
    .custom-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    
    /* Kart Hover Efekti */
    .kpi-card:hover { border-color: var(--atla-blue-dark); transform: translateY(-2px); }
</style>

<div class="bg-[#223488] rounded-2xl p-6 mb-6 text-white shadow-xl relative overflow-hidden">
    <div class="absolute top-0 right-0 p-4 opacity-10 text-9xl transform rotate-12 translate-x-10 -translate-y-10">📊</div>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative z-10">
        <div>
            <h2 class="text-2xl font-black mb-1 tracking-tight">Genel Durum Raporu</h2>
            <p class="text-blue-200 text-sm flex items-center gap-2 font-medium">
                <span class="w-2 h-2 rounded-full bg-[#ec9731] animate-pulse"></span>
                <?php echo htmlspecialchars($studentName); ?>
            </p>
        </div>
        <div class="flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-2 rounded-xl border border-white/10 shadow-inner">
            <span class="text-xs text-blue-100 mr-2 font-bold uppercase tracking-wider">Seviye</span>
            <span class="bg-white text-[#223488] px-3 py-1 rounded-lg text-xs font-black uppercase tracking-wider shadow-sm"><?php echo htmlspecialchars($lvl); ?></span>
        </div>
    </div>
</div>

<div class="analysis-container pb-10">
    
    <div class="analysis-card mb-6 animate-fadeIn">
        
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            
            <div class="kpi-card bg-white p-4 rounded-xl border border-slate-200 shadow-sm transition-all duration-300">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-3 pb-2 border-b border-slate-50">Bugün</div>
                <div class="flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-[#223488] leading-none"><?php echo number_format($stats['today_q']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Soru</span>
                    </div>
                    <div class="w-px h-8 bg-slate-200"></div>
                    <div class="flex flex-col text-right">
                        <span class="text-2xl font-black text-[#ec9731] leading-none"><?php echo number_format($stats['today_t']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Konu</span>
                    </div>
                </div>
            </div>

            <div class="kpi-card bg-white p-4 rounded-xl border border-slate-200 shadow-sm transition-all duration-300">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-3 pb-2 border-b border-slate-50">Bu Hafta</div>
                <div class="flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-[#223488] leading-none"><?php echo number_format($stats['week_q']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Soru</span>
                    </div>
                    <div class="w-px h-8 bg-slate-200"></div>
                    <div class="flex flex-col text-right">
                        <span class="text-2xl font-black text-[#ec9731] leading-none"><?php echo number_format($stats['week_t']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Konu</span>
                    </div>
                </div>
            </div>

            <div class="kpi-card bg-white p-4 rounded-xl border border-slate-200 shadow-sm transition-all duration-300">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-3 pb-2 border-b border-slate-50">Bu Ay</div>
                <div class="flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-[#223488] leading-none"><?php echo number_format($stats['month_q']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Soru</span>
                    </div>
                    <div class="w-px h-8 bg-slate-200"></div>
                    <div class="flex flex-col text-right">
                        <span class="text-2xl font-black text-[#ec9731] leading-none"><?php echo number_format($stats['month_t']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Konu</span>
                    </div>
                </div>
            </div>

            <div class="kpi-card bg-white p-4 rounded-xl border border-slate-200 shadow-sm transition-all duration-300">
                <div class="text-slate-500 text-xs font-bold uppercase tracking-wider mb-3 pb-2 border-b border-slate-50 flex justify-between">
                    <span>Genel Toplam</span>
                    <span class="text-[#223488] font-black">%<?php echo $success_rate; ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-[#223488] leading-none"><?php echo number_format($stats['total_q']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Soru</span>
                    </div>
                    <div class="w-px h-8 bg-slate-200"></div>
                    <div class="flex flex-col text-right">
                        <span class="text-2xl font-black text-[#ec9731] leading-none"><?php echo number_format($stats['total_t']); ?></span>
                        <span class="text-[9px] font-bold text-slate-400 uppercase mt-1">Konu</span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 h-1 mt-3 rounded-full overflow-hidden">
                    <div class="bg-[#223488] h-full" style="width: <?php echo $success_rate; ?>%"></div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm lg:col-span-2">
                <h3 class="font-bold text-[#223488] mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                    <span class="bg-[#223488]/10 text-[#223488] p-1.5 rounded-lg text-xs">📈</span> 
                    Son 7 Günlük Performans
                </h3>
                <div class="h-[250px] w-full">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                <h3 class="font-bold text-[#223488] mb-4 flex items-center gap-2 text-sm uppercase tracking-wide">
                    <span class="bg-[#ec9731]/10 text-[#ec9731] p-1.5 rounded-lg text-xs">🎯</span> 
                    Görev Durumu
                </h3>
                <div class="flex flex-col items-center justify-center h-[200px] relative">
                     <canvas id="statusChart"></canvas>
                     <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none mt-4">
                         <span class="text-3xl font-black text-[#223488]">%<?php echo $success_rate; ?></span>
                         <span class="text-[10px] text-slate-400 font-bold uppercase">Tamamlanan</span>
                     </div>
                </div>
                <div class="mt-4 space-y-2 text-xs">
                    <div class="flex justify-between items-center border-b border-dashed border-slate-100 pb-1">
                        <span class="flex items-center gap-2 text-slate-600"><span class="w-2 h-2 rounded-full bg-[#ec9731]"></span> Yapıldı</span>
                        <span class="font-bold text-[#223488]"><?php echo $done_tasks; ?></span>
                    </div>
                    <div class="flex justify-between items-center border-b border-dashed border-slate-100 pb-1">
                        <span class="flex items-center gap-2 text-slate-600"><span class="w-2 h-2 rounded-full bg-[#314595]"></span> Yarım/Eksik</span>
                        <span class="font-bold text-[#223488]"><?php echo $comp_raw['yarim'] ?? 0; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="flex items-center gap-2 text-slate-600"><span class="w-2 h-2 rounded-full bg-slate-300"></span> Yapılmadı</span>
                        <span class="font-bold text-[#223488]"><?php echo ($comp_raw['yapilmadi'] ?? 0) + ($comp_raw['bekliyor'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h3 class="font-bold text-[#223488] text-sm uppercase tracking-wide">📚 Ders Bazlı Soru Dağılımı</h3>
                <span class="text-xs font-bold text-[#ec9731] bg-orange-50 px-2 py-1 rounded border border-orange-100"><?php echo count($subject_data); ?> Ders</span>
            </div>
            
            <div class="max-h-[400px] overflow-y-auto custom-scrollbar p-2">
                <?php if(empty($subject_data)): ?>
                    <div class="text-center py-8 text-slate-400 text-sm">Henüz veri girişi yapılmamış.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 p-2">
                        <?php 
                        $max_val = !empty($subject_data) ? $subject_data[0]['total_amount'] : 1; 
                        foreach($subject_data as $sub): 
                            $percent = ($stats['total_q'] > 0) ? ($sub['total_amount'] / $stats['total_q']) * 100 : 0;
                            $width_percent = ($max_val > 0) ? ($sub['total_amount'] / $max_val) * 100 : 0;
                        ?>
                        <div class="group">
                            <div class="flex justify-between items-end mb-1">
                                <span class="font-bold text-xs text-slate-700 uppercase tracking-tight group-hover:text-[#223488] transition-colors"><?php echo $sub['subject_name']; ?></span>
                                <div class="text-right">
                                    <span class="font-black text-sm text-[#223488]"><?php echo number_format($sub['total_amount']); ?></span>
                                    <span class="text-[10px] text-slate-400 font-medium ml-1">(%<?php echo round($percent, 1); ?>)</span>
                                </div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#223488] to-[#314595] transition-all duration-1000 group-hover:from-[#ec9731] group-hover:to-[#f59e0b]" 
                                     style="width: <?php echo $width_percent; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- GRAFİK RENKLERİ: ATLA PALETİ ---
    const weekLabels = <?php echo json_encode($graph_dates); ?>;
    const weekData = <?php echo json_encode($graph_data); ?>;
    
    const ctxWeekly = document.getElementById('weeklyChart');
    if(ctxWeekly) {
        new Chart(ctxWeekly, {
            type: 'line',
            data: {
                labels: weekLabels,
                datasets: [{
                    label: 'Çözülen Soru',
                    data: weekData,
                    borderColor: '#223488', // ATLA Mavisi
                    backgroundColor: 'rgba(34, 52, 136, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ec9731', // ATLA Turuncusu
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            }
        });
    }

    const ctxStatus = document.getElementById('statusChart');
    if(ctxStatus) {
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: ['Yapıldı', 'Yarım', 'Yapılmadı'],
                datasets: [{
                    data: [
                        <?php echo $done_tasks; ?>, 
                        <?php echo $comp_raw['yarim'] ?? 0; ?>, 
                        <?php echo ($comp_raw['yapilmadi'] ?? 0) + ($comp_raw['bekliyor'] ?? 0); ?>
                    ],
                    backgroundColor: [
                        '#ec9731', // Yapıldı: Turuncu
                        '#314595', // Yarım: Açık Mavi
                        '#cbd5e1'  // Yapılmadı: Gri
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>

</div>