<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'orders';
$page_title  = 'Place New Order';
$breadcrumb  = [
    ['label' => 'Orders', 'url' => BASE_URL . '/orders/index.php'],
    ['label' => 'New Order']
];

$pdo  = getDB();
$user = currentUser();

// Fetch active marketplace listings
$listings = $pdo->query("
    SELECT ml.id, ml.title, ml.price, ml.quantity, ml.unit, ml.seller_id,
           u.name as seller_name, f.farm_name
    FROM marketplace_listings ml
    JOIN users u ON ml.seller_id = u.id
    LEFT JOIN farmers f ON f.user_id = u.id
    WHERE ml.status = 'active' AND ml.quantity > 0
    ORDER BY ml.title ASC
")->fetchAll();

// Users who can be buyers (if admin creating order for a user)
$buyers = $pdo->query("SELECT id, name, email FROM users ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listingId = (int)($_POST['listing_id'] ?? 0);
    $buyerId   = ($user['role'] === 'admin') ? (int)($_POST['buyer_id'] ?? $user['id']) : $user['id'];
    $orderQty  = (float)($_POST['quantity'] ?? 0);
    $address   = trim($_POST['delivery_address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $payStatus = trim($_POST['payment_status'] ?? 'pending');
    $ordStatus = trim($_POST['status'] ?? 'pending');

    if (!$listingId) {
        $errors['listing_id'] = 'Please select an item to order.';
    }
    if ($orderQty <= 0) {
        $errors['quantity'] = 'Quantity must be greater than zero.';
    }
    if (empty($address)) {
        $errors['delivery_address'] = 'Delivery address is required.';
    }

    if (empty($errors)) {
        // Fetch listing to verify quantity and seller
        $lStmt = $pdo->prepare("SELECT * FROM marketplace_listings WHERE id = ?");
        $lStmt->execute([$listingId]);
        $listing = $lStmt->fetch();

        if (!$listing) {
            $errors['listing_id'] = 'Selected listing was not found.';
        } elseif ($orderQty > $listing['quantity']) {
            $errors['quantity'] = "Maximum available stock is {$listing['quantity']} {$listing['unit']}.";
        } else {
            $unitPrice   = (float)$listing['price'];
            $totalAmount = $orderQty * $unitPrice;
            $orderNum    = 'ORD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));

            $ins = $pdo->prepare("
                INSERT INTO orders (order_number, buyer_id, seller_id, listing_id, quantity, unit_price, total_amount, status, payment_status, delivery_address, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ins->execute([
                $orderNum,
                $buyerId,
                $listing['seller_id'],
                $listingId,
                $orderQty,
                $unitPrice,
                $totalAmount,
                $ordStatus,
                $payStatus,
                $address,
                $notes
            ]);
            $orderId = $pdo->lastInsertId();

            // Deduct stock
            $remQty = max(0, $listing['quantity'] - $orderQty);
            $newStatus = ($remQty <= 0) ? 'sold' : $listing['status'];
            $pdo->prepare("UPDATE marketplace_listings SET quantity = ?, status = ? WHERE id = ?")
                ->execute([$remQty, $newStatus, $listingId]);

            logActivity($user['id'], 'order_created', "Created order $orderNum for {$listing['title']}");
            redirect(BASE_URL . '/orders/view.php?id=' . $orderId, "Order $orderNum created successfully!", 'success');
        }
    }
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Place New Order</div>
      <div class="page-subtitle">Create a purchase order for marketplace produce</div>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Orders</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-shopping-cart" style="color:var(--primary);margin-right:8px;"></i>Order Details</div>
    </div>
    <div class="card-body">
      
      <div class="form-row">
        <!-- Marketplace Listing Selection -->
        <div class="form-group">
          <label class="form-label">Select Marketplace Item <span class="req">*</span></label>
          <select name="listing_id" id="listingSelect" class="form-control" required onchange="onListingChange(this)">
            <option value="">-- Choose Item from Marketplace --</option>
            <?php foreach ($listings as $l): ?>
            <option value="<?= $l['id'] ?>"
                    data-price="<?= $l['price'] ?>"
                    data-unit="<?= htmlspecialchars($l['unit']) ?>"
                    data-max="<?= $l['quantity'] ?>"
                    data-seller="<?= htmlspecialchars($l['farm_name'] ?: $l['seller_name']) ?>">
              <?= htmlspecialchars($l['title']) ?> (₹<?= $l['price'] ?>/<?= $l['unit'] ?> · <?= $l['quantity'] ?> <?= $l['unit'] ?> left)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($user['role'] === 'admin'): ?>
        <div class="form-group">
          <label class="form-label">Buyer Account <span class="req">*</span></label>
          <select name="buyer_id" class="form-control">
            <?php foreach ($buyers as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ($user['id'] == $b['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['name'] . ' (' . $b['email'] . ')') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="buyer_id" value="<?= $user['id'] ?>">
        <?php endif; ?>
      </div>

      <!-- Quantity & Dynamic Price Calculation -->
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Quantity <span class="req">*</span></label>
          <div class="input-group">
            <input type="number" name="quantity" id="orderQtyInput" class="form-control" 
                   placeholder="1" min="1" step="any" required oninput="calculateOrderTotal()">
            <span id="unitDisplay" style="background:var(--gray-100);padding:10px 14px;border:1px solid var(--gray-300);border-left:none;border-radius:0 8px 8px 0;font-weight:600;">
              units
            </span>
          </div>
          <div class="text-sm text-muted" id="maxStockNotice" style="margin-top:4px;"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Unit Price (₹)</label>
          <input type="text" id="unitPriceDisplay" class="form-control" readonly placeholder="0.00" style="background:var(--gray-100);">
        </div>

        <div class="form-group">
          <label class="form-label">Estimated Total (₹)</label>
          <input type="text" id="totalPriceDisplay" class="form-control" readonly placeholder="₹ 0.00" style="background:var(--primary-pale);font-weight:700;color:var(--secondary);">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Order Status</label>
          <select name="status" class="form-control">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Status</label>
          <select name="payment_status" class="form-control">
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Delivery Address <span class="req">*</span></label>
        <textarea name="delivery_address" class="form-control" rows="3" placeholder="Enter complete destination address, city, state and PIN..." required></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Delivery Notes / Requirements</label>
        <input type="text" name="notes" class="form-control" placeholder="e.g. Standard dispatch, urgent bulk delivery">
      </div>

    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Create Order</button>
    </div>
  </form>
</div>

<script>
let currentPrice = 0;
let currentMax = 0;

function onListingChange(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        currentPrice = parseFloat(opt.dataset.price) || 0;
        currentMax   = parseFloat(opt.dataset.max) || 0;
        const unit   = opt.dataset.unit || 'units';

        document.getElementById('unitDisplay').innerText = unit;
        document.getElementById('unitPriceDisplay').value = '₹ ' + currentPrice.toFixed(2);
        document.getElementById('maxStockNotice').innerText = 'Available stock: ' + currentMax + ' ' + unit;
        document.getElementById('orderQtyInput').max = currentMax;
        calculateOrderTotal();
    } else {
        currentPrice = 0;
        currentMax = 0;
        document.getElementById('unitDisplay').innerText = 'units';
        document.getElementById('unitPriceDisplay').value = '';
        document.getElementById('maxStockNotice').innerText = '';
        document.getElementById('totalPriceDisplay').value = '';
    }
}

function calculateOrderTotal() {
    const qty = parseFloat(document.getElementById('orderQtyInput').value) || 0;
    const total = qty * currentPrice;
    document.getElementById('totalPriceDisplay').value = '₹ ' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php include '../includes/footer.php'; ?>
