<?php
require_once '../config/db.php';
requireLogin();
$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$crop = $pdo->prepare("SELECT name FROM crops WHERE id=?");
$crop->execute([$id]);
$crop = $crop->fetch();
if ($crop) {
    $pdo->prepare("DELETE FROM crops WHERE id=?")->execute([$id]);
    logActivity(currentUser()['id'], 'crop_deleted', "Deleted crop: {$crop['name']}");
    redirect(BASE_URL . '/crops/index.php', "Crop '{$crop['name']}' deleted successfully.", 'success');
} else {
    redirect(BASE_URL . '/crops/index.php', 'Crop not found.', 'danger');
}
