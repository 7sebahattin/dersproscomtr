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

                <div class="flex bg-slate-100 p-1.5 rounded-xl mb-5 border border-slate-200">
                    <button type="button" onclick="switchModeV3('system')" id="btn-systemV3" class="flex-1 py-2.5 text-xs font-black rounded-lg bg-white text-[#223488] shadow-sm transition-all duration-200 border border-slate-100">SİSTEMDEN SEÇ</button>
                    <button type="button" onclick="switchModeV3('manual')" id="btn-manualV3" class="flex-1 py-2.5 text-xs font-bold rounded-lg text-slate-500 hover:bg-white/60 hover:text-[#223488] transition-all duration-200">MANUEL YAZ</button>
                </div>

                <div id="select-modeV3" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Alan</label>
                            <select id="fieldSelect" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all" onchange="updateSubjectList()" required>
                                <option value="">Seçiniz...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Ders</label>
                            <select id="subjectSelectV3" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all" onchange="loadTopicsV3()" required>
                                <option value="">Ders seçiniz...</option>
                            </select>
                        </div>
                    </div>

                    <div class="relative group">
                        <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Konu</label>
                        
                        <div class="flex items-center justify-end mb-1 gap-2 absolute right-4 top-0 pointer-events-none">
                             <span class="text-[9px] font-bold text-[#223488] flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#223488]"></span>Soru</span>
                             <span class="text-[9px] font-bold text-[#ec9731] flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-[#ec9731]"></span>Konu</span>
                        </div>

                        <input type="hidden" name="topic" id="topicSelectV3">

                        <div id="customTopicTrigger" onclick="toggleCustomTopicDropdown()" 
                             class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-500 cursor-pointer flex justify-between items-center hover:border-[#223488] hover:bg-white transition-all select-none h-12">
                            <span id="customTopicText" class="truncate">Konu seçiniz...</span>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>

                        <div id="customTopicList" class="hidden absolute z-[100] w-full bg-white border border-slate-200 rounded-xl shadow-xl mt-1 max-h-60 overflow-y-auto custom-scrollbar animate-fadeIn">
                            <div class="p-3 text-xs text-slate-400 text-center">Önce ders seçiniz...</div>
                        </div>
                    </div>
                </div>

                <div id="manual-modeV3" class="hidden space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-1.5 ml-1">Ders Adı</label>
                        <input name="custom_subject" id="customSubjectV3" placeholder="Örn: Geometri" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Konu / Not</label>
                        <input name="custom_topic" id="customTopicV3" placeholder="Örn: Üçgenler tekrar" class="w-full bg-slate-50 border border-slate-200 rounded-xl text-sm p-3 font-medium text-slate-700 focus:bg-white focus:border-[#223488] focus:ring-1 focus:ring-[#223488] outline-none transition-all">
                    </div>
                </div>

                <div class="mt-5">
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

                <div class="mt-4">
                    <div id="quickButtonsContainer" class="flex gap-2 overflow-x-auto pb-2 custom-scrollbar no-scrollbar touch-pan-x snap-x">
                        </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-2">
                    <div>
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

                <div class="mt-5">
                    <label class="block text-[10px] font-bold text-[#223488]/70 uppercase mb-2 ml-1">Durum</label>
                    <select name="status" id="statusInputV3" onchange="updateSelectColor(this)" class="w-full h-12 px-3 rounded-xl text-sm font-bold border-2 border-slate-200 focus:border-[#223488] focus:ring-0 transition cursor-pointer bg-white text-slate-600 outline-none">
                        <option value="bekliyor">⏳ Bekliyor</option>
                        <option value="yapildi">✅ Yapıldı</option>
                        <option value="yarim">⚠️ Yarım Yapıldı</option>
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

<div id="statusModal" class="fixed inset-0 z-[9999] flex items-end sm:items-center justify-center bg-slate-900/70 backdrop-blur-sm p-0 sm:p-4 hidden">
    <div class="bg-white rounded-t-[2rem] sm:rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden relative animate-fadeIn max-h-[92vh] flex flex-col">

        <!-- Başlık: ders + konu doğrudan burada (mobil sheet başlığı) -->
        <div class="relative bg-gradient-to-r from-[#223488] to-[#314595] px-5 pb-4 pt-3 text-white flex-shrink-0">
            <div class="absolute left-0 top-0 h-full w-1.5 bg-[#ec9731]"></div>
            <div class="sm:hidden w-10 h-1 rounded-full bg-white/30 mx-auto mb-3"></div>
            <div class="flex justify-between items-start gap-3 pl-2">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Görev Girişi</p>
                    <p id="statusSubjectName" class="font-black text-xl leading-tight truncate">-</p>
                    <p id="statusTopicName" class="text-white/80 text-xs font-semibold leading-tight mt-0.5 truncate">-</p>
                </div>
                <button type="button" onclick="closeStatusModal()" aria-label="Kapat" class="bg-white/15 hover:bg-white/25 rounded-full w-9 h-9 flex items-center justify-center transition flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <form method="POST" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="schedule_id" id="statusSchedId">

            <div class="overflow-y-auto custom-scrollbar p-5 space-y-4 flex-1">

                <!-- İlerleme: yapılan / hedef canlı çubuk -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-end justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">İlerleme</span>
                        <span class="text-sm font-black text-[#223488]"><span id="progressNow">0</span><span class="text-slate-400"> / </span><span id="displayTarget">0</span> <span id="targetUnit" class="text-[10px] text-slate-400 font-bold">Soru</span></span>
                    </div>
                    <div class="h-3 w-full bg-slate-200 rounded-full overflow-hidden">
                        <div id="statusProgressBar" class="h-full rounded-full transition-all duration-500 ease-out" style="width:0%;background:linear-gradient(90deg,#223488,#ec9731)"></div>
                    </div>
                    <p id="statusProgressPct" class="text-right text-[10px] font-black text-slate-400 mt-1.5">%0</p>
                </div>

                <!-- Doğru / Yanlış: büyük dokunmalı sayaçlar -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-green-50 border-2 border-green-200 rounded-2xl p-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-green-600 text-center">✓ Doğru</p>
                        <div class="flex items-center gap-1.5 mt-2">
                            <button type="button" onclick="stepStatusVal('statusCorrect',-1)" aria-label="Doğru azalt"
                                    class="w-10 h-11 flex-shrink-0 rounded-xl bg-white border border-green-200 text-green-600 font-black text-xl leading-none active:scale-90 transition">−</button>
                            <input type="number" name="correct_count" id="statusCorrect" min="0" placeholder="0" inputmode="numeric"
                                   class="w-full min-w-0 bg-transparent text-center font-black text-2xl text-green-700 outline-none"
                                   oninput="calcYapilan();updateStatusProgress()">
                            <button type="button" onclick="stepStatusVal('statusCorrect',1)" aria-label="Doğru artır"
                                    class="w-10 h-11 flex-shrink-0 rounded-xl bg-green-500 text-white font-black text-xl leading-none shadow-sm active:scale-90 transition">+</button>
                        </div>
                    </div>
                    <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-red-500 text-center">✕ Yanlış</p>
                        <div class="flex items-center gap-1.5 mt-2">
                            <button type="button" onclick="stepStatusVal('statusWrong',-1)" aria-label="Yanlış azalt"
                                    class="w-10 h-11 flex-shrink-0 rounded-xl bg-white border border-red-200 text-red-500 font-black text-xl leading-none active:scale-90 transition">−</button>
                            <input type="number" name="wrong_count" id="statusWrong" min="0" placeholder="0" inputmode="numeric"
                                   class="w-full min-w-0 bg-transparent text-center font-black text-2xl text-red-600 outline-none"
                                   oninput="calcYapilan();updateStatusProgress()">
                            <button type="button" onclick="stepStatusVal('statusWrong',1)" aria-label="Yanlış artır"
                                    class="w-10 h-11 flex-shrink-0 rounded-xl bg-red-500 text-white font-black text-xl leading-none shadow-sm active:scale-90 transition">+</button>
                        </div>
                    </div>
                </div>

                <!-- Yapılan: büyük merkezi sayaç -->
                <div class="bg-[#eef1fb] border-2 border-[#223488]/20 rounded-2xl p-3">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-[10px] font-black uppercase tracking-wider text-[#223488]">Yapılan</span>
                        <span id="statusDyHint" class="text-[9px] font-bold text-slate-400">Doğru + yanlış girersen otomatik dolar</span>
                    </div>
                    <div class="flex items-center gap-2 mt-2">
                        <button type="button" onclick="stepStatusVal('statusAmount',-1)" aria-label="Yapılan azalt"
                                class="w-12 h-12 flex-shrink-0 rounded-xl bg-white border border-[#223488]/20 text-[#223488] font-black text-2xl leading-none active:scale-90 transition">−</button>
                        <input type="number" name="amount" id="statusAmount" min="0" inputmode="numeric"
                               class="w-full min-w-0 bg-transparent text-center font-black text-3xl text-[#223488] outline-none"
                               oninput="clearDogrusYanlis();updateStatusProgress()">
                        <button type="button" onclick="stepStatusVal('statusAmount',1)" aria-label="Yapılan artır"
                                class="w-12 h-12 flex-shrink-0 rounded-xl bg-[#223488] text-white font-black text-2xl leading-none shadow-md shadow-[#223488]/30 active:scale-90 transition">+</button>
                    </div>
                </div>

                <!-- Durum: dokunmalı çipler -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 mb-2 uppercase tracking-wider">Durum</label>
                    <select name="status" id="statusSelect" class="hidden">
                        <option value="bekliyor">⏳ Bekliyor</option>
                        <option value="yapildi">✅ Yapıldı</option>
                        <option value="yarim">⚠️ Yarım Kaldı</option>
                        <option value="yapilmadi">❌ Yapılmadı</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2" id="statusChips">
                        <button type="button" data-st="bekliyor" onclick="setStatusChip('bekliyor',true)">⏳ Bekliyor</button>
                        <button type="button" data-st="yapildi" onclick="setStatusChip('yapildi',true)">✅ Yapıldı</button>
                        <button type="button" data-st="yarim" onclick="setStatusChip('yarim',true)">⚠️ Yarım</button>
                        <button type="button" data-st="yapilmadi" onclick="setStatusChip('yapilmadi',true)">❌ Yapılmadı</button>
                    </div>
                </div>
            </div>

            <!-- Sabit alt eylem çubuğu (uygulama hissi) -->
            <div class="p-4 pt-3 border-t border-slate-100 bg-white flex-shrink-0" style="padding-bottom:max(1rem,env(safe-area-inset-bottom))">
                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3.5 rounded-2xl font-black text-sm shadow-lg shadow-green-200 transition active:scale-[0.98]">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Görev Girişi modalı: mobil sayaç + ilerleme + durum çipleri ── */
let _statusManual = false; // kullanıcı çipe elle dokunduysa otomatik öneri devreye girmez

function stepStatusVal(id, d){
    const el = document.getElementById(id);
    let v = (parseInt(el.value) || 0) + d;
    if (v < 0) v = 0;
    el.value = v;
    if (id === 'statusAmount') clearDogrusYanlis(); else calcYapilan();
    updateStatusProgress();
    if (navigator.vibrate) navigator.vibrate(8);
}

function updateStatusProgress(){
    const t = parseInt(document.getElementById('displayTarget').innerText) || 0;
    const a = parseInt(document.getElementById('statusAmount').value) || 0;
    const pct = t > 0 ? Math.min(100, Math.round(a / t * 100)) : (a > 0 ? 100 : 0);
    document.getElementById('progressNow').innerText = a;
    document.getElementById('statusProgressBar').style.width = pct + '%';
    document.getElementById('statusProgressPct').innerText = '%' + pct;
    // Otomatik durum önerisi: hedefe ulaşıldıysa Yapıldı, kısmen yapıldıysa Yarım
    if (!_statusManual) {
        let st = document.getElementById('statusSelect').value;
        if (a > 0 && t > 0 && a >= t) st = 'yapildi';
        else if (a > 0 && t > 0 && a < t) st = 'yarim';
        setStatusChip(st, false);
    }
}

function setStatusChip(st, manual){
    if (manual) { _statusManual = true; if (navigator.vibrate) navigator.vibrate(8); }
    document.getElementById('statusSelect').value = st;
    const activeCls = {
        bekliyor:  'bg-slate-700 border-slate-700 text-white shadow-md',
        yapildi:   'bg-green-500 border-green-500 text-white shadow-md shadow-green-200',
        yarim:     'bg-amber-400 border-amber-400 text-white shadow-md shadow-amber-200',
        yapilmadi: 'bg-red-500 border-red-500 text-white shadow-md shadow-red-200'
    };
    document.querySelectorAll('#statusChips [data-st]').forEach(b => {
        b.className = 'h-12 rounded-xl border-2 text-xs font-black transition active:scale-95 ' +
            (b.dataset.st === st ? activeCls[b.dataset.st] : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300');
    });
}

/* openStatusModal (scripts.php) değerleri doldurduktan sonra arayüzü senkronlar */
function syncStatusModalUI(){
    _statusManual = false;
    setStatusChip(document.getElementById('statusSelect').value || 'bekliyor', false);
    _statusManual = false; // setStatusChip manual=false zaten; garanti olsun
    const t = parseInt(document.getElementById('displayTarget').innerText) || 0;
    const a = parseInt(document.getElementById('statusAmount').value) || 0;
    const pct = t > 0 ? Math.min(100, Math.round(a / t * 100)) : (a > 0 ? 100 : 0);
    document.getElementById('progressNow').innerText = a;
    document.getElementById('statusProgressBar').style.width = pct + '%';
    document.getElementById('statusProgressPct').innerText = '%' + pct;
}

function closeStatusModal(){
    document.getElementById('statusModal').classList.add('hidden');
}
document.getElementById('statusModal').addEventListener('click', function(e){
    if (e.target === this) closeStatusModal();
});
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        const m = document.getElementById('statusModal');
        if (m && !m.classList.contains('hidden')) closeStatusModal();
    }
});
</script>
<!-- ═══ VİDEO GÖREV MODALI: üstte izlenebilir YouTube, altta kaynak linki, "İzledim" ═══ -->
<div id="videoTaskModal" class="fixed inset-0 z-[9999] items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 hidden">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden relative animate-fadeIn max-h-[92vh] flex flex-col">
        <div class="bg-red-600 p-4 flex justify-between items-center text-white flex-shrink-0">
            <h3 class="font-black text-sm tracking-wide flex items-center gap-2">🎬 Video Görevi</h3>
            <button type="button" onclick="closeVideoTaskModal()" aria-label="Kapat" class="bg-white/20 hover:bg-white/30 rounded-full w-8 h-8 flex items-center justify-center transition">✕</button>
        </div>
        <div class="p-5 space-y-4 overflow-y-auto">
            <div class="border-b border-slate-100 pb-3">
                <p id="vtSubject" class="font-black text-[#223488] text-lg leading-tight">-</p>
                <p id="vtTopic" class="font-medium text-slate-600 text-sm leading-tight">-</p>
                <p id="vtResource" class="text-[11px] font-bold text-red-600 mt-1 hidden"></p>
            </div>

            <!-- YouTube gömülü oynatıcı (YouTube linkiyse) -->
            <div id="vtEmbedWrap" class="hidden rounded-2xl overflow-hidden border border-slate-200 bg-black" style="aspect-ratio:16/9">
                <iframe id="vtEmbed" class="w-full h-full" src="" title="Video" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        allowfullscreen></iframe>
            </div>

            <!-- Görev notu -->
            <div id="vtNote" class="hidden text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5">📝 <span></span></div>

            <!-- Kaynağa giden link -->
            <a id="vtLink" href="#" target="_blank" rel="noopener"
               class="hidden w-full flex items-center justify-center gap-2 bg-white border-2 border-slate-200 hover:border-[#223488] text-[#223488] py-3 rounded-xl font-bold text-sm transition">
                🔗 Videoyu Kaynağında Aç
            </a>
            <p id="vtNoUrl" class="hidden text-center text-xs text-slate-400 font-semibold py-2">Bu kaynak için bağlantı eklenmemiş — koçuna sorabilirsin.</p>

            <!-- İzledim / İzlendi (geri alınabilir — yanlış tıklamalar düzeltilebilsin) -->
            <form method="POST" id="vtForm">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="schedule_id" id="vtSchedId">
                <input type="hidden" name="status" value="yapildi">
                <input type="hidden" name="amount" value="1">
                <button type="submit" id="vtWatchBtn"
                        class="w-full bg-green-500 hover:bg-green-600 text-white py-3.5 rounded-xl font-black text-sm shadow-lg shadow-green-200 transition active:scale-[0.98]">
                    ✅ İzledim
                </button>
            </form>
            <div id="vtWatched" class="hidden space-y-2">
                <div class="w-full text-center bg-green-50 border border-green-200 text-green-700 py-3 rounded-xl font-black text-sm">
                    ✓ Bu video izlendi olarak işaretlendi
                </div>
                <form method="POST" id="vtUndoForm">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="schedule_id" id="vtUndoSchedId">
                    <input type="hidden" name="status" value="bekliyor">
                    <input type="hidden" name="amount" value="0">
                    <button type="submit"
                            class="w-full bg-white border-2 border-slate-200 hover:border-red-300 hover:text-red-600 text-slate-500 py-2.5 rounded-xl font-bold text-xs transition">
                        ↩︎ İzlemeyi Geri Al
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// YouTube linkinden video ID çıkar (watch / youtu.be / embed / shorts / live)
function _ytId(u){
    if(!u) return null;
    var m = String(u).match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{6,})/);
    return m ? m[1] : null;
}
function openVideoTaskModal(item){
    var subj = item.edu_subject_name || item.custom_subject || item.subject_name || '-';
    var top  = item.edu_topic_name  || item.custom_topic  || item.topic_name  || '-';
    document.getElementById('vtSubject').textContent = subj;
    document.getElementById('vtTopic').textContent = top;

    var resEl = document.getElementById('vtResource');
    if(item.resource_title){ resEl.textContent = '🎬 ' + item.resource_title; resEl.classList.remove('hidden'); }
    else resEl.classList.add('hidden');

    var noteEl = document.getElementById('vtNote');
    if(item.task_note){ noteEl.querySelector('span').textContent = item.task_note; noteEl.classList.remove('hidden'); }
    else noteEl.classList.add('hidden');

    var url = item.resource_url || '';
    var vid = _ytId(url);
    var wrap = document.getElementById('vtEmbedWrap'), ifr = document.getElementById('vtEmbed');
    var link = document.getElementById('vtLink'), noUrl = document.getElementById('vtNoUrl');
    if(vid){
        ifr.src = 'https://www.youtube-nocookie.com/embed/' + vid;
        wrap.classList.remove('hidden');
    } else { ifr.src = ''; wrap.classList.add('hidden'); }
    if(url){ link.href = url; link.classList.remove('hidden'); noUrl.classList.add('hidden'); }
    else { link.classList.add('hidden'); noUrl.classList.remove('hidden'); }

    document.getElementById('vtSchedId').value = item.id;
    document.getElementById('vtUndoSchedId').value = item.id;
    var done = (item.status === 'yapildi');
    document.getElementById('vtForm').classList.toggle('hidden', done);
    document.getElementById('vtWatched').classList.toggle('hidden', !done);

    var m = document.getElementById('videoTaskModal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function closeVideoTaskModal(){
    var m = document.getElementById('videoTaskModal');
    m.classList.add('hidden'); m.classList.remove('flex');
    document.getElementById('vtEmbed').src = ''; // videoyu durdur
}
document.getElementById('videoTaskModal').addEventListener('click', function(e){
    if(e.target === this) closeVideoTaskModal();
});
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
        var m = document.getElementById('videoTaskModal');
        if(m && !m.classList.contains('hidden')) closeVideoTaskModal();
    }
});
</script>
