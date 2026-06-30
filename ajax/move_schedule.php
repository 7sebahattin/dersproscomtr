<?php
require_once '../db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_id']) && isset($_POST['new_date'])) {
    $stmt = $pdo->prepare("UPDATE schedule_items SET date = ? WHERE id = ?");
    $stmt->execute([$_POST['new_date'], $_POST['item_id']]);
    echo "OK";
}
?>