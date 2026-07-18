<div id="content-schedule" class="tab-content block animate-fadeIn font-sans">
    
    <style>
        :root {
            --atla-blue-dark: #223488;
            --atla-blue-light: #314595;
            --atla-orange: #ec9731;
            --atla-orange-hover: #d68625;
        }
        /* Buton Stilleri */
        .btn-nav-blue { background-color: var(--atla-blue-dark); color: white; }
        .btn-nav-blue:hover { background-color: var(--atla-blue-light); }
        
        .btn-nav-outline { background-color: #f8fafc; color: var(--atla-blue-dark); border: 1px solid #e2e8f0; }
        .btn-nav-outline:hover { background-color: #eff6ff; border-color: var(--atla-blue-light); color: var(--atla-blue-light); }

        .btn-today { background-color: var(--atla-orange); color: white; }
        .btn-today:hover { background-color: var(--atla-orange-hover); }
        
        /* Kart Hover */
        .task-card:hover { transform: translateY(-2px); box-shadow: 0 6px 12px -2px rgba(0, 0, 0, 0.1), 0 3px 7px -3px rgba(0, 0, 0, 0.08); }
    </style>

    <div class="flex flex-wrap justify-between items-center mb-6 bg-white p-2 md:p-3 rounded-2xl border border-slate-100 shadow-lg shadow-slate-200/50 gap-2 md:gap-3">
        <div class="flex gap-1 md:gap-2">
            <a href="?date=<?php echo $prev_week; ?>" class="px-2 md:px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm btn-nav-outline flex items-center gap-1"><span class="text-lg leading-none">«</span> <span class="hidden sm:inline">Hafta</span><span class="sm:hidden">H</span></a>
            <a href="?date=<?php echo $prev_day; ?>" class="px-2 md:px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm btn-nav-blue flex items-center gap-1"><span class="text-lg leading-none">‹</span> <span class="hidden sm:inline">Gün</span><span class="sm:hidden">G</span></a>
        </div>

        <div class="flex items-center gap-2 md:gap-3">
            <a href="?date=<?php echo $today_date; ?>" class="px-3 md:px-6 py-2 rounded-xl text-xs font-bold transition shadow-md shadow-orange-100 btn-today flex items-center gap-2 transform active:scale-95"><span>📅</span> Bugün</a>
            <span class="font-bold text-[#223488] text-sm hidden md:block border-l pl-3 border-slate-200"><?php echo date('d.m.Y', strtotime($week_start)); ?> Başlangıçlı</span>
        </div>

        <div class="flex gap-1 md:gap-2">
            <a href="?date=<?php echo $next_day; ?>" class="px-2 md:px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm btn-nav-blue flex items-center gap-1"><span class="hidden sm:inline">Gün</span><span class="sm:hidden">G</span> <span class="text-lg leading-none">›</span></a>
            <a href="?date=<?php echo $next_week; ?>" class="px-2 md:px-3 py-2 rounded-xl text-xs font-bold transition shadow-sm btn-nav-outline flex items-center gap-1"><span class="hidden sm:inline">Hafta</span><span class="sm:hidden">H</span> <span class="text-lg leading-none">»</span></a>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 xl:gap-2 pb-4">
        <?php foreach ($week_dates as $wd):
            $englishDay = date('l', strtotime($wd));
            $turkishDay = $gunlerTR[$englishDay] ?? $englishDay;
            $dayItems   = $schedule_items[$wd] ?? [];
            // Günün BEKLEYEN görevleri — ✓✓ modalında seçmeli listelenir
            $pendingList = [];
            foreach ($dayItems as $di) {
                if (($di['status'] ?? 'bekliyor') !== 'bekliyor') continue;
                $pTitle = $di['edu_subject_name'] ?? (!empty($di['topic_name']) ? ($di['subject_name'] ?? '') : ($di['custom_subject'] ?? ''));
                $pSub   = $di['edu_topic_name'] ?? (!empty($di['topic_name']) ? ($di['topic_name'] ?? '') : ($di['custom_topic'] ?? ''));
                $pType  = $di['action_type'] ?? 'soru';
                $pAmt   = (isset($di['target_amount']) && $di['target_amount'] !== null && $di['target_amount'] !== '') ? (int)$di['target_amount'] : (int)($di['amount'] ?? 0);
                $pendingList[] = [
                    'id'    => (int)$di['id'],
                    'title' => trim($pTitle) !== '' ? $pTitle : 'Genel Çalışma',
                    'sub'   => (string)$pSub,
                    'unit'  => $pType === 'konu' ? 'dk' : ($pType === 'video' ? 'video' : 'soru'),
                    'amount'=> $pAmt,
                ];
            }
            $pendingCount = count($pendingList);
        ?>
            <div class="flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden h-full min-h-[350px] transition-all hover:shadow-md">
                <div class="relative bg-gradient-to-r from-[#223488] to-[#314595] text-white px-3 py-3 xl:px-2.5 xl:py-2.5 border-b border-[#1e2e7a]">
                    <div class="absolute left-0 top-0 h-full w-1 bg-[#ec9731]"></div>
                    <div class="flex items-center justify-between gap-2 relative z-10">
                        <div class="flex items-center gap-2.5 xl:gap-2 min-w-0">
                            <div class="w-10 h-10 xl:w-9 xl:h-9 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center flex-shrink-0 backdrop-blur-sm">
                                <span class="font-black text-xl xl:text-lg leading-none"><?php echo date('d', strtotime($wd)); ?></span>
                            </div>
                            <div class="min-w-0">
                                <div class="font-black text-[13px] xl:text-[12px] uppercase tracking-wide truncate"><?php echo $turkishDay; ?></div>
                                <div class="text-[10px] font-medium text-blue-100/80 truncate mt-0.5">Günlük Plan</div>
                            </div>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                        <!-- ✓✓ Toplu işaretle: modal açar, öğrenci istediği görevleri seçip yapıldı yapar -->
                        <button type="button"
                                onclick='openBulkDone(<?php echo htmlspecialchars(json_encode(["day"=>$wd,"dayName"=>$turkishDay,"items"=>$pendingList], JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8"); ?>)'
                                class="flex-shrink-0 w-8 h-8 rounded-full bg-white/15 hover:bg-emerald-500 border border-white/25 hover:border-emerald-400 text-white text-[11px] font-black flex items-center justify-center transition active:scale-90"
                                title="Bu günün bekleyen görevlerini seçerek yapıldı işaretle (<?php echo $pendingCount; ?> görev)">✓✓</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-2 pr-10 md:pr-2 space-y-3 xl:space-y-2 flex-grow bg-slate-50 relative min-h-[150px]">
                    <div class="absolute top-0 bottom-0 left-6 w-px bg-slate-200 z-0"></div>
                    <?php if (empty($dayItems)): ?>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 opacity-60 z-0 pointer-events-none select-none">
                            <span class="text-4xl mb-2 grayscale">☕</span>
                            <span class="text-xs font-bold uppercase tracking-widest">Plan Yok</span>
                        </div>
                    <?php endif; ?>

                    <div id="day-<?php echo $wd; ?>" class="space-y-3 relative z-10 w-full pb-10">
                        <?php if (!empty($dayItems)): foreach ($dayItems as $item):
                            $status   = $item['status'] ?? 'bekliyor';
                            $action   = $item['action_type'] ?? 'soru';
                            // Konu kimliği: önce YENİ müfredat (edu_*) -> eski koçluk -> manuel
                            $eduCat   = $item['edu_category_name'] ?? '';
                            $eduSubj  = $item['edu_subject_name']  ?? '';
                            $eduTopic = $item['edu_topic_name']    ?? '';
                            $isManual = ($eduCat === '' && empty($item['subject_category']) && (!empty($item['custom_topic']) || !empty($item['custom_subject'])));
                            $category = $eduCat ?: ($item['subject_category'] ?? '');
                            if ($category === '' && $isManual) $category = 'Diğer';

                            $borderClass = 'border-slate-300 hover:border-slate-400 bg-white';
                            $statusBadge = 'bg-slate-100 text-slate-700';
                            $statusIcon  = '⏳';
                            $iconBg      = 'bg-slate-100 text-slate-500';

                            if ($status === 'yapildi') {
                                $borderClass = 'border-green-300 hover:border-green-400 bg-green-50'; 
                                $statusBadge = 'bg-green-100 text-green-500';
                                $statusIcon  = '✅';
                                $iconBg      = 'bg-green-100 text-green-400';
                            } elseif ($status === 'yapilmadi') {
                                $borderClass = 'border-red-500 hover:border-red-600 bg-red-100';
                                $statusBadge = 'bg-red-200 text-red-900';
                                $statusIcon  = '❌';
                                $iconBg      = 'bg-red-200 text-red-800';
                            } elseif ($status === 'yarim') {
                                $borderClass = 'border-orange-500 hover:border-orange-600 bg-orange-100';
                                $statusBadge = 'bg-orange-200 text-orange-900';
                                $statusIcon  = '⚠️';
                                $iconBg      = 'bg-orange-200 text-orange-800';
                            }

                            $metricLabel = ($action === 'konu') ? 'Dakika' : 'Soru';
                            $metricClass = ($action === 'konu') ? 'bg-[#ec9731] text-white' : 'bg-[#223488] text-white';

                            $title = $eduSubj ?: (!empty($item['topic_name']) ? ($item['subject_name'] ?? '') : ($item['custom_subject'] ?? ''));
                            $subtitle = $eduTopic ?: (!empty($item['topic_name']) ? ($item['topic_name'] ?? '') : ($item['custom_topic'] ?? ''));
                            $resourceTitle = $item['resource_title'] ?? '';
                            $isVideoTask = (($item['action_type'] ?? '') === 'video');
                            $taskNote = trim((string)($item['task_note'] ?? ''));
                            if ($isVideoTask) { $metricLabel = 'Video'; $metricClass = 'bg-red-600 text-white'; }
                            $safeItem = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                            $clickFn = $isVideoTask ? 'openVideoTaskModal' : 'openStatusModal';
                        ?>
                            <div class="task-card group relative rounded-xl border-[3px] <?php echo $borderClass; ?> p-3 xl:p-2.5 shadow-sm transition-all duration-200 cursor-pointer z-10"
                                 data-item='<?php echo $safeItem; ?>'
                                 onclick='<?php echo $clickFn; ?>(JSON.parse(this.getAttribute("data-item")))'>

                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php if ($isVideoTask): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wide bg-red-600 text-white">🎬 VİDEO</span>
                                        <?php elseif ($resourceTitle !== ''): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wide bg-red-50 text-red-600 border border-red-200 truncate max-w-[120px]" title="Kaynak: <?php echo htmlspecialchars($resourceTitle); ?>">
                                            📕 <?php echo htmlspecialchars($resourceTitle); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wide border border-transparent <?php echo $statusBadge; ?>">
                                            <?php echo htmlspecialchars($category ?: 'GENEL'); ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['time_note'])): ?>
                                            <span class="text-[10px] font-bold text-slate-600 flex items-center gap-1 bg-white/60 px-1.5 py-0.5 rounded border border-slate-300/50">
                                                ⏰ <?php echo htmlspecialchars($item['time_note']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-base leading-none border border-transparent <?php echo $iconBg; ?>">
                                        <?php echo $statusIcon; ?>
                                    </div>
                                </div>

                                <div class="pl-1">
                                    <div class="font-extrabold text-slate-900 text-sm leading-tight mb-0.5 truncate group-hover:text-[#223488] transition-colors">
                                        <?php echo htmlspecialchars($title); ?>
                                    </div>
                                    <div class="text-xs font-bold text-slate-600 truncate">
                                        <?php echo htmlspecialchars($subtitle); ?>
                                    </div>
                                    <?php if ($taskNote !== ''): ?>
                                    <div class="text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-100 rounded px-1.5 py-0.5 mt-1 truncate" title="<?php echo htmlspecialchars($taskNote); ?>">
                                        📝 <?php echo htmlspecialchars($taskNote); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 pt-2 border-t border-black/10 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <?php if (!empty($timerOn) && !$isVideoTask && in_array($status, ['bekliyor', 'yarim'], true)): ?>
                                        <button type="button"
                                                onclick='event.stopPropagation(); startStudyTimer(<?php echo (int)$item['id']; ?>, <?php echo htmlspecialchars(json_encode(trim($title . " › " . $subtitle, " ›"), JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>, <?php echo (int)($item['edu_topic_id'] ?? 0); ?>)'
                                                class="w-6 h-6 rounded-full bg-[#223488] hover:bg-[#ec9731] text-white text-[10px] font-black flex items-center justify-center transition shrink-0"
                                                title="Sayaçla çalışmaya başla">▶</button>
                                        <?php endif; ?>
                                        <span class="text-[9px] font-bold uppercase text-slate-500 tracking-wider">Hedef / Yapılan</span>
                                    </span>
                                    <div class="flex items-center gap-1">
                                        <?php 
                                            // --- GÜNCELLENEN KISIM: HEDEF / YAPILAN GÖSTERİMİ ---
                                            $realized = (int)($item['amount'] ?? 0);
                                            // target_amount varsa kullan, yoksa realized'ı hedef kabul et
                                            $target   = (isset($item['target_amount']) && $item['target_amount'] !== null && $item['target_amount'] !== '') 
                                                        ? (int)$item['target_amount'] 
                                                        : $realized;
                                            
                                            // Eğer hedef farklıysa ve durum "bekliyor" değilse göster
                                            if ($target != $realized && $status != 'bekliyor'): 
                                        ?>
                                            <div class="flex items-baseline gap-1.5 mr-1">
                                                <span class="text-xs text-slate-400 font-bold line-through decoration-red-400 decoration-1" title="Hedeflenen">
                                                    <?php echo $target; ?>
                                                </span>
                                                <span class="text-xl font-black text-slate-900" title="Gerçekleşen">
                                                    <?php echo $realized; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xl font-black text-slate-900"><?php echo $realized; ?></span>
                                        <?php endif; ?>
                                        <span class="text-[9px] px-1.5 py-0.5 rounded font-bold <?php echo $metricClass; ?>">
                                            <?php echo $metricLabel; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ✓✓ TOPLU İŞARETLE MODALI: günün bekleyen görevleri seçmeli listelenir -->
    <div id="bulkDoneModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 flex flex-col max-h-[85vh]">
            <div class="bg-gradient-to-r from-[#223488] to-[#314595] text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-base">✅ Görevleri İşaretle</h3>
                    <p id="bulkDoneSubtitle" class="text-[11px] text-blue-100 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeBulkDone()" class="text-white/60 hover:text-white text-xl font-black leading-none">✕</button>
            </div>
            <form method="POST" id="bulkDoneForm" class="flex flex-col min-h-0">
                <input type="hidden" name="bulk_day_done" value="1">
                <div class="px-5 py-2 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                    <span class="text-[11px] font-bold text-slate-500">Yaptığın görevleri seç:</span>
                    <button type="button" onclick="bulkToggleAll()" id="bulkToggleAllBtn" class="text-[11px] font-black text-[#223488] hover:underline">Tümünü Seç</button>
                </div>
                <div id="bulkDoneList" class="overflow-y-auto p-3 space-y-2 flex-grow"></div>
                <div class="p-4 border-t border-slate-100 flex items-center gap-2">
                    <button type="button" onclick="closeBulkDone()" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-sm transition">Vazgeç</button>
                    <button type="submit" id="bulkDoneSubmit" class="flex-1 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white font-black text-sm transition">Yapıldı İşaretle</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var modal = document.getElementById('bulkDoneModal');
        var list  = document.getElementById('bulkDoneList');
        var esc = function(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };

        window.openBulkDone = function(data){
            document.getElementById('bulkDoneSubtitle').textContent =
                (data.dayName || '') + ' — ' + data.items.length + ' bekleyen görev';
            list.innerHTML = '';
            data.items.forEach(function(it){
                var amt = it.amount ? ('<span class="text-[10px] font-black text-white bg-[#223488] rounded px-1.5 py-0.5 ml-auto shrink-0">' + it.amount + ' ' + esc(it.unit) + '</span>') : '';
                var subLine = it.sub ? ('<div class="text-[11px] text-slate-500 truncate">' + esc(it.sub) + '</div>') : '';
                var row = document.createElement('label');
                row.className = 'flex items-center gap-3 p-2.5 rounded-xl border border-slate-200 hover:border-[#223488]/40 hover:bg-slate-50 cursor-pointer transition';
                row.innerHTML =
                    '<input type="checkbox" name="done_ids[]" value="' + it.id + '" checked class="w-5 h-5 accent-emerald-500 shrink-0">' +
                    '<div class="min-w-0 flex-grow">' +
                        '<div class="text-sm font-bold text-slate-800 truncate">' + esc(it.title) + '</div>' +
                        subLine +
                    '</div>' + amt;
                list.appendChild(row);
            });
            updateToggleLabel();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };
        window.closeBulkDone = function(){
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };
        window.bulkToggleAll = function(){
            var boxes = list.querySelectorAll('input[type=checkbox]');
            var allChecked = Array.prototype.every.call(boxes, function(b){ return b.checked; });
            boxes.forEach(function(b){ b.checked = !allChecked; });
            updateToggleLabel();
        };
        function updateToggleLabel(){
            var boxes = list.querySelectorAll('input[type=checkbox]');
            var allChecked = boxes.length > 0 && Array.prototype.every.call(boxes, function(b){ return b.checked; });
            document.getElementById('bulkToggleAllBtn').textContent = allChecked ? 'Tümünü Kaldır' : 'Tümünü Seç';
        }
        list.addEventListener('change', updateToggleLabel);
        modal.addEventListener('click', function(e){ if (e.target === modal) window.closeBulkDone(); });
        document.getElementById('bulkDoneForm').addEventListener('submit', function(e){
            if (!list.querySelector('input[type=checkbox]:checked')) {
                e.preventDefault();
                alert('En az bir görev seçmelisin.');
            }
        });
    })();
    </script>
</div>