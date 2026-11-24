<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'orders';
$page_title  = 'Order Management';
$breadcrumb  = [['label' => 'Orders']];

$pdo  = getDB();
$user = currentUser();

// ── Filters & Search ──
$search  = trim($_GET['search'] ?? '');
$status  = trim($_GET['status'] ?? '');
$payment = trim($_GET['payment'] ?? '');
$per_page = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

// Role-based scoping: regular buyers only see their orders, farmers see their sales & purchases, admin sees all
if ($user['role'] === 'buyer') {
    $where[] = "o.buyer_id = ?";
    $params[] = $user['id'];
} elseif ($user['role'] === 'farmer') {
    $where[] = "(o.seller_id = ? OR o.buyer_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['id'];
}

if ($search) {
    $where[] = "(o.order_number LIKE ? OR u_b.name LIKE ? OR u_s.name LIKE ? OR ml.title LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($status) {
    $where[] = "o.status = ?";
    $params[] = $status;
}
if ($payment) {
    $where[] = "o.payment_status = ?";
    $params[] = $payment;
}

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM orders o
    LEFT JOIN users u_b ON o.buyer_id = u_b.id
    LEFT JOIN users u_s ON o.seller_id = u_s.id
    LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
    WHERE $whereStr
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

// Fetch orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           u_b.name as buyer_name, u_b.email as buyer_email,
           u_s.name as seller_name, u_s.email as seller_email,
           ml.title as listing_title, ml.type as item_type, ml.unit as listing_unit, ml.image as image
    FROM orders o
    LEFT JOIN users u_b ON o.buyer_id = u_b.id
    LEFT JOIN users u_s ON o.seller_id = u_s.id
    LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
    WHERE $whereStr
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// KPI Stats (scoped for role)
$userScope = "";
if ($user['role'] === 'buyer') {
    $userScope = " WHERE buyer_id = " . (int)$user['id'];
} elseif ($user['role'] === 'farmer') {
    $userScope = " WHERE (seller_id = " . (int)$user['id'] . " OR buyer_id = " . (int)$user['id'] . ")";
}
$whereOnly = $userScope ?: "";

