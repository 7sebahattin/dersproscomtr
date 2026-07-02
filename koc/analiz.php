<div id="content-topics" class="tab-content hidden animate-fadeIn font-sans">

<?php
// --- 1. AYARLAR VE GÜVENLİK ---
if (!isset($sid) || !$sid) {
    echo '<div class="p-8 text-center text-slate-500 font-bold bg-white rounded-xl shadow-sm border border-slate-200">Verileri görmek için lütfen öğrenci seçiniz.</div></div>';
    return;
}

$lvl = $selected_student['school_level'] ?? 'Lise';
$studentName = isset($selected_student) ? ($selected_student['first_name'] . ' ' . $selected_student['last_name']) : 'Öğrenci';
$jsVeriDeposu = [];

// --- 2. ANALİZ SORGULARI ---
$today = date('Y-m-d');
$start_week = date('Y-m-d', strtotime('monday this week'));
$start_month = date('Y-m-01');

// Varsayılan değerler (try başarısız olursa tanımsız değişken hatası önlenir)
$stats = [
    'today_q' => 0, 'week_q' => 0, 'month_q' => 0, 'total_q' => 0,
    'today_t' => 0, 'week_t' => 0, 'month_t' => 0, 'total_t' => 0
];
$subject_data      = [];
$graph_dates       = [];
$graph_data        = [];
$comp_raw          = [];
$success_rate      = 0;
$dy_data           = [];
$total_correct_all = 0;
$total_wrong_all   = 0;
$total_answered    = 0;

try {
    // custom_subject kolonu yoksa ekle
    $chkCS2 = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'custom_subject'");
    if($chkCS2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN custom_subject VARCHAR(100) DEFAULT NULL");
    }

    // A. GENEL İSTATİSTİKLER
    $sql_stats = "
        SELECT
            SUM(CASE WHEN action_type='soru' AND date = '$today' AND status='yapildi' THEN amount ELSE 0 END) as today_q,
            SUM(CASE WHEN action_type='soru' AND date >= '$start_week' AND status='yapildi' THEN amount ELSE 0 END) as week_q,
            SUM(CASE WHEN action_type='soru' AND date >= '$start_month' AND status='yapildi' THEN amount ELSE 0 END) as month_q,
            SUM(CASE WHEN action_type='soru' AND status='yapildi' THEN amount ELSE 0 END) as total_q,
            COUNT(CASE WHEN action_type='konu' AND date = '$today' AND status='yapildi' THEN 1 ELSE NULL END) as today_t,
            COUNT(CASE WHEN action_type='konu' AND date >= '$start_week' AND status='yapildi' THEN 1 ELSE NULL END) as week_t,
            COUNT(CASE WHEN action_type='konu' AND date >= '$start_month' AND status='yapildi' THEN 1 ELSE NULL END) as month_t,
            COUNT(CASE WHEN action_type='konu' AND status='yapildi' THEN 1 ELSE NULL END) as total_t
        FROM schedule_items WHERE student_id = ?
    ";
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute([$sid]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC) ?: $stats;

    // B. DERS BAZLI DAĞILIM
    // Önce YENİ müfredat (edu_topic_id) -> yoksa eski koçluk (topic_id) -> yoksa manuel (custom_subject)
    $sql_subjects = "
        SELECT COALESCE(es.lesson_name, s.name, si.custom_subject) as subject_name, SUM(si.amount) as total_amount
        FROM schedule_items si
        LEFT JOIN education_topics    et ON si.edu_topic_id = et.id
        LEFT JOIN education_subjects  es ON et.subject_id   = es.id
        LEFT JOIN coaching_topics t ON si.topic_id = t.id
        LEFT JOIN coaching_subjects s ON t.subject_id = s.id
        WHERE si.student_id = ? AND si.action_type = 'soru' AND si.status = 'yapildi'
          AND (es.lesson_name IS NOT NULL OR s.name IS NOT NULL OR si.custom_subject IS NOT NULL)
        GROUP BY subject_name ORDER BY total_amount DESC
    ";
    $stmt_sub = $pdo->prepare($sql_subjects);
    $stmt_sub->execute([$sid]);
    $subject_data = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

    // C. SON 7 GÜNLÜK GRAFİK
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $graph_dates[] = date('d.m', strtotime($d));
        $q = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND date = ? AND action_type='soru' AND status='yapildi'");
        $q->execute([$sid, $d]);
        $graph_data[] = $q->fetchColumn() ?: 0;
    }

    // D. BAŞARI ORANI
    $sql_compliance = "SELECT status, COUNT(*) as count FROM schedule_items WHERE student_id = ? GROUP BY status";
    $stmt_comp = $pdo->prepare($sql_compliance);
    $stmt_comp->execute([$sid]);
    $comp_raw     = $stmt_comp->fetchAll(PDO::FETCH_KEY_PAIR);
    $total_tasks  = array_sum($comp_raw);
    $done_tasks   = $comp_raw['yapildi'] ?? 0;
    $success_rate = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;

} catch (Exception $e) {}

