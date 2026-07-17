<aside class="w-64 bg-[#1e1b4b] text-slate-300 flex flex-col flex-shrink-0 transition-all duration-300 z-50 h-screen shadow-2xl">

    <div class="h-20 flex items-center px-6 border-b border-indigo-900/50 bg-[#1e1b4b]">
        <h1 class="text-2xl font-black tracking-tight text-white">
            Admin<span class="text-indigo-400">Panel</span>
        </h1>
    </div>

    <nav class="flex-grow py-6 px-3 space-y-2 overflow-y-auto custom-scrollbar">

        <?php $cur = basename($_SERVER['PHP_SELF']); ?>

        <a href="index.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'index.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📊</span>
            <span class="font-bold text-sm tracking-wide">Dashboard</span>
        </a>

        <a href="users.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'users.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">👥</span>
            <span class="font-bold text-sm tracking-wide">Kullanıcılar</span>
        </a>

        <a href="relationships.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'relationships.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">🔗</span>
            <span class="font-bold text-sm tracking-wide">İlişkilendirme</span>
        </a>

        <div class="pt-4 pb-2 px-4">
            <p class="text-xs font-bold text-indigo-400/60 uppercase tracking-wider">İçerik Yönetimi</p>
        </div>

<a href="../eski_admin_canli_ders.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group hover:bg-white/5 hover:text-white">
    <span class="mr-3 text-xl">📹</span>
    <span class="font-bold text-sm tracking-wide">Canlı Dersler</span>
</a>


<a href="teachers.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 hover:bg-white/5 hover:text-white">
    <span class="mr-3 text-xl">👨‍🏫</span>
    <span class="font-bold text-sm tracking-wide">Öğretmen Vitrini</span>
</a>

        <a href="mufredat.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'mufredat.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📚</span>
            <span class="font-bold text-sm tracking-wide">Müfredat & Konu</span>
        </a>

        <a href="education.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'education.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">🗂️</span>
            <span class="font-bold text-sm tracking-wide">Müfredat Yönetimi <span class="text-[9px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded-full ml-1">YENİ</span></span>
        </a>

        <a href="../education_kaynaklar.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group hover:bg-white/5 hover:text-white">
            <span class="mr-3 text-xl">🔗</span>
            <span class="font-bold text-sm tracking-wide">Kaynak Havuzu <span class="text-[9px] bg-emerald-500/20 text-emerald-300 px-1.5 py-0.5 rounded-full ml-1">YENİ</span></span>
        </a>

        <a href="materyal.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'materyal.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📂</span>
            <span class="font-bold text-sm tracking-wide">Kaynaklar</span>
        </a>

        <a href="paketler.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'paketler.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📦</span>
            <span class="font-bold text-sm tracking-wide">Paketler</span>
        </a>

        <a href="../denemeler.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'denemeler.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📝</span>
            <span class="font-bold text-sm tracking-wide">Denemeler</span>
        </a>

        <div class="pt-4 pb-2 px-4">
            <p class="text-xs font-bold text-indigo-400/60 uppercase tracking-wider">Destek</p>
        </div>

        <a href="updates.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'updates.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📋</span>
            <span class="font-bold text-sm tracking-wide">Güncelleme Notları</span>
        </a>

        <a href="features.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'features.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">🚩</span>
            <span class="font-bold text-sm tracking-wide">Özellik Bayrakları</span>
        </a>

        <a href="rank_reference.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'rank_reference.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">📈</span>
            <span class="font-bold text-sm tracking-wide">Sıralama Referansı</span>
        </a>

        <a href="feedback.php" class="flex items-center px-4 py-3 rounded-xl transition-all duration-200 group <?php echo $cur == 'feedback.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/50' : 'hover:bg-white/5 hover:text-white'; ?>">
            <span class="mr-3 text-xl">🐛</span>
            <span class="font-bold text-sm tracking-wide">Geri Bildirimler</span>
            <?php
            try {
                global $pdo;
                if (!isset($pdo)) { require_once '../db.php'; }
                $pendingFb = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='bekliyor'")->fetchColumn();
                if ($pendingFb > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-[10px] font-black px-1.5 py-0.5 rounded-full min-w-[18px] text-center"><?php echo $pendingFb; ?></span>
            <?php endif;
            } catch (Exception $e) {} ?>
        </a>

    </nav>

    <div class="p-4 border-t border-indigo-900/50 bg-[#1e1b4b]">
        <a href="../index.php" class="flex items-center px-4 py-3 text-indigo-300 hover:text-white hover:bg-indigo-900/30 rounded-xl transition duration-200 mb-1">
            <span class="mr-3 text-lg">🏠</span>
            <span class="font-bold text-xs">Siteye Dön</span>
        </a>
        <a href="../logout.php" class="flex items-center px-4 py-3 text-red-400 hover:text-white hover:bg-red-600/20 rounded-xl transition duration-200">
            <span class="mr-3 text-lg">🚪</span>
            <span class="font-bold text-xs">Çıkış Yap</span>
        </a>
    </div>
</aside>
