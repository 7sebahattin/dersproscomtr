<div id="content-duyuru" class="tab-content block animate-fadeIn">
    <div class="grid grid-cols-1 gap-4">
        <?php foreach ($announcements as $ann): ?>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md transition-all group relative overflow-hidden">
            <div class="absolute top-0 left-0 w-1 h-full bg-orange-500 group-hover:w-2 transition-all"></div>
            <div class="flex justify-between items-start mb-2 pl-2">
                <h3 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($ann['title']); ?></h3>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded-full border border-slate-100">
                    <?php echo date('d.m.Y', strtotime($ann['created_at'])); ?>
                </span>
            </div>
            <p class="text-xs text-slate-500 leading-relaxed pl-2"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($announcements)): ?>
            <div class="bg-white p-10 rounded-2xl shadow-sm border border-slate-200 text-center">
                <div class="text-4xl mb-3 opacity-50">📭</div>
                <p class="text-slate-400 text-sm font-bold">Henüz yayınlanmış bir duyuru yok.</p>
            </div>
        <?php endif; ?>
    </div>
</div>