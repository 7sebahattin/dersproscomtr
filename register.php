<?php
// register.php
require_once 'db.php';
require_once 'mail_helper.php';

// 'parent' rolünü ENUM'a ekle (bir kez çalışır, sessizce başarısız olur)
try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('student','teacher','admin','superuser','parent') DEFAULT 'student'");
} catch (Exception $e) {}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']   ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']  ?? '';
    $password2  = $_POST['password2'] ?? '';

    $allowed_roles = ['student', 'teacher', 'parent'];
    $role = in_array($_POST['role'] ?? '', $allowed_roles) ? $_POST['role'] : 'student';

    // Rol'e göre ek alanlar
    $school_level = null;
    $parent_name  = null;
    $parent_phone = null;
    $branch       = null;
    $bio          = null;

    if ($role === 'student') {
        $lvl = $_POST['school_level'] ?? 'Lise';
        $school_level = in_array($lvl, ['Lise', 'LGS']) ? $lvl : 'Lise';
        $parent_name  = trim($_POST['parent_name']  ?? '');
        $parent_phone = trim($_POST['parent_phone'] ?? '');
    }
    if ($role === 'teacher') {
        $branch = trim(substr($_POST['branch'] ?? '', 0, 100));
        $bio    = trim($_POST['bio'] ?? '');
    }

    // Validasyon
    if (empty($username) || empty($email) || empty($password) || empty($phone) || empty($first_name) || empty($last_name)) {
        $error = "Lütfen tüm zorunlu alanları doldurun.";
    } elseif ($password !== $password2) {
        $error = "Şifreler eşleşmiyor. Lütfen tekrar kontrol edin.";
    } elseif (strlen($password) < 6) {
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($role === 'teacher' && empty($branch)) {
        $error = "Öğretmen hesabı için branş seçimi zorunludur.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $error = "Bu kullanıcı adı veya e-posta zaten kayıtlı.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // === GEÇİCİ: Admin onayı olmadan otomatik aktif kayıt ===
            // Yeni üyeler doğrudan is_active=1 olur (öğrenci ve öğretmen dahil).
            // Onay zorunluluğuna geri dönmek için aşağıdaki VALUES sonundaki 1 -> 0 yapılır.
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, phone, role, school_level, parent_name, parent_phone, branch, bio, is_active, date_joined)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            if ($stmt->execute([$username, $email, $hashed, $first_name, $last_name, $phone, $role, $school_level, $parent_name, $parent_phone, $branch, $bio])) {

                // Admin'lere bildirim maili
                $role_labels = ['student' => 'Öğrenci', 'teacher' => 'Öğretmen', 'parent' => 'Veli'];
                $role_label  = $role_labels[$role];
                $mailBody    = "
                    Sisteme yeni bir üye kaydı oluşturuldu.<br><br>
                    <strong>Ad Soyad:</strong> {$first_name} {$last_name}<br>
                    <strong>Kullanıcı Adı:</strong> {$username}<br>
                    <strong>E-posta:</strong> {$email}<br>
                    <strong>Telefon:</strong> {$phone}<br>
                    <strong>Rol:</strong> {$role_label}<br>
                    " . ($school_level ? "<strong>Seviye:</strong> {$school_level}<br>"         : "") . "
                    " . ($parent_name  ? "<strong>Veli Adı:</strong> {$parent_name}<br>"  : "") . "
                    " . ($parent_phone ? "<strong>Veli Tel:</strong> {$parent_phone}<br>" : "") . "
                    " . ($branch       ? "<strong>Branş:</strong> {$branch}<br>"          : "") . "
                    " . ($bio          ? "<strong>Biyografi:</strong> " . nl2br(htmlspecialchars($bio)) . "<br>" : "") . "
                    <br>Hesap otomatik olarak <strong>aktif</strong> edilmiştir (geçici dönem — onay zorunluluğu kapalı).
                ";

                try {
                    $admins = $pdo->query("SELECT email, first_name, last_name FROM users WHERE role IN ('admin','superuser') AND is_active = 1")->fetchAll();
                    foreach ($admins as $adm) {
                        send_mail_notification(
                            $adm['email'],
                            $adm['first_name'] . ' ' . $adm['last_name'],
                            "🆕 Yeni Üye Kaydı: {$first_name} {$last_name} ({$role_label})",
                            $mailBody
                        );
                    }
                } catch (Exception $e) {}

                $success = "Kaydınız tamamlandı! Hesabınız aktif — <strong>{$username}</strong> kullanıcı adınız ve şifrenizle hemen giriş yapabilirsiniz.";
            } else {
                $error = "Bir hata oluştu, lütfen tekrar deneyin.";
            }
        }
    }
}

