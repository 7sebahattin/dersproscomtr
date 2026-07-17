<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .form-input-visible { background-color: #ffffff; border: 1px solid #94a3b8; color: #0f172a; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .form-input-visible:focus { border-color: #4f46e5; ring: 2px solid #4f46e5; outline: none; }
    select.form-input-visible { appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
    /* Scrollbar Gizleme (Mobil için) */
    .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
    const allSubjects = <?php echo json_encode($all_subjects ?? []); ?>;
    const studentLevel = "<?php echo isset($selected_student['school_level']) ? $selected_student['school_level'] : 'Lise'; ?>";

    // --- MODAL AÇMA YARDIMCISI (SIDEBAR İÇİN ÖNEMLİ) ---
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
        } else {
            console.error(`Hata: '${modalId}' id'li modal bulunamadı. Lütfen modals.php dosyasının sayfaya dahil edildiğinden emin olun.`);
            alert("İşlem penceresi açılamadı. Sayfayı yenileyip tekrar deneyin.");
        }
    }

    // --- ÖZEL DROPDOWN İŞLEVLERİ ---
    document.addEventListener('click', function(e) {
        const trigger = document.getElementById('customTopicTrigger');
        const list = document.getElementById('customTopicList');
        if (trigger && list && !trigger.contains(e.target) && !list.contains(e.target)) {
            list.classList.add('hidden');
        }
    });

    function toggleCustomTopicDropdown() {
        const list = document.getElementById('customTopicList');
        if(list) list.classList.toggle('hidden');
    }

    function selectTopic(id, name) {
        const hiddenInput = document.getElementById('topicSelectV3');
        if(hiddenInput) hiddenInput.value = id;
        
        const triggerText = document.getElementById('customTopicText');
        if(triggerText) {
            triggerText.innerText = name;
            triggerText.classList.remove('text-slate-500');
            triggerText.classList.add('text-slate-800', 'font-bold');
        }
        
        const list = document.getElementById('customTopicList');
        if(list) list.classList.add('hidden');
    }

    // --- SAYFA YÜKLENDİĞİNDE ---
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Kategorileri Filtrele
        const categories = [...new Set(allSubjects.map(s => s.category || 'Genel'))];
        const fieldSelect = document.getElementById('fieldSelect');
        if(fieldSelect) {
            categories.forEach(cat => {
                let allow = false;
                if(studentLevel === 'Lise' && (cat === 'TYT' || cat === 'AYT')) allow = true;
                if(studentLevel === 'Ortaokul' && cat === 'LGS') allow = true;
                if(cat === 'Genel') allow = true;
                if(allow) {
                    const opt = document.createElement('option');
                    opt.value = cat;
                    opt.innerText = cat;
                    fieldSelect.appendChild(opt);
                }
            });
        }

        // 2. Sürükle Bırak (Sortable)
        const isDesktopPointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        let ctrlDown = false;

        if (isDesktopPointer) {
            window.addEventListener('keydown', (e) => { if (e.key === 'Control') ctrlDown = true; });
            window.addEventListener('keyup', (e) => { if (e.key === 'Control') ctrlDown = false; });
            window.addEventListener('blur', () => { ctrlDown = false; });
        }

        const postScheduleAjax = async (payload) => {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams(payload)
            });
            const text = await res.text();
            try { return JSON.parse(text); } 
            catch (e1) { return null; }
        };

        document.querySelectorAll('.sortable-list').forEach(listEl => {
            new Sortable(listEl, {
                group: {
                    name: 'shared',
                    pull: (to, from, dragEl, evt) => {
                        if (!isDesktopPointer) return true; 
                        const ctrl = ctrlDown || (evt && evt.ctrlKey);
                        return ctrl ? 'clone' : true;
                    },
                    put: true
                },
                animation: 150,
                onAdd: async (evt) => {
                    const toDate = evt.to && evt.to.getAttribute('data-date');
                    if (!toDate) return;
                    const itemEl = evt.item;
                    const originalId = itemEl.getAttribute('data-id') || itemEl.dataset.id;
                    if (!originalId) return;

                    const isClone = (evt.pullMode === 'clone') || (isDesktopPointer && (ctrlDown || (evt.originalEvent && evt.originalEvent.ctrlKey)));

                    try {
                        if (isClone) {
                            itemEl.setAttribute('data-id', 'tmp_' + Date.now());
                            const out = await postScheduleAjax({ ajax: 'copy_schedule', schedule_id: originalId, to_date: toDate });
                            if (!out || !out.new_id) { window.location.reload(); return; }
                            itemEl.setAttribute('data-id', String(out.new_id));
                            itemEl.dataset.item = JSON.stringify({...JSON.parse(itemEl.dataset.item), id: out.new_id, date: toDate});
                        } else {
                            const out = await postScheduleAjax({ ajax: 'move_schedule', schedule_id: originalId, to_date: toDate });
                            itemEl.dataset.item = JSON.stringify({...JSON.parse(itemEl.dataset.item), date: toDate});
                        }
                    } catch (err) { console.warn('Sürükle-bırak uyarısı:', err); }
                }
            });
        });

        // 3. Başlangıç Ayarları
        const defaultType = (studentLevel === 'Ortaokul') ? 'LGS' : 'TYT';
        if(document.getElementById('examChart')) initChart(defaultType);
        if(document.getElementById('examTypeSelector')) renderExamRows('examRowsArea');
        if(typeof filterAnalysis === 'function') filterAnalysis(defaultType);

        // Mod Değişimi Butonları
        const btnSystem = document.getElementById('btn-systemV3');
        const btnManual = document.getElementById('btn-manualV3');
        const systemInputs = [document.getElementById('fieldSelect'), document.getElementById('subjectSelectV3'), document.getElementById('topicSelectV3')];
        const manualInputs = [document.getElementById('customSubjectV3'), document.getElementById('customTopicV3')];

        if(btnSystem && btnManual) {
            btnSystem.addEventListener('click', function() {
                btnSystem.classList.add('bg-white', 'text-[#223488]', 'shadow-sm');
                btnSystem.classList.remove('text-slate-500', 'hover:bg-white/60');
                btnManual.classList.add('text-slate-500', 'hover:bg-white/60');
                btnManual.classList.remove('bg-white', 'text-[#223488]', 'shadow-sm');
                systemInputs.forEach(el => el.setAttribute('required', ''));
                manualInputs.forEach(el => el.removeAttribute('required'));
            });

            btnManual.addEventListener('click', function() {
                btnManual.classList.add('bg-white', 'text-[#223488]', 'shadow-sm');
                btnManual.classList.remove('text-slate-500', 'hover:bg-white/60');
                btnSystem.classList.add('text-slate-500', 'hover:bg-white/60');
                btnSystem.classList.remove('bg-white', 'text-[#223488]', 'shadow-sm');
                systemInputs.forEach(el => el.removeAttribute('required'));
                manualInputs.forEach(el => el.setAttribute('required', ''));
            });
        }

        // Adet / Dakika Değişimi ve Buton Güncelleme
        const unitLabel = document.getElementById('amountUnitLabel');
        const actionRadios = document.querySelectorAll('input[name="action_type"]');
        if(unitLabel) {
            actionRadios.forEach(radio => {
                radio.addEventListener('change', function(e) {
                    const type = e.target.value;
                    unitLabel.textContent = (type === 'konu') ? 'Dakika' : 'Adet';
                    updateQuickButtons(type); // Butonları güncelle
                });
            });
        }
        
        // İlk açılışta butonları yükle (Varsayılan: Soru)
        updateQuickButtons('soru');
    });

    // --- HIZLI MİKTAR BUTONLARI FONKSİYONU ---
    function updateQuickButtons(type) {
        const container = document.getElementById('quickButtonsContainer');
        if (!container) return;

        container.innerHTML = ''; // Temizle

        let values = [];
        let btnColorClass = '';

        if (type === 'konu') {
            // Konu için değerler (Turuncu Tema)
            values = [20, 30, 40, 50, 60, 75, 90];
            btnColorClass = 'bg-[#ec9731] hover:bg-[#d68625] text-white border-[#ec9731] shadow-orange-100';
        } else {
            // Soru için değerler (Mavi Tema)
            values = [10, 15, 20, 25, 30, 50, 75];
            btnColorClass = 'bg-[#223488] hover:bg-[#314595] text-white border-[#223488] shadow-indigo-100';
        }

        values.forEach(val => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `flex-shrink-0 w-10 h-10 rounded-full font-bold text-xs shadow-md border-2 transition-transform active:scale-90 flex items-center justify-center snap-center ${btnColorClass}`;
            btn.innerText = val;
            btn.onclick = function() {
                const input = document.getElementById('amountInputV3');
                if (input) {
                    input.value = val;
                    // Küçük bir animasyon efekti
                    input.classList.add('ring-2', 'ring-offset-1', 'ring-opacity-50');
                    setTimeout(() => input.classList.remove('ring-2', 'ring-offset-1', 'ring-opacity-50'), 200);
                }
            };
            container.appendChild(btn);
        });
    }

    // --- ÖZEL KONU YÜKLEME FONKSİYONU ---
    function loadTopicsV3() {
        const ajaxPath = '../ajax/'; 
        
        const sid = document.getElementById('subjectSelectV3').value;
        const listContainer = document.getElementById('customTopicList');
        const triggerText = document.getElementById('customTopicText');
        const hiddenInput = document.getElementById('topicSelectV3');

        triggerText.innerText = "Yükleniyor...";
        hiddenInput.value = "";
        listContainer.innerHTML = '<div class="p-4 text-center"><div class="animate-spin rounded-full h-5 w-5 border-b-2 border-[#223488] mx-auto"></div></div>';

        const urlParams = new URLSearchParams(window.location.search);
        const studentIdParam = urlParams.get('student_id') || <?php echo $sid ?? 0; ?>;

        if(sid) {
            const fetchUrl = `${ajaxPath}get_topics.php?subject_id=${sid}&student_id=${studentIdParam}`;
            
            fetch(fetchUrl)
                .then(r => {
                    if (!r.ok) throw new Error("Dosya bulunamadı: " + fetchUrl);
                    return r.json();
                })
                .then(data => {
                    listContainer.innerHTML = '';
                    
                    if (!data || data.length === 0) {
                        listContainer.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Bu derse ait konu bulunamadı.</div>';
                        triggerText.innerText = "Konu bulunamadı";
                        return;
                    }

                    triggerText.innerText = "Konu Seçiniz...";

                    data.forEach(t => {
                        const sCount = parseInt(t.total_soru) || 0;
                        const kCount = parseInt(t.total_konu) || 0;
                        
                        let statsHTML = '';
                        if (sCount > 0 || kCount > 0) {
                            statsHTML = `
                                <div class="flex items-center gap-2 font-mono text-[10px]">
                                    ${sCount > 0 ? `<span class="font-bold text-[#223488] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100">${sCount} S</span>` : ''}
                                    ${kCount > 0 ? `<span class="font-bold text-[#ec9731] bg-orange-50 px-1.5 py-0.5 rounded border border-orange-100">${kCount} K</span>` : ''}
                                </div>
                            `;
                        } else {
                            statsHTML = `<span class="text-[9px] text-slate-300">-</span>`;
                        }

                        const div = document.createElement('div');
                        div.className = "flex justify-between items-center p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors group";
                        div.onclick = function() { selectTopic(t.id, t.name); };
                        div.innerHTML = `<span class="text-sm font-semibold text-slate-700 group-hover:text-[#223488]">${t.name}</span>${statsHTML}`;
                        listContainer.appendChild(div);
                    });
                })
                .catch(err => {
                    console.error("Hata Detayı:", err);
                    triggerText.innerText = "Hata";
                    listContainer.innerHTML = `<div class="p-3 text-xs text-red-500 text-center">Bağlantı Hatası!</div>`;
                });
        } else {
            triggerText.innerText = "Önce ders seçiniz";
            listContainer.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Önce ders seçiniz...</div>';
        }
    }

    function openEditModal(item, category) {
        document.getElementById('scheduleFormV3').reset();
        document.getElementById('scheduleIdV3').value = item.id;
        document.getElementById('modalDateV3').value = item.date;
        document.getElementById('amountInputV3').value = item.amount;

        const radios = document.getElementsByName('action_type');
        let selectedType = 'soru'; 
        for(let r of radios) { 
            if(r.value == item.action_type) { 
                r.checked = true; 
                selectedType = item.action_type;
            } 
        }
        
        // Modal açılınca seçili tipe göre butonları güncelle
        updateQuickButtons(selectedType);

        document.getElementById('statusInputV3').value = item.status;
        document.getElementById('timeInputV3').value = item.time_note || '';

        const unitLabel = document.getElementById('amountUnitLabel');
        if(unitLabel) unitLabel.textContent = (item.action_type === 'konu') ? 'Dakika' : 'Adet';

        if (item.custom_subject || item.custom_topic) {
            switchModeV3('manual');
            document.getElementById('customSubjectV3').value = item.custom_subject;
            document.getElementById('customTopicV3').value = item.custom_topic;
        } else {
            switchModeV3('system');
            if(category) {
                const fs = document.getElementById('fieldSelect');
                const ss = document.getElementById('subjectSelectV3');
                
                fs.value = category;
                ss.innerHTML = '<option value="">Ders Seç...</option>';

                allSubjects.filter(s => s.category == category).forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.innerText = s.name;
                    ss.appendChild(opt);
                });

                requestAnimationFrame(() => {
                    ss.value = item.subject_id;
                    if(item.subject_id) {
                        const ajaxPath = '../ajax/';
                        const urlParams = new URLSearchParams(window.location.search);
                        const studentIdParam = urlParams.get('student_id') || <?php echo $sid ?? 0; ?>;
                        
                        fetch(`${ajaxPath}get_topics.php?subject_id=${item.subject_id}&student_id=${studentIdParam}`)
                            .then(r=>r.json())
                            .then(data => {
                                const listContainer = document.getElementById('customTopicList');
                                const triggerText = document.getElementById('customTopicText');
                                listContainer.innerHTML = '';
                                
                                data.forEach(t => {
                                    const sCount = parseInt(t.total_soru) || 0;
                                    const kCount = parseInt(t.total_konu) || 0;
                                    let statsHTML = (sCount > 0 || kCount > 0) 
                                        ? `<div class="flex items-center gap-2 font-mono text-[10px]">${sCount > 0 ? `<span class="font-bold text-[#223488] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100">${sCount} S</span>` : ''}${kCount > 0 ? `<span class="font-bold text-[#ec9731] bg-orange-50 px-1.5 py-0.5 rounded border border-orange-100">${kCount} K</span>` : ''}</div>` 
                                        : `<span class="text-[9px] text-slate-300">-</span>`;

                                    const div = document.createElement('div');
                                    div.className = "flex justify-between items-center p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors group";
                                    div.onclick = function() { selectTopic(t.id, t.name); };
                                    div.innerHTML = `<span class="text-sm font-semibold text-slate-700 group-hover:text-[#223488]">${t.name}</span>${statsHTML}`;
                                    listContainer.appendChild(div);
                                });

                                const selected = data.find(t => t.id == item.topic_id);
                                if(selected) {
                                    selectTopic(selected.id, selected.name);
                                } else {
                                    triggerText.innerText = "Konu Seçiniz...";
                                }
                            })
                            .catch(err => console.error("Hata:", err));
                    }
                });
            }
        }

        document.getElementById('deleteBtnV3').classList.remove('hidden');
        document.getElementById('addModalV3').classList.remove('hidden');
    }

    function updateSubjectList() {
        const cat = document.getElementById('fieldSelect').value;
        const subSelect = document.getElementById('subjectSelectV3');
        subSelect.innerHTML = '<option value="">Ders Seçiniz...</option>';
        allSubjects.filter(s => s.category == cat).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.innerText = s.name;
            subSelect.appendChild(opt);
        });
        
        document.getElementById('topicSelectV3').value = "";
        document.getElementById('customTopicText').innerText = "Önce ders seçiniz";
        document.getElementById('customTopicText').classList.remove('text-slate-800', 'font-bold');
        document.getElementById('customTopicText').classList.add('text-slate-500');
        document.getElementById('customTopicList').innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Önce ders seçiniz...</div>';
    }

    function closeModalV3() { document.getElementById('addModalV3').classList.add('hidden'); }

    function switchModeV3(mode) {
        if(mode === 'system') {
            document.getElementById('select-modeV3').classList.remove('hidden');
            document.getElementById('manual-modeV3').classList.add('hidden');
            document.getElementById('btn-systemV3').className = "flex-1 py-2 text-xs font-bold rounded-lg bg-white text-slate-800 shadow-sm transition";
            document.getElementById('btn-manualV3').className = "flex-1 py-2 text-xs font-bold rounded-lg text-slate-500 hover:bg-white/50 transition";
        } else {
            document.getElementById('select-modeV3').classList.add('hidden');
            document.getElementById('manual-modeV3').classList.remove('hidden');
            document.getElementById('btn-manualV3').className = "flex-1 py-2 text-xs font-bold rounded-lg bg-white text-slate-800 shadow-sm transition";
            document.getElementById('btn-systemV3').className = "flex-1 py-2 text-xs font-bold rounded-lg text-slate-500 hover:bg-white/50 transition";
        }
    }

    function openAddModalV3(date) {
        document.getElementById('scheduleFormV3').reset();
        document.getElementById('scheduleIdV3').value = "";
        document.getElementById('modalDateV3').value = date;
        document.getElementById('deleteBtnV3').classList.add('hidden');
        switchModeV3('system');
        
        document.getElementById('topicSelectV3').value = "";
        document.getElementById('customTopicText').innerText = "Önce ders seçiniz";
        document.getElementById('customTopicText').classList.remove('text-slate-800', 'font-bold');
        document.getElementById('customTopicText').classList.add('text-slate-500');
        
        // Varsayılan Soru seçili, butonları yükle
        updateQuickButtons('soru');
        
        document.getElementById('addModalV3').classList.remove('hidden');
    }

    function updateSelectColor(select) {
        select.className = select.className.replace(/bg-\w+-\d+ text-\w+-\d+/g, '');
        select.classList.add('bg-white', 'text-slate-600'); 
        const val = select.value;
        if(val === 'yapildi') { select.classList.remove('bg-white','text-slate-600'); select.classList.add('bg-green-100','text-green-700','border-green-200'); }
        else if(val === 'yarim') { select.classList.remove('bg-white','text-slate-600'); select.classList.add('bg-orange-100','text-orange-800','border-orange-200'); }
        else if(val === 'yapilmadi') { select.classList.remove('bg-white','text-slate-600'); select.classList.add('bg-red-100','text-red-700','border-red-200'); }
    }

    function openStatusModal(item) {
        document.getElementById('statusSchedId').value = item.id;

        const subjectName = item.subject_name ? item.subject_name : (item.custom_subject || '-');
        document.getElementById('statusSubjectName').innerText = subjectName;

        const topicName = item.topic_name ? item.topic_name : (item.custom_topic || '-');
        document.getElementById('statusTopicName').innerText = topicName;

        let targetVal = (item.target_amount !== null && item.target_amount !== undefined && item.target_amount !== '')
                        ? item.target_amount
                        : item.amount;

        document.getElementById('displayTarget').innerText = targetVal;

        // Daha önce girilmiş veri varsa onu göster, yoksa hedefi varsayılan yap
        if (item.status && item.status !== 'bekliyor') {
            // Kayıtlı yapılan miktarı göster
            document.getElementById('statusAmount').value = item.amount || 0;
            // Daha önce kaydedilmiş doğru/yanlış varsa doldur
            document.getElementById('statusCorrect').value = (item.correct_count !== null && item.correct_count !== undefined && item.correct_count !== '') ? item.correct_count : '';
            document.getElementById('statusWrong').value   = (item.wrong_count   !== null && item.wrong_count   !== undefined && item.wrong_count   !== '') ? item.wrong_count   : '';
        } else {
            // İlk giriş: varsayılan Yapılan = Hedef
            document.getElementById('statusAmount').value = targetVal || 0;
            document.getElementById('statusCorrect').value = '';
            document.getElementById('statusWrong').value   = '';
        }

        let unitLabel = (item.action_type === 'konu') ? 'Dakika' : 'Soru';
        document.getElementById('targetUnit').innerText = unitLabel;

        // Konu tipinde doğru/yanlış alanlarını (ve ipucunu) gizle
        const dogrusYanlisDiv = document.getElementById('statusCorrect').closest('.grid');
        dogrusYanlisDiv.style.display = (item.action_type === 'konu') ? 'none' : '';
        const dyHint = document.getElementById('statusDyHint');
        if (dyHint) dyHint.style.display = (item.action_type === 'konu') ? 'none' : '';

        document.getElementById('statusSelect').value = item.status || 'bekliyor';
        // Yeni mobil arayüz: çipleri ve ilerleme çubuğunu doldurulan değerlerle senkronla
        if (typeof syncStatusModalUI === 'function') syncStatusModalUI();
        document.getElementById('statusModal').classList.remove('hidden');
    }

    // Doğru + Yanlış → Yapılan otomatik hesapla
    function calcYapilan() {
        const d = parseInt(document.getElementById('statusCorrect').value) || 0;
        const y = parseInt(document.getElementById('statusWrong').value)   || 0;
        if (d > 0 || y > 0) {
            document.getElementById('statusAmount').value = d + y;
        }
    }

    // Yapılan elle girilince Doğru/Yanlış temizle
    function clearDogrusYanlis() {
        document.getElementById('statusCorrect').value = '';
        document.getElementById('statusWrong').value   = '';
    }

    function openTab(id) {
        document.querySelectorAll('.tab-content').forEach(e => e.classList.add('hidden'));
        document.getElementById('content-' + id).classList.remove('hidden');
        document.querySelectorAll('#tab-schedule, #tab-topics, #tab-exams').forEach(b => {
            b.className = "px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2";
        });
        document.getElementById('tab-' + id).className = "px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2";
        
        if(id === 'exams') {
            const defaultType = (typeof studentLevel !== 'undefined' && studentLevel === 'Ortaokul') ? 'LGS' : 'TYT';
            setTimeout(() => { initChart(defaultType); renderExamRows('examRowsArea'); }, 100);
        }
        if(id === 'topics' && typeof filterAnalysis === 'function') {
            const defaultType = (typeof studentLevel !== 'undefined' && studentLevel === 'Ortaokul') ? 'LGS' : 'TYT';
            filterAnalysis(defaultType);
        }
    }

    function toggleSidebarMenu(id, arrowId) { 
        document.getElementById(id).classList.toggle('hidden'); 
        if(arrowId) document.getElementById(arrowId).classList.toggle('rotate-180');
    }

    // Chart init functions
    function initChart(type) {
        const ctx = document.getElementById('examChart').getContext('2d');
        if(examChart) examChart.destroy();
        examChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData[type].labels,
                datasets: [{
                    label: type + ' Netleri',
                    data: chartData[type].data,
                    borderColor: type === 'TYT' ? '#4f46e5' : (type === 'AYT' ? '#9333ea' : '#ea580c'),
                    backgroundColor: type === 'TYT' ? 'rgba(79, 70, 229, 0.1)' : (type === 'AYT' ? 'rgba(147, 51, 234, 0.1)' : 'rgba(234, 88, 12, 0.1)'),
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function switchChart(type) {
        document.querySelectorAll('.chart-tab').forEach(btn => {
            btn.className = "chart-tab px-4 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-500 hover:bg-slate-200 transition";
        });
        event.target.className = `chart-tab px-4 py-1 rounded-full text-xs font-bold shadow-sm transition ${type==='TYT'?'bg-indigo-600 text-white':(type==='AYT'?'bg-purple-600 text-white':'bg-orange-600 text-white')}`;
        initChart(type);
    }
</script>
<?php
// ── ZAMAN MOTORU (S1): sayaç widget'ı + istemci motoru ──────────────────────
// Sunucu zamanı tek doğruluk kaynağıdır; buradaki sayaç yalnız görseldir.
// ff_timer kapalıyken hiçbir şey render edilmez.
$dsTimerOn = false;
try {
    require_once __DIR__ . '/../app_settings_lib.php';
    if (isset($pdo)) $dsTimerOn = ff_enabled($pdo, 'timer');
} catch (Throwable $e) {}
if ($dsTimerOn): ?>
<div id="dsTimerBar" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[9000] w-[calc(100%-2rem)] max-w-md">
    <div class="bg-gradient-to-r from-[#223488] to-[#314595] text-white rounded-2xl shadow-2xl shadow-[#223488]/40 border border-white/10 px-4 py-3 flex items-center gap-3">
        <span id="dsTimerIcon" class="text-xl">⏱</span>
        <div class="flex-grow min-w-0">
            <div id="dsTimerLabel" class="text-[11px] font-bold text-blue-200 truncate">Serbest çalışma</div>
            <div id="dsTimerClock" class="text-xl font-black tabular-nums leading-none mt-0.5">00:00</div>
        </div>
        <button type="button" id="dsTimerPause"
                class="bg-white/15 hover:bg-white/25 rounded-xl px-3 py-2 text-xs font-black transition">⏸ Mola</button>
        <button type="button" id="dsTimerFinish"
                class="bg-[#ec9731] hover:bg-[#d68625] rounded-xl px-3 py-2 text-xs font-black transition">✓ Bitir</button>
    </div>
</div>
<script>
(function () {
    var API = '<?php echo BASE_URL; ?>/ajax/study_api.php';
    var T = { id: null, activeSec: 0, running: false, itemId: null, mode: 'stopwatch', pomoWarned: false };
    var tickHandle = null, hbHandle = null;

    var bar    = document.getElementById('dsTimerBar');
    var clock  = document.getElementById('dsTimerClock');
    var label  = document.getElementById('dsTimerLabel');
    var pauseB = document.getElementById('dsTimerPause');
    var finB   = document.getElementById('dsTimerFinish');
    if (!bar) return;

    function fmt(s) {
        var m = Math.floor(s / 60), ss = s % 60;
        var h = Math.floor(m / 60);
        if (h > 0) { m = m % 60; return h + ':' + String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0'); }
        return String(m).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
    }
    function lsKey() { return 'ds_timer_' + T.id; }
    function persist() { try { localStorage.setItem(lsKey(), String(T.activeSec)); } catch (e) {} }
    function restoreLocal(serverSec) {
        var v = 0;
        try { v = parseInt(localStorage.getItem(lsKey()) || '0', 10) || 0; } catch (e) {}
        T.activeSec = Math.max(serverSec, v);
    }

    function post(action, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        return fetch(API + '?action=' + action, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); });
    }

    function render() {
        clock.textContent = fmt(T.activeSec);
        pauseB.textContent = T.running ? '⏸ Mola' : '▶ Devam';
        document.getElementById('dsTimerIcon').textContent = T.running ? (T.mode === 'pomodoro' ? '🍅' : '⏱') : '💤';
        // Pomodoro: 25 dk dolunca bir kez mola hatırlatması
        if (T.mode === 'pomodoro' && !T.pomoWarned && T.activeSec >= 1500) {
            T.pomoWarned = true;
            label.textContent = '🍅 25 dk doldu — kısa mola iyi gelir!';
        }
    }

    function tick() { if (T.running) { T.activeSec++; persist(); } render(); }
    function heartbeat() {
        if (!T.id) return;
        post('heartbeat', { session_id: T.id, active_sec: T.activeSec }).then(function (j) {
            if (j.gone) { hideTimer(); return; }
            // Sunucu düzeltmesi: duvar saati sınırı aşan sayaç geri çekilir
            if (j.ok && typeof j.duration_sec === 'number' && j.duration_sec < T.activeSec) {
                T.activeSec = j.duration_sec; persist();
            }
        }).catch(function () {});
    }

    function showTimer(session, lbl) {
        T.id = session.id; T.itemId = session.schedule_item_id; T.mode = session.mode || 'stopwatch';
        restoreLocal(session.duration_sec || 0);
        T.running = true; T.pomoWarned = false;
        label.textContent = lbl || 'Serbest çalışma';
        bar.classList.remove('hidden');
        if (!tickHandle) tickHandle = setInterval(tick, 1000);
        if (!hbHandle)   hbHandle   = setInterval(heartbeat, 60000);
        render();
    }
    function hideTimer() {
        T.id = null; T.running = false;
        bar.classList.add('hidden');
        if (tickHandle) { clearInterval(tickHandle); tickHandle = null; }
        if (hbHandle)   { clearInterval(hbHandle);   hbHandle = null; }
    }

    window.startStudyTimer = function (itemId, lbl, eduTopicId, mode) {
        post('start', {
            schedule_item_id: itemId || '',
            edu_topic_id: eduTopicId || '',
            mode: mode || 'stopwatch'
        }).then(function (j) {
            if (!j.ok) { alert(j.error || 'Sayaç başlatılamadı.'); return; }
            if (j.resumed) lbl = labelForItem(j.session.schedule_item_id) || lbl || 'Devam eden çalışma';
            showTimer(j.session, lbl);
        }).catch(function () { alert('Bağlantı hatası — tekrar dene.'); });
    };

    function labelForItem(itemId) {
        if (!itemId) return null;
        var found = null;
        document.querySelectorAll('.task-card[data-item]').forEach(function (el) {
            try {
                var it = JSON.parse(el.getAttribute('data-item'));
                if (parseInt(it.id, 10) === parseInt(itemId, 10)) found = it;
            } catch (e) {}
        });
        if (!found) return null;
        var t = found.edu_subject_name || found.subject_name || found.custom_subject || '';
        var s = found.edu_topic_name || found.topic_name || found.custom_topic || '';
        return (t + ' › ' + s).replace(/^ › | › $/g, '');
    }

    pauseB.addEventListener('click', function () { T.running = !T.running; render(); heartbeat(); });

    finB.addEventListener('click', function () {
        if (!T.id) return;
        post('finish', { session_id: T.id, active_sec: T.activeSec }).then(function (j) {
            if (!j.ok) { alert(j.error || 'Kaydedilemedi.'); return; }
            try { localStorage.removeItem(lsKey()); } catch (e) {}
            var itemId = j.schedule_item_id;
            hideTimer();
            // Bağlı görev varsa işaretleme modalını aç — süre akışı görev akışına bağlanır
            var openIt = null;
            if (itemId) {
                document.querySelectorAll('.task-card[data-item]').forEach(function (el) {
                    try {
                        var it = JSON.parse(el.getAttribute('data-item'));
                        if (parseInt(it.id, 10) === parseInt(itemId, 10)) openIt = it;
                    } catch (e) {}
                });
            }
            alert('🎉 ' + j.minutes + ' dk çalışma kaydedildi!');
            if (openIt && typeof openStatusModal === 'function') openStatusModal(openIt);
        }).catch(function () { alert('Bağlantı hatası — sayaç sunucuda açık kaldı, tekrar Bitir de.'); });
    });

    // Sayfa açılışı: sunucuda aktif oturum varsa kaldığı yerden devam
    fetch(API + '?action=status').then(function (r) { return r.json(); }).then(function (j) {
        if (j.ok && j.session) {
            showTimer(j.session, labelForItem(j.session.schedule_item_id) || 'Devam eden çalışma');
        }
    }).catch(function () {});
})();
</script>
<?php endif; ?>
