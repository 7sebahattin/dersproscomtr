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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 pb-4">
        <?php foreach ($week_dates as $wd):
            $englishDay = date('l', strtotime($wd));
            $turkishDay = $gunlerTR[$englishDay] ?? $englishDay;
            $dayItems   = $schedule_items[$wd] ?? [];
            $totalCount = is_array($dayItems) ? count($dayItems) : 0;
            $doneCount  = 0;
            if ($totalCount > 0) {
                foreach ($dayItems as $di) { if (($di['status'] ?? '') === 'yapildi') $doneCount++; }
            }
            $progress = ($totalCount > 0) ? (int)round(($doneCount / $totalCount) * 100) : 0;
        ?>
            <div class="flex flex-col bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden h-full min-h-[350px] transition-all hover:shadow-md">
                <div class="relative bg-gradient-to-r from-[#223488] to-[#314595] text-white px-3 py-3 border-b border-[#1e2e7a]">
                    <div class="absolute left-0 top-0 h-full w-1 bg-[#ec9731]"></div>
                    <div class="flex items-center justify-between gap-3 relative z-10">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center flex-shrink-0 backdrop-blur-sm">
                                <span class="font-black text-xl leading-none"><?php echo date('d', strtotime($wd)); ?></span>
                            </div>
                            <div class="min-w-0">
                                <div class="font-black text-[13px] uppercase tracking-wide truncate"><?php echo $turkishDay; ?></div>
                                <div class="text-[10px] font-medium text-blue-100/80 truncate mt-0.5">Günlük Plan</div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#ec9731] text-white shadow-sm border border-orange-400/50"><?php echo $totalCount; ?> Görev</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white/20 text-white border border-white/10">✅ <?php echo $doneCount; ?></span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-[10px] font-bold text-blue-100 mb-1">
                            <span>İlerleme</span>
                            <span>%<?php echo $progress; ?></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-black/20 overflow-hidden">
                            <div class="h-full rounded-full bg-[#ec9731] transition-all duration-500 ease-out shadow-[0_0_10px_rgba(236,151,49,0.5)]" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-2 pr-10 md:pr-2 space-y-3 flex-grow bg-slate-50 relative min-h-[150px]">
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
                                $statusIcon  = '✖';
                                $iconBg      = 'bg-red-200 text-red-800';
                            } elseif ($status === 'yarim') {
                                $borderClass = 'border-orange-500 hover:border-orange-600 bg-orange-100';
                                $statusBadge = 'bg-orange-200 text-orange-900';
                                $statusIcon  = '⚠';
                                $iconBg      = 'bg-orange-200 text-orange-800';
                            }

                            $metricLabel = ($action === 'konu') ? 'Dakika' : 'Soru';
                            $metricClass = ($action === 'konu') ? 'bg-[#ec9731] text-white' : 'bg-[#223488] text-white';

                            $title = $eduSubj ?: (!empty($item['topic_name']) ? ($item['subject_name'] ?? '') : ($item['custom_subject'] ?? ''));
                            $subtitle = $eduTopic ?: (!empty($item['topic_name']) ? ($item['topic_name'] ?? '') : ($item['custom_topic'] ?? ''));
                            $resourceTitle = $item['resource_title'] ?? '';
                            $safeItem = htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="task-card group relative rounded-xl border-[3px] <?php echo $borderClass; ?> p-3 shadow-sm transition-all duration-200 cursor-pointer z-10"
                                 data-item='<?php echo $safeItem; ?>'
                                 onclick='openStatusModal(JSON.parse(this.getAttribute("data-item")))'>

                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php if ($resourceTitle !== ''): ?>
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
                                    <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs border border-transparent <?php echo $iconBg; ?>">
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
                                </div>

                                <div class="mt-3 pt-2 border-t border-black/10 flex items-center justify-between">
                                    <span class="text-[9px] font-bold uppercase text-slate-500 tracking-wider">Hedef / Yapılan</span>
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
                                            <div class="flex flex-col items-end leading-none mr-1">
                                                <span class="text-[9px] text-slate-400 font-bold line-through decoration-red-400 decoration-1" title="Hedeflenen">
                                                    <?php echo $target; ?>
                                                </span>
                                                <span class="text-sm font-black text-slate-900" title="Gerçekleşen">
                                                    <?php echo $realized; ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm font-black text-slate-900"><?php echo $realized; ?></span>
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
</div>