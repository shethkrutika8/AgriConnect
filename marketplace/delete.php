<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

$stmt = $pdo->prepare("SELECT * FROM marketplace_listings WHERE id = ?");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if ($listing) {
    // Only seller or admin can delete
    if ($user['role'] !== 'admin' && $user['id'] != $listing['seller_id']) {
        redirect(BASE_URL . '/marketplace/view.php?id=' . $id, 'Unauthorized.', 'danger');
    }

    $pdo->prepare("DELETE FROM marketplace_listings WHERE id = ?")->execute([$id]);
    logActivity($user['id'], 'listing_deleted', "Deleted marketplace listing: {$listing['title']}");
    redirect(BASE_URL . '/marketplace/index.php', "Marketplace listing '{$listing['title']}' deleted successfully.", 'success');
} else {
    redirect(BASE_URL . '/marketplace/index.php', 'Listing not found.', 'danger');
}
