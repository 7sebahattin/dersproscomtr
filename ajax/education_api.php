<?php
/**
 * education_api.php — Yeni müfredat sistemi REST API (salt okunur)
 *
 * Uçlar (GET):
 *   ?action=categories                          -> tüm aktif kategoriler
 *   ?action=subjects&category_id={id}           -> kategorinin aktif dersleri
 *   ?action=topics&subject_id={id}              -> dersin aktif konuları
 *        [&q=arama] [&page=1] [&per_page=100]      (arama + sayfalama)
 *   ?action=resource_topics&resource_id={id}    -> kaynağa bağlı konular
 *
 * Yetki: giriş yapmış tüm kullanıcılar okuyabilir (öğretmenler seçim için).
 * Yazma işlemleri burada YOKTUR — müfredat düzenleme yalnızca admin
 * panelindedir (admin/education.php).
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../education_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Giriş gerekli.']);
    exit;
}

try {
    education_ensure_schema($pdo);

    // Görünürlük kapsamı: admin her şeyi, öğretmen kendi + kilitli/global içeriği görür
    $scopeUid     = (int)$_SESSION['user_id'];
    $scopeIsAdmin = education_is_admin();

    $action = $_GET['action'] ?? '';

    switch ($action) {

        case 'categories':
            echo json_encode(['ok' => true, 'data' => education_get_categories($pdo, true, $scopeUid, $scopeIsAdmin)], JSON_UNESCAPED_UNICODE);
            break;

        case 'subjects':
            $catId = (int)($_GET['category_id'] ?? 0);
            if ($catId <= 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'category_id gerekli.']); break; }
            echo json_encode(['ok' => true, 'data' => education_get_subjects($pdo, $catId, true, $scopeUid, $scopeIsAdmin)], JSON_UNESCAPED_UNICODE);
            break;

        case 'topics':
            $subjId = (int)($_GET['subject_id'] ?? 0);
            if ($subjId <= 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'subject_id gerekli.']); break; }
            $q       = trim($_GET['q'] ?? '');
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(500, max(10, (int)($_GET['per_page'] ?? 100)));
            $offset  = ($page - 1) * $perPage;
            $rows = education_get_topics($pdo, $subjId, true, $q, $perPage, $offset, $scopeUid, $scopeIsAdmin);
            echo json_encode(['ok' => true, 'page' => $page, 'per_page' => $perPage, 'data' => $rows], JSON_UNESCAPED_UNICODE);
            break;

        case 'tree':
            // Tam müfredat ağacı (öğretmen görev/müfredat ekranları için) — kapsamlı
            echo json_encode(['ok' => true, 'data' => education_get_full_tree($pdo, $scopeUid, $scopeIsAdmin)], JSON_UNESCAPED_UNICODE);
            break;

        case 'resources':
            // Filtreler: category_id, subject_id, topic_id, topic_q (konu adı), q (kaynak adı), type
            $f = [
                'category_id' => (int)($_GET['category_id'] ?? 0),
                'subject_id'  => (int)($_GET['subject_id'] ?? 0),
                'topic_id'    => (int)($_GET['topic_id'] ?? 0),
                'topic_q'     => trim($_GET['topic_q'] ?? ''),
                'q'           => trim($_GET['q'] ?? ''),
                'type'        => trim($_GET['type'] ?? ''),
            ];
            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(200, max(10, (int)($_GET['per_page'] ?? 50)));
            $rows = education_list_resources($pdo, $f, $perPage, ($page - 1) * $perPage);
            echo json_encode(['ok' => true, 'page' => $page, 'per_page' => $perPage, 'data' => $rows], JSON_UNESCAPED_UNICODE);
            break;

        case 'resource_topics':
            $resId = (int)($_GET['resource_id'] ?? 0);
            if ($resId <= 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'resource_id gerekli.']); break; }
            echo json_encode(['ok' => true, 'data' => education_get_resource_topics($pdo, $resId)], JSON_UNESCAPED_UNICODE);
            break;

        case 'topic_info':
            // Tek konunun kategori/ders kimlikleri — görev düzenlerken Müfredattan
            // sekmesini önceden seçili doldurmak için kullanılır.
            $topicId = (int)($_GET['topic_id'] ?? 0);
            if ($topicId <= 0) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'topic_id gerekli.']); break; }
            $st = $pdo->prepare("
                SELECT t.id, t.topic_name, t.subject_id, s.lesson_name, s.category_id, c.name AS category_name
                FROM education_topics t
                JOIN education_subjects s ON t.subject_id = s.id
                JOIN education_categories c ON s.category_id = c.id
                WHERE t.id = ? LIMIT 1
            ");
            $st->execute([$topicId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['ok' => false, 'error' => 'Konu bulunamadı.']); break; }
            echo json_encode(['ok' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Geçersiz action. Geçerli: categories, subjects, topics, resources, resource_topics, topic_info']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Sunucu hatası.']);
}
