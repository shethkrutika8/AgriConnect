<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if ($order) {
    // Only admin or seller can delete/cancel
    if ($user['role'] !== 'admin' && $user['id'] != $order['seller_id']) {
        redirect(BASE_URL . '/orders/view.php?id=' . $id, 'Unauthorized.', 'danger');
    }

    // Return stock to listing if order was not delivered
    if ($order['status'] !== 'delivered' && !empty($order['listing_id'])) {
        $pdo->prepare("UPDATE marketplace_listings SET quantity = quantity + ?, status = 'active' WHERE id = ?")
            ->execute([$order['quantity'], $order['listing_id']]);
    }

    $pdo->prepare("DELETE FROM orders WHERE id = ?")->execute([$id]);
    logActivity($user['id'], 'order_deleted', "Deleted order: {$order['order_number']}");
    redirect(BASE_URL . '/orders/index.php', "Order {$order['order_number']} has been removed.", 'success');
} else {
    redirect(BASE_URL . '/orders/index.php', 'Order not found.', 'danger');
}
