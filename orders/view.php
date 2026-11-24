<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

$stmt = $pdo->prepare("
    SELECT o.*,
           u_b.id as buyer_id, u_b.name as buyer_name, u_b.email as buyer_email, u_b.phone as buyer_phone,
           u_s.id as seller_id, u_s.name as seller_name, u_s.email as seller_email, u_s.phone as seller_phone,
           f.farm_name, f.location as farm_location,
           ml.id as listing_id, ml.title as listing_title, ml.type as item_type, ml.unit as listing_unit, ml.image as image
    FROM orders o
    LEFT JOIN users u_b ON o.buyer_id = u_b.id
    LEFT JOIN users u_s ON o.seller_id = u_s.id
    LEFT JOIN farmers f ON f.user_id = u_s.id
    LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
    WHERE o.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect(BASE_URL . '/orders/index.php', 'Order not found.', 'danger');
}

// Check permission
if ($user['role'] !== 'admin' && $user['id'] != $order['buyer_id'] && $user['id'] != $order['seller_id']) {
    redirect(BASE_URL . '/orders/index.php', 'Unauthorized to view this order.', 'danger');
}

// Status step mapping
$statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$currentStep = array_search($order['status'], $statuses);
if ($currentStep === false && $order['status'] === 'cancelled') {
    $isCancelled = true;
} else {
    $isCancelled = false;
}

$active_page = 'orders';
$page_title  = 'Order ' . ($order['order_number'] ?: '#' . $order['id']);
$breadcrumb  = [
    ['label' => 'Orders', 'url' => BASE_URL . '/orders/index.php'],
    ['label' => $order['order_number'] ?: ('#' . $order['id'])]
];

include '../includes/header.php';
?>

<style>
/* Status Stepper Tracker */
.stepper-track {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 24px 0 32px;
}
.stepper-track::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 40px;
    right: 40px;
    height: 4px;
    background: var(--gray-200);
    z-index: 1;
}
.stepper-step {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 80px;
    text-align: center;
}
.stepper-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--white);
    border: 3px solid var(--gray-300);
    color: var(--gray-400);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    margin-bottom: 8px;
    transition: var(--transition);
}
.stepper-step.active .stepper-icon {
    border-color: var(--primary);
    background: var(--primary);
    color: var(--white);
    box-shadow: 0 0 0 4px var(--primary-pale);
}
.stepper-step.completed .stepper-icon {
    border-color: var(--primary);
    background: var(--primary);
    color: var(--white);
}
.stepper-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--gray-500);
    text-transform: capitalize;
}
.stepper-step.active .stepper-label,
.stepper-step.completed .stepper-label {
    color: var(--primary-dark);
}

