<?php
// koc_paneli.php - HATASIZ SÜRÜM (Ders Filtreleme ve Kopyalama Düzeltildi)
ob_start(); // Çıktı tamponlamayı başlat (JSON hatalarını önler)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'education_lib.php';
include 'header.php';

// Yeni müfredat sistemi şeması + schedule_items.edu_topic_id garanti (idempotent)
try {
    education_ensure_schema($pdo);
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'edu_topic_id'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN edu_topic_id INT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE schedule_items ADD KEY idx_si_edu_topic (edu_topic_id)");
    }
    // Kaynaktan seçilen görevlerde kaynak adını kartta göstermek için (additive, silinmez)
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'resource_title'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN resource_title VARCHAR(255) NULL DEFAULT NULL");
    }
    // ── VİDEO GÖREV desteği (additive) ──
    // action_type ENUM'una 'video' değeri: mevcut soru/konu verisi hiç etkilenmez
    $atCol = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'action_type'")->fetch(PDO::FETCH_ASSOC);
    if ($atCol && stripos($atCol['Type'] ?? '', 'video') === false) {
        $pdo->exec("ALTER TABLE schedule_items MODIFY action_type ENUM('soru','konu','video') DEFAULT 'soru'");
    }
    // Kaynak bağı (video URL/tipi render'da canlı JOIN ile gelir) + görev kısa notu
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'resource_id'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN resource_id INT NULL DEFAULT NULL");
    }
    if ($pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'task_note'")->rowCount() === 0) {
        $pdo->exec("ALTER TABLE schedule_items ADD COLUMN task_note VARCHAR(255) NULL DEFAULT NULL");
    }
} catch (Throwable $e) { /* yeni sistem yoksa eski akış çalışmaya devam eder */ }

// 1. GÜVENLİK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

$teacher_id = $_SESSION['user_id'];
$user_role = 'teacher'; 
$sid = $_GET['student_id'] ?? null;
$message = '';
$error = '';

$exam_results = [];
$progress_data = [];
$schedule_items = [];
$my_students = [];
$selected_student = null;
$report_stats = ['total_q' => 0, 'total_t' => 0, 'week_q' => 0, 'week_t' => 0];

