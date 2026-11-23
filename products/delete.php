<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    // Delete product
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    // Also remove from marketplace listings if listed
    $pdo->prepare("DELETE FROM marketplace_listings WHERE type='product' AND item_id = ?")->execute([$id]);

    logActivity(currentUser()['id'], 'product_deleted', "Deleted product: {$product['name']}");
    redirect(BASE_URL . '/products/index.php', "Product '{$product['name']}' was successfully deleted.", 'success');
} else {
    redirect(BASE_URL . '/products/index.php', 'Product not found.', 'danger');
}