// E. DOĞRU / YANLIŞ — ayrı try: sütunlar eksikse diğer veriler etkilenmesin
try {
    // GROUP BY alias bazı MySQL modlarında çalışmaz, tam ifade kullanılıyor
    $sql_dy = "
        SELECT
            COALESCE(es.lesson_name, s.name, si.custom_subject, '(Belirtilmemiş)') AS subject_name,
            SUM(COALESCE(si.correct_count, 0)) AS total_correct,
            SUM(COALESCE(si.wrong_count, 0))   AS total_wrong
        FROM schedule_items si
        LEFT JOIN education_topics    et ON si.edu_topic_id = et.id
        LEFT JOIN education_subjects  es ON et.subject_id   = es.id
        LEFT JOIN coaching_topics t  ON si.topic_id  = t.id
        LEFT JOIN coaching_subjects s ON t.subject_id = s.id
        WHERE si.student_id = ?
          AND si.action_type = 'soru'
          AND (si.status = 'yapildi' OR si.status = 'yarim')
          AND (COALESCE(si.correct_count, 0) + COALESCE(si.wrong_count, 0)) > 0
        GROUP BY COALESCE(es.lesson_name, s.name, si.custom_subject, '(Belirtilmemiş)')
        ORDER BY (SUM(COALESCE(si.correct_count, 0)) + SUM(COALESCE(si.wrong_count, 0))) DESC
    ";
    $stmt_dy = $pdo->prepare($sql_dy);
    $stmt_dy->execute([$sid]);
    $dy_data           = $stmt_dy->fetchAll(PDO::FETCH_ASSOC);
    $total_correct_all = array_sum(array_column($dy_data, 'total_correct'));
    $total_wrong_all   = array_sum(array_column($dy_data, 'total_wrong'));
    $total_answered    = $total_correct_all + $total_wrong_all;
} catch (Exception $e) {}