// 2. VERİTABANI GÜNCELLEMELERİ
try {
    $checkTime = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'time_note'");
    if($checkTime->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN time_note VARCHAR(20) DEFAULT NULL");

    $checkParent = $pdo->query("SHOW COLUMNS FROM users LIKE 'parent_name'");
    if($checkParent->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN parent_name VARCHAR(100) DEFAULT NULL AFTER phone");
        $pdo->exec("ALTER TABLE users ADD COLUMN parent_phone VARCHAR(20) DEFAULT NULL AFTER parent_name");
    }

    $checkOrder = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'item_order'");
    if($checkOrder->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN item_order INT DEFAULT 0");

    $checkLevel = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_level'");
    if($checkLevel->rowCount() == 0) $pdo->exec("ALTER TABLE users ADD COLUMN school_level VARCHAR(20) DEFAULT 'Lise' AFTER role");

    $checkTarget = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'target_amount'");
    if($checkTarget->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN target_amount INT DEFAULT NULL");

    $checkCorrect = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'correct_count'");
    if($checkCorrect->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN correct_count INT DEFAULT NULL");

    $checkWrong = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE 'wrong_count'");
    if($checkWrong->rowCount() == 0) $pdo->exec("ALTER TABLE schedule_items ADD COLUMN wrong_count INT DEFAULT NULL");

} catch (PDOException $e) {}

// 3. TARİH AYARLARI
$date_param = $_GET['date'] ?? date('Y-m-d');
$week_start = $date_param; 

// Hafta İleri/Geri
$prev_week = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
$next_week = date('Y-m-d', strtotime('+1 week', strtotime($week_start)));

// ✅ EKLENEN KISIM: Gün İleri/Geri ve Bugün
$prev_day  = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));
$next_day  = date('Y-m-d', strtotime('+1 day', strtotime($week_start)));
$today_date = date('Y-m-d');

$week_dates = [];
for($i=0; $i<7; $i++) { 
    $week_dates[] = date('Y-m-d', strtotime("+$i days", strtotime($week_start))); 
}
$gunlerTR = ['Sunday'=>'Pazar','Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi'];


// ✅ AJAX İŞLEMLERİ (JSON Yanıt Garantili)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    // Önceki tüm çıktıları temizle (Hata mesajları JSON'u bozmasın)
    ob_end_clean(); 
    header('Content-Type: application/json; charset=utf-8');

    if (!$sid) { echo json_encode(['ok' => false, 'error' => 'Öğrenci seçilmemiş.']); exit; }

    try {
        $rel = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ? LIMIT 1");
        $rel->execute([$teacher_id, $sid]);
        if (!$rel->fetchColumn()) { echo json_encode(['ok' => false, 'error' => 'Yetkisiz işlem.']); exit; }
    } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Yetki hatası.']); exit; }

    $action = $_POST['ajax'];
    $itemId = (int)($_POST['schedule_id'] ?? $_POST['item_id'] ?? 0);
    $toDate = $_POST['to_date'] ?? $_POST['new_date'] ?? '';

    // A. TAŞIMA
    if ($action === 'move_schedule') {
        try {
            $up = $pdo->prepare("UPDATE schedule_items SET date = ? WHERE id = ? AND student_id = ?");
            $up->execute([$toDate, $itemId, $sid]);
            echo json_encode(['ok' => true]); exit;
        } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Taşıma hatası.']); exit; }
    }

    // B. KOPYALAMA
    if ($action === 'copy_schedule') {
        try {
            $get = $pdo->prepare("SELECT * FROM schedule_items WHERE id = ? AND student_id = ? LIMIT 1");
            $get->execute([$itemId, $sid]);
            $row = $get->fetch(PDO::FETCH_ASSOC);

            if (!$row) { echo json_encode(['ok' => false, 'error' => 'Kayıt bulunamadı.']); exit; }

            $copyCols = ['student_id','date','amount','action_type','status','topic_id','custom_subject','custom_topic','time_note'];
            $copyVals = [$sid, $toDate, $row['amount'], $row['action_type'], $row['status'],
                         $row['topic_id'], $row['custom_subject'], $row['custom_topic'], $row['time_note']];
            // Müfredat/kaynak bağı ve diğer opsiyonel alanlar da kopyalansın —
            // aksi halde kopya edu_topic_id'siz kalıp analizde "Diğer"e düşüyordu.
            foreach (['edu_topic_id','resource_title','resource_id','task_note','target_amount','correct_count','wrong_count'] as $c) {
                if (array_key_exists($c, $row)) { $copyCols[] = $c; $copyVals[] = $row[$c]; }
            }
            $copyCols[] = 'item_order'; $copyVals[] = 0;
            $qm = implode(', ', array_fill(0, count($copyCols), '?'));
            $ins = $pdo->prepare("INSERT INTO schedule_items (".implode(', ', $copyCols).") VALUES ($qm)");
            $ins->execute($copyVals);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['ok' => true, 'new_id' => $newId]); exit;
        } catch (Exception $e) { echo json_encode(['ok' => false, 'error' => 'Kopyalama hatası.']); exit; }
    }

    // B2. PLANLAYICI YAYINLAMA — Planlama Stüdyosu'nun "Haftayı Kaydet" ucu.
    //     Taslakta biriken TÜM değişiklikler (ekle / güncelle / sil) tek istekte,
    //     tek TRANSACTION içinde uygulanır: hata olursa hiçbiri yazılmaz.
    if ($action === 'plan_apply' || $action === 'plan_apply_multi') {
        $optCols = [];
        foreach (['edu_topic_id','resource_title','resource_id','task_note'] as $c) {
            try { $optCols[$c] = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE '$c'")->rowCount() > 0; }
            catch (Throwable $e) { $optCols[$c] = false; }
        }

        // Ortak doğrulama: tek-görev kaydıyla (save_schedule) birebir aynı kurallar.
        $normalize = function (array $it) use ($optCols): ?array {
            $date = (string)($it['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
            $act = in_array(($it['action_type'] ?? ''), ['soru','konu','video'], true) ? $it['action_type'] : 'soru';
            $amt = max(1, (int)($it['amount'] ?? 0));
            if ($act === 'video') $amt = 1; // video görevde miktar tek video sabittir
            $eduTid = !empty($it['edu_topic_id']) ? (int)$it['edu_topic_id'] : null;
            $csub = trim((string)($it['custom_subject'] ?? ''));
            $ctop = trim((string)($it['custom_topic'] ?? ''));
            if (!$eduTid && $csub === '' && $ctop === '') return null; // isimsiz görev engeli
            return [
                'date' => $date, 'action_type' => $act, 'amount' => $amt,
                'custom_subject' => $csub, 'custom_topic' => $ctop,
                'time_note' => (trim((string)($it['time_note'] ?? '')) ?: null),
                'opt' => [
                    'edu_topic_id'   => $eduTid,
                    'resource_title' => (trim((string)($it['resource_title'] ?? '')) ?: null),
                    'resource_id'    => (!empty($it['resource_id']) ? (int)$it['resource_id'] : null),
                    'task_note'      => (mb_substr(trim((string)($it['task_note'] ?? '')), 0, 255) ?: null),
                ],
            ];
        };

        $insCols = ['student_id','date','amount','action_type','status','topic_id','custom_subject','custom_topic','time_note'];
        foreach ($optCols as $c => $has) if ($has) $insCols[] = $c;
        $insCols[] = 'item_order';
        $qm  = implode(', ', array_fill(0, count($insCols), '?'));
        $ins = $pdo->prepare("INSERT INTO schedule_items (".implode(', ', $insCols).") VALUES ($qm)");

        // ── ÇOKLU ÖĞRENCİYE UYGULA: bu haftadaki kartlar seçilen öğrencilere
        //    YENİ görev olarak eklenir (mevcut programları silinmez/değişmez). ──
        if ($action === 'plan_apply_multi') {
            $targets = json_decode((string)($_POST['students'] ?? ''), true);
            $items   = json_decode((string)($_POST['items'] ?? ''), true);
            if (!is_array($targets) || !count($targets) || !is_array($items) || !count($items)) {
                echo json_encode(['ok' => false, 'error' => 'Öğrenci ve görev listesi gerekli.']); exit;
            }
            if (count($targets) > 50 || count($items) > 300) {
                echo json_encode(['ok' => false, 'error' => 'Tek seferde çok fazla öğrenci/görev.']); exit;
            }
            // Hedef öğrencilerin TÜMÜ bu öğretmene bağlı olmalı
            $ids = array_values(array_unique(array_filter(array_map('intval', $targets), fn($v) => $v > 0)));
            if (!count($ids)) { echo json_encode(['ok' => false, 'error' => 'Geçerli öğrenci yok.']); exit; }
            $inQ = implode(',', array_fill(0, count($ids), '?'));
            $chk = $pdo->prepare("SELECT student_id FROM coaching_relationships WHERE teacher_id = ? AND student_id IN ($inQ)");
            $chk->execute(array_merge([$teacher_id], $ids));
            $owned = array_map('intval', $chk->fetchAll(PDO::FETCH_COLUMN));
            if (count($owned) !== count($ids)) {
                echo json_encode(['ok' => false, 'error' => 'Listenizde olmayan öğrenci seçildi.']); exit;
            }
            $nCre = 0; $skipped = 0;
            try {
                $pdo->beginTransaction();
                foreach ($owned as $stuId) {
                    foreach ($items as $it) {
                        $n = is_array($it) ? $normalize($it) : null;
                        if (!$n) { $skipped++; continue; }
                        $vals = [$stuId, $n['date'], $n['amount'], $n['action_type'], 'bekliyor', null, $n['custom_subject'], $n['custom_topic'], $n['time_note']];
                        foreach ($optCols as $c => $has) if ($has) $vals[] = $n['opt'][$c];
                        $vals[] = 0;
                        $ins->execute($vals);
                        $nCre++;
                    }
                }
                $pdo->commit();
                echo json_encode(['ok' => true, 'created' => $nCre, 'students' => count($owned), 'skipped' => $skipped]); exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Uygulama hatası: hiçbir öğrenciye yazılmadı.']); exit;
            }
        }

        // ── HAFTAYI KAYDET (tek öğrenci, taslak diff'i) ──
        $ops = json_decode((string)($_POST['ops'] ?? ''), true);
        if (!is_array($ops)) { echo json_encode(['ok' => false, 'error' => 'Geçersiz veri.']); exit; }
        $create = is_array($ops['create'] ?? null) ? $ops['create'] : [];
        $update = is_array($ops['update'] ?? null) ? $ops['update'] : [];
        $delete = is_array($ops['delete'] ?? null) ? $ops['delete'] : [];
        if (!count($create) && !count($update) && !count($delete)) { echo json_encode(['ok' => false, 'error' => 'Kaydedilecek değişiklik yok.']); exit; }
        if (count($create) > 300 || count($update) > 500 || count($delete) > 500) { echo json_encode(['ok' => false, 'error' => 'Tek seferde çok fazla değişiklik.']); exit; }

        // UPDATE: status ve topic_id bilerek dokunulmaz (save_schedule ile aynı;
        // topic_id'yi yazmak eski müfredat bağını sessizce koparırdı).
        $updSet = ['date=?','amount=?','action_type=?','custom_subject=?','custom_topic=?','time_note=?'];
        foreach ($optCols as $c => $has) if ($has) $updSet[] = "$c=?";
        $upd = $pdo->prepare("UPDATE schedule_items SET ".implode(', ', $updSet)." WHERE id=? AND student_id=?");

        $del = $pdo->prepare("DELETE FROM schedule_items WHERE id=? AND student_id=?");

        $nCre = 0; $nUpd = 0; $nDel = 0; $skipped = 0;
        try {
            $pdo->beginTransaction();
            foreach ($create as $it) {
                $n = is_array($it) ? $normalize($it) : null;
                if (!$n) { $skipped++; continue; }
                $vals = [$sid, $n['date'], $n['amount'], $n['action_type'], 'bekliyor', null, $n['custom_subject'], $n['custom_topic'], $n['time_note']];
                foreach ($optCols as $c => $has) if ($has) $vals[] = $n['opt'][$c];
                $vals[] = 0;
                $ins->execute($vals);
                $nCre++;
            }
            foreach ($update as $it) {
                $id = is_array($it) ? (int)($it['id'] ?? 0) : 0;
                $n  = ($id > 0 && is_array($it)) ? $normalize($it) : null;
                if (!$n) { $skipped++; continue; }
                $vals = [$n['date'], $n['amount'], $n['action_type'], $n['custom_subject'], $n['custom_topic'], $n['time_note']];
                foreach ($optCols as $c => $has) if ($has) $vals[] = $n['opt'][$c];
                $vals[] = $id; $vals[] = $sid; // sahiplik koşulu WHERE'de
                $upd->execute($vals);
                $nUpd += $upd->rowCount() > 0 ? 1 : 0;
            }
            foreach ($delete as $id) {
                $id = (int)$id;
                if ($id <= 0) { $skipped++; continue; }
                $del->execute([$id, $sid]); // sahiplik koşulu WHERE'de
                $nDel += $del->rowCount() > 0 ? 1 : 0;
            }
            $pdo->commit();
            echo json_encode(['ok' => true, 'created' => $nCre, 'updated' => $nUpd, 'deleted' => $nDel, 'skipped' => $skipped]); exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Kaydetme hatası: tüm değişiklikler geri alındı.']); exit;
        }
    }

    // B3. HAFTA GETİR — "Geçen haftayı kopyala" kaynağı: verilen haftanın
    //     görevlerini gün-ofseti (0-6) ile döndürür; istemci taslağa kart olarak ekler.
    if ($action === 'plan_fetch_week') {
        $ws = (string)($_POST['week_start'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ws)) { echo json_encode(['ok' => false, 'error' => 'Geçersiz tarih.']); exit; }
        $we = date('Y-m-d', strtotime("$ws +6 days"));
        try {
            $q = $pdo->prepare("
                SELECT si.*, t.name AS old_topic_name, s.name AS old_subject_name, s.category AS old_category,
                       et.topic_name AS edu_topic_name, es.lesson_name AS edu_subject_name, ec.name AS edu_category_name,
                       er.type AS res_type
                FROM schedule_items si
                LEFT JOIN coaching_topics t ON si.topic_id = t.id
                LEFT JOIN coaching_subjects s ON t.subject_id = s.id
                LEFT JOIN education_topics et ON si.edu_topic_id = et.id
                LEFT JOIN education_subjects es ON et.subject_id = es.id
                LEFT JOIN education_categories ec ON es.category_id = ec.id
                LEFT JOIN education_resources er ON si.resource_id = er.id
                WHERE si.student_id = ? AND si.date BETWEEN ? AND ?
                ORDER BY si.date, si.item_order, si.id");
            $q->execute([$sid, $ws, $we]);
            $items = [];
            $t0 = strtotime($ws);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $it) {
                $subject = ($it['edu_subject_name'] ?? '') ?: (!empty($it['old_topic_name']) ? ($it['old_subject_name'] ?? '') : ($it['custom_subject'] ?? ''));
                $topic   = ($it['edu_topic_name'] ?? '')   ?: (!empty($it['old_topic_name']) ? $it['old_topic_name'] : ($it['custom_topic'] ?? ''));
                $catN    = ($it['edu_category_name'] ?? '') ?: (($it['old_category'] ?? '') ?: 'Diğer');
                // Kopyada HEDEF esas alınır (durum girilmişse amount gerçekleşene dönmüş olabilir)
                $hedef = (isset($it['target_amount']) && $it['target_amount'] !== null && $it['target_amount'] !== '')
                    ? (int)$it['target_amount'] : (int)$it['amount'];
                $items[] = [
                    'day_offset'     => (int)round((strtotime($it['date']) - $t0) / 86400),
                    'category'       => $catN,
                    'subject'        => (string)$subject,
                    'topic'          => (string)$topic,
                    'edu_topic_id'   => !empty($it['edu_topic_id']) ? (int)$it['edu_topic_id'] : null,
                    'custom_subject' => (string)$subject,
                    'custom_topic'   => (string)$topic,
                    'resource_id'    => !empty($it['resource_id']) ? (int)$it['resource_id'] : null,
                    'resource_title' => (string)($it['resource_title'] ?? ''),
                    'resource_type'  => (string)($it['res_type'] ?? ''),
                    'action_type'    => $it['action_type'],
                    'amount'         => $hedef,
                    'time_note'      => trim((string)($it['time_note'] ?? '')),
                    'task_note'      => trim((string)($it['task_note'] ?? '')),
                ];
            }
            echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE); exit;
        } catch (Throwable $e) { echo json_encode(['ok' => false, 'error' => 'Hafta okunamadı.']); exit; }
    }

    // B5. HAFTA ŞABLONLARI — kaydet / listele / getir / sil (öğretmene özel)
    if (in_array($action, ['tpl_save','tpl_list','tpl_get','tpl_delete'], true)) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS plan_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                data LONGTEXT NOT NULL,
                items_count INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_tpl_teacher (teacher_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
        } catch (Throwable $e) {}

        try {
            if ($action === 'tpl_list') {
                $st = $pdo->prepare("SELECT id, name, items_count, DATE_FORMAT(created_at, '%d.%m.%Y') AS d FROM plan_templates WHERE teacher_id = ? ORDER BY id DESC LIMIT 50");
                $st->execute([$teacher_id]);
                echo json_encode(['ok' => true, 'templates' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE); exit;
            }
            if ($action === 'tpl_get') {
                $tid = (int)($_POST['id'] ?? 0);
                $st = $pdo->prepare("SELECT data FROM plan_templates WHERE id = ? AND teacher_id = ?");
                $st->execute([$tid, $teacher_id]);
                $data = $st->fetchColumn();
                if ($data === false) { echo json_encode(['ok' => false, 'error' => 'Şablon bulunamadı.']); exit; }
                echo json_encode(['ok' => true, 'items' => (json_decode($data, true) ?: [])], JSON_UNESCAPED_UNICODE); exit;
            }
            if ($action === 'tpl_delete') {
                $tid = (int)($_POST['id'] ?? 0);
                $st = $pdo->prepare("DELETE FROM plan_templates WHERE id = ? AND teacher_id = ?");
                $st->execute([$tid, $teacher_id]);
                echo json_encode(['ok' => true, 'deleted' => $st->rowCount()]); exit;
            }
            // tpl_save — istemcinin gönderdiği kart listesini temizleyip sakla
            $name  = trim(mb_substr((string)($_POST['name'] ?? ''), 0, 100));
            $itemsIn = json_decode((string)($_POST['items'] ?? ''), true);
            if ($name === '' || !is_array($itemsIn) || !count($itemsIn)) { echo json_encode(['ok' => false, 'error' => 'Şablon adı ve görev listesi gerekli.']); exit; }
            if (count($itemsIn) > 300) { echo json_encode(['ok' => false, 'error' => 'Şablon çok büyük.']); exit; }
            $clean = [];
            foreach ($itemsIn as $it) {
                if (!is_array($it)) continue;
                $off = (int)($it['day_offset'] ?? -1);
                if ($off < 0 || $off > 6) continue;
                $act = in_array(($it['action_type'] ?? ''), ['soru','konu','video'], true) ? $it['action_type'] : 'soru';
                $eduTid = !empty($it['edu_topic_id']) ? (int)$it['edu_topic_id'] : null;
                $csub = trim((string)($it['custom_subject'] ?? ($it['subject'] ?? '')));
                $ctop = trim((string)($it['custom_topic'] ?? ($it['topic'] ?? '')));
                if (!$eduTid && $csub === '' && $ctop === '') continue; // isimsiz görev
                $clean[] = [
                    'day_offset'     => $off,
                    'category'       => trim((string)($it['category'] ?? '')),
                    'subject'        => trim((string)($it['subject'] ?? '')),
                    'topic'          => trim((string)($it['topic'] ?? '')),
                    'edu_topic_id'   => $eduTid,
                    'custom_subject' => $csub,
                    'custom_topic'   => $ctop,
                    'resource_id'    => !empty($it['resource_id']) ? (int)$it['resource_id'] : null,
                    'resource_title' => trim((string)($it['resource_title'] ?? '')),
                    'resource_type'  => trim((string)($it['resource_type'] ?? '')),
                    'action_type'    => $act,
                    'amount'         => ($act === 'video') ? 1 : max(1, (int)($it['amount'] ?? 1)),
                    'time_note'      => trim((string)($it['time_note'] ?? '')),
                    'task_note'      => mb_substr(trim((string)($it['task_note'] ?? '')), 0, 255),
                ];
            }
            if (!count($clean)) { echo json_encode(['ok' => false, 'error' => 'Geçerli görev bulunamadı.']); exit; }
            $pdo->prepare("INSERT INTO plan_templates (teacher_id, name, data, items_count) VALUES (?,?,?,?)")
                ->execute([$teacher_id, $name, json_encode($clean, JSON_UNESCAPED_UNICODE), count($clean)]);
            echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'items_count' => count($clean)]); exit;
        } catch (Throwable $e) { echo json_encode(['ok' => false, 'error' => 'Şablon işlemi başarısız.']); exit; }
    }

    // C. SIRALAMA GÜNCELLEME
    if ($action === 'reorder_schedule') {
        $orderList = $_POST['order'] ?? [];
        if (is_array($orderList)) {
            try {
                $sql = "UPDATE schedule_items SET item_order = ? WHERE id = ? AND student_id = ?";
                $stmt = $pdo->prepare($sql);
                foreach ($orderList as $index => $id) {
                    $stmt->execute([$index, $id, $sid]);
                }
                echo json_encode(['ok' => true]); exit;
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => 'Sıralama hatası.']); exit;
            }
        }
    }

    echo json_encode(['ok' => false, 'error' => 'Bilinmeyen işlem.']); exit;
}

