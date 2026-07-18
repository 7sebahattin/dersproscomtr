<?php
// sidebar.php
// Sol panel sadece 2+ öğrenci varsa görünsün:
$children = (isset($my_children) && is_array($my_children)) ? $my_children : [];
?>

<?php if (count($children) > 1): ?>
<aside class="w-72 bg-white border-r border-slate-200 flex flex-col flex-shrink-0 z-40 h-screen shadow-xl">

    <div class="h-24 flex items-center px-6 border-b border-slate-100 bg-slate-50/50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg">
                <?php echo strtoupper(substr($_SESSION['username'] ?? 'V', 0, 1)); ?>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-800">Sayın Velimiz</h2>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Veli Paneli</p>
            </div>
        </div>
    </div>

    <div class="flex-grow py-6 px-4 overflow-y-auto">
        <p class="text-xs font-bold text-slate-400 uppercase mb-4 px-2">Öğrencileriniz</p>

        <ul class="space-y-2">
            <?php foreach ($children as $child): ?>
            <li>
                <a href="?student_id=<?php echo $child['id']; ?>"
                   class="flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-200 group border
                   <?php echo (!empty($selected_student) && ($selected_student['id'] ?? null) == ($child['id'] ?? null))
                        ? 'bg-orange-50 border-orange-200 shadow-sm'
                        : 'border-transparent hover:bg-slate-50'; ?>">

                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shadow-sm transition-all
                        <?php echo (!empty($selected_student) && ($selected_student['id'] ?? null) == ($child['id'] ?? null))
                            ? 'bg-orange-500 text-white'
                            : 'bg-white border border-slate-200 text-slate-400 group-hover:border-orange-200 group-hover:text-orange-500'; ?>">
                        <?php echo htmlspecialchars(strtoupper(substr($child['first_name'] ?? '', 0, 1))); ?>
                    </div>

                    <div>
                        <p class="font-bold text-sm text-slate-700 group-hover:text-orange-600 transition-colors">
                            <?php echo htmlspecialchars(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')); ?>
                        </p>
                        <p class="text-[10px] text-slate-400 font-medium">
                            <?php echo $child['school_level'] ?? 'Öğrenci'; ?>
                        </p>
                    </div>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="p-4 border-t border-slate-100 bg-slate-50/50">
        <a href="logout.php"
           class="flex items-center justify-center gap-2 px-4 py-3 text-red-500 bg-white border border-red-100 hover:bg-red-50 hover:border-red-200 rounded-xl transition shadow-sm font-bold text-xs w-full">
            <span>🚪</span> Çıkış Yap
        </a>
    </div>

</aside>
<?php endif; ?>
