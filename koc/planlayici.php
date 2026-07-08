<?php
/**
 * koc/planlayici.php — PLANLAMA STÜDYOSU (Faz A)
 *
 * Mevcut program oluşturmanın tam ekran, sürükle-bırak tabanlı, yüksek hızlı
 * sürümü. koc_paneli.php tarafından include edilir ($sid, $selected_student,
 * $week_dates, $gunlerTR, $raw_items kapsamda olmalı).
 *
 * Tasarım kararları (kullanıcı onaylı):
 *  1) Tam ekran stüdyo (modal değil) — koç ekranı terk etmeden tüm haftayı kurar.
 *  2) "Aktif Kalem" (sticky preset) varsayılan: bırakılan her konu kalemdeki
 *     Tür/Miktar/Saat/Not ile hazır kart olur. Chip sürükleme + karta tıklama
 *     istisnaları düzenlemek içindir.
 *  3) Taslak modeli: her hareket anında localStorage'a yazılır (çevrimdışı
 *     güvenli, Gmail hissi); veritabanına yazma yalnızca "Haftayı Kaydet" ile
 *     tek TRANSACTION'da yapılır (ajax=plan_apply). Yarım plan asla oluşmaz.
 */
if (empty($sid) || empty($selected_student)) return;

// Stüdyo tahtası için hafta verisini derle (program.php ile aynı kimlik kuralları)
$psItems = [];
foreach (($raw_items ?? []) as $it) {
    $eduCat   = $it['edu_category_name'] ?? '';
    $isManual = (empty($eduCat) && empty($it['subject_category']) && (!empty($it['custom_topic']) || !empty($it['custom_subject'])));
    $category = $eduCat ?: ($it['subject_category'] ?? '');
    if ($category === '' && $isManual) $category = 'Diğer';
    $psItems[] = [
        'id'             => (int)$it['id'],
        'date'           => (string)$it['date'],
        'amount'         => (int)($it['amount'] ?? 0),
        'action_type'    => (string)($it['action_type'] ?? 'soru'),
        'status'         => (string)($it['status'] ?? 'bekliyor'),
        'time_note'      => (string)($it['time_note'] ?? ''),
        'task_note'      => (string)($it['task_note'] ?? ''),
        'edu_topic_id'   => $it['edu_topic_id'] ?? null,
        'custom_subject' => (string)($it['custom_subject'] ?? ''),
        'custom_topic'   => (string)($it['custom_topic'] ?? ''),
        'resource_id'    => $it['resource_id'] ?? null,
        'resource_title' => (string)($it['resource_title'] ?? ''),
        'resource_type'  => (string)($it['resource_type'] ?? ''),
        'category'       => (string)$category,
        'subject'        => (string)(($it['edu_subject_name'] ?? '') ?: (!empty($it['topic_name']) ? ($it['subject_name'] ?? '') : ($it['custom_subject'] ?? ''))),
        'topic'          => (string)(($it['edu_topic_name'] ?? '') ?: (!empty($it['topic_name']) ? ($it['topic_name'] ?? '') : ($it['custom_topic'] ?? ''))),
    ];
}
?>
<style>
/* Hafta tahtasının yatay kaydırma çubuğu — daha belirgin */
#plannerStudio .ps-board-scroll{ scrollbar-width:auto; scrollbar-color:#223488 #e2e8f0; }
#plannerStudio .ps-board-scroll::-webkit-scrollbar{ height:14px; }
#plannerStudio .ps-board-scroll::-webkit-scrollbar-track{ background:#e2e8f0; border-radius:999px; }
#plannerStudio .ps-board-scroll::-webkit-scrollbar-thumb{ background:#223488; border-radius:999px; border:3px solid #e2e8f0; }
#plannerStudio .ps-board-scroll::-webkit-scrollbar-thumb:hover{ background:#314595; }
</style>
<!-- Masaüstünde üst-barın altına sabitlenen gömülü panel (görünürlük tamamen JS
     style.display ile kontrol edilir; hidden/lg:flex çakışmasından kaçınmak için).
     Mobilde hiç gösterilmez (eski tablo kullanılır). -->
