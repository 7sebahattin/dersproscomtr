<?php
// users.php - TAM SÜRÜM v2.3 (Giriş Yap İkonu Geri Eklendi)
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
require_once '../db.php';

// Güvenlik
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superuser')) {
    header("Location: ../index.php");
    exit;
}

$message = '';

// --- DEĞİŞKENLER ---
$edit_mode = false;
$id_val = $username_val = $email_val = $fname_val = $lname_val = $phone_val = $expires_val = '';
$role_val = 'student';
$level_val = 'Lise';
$active_val = 1;

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- DÜZENLEME MODU ---
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data) {
        $edit_mode = true;
        $id_val = $user_data['id'];
        $username_val = $user_data['username'];
        $email_val = $user_data['email'];
        $fname_val = $user_data['first_name'];
        $lname_val = $user_data['last_name'];
        $phone_val = $user_data['phone'];
        $role_val = $user_data['role'];
        $level_val = $user_data['school_level'];
        $active_val = $user_data['is_active'];
        $expires_val = $user_data['membership_expires_at'] ? date('Y-m-d\TH:i', strtotime($user_data['membership_expires_at'])) : '';
    }
}

// --- KAYDETME ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_user'])) {
    $post_id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $fname = trim($_POST['first_name']);
    $lname = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $level = $_POST['school_level'] ?? 'Lise';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $expires_at = !empty($_POST['membership_expires_at']) ? $_POST['membership_expires_at'] : null;

    $check_id = $post_id ? $post_id : 0;
    $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $check->execute([$username, $email, $check_id]);

    if ($check->rowCount() > 0) {
        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-xl mb-6'>Hata: Kullanıcı adı/Email çakışması!</div>";
    } else {
        if (!empty($post_id)) {
            // GÜNCELLEME
            $sql = "UPDATE users SET username=?, first_name=?, last_name=?, email=?, phone=?, role=?, school_level=?, is_active=?, membership_expires_at=?";
            $params = [$username, $fname, $lname, $email, $phone, $role, $level, $is_active, $expires_at];

            if (!empty($password)) {
                $sql .= ", password=?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $params[] = $post_id;

            if ($pdo->prepare($sql)->execute($params)) {
                $message = "<div class='bg-blue-50 text-blue-700 p-4 rounded-xl mb-6'>✅ Kullanıcı güncellendi.</div>";
                echo "<script>setTimeout(function(){ window.location.href='users.php?search=".urlencode($search_term)."'; }, 1000);</script>";
            }
        } else {
            // EKLEME
            if (empty($password)) {
                $message = "<div class='bg-red-50 text-red-700 p-4 rounded-xl mb-6'>⚠️ Şifre zorunludur.</div>";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, password, first_name, last_name, email, phone, role, school_level, is_active, membership_expires_at, date_joined)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                if ($pdo->prepare($sql)->execute([$username, $hash, $fname, $lname, $email, $phone, $role, $level, $is_active, $expires_at])) {
                    $message = "<div class='bg-green-50 text-green-700 p-4 rounded-xl mb-6'>✅ Kullanıcı oluşturuldu.</div>";
                }
            }
        }
    }
}

// --- SİLME ---
if (isset($_POST['delete_user'])) {
    if ($_POST['user_id'] == $_SESSION['user_id']) {
        $message = "<div class='bg-red-50 text-red-700 p-4 rounded-xl mb-6'>Kendi hesabınızı silemezsiniz!</div>";
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$_POST['user_id']]);
        $message = "<div class='bg-yellow-50 text-yellow-700 p-4 rounded-xl mb-6'>🗑️ Kullanıcı silindi.</div>";
    }
}

// --- LİSTELEME ---
$sql_query = "SELECT * FROM users WHERE 1=1";
$sql_params = [];
if (!empty($search_term)) {
    $sql_query .= " AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ?)";
    $wildcard = "%$search_term%";
    $sql_params = [$wildcard, $wildcard, $wildcard];
}
$sql_query .= " ORDER BY is_active ASC, role ASC, first_name ASC";
$stmt_list = $pdo->prepare($sql_query);
$stmt_list->execute($sql_params);
$users = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kullanıcı Yönetimi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Poppins', sans-serif; } .custom-scrollbar::-webkit-scrollbar{width:6px;height:6px} .custom-scrollbar::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px}</style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

<?php include 'sidebar.php'; ?>

