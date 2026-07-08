<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .form-input-visible { background-color: #ffffff; border: 1px solid #94a3b8; color: #0f172a; font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .form-input-visible:focus { border-color: #4f46e5; ring: 2px solid #4f46e5; outline: none; }
    select.form-input-visible { appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
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

    // --- MODAL AÇMA YARDIMCISI ---
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('hidden');
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

    document.addEventListener('DOMContentLoaded', () => {
        // 1. KATEGORİ FİLTRESİ VE OLAY DİNLEYİCİSİ (DÜZELTİLDİ)
        const fieldSelect = document.getElementById('fieldSelect');
        if(fieldSelect) {
            const categories = [...new Set(allSubjects.map(s => s.category || 'Genel'))];
            fieldSelect.innerHTML = '<option value="">Alan Seçiniz...</option>';
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
            // EKSİK OLAN SATIR BURASIYDI:
            fieldSelect.addEventListener('change', updateSubjectList);
        }

        // 2. SÜRÜKLE BIRAK (SORTABLE) AYARLARI
        const isDesktopPointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        let ctrlDown = false;
        if (isDesktopPointer) {
            window.addEventListener('keydown', (e) => { if (e.key === 'Control') ctrlDown = true; });
            window.addEventListener('keyup', (e) => { if (e.key === 'Control') ctrlDown = false; });
            window.addEventListener('blur', () => { ctrlDown = false; });
        }

        // AJAX Yardımcısı (Gelişmiş)
        const postScheduleAjax = async (payload) => {
            const params = new URLSearchParams();
            for (const [key, value] of Object.entries(payload)) {
                if (Array.isArray(value)) {
                    value.forEach(v => {
                        let finalKey = key.endsWith('[]') ? key : key + '[]';
                        params.append(finalKey, v);
                    });
                } else {
                    params.append(key, value);
                }
            }
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: params
            });
            const text = await res.text();
            try { return JSON.parse(text); } 
            catch (e1) { return null; }
        };

        // Sıralamayı Kaydet
        const saveSortOrder = async (container) => {
            const orderIds = Array.from(container.children).map(el => el.getAttribute('data-id')).filter(id => id);
            if(orderIds.length > 0) {
                await postScheduleAjax({ ajax: 'reorder_schedule', 'order[]': orderIds });
            }
        };

        // Eski tablo artık yalnızca mobil/tablette görünüyor; oradaki dokunmatik
        // sürükle-bırak sorun çıkardığı için Sortable YALNIZCA masaüstü işaretçide
        // (fine pointer) etkin. Masaüstünde asıl arayüz zaten Planlama Stüdyosu.
        if (isDesktopPointer) document.querySelectorAll('.sortable-list').forEach(listEl => {
            new Sortable(listEl, {
                group: {
                    name: 'shared',
                    pull: (to, from, dragEl, evt) => {
                        return (isDesktopPointer && (ctrlDown || (evt && evt.ctrlKey))) ? 'clone' : true;
                    },
                    put: true
                },
                animation: 150,
                // A. Kendi içinde yer değişince
                onUpdate: (evt) => {
                    saveSortOrder(evt.to);
                },
                // B. Taşıma veya Kopyalama
                onAdd: async (evt) => {
                    const toDate = evt.to.getAttribute('data-date');
                    if (!toDate) return;
                    
                    const itemEl = evt.item;
                    const originalId = itemEl.getAttribute('data-id');
                    const isClone = (evt.pullMode === 'clone') || (isDesktopPointer && (ctrlDown || (evt.originalEvent && evt.originalEvent.ctrlKey)));

                    try {
                        if (isClone) {
                            // Kopyalama
                            itemEl.setAttribute('data-id', '');
                            const out = await postScheduleAjax({ ajax: 'copy_schedule', schedule_id: originalId, to_date: toDate });
                            
                            // BAŞARILIYSA SAYFAYI YENİLE
                            if (out && out.ok) { 
                                window.location.reload(); 
                            } else {
                                itemEl.remove();
                            }
                        } else {
                            // Taşıma
                            await postScheduleAjax({ ajax: 'move_schedule', schedule_id: originalId, to_date: toDate });
                            if(itemEl.dataset.item) {
                                let jsonData = JSON.parse(itemEl.dataset.item);
                                jsonData.date = toDate;
                                itemEl.dataset.item = JSON.stringify(jsonData);
                            }
                            saveSortOrder(evt.to);
                        }
                    } catch (err) { console.warn('Hata:', err); }
                }
            });
        });

        // 3. Başlangıç Ayarları
        const defaultType = (studentLevel === 'Ortaokul') ? 'LGS' : 'TYT';
        if(document.getElementById('examChart')) initChart(defaultType);
        if(typeof filterAnalysis === 'function') filterAnalysis(defaultType);

        // Mod Butonları
        const btnSystem = document.getElementById('btn-systemV3');
        const btnManual = document.getElementById('btn-manualV3');
        const systemInputs = [document.getElementById('fieldSelect'), document.getElementById('subjectSelectV3'), document.getElementById('topicSelectV3')];
        const manualInputs = [document.getElementById('customSubjectV3'), document.getElementById('customTopicV3')];

        if(btnSystem && btnManual) {
            btnSystem.addEventListener('click', function() {
                btnSystem.className = "flex-1 py-2 text-xs font-bold rounded-lg bg-white text-[#223488] shadow-sm transition border border-slate-100";
                btnManual.className = "flex-1 py-2 text-xs font-bold rounded-lg text-slate-500 hover:bg-white/60 transition";
                document.getElementById('select-modeV3').classList.remove('hidden');
                document.getElementById('manual-modeV3').classList.add('hidden');
                systemInputs.forEach(el => el.setAttribute('required', ''));
                manualInputs.forEach(el => el.removeAttribute('required'));
            });
            btnManual.addEventListener('click', function() {
                btnManual.className = "flex-1 py-2 text-xs font-bold rounded-lg bg-white text-[#223488] shadow-sm transition border border-slate-100";
                btnSystem.className = "flex-1 py-2 text-xs font-bold rounded-lg text-slate-500 hover:bg-white/60 transition";
                document.getElementById('select-modeV3').classList.add('hidden');
                document.getElementById('manual-modeV3').classList.remove('hidden');
                systemInputs.forEach(el => el.removeAttribute('required'));
                manualInputs.forEach(el => el.setAttribute('required', ''));
            });
        }
        
        // Hızlı Butonlar
        const unitLabel = document.getElementById('amountUnitLabel');
        const actionRadios = document.querySelectorAll('input[name="action_type"]');
        if(unitLabel) {
            actionRadios.forEach(radio => {
                radio.addEventListener('change', function(e) {
                    const type = e.target.value;
                    unitLabel.textContent = (type === 'konu') ? 'Dakika' : 'Adet';
                    updateQuickButtons(type);
                });
            });
        }
        updateQuickButtons('soru');
    });

    function updateQuickButtons(type) {
        const container = document.getElementById('quickButtonsContainer');
        if (!container) return;
        container.innerHTML = '';
        let values = (type === 'konu') ? [20, 30, 40, 50, 60, 75, 90] : [10, 15, 20, 25, 30, 50, 75];
        let btnColor = (type === 'konu') ? 'bg-[#ec9731] hover:bg-[#d68625] border-[#ec9731]' : 'bg-[#223488] hover:bg-[#314595] border-[#223488]';
        values.forEach(val => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `flex-shrink-0 w-10 h-10 rounded-full font-bold text-xs shadow-md border-2 transition-transform active:scale-90 flex items-center justify-center snap-center text-white ${btnColor}`;
            btn.innerText = val;
            btn.onclick = function() {
                const input = document.getElementById('amountInputV3');
                if (input) {
                    input.value = val;
                    input.classList.add('ring-2', 'ring-offset-1', 'ring-opacity-50');
                    setTimeout(() => input.classList.remove('ring-2', 'ring-offset-1', 'ring-opacity-50'), 200);
                }
            };
            container.appendChild(btn);
        });
    }

    // --- DERS LİSTESİ YENİLEME FONKSİYONU ---
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

    function loadTopicsV3() {
        const ajaxPath = 'ajax/'; 
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
            fetch(`${ajaxPath}get_topics.php?subject_id=${sid}&student_id=${studentIdParam}`)
                .then(r => r.json())
                .then(data => {
                    listContainer.innerHTML = '';
                    if (!data || data.length === 0) {
                        listContainer.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Konu bulunamadı.</div>';
                        triggerText.innerText = "Konu bulunamadı";
                        return;
                    }
                    triggerText.innerText = "Konu Seçiniz...";
                    data.forEach(t => {
                        const sCount = parseInt(t.total_soru)||0;
                        const kCount = parseInt(t.total_konu)||0;
                        let statsHTML = (sCount>0||kCount>0) ? `<div class="flex items-center gap-2 font-mono text-[10px]">${sCount>0?`<span class="font-bold text-[#223488] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100">${sCount} S</span>`:''}${kCount>0?`<span class="font-bold text-[#ec9731] bg-orange-50 px-1.5 py-0.5 rounded border border-orange-100">${kCount} K</span>`:''}</div>` : `<span class="text-[9px] text-slate-300">-</span>`;
                        const div = document.createElement('div');
                        div.className = "flex justify-between items-center p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0 transition-colors group";
                        div.onclick = function() { selectTopic(t.id, t.name); };
                        div.innerHTML = `<span class="text-sm font-semibold text-slate-700 group-hover:text-[#223488]">${t.name}</span>${statsHTML}`;
                        listContainer.appendChild(div);
                    });
                })
                .catch(err => { triggerText.innerText = "Hata"; listContainer.innerHTML = `<div class="p-3 text-xs text-red-500 text-center">Hata!</div>`; });
        } else {
            triggerText.innerText = "Önce ders seçiniz";
            listContainer.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Önce ders seçiniz...</div>';
        }
    }

    function openAddModalV3(date) {
        document.getElementById('scheduleFormV3').reset();
        document.getElementById('scheduleIdV3').value = "";
        document.getElementById('modalDateV3').value = date;
        document.getElementById('deleteBtnV3').classList.add('hidden');

        if (typeof window.eduTaskReset === 'function') window.eduTaskReset();

        updateQuickButtons('soru');
        openModal('addModalV3');
    }

    function openEditModal(item, category) {
        document.getElementById('scheduleFormV3').reset();
        document.getElementById('scheduleIdV3').value = item.id;
        document.getElementById('modalDateV3').value = item.date;
        document.getElementById('amountInputV3').value = item.amount;
        document.getElementById('statusInputV3').value = item.status;
        document.getElementById('timeInputV3').value = item.time_note || '';

        const radios = document.getElementsByName('action_type');
        let selectedType = 'soru';
        for(let r of radios) { if(r.value == item.action_type) { r.checked = true; selectedType = item.action_type; } }
        updateQuickButtons(selectedType);

        const unitLabel = document.getElementById('amountUnitLabel');
        if(unitLabel) unitLabel.textContent = (item.action_type === 'konu') ? 'Dakika' : 'Adet';

        // Yeni iki katmanlı seçiciyi görev verisiyle önyükle
        if (typeof window.eduTaskPrefill === 'function') window.eduTaskPrefill(item);

        document.getElementById('deleteBtnV3').classList.remove('hidden');
        openModal('addModalV3');
    }

    function closeModalV3() { document.getElementById('addModalV3').classList.add('hidden'); }

    function updateSelectColor(select) {
        select.className = "w-full h-12 px-3 rounded-xl text-sm font-bold border-2 border-slate-200 transition cursor-pointer outline-none ";
        const val = select.value;
        if(val === 'yapildi') select.classList.add('bg-green-100','text-green-700','border-green-200');
        else if(val === 'yarim') select.classList.add('bg-orange-100','text-orange-800','border-orange-200');
        else if(val === 'yapilmadi') select.classList.add('bg-red-100','text-red-700','border-red-200');
        else select.classList.add('bg-white','text-slate-600');
    }

    function openStatusModal(item) {
        document.getElementById('statusSchedId').value = item.id;
        document.getElementById('statusTopicName').innerText = item.topic_name ? item.topic_name : (item.custom_topic || '-');
        document.getElementById('displayTarget').innerText = item.target_amount || item.amount;
        document.getElementById('statusAmount').value = item.amount || 0;
        document.getElementById('targetUnit').innerText = (item.action_type === 'konu') ? 'Dakika' : 'Soru';
        document.getElementById('statusSelect').value = item.status || 'bekliyor';
        openModal('statusModal');
    }

    function openTab(id) {
        document.querySelectorAll('.tab-content').forEach(e => e.classList.add('hidden'));
        document.getElementById('content-' + id).classList.remove('hidden');
        const inactive = "px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2";
        const active   = "px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2";
        ['schedule','topics','exams'].forEach(t => {
            ['tab-'+t, 'tab-'+t+'-desk'].forEach(id2 => {
                const el = document.getElementById(id2);
                if(el) el.className = inactive;
            });
        });
        ['tab-'+id, 'tab-'+id+'-desk'].forEach(id2 => {
            const el = document.getElementById(id2);
            if(el) el.className = active;
        });
        // Masaüstü: Program sekmesi gömülü Planlama Stüdyosunu gösterir; diğer sekmeler gizler
        if(typeof window.psShowStudio === 'function') window.psShowStudio(id === 'schedule');
        if(id === 'exams') {
            const defaultType = (studentLevel === 'Ortaokul') ? 'LGS' : 'TYT';
            setTimeout(() => { if(typeof initChart === 'function') initChart(defaultType); }, 100);
        }
        if(id === 'topics' && typeof filterAnalysis === 'function') filterAnalysis('RAPOR');
    }

    function toggleSidebarMenu(id, arrowId) { 
        document.getElementById(id).classList.toggle('hidden'); 
        if(arrowId) document.getElementById(arrowId).classList.toggle('rotate-180');
    }

    function initChart(type) {
        const ctx = document.getElementById('examChart');
        if(!ctx) return;
        if(typeof examChart !== 'undefined' && examChart) examChart.destroy();
        if(typeof chartData === 'undefined' || !chartData[type]) return;
        const ctx2d = ctx.getContext('2d');
        examChart = new Chart(ctx2d, {
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
        if(event && event.target) event.target.className = `chart-tab px-4 py-1 rounded-full text-xs font-bold shadow-sm transition ${type==='TYT'?'bg-indigo-600 text-white':(type==='AYT'?'bg-purple-600 text-white':'bg-orange-600 text-white')}`;
        initChart(type);
    }
</script>