include 'header.php';
?>

<style>
    :root {
        --atla-blue-dark:  #223488;
        --atla-blue-light: #314595;
        --atla-orange:     #ec9731;
    }
    .focus-atla:focus {
        outline: none;
        border-color: var(--atla-blue-light);
        box-shadow: 0 0 0 3px rgba(49,69,149,.18);
    }
    .role-card input[type="radio"]:checked + .role-label {
        border-color: var(--atla-blue-dark);
        background-color: #eef1fb;
        color: var(--atla-blue-dark);
    }
    .role-card input[type="radio"]:checked + .role-label .role-icon {
        background-color: var(--atla-blue-dark);
        color: #fff;
    }
    .field-group { transition: all .25s ease; }
</style>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 bg-slate-50">
<div class="max-w-lg w-full bg-white p-8 rounded-3xl shadow-xl border border-slate-100">

    <div class="text-center mb-7">
        <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Aramıza Katılın</h2>
        <p class="mt-2 text-sm text-slate-500">
            Zaten hesabınız var mı? <a href="login.php" class="font-bold text-[#ec9731] hover:underline">Giriş Yapın</a>
        </p>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-5 py-4 rounded-xl mb-6 text-sm font-medium flex items-start gap-3 shadow-sm">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div><?php echo $success; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 px-5 py-3 rounded-xl mb-6 text-sm flex items-start gap-2 shadow-sm">
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" class="space-y-5" id="registerForm">

        <!-- KULLANICI ADI -->
        <div>
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Kullanıcı Adı <span class="text-red-500">*</span></label>
            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   class="mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white">
        </div>

        <!-- AD / SOYAD -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ad <span class="text-red-500">*</span></label>
                <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                       class="js-upper mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Soyad <span class="text-red-500">*</span></label>
                <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                       class="js-upper mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white">
            </div>
        </div>

        <!-- TELEFON / E-POSTA -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Telefon <span class="text-red-500">*</span></label>
                <input type="tel" name="phone" required placeholder="0555 555 55 55" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                       class="mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">E-posta <span class="text-red-500">*</span></label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white">
            </div>
        </div>

        <!-- ŞİFRE / DOĞRULAMA -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Şifre <span class="text-red-500">*</span></label>
                <input type="password" name="password" id="pw1" required minlength="6"
                       class="mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white"
                       oninput="checkPw()">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Şifre Tekrar <span class="text-red-500">*</span></label>
                <input type="password" name="password2" id="pw2" required
                       class="mt-1 block w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-slate-900 text-sm focus-atla transition focus:bg-white"
                       oninput="checkPw()">
            </div>
        </div>
        <p id="pw_hint" class="text-xs hidden mt-1"></p>

        <!-- ROL SEÇİMİ -->
        <div>
            <label class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-2 block">Hesap Türü <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-3 gap-3">

                <label class="role-card cursor-pointer">
                    <input type="radio" name="role" value="student" class="sr-only" <?= (($_POST['role'] ?? 'student') === 'student') ? 'checked' : '' ?> onchange="switchRole('student')">
                    <div class="role-label border-2 border-slate-200 rounded-2xl p-4 text-center transition-all hover:border-[#223488]/40">
                        <div class="role-icon w-10 h-10 mx-auto rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center text-xl mb-2 transition-all">🎒</div>
                        <div class="font-bold text-slate-700 text-sm">Öğrenci</div>
                    </div>
                </label>

                <label class="role-card cursor-pointer">
                    <input type="radio" name="role" value="teacher" class="sr-only" <?= (($_POST['role'] ?? '') === 'teacher') ? 'checked' : '' ?> onchange="switchRole('teacher')">
                    <div class="role-label border-2 border-slate-200 rounded-2xl p-4 text-center transition-all hover:border-[#223488]/40">
                        <div class="role-icon w-10 h-10 mx-auto rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center text-xl mb-2 transition-all">👨‍🏫</div>
                        <div class="font-bold text-slate-700 text-sm">Öğretmen</div>
                    </div>
                </label>

                <label class="role-card cursor-pointer">
                    <input type="radio" name="role" value="parent" class="sr-only" <?= (($_POST['role'] ?? '') === 'parent') ? 'checked' : '' ?> onchange="switchRole('parent')">
                    <div class="role-label border-2 border-slate-200 rounded-2xl p-4 text-center transition-all hover:border-[#223488]/40">
                        <div class="role-icon w-10 h-10 mx-auto rounded-xl bg-slate-100 text-slate-500 flex items-center justify-center text-xl mb-2 transition-all">👪</div>
                        <div class="font-bold text-slate-700 text-sm">Veli</div>
                    </div>
                </label>

            </div>
        </div>

        <!-- ÖĞRENCİ ALANI -->
        <div id="section-student" class="field-group">
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 space-y-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1 block">Seviye <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="school_level" value="Lise" class="sr-only" id="level_lise" <?= (($_POST['school_level'] ?? 'Lise') === 'Lise') ? 'checked' : '' ?>>
                            <div class="level-btn border-2 border-slate-200 bg-white rounded-xl py-2.5 text-center text-sm font-bold text-slate-600 transition-all hover:border-[#223488]/40 peer-checked:border-[#223488]" id="lbl_lise">
                                🏫 Lise
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="school_level" value="LGS" class="sr-only" id="level_lgs" <?= (($_POST['school_level'] ?? '') === 'LGS') ? 'checked' : '' ?>>
                            <div class="level-btn border-2 border-slate-200 bg-white rounded-xl py-2.5 text-center text-sm font-bold text-slate-600 transition-all hover:border-[#223488]/40" id="lbl_lgs">
                                📚 LGS
                            </div>
                        </label>
                    </div>
                </div>
                <!-- VELİ BİLGİLERİ -->
                <div class="bg-orange-50 border border-orange-100 rounded-xl p-3 space-y-2">
                    <p class="text-[11px] font-bold text-orange-700 uppercase tracking-wide flex items-center gap-1.5">
                        👪 Veli Bilgileri <span class="font-normal text-orange-400 normal-case">(isteğe bağlı)</span>
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <input type="text" name="parent_name" placeholder="Veli Adı Soyadı"
                                   value="<?= htmlspecialchars($_POST['parent_name'] ?? '') ?>"
                                   class="js-upper w-full border border-orange-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                        <div>
                            <input type="text" name="parent_phone" placeholder="Veli Telefonu"
                                   value="<?= htmlspecialchars($_POST['parent_phone'] ?? '') ?>"
                                   class="w-full border border-orange-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-orange-300">
                        </div>
                    </div>
                </div>

                <div class="flex items-start gap-2 text-xs text-blue-700 bg-blue-100/60 rounded-xl px-3 py-2.5">
                    <span class="mt-0.5">ℹ️</span>
                    <span>Kaydınız tamamlandıktan sonra <strong>kullanıcı adınızı öğretmeninize iletiniz</strong>. Öğretmeniniz hesabınızı aktif edecektir.</span>
                </div>
            </div>
        </div>

        <!-- ÖĞRETMEN ALANI -->
        <div id="section-teacher" class="field-group hidden">
            <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 space-y-3">
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1 block">Branş <span class="text-red-500">*</span></label>
                    <select name="branch" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 bg-white text-sm focus-atla">
                        <option value="">— Branşınızı seçin —</option>
                        <?php
                        $branches = ['Matematik','Fizik','Kimya','Biyoloji','Türkçe','Edebiyat','Tarih','Coğrafya','Sosyal Bilgiler','İngilizce','Almanca','Fransızca','Felsefe','Rehberlik','Eğitim Koçu','Diğer'];
                        $selBranch = $_POST['branch'] ?? '';
                        foreach ($branches as $b): ?>
                            <option value="<?= $b ?>" <?= $selBranch === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1 block">Kısa Tanıtım / Biyografi</label>
                    <textarea name="bio" rows="3" maxlength="500" placeholder="Kendinizi kısaca tanıtın. Öğrenciler sizi bu bilgiyle tanıyacak..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-2.5 bg-white text-sm resize-none focus-atla"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                    <p class="text-right text-xs text-slate-400 mt-0.5">Max 500 karakter</p>
                </div>
                <div class="flex items-start gap-2 text-xs text-indigo-700 bg-indigo-100/60 rounded-xl px-3 py-2.5">
                    <span class="mt-0.5">ℹ️</span>
                    <span>Başvurunuz alındıktan sonra <strong>üyelik işlemleri için sizinle iletişime geçilecektir.</strong></span>
                </div>
            </div>
        </div>

        <!-- VELİ ALANI -->
        <div id="section-parent" class="field-group hidden">
            <div class="bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 flex items-start gap-2 text-xs text-amber-800">
                <span class="mt-0.5">ℹ️</span>
                <span><strong>Öğretmeninize kullanıcı adınızı ilettiğinizde</strong> üyeliğiniz aktif olacaktır.</span>
            </div>
        </div>

        <!-- GENEL NOT -->
        <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-500 flex items-start gap-2">
            <span>🔒</span>
            <span>Bilgileriniz güvenle saklanmakta ve üçüncü taraflarla paylaşılmamaktadır.</span>
        </div>

        <button type="submit"
                class="w-full py-3.5 px-4 text-sm font-bold rounded-xl text-white shadow-lg shadow-orange-200 transition transform hover:-translate-y-0.5"
                style="background-color:#ec9731" onmouseover="this.style.backgroundColor='#d68625'" onmouseout="this.style.backgroundColor='#ec9731'">
            Kayıt Ol
        </button>
    </form>
    <?php endif; ?>
