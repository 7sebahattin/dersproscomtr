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

// İstatistikler (soru adedi + konu dakikası + video sayısı)
$total_questions = 0;
$total_topics = 0;
$total_videos = 0;

if (!empty($week_dates)) {
    foreach($week_dates as $wd) {
        $items = $schedule_items[$wd] ?? [];
        foreach($items as $item) {
            if ($item['action_type'] === 'soru') $total_questions += (int)$item['amount'];
            elseif ($item['action_type'] === 'konu') $total_topics += (int)$item['amount'];
            elseif ($item['action_type'] === 'video') $total_videos++;
        }
    }
}

$stats_js = ['total_q' => $total_questions, 'week_q' => $total_questions, 'week_t' => $total_topics, 'week_v' => $total_videos];

// Program Verisi — YENİ müfredat (edu_*) öncelikli; yoksa eski koçluk; yoksa manuel.
// (koc/program.php ile birebir aynı çözümleme — Stüdyo/Görev Seç ile girilen
//  görevlerde ders/konu adı boş kalmasın; not/kaynak/durum da PDF'e taşınsın.)
$schedule_js = [];
if (!empty($week_dates)) {
    foreach($week_dates as $wd) {
        $items = $schedule_items[$wd] ?? [];
        $cleanItems = [];
        foreach($items as $item) {
            $eduCat   = $item['edu_category_name'] ?? '';
            $eduSubj  = $item['edu_subject_name']  ?? '';
            $eduTopic = $item['edu_topic_name']    ?? '';
            $isManual = (empty($eduCat) && empty($item['subject_category']) && (!empty($item['custom_topic']) || !empty($item['custom_subject'])));
            $category = $eduCat ?: ($item['subject_category'] ?? '');
            if ($category === '' && $isManual) $category = 'Diğer';

            // Planda HEDEF gösterilir (durum girildiyse amount gerçekleşene döner)
            $hedef = (isset($item['target_amount']) && $item['target_amount'] !== null && $item['target_amount'] !== '')
                ? (int)$item['target_amount'] : (int)$item['amount'];

            $cleanItems[] = [
                'category' => $category,
                'subject'  => $eduSubj  ?: (!empty($item['topic_name']) ? ($item['subject_name'] ?? '') : ($item['custom_subject'] ?? '')),
                'topic'    => $eduTopic ?: (!empty($item['topic_name']) ? ($item['topic_name'] ?? '') : ($item['custom_topic'] ?? '')),
                'amount'   => $hedef,
                'type'     => $item['action_type'],
                'status'   => $item['status'],
                'time'     => trim((string)($item['time_note'] ?? '')),
                'note'     => trim((string)($item['task_note'] ?? '')),
                'resource' => (string)($item['resource_title'] ?? ''),
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
    <!-- Üst bant: lacivert zemin + turuncu şerit — öğrenci/veli için sıcak ve okunaklı -->
    <div style="background: #223488; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 19px; font-weight: 900; color: #ffffff; letter-spacing: -0.5px;">📅 HAFTALIK ÇALIŞMA PROGRAMI</div>
            <div style="font-size: 11px; color: #ec9731; font-weight: 800; margin-top: 3px;">
                <?php echo $week_dates ? date('d.m.Y', strtotime($week_dates[0])) . ' — ' . date('d.m.Y', strtotime(end($week_dates))) : ''; ?>
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 16px; font-weight: 900; color: #ffffff;"><?php echo $student_name_js; ?></div>
            <div style="font-size: 10px; color: #c7d2fe; font-weight: 600;">Koç: <?php echo $coach_name_js; ?></div>
        </div>
    </div>
    <div style="height: 4px; background: #ec9731;"></div>

    <!-- Haftalık özet çipleri -->
    <div style="display: flex; align-items: center; gap: 8px; padding: 8px 18px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
        <span style="font-size: 10px; font-weight: 800; color: #223488; background: #e0e7ff; border: 1px solid #c7d2fe; padding: 3px 9px; border-radius: 10px;">🎯 <span id="pdf_total_questions">0</span> Soru Hedefi</span>
        <span style="font-size: 10px; font-weight: 800; color: #b45309; background: #fef3c7; border: 1px solid #fde68a; padding: 3px 9px; border-radius: 10px;">📖 <span id="pdf_total_topics">0</span> dk Konu Çalışması</span>
        <span id="pdf_video_chip" style="display:none; font-size: 10px; font-weight: 800; color: #b91c1c; background: #fee2e2; border: 1px solid #fecaca; padding: 3px 9px; border-radius: 10px;">🎬 <span id="pdf_total_videos">0</span> Video</span>
        <span style="font-size: 10px; font-weight: 800; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 3px 9px; border-radius: 10px;">📊 Son Deneme: <span id="pdf_last_exam" style="color:#ea580c;">-</span></span>
    </div>

    <div id="pdf_schedule_grid" style="display: flex; gap: 8px; align-items: stretch; padding: 10px 14px;"></div>

    <div style="margin: 0 14px 8px; text-align: center; font-size: 9px; color: #64748b; border-top: 1px solid #f1f5f9; padding-top: 5px;">
        <span style="color:#223488; font-weight:800;">Planlı çalışan kazanır — başarılar! 🚀</span>
        &nbsp;·&nbsp; DersPROS Koçluk Sistemi · <?php echo date('d.m.Y H:i'); ?>
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

    // Güvenli HTML (ders/konu/not adları serbest metin olabilir)
    function _pdfEsc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    // PDF şablonunu doldur ve element'i hazırla
    function _preparePdfElement() {
        const grid = document.getElementById('pdf_schedule_grid');
        grid.innerHTML = '';
        let totalQuestions = 0, totalMinutes = 0, totalVideos = 0;
        Object.values(REPORT_DATA.schedule).forEach(dayItems => {
            dayItems.forEach(item => {
                if(item.type === 'soru') totalQuestions += parseInt(item.amount) || 0;
                if(item.type === 'konu') totalMinutes += parseInt(item.amount) || 0;
                if(item.type === 'video') totalVideos++;
            });
        });
        document.getElementById('pdf_total_questions').textContent = totalQuestions;
        document.getElementById('pdf_total_topics').textContent = totalMinutes;
        document.getElementById('pdf_total_videos').textContent = totalVideos;
        document.getElementById('pdf_video_chip').style.display = totalVideos > 0 ? 'inline' : 'none';
        document.getElementById('pdf_last_exam').textContent =
            REPORT_DATA.lastExam.name !== '-' ? `${REPORT_DATA.lastExam.name}: ${REPORT_DATA.lastExam.net} Net` : '-';

        const trDays = { 'Monday': 'Pazartesi', 'Tuesday': 'Salı', 'Wednesday': 'Çarşamba', 'Thursday': 'Perşembe', 'Friday': 'Cuma', 'Saturday': 'Cumartesi', 'Sunday': 'Pazar' };
        // Tür rengi + birimi (kart sol şeridi ve miktar rozeti)
        const typeMeta = { soru: ['#223488', 'Soru'], konu: ['#ec9731', 'Dakika'], video: ['#dc2626', 'Video'] };
        // Durum → kart zemini + köşe işareti (hafta içinde gönderilirse ilerleme görünsün)
        const stMeta = {
            yapildi:   ['#f0fdf4', '#bbf7d0', '<span style="font-size:9px;color:#16a34a;font-weight:900;">✓</span>'],
            yarim:     ['#fffbeb', '#fde68a', '<span style="font-size:9px;color:#d97706;font-weight:900;">◐</span>'],
            yapilmadi: ['#fef2f2', '#fecaca', '<span style="font-size:9px;color:#dc2626;font-weight:900;">✕</span>'],
        };

        REPORT_DATA.weekDates.forEach(dateStr => {
            const dateObj = new Date(dateStr);
            const dayNameTr = trDays[dateObj.toLocaleDateString('en-US', { weekday: 'long' })] || '';
            const isWeekend = [0, 6].includes(dateObj.getDay());
            const items = REPORT_DATA.schedule[dateStr] || [];

            let colHtml = `<div style="flex:1; background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; min-height:400px;">` +
                `<div style="background:${isWeekend ? '#314595' : '#223488'}; padding:8px 4px; text-align:center;">` +
                    `<div style="font-size:15px; font-weight:900; color:#ec9731; line-height:1;">${dateObj.getDate()}</div>` +
                    `<div style="font-size:10px; font-weight:800; color:#fff; text-transform:uppercase; margin-top:2px;">${dayNameTr}</div>` +
                `</div><div style="padding:6px; display:flex; flex-direction:column; gap:5px;">`;

            if (items.length === 0) {
                colHtml += `<div style="text-align:center; color:#cbd5e1; font-size:10px; margin-top:50px; font-style:italic;">🌤 Serbest gün</div>`;
            } else {
                items.forEach(item => {
                    const tm = typeMeta[item.type] || typeMeta.soru;
                    const st = stMeta[item.status] || null;
                    const cardBg = st ? st[0] : '#ffffff';
                    const cardBorder = st ? st[1] : '#e2e8f0';

                    // Kategori/Kaynak rozeti
                    let badgeBg = '#f1f5f9', badgeFg = '#64748b', badgeTxt = (item.category || '').toUpperCase();
                    const cat = badgeTxt;
                    if (cat === 'TYT') { badgeBg = '#e0e7ff'; badgeFg = '#4338ca'; }
                    else if (cat === 'AYT') { badgeBg = '#f3e8ff'; badgeFg = '#7e22ce'; }
                    else if (cat === 'LGS') { badgeBg = '#ffedd5'; badgeFg = '#c2410c'; }
                    if (item.resource) { badgeBg = '#fee2e2'; badgeFg = '#b91c1c'; badgeTxt = '📕 ' + item.resource.substring(0, 14); }

                    const noteHtml = item.note ? `<div style="font-size:7.5px; color:#b45309; margin-top:2px; line-height:1.2;">📝 ${_pdfEsc(item.note)}</div>` : '';
                    const timeHtml = item.time ? `<span style="font-size:8px; color:#dc2626; font-weight:800;">⏰ ${_pdfEsc(item.time)}</span>` : '<span></span>';

                    colHtml += `<div style="background:${cardBg}; border:1px solid ${cardBorder}; border-left:4px solid ${tm[0]}; border-radius:5px; padding:5px 6px;">` +
                        `<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">` +
                            `<span style="font-size:6.5px; font-weight:900; background:${badgeBg}; color:${badgeFg}; padding:1px 4px; border-radius:3px;">${_pdfEsc(badgeTxt) || 'GÖREV'}</span>` +
                            (st ? st[2] : '') +
                        `</div>` +
                        `<div style="font-size:9.5px; font-weight:900; color:#0f172a;">${_pdfEsc(item.subject) || '-'}</div>` +
                        `<div style="font-size:8.5px; color:#334155; line-height:1.25; margin-top:1px;">${_pdfEsc(item.topic)}</div>` +
                        noteHtml +
                        `<div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px; border-top:1px dashed #e2e8f0; padding-top:3px;">` +
                            timeHtml +
                            `<span style="font-size:8px; font-weight:900; color:#fff; background:${tm[0]}; padding:1px 6px; border-radius:8px;">${item.amount} ${tm[1]}</span>` +
                        `</div>` +
                    `</div>`;
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