<main class="flex-grow overflow-y-auto p-8 custom-scrollbar">
    <header class="mb-8 flex justify-between">
        <h1 class="text-3xl font-black text-slate-800">Kullanıcı Yönetimi</h1>
        <?php if($edit_mode): ?>
            <a href="users.php" class="bg-gray-200 px-4 py-2 rounded text-sm font-bold flex items-center gap-2">
                <span>↩️</span> Yeni Ekleme Moduna Dön
            </a>
        <?php endif; ?>
    </header>
    <?php echo $message; ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 h-fit sticky top-8">
            <h2 class="font-bold text-lg mb-4 text-slate-800 border-b pb-3">
                <?php echo $edit_mode ? '✏️ Düzenle' : '➕ Yeni Ekle'; ?>
            </h2>

            <form method="POST" class="space-y-4" autocomplete="off">
                <input type="hidden" name="save_user" value="1">
                <input type="hidden" name="user_id" value="<?php echo $id_val; ?>">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500">Ad</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($fname_val, ENT_QUOTES, 'UTF-8'); ?>" required class="js-upper w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Soyad</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($lname_val, ENT_QUOTES, 'UTF-8'); ?>" required class="js-upper w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500">Kullanıcı Adı</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username_val, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500">E-Posta</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email_val, ENT_QUOTES, 'UTF-8'); ?>" required class="w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="text-xs font-bold text-slate-500">Telefon</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone_val, ENT_QUOTES, 'UTF-8'); ?>" class="w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500 block mb-1">Üyelik Bitiş Tarihi</label>
                    <input type="datetime-local" name="membership_expires_at" value="<?php echo $expires_val; ?>" class="w-full border border-orange-200 bg-orange-50 p-2 rounded-lg text-sm text-orange-800 outline-none">
                    <p class="text-[10px] text-slate-400 mt-1">Boş bırakılırsa sistem giriş izni vermez (Admin hariç).</p>
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-500">Şifre</label>
                    <input type="text" name="password" placeholder="<?php echo $edit_mode ? 'Boş bırakırsan değişmez' : 'Şifre girin'; ?>" class="w-full border p-2 rounded-lg text-sm focus:border-indigo-500 outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3 items-center">
                    <div>
                        <label class="text-xs font-bold text-slate-500">Rol</label>
                        <select name="role" class="w-full border p-2 rounded-lg text-sm bg-white">
                            <option value="student" <?php echo ($role_val=='student')?'selected':''; ?>>Öğrenci</option>
                            <option value="teacher" <?php echo ($role_val=='teacher')?'selected':''; ?>>Öğretmen</option>
                            <option value="parent" <?php echo ($role_val=='parent')?'selected':''; ?>>Veli</option>
                            <option value="admin" <?php echo ($role_val=='admin')?'selected':''; ?>>Yönetici</option>
                        </select>
                    </div>
                    <div class="flex items-center pt-4">
                        <input type="checkbox" name="is_active" id="is_active" <?php echo ($active_val==1)?'checked':''; ?> class="w-5 h-5 text-indigo-600 rounded">
                        <label for="is_active" class="ml-2 text-sm font-bold text-slate-700 cursor-pointer">Hesap Onaylı</label>
                    </div>
                </div>

                <button class="w-full bg-slate-800 text-white font-bold py-3 rounded-xl hover:bg-slate-900 transition shadow-lg">
                    <?php echo $edit_mode ? 'Değişiklikleri Kaydet' : 'Kullanıcı Oluştur'; ?>
                </button>
            </form>
        </div>

        <div class="xl:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col h-[800px]">

             <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-3">
                <h2 class="font-bold">Kullanıcılar (<?php echo count($users); ?>)</h2>
                <form class="flex gap-2 w-full md:w-auto">
                    <input type="text" name="search" value="<?php echo $search_term; ?>" placeholder="Ara..." class="border p-2 rounded-lg text-sm w-full outline-none focus:border-indigo-500">
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">Ara</button>
                    <?php if(!empty($search_term)): ?>
                        <a href="users.php" class="bg-red-100 text-red-600 px-3 py-2 rounded-lg font-bold hover:bg-red-200">X</a>
                    <?php endif; ?>
                </form>
             </div>

             <div class="overflow-y-auto custom-scrollbar flex-grow pr-2">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 font-bold text-slate-500 text-xs sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="p-3 rounded-tl-lg">Kullanıcı</th>
                            <th class="p-3">Durum</th>
                            <th class="p-3">Bitiş Tarihi</th>
                            <th class="p-3 text-center">Giriş</th> <th class="p-3 text-right rounded-tr-lg">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($users as $u): ?>
                            <tr class="<?php echo ($edit_mode && $id_val == $u['id']) ? 'bg-indigo-50' : 'hover:bg-slate-50'; ?> transition">
                                <td class="p-3">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></div>
                                    <div class="text-xs text-slate-400">@<?php echo $u['username']; ?> | <span class="uppercase"><?php echo $u['role']; ?></span></div>
                                </td>
                                <td class="p-3">
                                    <?php if($u['is_active'] == 1): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded-md text-xs font-bold">Aktif</span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded-md text-xs font-bold animate-pulse">Onay Bekliyor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <?php
                                        if(!$u['membership_expires_at']) {
                                            echo '<span class="text-slate-400 text-xs italic">Süresiz / Yok</span>';
                                        } else {
                                            $exp = strtotime($u['membership_expires_at']);
                                            $is_expired = time() > $exp;
                                            $color = $is_expired ? 'text-red-600 font-bold' : 'text-slate-600';
                                            echo "<span class='$color text-xs'>".date('d.m.Y', $exp)."</span>";
                                            if($is_expired) echo " <span class='text-[10px] bg-red-100 text-red-600 px-1 rounded ml-1'>Süre Doldu</span>";
                                        }
                                    ?>
                                </td>

                                <td class="p-3 text-center">
                                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="login_as.php?id=<?php echo $u['id']; ?>" target="_blank" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition shadow-sm border border-indigo-100" title="Bu kullanıcı olarak giriş yap">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-300 italic">Siz</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <div class="flex gap-1 justify-end">
                                        <a href="?edit_id=<?php echo $u['id']; ?>&search=<?php echo urlencode($search_term); ?>" class="text-blue-600 bg-blue-50 hover:bg-blue-100 p-2 rounded transition" title="Düzenle">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                                            <input type="hidden" name="delete_user" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button class="text-red-600 bg-red-50 hover:bg-red-100 p-2 rounded transition" title="Sil">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
             </div>
        </div>
    </div>
</main>
</body>
</html>
