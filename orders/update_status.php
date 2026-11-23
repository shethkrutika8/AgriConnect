<?php
require_once '../config/db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/orders/index.php');
}

$orderId   = (int)($_POST['order_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');
$newPay    = trim($_POST['payment_status'] ?? '');

$pdo  = getDB();
$user = currentUser();

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    redirect(BASE_URL . '/orders/index.php', 'Order not found.', 'danger');
}

// Check permission: admin or seller
if ($user['role'] !== 'admin' && $user['id'] != $order['seller_id']) {
    redirect(BASE_URL . '/orders/view.php?id=' . $orderId, 'Unauthorized to update order.', 'danger');
}

$validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
$validPayments = ['pending', 'paid', 'failed', 'refunded'];

if (in_array($newStatus, $validStatuses) && in_array($newPay, $validPayments)) {
    $pdo->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?")
        ->execute([$newStatus, $newPay, $orderId]);

    // If cancelled, optionally return stock to listing
    if ($newStatus === 'cancelled' && $order['status'] !== 'cancelled' && !empty($order['listing_id'])) {
        $pdo->prepare("UPDATE marketplace_listings SET quantity = quantity + ?, status = 'active' WHERE id = ?")
            ->execute([$order['quantity'], $order['listing_id']]);
    }

    logActivity($user['id'], 'order_status_updated', "Updated order #{$order['order_number']} status to '$newStatus', payment to '$newPay'");
    redirect(BASE_URL . '/orders/view.php?id=' . $orderId, "Order status updated successfully!", 'success');
} else {
    redirect(BASE_URL . '/orders/view.php?id=' . $orderId, 'Invalid status selection.', 'danger');
}
