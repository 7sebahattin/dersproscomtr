<div id="content-rapor" class="tab-content hidden animate-fadeIn">
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-indigo-100 flex items-center gap-4 hover:shadow-md transition group">
            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl group-hover:scale-110 transition">❓</div>
            <div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Toplam Soru</p><h3 class="text-2xl font-black text-slate-800"><?php echo number_format($report_stats['total_q']); ?></h3></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-yellow-100 flex items-center gap-4 hover:shadow-md transition group">
            <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center text-xl group-hover:scale-110 transition">📖</div>
            <div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Toplam Konu</p><h3 class="text-2xl font-black text-slate-800"><?php echo number_format($report_stats['total_t']); ?></h3></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-green-100 flex items-center gap-4 hover:shadow-md transition group">
            <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-xl group-hover:scale-110 transition">🚀</div>
            <div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Bu Hafta Soru</p><h3 class="text-2xl font-black text-green-600">+<?php echo number_format($report_stats['week_q']); ?></h3></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-blue-100 flex items-center gap-4 hover:shadow-md transition group">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl group-hover:scale-110 transition">📊</div>
            <div><p class="text-slate-400 text-[10px] font-bold uppercase tracking-wider">Bu Hafta Konu</p><h3 class="text-2xl font-black text-blue-600">+<?php echo number_format($report_stats['week_t']); ?></h3></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 relative overflow-hidden flex flex-col">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg">
                        <span class="text-indigo-600">⚡</span> Soru Çözüm Hacmi
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Geçmiş birikim ve bu haftanın katkısı</p>
                </div>
                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[10px] font-bold uppercase">Hacim Analizi</span>
            </div>
            <div class="flex-grow h-[300px] w-full">
                <canvas id="qChart"></canvas>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 relative overflow-hidden flex flex-col">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="font-bold text-slate-800 flex items-center gap-2 text-lg">
                        <span class="text-yellow-500">🎓</span> Konu İlerlemesi
                    </h3>
                    <p class="text-xs text-slate-400 mt-1">Toplam konu ve haftalık çalışma dengesi</p>
                </div>
                <span class="px-3 py-1 bg-yellow-50 text-yellow-600 rounded-lg text-[10px] font-bold uppercase">Verimlilik Analizi</span>
            </div>
            <div class="flex-grow h-[300px] w-full">
                <canvas id="tChart"></canvas>
            </div>
        </div>

    </div>
</div>