@media print {
    .sidebar, .topbar, .btn, .no-print, #statusUpdate { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

<div class="page-content">
  <!-- Header with Actions -->
  <div class="page-header no-print">
    <div>
      <div class="page-title"><?= htmlspecialchars($order['order_number'] ?: ('#ORD-' . $order['id'])) ?></div>
      <div class="page-subtitle">Placed on <?= date('d M Y \a\t h:i A', strtotime($order['created_at'])) ?></div>
    </div>
    <div class="d-flex gap-8">
      <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Print Invoice</button>
      <?php if ($user['role'] === 'admin' || $user['id'] == $order['seller_id']): ?>
      <a href="#statusUpdate" class="btn btn-primary"><i class="fas fa-tasks"></i> Update Status</a>
      <a href="delete.php?id=<?= $order['id'] ?>" class="btn btn-outline-danger" data-confirm="Are you sure you want to cancel/delete this order?"><i class="fas fa-trash"></i> Cancel</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> All Orders</a>
    </div>
  </div>

  <!-- Status Tracker Card -->
  <div class="card mb-24">
    <div class="card-header d-flex justify-between align-center">
      <div class="card-title"><i class="fas fa-shipping-fast" style="color:var(--primary);margin-right:8px;"></i>Order Fulfillment Progress</div>
      <div>
        <?php 
        $oCls = ['pending'=>'badge-warning','confirmed'=>'badge-primary','processing'=>'badge-primary','shipped'=>'badge-primary','delivered'=>'badge-success','cancelled'=>'badge-danger'][$order['status']] ?? 'badge-secondary';
        $pCls = ['paid'=>'badge-success','pending'=>'badge-warning','failed'=>'badge-danger','refunded'=>'badge-secondary'][$order['payment_status']] ?? 'badge-secondary';
        ?>
        <span class="badge <?= $pCls ?>" style="margin-right:6px;">Payment: <?= ucfirst($order['payment_status']) ?></span>
        <span class="badge <?= $oCls ?>">Order: <?= ucfirst($order['status']) ?></span>
      </div>
    </div>
    <div class="card-body">
      <?php if ($isCancelled): ?>
      <div class="alert alert-danger" style="margin: 12px 0;">
        <i class="fas fa-times-circle"></i>
        <div>This order was <strong>Cancelled</strong>.</div>
      </div>
      <?php else: ?>
      <div class="stepper-track">
        <?php 
        $icons = ['clock', 'clipboard-check', 'cogs', 'truck', 'check-double'];
        foreach ($statuses as $idx => $s): 
            $cls = '';
            if ($idx < $currentStep) $cls = 'completed';
            elseif ($idx === $currentStep) $cls = 'active';
        ?>
        <div class="stepper-step <?= $cls ?>">
          <div class="stepper-icon"><i class="fas fa-<?= $icons[$idx] ?>"></i></div>
          <div class="stepper-label"><?= ucfirst($s) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Invoice Content -->
  <div class="grid-2 mb-24">
    <!-- Buyer Info -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-user" style="color:var(--primary);margin-right:8px;"></i>Buyer / Delivery Destination</div></div>
      <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
        <div style="font-size:15px;font-weight:700;"><?= htmlspecialchars($order['buyer_name'] ?? 'Guest Buyer') ?></div>
        <div><i class="fas fa-envelope" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($order['buyer_email'] ?? '—') ?></div>
        <div><i class="fas fa-phone" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($order['buyer_phone'] ?: '—') ?></div>
        <hr class="divider" style="margin:8px 0;">
        <div>
          <strong style="color:var(--gray-800);">Shipping Address:</strong><br>
          <span style="color:var(--gray-600);"><?= nl2br(htmlspecialchars($order['delivery_address'] ?: 'No address specified.')) ?></span>
        </div>
        <?php if (!empty($order['notes'])): ?>
        <div style="margin-top:6px;">
          <strong style="color:var(--gray-800);">Order Notes:</strong><br>
          <span style="color:var(--gray-600);"><?= htmlspecialchars($order['notes']) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Seller / Farm Info -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px;"></i>Supplier / Farm Details</div></div>
      <div class="card-body" style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
        <div style="font-size:15px;font-weight:700;"><?= htmlspecialchars($order['farm_name'] ?: $order['seller_name']) ?></div>
        <div><i class="fas fa-user-tie" style="color:var(--primary);width:16px;"></i> Seller: <?= htmlspecialchars($order['seller_name'] ?? '—') ?></div>
        <div><i class="fas fa-envelope" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($order['seller_email'] ?? '—') ?></div>
        <div><i class="fas fa-phone" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($order['seller_phone'] ?: '—') ?></div>
        <?php if (!empty($order['farm_location'])): ?>
        <div><i class="fas fa-map-marker-alt" style="color:var(--primary);width:16px;"></i> Farm Location: <?= htmlspecialchars($order['farm_location']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Item Breakdown Table -->
  <div class="card mb-24">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-receipt" style="color:var(--primary);margin-right:8px;"></i>Invoice Line Items</div>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Item Description</th>
            <th>Type</th>
            <th>Unit Price</th>
            <th>Quantity</th>
            <th style="text-align:right;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <div class="d-flex align-center gap-12">
                <img src="<?= listingImage($order) ?>" alt="<?= htmlspecialchars($order['listing_title'] ?? 'Item') ?>" class="thumb-40" loading="lazy" width="44" height="44">
                <div>
                  <div style="font-weight:700;font-size:15px;"><?= htmlspecialchars($order['listing_title'] ?: 'Agricultural Produce') ?></div>
                  <?php if (!empty($order['listing_id'])): ?>
                  <div class="text-muted text-sm no-print">
                    <a href="../marketplace/view.php?id=<?= $order['listing_id'] ?>" style="color:var(--primary);">View Marketplace Listing #<?= $order['listing_id'] ?></a>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><span class="badge badge-primary"><?= ucfirst($order['item_type'] ?: 'item') ?></span></td>
            <td><?= formatCurrency($order['unit_price'] ?: ($order['total_amount'] / max(1, $order['quantity']))) ?></td>
            <td><?= number_format($order['quantity']) ?> <?= htmlspecialchars($order['listing_unit'] ?? 'units') ?></td>
            <td style="text-align:right;font-weight:700;font-size:16px;">
              <?= formatCurrency($order['total_amount']) ?>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr style="background:var(--gray-50);font-weight:700;">
            <td colspan="4" style="text-align:right;font-size:15px;">Total Order Amount:</td>
            <td style="text-align:right;font-size:18px;color:var(--secondary);">
              <?= formatCurrency($order['total_amount']) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Status Update Form for Admin & Seller -->
  <?php if ($user['role'] === 'admin' || $user['id'] == $order['seller_id']): ?>
  <div class="card no-print" id="statusUpdate">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Manage Order & Payment Status</div>
    </div>
    <form action="update_status.php" method="POST" class="card-body">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Fulfillment Status</label>
          <select name="status" class="form-control">
            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending Confirmation</option>
            <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing / Packing</option>
            <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped / In Transit</option>
            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Status</label>
          <select name="payment_status" class="form-control">
            <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending Payment</option>
            <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid (Completed)</option>
            <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-between align-center">
        <div class="text-sm text-muted">Updating this will notify the buyer and log system activity.</div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Status Updates</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
