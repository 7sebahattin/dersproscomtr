<?php
// profil.php - TAM SÜRÜM (Görünürlük Ayarı Eklendi)
require_once 'db.php';
include 'header.php';

// Güvenlik
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// --- FORM GÖNDERİLDİ Mİ? ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    
    // Öğretmenlere özel alanlar
    $branch = isset($_POST['branch']) ? $_POST['branch'] : null;
    $bio    = isset($_POST['bio']) ? $_POST['bio'] : null;
    
    // YENİ: Görünürlük Ayarı (Checkbox işaretliyse 1, değilse 0)
    // Eğer post içinde is_public yoksa (checkbox işaretli değilse) 0 olur.
    $is_public = isset($_POST['is_public_instructor']) ? 1 : 0;

    // 1. FOTOĞRAF YÜKLEME İŞLEMİ
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = "profile_" . $user_id . "_" . time() . "." . $ext;
            $upload_dir = 'uploads/profiles/';
            
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $new_name)) {
                $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?")->execute([$upload_dir . $new_name, $user_id]);
            }
        } else {
            $message = "Hata: Sadece JPG, PNG ve WEBP formatları kabul edilir.";
        }
    }

    // 2. TEMEL BİLGİLERİ GÜNCELLE
    if ($_SESSION['role'] == 'teacher') {
        // Öğretmense: is_public_instructor alanını da güncelle
        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=?, branch=?, bio=?, is_public_instructor=? WHERE id=?";
        $params = [$first_name, $last_name, $email, $phone, $branch, $bio, $is_public, $user_id];
    } else {
        // Öğrenciyse: Sadece temel bilgiler (Görünürlük ayarı yok)
        $sql = "UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?";
        $params = [$first_name, $last_name, $email, $phone, $user_id];
    }

    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        if(empty($message)) $message = "Profiliniz başarıyla güncellendi! ✅";
        $_SESSION['first_name'] = $first_name; // Session'ı tazele
    } else {
        $message = "Veritabanı güncelleme hatası.";
    }
}

// Güncel bilgileri çek
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$is_teacher = ($user['role'] == 'teacher');
?>

<div class="max-w-4xl mx-auto py-12 px-4 sm:px-6 lg:px-8 min-h-screen">
    
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-black text-slate-800">Profil Ayarları</h1>
        <p class="text-slate-500 mt-2">Kişisel bilgilerinizi ve tercihlerinizi yönetin.</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm font-medium animate-pulse">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-xl border border-indigo-50 overflow-hidden">
        <div class="p-8 sm:p-10">
            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                
                <div class="flex flex-col items-center justify-center mb-8">
                    <div class="relative group cursor-pointer">
                        <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-indigo-100 shadow-lg bg-slate-50">
                            <?php if (!empty($user['photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($user['photo_path']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-indigo-50 text-indigo-300 text-4xl font-bold">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="absolute inset-0 bg-black/40 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                            <span class="text-white text-xs font-bold">📷 Değiştir</span>
                        </div>
                        
                        <input type="file" name="photo" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" title="Fotoğrafı değiştirmek için tıklayın">
                    </div>
                    <p class="text-xs text-slate-400 mt-3 font-medium">Fotoğrafı değiştirmek için üzerine tıklayın</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Adınız</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-slate-50 focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Soyadınız</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-slate-50 focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">E-posta Adresi</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-slate-50 focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Telefon</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="0555 555 55 55" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-slate-50 focus:bg-white">
                    </div>
                </div>


                <?php if($is_teacher): ?>
                <div class="bg-indigo-50/50 p-6 rounded-2xl border border-indigo-100 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">👨‍🏫</span>
                            <h3 class="font-bold text-indigo-900">Eğitmen Bilgileri</h3>
                        </div>

                        <label for="is_public" class="flex items-center gap-3 bg-white px-4 py-2 rounded-lg border border-indigo-100 shadow-sm cursor-pointer">
                            <span class="text-sm font-bold text-slate-600 select-none whitespace-nowrap">Eğitmen Sayfasında Görün</span>
                            <div class="relative inline-block w-12 h-6 flex-shrink-0 select-none">
                                <input type="checkbox" name="is_public_instructor" id="is_public" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer" <?php echo ($user['is_public_instructor'] == 1) ? 'checked' : ''; ?>>
                                <span class="toggle-label block w-full h-full rounded-full bg-slate-300"></span>
                            </div>
                        </label>
                    </div>
                    
                    <style>
                        .toggle-checkbox:checked { right: 0; border-color: #4f46e5; }
                        .toggle-checkbox:checked + .toggle-label { background-color: #4f46e5; }
                        .toggle-checkbox { right: 50%; border-color: #cbd5e1; transition: all 0.3s; top: 0; }
                    </style>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Branş / Ders</label>
                        <select name="branch" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none bg-white cursor-pointer">
                            <?php 
                            $branches = ['Matematik','Fizik','Kimya','Biyoloji','Türkçe','Tarih','Coğrafya','Sosyal Bilgiler','İngilizce','Rehberlik','Eğitim Koçu','Diğer'];
                            foreach($branches as $b) {
                                $sel = ($user['branch'] == $b) ? 'selected' : '';
                                echo "<option value='$b' $sel>$b</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Kısa Tanıtım (Biyografi)</label>
                        <textarea name="bio" rows="4" placeholder="Öğrencileriniz sizi vitrinde bu yazı ile tanıyacak..." class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 outline-none bg-white"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <p class="text-xs text-slate-400 mt-2 text-right">Kısa ve etkileyici bir tanıtım yazın.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
                    <a href="index.php" class="text-slate-500 font-bold hover:text-slate-800 transition px-4">İptal</a>
                    <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-0.5 transition transform">
                        💾 Değişiklikleri Kaydet
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<?php include 'footer.php'; ?>