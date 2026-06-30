<?php
// koc/sidebar.php - GÜNCELLENMİŞ SÜRÜM

// 1. Telefon Temizleme
function cleanPhone($phone) {
    if (!$phone) return '';
    $clean = preg_replace('/[^0-9]/', '', trim($phone));
    if (empty($clean)) return '';
    $clean = ltrim($clean, '0');
    $len = strlen($clean);
    if ($len === 10) return '90' . $clean;
    if ($len === 12 && substr($clean, 0, 2) === '90') return $clean;
    return ''; 
}

// 2. Verileri Hazırla
$student_phone_raw = $selected_student['phone'] ?? '';
$parent_phone_raw  = $selected_student['parent_phone'] ?? '';

$student_phone_clean = cleanPhone($student_phone_raw);
$parent_phone_clean  = cleanPhone($parent_phone_raw);

$student_name_js = htmlspecialchars(($selected_student['first_name'] ?? '') . ' ' . ($selected_student['last_name'] ?? ''), ENT_QUOTES);
$parent_name_js  = htmlspecialchars($selected_student['parent_name'] ?? 'Sayın Veli', ENT_QUOTES);
$coach_name_js   = htmlspecialchars($_SESSION['username'] ?? 'Koç', ENT_QUOTES);

// Son Deneme
$last_exam_js = ['name' => '-', 'net' => '-', 'date' => '-'];
if (!empty($exam_results)) {
    $last = reset($exam_results);
    $last_exam_js = [
        'name' => htmlspecialchars($last['exam_name'], ENT_QUOTES),
        'net'  => $last['total_net'],
        'date' => date('d.m.Y', strtotime($last['date_taken']))
    ];
}

// İstatistikler
$total_questions = 0;
$total_topics = 0;

if (!empty($week_dates)) {
    foreach($week_dates as $wd) {
        $items = $schedule_items[$wd] ?? [];
        foreach($items as $item) {
            if ($item['action_type'] === 'soru') $total_questions += (int)$item['amount'];
            elseif ($item['action_type'] === 'konu') $total_topics += (int)$item['amount'];
        }
    }
}

$stats_js = ['total_q' => $total_questions, 'week_q' => $total_questions, 'week_t' => $total_topics];

// Program Verisi
$schedule_js = [];
if (!empty($week_dates)) {
    foreach($week_dates as $wd) {
        $items = $schedule_items[$wd] ?? [];
        $cleanItems = [];
        foreach($items as $item) {
            $cleanItems[] = [
                'category' => $item['subject_category'] ?? '',
                'subject'  => $item['subject_name'] ?? $item['custom_subject'],
                'topic'    => $item['topic_name'] ?? $item['custom_topic'],
                'amount'   => $item['amount'],
                'type'     => $item['action_type'],
                'status'   => $item['status'],
                'time'     => isset($item['time_note']) ? trim($item['time_note']) : ''
            ];
        }
        $schedule_js[$wd] = $cleanItems;
    }
}
$week_dates_js = $week_dates; 
?>

<style>
    :root {
        --atla-blue-dark: #223488;
        --atla-blue-light: #314595;
        --atla-orange: #ec9731;
        --atla-orange-hover: #d68625;
    }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 4px; }
    .sidebar-btn { transition: all 0.2s ease; }
    .sidebar-btn:active { transform: scale(0.97); }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>