</div>
</div>

<script>
function switchRole(role) {
    document.getElementById('section-student').classList.toggle('hidden', role !== 'student');
    document.getElementById('section-teacher').classList.toggle('hidden', role !== 'teacher');
    document.getElementById('section-parent').classList.toggle('hidden', role !== 'parent');
}

// Level seçimi görsel güncelleme
document.querySelectorAll('input[name="school_level"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.getElementById('lbl_lise').classList.toggle('border-[#223488]', document.getElementById('level_lise').checked);
        document.getElementById('lbl_lise').classList.toggle('bg-indigo-50',     document.getElementById('level_lise').checked);
        document.getElementById('lbl_lgs').classList.toggle('border-[#223488]',  document.getElementById('level_lgs').checked);
        document.getElementById('lbl_lgs').classList.toggle('bg-indigo-50',      document.getElementById('level_lgs').checked);
    });
});

// Şifre eşleşme kontrolü
function checkPw() {
    var p1 = document.getElementById('pw1').value;
    var p2 = document.getElementById('pw2').value;
    var hint = document.getElementById('pw_hint');
    if (p2.length === 0) { hint.classList.add('hidden'); return; }
    if (p1 === p2) {
        hint.textContent = '✓ Şifreler eşleşiyor';
        hint.className = 'text-xs text-green-600 mt-1';
    } else {
        hint.textContent = '✗ Şifreler eşleşmiyor';
        hint.className = 'text-xs text-red-500 mt-1';
    }
    hint.classList.remove('hidden');
}

// Sayfa yüklenince mevcut seçime göre section'ları göster
(function() {
    var checked = document.querySelector('input[name="role"]:checked');
    if (checked) switchRole(checked.value);

    // Level butonu başlangıç stili
    var lise = document.getElementById('level_lise');
    var lgs  = document.getElementById('level_lgs');
    if (lise && lise.checked) {
        document.getElementById('lbl_lise').classList.add('border-[#223488]','bg-indigo-50');
    }
    if (lgs && lgs.checked) {
        document.getElementById('lbl_lgs').classList.add('border-[#223488]','bg-indigo-50');
    }
})();
</script>

<?php include 'footer.php'; ?>
