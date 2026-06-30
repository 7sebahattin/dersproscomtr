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
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Ad</label><input type="text" name="first_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Soyad</label><input type="text" name="last_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
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
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Ad</label><input type="text" name="first_name" value="<?php echo $selected_student['first_name']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Soyad</label><input type="text" name="last_name" value="<?php echo $selected_student['last_name']; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-sm outline-none"></div>
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