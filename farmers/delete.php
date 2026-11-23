<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT farm_name FROM farmers WHERE id = ?");
$stmt->execute([$id]);
$farmer = $stmt->fetch();

if ($farmer) {
    // Delete farmer profile
    $pdo->prepare("DELETE FROM farmers WHERE id = ?")->execute([$id]);
    logActivity(currentUser()['id'], 'farmer_deleted', "Deleted farmer profile: {$farmer['farm_name']}");
    redirect(BASE_URL . '/farmers/index.php', "Farmer profile '{$farmer['farm_name']}' was successfully deleted.", 'success');
} else {
    redirect(BASE_URL . '/farmers/index.php', 'Farmer profile not found.', 'danger');
}