// F. HEDEF - YAPILAN FARKI
$gap_data      = [];
$total_hedef   = 0;
$total_yapilan = 0;
$gap_error     = '';
try {
    // Gerekli kolonlar var mı, yoksa ekle
    $chkTA = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'target_amount'");
    if($chkTA->rowCount() == 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN target_amount INT DEFAULT NULL");
    }
    $chkCS = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'custom_subject'");
    if($chkCS->rowCount() == 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN custom_subject VARCHAR(100) DEFAULT NULL");
    }

    // Kategori (TYT/AYT/LGS) + ders adı ile gruplama
    $sql_gap = "
        SELECT
            COALESCE(es.lesson_name, s.name, si.custom_subject, '(Belirtilmemiş)') AS subject_name,
            COALESCE(ec.name, s.category, '')                               AS category,
            SUM(CASE WHEN si.target_amount IS NOT NULL THEN si.target_amount ELSE si.amount END) AS hedef_toplam,
            SUM(si.amount) AS yapilan_toplam,
            COUNT(*) AS islem_sayisi
        FROM schedule_items si
        LEFT JOIN education_topics     et ON si.edu_topic_id = et.id
        LEFT JOIN education_subjects   es ON et.subject_id   = es.id
        LEFT JOIN education_categories ec ON es.category_id  = ec.id
        LEFT JOIN coaching_topics t   ON si.topic_id   = t.id
        LEFT JOIN coaching_subjects s ON t.subject_id  = s.id
        WHERE si.student_id = ?
          AND si.action_type = 'soru'
          AND si.status IN ('yapildi','yarim')
        GROUP BY
            COALESCE(es.lesson_name, s.name, si.custom_subject, '(Belirtilmemiş)'),
            COALESCE(ec.name, s.category, '')
        HAVING SUM(CASE WHEN si.target_amount IS NOT NULL THEN si.target_amount ELSE si.amount END)
               != SUM(si.amount)
        ORDER BY ABS(
            SUM(CASE WHEN si.target_amount IS NOT NULL THEN si.target_amount ELSE si.amount END)
            - SUM(si.amount)
        ) DESC
    ";
    $stmt_gap = $pdo->prepare($sql_gap);
    $stmt_gap->execute([$sid]);
    $gap_data = $stmt_gap->fetchAll(PDO::FETCH_ASSOC);

    // Genel hedef / yapılan toplamı
    $sql_hy = "
        SELECT
            SUM(CASE WHEN target_amount IS NOT NULL THEN target_amount ELSE amount END) AS hedef_toplam,
            SUM(amount) AS yapilan_toplam
        FROM schedule_items
        WHERE student_id = ? AND action_type = 'soru' AND status IN ('yapildi','yarim')
    ";
    $stmt_hy = $pdo->prepare($sql_hy);
    $stmt_hy->execute([$sid]);
    $hy = $stmt_hy->fetch(PDO::FETCH_ASSOC);
    $total_hedef   = (int)($hy['hedef_toplam']   ?? 0);
    $total_yapilan = (int)($hy['yapilan_toplam'] ?? 0);

} catch (Exception $e) {
    $gap_error = $e->getMessage();
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
            <h2 class="text-2xl font-black mb-1 tracking-tight">Performans Analizi</h2>
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

<div class="flex flex-wrap justify-center gap-3 mb-8 sticky top-2 z-40 bg-slate-50/90 backdrop-blur-sm p-2 rounded-xl border border-slate-200 shadow-sm mx-auto max-w-fit">
    <button onclick="filterAnalysis('RAPOR')" id="btn-analiz-RAPOR"
        class="analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-md bg-[#ec9731] text-white ring-2 ring-orange-200 ring-offset-1 transform scale-105">
        📈 GENEL RAPOR
    </button>
    
    <?php if($lvl == 'Lise' || $lvl == 'Mezun'): ?>
        <button onclick="filterAnalysis('TYT')" id="btn-analiz-TYT" class="analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-sm bg-white text-[#223488] hover:bg-blue-50 border border-[#223488]/20">📚 TYT</button>
        <button onclick="filterAnalysis('AYT')" id="btn-analiz-AYT" class="analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-sm bg-white text-[#223488] hover:bg-blue-50 border border-[#223488]/20">🎓 AYT</button>
    <?php else: ?>
        <button onclick="filterAnalysis('LGS')" id="btn-analiz-LGS" class="analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-sm bg-white text-[#223488] hover:bg-blue-50 border border-[#223488]/20">🎯 LGS</button>
        <button onclick="filterAnalysis('Ara Sınıf')" id="btn-analiz-ARASINIF" class="analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-sm bg-white text-[#223488] hover:bg-blue-50 border border-[#223488]/20">🎒 Ara Sınıf</button>
    <?php endif; ?>
</div>

<div class="analysis-container pb-10">
    
    <div class="analysis-card mb-6 animate-fadeIn" data-category="RAPOR">
        
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
                        <span class="font-bold text-[#223488]"><?php echo $comp_raw['yapildi'] ?? 0; ?></span>
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
                            $percent = ($sub['total_amount'] / $stats['total_q']) * 100;
                            $width_percent = ($sub['total_amount'] / $max_val) * 100;
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

        <!-- DOĞRU / YANLIŞ + HEDEF YAPILAN YAN YANA -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4 items-start">

        <!-- DOĞRU / YANLIŞ DAĞILIMI -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex flex-wrap justify-between items-center gap-2">
                <h3 class="font-bold text-[#223488] text-sm uppercase tracking-wide">✅ Ders Bazlı Doğru / Yanlış Dağılımı</h3>
                <div class="flex items-center gap-3">
                    <?php if($total_answered > 0): ?>
                    <span class="text-[10px] font-bold text-green-700 bg-green-50 px-2 py-1 rounded border border-green-100 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span> <?php echo $total_correct_all; ?> Doğru
                    </span>
                    <span class="text-[10px] font-bold text-red-700 bg-red-50 px-2 py-1 rounded border border-red-100 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-red-400 inline-block"></span> <?php echo $total_wrong_all; ?> Yanlış
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="max-h-[400px] overflow-y-auto custom-scrollbar p-4">
                <?php if(empty($dy_data)): ?>
                    <div class="text-center py-8 text-slate-400 text-sm">Doğru/Yanlış verisi girilmemiş.</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 gap-y-4">
                        <?php foreach($dy_data as $row):
                            $c = (int)$row['total_correct'];
                            $w = (int)$row['total_wrong'];
                            $total = $c + $w;
                            $c_pct = $total > 0 ? round(($c / $total) * 100) : 0;
                            $w_pct = 100 - $c_pct;
                        ?>
                        <div class="group">
                            <div class="flex justify-between items-end mb-1">
                                <span class="font-bold text-xs text-slate-700 uppercase tracking-tight group-hover:text-[#223488] transition-colors">
                                    <?php echo htmlspecialchars($row['subject_name']); ?>
                                </span>
                                <div class="flex items-center gap-2 text-right">
                                    <span class="text-[10px] font-bold text-green-600"><?php echo $c; ?> Doğru</span>
                                    <span class="text-[10px] text-slate-300">/</span>
                                    <span class="text-[10px] font-bold text-red-500"><?php echo $w; ?> Yanlış</span>
                                </div>
                            </div>
                            <!-- Stacked bar -->
                            <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden flex">
                                <?php if($c_pct > 0): ?>
                                <div class="h-full bg-gradient-to-r from-green-400 to-green-500 transition-all duration-1000 rounded-l-full"
                                     style="width:<?php echo $c_pct; ?>%" title="Doğru: <?php echo $c; ?>"></div>
                                <?php endif; ?>
                                <?php if($w_pct > 0): ?>
                                <div class="h-full bg-gradient-to-r from-red-400 to-red-500 transition-all duration-1000 <?php echo $c_pct == 0 ? 'rounded-full' : 'rounded-r-full'; ?>"
                                     style="width:<?php echo $w_pct; ?>%" title="Yanlış: <?php echo $w; ?>"></div>
                                <?php endif; ?>
                            </div>
                            <div class="flex justify-between mt-0.5">
                                <span class="text-[9px] text-green-500 font-bold">%<?php echo $c_pct; ?> Doğru</span>
                                <span class="text-[9px] text-red-400 font-bold">%<?php echo $w_pct; ?> Yanlış</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- HEDEF / YAPILAN FARKI -->
        <?php
        $toplam_eksik = $total_hedef - $total_yapilan;
        $genel_tamamlanma = $total_hedef > 0 ? round(($total_yapilan / $total_hedef) * 100) : 100;
        ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50 flex flex-wrap justify-between items-center gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-bold text-[#223488] text-sm uppercase tracking-wide">📊 Hedef–Yapılan Karşılaştırması</h3>
                    <?php
                    $eksik_count = 0; $fazla_count = 0;
                    foreach($gap_data as $gd) {
                        if((int)$gd['yapilan_toplam'] < (int)$gd['hedef_toplam']) $eksik_count++;
                        else $fazla_count++;
                    }
                    if($eksik_count > 0): ?>
                    <span class="text-[10px] font-bold text-red-700 bg-red-50 px-2 py-1 rounded border border-red-100">⬇ <?php echo $eksik_count; ?> Ders Eksik</span>
                    <?php endif; if($fazla_count > 0): ?>
                    <span class="text-[10px] font-bold text-green-700 bg-green-50 px-2 py-1 rounded border border-green-100">⬆ <?php echo $fazla_count; ?> Ders Fazla</span>
                    <?php endif; ?>
                </div>
                <?php if($total_hedef > 0): ?>
                <div class="flex items-center gap-3">
                    <span class="text-[10px] text-slate-500 font-bold">Genel Tamamlanma</span>
                    <div class="flex items-center gap-2">
                        <div class="w-24 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full <?php echo $genel_tamamlanma >= 80 ? 'bg-green-400' : ($genel_tamamlanma >= 50 ? 'bg-orange-400' : 'bg-red-400'); ?>"
                                 style="width:<?php echo $genel_tamamlanma; ?>%"></div>
                        </div>
                        <span class="text-[11px] font-black <?php echo $genel_tamamlanma >= 80 ? 'text-green-600' : ($genel_tamamlanma >= 50 ? 'text-orange-500' : 'text-red-500'); ?>">
                            %<?php echo $genel_tamamlanma; ?>
                        </span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-400"><?php echo number_format($total_yapilan); ?> / <?php echo number_format($total_hedef); ?> soru</span>
                </div>
                <?php endif; ?>
            </div>

            <?php if($gap_error): ?>
                <div class="mx-4 mt-4 bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-700 font-mono mb-3">⚠️ Sorgu hatası: <?php echo htmlspecialchars($gap_error); ?></div>
            <?php endif; ?>
            <?php if(empty($gap_data)): ?>
                <div class="text-center py-8 px-4">
                    <div class="text-3xl mb-2">🎉</div>
                    <p class="text-slate-500 font-bold text-sm">Hedef ile yapılan arasında fark bulunamadı.</p>
                    <?php if($total_hedef > 0): ?>
                    <p class="text-slate-400 text-xs mt-1">Toplam: <?php echo $total_hedef; ?> hedef / <?php echo $total_yapilan; ?> yapılan</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Tablo başlığı — sabit, scroll dışında -->
                <div class="bg-slate-50 grid grid-cols-12 gap-2 px-6 py-2 border-b-2 border-slate-200 text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                    <div class="col-span-4">Ders / Alan</div>
                    <div class="col-span-2 text-right">Hedef</div>
                    <div class="col-span-2 text-right">Yapılan</div>
                    <div class="col-span-2 text-right">Fark</div>
                    <div class="col-span-2 text-right">Durum</div>
                </div>
                <div class="max-h-[360px] overflow-y-auto custom-scrollbar">
                    <div class="flex flex-col gap-3 px-4 pb-4 pt-3">
                    <?php foreach($gap_data as $gap):
                        $h = (int)$gap['hedef_toplam'];
                        $y = (int)$gap['yapilan_toplam'];
                        $fark = $y - $h; // pozitif = fazla, negatif = eksik
                        $isFazla = $fark > 0;
                        $gapCat = strtoupper(trim($gap['category'] ?? ''));
                        // Kategori badge rengi
                        $catStyle = match($gapCat) {
                            'TYT'      => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                            'AYT'      => 'bg-purple-50 text-purple-700 border-purple-200',
                            'LGS'      => 'bg-orange-50 text-orange-700 border-orange-200',
                            'ARA SINIF','ARASINIF' => 'bg-teal-50 text-teal-700 border-teal-200',
                            default    => ''
                        };
                        $tamamPct = $h > 0 ? min(round(($y / $h) * 100), 100) : 100;

                        if($isFazla) {
                            // Fazla yapıldı → yeşil
                            $farkText   = '+' . number_format($fark);
                            $farkColor  = 'text-green-600';
                            $barColor   = 'bg-green-400';
                            $bgBadge    = 'bg-green-50 border-green-200 text-green-700';
                            $badgeLabel = '⬆ Fazla';
                            $hint       = 'Hedef artırılabilir';
                        } elseif($tamamPct < 50) {
                            $farkText   = number_format($fark); // negatif
                            $farkColor  = 'text-red-600';
                            $barColor   = 'bg-red-400';
                            $bgBadge    = 'bg-red-50 border-red-200 text-red-700';
                            $badgeLabel = '⬇ Çok Eksik';
                            $hint       = 'Takip gerekli';
                        } elseif($tamamPct < 75) {
                            $farkText   = number_format($fark);
                            $farkColor  = 'text-orange-500';
                            $barColor   = 'bg-orange-400';
                            $bgBadge    = 'bg-orange-50 border-orange-200 text-orange-700';
                            $badgeLabel = '⬇ Eksik';
                            $hint       = 'İlerleme yavaş';
                        } else {
                            $farkText   = number_format($fark);
                            $farkColor  = 'text-yellow-600';
                            $barColor   = 'bg-yellow-400';
                            $bgBadge    = 'bg-yellow-50 border-yellow-200 text-yellow-700';
                            $badgeLabel = '⬇ Az Eksik';
                            $hint       = 'Neredeyse tamam';
                        }
                    ?>
                    <div class="group bg-slate-50/50 rounded-lg p-2 hover:bg-white hover:shadow-sm transition-all border border-transparent hover:border-slate-200">
                        <div class="grid grid-cols-12 gap-2 items-center mb-2">
                            <div class="col-span-4">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold text-xs text-slate-700 group-hover:text-[#223488] transition-colors leading-tight truncate">
                                        <?php echo htmlspecialchars($gap['subject_name']); ?>
                                    </span>
                                    <?php if($gapCat && $catStyle): ?>
                                    <span class="text-[8px] font-black px-1.5 py-0.5 rounded border <?php echo $catStyle; ?> flex-shrink-0">
                                        <?php echo $gapCat; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[9px] text-slate-400"><?php echo $gap['islem_sayisi']; ?> işlem · <?php echo $hint; ?></span>
                            </div>
                            <div class="col-span-2 text-right">
                                <span class="text-xs font-bold text-slate-500"><?php echo number_format($h); ?></span>
                            </div>
                            <div class="col-span-2 text-right">
                                <span class="text-xs font-bold text-[#223488]"><?php echo number_format($y); ?></span>
                            </div>
                            <div class="col-span-2 text-right">
                                <span class="text-xs font-black <?php echo $farkColor; ?>"><?php echo $farkText; ?></span>
                            </div>
                            <div class="col-span-2 text-right">
                                <span class="text-[9px] font-black px-1.5 py-0.5 rounded border <?php echo $bgBadge; ?>"><?php echo $badgeLabel; ?></span>
                            </div>
                        </div>
                        <!-- İlerleme çubuğu -->
                        <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $barColor; ?> rounded-full transition-all duration-700"
                                 style="width:<?php echo $tamamPct; ?>%"></div>
                        </div>
                        <div class="flex justify-between mt-0.5">
                            <span class="text-[9px] <?php echo $farkColor; ?> font-bold"><?php echo $tamamPct; ?>% tamamlandı</span>
                            <?php if($isFazla): ?>
                            <span class="text-[9px] text-green-500 font-bold">Hedef aşıldı 🎯</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div><!-- /overflow scroll -->
            <?php endif; ?>
        </div>

        </div><!-- /yan yana grid -->

    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php 
        // Veri güvenliği
        $progressDataSafe = $progress_data ?? [];
        
        foreach ($progressDataSafe as $sub):
            $cat = !empty($sub['category']) ? $sub['category'] : 'Genel';
            if($lvl == 'Ortaokul' && $cat == 'Genel') $cat = 'LGS';

            $topics = $sub['topics'] ?? [];
            
            $qTotal = 0; $tTotal = 0;
            if (isset($sub['q_total'])) {
                $qTotal = (int)$sub['q_total'];
                $tTotal = (int)($sub['t_total'] ?? 0);
            } else {
                foreach ($topics as $tpItem) {
                    $qTotal += (int)($tpItem['q_count'] ?? 0);
                    $tTotal += (int)($tpItem['t_count'] ?? 0);
                }
            }

            $subjectName = $sub['subject_name'] ?? ($sub['name'] ?? 'Ders');

            // Doğru/Yanlış toplamı
            $subCorrect = 0; $subWrong = 0;
            foreach ($topics as $tpItem) {
                foreach ($tpItem['history'] ?? [] as $h) {
                    if (($h['action_type'] ?? '') === 'soru') {
                        $subCorrect += (int)($h['correct_count'] ?? 0);
                        $subWrong   += (int)($h['wrong_count']   ?? 0);
                    }
                }
            }

            // Hiç işlem kaydı olmayan dersi gizle
            $hasAnyHistory = false;
            foreach ($topics as $tpItem) {
                if (!empty($tpItem['history'])) { $hasAnyHistory = true; break; }
            }
            if (!$hasAnyHistory) continue;
        ?>

        <div class="analysis-card group hidden" data-category="<?php echo htmlspecialchars($cat); ?>">
            <div class="bg-white rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 border border-slate-200 overflow-hidden h-full flex flex-col group-hover:border-[#223488]/30">

                <div class="bg-slate-50 border-b border-slate-100 p-4 flex justify-between items-center relative overflow-hidden">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#223488]"></div>
                    <div>
                        <h3 class="text-lg font-black text-[#223488]"><?php echo htmlspecialchars($subjectName); ?></h3>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-[#ec9731] bg-orange-50 inline-block px-2 py-0.5 rounded mt-1 border border-orange-100">
                            <?php echo htmlspecialchars($cat); ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 justify-end">
                        <?php if($subCorrect > 0 || $subWrong > 0): ?>
                        <span class="flex items-center gap-1 bg-green-50 text-green-700 text-[10px] font-bold px-2 py-1 rounded-full border border-green-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span><?php echo $subCorrect; ?> Doğru
                        </span>
                        <span class="flex items-center gap-1 bg-red-50 text-red-600 text-[10px] font-bold px-2 py-1 rounded-full border border-red-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span><?php echo $subWrong; ?> Yanlış
                        </span>
                        <?php endif; ?>
                        <div class="flex gap-2 text-center">
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">Soru</div>
                                <div class="text-lg font-black text-slate-700"><?php echo $qTotal; ?></div>
                            </div>
                            <div class="w-px bg-slate-200"></div>
                            <div>
                                <div class="text-[10px] text-slate-400 uppercase font-bold">Konu</div>
                                <div class="text-lg font-black text-slate-700"><?php echo $tTotal; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-grow max-h-80 overflow-y-auto custom-scrollbar p-0 bg-white">
                    <?php if (empty($topics)): ?>
                    <div class="flex flex-col items-center justify-center h-40 text-slate-400">
                        <div class="text-3xl mb-2 opacity-30 grayscale">📝</div>
                        <p class="text-xs font-medium">Henüz konu kaydı yok</p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-slate-50">
                        <?php 
                        $topicIndex = 0;
                        foreach ($topics as $t):
                            $topicName = $t['name'] ?? 'Konu';
                            $qCount = (int)($t['q_count'] ?? 0);
                            $tCount = (int)($t['t_count'] ?? 0);
                            $history = $t['history'] ?? [];
                            $hasHistory = !empty($history);
                            
                            $uniqueRowId = 'row_' . md5($cat . $subjectName . $topicIndex . rand(1000,9999));
                            
                            if ($hasHistory) {
                                $jsVeriDeposu[$uniqueRowId] = $history;
                            }
                            $topicIndex++;
                        ?>
                        <div class="topic-item-clickable p-3 hover:bg-blue-50/50 transition-colors cursor-pointer flex items-center justify-between group/item"
                             data-row-id="<?php echo $uniqueRowId; ?>"
                             data-topic-name="<?php echo htmlspecialchars($topicName); ?>">
                            
                            <div class="flex-1 min-w-0 pr-3">
                                <h4 class="text-sm font-semibold text-slate-700 truncate group-hover/item:text-[#223488] transition-colors">
                                    <?php echo htmlspecialchars($topicName); ?>
                                </h4>
                                <p class="text-[10px] text-slate-400">
                                    <?php echo $hasHistory ? count($history).' işlem kaydı' : 'İşlem yok'; ?>
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <?php if($qCount > 0): ?>
                                <span class="bg-blue-50 text-[#223488] text-[10px] font-bold px-2 py-1 rounded border border-blue-100 min-w-[35px] text-center">
                                    <?php echo $qCount; ?>s
                                </span>
                                <?php endif; ?>
                                <?php if($tCount > 0): ?>
                                <span class="bg-orange-50 text-[#ec9731] text-[10px] font-bold px-2 py-1 rounded border border-orange-100 min-w-[35px] text-center">
                                    <?php echo $tCount; ?>k
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($progressDataSafe)): ?>
        <div class="col-span-full hidden" data-category="TYT">
            <div class="bg-white rounded-xl p-8 text-center border border-dashed border-slate-300">
                <span class="text-4xl grayscale opacity-50">📭</span>
                <p class="text-slate-500 mt-2 font-medium">Bu kategori için henüz veri oluşmadı.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="topicDetailsModal" class="fixed inset-0 z-[99999] flex items-center justify-center bg-[#223488]/20 backdrop-blur-sm p-4 hidden transition-opacity duration-300">
    <div class="bg-white rounded-2xl w-full max-w-xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-300 border border-slate-200" id="topicModalContent">
        <div class="bg-[#223488] p-4 text-white shadow-md">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-lg flex items-center gap-2">
                    <span class="bg-white/10 p-1.5 rounded-lg text-base border border-white/10">📜</span>
                    <span id="topicModalTitle">Konu Detayı</span>
                </h3>
                <button type="button" onclick="closeTopicDetails()" class="bg-white/10 hover:bg-white/20 text-white rounded-full w-8 h-8 flex items-center justify-center transition">✕</button>
            </div>
            <!-- Stats satırı (JS tarafından doldurulur) -->
            <div id="topicModalStats" class="flex flex-wrap items-center gap-2 mt-2 hidden">
                <span id="topicModalDogruBadge" class="flex items-center gap-1 bg-green-500/20 text-green-200 text-[11px] font-bold px-2.5 py-1 rounded-full border border-green-400/30"></span>
                <span id="topicModalYanlisBadge" class="flex items-center gap-1 bg-red-500/20 text-red-200 text-[11px] font-bold px-2.5 py-1 rounded-full border border-red-400/30"></span>
                <span class="text-white/20 text-xs">|</span>
                <span id="topicModalSoruBadge" class="text-[11px] font-bold text-white/70"></span>
                <span id="topicModalKonuBadge" class="text-[11px] font-bold text-white/70"></span>
            </div>
        </div>
        <div class="p-0 max-h-[60vh] overflow-y-auto custom-scrollbar bg-slate-50">
            <table class="w-full text-sm text-left border-collapse">
                <thead class="bg-white text-slate-500 sticky top-0 shadow-sm z-10 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="p-3 font-bold border-b border-slate-100">Tarih</th>
                        <th class="p-3 font-bold border-b border-slate-100">Tür</th>
                        <th class="p-3 font-bold border-b border-slate-100 text-center" colspan="2">
                            <span class="text-green-600">Doğru</span> / <span class="text-red-500">Yanlış</span>
                        </th>
                        <th class="p-3 font-bold border-b border-slate-100 text-right">Hedef</th>
                        <th class="p-3 font-bold border-b border-slate-100 text-right">Yapılan</th>
                        <th class="p-3 font-bold border-b border-slate-100 text-center">Durum</th>
                    </tr>
                </thead>
                <tbody id="topicModalBody" class="divide-y divide-slate-100 bg-white"></tbody>
            </table>
        </div>
        <div class="p-3 bg-slate-50 border-t border-slate-200 text-center">
            <button onclick="closeTopicDetails()" class="text-xs font-bold text-[#223488] hover:text-[#314595] uppercase tracking-wider">Kapat</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
window.DB_VERILERI = <?php 
    $jsonCikti = json_encode($jsVeriDeposu, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    echo ($jsonCikti === false) ? '{}' : $jsonCikti; 
?>;

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
                    borderColor: '#223488',
                    backgroundColor: 'rgba(34, 52, 136, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ec9731',
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
                        <?php echo $comp_raw['yapildi'] ?? 0; ?>, 
                        <?php echo $comp_raw['yarim'] ?? 0; ?>, 
                        <?php echo ($comp_raw['yapilmadi'] ?? 0) + ($comp_raw['bekliyor'] ?? 0); ?>
                    ],
                    backgroundColor: ['#ec9731', '#314595', '#cbd5e1'],
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

    const items = document.querySelectorAll('.topic-item-clickable');
    items.forEach(function(item) {
        item.onclick = function(e) {
            e.preventDefault();
            var rowId = this.getAttribute('data-row-id');
            var topicName = this.getAttribute('data-topic-name');
            var historyData = window.DB_VERILERI[rowId];
            if (historyData) showModal(topicName, historyData);
        };
    });

    setTimeout(function() {
        filterAnalysis('RAPOR');
        var raporBtn = document.getElementById('btn-analiz-RAPOR');
        if(raporBtn) raporBtn.click();
    }, 100);
});

function showModal(title, data) {
    var modal = document.getElementById('topicDetailsModal');
    var modalContent = document.getElementById('topicModalContent');
    var titleEl = document.getElementById('topicModalTitle');
    var bodyEl  = document.getElementById('topicModalBody');
    var statsEl = document.getElementById('topicModalStats');

    if(!modal) return;

    titleEl.innerText = title;
    bodyEl.innerHTML = '';

    // --- Başlık stats hesapla ---
    var totalCorrect = 0, totalWrong = 0, totalSoru = 0, totalKonu = 0;
    if (Array.isArray(data)) {
        data.forEach(function(row) {
            var t = (row.action_type || '').toLowerCase();
            if (t === 'soru') {
                totalSoru   += parseInt(row.amount || 0);
                totalCorrect += (row.correct_count !== null && row.correct_count !== undefined && row.correct_count !== '') ? parseInt(row.correct_count) : 0;
                totalWrong   += (row.wrong_count   !== null && row.wrong_count   !== undefined && row.wrong_count   !== '') ? parseInt(row.wrong_count)   : 0;
            } else {
                totalKonu++;
            }
        });
    }
    // Stat badge'leri doldur
    if (statsEl) {
        document.getElementById('topicModalDogruBadge').innerHTML  = '● ' + totalCorrect + ' Doğru';
        document.getElementById('topicModalYanlisBadge').innerHTML = '● ' + totalWrong   + ' Yanlış';
        document.getElementById('topicModalSoruBadge').innerHTML   = 'SORU: ' + totalSoru;
        document.getElementById('topicModalKonuBadge').innerHTML   = 'KONU: ' + totalKonu;
        statsEl.classList.remove('hidden');
    }

    if (!Array.isArray(data) || data.length === 0) {
        bodyEl.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-slate-400 font-medium">Kayıt bulunamadı.</td></tr>';
    } else {
        data.sort((a, b) => new Date(b.date) - new Date(a.date));
        data.forEach(function(row) {
            var d = row.date || '-';
            if(d.length >= 10) {
                var parts = d.substring(0,10).split('-');
                if(parts.length === 3) d = parts[2] + '.' + parts[1] + '.' + parts[0];
            }

            var type = (row.action_type || '').toLowerCase();
            var badgeHTML = type === 'soru'
                ? '<span class="inline-flex items-center gap-1 bg-blue-50 text-[#223488] px-2 py-1 rounded text-[10px] font-bold border border-blue-100">❓ Soru</span>'
                : '<span class="inline-flex items-center gap-1 bg-orange-50 text-[#ec9731] px-2 py-1 rounded text-[10px] font-bold border border-orange-100">📖 Konu</span>';

            // Doğru / Yanlış
            var dogruHTML = '-', yanlisHTML = '-';
            if (type === 'soru') {
                var dogru  = (row.correct_count !== null && row.correct_count !== undefined && row.correct_count !== '') ? parseInt(row.correct_count) : null;
                var yanlis = (row.wrong_count   !== null && row.wrong_count   !== undefined && row.wrong_count   !== '') ? parseInt(row.wrong_count)   : null;
                if (dogru  !== null) dogruHTML  = `<span class="inline-block bg-green-100 text-green-700 font-black text-sm px-3 py-1 rounded-lg border border-green-200 min-w-[36px] text-center">${dogru}</span>`;
                if (yanlis !== null) yanlisHTML = `<span class="inline-block bg-red-100 text-red-600 font-black text-sm px-3 py-1 rounded-lg border border-red-200 min-w-[36px] text-center">${yanlis}</span>`;
            }

            // Hedef ve Yapılan — ayrı sütunlar
            var unit = (type !== 'soru') ? ' dk' : '';
            var realAmount   = parseInt(row.amount || 0);
            var targetAmount = (row.target_amount !== null && row.target_amount !== undefined && row.target_amount !== '') ? parseInt(row.target_amount) : realAmount;

            var hedefHTML  = `<span class="font-bold text-slate-500 text-sm">${targetAmount}${unit}</span>`;
            var yapilanHTML = '';
            if (row.status === 'bekliyor') {
                yapilanHTML = '<span class="text-slate-300 text-sm">—</span>';
            } else if (targetAmount !== realAmount) {
                yapilanHTML = `<span class="font-bold text-[#223488] text-sm">${realAmount}${unit}</span>`;
            } else {
                yapilanHTML = `<span class="font-bold text-[#223488] text-sm">${realAmount}${unit}</span>`;
            }

            var status = row.status || 'yapildi';
            var statusIcon = '';
            if(status === 'yapildi')  statusIcon = '<span class="inline-block bg-green-100 text-green-600 rounded-lg p-1 text-base">✅</span>';
            else if(status === 'yarim') statusIcon = '<span class="inline-block bg-orange-100 text-orange-600 rounded-lg p-1 text-base">⚠️</span>';
            else statusIcon = '<span class="inline-block bg-red-100 text-red-500 rounded-lg p-1 text-base">❌</span>';

            var tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50/80 transition-colors';
            tr.innerHTML = `
                <td class="p-3 text-slate-600 font-medium font-mono text-xs whitespace-nowrap">${d}</td>
                <td class="p-3">${badgeHTML}</td>
                <td class="p-3 text-center">${dogruHTML}</td>
                <td class="p-3 text-center">${yanlisHTML}</td>
                <td class="p-3 text-right">${hedefHTML}</td>
                <td class="p-3 text-right">${yapilanHTML}</td>
                <td class="p-3 text-center">${statusIcon}</td>
            `;
            bodyEl.appendChild(tr);
        });
    }

    modal.classList.remove('hidden');
    setTimeout(() => {
        modalContent.classList.remove('scale-95');
        modalContent.classList.add('scale-100');
    }, 10);
}

function closeTopicDetails() {
    var modal = document.getElementById('topicDetailsModal');
    var modalContent = document.getElementById('topicModalContent');
    if(modal) {
        modalContent.classList.remove('scale-100');
        modalContent.classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); }, 200);
    }
}

function filterAnalysis(cat) {
    var cards = document.querySelectorAll('.analysis-card');
    cards.forEach(function(c) {
        var cardCat = c.getAttribute('data-category');
        if (cat === 'RAPOR') {
            c.style.display = (cardCat === 'RAPOR') ? 'block' : 'none';
        } else {
            if (cardCat === 'RAPOR') c.style.display = 'none';
            else c.style.display = (cardCat === cat) ? 'block' : 'none';
        }
    });

    var btns = document.querySelectorAll('.analiz-filter-btn');
    btns.forEach(function(b) {
        b.className = "analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-sm bg-white text-slate-600 hover:bg-slate-100 border border-slate-200";
    });
    
    var activeBtn = document.getElementById('btn-analiz-' + cat.replace(' ', ''));
    if(activeBtn) {
        activeBtn.className = "analiz-filter-btn px-6 py-2 rounded-lg text-sm font-bold transition-all shadow-md bg-[#ec9731] text-white ring-2 ring-orange-200 ring-offset-1 transform scale-105";
    }
}
</script>

</div>