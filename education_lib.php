<?php
/**
 * education_lib.php — Yeni (bağımsız) müfredat sistemi: şema + yardımcılar
 *
 * ÖNEMLİ: Bu sistem, mevcut müfredat yapısından (coaching_subjects,
 * coaching_topics, schedule_items, materials) TAMAMEN BAĞIMSIZDIR.
 * Eski tablolara hiçbir şekilde dokunmaz.
 *
 * Tablolar:
 *  - education_categories : TYT, AYT, 9-12. Sınıf
 *  - education_subjects   : kategoriye bağlı dersler
 *  - education_topics     : derse bağlı konular (sıralama + aktif/pasif)
 *  - resource_topics      : kaynak <-> konu çoktan çoğa pivot
 */

/**
 * Tabloları (yoksa) oluşturur; kategoriler boşsa seed verisini yükler.
 * Idempotent: her çağrıda güvenle çalıştırılabilir, mevcut veriyi değiştirmez.
 */
function education_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS education_categories (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL UNIQUE,
        display_order INT NOT NULL DEFAULT 0,
        is_active     TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS education_subjects (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        category_id   INT NOT NULL,
        lesson_name   VARCHAR(100) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active     TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_cat_lesson (category_id, lesson_name),
        KEY idx_edu_subj_cat (category_id),
        CONSTRAINT fk_edu_subj_cat FOREIGN KEY (category_id)
            REFERENCES education_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS education_topics (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        subject_id    INT NOT NULL,
        topic_name    VARCHAR(255) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        status        TINYINT(1) NOT NULL DEFAULT 1,
        KEY idx_edu_topic_subj (subject_id),
        KEY idx_edu_topic_status (status),
        KEY idx_edu_topic_name (topic_name),
        CONSTRAINT fk_edu_topic_subj FOREIGN KEY (subject_id)
            REFERENCES education_subjects(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    // Yeni bağımsız kaynak havuzu (kitap / deneme / pdf / video ...)
    $pdo->exec("CREATE TABLE IF NOT EXISTS education_resources (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        title        VARCHAR(255) NOT NULL,
        type         ENUM('kitap','deneme','pdf','video','diger') NOT NULL DEFAULT 'kitap',
        description  TEXT NULL,
        external_url VARCHAR(500) NULL,
        created_by   INT NULL,
        is_active    TINYINT(1) NOT NULL DEFAULT 1,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_res_active (is_active),
        KEY idx_res_title (title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    // resource_id -> education_resources.id (silme temizliği kod tarafında yapılır)
    $pdo->exec("CREATE TABLE IF NOT EXISTS resource_topics (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        resource_id INT NOT NULL,
        topic_id    INT NOT NULL,
        UNIQUE KEY uq_res_topic (resource_id, topic_id),
        KEY idx_rt_resource (resource_id),
        KEY idx_rt_topic (topic_id),
        CONSTRAINT fk_rt_topic FOREIGN KEY (topic_id)
            REFERENCES education_topics(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    // Seed: yalnızca kategoriler tablosu TAMAMEN BOŞSA
    $count = (int)$pdo->query("SELECT COUNT(*) FROM education_categories")->fetchColumn();
    if ($count === 0) {
        education_run_seed($pdo);
    }

    // ── Faz: iki katmanlı sahiplik + kilit kolonları (idempotent yükseltme) ──
    // created_by NULL = admin/seed içeriği. is_locked=1 = global + silinemez.
    foreach (['education_categories', 'education_subjects', 'education_topics', 'education_resources'] as $t) {
        $cols = $pdo->query("SHOW COLUMNS FROM $t")->fetchAll(PDO::FETCH_COLUMN);
        $justAdded = false;
        if (!in_array('created_by', $cols)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN created_by INT NULL DEFAULT NULL");
            $justAdded = true;
        }
        if (!in_array('is_locked', $cols)) {
            $pdo->exec("ALTER TABLE $t ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0");
            $justAdded = true;
        }
        if ($justAdded) {
            // Mevcut (seed/admin) satırlar: sahipsizler kilitli-global kabul edilir
            $pdo->exec("UPDATE $t SET is_locked = 1 WHERE created_by IS NULL");
        }
    }
}

/**
 * Görünürlük koşulu: admin her şeyi görür; öğretmen yalnızca
 * kilitli/global içerik + admin(seed) içeriği + KENDİ ekledikleri.
 * Dönen: [whereSql, params]
 */
function education_visibility_where(string $alias, ?int $uid, bool $isAdmin): array
{
    if ($isAdmin || $uid === null) return ['1=1', []];
    return ["($alias.is_locked = 1 OR $alias.created_by IS NULL OR $alias.created_by = ?)", [$uid]];
}

/** Kayıt silinebilir mi? Kilitliyse HİÇ KİMSE silemez (admin önce kilidi açmalı). */
function education_can_delete(array $row, int $uid, bool $isAdmin): bool
{
    if ((int)($row['is_locked'] ?? 0) === 1) return false;
    if ($isAdmin) return true;
    return (int)($row['created_by'] ?? 0) === $uid;
}

/** Kayıt düzenlenebilir mi? Kilitli içeriği yalnızca admin düzenler. */
function education_can_edit_item(array $row, int $uid, bool $isAdmin): bool
{
    if ((int)($row['is_locked'] ?? 0) === 1) return $isAdmin;
    if ($isAdmin) return true;
    return (int)($row['created_by'] ?? 0) === $uid;
}

/** Seed verisini yükler (yalnızca ilk kurulumda çağrılır). */
function education_run_seed(PDO $pdo): void
{
    $seedFile = __DIR__ . '/education_seed_data.php';
    if (!file_exists($seedFile)) return;
    $seed = require $seedFile;
    if (!is_array($seed)) return;

    $insCat   = $pdo->prepare("INSERT INTO education_categories (name, display_order) VALUES (?, ?)");
    $insSubj  = $pdo->prepare("INSERT INTO education_subjects (category_id, lesson_name, display_order) VALUES (?, ?, ?)");
    $insTopic = $pdo->prepare("INSERT INTO education_topics (subject_id, topic_name, display_order) VALUES (?, ?, ?)");

    $pdo->beginTransaction();
    try {
        $catOrder = 0;
        foreach ($seed as $catName => $subjects) {
            $insCat->execute([$catName, ++$catOrder]);
            $catId = (int)$pdo->lastInsertId();

            $subjOrder = 0;
            foreach ($subjects as $lessonName => $topics) {
                $insSubj->execute([$catId, $lessonName, ++$subjOrder]);
                $subjId = (int)$pdo->lastInsertId();

                $topicOrder = 0;
                foreach ($topics as $topicName) {
                    $insTopic->execute([$subjId, $topicName, ++$topicOrder]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Aktif kategorileri sıralı döndürür.
 * $scopeUid verilirse (ve $scopeIsAdmin=false) öğretmen görünürlük kuralı uygulanır.
 */
function education_get_categories(PDO $pdo, bool $onlyActive = true,
                                  ?int $scopeUid = null, bool $scopeIsAdmin = true): array
{
    [$vis, $params] = education_visibility_where('c', $scopeUid, $scopeIsAdmin);
    $sql = "SELECT c.id, c.name, c.display_order, c.is_active, c.created_by, c.is_locked
            FROM education_categories c WHERE $vis" .
           ($onlyActive ? " AND c.is_active = 1" : "") .
           " ORDER BY c.display_order, c.name";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Bir kategorinin derslerini sıralı döndürür (görünürlük kapsamı destekli). */
function education_get_subjects(PDO $pdo, int $categoryId, bool $onlyActive = true,
                                ?int $scopeUid = null, bool $scopeIsAdmin = true): array
{
    [$vis, $vparams] = education_visibility_where('s', $scopeUid, $scopeIsAdmin);
    $sql = "SELECT s.id, s.category_id, s.lesson_name, s.display_order, s.is_active, s.created_by, s.is_locked
            FROM education_subjects s WHERE s.category_id = ? AND $vis" .
           ($onlyActive ? " AND s.is_active = 1" : "") .
           " ORDER BY s.display_order, s.lesson_name";
    $st = $pdo->prepare($sql);
    $st->execute(array_merge([$categoryId], $vparams));
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Bir dersin konularını döndürür (arama + sayfalama + görünürlük kapsamı).
 * N+1 önlemek için tek sorgu; konu sayısı yüksek dersler için LIMIT/OFFSET.
 */
function education_get_topics(PDO $pdo, int $subjectId, bool $onlyActive = true,
                              string $search = '', int $limit = 500, int $offset = 0,
                              ?int $scopeUid = null, bool $scopeIsAdmin = true): array
{
    [$vis, $vparams] = education_visibility_where('t', $scopeUid, $scopeIsAdmin);
    $sql = "SELECT t.id, t.subject_id, t.topic_name, t.display_order, t.status, t.created_by, t.is_locked
            FROM education_topics t WHERE t.subject_id = ? AND $vis";
    $params = array_merge([$subjectId], $vparams);
    if ($onlyActive)   { $sql .= " AND t.status = 1"; }
    if ($search !== '') { $sql .= " AND t.topic_name LIKE ?"; $params[] = '%' . $search . '%'; }
    $sql .= " ORDER BY t.display_order, t.topic_name LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Bir kaynağa bağlı konuları (kategori/ders bilgisiyle) döndürür. */
function education_get_resource_topics(PDO $pdo, int $resourceId): array
{
    $st = $pdo->prepare(
        "SELECT t.id, t.topic_name, t.display_order, t.status,
                s.id AS subject_id, s.lesson_name,
                c.id AS category_id, c.name AS category_name
         FROM resource_topics rt
         JOIN education_topics t   ON t.id = rt.topic_id
         JOIN education_subjects s ON s.id = t.subject_id
         JOIN education_categories c ON c.id = s.category_id
         WHERE rt.resource_id = ?
         ORDER BY c.display_order, s.display_order, t.display_order"
    );
    $st->execute([$resourceId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Oturumdaki kullanıcı admin mi? (müfredatı yalnızca admin düzenleyebilir) */
function education_is_admin(): bool
{
    return isset($_SESSION['user_id'])
        && in_array($_SESSION['role'] ?? '', ['admin', 'superuser'], true);
}

/** Kaynak oluşturabilir mi? (öğretmenler seçim/kaynak ekleyebilir, admin her şeyi) */
function education_can_manage_resources(): bool
{
    return isset($_SESSION['user_id'])
        && in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'superuser'], true);
}

/**
 * Kaynakları filtreleyerek listeler. Tek sorgu (N+1 yok).
 * Filtreler: category_id, subject_id, topic_id, topic_q (konu adı), q (kaynak adı), type
 */
function education_list_resources(PDO $pdo, array $f = [], int $limit = 200, int $offset = 0): array
{
    $where  = ["r.is_active = 1"];
    $params = [];

    if (!empty($f['q'])) { $where[] = "r.title LIKE ?"; $params[] = '%' . $f['q'] . '%'; }
    if (!empty($f['type'])) { $where[] = "r.type = ?"; $params[] = $f['type']; }

    // Konu tabanlı filtreler: EXISTS ile (kaynak başına tek satır korunur)
    $topicConds = []; $topicParams = [];
    if (!empty($f['topic_id']))    { $topicConds[] = "t.id = ?";          $topicParams[] = (int)$f['topic_id']; }
    if (!empty($f['subject_id']))  { $topicConds[] = "t.subject_id = ?";  $topicParams[] = (int)$f['subject_id']; }
    if (!empty($f['category_id'])) { $topicConds[] = "s.category_id = ?"; $topicParams[] = (int)$f['category_id']; }
    if (!empty($f['topic_q']))     { $topicConds[] = "t.topic_name LIKE ?"; $topicParams[] = '%' . $f['topic_q'] . '%'; }

    if ($topicConds) {
        $where[] = "EXISTS (SELECT 1 FROM resource_topics rt
                            JOIN education_topics t ON t.id = rt.topic_id
                            JOIN education_subjects s ON s.id = t.subject_id
                            WHERE rt.resource_id = r.id AND " . implode(' AND ', $topicConds) . ")";
        $params = array_merge($params, $topicParams);
    }

    $sql = "SELECT r.*, COALESCE(tc.cnt, 0) AS topic_count, u.first_name, u.last_name
            FROM education_resources r
            LEFT JOIN (SELECT resource_id, COUNT(*) cnt FROM resource_topics GROUP BY resource_id) tc
                   ON tc.resource_id = r.id
            LEFT JOIN users u ON u.id = r.created_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Kaynağın konu bağlantılarını topluca değiştirir (transaction). */
function education_set_resource_topics(PDO $pdo, int $resourceId, array $topicIds): void
{
    $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds), fn($v) => $v > 0)));
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM resource_topics WHERE resource_id = ?")->execute([$resourceId]);
        if ($topicIds) {
            $ins = $pdo->prepare("INSERT IGNORE INTO resource_topics (resource_id, topic_id) VALUES (?, ?)");
            foreach ($topicIds as $tid) { $ins->execute([$resourceId, $tid]); }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Tüm aktif müfredat ağacını tek seferde döndürür (kaynak formu accordion'u için).
 * 3 sorgu toplam — N+1 yok. [category][subject] => topics
 * Kapsam verilirse öğretmen yalnızca kendi + kilitli/global içeriği görür.
 */
function education_get_full_tree(PDO $pdo, ?int $scopeUid = null, bool $scopeIsAdmin = true): array
{
    $cats = education_get_categories($pdo, true, $scopeUid, $scopeIsAdmin);

    [$visS, $pS] = education_visibility_where('s', $scopeUid, $scopeIsAdmin);
    $stS = $pdo->prepare("SELECT s.id, s.category_id, s.lesson_name, s.created_by, s.is_locked
                          FROM education_subjects s WHERE s.is_active = 1 AND $visS
                          ORDER BY s.display_order, s.lesson_name");
    $stS->execute($pS);
    $subs = $stS->fetchAll(PDO::FETCH_ASSOC);

    [$visT, $pT] = education_visibility_where('t', $scopeUid, $scopeIsAdmin);
    $stT = $pdo->prepare("SELECT t.id, t.subject_id, t.topic_name, t.created_by, t.is_locked
                          FROM education_topics t WHERE t.status = 1 AND $visT
                          ORDER BY t.display_order, t.topic_name");
    $stT->execute($pT);
    $tops = $stT->fetchAll(PDO::FETCH_ASSOC);

    $topicsBySubj = [];
    foreach ($tops as $t) { $topicsBySubj[$t['subject_id']][] = $t; }

    $subsByCat = [];
    foreach ($subs as $s) {
        $s['topics'] = $topicsBySubj[$s['id']] ?? [];
        $subsByCat[$s['category_id']][] = $s;
    }

    $tree = [];
    foreach ($cats as $c) {
        $c['subjects'] = $subsByCat[$c['id']] ?? [];
        $tree[] = $c;
    }
    return $tree;
}
