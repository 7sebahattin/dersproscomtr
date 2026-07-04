<div id="addModalV3" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#223488]/20 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl shadow-[#223488]/20 overflow-hidden relative transform transition-all scale-100 border border-slate-100 flex flex-col max-h-[90vh]">
        
        <div class="relative bg-gradient-to-r from-[#223488] to-[#314595] p-5 flex justify-between items-center text-white flex-shrink-0">
            <div class="absolute left-0 top-0 h-full w-1.5 bg-[#ec9731]"></div>
            <h3 class="font-black text-lg tracking-wide pl-2" id="modalTitleV3">GÖREV EKLE</h3>
            <button type="button" onclick="closeModalV3()" class="bg-white/10 hover:bg-white/20 text-white rounded-full p-1.5 transition duration-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="overflow-y-auto custom-scrollbar flex-grow p-6">
            <form method="POST" id="scheduleFormV3">
                <input type="hidden" name="save_schedule" value="1">
                <input type="hidden" name="schedule_id" id="scheduleIdV3">
                <input type="hidden" name="date" id="modalDateV3">

                <!-- ═══ YENİ İKİ KATMANLI KONU SEÇİCİ (Müfredattan / Kaynaktan / Manuel) ═══ -->
                <input type="hidden" name="edu_topic_id" id="eduTopicIdV3">
                <!-- Seçilen konunun okunur adları (görünürlük + eski uyumluluk için) -->
                <input type="hidden" name="custom_subject" id="customSubjectV3">
                <input type="hidden" name="custom_topic"   id="customTopicV3">
                <!-- Kaynaktan seçildiyse kaynak adı (kartta kırmızı rozet olarak gösterilir) -->
                <input type="hidden" name="resource_title" id="resourceTitleV3">
                <!-- Kaynak bağı: video URL/tipi render'da bu id üzerinden canlı gelir -->
                <input type="hidden" name="resource_id" id="resourceIdV3">
                <!-- Video görev türü (video kaynak seçilince JS işaretler) -->
                <input type="radio" name="action_type" value="video" id="videoRadioV3" class="sr-only" tabindex="-1">

                <div class="flex bg-slate-100 p-1 rounded-xl mb-4 border border-slate-200 text-[11px] font-bold">
                    <button type="button" data-etab="mufredat" class="edu-tab flex-1 py-2.5 rounded-lg bg-[#ec9731] text-white shadow-sm transition">📚 MÜFREDATTAN</button>
                    <button type="button" data-etab="kaynak"   class="edu-tab flex-1 py-2.5 rounded-lg text-slate-500 hover:text-[#223488] transition">🔗 KAYNAKTAN</button>
                    <button type="button" data-etab="manuel"   class="edu-tab flex-1 py-2.5 rounded-lg text-slate-500 hover:text-[#223488] transition">✏️ MANUEL</button>
                </div>

                <!-- Seçili konu özeti -->
                <div id="eduChosen" class="hidden mb-3 flex items-center gap-2 bg-indigo-50 border border-indigo-100 rounded-xl px-3 py-2 text-xs">
                    <span class="text-indigo-500">✓ Seçilen:</span>
                    <span id="eduChosenText" class="font-bold text-indigo-700 truncate flex-1"></span>
                    <button type="button" id="eduClear" class="text-slate-400 hover:text-red-500 font-bold">✕</button>
                </div>

                <!-- MÜFREDATTAN -->
                <div class="edu-pane" data-epane="mufredat">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <select id="eduCatSel" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] outline-none">
                            <option value="" disabled selected>Kategori...</option>
                        </select>
                        <select id="eduSubjSel" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] outline-none disabled:opacity-50">
                            <option value="" disabled selected>Ders...</option>
                        </select>
                    </div>
                    <!-- Özel açılır konu seçici (native select yerine — daha şık + geçmiş adetleri gösterir) -->
                    <div class="relative" id="eduTopicWrap">
                        <button type="button" id="eduTopicBtn" disabled
                            class="w-full flex items-center justify-between gap-2 bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-400 focus:bg-white focus:border-[#223488] outline-none disabled:opacity-50 text-left transition">
                            <span id="eduTopicBtnLabel" class="truncate">Önce kategori ve ders seçin...</span>
                            <svg class="w-4 h-4 text-slate-400 shrink-0 transition-transform" id="eduTopicChevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div id="eduTopicPanel" class="hidden absolute z-[60] left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl shadow-[#223488]/10 max-h-64 overflow-y-auto custom-scrollbar py-1"></div>
                    </div>
                </div>

                <!-- KAYNAKTAN -->
                <div class="edu-pane hidden" data-epane="kaynak">
                    <select id="eduResourceSelect" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 mb-2 outline-none focus:bg-white focus:border-[#223488]">
                        <option value="" disabled selected>Kaynak seçiniz...</option>
                    </select>
                    <select id="eduResourceTopicSel" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] outline-none disabled:opacity-50">
                        <option value="" disabled selected>Önce kaynak seçin...</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1.5">Kaynak seçince yalnızca o kaynağa bağlı konular listelenir.</p>
                </div>

                <!-- MANUEL -->
                <div class="edu-pane hidden space-y-3" data-epane="manuel">
                    <div id="eduManualLockHint" class="hidden bg-amber-50 border border-amber-200 text-amber-700 text-[11px] font-semibold rounded-lg px-3 py-2">
                        🔒 Bu görev müfredat/kaynağa bağlı. Ders/konu adını değiştirmek için üstteki <b>✕</b> ile bağlantıyı kaldırın ya da Müfredattan/Kaynaktan sekmesini kullanın.
                    </div>
                    <div id="eduManualError" class="hidden bg-red-50 border border-red-200 text-red-700 text-[11px] font-semibold rounded-lg px-3 py-2">
                        ⚠️ Ders adı veya konu boş olamaz. Lütfen doldurun ya da Müfredattan/Kaynaktan seçin.
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Ders Adı</label>
                        <input id="eduManualSubject" placeholder="ÖRN: GEOMETRİ" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 uppercase focus:bg-white focus:border-[#223488] outline-none disabled:opacity-60">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Konu / Not</label>
                        <input id="eduManualTopic" placeholder="ÖRN: ÜÇGENLER TEKRAR" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 uppercase focus:bg-white focus:border-[#223488] outline-none disabled:opacity-60">
                    </div>
                    <p class="text-[10px] text-slate-400">Manuel görevler analizde <b>"Diğer"</b> başlığı altında listelenir.</p>
                </div>

                <!-- Video görev bilgi bandı (video kaynak seçilince görünür) -->
                <div id="videoModeInfo" class="hidden mt-4 flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 text-xs font-semibold text-red-600">
                    <span aria-hidden="true">🎬</span>
                    <span>Video İzleme görevi</span>
                </div>

                <div class="mt-5" id="turSectionV3">
                    <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-2 ml-1">Tür</label>
                    <div class="flex gap-3">
                        <label class="cursor-pointer flex-1 group">
                            <input type="radio" name="action_type" value="soru" class="peer sr-only" checked onchange="updateQuickButtons('soru')">
                            <div class="bg-white border-2 border-slate-200 rounded-xl py-2.5 text-center text-xs font-bold text-slate-500 group-hover:border-[#223488]/30 peer-checked:bg-[#223488] peer-checked:text-white peer-checked:border-[#223488] transition-all shadow-sm">
                                ❓ Soru
                            </div>
                        </label>
                        <label class="cursor-pointer flex-1 group">
                            <input type="radio" name="action_type" value="konu" class="peer sr-only" onchange="updateQuickButtons('konu')">
                            <div class="bg-white border-2 border-slate-200 rounded-xl py-2.5 text-center text-xs font-bold text-slate-500 group-hover:border-[#ec9731]/50 peer-checked:bg-[#ec9731] peer-checked:text-white peer-checked:border-[#ec9731] transition-all shadow-sm">
                                📖 Konu
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mt-4" id="quickBtnsWrapV3">
                    <div id="quickButtonsContainer" class="flex gap-2 overflow-x-auto pb-2 custom-scrollbar no-scrollbar touch-pan-x snap-x">
                        </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-2">
                    <div id="miktarBoxV3">
                        <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Miktar</label>
                        <div class="relative">
                            <input type="number" name="amount" id="amountInputV3" placeholder="0" class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl text-sm font-black text-slate-800 text-center h-12 focus:bg-white focus:border-[#ec9731] focus:ring-0 outline-none transition-all" required>
                            <span id="amountUnitLabel" class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400 pointer-events-none">Adet</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Zaman</label>
                        <input type="time" name="time_note" id="timeInputV3" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm text-center h-12 font-bold text-slate-600 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all">
                    </div>
                </div>

                <div class="mt-4">
                    <input type="text" name="task_note" id="taskNoteV3" maxlength="255" placeholder="Kısa Not (opsiyonel)"
                           class="js-upper w-full bg-[#fdf3e7] border border-dashed border-[#ec9731] rounded-xl text-sm p-3 font-medium text-amber-800 placeholder:text-[#ec9731]/70 focus:bg-white focus:border-[#223488] focus:border-solid outline-none transition-all">
                </div>

                <div class="mt-5">
                    <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-2 ml-1">Durum</label>
                    <select name="status" id="statusInputV3" onchange="updateSelectColor(this)" class="w-full h-12 px-3 rounded-xl text-sm font-bold border-2 border-slate-200 focus:border-[#223488] focus:ring-0 transition cursor-pointer bg-white text-slate-600 outline-none">
                        <option value="bekliyor">⏳ Bekliyor</option>
                        <option value="yapildi">✅ Yapıldı</option>
                        <option value="yarim">⚠️ Yarım</option>
                        <option value="yapilmadi">❌ Yapılmadı</option>
                    </select>
                </div>

                <div class="flex gap-3 mt-8 pt-5 border-t border-slate-100">
                    <button type="submit" name="delete_schedule" id="deleteBtnV3" class="bg-red-50 text-red-500 border border-red-100 px-4 rounded-xl font-bold text-xs hover:bg-red-500 hover:text-white hover:border-red-500 transition-all hidden">Sil</button>
                    <button type="button" onclick="closeModalV3()" class="flex-1 bg-white border border-slate-200 text-slate-500 py-3.5 rounded-xl font-bold text-sm hover:bg-slate-50 hover:text-slate-700 transition-colors">Vazgeç</button>
                    <button type="submit" id="saveBtnV3" class="flex-[2] bg-[#223488] text-white py-3.5 rounded-xl font-bold text-sm hover:bg-[#314595] transition-all shadow-lg shadow-[#223488]/20 active:scale-[0.98]">
                        KAYDET
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="addStudentModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#223488]/30 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-slate-200 overflow-hidden animate-fadeIn">
        <div class="bg-[#223488] p-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-sm uppercase tracking-wider flex items-center gap-2">
                <span class="bg-white/10 p-1 rounded">👤</span> Yeni Öğrenci Oluştur
            </h3>
            <button onclick="document.getElementById('addStudentModal').classList.add('hidden')" class="hover:bg-white/10 rounded-full p-1 transition">✕</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="create_student" value="1">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Kullanıcı Adı</label><input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Şifre</label><input type="text" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Ad</label><input type="text" name="first_name" required class="js-upper w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Soyad</label><input type="text" name="last_name" required class="js-upper w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
            </div>
            <div>
                <label class="text-[10px] font-bold text-slate-500 uppercase">Okul Seviyesi</label>
                <select name="school_level" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none">
                    <option value="Lise">Lise</option>
                    <option value="Mezun">Mezun</option>
                    <option value="Ortaokul">Ortaokul (LGS)</option>
                    <option value="Ara Sınıf">Ara Sınıf</option>
                </select>
            </div>
            <div class="pt-2">
                <button type="submit" class="w-full bg-[#ec9731] hover:bg-[#d68625] text-white font-bold py-3 rounded-xl shadow-md transition">OLUŞTUR VE BAĞLA</button>
            </div>
        </form>
    </div>
</div>

<div id="linkStudentModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#223488]/30 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl border border-slate-200 overflow-hidden animate-fadeIn">
        <div class="bg-[#223488] p-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-sm uppercase tracking-wider flex items-center gap-2">🔗 Mevcut Öğrenciyi Bağla</h3>
            <button onclick="document.getElementById('linkStudentModal').classList.add('hidden')" class="hover:bg-white/10 rounded-full p-1 transition">✕</button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="link_student" value="1">
            <p class="text-xs text-slate-500">Sistemde kayıtlı bir öğrenciyi listeye eklemek için:</p>
            <div><label class="text-[10px] font-bold text-slate-500 uppercase">Kullanıcı Adı / E-posta</label><input type="text" name="search_term" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-3 text-sm outline-none font-bold"></div>
            <div class="pt-2"><button type="submit" class="w-full bg-[#223488] hover:bg-[#314595] text-white font-bold py-3 rounded-xl shadow-md transition">BUL VE BAĞLA</button></div>
        </form>
    </div>
</div>

<div id="updateStudentModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#223488]/30 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl border border-slate-200 overflow-hidden animate-fadeIn">
        <div class="bg-[#223488] p-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-sm uppercase tracking-wider flex items-center gap-2">✏️ Öğrenci Bilgilerini Güncelle</h3>
            <button onclick="document.getElementById('updateStudentModal').classList.add('hidden')" class="hover:bg-white/10 rounded-full p-1 transition">✕</button>
        </div>
        <?php if(isset($selected_student) && $selected_student): ?>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="update_student_info" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Ad</label><input type="text" name="first_name" value="<?php echo $selected_student['first_name']; ?>" class="js-upper w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Soyad</label><input type="text" name="last_name" value="<?php echo $selected_student['last_name']; ?>" class="js-upper w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Öğrenci Telefon</label><input type="text" name="student_phone" value="<?php echo $selected_student['phone']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Veli Telefon</label><input type="text" name="parent_phone" value="<?php echo $selected_student['parent_phone']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
            </div>
            <div class="pt-4 flex gap-3">
                 <button type="button" onclick="document.getElementById('updateStudentModal').classList.add('hidden')" class="flex-1 bg-white border border-slate-200 text-slate-500 py-3 rounded-xl font-bold text-sm">İptal</button>
                 <button type="submit" class="flex-[2] bg-[#223488] hover:bg-[#314595] text-white font-bold py-3 rounded-xl shadow-md transition">GÜNCELLE</button>
            </div>
        </form>
        <?php else: ?><div class="p-8 text-center text-slate-500">Önce bir öğrenci seçmelisiniz.</div><?php endif; ?>
    </div>
</div>

<div id="statusModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden relative animate-fadeIn">
        <div class="bg-slate-800 p-4 flex justify-between items-center text-white">
            <h3 class="font-bold text-sm">Durumu Güncelle</h3>
            <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="bg-white/20 hover:bg-white/30 rounded-full p-1 transition">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="schedule_id" id="statusSchedId">
            <div><p class="text-xs text-slate-400 uppercase font-bold mb-1">Ders / Konu</p><p id="statusTopicName" class="font-bold text-slate-800 text-lg">-</p></div>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-100 p-3 rounded-xl border border-slate-200 flex flex-col justify-center text-center">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">HEDEF</span>
                    <span id="displayTarget" class="text-2xl font-black text-slate-700 mt-1">0</span>
                    <span class="text-[9px] text-slate-400 font-bold" id="targetUnit">Adet</span>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1 ml-1 uppercase">YAPILAN</label>
                    <input type="number" name="amount" id="statusAmount" class="w-full border-2 border-slate-200 rounded-xl p-3 text-center font-black text-xl text-[#223488] focus:border-[#ec9731] outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 ml-1 uppercase">Durum</label>
                <select name="status" id="statusSelect" class="w-full border border-slate-200 rounded-xl p-3 text-sm outline-none bg-white">
                    <option value="bekliyor">⏳ Bekliyor</option>
                    <option value="yapildi">✅ Yapıldı</option>
                    <option value="yarim">⚠️ Yarım Kaldı</option>
                    <option value="yapilmadi">❌ Yapılmadı</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition">Kaydet</button>
        </form>
    </div>
</div>
<?php
// Seçili öğrencinin konu bazlı GEÇMİŞ adetleri (yeni müfredat konusu = edu_topic_id).
// Görev Ekle penceresinde her konunun yanında "kaç soru / kaç konu tamamlandı" rozeti için.
$eduTopicStats = [];
if (!empty($sid)) {
    $addStat = function ($id, $soru, $konu, $video = 0) use (&$eduTopicStats) {
        $id = (int)$id;
        if (!isset($eduTopicStats[$id])) $eduTopicStats[$id] = ['soru' => 0, 'konu' => 0, 'video' => 0];
        $eduTopicStats[$id]['soru']  += (int)$soru;
        $eduTopicStats[$id]['konu']  += (int)$konu;
        $eduTopicStats[$id]['video'] += (int)$video;
    };
    // 1) Doğrudan yeni müfredat konusuna bağlı tamamlanmış görevler
    try {
        $qStats = $pdo->prepare("
            SELECT edu_topic_id AS etid,
                   SUM(CASE WHEN action_type='soru' THEN amount ELSE 0 END) AS soru,
                   SUM(CASE WHEN action_type='konu' THEN 1      ELSE 0 END) AS konu,
                   SUM(CASE WHEN action_type='video' THEN 1     ELSE 0 END) AS video
            FROM schedule_items
            WHERE student_id = ? AND status = 'yapildi' AND edu_topic_id IS NOT NULL
            GROUP BY edu_topic_id
        ");
        $qStats->execute([$sid]);
        while ($r = $qStats->fetch(PDO::FETCH_ASSOC)) $addStat($r['etid'], $r['soru'], $r['konu'], $r['video'] ?? 0);
    } catch (Throwable $e) {}
    // 2) Göç haritası varsa: edu_topic_id atanmamış ESKİ koçluk görevlerini de eşleştir
    try {
        $qMap = $pdo->prepare("
            SELECT m.new_topic_id AS etid,
                   SUM(CASE WHEN si.action_type='soru' THEN si.amount ELSE 0 END) AS soru,
                   SUM(CASE WHEN si.action_type='konu' THEN 1        ELSE 0 END) AS konu
            FROM schedule_items si
            JOIN education_topic_map m ON m.old_topic_id = si.topic_id
            WHERE si.student_id = ? AND si.status = 'yapildi' AND si.edu_topic_id IS NULL
            GROUP BY m.new_topic_id
        ");
        $qMap->execute([$sid]);
        while ($r = $qMap->fetch(PDO::FETCH_ASSOC)) $addStat($r['etid'], $r['soru'], $r['konu']);
    } catch (Throwable $e) { /* göç tablosu yoksa yok say */ }
}
?>
<!-- ═══ YENİ İKİ KATMANLI KONU SEÇİCİ — kendi kendine yeten JS (scripts.php'ye bağımsız) ═══ -->
<script>
(function () {
    var API = '/ajax/education_api.php';
    window.eduTopicStats = <?php echo json_encode($eduTopicStats, JSON_UNESCAPED_UNICODE); ?>;
    var eduIdEl   = document.getElementById('eduTopicIdV3');
    if (!eduIdEl) return;
    var csEl      = document.getElementById('customSubjectV3');
    var ctEl      = document.getElementById('customTopicV3');
    var resTitleEl = document.getElementById('resourceTitleV3');
    var resIdEl    = document.getElementById('resourceIdV3');
    var chosenBox = document.getElementById('eduChosen');
    var chosenTxt = document.getElementById('eduChosenText');
    var catSel    = document.getElementById('eduCatSel');
    var subjSel   = document.getElementById('eduSubjSel');
    var topicBtn   = document.getElementById('eduTopicBtn');
    var topicLabel = document.getElementById('eduTopicBtnLabel');
    var topicPanel = document.getElementById('eduTopicPanel');
    var topicChev  = document.getElementById('eduTopicChevron');
    var STATS      = window.eduTopicStats || {};
    var resSel    = document.getElementById('eduResourceSelect');
    var resTopicSel = document.getElementById('eduResourceTopicSel');
    var manSubj   = document.getElementById('eduManualSubject');
    var manTopic  = document.getElementById('eduManualTopic');
    var manHint   = document.getElementById('eduManualLockHint');
    var catsLoaded = false, resLoaded = false;

    // Manuel alanları kilitle/aç — müfredat/kaynak bağlı bir görev "Manuel" sekmesinde
    // salt-okunur gösterilir; aksi halde metni değiştirmek sessizce bağlantıyı koparıp
    // görevi "Diğer" altına düşürürdü.
    function setManualLock(locked) {
        if (manSubj)  manSubj.disabled  = locked;
        if (manTopic) manTopic.disabled = locked;
        if (manHint)  manHint.classList.toggle('hidden', !locked);
    }

    function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

    // ── Sekme geçişi ──
    document.querySelectorAll('.edu-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.dataset.etab;
            document.querySelectorAll('.edu-tab').forEach(function (b) {
                var on = b.dataset.etab === tab;
                b.className = 'edu-tab flex-1 py-2.5 rounded-lg transition ' + (on ? 'bg-[#ec9731] text-white shadow-sm' : 'text-slate-500 hover:text-[#223488]');
            });
            document.querySelectorAll('.edu-pane').forEach(function (p) {
                p.classList.toggle('hidden', p.dataset.epane !== tab);
            });
            if (tab === 'mufredat' && !catsLoaded) loadCats();
            if (tab === 'kaynak'   && !resLoaded)  loadResources();
        });
    });

    // ── Video görev modu: Tür/Miktar gizlenir, miktar=1, video radyosu işaretlenir ──
    function videoMode(on) {
        var tur  = document.getElementById('turSectionV3');
        var mik  = document.getElementById('miktarBoxV3');
        var qb   = document.getElementById('quickBtnsWrapV3');
        var info = document.getElementById('videoModeInfo');
        var vr   = document.getElementById('videoRadioV3');
        var amt  = document.getElementById('amountInputV3');
        if (on) {
            if (vr) vr.checked = true;
            if (amt) amt.value = 1;
            if (tur) tur.classList.add('hidden');
            if (mik) mik.classList.add('hidden');
            if (qb)  qb.classList.add('hidden');
            if (info) info.classList.remove('hidden');
        } else {
            // video radyosu işaretliyken mod kapanıyorsa varsayılana (soru) dön
            if (vr && vr.checked) {
                var s = document.querySelector('input[name="action_type"][value="soru"]');
                if (s) { s.checked = true; if (typeof updateQuickButtons === 'function') updateQuickButtons('soru'); }
            }
            if (tur) tur.classList.remove('hidden');
            if (mik) mik.classList.remove('hidden');
            if (qb)  qb.classList.remove('hidden');
            if (info) info.classList.add('hidden');
        }
    }

    // ── Seçilen konuyu ayarla ──
    function setChosen(eduId, subject, topic, resourceTitle, resourceId, resourceType) {
        eduIdEl.value = eduId || '';
        if (csEl) csEl.value = subject || '';
        if (ctEl) ctEl.value = topic || '';
        if (resTitleEl) resTitleEl.value = resourceTitle || '';
        if (resIdEl) resIdEl.value = resourceId || '';
        videoMode(resourceType === 'video' && !!resourceId);
        if (eduId || topic) {
            chosenTxt.textContent = (subject ? subject + ' › ' : '') + (topic || '');
            chosenBox.classList.remove('hidden');
        } else {
            chosenBox.classList.add('hidden');
        }
    }
    window.eduTaskSetChosen = setChosen;
    document.getElementById('eduClear').addEventListener('click', function(){
        setChosen('', '', '');
        setManualLock(false); // bağlantı kaldırıldı -> manuel alanlar artık serbestçe düzenlenebilir
    });

    // ── MÜFREDATTAN ──
    function loadCats() {
        catsLoaded = true;
        fetch(API + '?action=categories', {credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if (!j.ok) return;
            catSel.innerHTML = '<option value="" disabled selected>Kategori...</option>' + j.data.map(function(c){return '<option value="'+c.id+'">'+esc(c.name)+'</option>';}).join('');
        }).catch(function(){});
    }
    catSel.addEventListener('change', function () {
        subjSel.innerHTML = '<option value="" disabled selected>Yükleniyor...</option>'; subjSel.disabled = true;
        resetTopicSel('Önce ders seçin...');
        if (!catSel.value) { subjSel.innerHTML = '<option value="" disabled selected>Ders...</option>'; return; }
        fetch(API + '?action=subjects&category_id=' + catSel.value, {credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if (!j.ok) return;
            subjSel.innerHTML = '<option value="" disabled selected>Ders...</option>' + j.data.map(function(s){return '<option value="'+s.id+'">'+esc(s.lesson_name)+'</option>';}).join('');
            subjSel.disabled = false;
        }).catch(function(){});
    });
    // ── Özel açılır konu seçici yardımcıları ──
    function topicPanelOpen(open){
        topicPanel.classList.toggle('hidden', !open);
        topicChev.style.transform = open ? 'rotate(180deg)' : '';
    }
    function resetTopicSel(msg){
        topicBtn.disabled = true;
        topicLabel.textContent = msg;
        topicLabel.className = 'truncate text-slate-400';
        topicPanel.innerHTML = '';
        topicPanelOpen(false);
    }
    // Geçmiş adet rozeti (yeni müfredat konusu için)
    function statBadge(topicId){
        var s = STATS[topicId];
        if (!s || (!s.soru && !s.konu && !s.video)) return '';
        var parts = [];
        if (s.soru) parts.push(s.soru + ' soru');
        if (s.konu) parts.push(s.konu + ' konu');
        if (s.video) parts.push(s.video + ' video');
        return '<span class="ml-2 shrink-0 inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-md px-1.5 py-0.5">✓ ' + parts.join(' · ') + '</span>';
    }
    // Konu satırını seç
    topicPanel.addEventListener('click', function (e) {
        var row = e.target.closest('[data-topic-id]');
        if (!row) return;
        var id = row.getAttribute('data-topic-id');
        var subj = row.getAttribute('data-subject');
        var top = row.getAttribute('data-topic');
        setChosen(id, subj, top);
        topicLabel.textContent = top;
        topicLabel.className = 'truncate text-slate-700 font-semibold';
        topicPanelOpen(false);
    });
    topicBtn.addEventListener('click', function () {
        if (topicBtn.disabled) return;
        topicPanelOpen(topicPanel.classList.contains('hidden'));
    });
    // Dışarı tıklayınca kapan
    document.addEventListener('click', function (e) {
        var wrap = document.getElementById('eduTopicWrap');
        if (wrap && !wrap.contains(e.target)) topicPanelOpen(false);
    });

    subjSel.addEventListener('change', function () {
        if (!subjSel.value) { resetTopicSel('Önce ders seçin...'); return; }
        var subjName = subjSel.options[subjSel.selectedIndex].text;
        topicBtn.disabled = true;
        topicLabel.textContent = 'Yükleniyor...';
        topicLabel.className = 'truncate text-slate-400';
        fetch(API + '?action=topics&subject_id=' + subjSel.value + '&per_page=500', {credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if (!j.ok) { resetTopicSel('Yüklenemedi'); return; }
            if (!j.data.length) { resetTopicSel('Bu derste konu yok'); return; }
            topicPanel.innerHTML = j.data.map(function(t){
                return '<button type="button" data-topic-id="'+t.id+'" data-subject="'+esc(subjName)+'" data-topic="'+esc(t.topic_name)+'" ' +
                    'class="w-full flex items-center justify-between text-left px-3 py-2 text-sm text-slate-700 hover:bg-indigo-50 transition">' +
                    '<span class="truncate">'+esc(t.topic_name)+'</span>' + statBadge(t.id) + '</button>';
            }).join('');
            topicBtn.disabled = false;
            topicLabel.textContent = 'Konu seçiniz...';
            topicLabel.className = 'truncate text-slate-500';
        }).catch(function(){ resetTopicSel('Yüklenemedi'); });
    });

    // ── KAYNAKTAN ──
    function loadResources() {
        resLoaded = true;
        fetch(API + '?action=resources&per_page=200', {credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if (!j.ok || !j.data.length) { resSel.innerHTML = '<option value="" disabled selected>Henüz kaynak yok</option>'; return; }
            var tIcon = {kitap:'📕', deneme:'📝', pdf:'📄', video:'🎬', diger:'📦'};
            resSel.innerHTML = '<option value="" disabled selected>Kaynak seçiniz...</option>' + j.data.map(function(res){
                var ic = tIcon[res.type] || '📦';
                var vid = res.type === 'video' ? ' [VİDEO]' : '';
                return '<option value="'+res.id+'" data-title="'+esc(res.title)+'" data-type="'+esc(res.type||'')+'">'+ic+' '+esc(res.title)+vid+' ('+(res.topic_count||0)+' konu)</option>';
            }).join('');
        }).catch(function(){ resSel.innerHTML = '<option value="" disabled selected>Yüklenemedi</option>'; });
    }
    resSel.addEventListener('change', function () {
        if (!resSel.value) { resTopicSel.innerHTML = '<option value="" disabled selected>Önce kaynak seçin...</option>'; resTopicSel.disabled = true; return; }
        resTopicSel.innerHTML = '<option value="" disabled selected>Yükleniyor...</option>'; resTopicSel.disabled = true;
        fetch(API + '?action=resource_topics&resource_id=' + resSel.value, {credentials:'same-origin'}).then(r=>r.json()).then(function(j){
            if (!j.ok || !j.data.length) { resTopicSel.innerHTML = '<option value="" disabled selected>Bu kaynağa konu bağlı değil</option>'; return; }
            resTopicSel.innerHTML = '<option value="" disabled selected>Konu seçiniz...</option>' + j.data.map(function(t){
                return '<option value="'+t.id+'" data-subject="'+esc(t.lesson_name)+'" data-topic="'+esc(t.topic_name)+'">'+esc(t.category_name+' › '+t.lesson_name+' › '+t.topic_name)+'</option>';
            }).join('');
            resTopicSel.disabled = false;
        }).catch(function(){ resTopicSel.innerHTML = '<option value="" disabled selected>Yüklenemedi</option>'; });
    });
    resTopicSel.addEventListener('change', function () {
        var o = resTopicSel.options[resTopicSel.selectedIndex];
        if (!o || !o.value) { setChosen('', '', ''); return; }
        var resOpt = resSel.options[resSel.selectedIndex];
        var resTitle = resOpt ? (resOpt.getAttribute('data-title') || '') : '';
        var resType  = resOpt ? (resOpt.getAttribute('data-type')  || '') : '';
        setChosen(o.value, o.getAttribute('data-subject'), o.getAttribute('data-topic'), resTitle, resSel.value, resType);
    });

    // ── MANUEL (serbest metin → edu_topic_id boş) ──
    // Türkçe büyük/küçük harf eşleşme sorununu önlemek için (İ/I, ı/I vb.)
    // yazılan her şey Türkçe kurallarına göre büyük harfe çevrilir.
    function turkishUpper(el) {
        var start = el.selectionStart, end = el.selectionEnd;
        var upper = el.value.toLocaleUpperCase('tr-TR');
        if (upper !== el.value) {
            el.value = upper;
            try { el.setSelectionRange(start, end); } catch (e) {}
        }
    }
    var manError = document.getElementById('eduManualError');
    function manualSync(){
        turkishUpper(manSubj);
        turkishUpper(manTopic);
        if (manError) manError.classList.add('hidden');
        setChosen('', manSubj.value.trim(), manTopic.value.trim());
    }
    manSubj.addEventListener('input', manualSync);
    manTopic.addEventListener('input', manualSync);

    // ── İsimsiz görev kaydını engelle ──
    // Ne müfredat/kaynak bağlantısı ne de manuel ders/konu adı varsa kaydetme.
    var scheduleFormEl = document.getElementById('scheduleFormV3');
    if (scheduleFormEl) {
        scheduleFormEl.addEventListener('submit', function (e) {
            var hasEdu = !!eduIdEl.value;
            var hasManual = (csEl.value || '').trim() !== '' || (ctEl.value || '').trim() !== '';
            if (!hasEdu && !hasManual) {
                e.preventDefault();
                var mtab = document.querySelector('.edu-tab[data-etab="manuel"]');
                if (mtab) mtab.click();
                if (manError) manError.classList.remove('hidden');
                if (manSubj) manSubj.focus();
            }
        });
    }

    // ── scripts.php'nin çağıracağı global yardımcılar ──
    window.eduTaskReset = function () {
        setChosen('', '', '');
        setManualLock(false);
        if (manError) manError.classList.add('hidden');
        if (catSel) catSel.value = '';
        if (subjSel) { subjSel.innerHTML = '<option value="" disabled selected>Ders...</option>'; subjSel.disabled = true; }
        resetTopicSel('Önce kategori ve ders seçin...');
        if (resSel) resSel.value = '';
        if (resTopicSel) { resTopicSel.innerHTML = '<option value="" disabled selected>Önce kaynak seçin...</option>'; resTopicSel.disabled = true; }
        if (manSubj) manSubj.value = '';
        if (manTopic) manTopic.value = '';
        var tnEl = document.getElementById('taskNoteV3');
        if (tnEl) tnEl.value = '';
        videoMode(false);
        // Varsayılan sekme: Müfredattan
        var first = document.querySelector('.edu-tab[data-etab="mufredat"]');
        if (first) first.click();
    };
    // Düzenlemede: eski/yeni görev verisini seçiciye önyükle (Manuel sekmesinde göster)
    window.eduTaskPrefill = function (item) {
        window.eduTaskReset();
        var eduId = item.edu_topic_id || '';
        var subj, top;
        if (eduId) {
            // Müfredat/kaynak bağlı — okunur adları önce edu alanlarından al (otorite kaynağı)
            subj = item.edu_subject_name || item.custom_subject || item.subject_name || '';
            top  = item.edu_topic_name   || item.custom_topic   || item.topic_name   || '';
        } else {
            subj = item.custom_subject || item.subject_name || '';
            top  = item.custom_topic  || item.topic_name   || '';
        }
        setChosen(eduId, subj, top, item.resource_title || '', item.resource_id || '', item.resource_type || '');
        if (manSubj)  manSubj.value  = subj;
        if (manTopic) manTopic.value = top;
        var tnEl = document.getElementById('taskNoteV3');
        if (tnEl) tnEl.value = item.task_note || '';
        // Kayıt video göreviyse (resource_type gelmese bile) video modunu koru
        if (item.action_type === 'video') videoMode(true);
        setManualLock(!!eduId); // müfredat/kaynak bağlıysa metin alanları kilitli gösterilir
        var mtab = document.querySelector('.edu-tab[data-etab="manuel"]');
        if (mtab) mtab.click();
    };

    if (!catsLoaded) loadCats(); // ilk açılış için hazırla
})();
</script>
