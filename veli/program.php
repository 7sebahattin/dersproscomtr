<div id="content-schedule" class="tab-content hidden animate-fadeIn">
    <div class="flex flex-wrap justify-between items-center mb-6 bg-white p-3 rounded-2xl border border-slate-200 shadow-sm gap-2">
        
        <div class="flex gap-2">
            <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $prev_week; ?>&tab=schedule" 
               class="px-4 py-2 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl text-xs font-bold text-slate-600 transition flex items-center gap-1 shadow-sm border border-slate-100">
                <span>⏪</span> <span class="hidden sm:inline">Hafta</span>
            </a>
            <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $prev_day; ?>&tab=schedule" 
               class="px-4 py-2 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl text-xs font-bold text-slate-600 transition flex items-center gap-1 shadow-sm border border-slate-100">
                <span>⬅️</span> <span class="hidden sm:inline">Gün</span>
            </a>
        </div>

        <div class="flex items-center gap-2">
            <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $today_date; ?>&tab=schedule" 
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-bold transition shadow-md shadow-indigo-200 flex items-center gap-2">
                📅 <span class="hidden sm:inline">Bugün</span>
            </a>
            <div class="hidden md:block text-sm font-bold text-slate-700 bg-white px-4 py-2 rounded-xl border border-slate-100 shadow-sm">
                Başlangıç: <?php echo date('d.m.Y', strtotime($week_start)); ?>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $next_day; ?>&tab=schedule" 
               class="px-4 py-2 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl text-xs font-bold text-slate-600 transition flex items-center gap-1 shadow-sm border border-slate-100">
                <span class="hidden sm:inline">Gün</span> <span>➡️</span>
            </a>
            <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $next_week; ?>&tab=schedule" 
               class="px-4 py-2 bg-slate-50 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl text-xs font-bold text-slate-600 transition flex items-center gap-1 shadow-sm border border-slate-100">
                <span class="hidden sm:inline">Hafta</span> <span>⏩</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 pb-8">
        <?php foreach ($week_dates as $wd):
            $englishDay = date('l', strtotime($wd));
            $turkishDay = $gunlerTR[$englishDay] ?? $englishDay;

            $dayItems   = $schedule_items[$wd] ?? [];
            $totalCount = count($dayItems);

            $doneCount  = 0;
            foreach ($dayItems as $di) {
                if (($di['status'] ?? '') === 'yapildi') $doneCount++;
            }
            $progress = ($totalCount > 0) ? (int)round(($doneCount / $totalCount) * 100) : 0;
            
            // Bugünün kartını vurgula
            $isToday = ($wd == $today_date);
            $cardBorder = $isToday ? 'ring-2 ring-indigo-500 ring-offset-2' : '';
        ?>
            <div class="flex flex-col bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden h-full min-h-[400px] <?php echo $cardBorder; ?>">

                <div class="relative bg-slate-900 text-white px-4 py-4 border-b border-slate-800">
                    <div class="absolute left-0 top-0 h-full w-1.5 bg-orange-500/80"></div>

                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/15 flex items-center justify-center">
                                <span class="font-black text-lg leading-none"><?php echo date('d', strtotime($wd)); ?></span>
                            </div>
                            <div class="min-w-0">
                                <span class="font-black text-[13px] uppercase tracking-wide block truncate"><?php echo $turkishDay; ?></span>
                                <span class="text-[10px] font-bold text-white/50 block"><?php echo date('m.Y', strtotime($wd)); ?></span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end">
                             <span class="text-[10px] font-black text-orange-400"><?php echo $doneCount; ?> / <?php echo $totalCount; ?></span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full rounded-full bg-orange-500 transition-all duration-700"
                                 style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="p-3 space-y-3 flex-grow bg-slate-50/50 overflow-y-auto custom-scrollbar">
                    <?php if (!empty($dayItems)): foreach ($dayItems as $item):
                        $status   = $item['status'] ?? 'bekliyor';
                        $action   = $item['action_type'] ?? 'soru';
                        $category = $item['subject_category'] ?? 'Genel';

                        // Durum Stilleri
                        $borderClass = 'border-slate-200';
                        $statusBadge = 'bg-slate-100 text-slate-500';
                        $statusIcon  = '⏳';

                        if ($status === 'yapildi') {
                            $borderClass = 'border-emerald-200 shadow-emerald-50';
                            $statusBadge = 'bg-emerald-50 text-emerald-600';
                            $statusIcon  = '✅';
                        } elseif ($status === 'yapilmadi') {
                            $borderClass = 'border-red-200 shadow-red-50';
                            $statusBadge = 'bg-red-50 text-red-600';
                            $statusIcon  = '❌';
                        } elseif ($status === 'yarim') {
                            $borderClass = 'border-amber-200 shadow-amber-50';
                            $statusBadge = 'bg-amber-50 text-amber-700';
                            $statusIcon  = '⚠️';
                        }

                        $title = !empty($item['topic_name']) ? $item['subject_name'] : $item['custom_subject'];
                        $subtitle = !empty($item['topic_name']) ? $item['topic_name'] : $item['custom_topic'];
                        $metricLabel = ($action === 'soru') ? 'Soru' : 'Dakika';
                    ?>
                        <div class="bg-white group relative rounded-2xl border-2 <?php echo $borderClass; ?> p-3 shadow-sm transition duration-300">
                            
                            <div class="flex items-start justify-between mb-2">
                                <span class="px-2 py-0.5 rounded-lg text-[9px] font-black uppercase tracking-tighter <?php echo $statusBadge; ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </span>
                                <span class="text-sm"><?php echo $statusIcon; ?></span>
                            </div>

                            <div class="font-bold text-slate-800 text-xs leading-tight mb-1 truncate">
                                <?php echo htmlspecialchars($title); ?>
                            </div>
                            <div class="text-slate-500 text-[10px] font-medium truncate mb-3">
                                <?php echo htmlspecialchars($subtitle); ?>
                            </div>

                            <div class="pt-2 border-t border-slate-50 flex items-center justify-between">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">
                                    <?php echo $metricLabel; ?> Hedefi
                                </span>
                                <span class="text-slate-900 font-black text-xs">
                                    <?php echo (int)$item['amount']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="h-full flex flex-col items-center justify-center text-center py-10 opacity-20">
                            <span class="text-3xl mb-2">🍃</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Planlanmadı</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>