<?php
// student/bildirimler.php — Öğrenci Bildirim Geçmişi

$dbPath     = __DIR__ . '/../db.php';
$headerPath = __DIR__ . '/../header.php';
$footerPath = __DIR__ . '/../footer.php';

require_once $dbPath;

// ── is_read kolonu yoksa ekle (tek seferlik migrasyon) ──────────────────────
try {
    $pdo->exec("ALTER TABLE push_notification_log ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) { /* Zaten varsa hata vermez */ }

include $headerPath;

if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='../login.php';</script>"; exit;
}

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Öğrenci veya koçu tarafından görüntülenen öğrenci
$student_id = $uid;
if ($role === 'teacher' && isset($_GET['student_id'])) {
    $sid = (int)$_GET['student_id'];
    $chk = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id=? AND student_id=?");
    $chk->execute([$uid, $sid]);
    if ($chk->fetchColumn()) $student_id = $sid;
}

// ── Sayfaya girince öğrencinin kendi bildirimleri okundu işaretle ────────────
// (sadece kendi profiline bakarken, koç başkasınınkileri görüntülüyorsa sayaç değişmesin)
if ($role === 'student' && $student_id === $uid) {
    try {
        $pdo->prepare("UPDATE push_notification_log SET is_read=1 WHERE student_id=? AND is_read=0")
            ->execute([$uid]);
    } catch (Throwable $e) {}
}

// Öğrenci adı
$nameRow = $pdo->prepare("SELECT first_name, last_name, push_notifications_enabled FROM users WHERE id=?");
$nameRow->execute([$student_id]);
$sName = $nameRow->fetch();
$studentName  = $sName ? ($sName['first_name'] . ' ' . $sName['last_name']) : 'Öğrenci';
$pushEnabled  = (int)($sName['push_notifications_enabled'] ?? 1);

// Bildirim geçmişini çek
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$notifications = [];
$totalCount    = 0;

// Toplam sayı
try {
    $total = $pdo->prepare("SELECT COUNT(*) FROM push_notification_log WHERE student_id=?");
    $total->execute([$student_id]);
    $totalCount = (int)$total->fetchColumn();
} catch (Throwable $e) { $totalCount = 0; }

// title/body kolonları var mı kontrol et
$hasContentCols = false;
try {
    $colChk = $pdo->query("SHOW COLUMNS FROM push_notification_log LIKE 'title'");
    $hasContentCols = ($colChk->rowCount() > 0);
} catch (Throwable $e) {}

// Bildirimleri çek
if ($totalCount > 0) {
    try {
        $selectCols = $hasContentCols
            ? "notification_type, scheduled_date, sent_at, title, body"
            : "notification_type, scheduled_date, sent_at, NULL AS title, NULL AS body";

        $stmt = $pdo->prepare("
            SELECT $selectCols
            FROM push_notification_log
            WHERE student_id = ?
            ORDER BY sent_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt->execute([$student_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $notifications = [];
    }
}

$totalPages = max(1, ceil($totalCount / $perPage));

// Bildirim tipine göre ikon ve renk
function notifStyle(string $type): array {
    if (str_starts_with($type, 'A_')) {
        return ['icon' => '🚪', 'color' => 'bg-red-50 border-red-100',    'badge' => 'bg-red-100 text-red-700',    'label' => 'Giriş Hatırlatması'];
    }
    if (str_starts_with($type, 'B_22_completed')) {
        return ['icon' => '🎉', 'color' => 'bg-green-50 border-green-100', 'badge' => 'bg-green-100 text-green-700', 'label' => 'Tamamlandı'];
    }
    if (str_starts_with($type, 'B_')) {
        return ['icon' => '📋', 'color' => 'bg-amber-50 border-amber-100', 'badge' => 'bg-amber-100 text-amber-700', 'label' => 'Görev Uyarısı'];
    }
    if (str_starts_with($type, 'C_')) {
        return ['icon' => '📌', 'color' => 'bg-blue-50 border-blue-100',   'badge' => 'bg-blue-100 text-blue-700',  'label' => 'Görev Zamanı'];
    }
    return     ['icon' => '🔔', 'color' => 'bg-slate-50 border-slate-100', 'badge' => 'bg-slate-100 text-slate-600','label' => 'Bildirim'];
}

$B = BASE_URL;
?>

<div class="max-w-2xl mx-auto px-4 py-8 min-h-screen">

    <!-- Başlık -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-black text-slate-800">🔔 Bildirim Geçmişi</h1>
            <p class="text-sm text-slate-400 mt-0.5">
                <?php echo htmlspecialchars($studentName); ?> &nbsp;·&nbsp; Son bildirimlerin
            </p>
        </div>
        <span class="text-xs font-bold px-3 py-1.5 rounded-full bg-indigo-100 text-indigo-700">
            <?php echo $totalCount; ?> bildirim
        </span>
    </div>

    <!-- Bildirim Toggle (sadece kendi profilini görüntüleyen öğrenci) -->
    <?php if ($role === 'student' && $student_id === $uid): ?>
    <div class="mb-5 p-4 bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-lg">🔔</span>
                <div>
                    <p class="text-sm font-bold text-slate-700">Anlık Bildirimler</p>
                    <p class="text-xs text-slate-400">Görev hatırlatmalarını tarayıcıdan al.</p>
                </div>
            </div>
            <label id="pushToggleLabel" class="flex items-center gap-3 cursor-pointer flex-shrink-0">
                <div class="relative inline-block w-12 h-6 select-none">
                    <input type="checkbox" id="pushToggle"
                           class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer"
                           <?php echo $pushEnabled ? 'checked' : ''; ?>>
                    <span class="toggle-label block w-full h-full rounded-full bg-slate-300"></span>
                </div>
                <span id="pushToggleText" class="text-sm font-bold <?php echo $pushEnabled ? 'text-indigo-600' : 'text-slate-400'; ?>">
                    <?php echo $pushEnabled ? 'Açık' : 'Kapalı'; ?>
                </span>
            </label>
        </div>
        <div id="pushStatusMsg" class="mt-2 text-xs font-medium hidden"></div>
        <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-400">Bildirimlerin çalışıp çalışmadığını hemen test et.</p>
            <button id="selfTestBtn" type="button"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition whitespace-nowrap">
                🔔 Kendime Test Gönder
            </button>
        </div>
        <div id="selfTestResult" class="hidden mt-2 text-xs rounded-xl border p-3"></div>
    </div>
    <style>
        .toggle-checkbox:checked { right: 0; border-color: #4f46e5; }
        .toggle-checkbox:checked + .toggle-label { background-color: #4f46e5; }
        .toggle-checkbox { right: 50%; border-color: #cbd5e1; transition: all 0.3s; top: 0; }
    </style>
    <script>
    (function() {
        const toggle  = document.getElementById('pushToggle');
        const label   = document.getElementById('pushToggleText');
        const msgEl   = document.getElementById('pushStatusMsg');
        const BASE    = '<?php echo $B; ?>';

        if (!toggle) return;

        function showMsg(txt, ok) {
            msgEl.textContent = txt;
            msgEl.className   = 'mt-2 text-xs font-medium ' + (ok ? 'text-green-600' : 'text-red-500');
            msgEl.classList.remove('hidden');
            setTimeout(() => msgEl.classList.add('hidden'), 3000);
        }

        // ── Kendime test bildirimi (TEŞHİS) ──
        const selfTestBtn = document.getElementById('selfTestBtn');
        const selfTestBox = document.getElementById('selfTestResult');
        if (selfTestBtn) {
            selfTestBtn.addEventListener('click', async function() {
                selfTestBtn.disabled = true;
                const oldTxt = selfTestBtn.textContent;
                selfTestBtn.textContent = '⏳ Gönderiliyor...';
                selfTestBox.className = 'mt-2 text-xs rounded-xl border p-3 bg-slate-50 border-slate-200 text-slate-500';
                selfTestBox.classList.remove('hidden');
                selfTestBox.textContent = 'Test bildirimi gönderiliyor...';
                let subNote = '';
                try {
                    // 1) Abonelik henüz kurulmamışsa önce kurmayı dene (izin verilmişse)
                    //    Bu adım fetch'ten AYRI try ile sarıldı ki abonelik hatası
                    //    "bağlantı hatası" gibi görünmesin — gerçek sebebi gösterelim.
                    try {
                        if ('serviceWorker' in navigator && 'PushManager' in window && Notification.permission === 'granted') {
                            const reg = await navigator.serviceWorker.ready;
                            let sub = await reg.pushManager.getSubscription();
                            if (!sub && (window.__VAPID_PUB__ || '')) {
                                const v = window.__VAPID_PUB__;
                                const pad = '='.repeat((4 - v.length % 4) % 4);
                                const b64 = (v + pad).replace(/-/g,'+').replace(/_/g,'/');
                                const raw = atob(b64);
                                const key = Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));
                                sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
                                await fetch(BASE + '/ajax/push_subscribe.php', {
                                    method:'POST', headers:{'Content-Type':'application/json'},
                                    body: JSON.stringify({ subscription: sub.toJSON(), action:'subscribe' })
                                });
                            }
                        } else if (Notification.permission !== 'granted') {
                            subNote = '⚠️ Tarayıcı bildirim izni verilmemiş (' + Notification.permission + ').';
                        }
                    } catch (subErr) {
                        subNote = '⚠️ Abonelik kurulamadı: ' + (subErr && subErr.message ? subErr.message : subErr)
                                + ' (eski VAPID anahtarıyla kayıtlı abonelik olabilir).';
                    }

                    // 2) Sunucudan test push'u iste — ham yanıtı yakala
                    const res = await fetch(BASE + '/ajax/push_test.php', {
                        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({})
                    });
                    const rawText = await res.text();
                    let data;
                    try { data = JSON.parse(rawText); }
                    catch(pe) { throw new Error('Sunucu JSON döndürmedi (HTTP ' + res.status + '). Yanıt: ' + rawText.slice(0, 400)); }

                    const okCls   = 'bg-green-50 border-green-200 text-green-700';
                    const failCls = 'bg-red-50 border-red-200 text-red-700';
                    selfTestBox.className = 'mt-2 text-xs rounded-xl border p-3 break-words ' + (data.ok ? okCls : failCls);
                    let html = '<p class="font-bold">' + (data.ok ? '✅ ' : '⚠️ ') + (data.msg || '') + '</p>';
                    if (subNote) html += '<p class="text-amber-700 mt-1">' + subNote + '</p>';
                    if (typeof data.device_count !== 'undefined') {
                        html += '<p class="text-slate-500 mt-1">Kayıtlı cihaz: <b>' + data.device_count + '</b></p>';
                    }
                    if (data.results && data.results.length) {
                        html += '<ul class="mt-1 space-y-0.5">';
                        data.results.forEach(r => {
                            html += '<li>' + (r.ok ? '✅' : '❌') + ' ' + r.device
                                 + (r.http ? ' (HTTP ' + r.http + ')' : '') + ' — ' + r.detail + '</li>';
                        });
                        html += '</ul>';
                    }
                    selfTestBox.innerHTML = html;
                } catch(e) {
                    selfTestBox.className = 'mt-2 text-xs rounded-xl border p-3 bg-red-50 border-red-200 text-red-700 break-words';
                    selfTestBox.innerHTML = '<p class="font-bold">Hata: ' + (e && e.message ? e.message : 'test gönderilemedi.') + '</p>'
                                          + (subNote ? '<p class="text-amber-700 mt-1">' + subNote + '</p>' : '');
                } finally {
                    selfTestBtn.disabled = false;
                    selfTestBtn.textContent = oldTxt;
                }
            });
        }

        toggle.addEventListener('change', async function() {
            const enabled = this.checked ? 1 : 0;
            label.textContent = enabled ? 'Açık' : 'Kapalı';
            label.className   = 'text-sm font-bold ' + (enabled ? 'text-indigo-600' : 'text-slate-400');

            try {
                const res  = await fetch(BASE + '/ajax/push_toggle.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ enabled })
                });
                const data = await res.json();

                if (data.ok) {
                    if (enabled && 'serviceWorker' in navigator && 'PushManager' in window) {
                        const reg  = await navigator.serviceWorker.ready;
                        const perm = await Notification.requestPermission();
                        if (perm === 'granted') {
                            const vapidKey = window.__VAPID_PUB__ || '';
                            if (vapidKey) {
                                const padding = '='.repeat((4 - vapidKey.length % 4) % 4);
                                const b64     = (vapidKey + padding).replace(/-/g,'+').replace(/_/g,'/');
                                const raw     = atob(b64);
                                const key     = Uint8Array.from([...raw].map(c=>c.charCodeAt(0)));
                                const sub     = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
                                await fetch(BASE + '/ajax/push_subscribe.php', {
                                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                                    body:   JSON.stringify({ subscription: sub.toJSON(), action: 'subscribe' })
                                });
                            }
                            showMsg('Bildirimler açıldı. ✅', true);
                        } else {
                            showMsg('Tarayıcı izni reddedildi. Ayarlardan açabilirsiniz.', false);
                            toggle.checked = false;
                            label.textContent = 'Kapalı';
                            label.className = 'text-sm font-bold text-slate-400';
                        }
                    } else {
                        showMsg(enabled ? 'Bildirimler açıldı. ✅' : 'Bildirimler kapatıldı.', true);
                    }
                } else {
                    showMsg('Hata: ' + (data.msg || 'Bilinmeyen'), false);
                }
            } catch(e) {
                showMsg('Bağlantı hatası.', false);
            }
        });
    })();
    </script>
    <?php endif; ?>

    <?php if (empty($notifications)): ?>
    <div class="text-center py-20 bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="text-5xl mb-3">🔕</div>
        <p class="text-slate-500 font-bold">Henüz bildirim yok</p>
        <p class="text-xs text-slate-400 mt-1">Sistem bildirim gönderdiğinde burada görünecek.</p>
    </div>
    <?php else: ?>

    <div class="space-y-3">
        <?php foreach ($notifications as $n):
            $s    = notifStyle($n['notification_type']);
            $date = $n['sent_at'] ? date('d.m.Y H:i', strtotime($n['sent_at'])) : '';
            $title = $n['title'] ?? '';
            $body  = $n['body']  ?? '';
            if (!$title) {
                $title = $s['label'];
                $body  = 'Bu bildirim içeriği kayıt altına alınmadan önce gönderilmiş.';
            }
        ?>
        <div class="flex gap-3 p-4 rounded-2xl border <?php echo $s['color']; ?> shadow-sm">
            <div class="text-2xl flex-shrink-0 mt-0.5"><?php echo $s['icon']; ?></div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                    <span class="font-bold text-sm text-slate-800 leading-tight"><?php echo htmlspecialchars($title); ?></span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $s['badge']; ?> flex-shrink-0 whitespace-nowrap"><?php echo $s['label']; ?></span>
                </div>
                <?php if ($body): ?>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($body); ?></p>
                <?php endif; ?>
                <p class="text-[10px] text-slate-400 mt-2 font-medium"><?php echo $date; ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sayfalama -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-6">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?><?php echo $role==='teacher'?"&student_id=$student_id":''; ?>"
               class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition">← Önceki</a>
        <?php endif; ?>
        <span class="text-xs text-slate-400 font-bold"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo $role==='teacher'?"&student_id=$student_id":''; ?>"
               class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition">Sonraki →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>


</div>

<?php include $footerPath; ?>