$stats = [
    'total_orders' => (int)$pdo->query("SELECT COUNT(*) FROM orders" . $whereOnly)->fetchColumn(),
    'pending'      => (int)$pdo->query("SELECT COUNT(*) FROM orders" . ($userScope ? "$userScope AND status='pending'" : " WHERE status='pending'"))->fetchColumn(),
    'in_progress'  => (int)$pdo->query("SELECT COUNT(*) FROM orders" . ($userScope ? "$userScope AND status IN ('confirmed','processing','shipped')" : " WHERE status IN ('confirmed','processing','shipped')"))->fetchColumn(),
    'delivered'    => (int)$pdo->query("SELECT COUNT(*) FROM orders" . ($userScope ? "$userScope AND status='delivered'" : " WHERE status='delivered'"))->fetchColumn(),
    'revenue'      => (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders" . ($userScope ? "$userScope AND payment_status='paid'" : " WHERE payment_status='paid'"))->fetchColumn(),
];

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Orders Management</div>
      <div class="page-subtitle"><?= number_format($total) ?> orders found</div>
    </div>
    <div class="d-flex gap-8">
      <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Place New Order</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="stats-grid mb-24">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="fas fa-shopping-bag"></i></div>
      <div class="stat-content">
        <div class="label">Total Orders</div>
        <div class="value"><?= number_format($stats['total_orders']) ?></div>
      </div>
    </div>
    <div class="stat-card amber">
      <div class="stat-icon amber"><i class="fas fa-clock"></i></div>
      <div class="stat-content">
        <div class="label">Pending Confirmation</div>
        <div class="value"><?= number_format($stats['pending']) ?></div>
      </div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="fas fa-shipping-fast"></i></div>
      <div class="stat-content">
        <div class="label">In Fulfillment</div>
        <div class="value"><?= number_format($stats['in_progress']) ?></div>
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
      <div class="stat-content">
        <div class="label">Delivered</div>
        <div class="value"><?= number_format($stats['delivered']) ?></div>
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon green"><i class="fas fa-rupee-sign"></i></div>
      <div class="stat-content">
        <div class="label">Settled Revenue</div>
        <div class="value"><?= formatCurrency($stats['revenue']) ?></div>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar mb-24">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search order #, buyer, seller, item…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status">
      <option value="">All Order Statuses</option>
      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
      <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
      <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
      <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
      <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <select name="payment">
      <option value="">All Payment Statuses</option>
      <option value="paid" <?= $payment === 'paid' ? 'selected' : '' ?>>Paid</option>
      <option value="pending" <?= $payment === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="failed" <?= $payment === 'failed' ? 'selected' : '' ?>>Failed</option>
      <option value="refunded" <?= $payment === 'refunded' ? 'selected' : '' ?>>Refunded</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $status || $payment): ?>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Orders Table Card -->
  <div class="card">
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Date</th>
            <th>Item / Listing</th>
            <th>Buyer</th>
            <th>Seller</th>
            <th>Quantity</th>
            <th>Total Amount</th>
            <th>Payment</th>
            <th>Order Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
          <tr>
            <td colspan="10">
              <div class="empty-state">
                <img src="<?= assetImg('listings/default_listing.jpg') ?>" alt="No orders" style="width:120px;height:120px;object-fit:cover;border-radius:16px;margin:0 auto 16px;display:block;">
                <h3>No orders found</h3>
                <p>There are no orders matching your current criteria.</p>
                <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Place an Order</a>
              </div>
            </td>
          </tr>
          <?php else: foreach ($orders as $o): ?>
          <tr data-searchable>
            <td>
              <a href="view.php?id=<?= $o['id'] ?>" style="font-weight:700;color:var(--primary);">
                <?= htmlspecialchars($o['order_number'] ?: ('#ORD-' . $o['id'])) ?>
              </a>
            </td>
            <td>
              <div style="font-size:13px;"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
              <div class="text-muted text-sm"><?= timeAgo($o['created_at']) ?></div>
            </td>
            <td>
              <div class="d-flex align-center gap-10">
                <img src="<?= listingImage($o) ?>" alt="<?= htmlspecialchars($o['listing_title'] ?? 'Item') ?>" class="thumb-40" loading="lazy" width="44" height="44">
                <div style="font-weight:600;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                  <?= htmlspecialchars($o['listing_title'] ?: 'Agricultural Produce') ?>
                </div>
              </div>
            </td>
            <td>
              <div style="font-weight:500;"><?= htmlspecialchars($o['buyer_name'] ?? '—') ?></div>
              <div class="text-muted text-sm"><?= htmlspecialchars($o['buyer_email'] ?? '') ?></div>
            </td>
            <td>
              <div style="font-weight:500;"><?= htmlspecialchars($o['seller_name'] ?? '—') ?></div>
            </td>
            <td>
              <strong><?= number_format($o['quantity']) ?></strong> <?= htmlspecialchars($o['listing_unit'] ?? 'units') ?>
            </td>
            <td>
              <strong style="color:var(--secondary);font-size:15px;"><?= formatCurrency($o['total_amount']) ?></strong>
            </td>
            <td>
              <?php 
              $pCls = ['paid'=>'badge-success','pending'=>'badge-warning','failed'=>'badge-danger','refunded'=>'badge-secondary'][$o['payment_status']] ?? 'badge-secondary';
              ?>
              <span class="badge <?= $pCls ?>"><?= ucfirst($o['payment_status']) ?></span>
            </td>
            <td>
              <?php 
              $oCls = [
                  'pending'    => 'badge-warning',
                  'confirmed'  => 'badge-primary',
                  'processing' => 'badge-primary',
                  'shipped'    => 'badge-primary',
                  'delivered'  => 'badge-success',
                  'cancelled'  => 'badge-danger'
              ][$o['status']] ?? 'badge-secondary';
              ?>
              <span class="badge <?= $oCls ?>"><?= ucfirst($o['status']) ?></span>
            </td>
            <td>
              <div class="d-flex gap-8">
                <a href="view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-secondary" data-tooltip="View Invoice / Tracker">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if ($user['role'] === 'admin' || $user['id'] == $o['seller_id']): ?>
                <a href="view.php?id=<?= $o['id'] ?>#statusUpdate" class="btn btn-sm btn-outline-primary" data-tooltip="Update Status">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="delete.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Cancel or delete order <?= htmlspecialchars($o['order_number']) ?>?" data-tooltip="Cancel Order">
                  <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex align-center justify-between">
      <div class="text-muted text-sm">
        Showing <?= $pag['offset'] + 1 ?>–<?= min($pag['offset'] + $per_page, $total) ?> of <?= $total ?> orders
      </div>
      <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['current'] - 1])) ?>" class="page-link <?= !$pag['has_prev'] ? 'disabled' : '' ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($p = 1; $p <= $pag['total_pages']; $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-link <?= $p == $pag['current'] ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['current'] + 1])) ?>" class="page-link <?= !$pag['has_next'] ? 'disabled' : '' ?>"><i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