<div id="pdf-template" style="display:none; width: 1050px; font-family: 'Helvetica', sans-serif; color: #1e293b; background-color: #fff;">
    <div style="padding: 14px 16px 10px;">
        <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 2px solid #223488; padding-bottom: 6px; margin-bottom: 8px;">
            <div style="font-size: 11px; color: #ec9731; font-weight: 700;">
                <?php echo $week_dates ? date('d.m.Y', strtotime($week_dates[0])) . ' — ' . date('d.m.Y', strtotime(end($week_dates))) : ''; ?>
            </div>
            <div style="text-align: center;">
                <h1 style="font-size: 18px; font-weight: 900; color: #223488; margin: 0; text-transform: uppercase; letter-spacing: -0.5px;">Haftalık Çalışma Programı</h1>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 14px; font-weight: 800; color: #223488;"><?php echo $student_name_js; ?></div>
                <div style="font-size: 9px; color: #64748b; font-weight: 600;">Koç: <?php echo $coach_name_js; ?></div>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 8px; padding: 5px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px;">
            <span style="font-size: 10px; font-weight: 700; color: #223488;">🎯 Soru Hedefi: <span id="pdf_total_questions" style="color: #4338ca;">0</span></span>
            <span style="color: #cbd5e1; font-size: 12px;">|</span>
            <span style="font-size: 10px; font-weight: 700; color: #223488;">📚 Konu Hedefi: <span id="pdf_total_topics" style="color: #4338ca;">0</span></span>
            <span style="color: #cbd5e1; font-size: 12px;">|</span>
            <span style="font-size: 10px; font-weight: 700; color: #223488;">📊 Son Deneme: <span id="pdf_last_exam" style="color: #ea580c;">-</span></span>
        </div>
        <div id="pdf_schedule_grid" style="display: flex; gap: 10px; align-items: stretch;"></div>
        <div style="margin-top: 8px; text-align: center; font-size: 8px; color: #cbd5e1; border-top: 1px solid #f1f5f9; padding-top: 4px;">DersPROS Koçluk Sistemi | <?php echo date('d.m.Y H:i'); ?></div>
    </div>
</div>

