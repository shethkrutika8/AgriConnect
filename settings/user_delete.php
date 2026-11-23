<?php
require_once '../config/db.php';
requireAdmin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

// Prevent self deletion
if ($id == $user['id']) {
    redirect(BASE_URL . '/settings/users.php', 'You cannot delete your own active administrator account.', 'danger');
}

$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$id]);
$target = $stmt->fetch();

if ($target) {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    logActivity($user['id'], 'user_deleted', "Admin deleted user: {$target['name']} (ID: $id)");
    redirect(BASE_URL . '/settings/users.php', "User '{$target['name']}' was successfully deleted.", 'success');
} else {
    redirect(BASE_URL . '/settings/users.php', 'User not found.', 'danger');
}