<div id="plannerStudio" class="fixed left-0 right-0 bottom-0 z-[40] flex-col bg-slate-100" style="font-family:'Poppins',sans-serif;top:0;display:none">

    <!-- (Başlık/öğrenci/nav ve Taslak durumu + Sıfırla + Kaydet artık paylaşılan üst barda) -->
    <div class="flex flex-grow min-h-0">

        <!-- ═══ SOL PALET ═══ -->
        <div class="w-[290px] flex-shrink-0 bg-white border-r border-slate-200 flex flex-col min-h-0">

            <!-- Sekmeler -->
            <div class="flex p-1.5 gap-1 border-b border-slate-100 flex-shrink-0 text-[10px] font-bold">
                <button type="button" data-pstab="mufredat" class="ps-tab flex-1 py-2 rounded-lg bg-[#ec9731] text-white transition">📚 MÜFREDAT</button>
                <button type="button" data-pstab="kaynak"   class="ps-tab flex-1 py-2 rounded-lg text-slate-500 hover:text-[#223488] transition">🔗 KAYNAK</button>
                <button type="button" data-pstab="manuel"   class="ps-tab flex-1 py-2 rounded-lg text-slate-500 hover:text-[#223488] transition">✏️ MANUEL</button>
            </div>

            <!-- ═══ AKTİF KALEM (sticky preset) — sekmelerin altında, Alan/Ders seçiminin üstünde ═══ -->
            <div id="psPresetBox" class="border-b border-[#223488]/30 bg-[#223488]/5 p-2.5 space-y-2 flex-shrink-0">
                <span id="psPresetLabel" class="text-[9px] font-black text-[#223488] uppercase tracking-wider block">🖊 Aktif Kalem</span>

                <!-- Tür: tam genişlik, tek-görev modalındaki gibi -->
                <div class="grid grid-cols-2 gap-1.5">
                    <button type="button" id="psPresetSoru" onclick="psSetPresetType('soru')" class="py-2 rounded-lg text-xs font-black transition bg-[#223488] text-white">❓ Soru</button>
                    <button type="button" id="psPresetKonu" onclick="psSetPresetType('konu')" class="py-2 rounded-lg text-xs font-black transition bg-white border-2 border-slate-200 text-slate-500">📖 Konu</button>
                </div>

                <!-- Hızlı miktar çipleri (Soru/Konu için ayrı setler — JS ile üretilir) -->
                <div id="psAmountChips" class="flex flex-wrap gap-1"></div>

                <!-- Miktar + Zaman -->
                <div class="grid grid-cols-2 gap-1.5">
                    <div>
                        <label class="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Miktar</label>
                        <input type="number" id="psPresetAmount" min="1" value="20" class="w-full bg-white border border-slate-200 rounded-lg text-[12px] font-black text-slate-800 text-center py-1.5 outline-none focus:border-[#ec9731]">
                    </div>
                    <div>
                        <label class="block text-[8px] font-bold text-slate-400 uppercase mb-0.5">Zaman</label>
                        <input type="time" id="psPresetTime" class="w-full bg-white border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 text-center py-1.5 outline-none focus:border-[#223488]" title="Saat (opsiyonel) — kart eklendiğinde otomatik sıfırlanır">
                    </div>
                </div>

                <!-- Kısa Not -->
                <input type="text" id="psPresetNote" maxlength="255" placeholder="Not..." class="js-upper w-full bg-white border border-dashed border-[#ec9731]/60 rounded-lg text-[10px] font-medium text-amber-800 px-2 py-1.5 outline-none focus:border-solid focus:border-[#223488]">
            </div>

            <!-- Sekme içerikleri: Alan/Ders (veya Kaynak/Manuel) -->
            <div class="p-2.5 space-y-2 border-b border-slate-100 flex-shrink-0">
                <div class="ps-pane" data-pspane="mufredat">
                    <div class="grid grid-cols-2 gap-1.5 mb-1.5">
                        <select id="psCat" class="w-full bg-white border border-red-300 rounded-lg text-[11px] font-semibold text-slate-700 p-2 outline-none focus:border-red-500"><option value="" disabled selected>Alan...</option></select>
                        <select id="psSubj" disabled class="w-full bg-white border border-red-300 rounded-lg text-[11px] font-semibold text-slate-700 p-2 outline-none focus:border-red-500 disabled:opacity-50"><option value="" disabled selected>Ders...</option></select>
                    </div>
                </div>
                <div class="ps-pane hidden" data-pspane="kaynak">
                    <select id="psRes" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[11px] font-semibold text-slate-700 p-2 outline-none focus:border-[#223488]"><option value="" disabled selected>Kaynak seçiniz...</option></select>
                </div>
                <div class="ps-pane hidden" data-pspane="manuel">
                    <input id="psManSubj" placeholder="DERS ADI (ÖRN: GEOMETRİ)" class="js-upper w-full bg-slate-50 border border-slate-200 rounded-lg text-[11px] font-semibold text-slate-700 p-2 outline-none focus:border-[#223488] mb-1.5 uppercase">
                    <div class="flex gap-1.5">
                        <input id="psManTopic" placeholder="KONU / NOT" class="js-upper flex-1 bg-slate-50 border border-slate-200 rounded-lg text-[11px] font-semibold text-slate-700 p-2 outline-none focus:border-[#223488] uppercase">
                        <button type="button" onclick="psAddPill()" class="px-3 rounded-lg bg-[#223488] text-white text-[11px] font-black hover:bg-[#314595] transition">＋</button>
                    </div>
                    <p class="text-[9px] text-slate-400 mt-1">Oluşan hap sürüklenebilir; tekrar tekrar kullan.</p>
                </div>
            </div>

            <!-- Konu ara -->
            <div class="p-2.5 pb-1.5 flex-shrink-0">
                <input id="psSearch" type="search" placeholder="🔍 Konu ara..." class="w-full bg-slate-50 border border-slate-200 rounded-lg text-[11px] font-medium text-slate-600 p-2 outline-none focus:border-[#223488]">
            </div>

            <!-- Sürüklenebilir konu listesi -->
            <div id="psTopicList" class="flex-grow overflow-y-auto custom-scrollbar p-2 space-y-1 min-h-0">
                <p class="ps-list-hint text-center text-[10px] text-slate-400 font-medium py-8">Alan ve ders seçince<br>konular burada listelenir.<br><b>Konuyu güne sürükle.</b></p>
            </div>
        </div>

        <!-- ═══ HAFTA TAHTASI ═══ -->
        <div class="flex-grow overflow-x-auto overflow-y-hidden min-w-0 ps-board-scroll">
            <div class="flex gap-2 p-3 h-full min-w-max">
                <?php foreach ($week_dates as $wd):
                    $dayName = $gunlerTR[date('l', strtotime($wd))] ?? '';
                    $isToday = ($wd === date('Y-m-d'));
                ?>
                <div class="ps-col w-[215px] flex-shrink-0 flex flex-col bg-white rounded-2xl border <?php echo $isToday ? 'border-[#ec9731]' : 'border-slate-200'; ?> overflow-hidden h-full" data-date="<?php echo $wd; ?>">
                    <div class="px-3 py-2 bg-gradient-to-r from-[#223488] to-[#314595] text-white flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-base leading-none"><?php echo date('d', strtotime($wd)); ?></span>
                            <span class="text-[10px] font-black uppercase"><?php echo htmlspecialchars($dayName); ?></span>
                        </div>
                        <span class="ps-col-count text-[9px] font-bold bg-white/15 rounded px-1.5 py-0.5">0</span>
                    </div>
                    <div class="ps-drop flex-grow overflow-y-auto custom-scrollbar p-1.5 space-y-1.5 min-h-0 transition-colors" data-date="<?php echo $wd; ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var API = '/ajax/education_api.php';
    var SID = <?php echo (int)$sid; ?>;
    var WEEK = <?php echo json_encode(array_values($week_dates)); ?>;
    var EXISTING = <?php echo json_encode($psItems, JSON_UNESCAPED_UNICODE); ?>;
    var DRAFT_KEY  = 'psDraft:' + SID + ':' + WEEK[0];
    var PRESET_KEY = 'psPreset';

    var studio = document.getElementById('plannerStudio');
    if (!studio) return;

    // ── Durum ──
    var cards = [];        // {uid,id,date,category,subject,topic,edu_topic_id,custom_subject,custom_topic,resource_id,resource_title,resource_type,action_type,amount,time_note,task_note,status,deleted}
    var original = {};     // id -> ilk sunucu hali (diff için)
    var pills = [];        // manuel haplar (oturumluk)
    var uidSeq = 1;
    var openEditor = null; // açık yerinde-düzenleyici uid

    function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
    function trUp(s){ return String(s||'').toLocaleUpperCase('tr-TR'); }

    // ── Kalem (sticky preset — oturumlar arası hatırlanır) ──
    // Tür/Miktar bilerek hatırlanır (peş peşe aynı ayarla eklemeyi hızlandırır);
    // Zaman/Not her kart eklendiğinde SIFIRLANIR (bkz. drop handler) — aksi halde
    // 2-3 konu peş peşe sürüklenince hepsine aynı saat/not yapışırdı.
    var preset = { action_type:'soru', amount:20, time_note:'', task_note:'' };
    try { var pp = JSON.parse(localStorage.getItem(PRESET_KEY)||''); if (pp && pp.action_type) preset = pp; } catch(e){}
    // Tek-görev modalındaki (koc/scripts.php updateQuickButtons) ile BİREBİR aynı setler.
    var AMOUNT_SETS = { soru: [10,15,20,25,30,50,75], konu: [20,30,40,50,60,75,90] };
    function renderAmountChips(){
        var list = AMOUNT_SETS[preset.action_type] || AMOUNT_SETS.soru;
        var isKonu = preset.action_type === 'konu';
        var activeCls = isKonu ? 'bg-[#ec9731] border-[#ec9731] text-white' : 'bg-[#223488] border-[#223488] text-white';
        var hoverCls  = isKonu ? 'hover:border-[#ec9731]' : 'hover:border-[#223488]';
        document.getElementById('psAmountChips').innerHTML = list.map(function(q){
            var on = q === preset.amount;
            return '<button type="button" onclick="psSetPresetAmount(' + q + ')" class="ps-amt-chip w-8 h-8 rounded-full border-2 text-[10px] font-black transition ' +
                (on ? activeCls : ('bg-white border-slate-200 ' + hoverCls + ' text-slate-600')) + '">' + q + '</button>';
        }).join('');
    }
    function syncPresetBoxColor(){
        var isKonu = preset.action_type === 'konu';
        document.getElementById('psPresetBox').className = 'border-b p-2.5 space-y-2 flex-shrink-0 ' +
            (isKonu ? 'border-[#ec9731]/40 bg-[#fdf3e7]/60' : 'border-[#223488]/30 bg-[#223488]/5');
        document.getElementById('psPresetLabel').className = 'text-[9px] font-black uppercase tracking-wider block ' + (isKonu ? 'text-[#d68625]' : 'text-[#223488]');
    }
    function syncPresetUI(){
        document.getElementById('psPresetSoru').className = 'py-2 rounded-lg text-xs font-black transition ' + (preset.action_type==='soru' ? 'bg-[#223488] text-white' : 'bg-white border-2 border-slate-200 text-slate-500');
        document.getElementById('psPresetKonu').className = 'py-2 rounded-lg text-xs font-black transition ' + (preset.action_type==='konu' ? 'bg-[#ec9731] text-white' : 'bg-white border-2 border-slate-200 text-slate-500');
        document.getElementById('psPresetAmount').value = preset.amount;
        document.getElementById('psPresetTime').value = preset.time_note || '';
        document.getElementById('psPresetNote').value = preset.task_note || '';
        renderAmountChips();
        syncPresetBoxColor();
    }
    function savePreset(){ try { localStorage.setItem(PRESET_KEY, JSON.stringify(preset)); } catch(e){} }
    window.psSetPresetType = function(t){ preset.action_type = t; savePreset(); syncPresetUI(); };
    window.psSetPresetAmount = function(a){ preset.amount = a; savePreset(); syncPresetUI(); };
    document.getElementById('psPresetAmount').addEventListener('input', function(){ preset.amount = Math.max(1, parseInt(this.value,10)||1); savePreset(); syncPresetUI(); });
    document.getElementById('psPresetTime').addEventListener('input', function(){ preset.time_note = this.value; savePreset(); });
    document.getElementById('psPresetNote').addEventListener('input', function(){ preset.task_note = trUp(this.value.trim()); savePreset(); });

    // ── Gömülü panel: masaüstünde üst-barın altına yerleşir, Program sekmesinde görünür ──
    var DESKTOP = function(){ return window.matchMedia('(min-width:1024px)').matches; };
    var built = false;
    function positionStudio(){
        // Paylaşılan üst bar (öğrenci/tab/nav) altından başlasın
        var anchor = document.getElementById('kocTopBar');
        var top = anchor ? Math.round(anchor.getBoundingClientRect().bottom) : 96;
        studio.style.top = Math.max(0, top) + 'px';
    }
    function studioVisible(){ return studio.style.display !== 'none'; }
    // Üst-bardaki Program'a özel öğeler (takvim navigasyonu + taslak/kaydet):
    // yalnızca Program sekmesinde görünür. Program'da → sınıflara bırak (mobilde
    // 'hidden', masaüstünde 'lg:flex'); diğer sekmelerde → inline 'none' ile gizle.
    function syncProgramBar(onProgram){
        document.querySelectorAll('.ps-progbar').forEach(function(el){
            el.style.display = onProgram ? '' : 'none';
        });
    }
    // scripts.php openTab() bunu çağırır: Program sekmesi masaüstünde stüdyoyu gösterir
    window.psShowStudio = function(show){
        syncProgramBar(!!show);
        if (show && DESKTOP()) {
            if (!built) { buildState(); loadCats(); built = true; }
            positionStudio();
            studio.style.display = 'flex';
        } else {
            studio.style.display = 'none';
        }
    };
    // Eski buton API'si korunur (mobilde çağrılmaz; masaüstünde göster)
    window.openPlannerStudio = function(){ window.psShowStudio(true); };
    window.closePlannerStudio = function(){ window.psShowStudio(false); };

    // Konum güncelle (kaydırma/yeniden boyutlanma); mobile küçülünce gizle
    window.addEventListener('resize', function(){
        if (!DESKTOP()) { studio.style.display = 'none'; return; }
        if (studioVisible()) positionStudio();
    });
    window.addEventListener('scroll', function(){ if (studioVisible()) positionStudio(); }, {passive:true});
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && openEditor && studioVisible()) { openEditor = null; renderBoard(); }
    });

    // Açılışta: Program sekmesi ise üst-bar araçlarını göster; masaüstünde stüdyoyu aç
    window.addEventListener('DOMContentLoaded', function(){
        var sched = document.getElementById('content-schedule');
        var active = sched && !sched.classList.contains('hidden');
        syncProgramBar(!!active);
        if (DESKTOP() && active) window.psShowStudio(true);
    });

    // ── Durumu kur: sunucu hali + varsa taslak ──
    function buildState(){
        cards = []; original = {};
        EXISTING.forEach(function(it){
            original[it.id] = JSON.parse(JSON.stringify(it));
            cards.push(Object.assign({ uid:'e'+it.id, deleted:false }, JSON.parse(JSON.stringify(it))));
        });
        try {
            var d = JSON.parse(localStorage.getItem(DRAFT_KEY)||'');
            if (d && d.v === 1) {
                (d.news||[]).forEach(function(n){ n.uid = 'n'+(uidSeq++); n.id = null; n.status='bekliyor'; n.deleted=false; cards.push(n); });
                Object.keys(d.mods||{}).forEach(function(id){
                    var c = cards.find(function(x){ return x.id == id; });
                    if (c) Object.assign(c, d.mods[id]);
                });
                (d.dels||[]).forEach(function(id){
                    var c = cards.find(function(x){ return x.id == id; });
                    if (c) c.deleted = true;
                });
            }
        } catch(e){}
        syncPresetUI();
        renderBoard();
    }

    // ── Diff & taslak ──
    var FIELDS = ['date','amount','action_type','time_note','task_note'];
    function diff(){
        var news = [], mods = {}, dels = [];
        cards.forEach(function(c){
            if (!c.id) { if (!c.deleted) news.push(c); return; }
            if (c.deleted) { dels.push(c.id); return; }
            var o = original[c.id]; if (!o) return;
            var ch = {};
            FIELDS.forEach(function(f){ if (String(c[f]??'') !== String(o[f]??'')) ch[f] = c[f]; });
            if (Object.keys(ch).length) mods[c.id] = ch;
        });
        return { news:news, mods:mods, dels:dels };
    }
    var draftTimer = null;
    function saveDraft(){
        clearTimeout(draftTimer);
        setStatus('⏳ Kaydediliyor…', 'bg-white/10');
        draftTimer = setTimeout(function(){
            var d = diff();
            var n = d.news.length + Object.keys(d.mods).length + d.dels.length;
            try {
                if (n) localStorage.setItem(DRAFT_KEY, JSON.stringify({ v:1, ts:Date.now(),
                    news: d.news.map(function(c){ var x = Object.assign({}, c); delete x.uid; delete x.id; delete x.deleted; delete x.status; return x; }),
                    mods: d.mods, dels: d.dels }));
                else localStorage.removeItem(DRAFT_KEY);
                setStatus(navigator.onLine === false ? '⚠ Çevrimdışı — taslak yerelde güvende' : '✓ Taslak kaydedildi', 'bg-white/10');
            } catch(e){ setStatus('⚠ Taslak yazılamadı', 'bg-red-500/30'); }
            updateSaveBtn(n);
        }, 250);
    }
    function setStatus(txt, cls){
        var el = document.getElementById('psDraftStatus');
        if (!el) return;
        var tone = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        if (cls && cls.indexOf('red') !== -1)        tone = 'bg-red-50 text-red-600 border-red-200';
        else if (cls && cls.indexOf('amber') !== -1) tone = 'bg-amber-50 text-amber-700 border-amber-200';
        el.textContent = txt;
        el.className = 'hidden lg:inline-flex items-center gap-1.5 text-[10px] font-bold border rounded-lg px-2.5 py-1.5 whitespace-nowrap ' + tone;
    }
    function updateSaveBtn(n){
        var b = document.getElementById('psSaveBtn');
        if (b) b.disabled = !n;
        var c = document.getElementById('psSaveCount');
        if (c) c.textContent = n ? '('+n+')' : '';
    }
    window.psResetDraft = function(){
        if (!confirm('Kaydedilmemiş tüm değişiklikler atılacak ve tablo sunucudaki haline dönecek. Emin misin?')) return;
        localStorage.removeItem(DRAFT_KEY);
        buildState(); saveDraft();
    };

    // ── Tahta render ──
    var STATUS_META = { bekliyor:['⏳','border-slate-300'], yapildi:['✅','border-green-300'], yarim:['⚠️','border-orange-400'], yapilmadi:['❌','border-red-400'] };
    function renderBoard(){
        document.querySelectorAll('#plannerStudio .ps-drop').forEach(function(zone){
            var date = zone.dataset.date;
            zone.innerHTML = '';
            var dayCards = cards.filter(function(c){ return c.date === date; });
            dayCards.forEach(function(c){ zone.appendChild(cardEl(c)); });
            var col = zone.closest('.ps-col');
            col.querySelector('.ps-col-count').textContent = dayCards.filter(function(c){ return !c.deleted; }).length;
        });
        saveDraft();
    }

    function cardEl(c){
        var el = document.createElement('div');
        el.dataset.uid = c.uid;
        if (c.deleted) {
            el.className = 'rounded-lg border border-dashed border-red-300 bg-red-50/60 p-2 opacity-70';
            el.innerHTML = '<div class="flex items-center justify-between gap-1">' +
                '<span class="text-[10px] font-bold text-red-400 line-through truncate">' + esc((c.subject||'') + ' › ' + (c.topic||'')) + '</span>' +
                '<button type="button" class="ps-undo text-[9px] font-black text-red-500 hover:text-red-700 whitespace-nowrap">↩ Geri Al</button></div>';
            el.querySelector('.ps-undo').addEventListener('click', function(ev){ ev.stopPropagation(); c.deleted = false; renderBoard(); });
            return el;
        }
        var isNew = !c.id;
        var o = c.id ? original[c.id] : null;
        var isMod = o && FIELDS.some(function(f){ return String(c[f]??'') !== String(o[f]??''); });
        var sm = STATUS_META[c.status] || STATUS_META.bekliyor;
        var actMeta = { soru:['Soru','bg-[#223488]'], konu:['Dakika','bg-[#ec9731]'], video:['Video','bg-red-600'] };
        var am = actMeta[c.action_type] || actMeta.soru;
        el.className = 'ps-card group rounded-lg border-l-4 border bg-white p-2 shadow-sm cursor-pointer transition hover:shadow-md ' + sm[1] +
                       (isNew ? ' ring-2 ring-indigo-200' : (isMod ? ' ring-2 ring-amber-200' : ''));
        el.draggable = true;
        el.innerHTML =
            '<div class="flex items-center justify-between gap-1 mb-0.5">' +
                '<span class="text-[8px] font-black uppercase px-1.5 py-0.5 rounded ' + (c.resource_title ? 'bg-red-50 text-red-600 border border-red-200' : 'bg-[#223488] text-white') + ' truncate max-w-[110px]">' + esc(c.resource_title || c.category || 'Diğer') + '</span>' +
                '<span class="flex items-center gap-1 shrink-0">' +
                    (isNew ? '<span class="text-[7px] font-black text-indigo-500 bg-indigo-50 rounded px-1 py-0.5">YENİ</span>' : (isMod ? '<span class="text-[7px] font-black text-amber-600 bg-amber-50 rounded px-1 py-0.5">DEĞİŞTİ</span>' : '')) +
                    (c.id
                        ? '<button type="button" class="ps-status-btn text-[13px] leading-none hover:scale-125 transition" title="Durumu/görevi eski pencerede düzenle">' + sm[0] + '</button>'
                        : '<span class="text-[11px]">' + sm[0] + '</span>') +
                '</span>' +
            '</div>' +
            '<p class="text-[11px] font-extrabold text-slate-900 leading-tight truncate">' + esc(c.subject || '-') + '</p>' +
            '<p class="text-[10px] font-semibold text-[#314595] leading-tight truncate">' + esc(c.topic || '-') + '</p>' +
            '<div class="flex items-center gap-1 mt-1 flex-wrap">' +
                '<span class="' + am[1] + ' text-white rounded px-1.5 py-[1px] text-[8px] font-black">' + c.amount + ' ' + am[0] + '</span>' +
                (c.time_note ? '<span class="text-[8px] font-bold text-[#ec9731]">⏰' + esc(c.time_note) + '</span>' : '') +
                (c.task_note ? '<span class="text-[8px] font-bold text-amber-700 truncate max-w-[80px]">📝' + esc(c.task_note) + '</span>' : '') +
            '</div>' +
            (openEditor === c.uid ? editorHtml(c) : '');
        // Sürükleme (gün değiştirme)
        el.addEventListener('dragstart', function(ev){
            ev.dataTransfer.setData('text/ps-card', c.uid);
            ev.dataTransfer.effectAllowed = 'copyMove';
        });
        // ⏳ durum ikonu → ESKİ düzenleme penceresi (durum/görev; öğrenci tarafındaki
        // gibi). Yalnızca kayıtlı kartlarda; kartın gövdesine tıklama yerinde
        // düzenleyiciyi açmaya devam eder (o davranış bilerek korundu).
        var stBtn = el.querySelector('.ps-status-btn');
        if (stBtn) stBtn.addEventListener('click', function(ev){
            ev.stopPropagation();
            if (typeof window.openEditModal !== 'function') return;
            window.openEditModal({
                id: c.id, date: c.date, amount: c.amount, status: c.status,
                time_note: c.time_note, action_type: c.action_type, task_note: c.task_note,
                edu_topic_id: c.edu_topic_id,
                custom_subject: c.custom_subject || c.subject, custom_topic: c.custom_topic || c.topic,
                edu_subject_name: c.subject, edu_topic_name: c.topic,
                resource_id: c.resource_id, resource_title: c.resource_title, resource_type: c.resource_type
            }, c.category || '');
        });
        // Tıkla → yerinde düzenleyici
        el.addEventListener('click', function(ev){
            if (ev.target.closest('.ps-editor') || ev.target.closest('button')) return;
            openEditor = (openEditor === c.uid) ? null : c.uid;
            renderBoard();
        });
        if (openEditor === c.uid) bindEditor(el, c);
        return el;
    }

    // ── Yerinde düzenleyici (karta tıklayınca) ──
    function editorHtml(c){
        var lockType = (c.action_type === 'video');
        return '<div class="ps-editor mt-2 pt-2 border-t border-slate-100 space-y-1.5" onclick="event.stopPropagation()">' +
            (lockType ? '<p class="text-[8px] font-bold text-red-500">🎬 Video görev — tür/miktar sabittir.</p>' :
            '<div class="flex gap-1">' +
                '<button type="button" data-ed="tur-soru" class="flex-1 py-1 rounded text-[9px] font-black ' + (c.action_type==='soru'?'bg-[#223488] text-white':'bg-slate-100 text-slate-500') + '">❓ Soru</button>' +
                '<button type="button" data-ed="tur-konu" class="flex-1 py-1 rounded text-[9px] font-black ' + (c.action_type==='konu'?'bg-[#ec9731] text-white':'bg-slate-100 text-slate-500') + '">📖 Konu</button>' +
                '<input type="number" data-ed="amount" min="1" value="' + c.amount + '" class="w-12 bg-slate-50 border border-slate-200 rounded text-[11px] font-black text-center outline-none focus:border-[#ec9731]">' +
            '</div>') +
            '<div class="flex gap-1">' +
                '<input type="time" data-ed="time" value="' + esc(c.time_note||'') + '" class="w-[80px] bg-slate-50 border border-slate-200 rounded text-[9px] font-bold text-center py-1 outline-none">' +
                '<input type="text" data-ed="note" maxlength="255" value="' + esc(c.task_note||'') + '" placeholder="Kısa not" class="js-upper flex-1 min-w-0 bg-slate-50 border border-slate-200 rounded text-[9px] font-medium px-1.5 py-1 outline-none uppercase">' +
            '</div>' +
            '<div class="flex gap-1 pt-0.5">' +
                '<button type="button" data-ed="del" class="px-2 py-1 rounded bg-red-50 text-red-500 text-[9px] font-black hover:bg-red-500 hover:text-white transition">🗑 Sil</button>' +
                '<button type="button" data-ed="ok" class="flex-1 py-1 rounded bg-[#223488] text-white text-[9px] font-black hover:bg-[#314595] transition">✓ Tamam</button>' +
            '</div></div>';
    }
    function bindEditor(el, c){
        var q = function(s){ return el.querySelector('[data-ed="'+s+'"]'); };
        if (q('tur-soru')) q('tur-soru').addEventListener('click', function(){ c.action_type='soru'; renderBoard(); openEditorKeep(c); });
        if (q('tur-konu')) q('tur-konu').addEventListener('click', function(){ c.action_type='konu'; renderBoard(); openEditorKeep(c); });
        if (q('amount')) q('amount').addEventListener('input', function(){ c.amount = Math.max(1, parseInt(this.value,10)||1); saveDraft(); });
        q('time').addEventListener('input', function(){ c.time_note = this.value; saveDraft(); });
        q('note').addEventListener('input', function(){ c.task_note = trUp(this.value); saveDraft(); });
        q('del').addEventListener('click', function(){
            openEditor = null;
            if (c.id) c.deleted = true; else cards = cards.filter(function(x){ return x.uid !== c.uid; });
            renderBoard();
        });
        q('ok').addEventListener('click', function(){ openEditor = null; renderBoard(); });
    }
    function openEditorKeep(c){ openEditor = c.uid; renderBoard(); }

    // ── Gün sütunlarına bırakma (konu → yeni kart, kart → gün değişimi/Ctrl+kopyalama) ──
    // Ana program sayfasıyla (koc/scripts.php Sortable clone) aynı davranış:
    // Ctrl basılıyken bırakılırsa kart KOPYALANIR, değilse taşınır.
    document.querySelectorAll('#plannerStudio .ps-drop').forEach(function(zone){
        zone.addEventListener('dragover', function(ev){
            var t = ev.dataTransfer.types;
            if (t.indexOf('text/ps-topic') !== -1 || t.indexOf('text/ps-card') !== -1) {
                ev.preventDefault();
                zone.classList.add('bg-indigo-50');
                if (t.indexOf('text/ps-card') !== -1) ev.dataTransfer.dropEffect = ev.ctrlKey ? 'copy' : 'move';
            }
        });
        zone.addEventListener('dragleave', function(){ zone.classList.remove('bg-indigo-50'); });
        zone.addEventListener('drop', function(ev){
            zone.classList.remove('bg-indigo-50');
            var cardUid = ev.dataTransfer.getData('text/ps-card');
            if (cardUid) {
                ev.preventDefault();
                var c = cards.find(function(x){ return x.uid === cardUid; });
                if (c && !c.deleted) {
                    if (ev.ctrlKey) {
                        var copy = JSON.parse(JSON.stringify(c));
                        copy.uid = 'n'+(uidSeq++); copy.id = null; copy.status = 'bekliyor'; copy.deleted = false;
                        copy.date = zone.dataset.date;
                        cards.push(copy);
                    } else {
                        c.date = zone.dataset.date;
                    }
                    renderBoard();
                }
                return;
            }
            var raw = ev.dataTransfer.getData('text/ps-topic');
            if (!raw) return;
            ev.preventDefault();
            var p; try { p = JSON.parse(raw); } catch(e){ return; }
            var isVideo = (p.resource_type === 'video' && p.resource_id);
            cards.push({
                uid: 'n'+(uidSeq++), id: null, date: zone.dataset.date,
                category: p.category || (p.edu_topic_id ? '' : 'Diğer'),
                subject: p.subject || '', topic: p.topic || '',
                edu_topic_id: p.edu_topic_id || null,
                custom_subject: p.subject || '', custom_topic: p.topic || '',
                resource_id: p.resource_id || null, resource_title: p.resource_title || '',
                resource_type: p.resource_type || '',
                action_type: isVideo ? 'video' : preset.action_type,
                amount: isVideo ? 1 : preset.amount,
                time_note: preset.time_note || '', task_note: preset.task_note || '',
                status: 'bekliyor', deleted: false
            });
            // Zaman/Not yalnızca BU kart için kullanılsın — peş peşe sürüklemede
            // bir sonraki karta yapışmasın. Tür/Miktar bilerek sıfırlanmaz (sticky).
            preset.time_note = ''; preset.task_note = '';
            savePreset(); syncPresetUI();
            renderBoard();
        });
    });

    // ── Palet: sekmeler ──
    var currentTab = 'mufredat';
    document.querySelectorAll('#plannerStudio .ps-tab').forEach(function(btn){
        btn.addEventListener('click', function(){
            currentTab = btn.dataset.pstab;
            document.querySelectorAll('#plannerStudio .ps-tab').forEach(function(b){
                var on = b === btn;
                b.className = 'ps-tab flex-1 py-2 rounded-lg transition ' + (on ? 'bg-[#ec9731] text-white' : 'text-slate-500 hover:text-[#223488]');
            });
            document.querySelectorAll('#plannerStudio .ps-pane').forEach(function(p){ p.classList.toggle('hidden', p.dataset.pspane !== btn.dataset.pstab); });
            if (currentTab === 'kaynak') loadResources();
            renderTopicList();
        });
    });
    function activeTab(){ return currentTab; }

    // ── Palet: müfredat verisi ──
    var catsP = null, resP = null, topics = [], resTopics = [];
    var catSel = document.getElementById('psCat'), subjSel = document.getElementById('psSubj'), resSel = document.getElementById('psRes');
    function loadCats(){
        if (catsP) return catsP;
        catsP = fetch(API + '?action=categories', {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if (!j.ok) return;
            catSel.innerHTML = '<option value="" disabled selected>Alan...</option>' + j.data.map(function(c){ return '<option value="'+c.id+'" data-name="'+esc(c.name)+'">'+esc(c.name)+'</option>'; }).join('');
        }).catch(function(){ catsP = null; });
        return catsP;
    }
    catSel.addEventListener('change', function(){
        subjSel.innerHTML = '<option value="" disabled selected>Yükleniyor...</option>'; subjSel.disabled = true;
        topics = []; renderTopicList();
        fetch(API + '?action=subjects&category_id=' + catSel.value, {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if (!j.ok) return;
            subjSel.innerHTML = '<option value="" disabled selected>Ders...</option>' + j.data.map(function(s){ return '<option value="'+s.id+'" data-name="'+esc(s.lesson_name)+'">'+esc(s.lesson_name)+'</option>'; }).join('');
            subjSel.disabled = false;
        }).catch(function(){});
    });
    subjSel.addEventListener('change', function(){
        topics = []; renderTopicList(true);
        fetch(API + '?action=topics&subject_id=' + subjSel.value + '&per_page=500', {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if (!j.ok) return;
            var catName = catSel.options[catSel.selectedIndex] ? catSel.options[catSel.selectedIndex].dataset.name : '';
            var subjName = subjSel.options[subjSel.selectedIndex] ? subjSel.options[subjSel.selectedIndex].dataset.name : '';
            topics = j.data.map(function(t){ return { edu_topic_id: t.id, category: catName, subject: subjName, topic: t.topic_name }; });
            renderTopicList();
        }).catch(function(){ renderTopicList(); });
    });
    function loadResources(){
        if (resP) return resP;
        resP = fetch(API + '?action=resources&per_page=200', {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if (!j.ok || !j.data.length) { resSel.innerHTML = '<option value="" disabled selected>Henüz kaynak yok</option>'; return; }
            var ic = {kitap:'📕', deneme:'📝', pdf:'📄', video:'🎬', diger:'📦'};
            resSel.innerHTML = '<option value="" disabled selected>Kaynak seçiniz...</option>' + j.data.map(function(res){
                return '<option value="'+res.id+'" data-title="'+esc(res.title)+'" data-type="'+esc(res.type||'')+'">'+(ic[res.type]||'📦')+' '+esc(res.title)+(res.type==='video'?' [VİDEO]':'')+' ('+(res.topic_count||0)+')</option>';
            }).join('');
        }).catch(function(){ resP = null; });
        return resP;
    }
    resSel.addEventListener('change', function(){
        resTopics = []; renderTopicList(true);
        var o = resSel.options[resSel.selectedIndex];
        fetch(API + '?action=resource_topics&resource_id=' + resSel.value, {credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
            if (!j.ok) return;
            resTopics = j.data.map(function(t){ return { edu_topic_id: t.id, category: t.category_name, subject: t.lesson_name, topic: t.topic_name, resource_id: resSel.value, resource_title: o ? o.dataset.title : '', resource_type: o ? o.dataset.type : '' }; });
            renderTopicList();
        }).catch(function(){ renderTopicList(); });
    });

    // ── Palet: manuel haplar ──
    window.psAddPill = function(){
        var s = trUp(document.getElementById('psManSubj').value.trim());
        var t = trUp(document.getElementById('psManTopic').value.trim());
        if (!s && !t) return;
        pills.push({ category:'Diğer', subject:s, topic:t });
        document.getElementById('psManTopic').value = '';
        renderTopicList();
    };
    document.getElementById('psManTopic').addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); psAddPill(); } });

    // ── Palet: konu listesi render (sürüklenebilir satırlar) ──
    // STATS lazy okunur (modals.php window.eduTopicStats'ı bu dosyadan SONRA
    // tanımlayabilir; render anında okuyunca sıralama sorunu olmaz).
    document.getElementById('psSearch').addEventListener('input', function(){ renderTopicList(); });
    function renderTopicList(loading){
        var list = document.getElementById('psTopicList');
        var tab = activeTab();
        var q = trUp(document.getElementById('psSearch').value.trim());
        var rows = tab === 'mufredat' ? topics : (tab === 'kaynak' ? resTopics : pills);
        list.innerHTML = '';
        if (loading) { list.innerHTML = '<p class="text-center text-[10px] text-slate-400 py-8">Yükleniyor...</p>'; return; }
        if (!rows.length) {
            list.innerHTML = '<p class="ps-list-hint text-center text-[10px] text-slate-400 font-medium py-8">' +
                (tab === 'mufredat' ? 'Alan ve ders seçince<br>konular burada listelenir.<br><b>Konuyu güne sürükle.</b>' :
                 tab === 'kaynak' ? 'Kaynak seçince konuları<br>burada listelenir.' : 'Ders + konu yazıp ＋ ile<br>sürüklenebilir hap oluştur.') + '</p>';
            return;
        }
        var STATS = window.eduTopicStats || {};
        rows.forEach(function(t){
            if (q && trUp(t.topic).indexOf(q) === -1 && trUp(t.subject).indexOf(q) === -1) return;
            var st = t.edu_topic_id ? STATS[t.edu_topic_id] : null;
            var badge = '';
            if (st && (st.soru || st.konu || st.video)) {
                var parts = []; if (st.soru) parts.push(st.soru+'s'); if (st.konu) parts.push(st.konu+'k'); if (st.video) parts.push(st.video+'v');
                badge = '<span class="shrink-0 text-[8px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded px-1">✓' + parts.join('·') + '</span>';
            }
            var row = document.createElement('div');
            row.draggable = true;
            row.className = 'flex items-center justify-between gap-1.5 px-2 py-1.5 rounded-lg border border-slate-100 bg-white hover:border-[#223488]/40 hover:bg-indigo-50/50 cursor-grab active:cursor-grabbing transition select-none';
            row.innerHTML = '<span class="text-[10px] font-bold text-slate-700 truncate">' +
                (t.resource_type === 'video' ? '🎬 ' : '⠿ ') + esc(t.topic || t.subject) + '</span>' + badge;
            row.title = (t.subject ? t.subject + ' › ' : '') + (t.topic || '');
            row.addEventListener('dragstart', function(ev){
                ev.dataTransfer.setData('text/ps-topic', JSON.stringify(t));
                ev.dataTransfer.effectAllowed = 'copy';
            });
            list.appendChild(row);
        });
    }

    // ── Haftayı Kaydet: tüm diff tek istekte, tek transaction ──
    window.psSaveWeek = function(){
        var d = diff();
        var total = d.news.length + Object.keys(d.mods).length + d.dels.length;
        if (!total) return;
        var btn = document.getElementById('psSaveBtn');
        btn.disabled = true; var old = btn.innerHTML; btn.innerHTML = '⏳ Kaydediliyor...';
        var ops = {
            create: d.news.map(function(c){ return { date:c.date, amount:c.amount, action_type:c.action_type, time_note:c.time_note, task_note:c.task_note, edu_topic_id:c.edu_topic_id, custom_subject:c.custom_subject, custom_topic:c.custom_topic, resource_id:c.resource_id, resource_title:c.resource_title }; }),
            update: Object.keys(d.mods).map(function(id){
                var c = cards.find(function(x){ return x.id == id; });
                return { id: parseInt(id,10), date:c.date, amount:c.amount, action_type:c.action_type, time_note:c.time_note, task_note:c.task_note, edu_topic_id:c.edu_topic_id, custom_subject:c.custom_subject, custom_topic:c.custom_topic, resource_id:c.resource_id, resource_title:c.resource_title };
            }),
            delete: d.dels
        };
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ ajax: 'plan_apply', ops: JSON.stringify(ops) })
        })
        .then(function(r){ return r.text(); })
        .then(function(t){
            var j; try { j = JSON.parse(t); } catch(e){ throw new Error('Sunucu yanıtı okunamadı — bağlantıyı kontrol edin (taslağın yerelde güvende).'); }
            if (!j.ok) throw new Error(j.error || 'Kaydedilemedi');
            localStorage.removeItem(DRAFT_KEY);
            btn.innerHTML = '✅ Kaydedildi (' + (j.created + j.updated + j.deleted) + ')';
            // Reload sonrası masaüstünde stüdyo Program sekmesinde otomatik açılır.
            setTimeout(function(){ window.location.reload(); }, 500);
        })
        .catch(function(err){
            btn.disabled = false; btn.innerHTML = old;
            setStatus('⚠ ' + (err.message || 'Kayıt hatası — taslak yerelde güvende'), 'bg-red-500/40');
        });
    };

    // Çevrimiçi/çevrimdışı durum yansıması
    window.addEventListener('offline', function(){ setStatus('⚠ Çevrimdışı — taslak yerelde güvende', 'bg-amber-500/40'); });
    window.addEventListener('online',  function(){ setStatus('✓ Bağlantı geri geldi — taslak hazır', 'bg-white/10'); });
})();
</script>