<script>
    const REPORT_DATA = {
        studentName: "<?php echo $student_name_js; ?>",
        studentPhone: "<?php echo $student_phone_clean; ?>",
        parentName: "<?php echo $parent_name_js; ?>",
        parentPhone: "<?php echo $parent_phone_clean; ?>",
        stats: <?php echo json_encode($stats_js); ?>,
        lastExam: <?php echo json_encode($last_exam_js); ?>,
        schedule: <?php echo json_encode($schedule_js); ?>,
        weekDates: <?php echo json_encode($week_dates_js); ?>
    };

    // PDF şablonunu doldur ve element'i hazırla
    function _preparePdfElement() {
        const grid = document.getElementById('pdf_schedule_grid');
        grid.innerHTML = '';
        let totalQuestions = 0, totalTopics = 0;
        Object.values(REPORT_DATA.schedule).forEach(dayItems => {
            dayItems.forEach(item => {
                if(item.type === 'soru') totalQuestions += parseInt(item.amount) || 0;
                if(item.type === 'konu') totalTopics += parseInt(item.amount) || 0;
            });
        });
        document.getElementById('pdf_total_questions').textContent = totalQuestions;
        document.getElementById('pdf_total_topics').textContent = totalTopics;
        if(REPORT_DATA.lastExam.name !== '-') document.getElementById('pdf_last_exam').textContent = `${REPORT_DATA.lastExam.name}: ${REPORT_DATA.lastExam.net} Net`;
        else document.getElementById('pdf_last_exam').textContent = '-';

        const trDays = { 'Monday': 'Pazartesi', 'Tuesday': 'Salı', 'Wednesday': 'Çarşamba', 'Thursday': 'Perşembe', 'Friday': 'Cuma', 'Saturday': 'Cumartesi', 'Sunday': 'Pazar' };
        REPORT_DATA.weekDates.forEach(dateStr => {
            const dateObj = new Date(dateStr);
            const dayNameTr = trDays[dateObj.toLocaleDateString('en-US', { weekday: 'long' })] || dateObj.toLocaleDateString('en-US', { weekday: 'long' });
            const items = REPORT_DATA.schedule[dateStr] || [];
            let colHtml = `<div style="flex: 1; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; min-height: 400px;"><div style="background: #223488; padding: 10px; text-align: center; border-bottom: 1px solid #1e293b;"><div style="font-size: 12px; font-weight: 800; color: #fff; text-transform: uppercase;">${dayNameTr}</div><div style="font-size: 10px; font-weight: 600; color: #ec9731;">${dateObj.getDate()}</div></div><div style="padding: 8px; display: flex; flex-direction: column; gap: 6px;">`;
            if(items.length === 0) colHtml += `<div style="text-align: center; color: #cbd5e1; font-size: 10px; margin-top: 50px; font-style: italic;">Boş</div>`;
            else {
                items.forEach(item => {
                    let badgeColor = "#64748b", badgeBg = "#f1f5f9", itemBorder = "#e2e8f0";
                    const cat = (item.category || '').toUpperCase();
                    if(cat === 'TYT') { badgeColor = "#4338ca"; badgeBg = "#e0e7ff"; itemBorder = "#c7d2fe"; }
                    if(cat === 'AYT') { badgeColor = "#7e22ce"; badgeBg = "#f3e8ff"; itemBorder = "#e9d5ff"; }
                    if(cat === 'LGS') { badgeColor = "#c2410c"; badgeBg = "#ffedd5"; itemBorder = "#fed7aa"; }
                    const unit = item.type === 'soru' ? 'soru' : 'dk';
                    let timeHtml = item.time && item.time.trim() ? `<div style="font-size:8px;color:#dc2626;font-weight:bold;margin-bottom:2px;">🕒 ${item.time}</div>` : '';
                    colHtml += `<div style="background:white;border:1px solid ${itemBorder};border-left:3px solid ${badgeColor};border-radius:4px;padding:6px;position:relative;">${cat ? `<div style="position:absolute;top:4px;right:4px;font-size:6px;font-weight:900;background:${badgeBg};color:${badgeColor};padding:1px 3px;border-radius:2px;">${cat}</div>` : ''}<div style="font-size:9px;font-weight:800;color:#1e293b;margin-bottom:2px;padding-right:15px;">${item.subject}</div><div style="font-size:8px;color:#64748b;margin-bottom:3px;line-height:1.1;">${item.topic}</div>${timeHtml}<div style="font-size:8px;font-weight:800;color:${badgeColor};text-align:right;border-top:1px solid #f1f5f9;padding-top:2px;margin-top:2px;">${item.amount} ${unit}</div></div>`;
                });
            }
            colHtml += `</div></div>`;
            grid.innerHTML += colHtml;
        });

        const element = document.getElementById('pdf-template');
        element.style.display = 'block';
        return element;
    }

    function _getPdfOpt(filename) {
        return { margin: 5, filename: filename, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true, scrollY: 0 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' } };
    }

    // PDF indir
    function downloadPdfProgram() {
        const element = _preparePdfElement();
        const filename = `Program_${REPORT_DATA.studentName}_${new Date().toLocaleDateString('tr-TR')}.pdf`;
        html2pdf().set(_getPdfOpt(filename)).from(element).save().then(() => { element.style.display = 'none'; });
    }

    // Ortak: PDF blob üret → sunucuya yükle → WhatsApp'ta link gönder
    async function _sendPdfViaWhatsapp(phone, label) {
        if (!phone) { alert(`${label} numarası bulunamadı!`); return; }
        const element = _preparePdfElement();
        const filename = `Program_${REPORT_DATA.studentName}_${new Date().toLocaleDateString('tr-TR')}.pdf`;
        const opt = _getPdfOpt(filename);

        try {
            const pdfBlob = await html2pdf().set(opt).from(element).outputPdf('blob');
            element.style.display = 'none';

            // Mobil: doğrudan dosya paylaşımı (Web Share API)
            const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
            const file = new File([pdfBlob], filename, { type: 'application/pdf' });
            if (isMobile && navigator.share && navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: filename });
                return;
            }

            // Masaüstü: sunucuya yükle, linki WhatsApp'ta gönder
            const formData = new FormData();
            formData.append('pdf', pdfBlob, filename);

            const resp = await fetch('<?php echo BASE_URL; ?>/save_temp_pdf.php', { method: 'POST', body: formData });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.error || 'Sunucu hatası');

            const text = `📋 ${REPORT_DATA.studentName} - Haftalık Çalışma Programı:\n${data.url}`;
            window.open(`https://web.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(text)}`, '_blank');

        } catch(e) {
            element.style.display = 'none';
            if (e.name !== 'AbortError') alert('Hata: ' + e.message);
        }
    }

    function sendWhatsappReport()  { _sendPdfViaWhatsapp(REPORT_DATA.parentPhone,  'Veli'); }
    function sendStudentMessage()  { _sendPdfViaWhatsapp(REPORT_DATA.studentPhone, 'Öğrenci'); }
</script>