// 4. POST İŞLEMLERİ (Form Gönderimi ve Yönlendirme)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['ajax'])) {
    $should_redirect = false;

    // Güvenlik: $sid (öğrenci) gerçekten bu öğretmenin öğrencisi mi? Değilse
    // null'a düşürülür — aşağıdaki tüm "&& $sid" ile korunan işlemler (bilgi
    // güncelleme, durum güncelleme, görev kaydetme) otomatik olarak atlanır.
    if ($sid) {
        $ownCheck = $pdo->prepare("SELECT 1 FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
        $ownCheck->execute([$teacher_id, $sid]);
        if (!$ownCheck->fetchColumn()) { $sid = null; }
    }

    // Öğrenci Ekleme
    if (isset($_POST['create_student'])) {
        $u_user = trim($_POST['username']); 
        $u_pass = $_POST['password']; 
        $u_name = trim($_POST['first_name']); 
        $u_last = trim($_POST['last_name']);
        $u_level = $_POST['school_level'] ?? 'Lise';
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?"); 
        $check->execute([$u_user]);
        if ($check->rowCount() > 0) { $error = "Bu kullanıcı adı alınmış."; } 
        else {
            $pass = password_hash($u_pass, PASSWORD_DEFAULT); 
            $mail = $u_user . "@derspros.com"; 
            $ins = $pdo->prepare("INSERT INTO users (username, password, first_name, last_name, role, school_level, email, is_active, date_joined) VALUES (?, ?, ?, ?, 'student', ?, ?, 1, NOW())");
            if ($ins->execute([$u_user, $pass, $u_name, $u_last, $u_level, $mail])) {
                $new_sid = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $new_sid]);
                $sid = $new_sid; $should_redirect = true;
            }
        }
    }
    // Öğrenci Bağlama
    if (isset($_POST['link_student'])) {
        $search = trim($_POST['search_term']);
        $find = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND role = 'student'");
        $find->execute([$search, $search]); $found = $find->fetch();
        if ($found) {
            $chk = $pdo->prepare("SELECT id FROM coaching_relationships WHERE teacher_id = ? AND student_id = ?");
            $chk->execute([$teacher_id, $found['id']]);
            if ($chk->rowCount() == 0) {
                $pdo->prepare("INSERT INTO coaching_relationships (teacher_id, student_id) VALUES (?, ?)")->execute([$teacher_id, $found['id']]);
                $should_redirect = true;
            } else {
                $error = "Bu öğrenci zaten listenizde kayıtlı.";
            }
        } else {
            $error = "Kullanıcı bulunamadı. Kullanıcı adını kontrol edin veya admin ile iletişime geçin.";
        }
    }
    // Bilgi Güncelleme
    if (isset($_POST['update_student_info']) && $sid) {
        try { 
            $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, parent_name=?, parent_phone=?, school_level=? WHERE id=?")
                ->execute([$_POST['first_name'], $_POST['last_name'], $_POST['student_phone'], $_POST['parent_name'], $_POST['parent_phone'], $_POST['school_level'], $sid]); 
            $should_redirect = true;
        } catch(Exception $e) {}
    }

    // Durum Güncelleme (Program kartına tıklayıp "Durumu Güncelle" penceresinden — koç tarafı)
    if (isset($_POST['update_status']) && $sid) {
        $schedId = (int)($_POST['schedule_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'bekliyor';
        $newAmount = (int)($_POST['amount'] ?? 0);

        $checkStmt = $pdo->prepare("SELECT amount, target_amount FROM schedule_items WHERE id = ? AND student_id = ?");
        $checkStmt->execute([$schedId, $sid]);
        $currentItem = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($currentItem) {
            $finalTarget = ($currentItem['target_amount'] === null || $currentItem['target_amount'] === '')
                ? $currentItem['amount'] : $currentItem['target_amount'];
            $pdo->prepare("UPDATE schedule_items SET status = ?, amount = ?, target_amount = ? WHERE id = ? AND student_id = ?")
                ->execute([$newStatus, $newAmount, $finalTarget, $schedId, $sid]);
        }
        $should_redirect = true;
    }

    // Görev İşlemleri
    if (isset($_POST['save_schedule']) && $sid) {
        $date = $_POST['date']; $amt = $_POST['amount']; $act = $_POST['action_type'];
        $st = $_POST['status']; $tid = !empty($_POST['topic'])?$_POST['topic']:null;
        $csub = $_POST['custom_subject'] ?? ''; $ctop = $_POST['custom_topic'] ?? ''; $tn = $_POST['time_note'];
        $eduTid = !empty($_POST['edu_topic_id']) ? (int)$_POST['edu_topic_id'] : null; // YENİ müfredat konusu
        $resTitle = trim($_POST['resource_title'] ?? '') ?: null; // Kaynaktan seçildiyse kaynak adı
        $resId    = !empty($_POST['resource_id']) ? (int)$_POST['resource_id'] : null; // kaynak bağı (video URL/tip)
        $taskNote = mb_substr(trim($_POST['task_note'] ?? ''), 0, 255) ?: null;        // görev kısa notu
        if (!in_array($act, ['soru','konu','video'], true)) $act = 'soru';
        if ($act === 'video') $amt = 1; // video görevde miktar tek video sabittir
        $id = $_POST['schedule_id'];

        // Opsiyonel kolonlar (üstteki ensure bloğu oluşturur; burada yalnızca var mı bakılır)
        $optCols = [];
        foreach (['edu_topic_id','resource_title','resource_id','task_note'] as $c) {
            $optCols[$c] = $pdo->query("SHOW COLUMNS FROM schedule_items LIKE '$c'")->rowCount() > 0;
        }
        $optVals = ['edu_topic_id'=>$eduTid, 'resource_title'=>$resTitle, 'resource_id'=>$resId, 'task_note'=>$taskNote];

        // Sunucu tarafı güvenlik: JS doğrulaması atlanırsa bile isimsiz görev kaydedilmesin
        if (!$eduTid && trim($csub) === '' && trim($ctop) === '') {
            $error = "Ders adı veya konu boş olamaz. Görev kaydedilmedi.";
        } elseif ($id) {
            // NOT: UPDATE'te topic_id kasıtlı olarak SET edilmiyor. Bu modalde artık eski
            // koçluk konusu seçme alanı yok (Faz 4'te kaldırıldı); topic_id'yi burada
            // yazmaya kalkışmak onu her düzenlemede sessizce null'a düşürüp eski müfredata
            // bağlı görevlerin bağlantısını koparırdı. Var olan değer neyse öyle kalır.
            $set  = ['amount=?','action_type=?','status=?','custom_subject=?','custom_topic=?','time_note=?'];
            $vals = [$amt, $act, $st, $csub, $ctop, $tn];
            foreach ($optCols as $c => $has) { if ($has) { $set[] = "$c=?"; $vals[] = $optVals[$c]; } }
            $vals[] = $id;
            $vals[] = $sid;
            // Güvenlik: yalnızca $sid'e (bu öğretmenin öğrencisi) ait kayıt güncellenir
            $pdo->prepare("UPDATE schedule_items SET ".implode(', ', $set)." WHERE id=? AND student_id=?")->execute($vals);
        } else {
            $cols = ['student_id','date','amount','action_type','status','topic_id','custom_subject','custom_topic','time_note'];
            $vals = [$sid, $date, $amt, $act, $st, $tid, $csub, $ctop, $tn];
            foreach ($optCols as $c => $has) { if ($has) { $cols[] = $c; $vals[] = $optVals[$c]; } }
            $cols[] = 'item_order'; $vals[] = 0;
            $qm = implode(', ', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO schedule_items (".implode(', ', $cols).") VALUES ($qm)")->execute($vals);
        }
        // Doğrulama hatası varsa yönlendirme yapılmaz, aksi halde $error kaybolur (redirect yeni GET başlatır)
        if (empty($error)) { $should_redirect = true; }
    }
    if (isset($_POST['delete_schedule'])) {
        $delId = (int)($_POST['schedule_id'] ?? 0);
        // Güvenlik: yalnızca kendi öğrencisine ait bir görev satırı silinebilir
        $pdo->prepare("DELETE si FROM schedule_items si
                       JOIN coaching_relationships cr ON cr.student_id = si.student_id
                       WHERE si.id = ? AND cr.teacher_id = ?")->execute([$delId, $teacher_id]);
        $should_redirect = true;
    }

    // Deneme İşlemleri
    if (isset($_POST['add_exam']) && $sid) {
        $nm = $_POST['name']; $dt = $_POST['date']; $cat = $_POST['category']; 
        $det = []; $tot = 0;
        if(isset($_POST['lesson_name'])) { 
            foreach($_POST['lesson_name'] as $k=>$l) { 
                $d=(float)($_POST['dogru'][$k]??0); $y=(float)($_POST['yanlis'][$k]??0); $n=$d-($y*0.25); 
                if($d>0||$y>0){ $det[$l]=['d'=>$d,'y'=>$y,'n'=>$n]; $tot+=$n; } 
            } 
        }
        $jdet = json_encode($det, JSON_UNESCAPED_UNICODE); $ftot = !empty($_POST['total_net'])?$_POST['total_net']:$tot;
        if(!empty($_POST['exam_result_id'])) { 
            $pdo->prepare("UPDATE quiz_results SET exam_name=?, date_taken=?, category=?, total_net=?, details=? WHERE id=?")->execute([$nm, $dt, $cat, $ftot, $jdet, $_POST['exam_result_id']]); 
        } else { 
            $pdo->prepare("INSERT INTO quiz_results (student_id, exam_name, date_taken, category, total_net, details) VALUES (?, ?, ?, ?, ?, ?)")->execute([$sid, $nm, $dt, $cat, $ftot, $jdet]); 
        }
        $should_redirect = true;
    }
    if (isset($_POST['delete_exam'])) { 
        $pdo->prepare("DELETE FROM quiz_results WHERE id=?")->execute([$_POST['delete_exam_id']]); 
        $should_redirect = true;
    }

    // --- YÖNLENDİRME (Hayalet Kopyayı Kesin Çözer) ---
    if ($should_redirect) {
        $current_url = strtok($_SERVER["REQUEST_URI"], '?');
        $qs = $_SERVER['QUERY_STRING'];
        header("Location: $current_url?$qs");
        exit;
    }
}

// 5. VERİ ÇEKME
$st = $pdo->prepare("SELECT u.* FROM users u JOIN coaching_relationships cr ON u.id = cr.student_id WHERE cr.teacher_id = ? ORDER BY u.first_name ASC");
$st->execute([$teacher_id]);
$my_students = $st->fetchAll(PDO::FETCH_ASSOC);

if ($sid) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
    $stmt->execute([$sid]); 
    $selected_student = $stmt->fetch(PDO::FETCH_ASSOC);
    $student_level = $selected_student['school_level'] ?? 'Lise';

    // PROGRAM VERİLERİ (Sıralama Düzeltildi)
    $sc = $pdo->prepare("
        SELECT si.*, t.name as topic_name, t.subject_id as subject_id, s.name as subject_name, s.category as subject_category,
               et.topic_name AS edu_topic_name, es.lesson_name AS edu_subject_name, ec.name AS edu_category_name,
               er.type AS resource_type, er.external_url AS resource_url
        FROM schedule_items si
        LEFT JOIN coaching_topics t ON si.topic_id = t.id
        LEFT JOIN coaching_subjects s ON t.subject_id = s.id
        LEFT JOIN education_topics    et ON si.edu_topic_id = et.id
        LEFT JOIN education_subjects  es ON et.subject_id = es.id
        LEFT JOIN education_categories ec ON es.category_id = ec.id
        LEFT JOIN education_resources er ON si.resource_id = er.id
        WHERE si.student_id = ? AND si.date BETWEEN ? AND ?
        ORDER BY si.date ASC, si.item_order ASC, si.id ASC
    ");
    $sc->execute([$sid, $week_dates[0], $week_dates[6]]); 
    $raw_items = $sc->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($week_dates as $wd) { 
        $schedule_items[$wd] = array_filter($raw_items, function($i) use ($wd) { 
            return $i['date'] == $wd; 
        }); 
    }

    // İstatistikler ve Analiz
    try {
        $week_ago = date('Y-m-d', strtotime('-7 days'));
        $q_stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND status = 'yapildi'"); 
        $q_stmt->execute([$sid]); $report_stats['total_q'] = $q_stmt->fetchColumn() ?: 0;
        
        $q_week_stmt = $pdo->prepare("SELECT SUM(amount) FROM schedule_items WHERE student_id = ? AND action_type = 'soru' AND status = 'yapildi' AND date >= ?"); 
        $q_week_stmt->execute([$sid, $week_ago]); $report_stats['week_q'] = $q_week_stmt->fetchColumn() ?: 0;

        $t_stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND status = 'yapildi'"); 
        $t_stmt->execute([$sid]); $report_stats['total_t'] = $t_stmt->fetchColumn() ?: 0;
        
        $t_week_stmt = $pdo->prepare("SELECT COUNT(*) FROM schedule_items WHERE student_id = ? AND action_type = 'konu' AND status = 'yapildi' AND date >= ?"); 
        $t_week_stmt->execute([$sid, $week_ago]); $report_stats['week_t'] = $t_week_stmt->fetchColumn() ?: 0;

        $topic_stats = [];
        // Öncelik: YENİ müfredat (edu_topic_id) -> eski koçluk konusu (topic_id) -> manuel (custom_topic)
        $completed = $pdo->prepare("
            SELECT si.*, t.id as topic_id, t.name as topic_name, t.subject_id,
                   et.topic_name AS edu_topic_name
            FROM schedule_items si
            LEFT JOIN coaching_topics t ON si.topic_id = t.id
            LEFT JOIN education_topics et ON si.edu_topic_id = et.id
            WHERE si.student_id = ? AND si.status = 'yapildi'
            ORDER BY si.date DESC
        ");
        $completed->execute([$sid]);
        foreach($completed->fetchAll(PDO::FETCH_ASSOC) as $item) {
            if (!empty($item['edu_topic_id'])) {
                $tid = 'edu_' . $item['edu_topic_id'];
                $tname = $item['edu_topic_name'];
            } elseif (!empty($item['topic_id'])) {
                $tid = $item['topic_id'];
                $tname = $item['topic_name'];
            } elseif (!empty($item['custom_topic'])) {
                $tid = 'custom_' . md5($item['custom_topic']);
                $tname = $item['custom_topic'];
            } else {
                continue;
            }
            if(!isset($topic_stats[$tid])) $topic_stats[$tid] = ['total_questions'=>0, 'total_topics'=>0, 'total_videos'=>0, 'history'=>[], 'name'=>$tname];
            if($item['action_type']=='soru') $topic_stats[$tid]['total_questions'] += (int)$item['amount'];
            if($item['action_type']=='konu') $topic_stats[$tid]['total_topics'] += 1;
            if($item['action_type']=='video') $topic_stats[$tid]['total_videos'] += 1;
            $topic_stats[$tid]['history'][] = [
                'date'          => $item['date'],
                'type'          => $item['action_type'],
                'action_type'   => $item['action_type'],
                'amount'        => $item['amount'],
                'target_amount' => $item['target_amount'],
                'correct_count' => $item['correct_count'] ?? null,
                'wrong_count'   => $item['wrong_count']   ?? null,
                'status'        => $item['status'],
            ];
        }

        // Konu başına verilen görev SAYISI (durumdan bağımsız).
        // Kapsama yüzdesi + FAZ sistemi için: faz N = her konuya en az N görev.
        $assigned_topics = [];
        try {
            $asg = $pdo->prepare("
                SELECT CAST(topic_id AS CHAR) AS k, COUNT(*) AS c FROM schedule_items
                WHERE student_id = ? AND topic_id IS NOT NULL GROUP BY topic_id
                UNION ALL
                SELECT CONCAT('edu_', edu_topic_id) AS k, COUNT(*) AS c FROM schedule_items
                WHERE student_id = ? AND edu_topic_id IS NOT NULL GROUP BY edu_topic_id
            ");
            $asg->execute([$sid, $sid]);
            foreach ($asg->fetchAll(PDO::FETCH_ASSOC) as $ar) {
                $assigned_topics[$ar['k']] = (int)$ar['c'];
            }
        } catch (Exception $e) {}

        // YENİ müfredat (edu_topic_id bazlı) — eski koçluk müfredatı artık kullanılmıyor,
        // yalnızca yeni sistem gösterilir.
        if ($student_level !== 'Ortaokul') {
            $eduCats = $pdo->query("SELECT id, name FROM education_categories WHERE name IN ('TYT','AYT') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($eduCats as $eduCat) {
                $eduSubs = $pdo->prepare("SELECT * FROM education_subjects WHERE category_id = ? ORDER BY display_order, lesson_name");
                $eduSubs->execute([$eduCat['id']]);
                foreach ($eduSubs->fetchAll(PDO::FETCH_ASSOC) as $esub) {
                    $eduTops = $pdo->prepare("SELECT * FROM education_topics WHERE subject_id = ? ORDER BY display_order, topic_name");
                    $eduTops->execute([$esub['id']]);
                    $et_list = $eduTops->fetchAll(PDO::FETCH_ASSOC);
                    $esub_data = ['subject_name'=>$esub['lesson_name'], 'name'=>$esub['lesson_name'], 'category'=>$eduCat['name'], 'src'=>'edu', 'topics'=>[], 'q_total'=>0, 't_total'=>0, 'v_total'=>0];
                    foreach ($et_list as $et) {
                        $key = 'edu_' . $et['id'];
                        $stats = $topic_stats[$key] ?? ['total_questions'=>0, 'total_topics'=>0, 'total_videos'=>0, 'history'=>[]];
                        $esub_data['q_total'] += $stats['total_questions'];
                        $esub_data['t_total'] += $stats['total_topics'];
                        $esub_data['v_total'] += ($stats['total_videos'] ?? 0);
                        $asgC = (int)($assigned_topics[$key] ?? 0);
                        $esub_data['topics'][] = ['id'=>$et['id'], 'name'=>$et['topic_name'], 'q_count'=>$stats['total_questions'], 't_count'=>$stats['total_topics'], 'v_count'=>($stats['total_videos'] ?? 0), 'history'=>$stats['history'], 'assigned'=>$asgC > 0, 'asg_count'=>$asgC];
                    }
                    $progress_data[] = $esub_data;
                }
            }
        }
    } catch (Exception $e) {}

    try {
        $ex = $pdo->prepare("SELECT * FROM quiz_results WHERE student_id = ? ORDER BY date_taken DESC, id DESC");
        $ex->execute([$sid]);
        $exam_results = $ex->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$all_subjects = []; 
try { $all_subjects = $pdo->query("SELECT * FROM coaching_subjects ORDER BY category, name")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) {}
?>

<div class="max-w-[98%] mx-auto py-4">

    <?php include 'koc/sidebar.php'; ?>

    <?php if($message): ?>
        <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-3 border border-green-200 text-sm">✅ <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-3 border border-red-200 text-sm">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- ÜST BAR: Öğrenci Seçici + Sekmeler + (masaüstü) Hafta/Gün Nav + Aksiyonlar
         id=kocTopBar: masaüstünde gömülü Planlama Stüdyosu bu barın altından başlar. -->
    <div id="kocTopBar" class="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3 mb-4 relative z-[45]">
        <!-- Mobil: flex-wrap | PC (md+): tek satır -->
        <div class="flex flex-wrap md:flex-nowrap items-center gap-2">

            <!-- Öğrenci seçici dropdown — mobilde 3-nokta ile aynı satırı paylaşır -->
            <div class="relative flex-1 min-w-0 md:w-48 md:flex-none">
                <select onchange="window.location.href='?student_id='+this.value+'&date=<?php echo $date_param; ?>'"
                        class="w-full appearance-none bg-slate-50 border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#223488]/20 cursor-pointer">
                    <option value="">👤 Öğrenci Seçin...</option>
                    <?php foreach($my_students as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($sid == $s['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-3 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <?php if ($selected_student): ?>
            <!-- 3-nokta: Veliye/Öğrenciye Mesaj + PDF — öğrenci adının hemen yanında -->
            <div class="relative flex-shrink-0">
                <button type="button" onclick="event.stopPropagation(); document.getElementById('kocMoreMenu').classList.toggle('hidden');"
                        class="flex items-center justify-center w-10 h-10 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition" title="Diğer işlemler">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                </button>
                <div id="kocMoreMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-slate-200 p-1.5 z-[60]">
                    <button onclick="document.getElementById('kocMoreMenu').classList.add('hidden'); sendWhatsappReport();"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-green-50 transition">
                        <span class="text-lg leading-none">💬</span> Veliye Mesaj At</button>
                    <button onclick="document.getElementById('kocMoreMenu').classList.add('hidden'); sendStudentMessage();"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-amber-50 transition">
                        <span class="text-lg leading-none">📨</span> Öğrenciye Mesaj At</button>
                    <button onclick="document.getElementById('kocMoreMenu').classList.add('hidden'); downloadPdfProgram();"
                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-slate-50 transition">
                        <span class="text-lg leading-none">📄</span> PDF İndir</button>
                </div>
            </div>

            <!-- Tabs — dropdown'ın hemen yanında (md+) -->
            <div class="hidden md:flex bg-slate-100 p-1 rounded-xl border border-slate-200 flex-shrink-0">
                <button onclick="openTab('schedule')" id="tab-schedule-desk" class="px-3 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-1.5">📅 Program</button>
                <button onclick="openTab('topics')"   id="tab-topics-desk"   class="px-3 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-1.5">📊 Analiz</button>
                <button onclick="openTab('exams')"    id="tab-exams-desk"    class="px-3 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-1.5">📝 Denemeler</button>
            </div>

            <!-- Masaüstü Hafta/Gün navigasyonu (yalnızca lg+; yalnızca Program sekmesinde) -->
            <div class="ps-progbar hidden lg:flex items-center gap-1 flex-shrink-0">
                <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $prev_week; ?>" title="Önceki hafta" class="px-2 py-2 rounded-lg text-xs font-bold bg-slate-50 border border-slate-200 text-[#223488] hover:bg-slate-100 transition">«</a>
                <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $prev_day; ?>" title="Önceki gün" class="px-2 py-2 rounded-lg text-xs font-bold bg-[#223488] text-white hover:bg-[#314595] transition">‹</a>
                <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $today_date; ?>" class="px-3 py-2 rounded-lg text-xs font-bold bg-[#ec9731] text-white hover:bg-[#d68625] transition whitespace-nowrap">📅 Bugün</a>
                <span class="text-[11px] font-bold text-slate-500 px-1 whitespace-nowrap"><?php echo date('d.m', strtotime($week_dates[0])); ?>–<?php echo date('d.m', strtotime($week_dates[6])); ?></span>
                <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $next_day; ?>" title="Sonraki gün" class="px-2 py-2 rounded-lg text-xs font-bold bg-[#223488] text-white hover:bg-[#314595] transition">›</a>
                <a href="?student_id=<?php echo $sid; ?>&date=<?php echo $next_week; ?>" title="Sonraki hafta" class="px-2 py-2 rounded-lg text-xs font-bold bg-slate-50 border border-slate-200 text-[#223488] hover:bg-slate-100 transition">»</a>
            </div>
            <?php endif; ?>

            <?php if ($selected_student): ?>
            <!-- Taslak durumu + Sıfırla + Kaydet — üst-barın sağ köşesi; yalnızca Program sekmesinde (masaüstü) -->
            <div class="ps-progbar hidden lg:flex items-center gap-2 ml-auto flex-shrink-0">
                <span id="psDraftStatus" class="hidden lg:inline-flex items-center gap-1.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg px-2.5 py-1.5 whitespace-nowrap">✓ Hazır</span>

                <!-- 🧰 Araçlar: şablonlar + geçen hafta + çoklu öğrenciye uygulama -->
                <div class="relative">
                    <button type="button" onclick="event.stopPropagation(); document.getElementById('psToolsMenu').classList.toggle('hidden');"
                            class="text-[10px] font-bold text-[#223488] bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg px-2.5 py-2 transition whitespace-nowrap" title="Şablonlar ve toplu işlemler">🧰 Araçlar ▾</button>
                    <div id="psToolsMenu" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 p-1.5 z-[70]">
                        <button onclick="psToolCopyPrevWeek()"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-blue-50 transition text-left">
                            <span class="text-base leading-none">📋</span> Geçen haftayı kopyala</button>
                        <button onclick="psToolSaveTemplate()"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-indigo-50 transition text-left">
                            <span class="text-base leading-none">💾</span> Haftayı şablon olarak kaydet</button>
                        <button onclick="psToolShowTemplates()"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-indigo-50 transition text-left">
                            <span class="text-base leading-none">📂</span> Şablondan uygula</button>
                        <div id="psTplList" class="hidden max-h-52 overflow-y-auto border-t border-slate-100 mt-1 pt-1"></div>
                        <div class="h-px bg-slate-100 my-1"></div>
                        <button onclick="psToolMultiApply()"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-bold text-slate-700 rounded-lg hover:bg-emerald-50 transition text-left">
                            <span class="text-base leading-none">👥</span> Bu haftayı başka öğrencilere uygula</button>
                    </div>
                </div>

                <button type="button" onclick="psResetDraft()" class="text-[10px] font-bold text-slate-500 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg px-2.5 py-2 transition whitespace-nowrap" title="Kaydedilmemiş tüm değişiklikleri at, tabloya dön">↺ Taslağı Sıfırla</button>
                <button type="button" id="psSaveBtn" onclick="psSaveWeek()" disabled
                    class="text-xs font-black text-white bg-[#ec9731] hover:bg-[#d68625] disabled:opacity-40 disabled:cursor-not-allowed rounded-lg px-4 py-2 shadow-sm transition whitespace-nowrap">💾 Haftayı Kaydet <span id="psSaveCount"></span></button>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <script>
    // 3-nokta ve Araçlar menülerini dışarı tıklayınca kapat
    document.addEventListener('click', function(e){
        ['kocMoreMenu', 'psToolsMenu'].forEach(function(id){
            var m = document.getElementById(id);
            if (m && !m.classList.contains('hidden') && !m.parentElement.contains(e.target)) m.classList.add('hidden');
        });
    });
    </script>

    <!-- ANA İÇERİK -->
    <div>
        <?php if ($selected_student): ?>
            <!-- Tabs — sadece mobil -->
            <div class="flex justify-end mb-4 md:hidden">
                <div class="flex bg-white p-1 rounded-xl shadow-sm border border-slate-200 w-fit">
                    <button onclick="openTab('schedule')" id="tab-schedule" class="px-4 py-1.5 rounded-lg font-bold text-xs transition bg-slate-800 text-white shadow-sm flex items-center gap-2">📅 Program</button>
                    <button onclick="openTab('topics')"   id="tab-topics"   class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📊 Analiz</button>
                    <button onclick="openTab('exams')"    id="tab-exams"    class="px-4 py-1.5 rounded-lg font-bold text-xs transition text-slate-500 hover:bg-slate-50 flex items-center gap-2">📝 Denemeler</button>
                </div>
            </div>

            <?php include 'koc/program.php'; ?>
            <?php include 'koc/analiz.php'; ?>
            <?php include 'koc/denemeler.php'; ?>

        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-[55vh] bg-white rounded-2xl border border-dashed border-slate-200 text-center p-8">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-4xl mb-4 text-slate-300">🎓</div>
                <h2 class="text-xl font-bold text-slate-700">Öğrenci Seçin</h2>
                <p class="text-slate-400 text-sm mt-2">Yukarıdaki açılır menüden bir öğrenci seçin.</p>
                <?php if(empty($my_students)): ?>
                <a href="<?php echo BASE_URL; ?>/koc/ogrencilerim.php" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 bg-[#223488] text-white text-sm font-bold rounded-xl hover:bg-[#314595] transition">
                    ➕ İlk Öğrencinizi Ekleyin
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'koc/modals.php'; ?>
<?php include 'koc/planlayici.php'; // Planlama Stüdyosu (window.eduTopicStats için modals'tan sonra) ?>
<?php include 'koc/scripts.php'; ?>
<?php ob_end_flush(); ?>