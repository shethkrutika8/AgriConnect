<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

// Increment views
$pdo->prepare("UPDATE marketplace_listings SET views = views + 1 WHERE id = ?")->execute([$id]);

// Fetch listing details with seller and farmer information
$stmt = $pdo->prepare("
    SELECT ml.*, 
           u.id as seller_user_id, u.name as seller_name, u.email as seller_email, u.phone as seller_phone, u.avatar as seller_avatar,
           f.id as farmer_id, f.farm_name, f.location as farm_location, f.farm_size, f.size_unit, f.soil_type, f.experience_years
    FROM marketplace_listings ml
    JOIN users u ON ml.seller_id = u.id
    LEFT JOIN farmers f ON f.user_id = u.id
    WHERE ml.id = ?
");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    redirect(BASE_URL . '/marketplace/index.php', 'Listing not found or expired.', 'danger');
}

$orderError = '';

// Handle direct order placement from the listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    $orderQty = (float)($_POST['quantity'] ?? 0);
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($orderQty <= 0) {
        $orderError = 'Please enter a valid order quantity.';
    } elseif ($orderQty > $listing['quantity']) {
        $orderError = 'Order quantity cannot exceed available stock (' . $listing['quantity'] . ' ' . $listing['unit'] . ').';
    } elseif (empty($deliveryAddress)) {
        $orderError = 'Delivery address is required.';
    } else {
        $unitPrice   = (float)$listing['price'];
        $totalAmount = $orderQty * $unitPrice;
        $orderNum    = 'ORD-' . date('Y') . '-' . strtoupper(substr(uniqid(), -6));

        $orderStmt = $pdo->prepare("
            INSERT INTO orders (order_number, buyer_id, seller_id, listing_id, quantity, unit_price, total_amount, status, payment_status, delivery_address, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, NOW())
        ");
        $orderStmt->execute([
            $orderNum,
            $user['id'],
            $listing['seller_id'],
            $listing['id'],
            $orderQty,
            $unitPrice,
            $totalAmount,
            $deliveryAddress,
            $notes
        ]);
        $newOrderId = $pdo->lastInsertId();

        // Reduce available quantity on listing
        $newQty = max(0, $listing['quantity'] - $orderQty);
        $newStatus = ($newQty <= 0) ? 'sold' : $listing['status'];
        $pdo->prepare("UPDATE marketplace_listings SET quantity = ?, status = ? WHERE id = ?")
            ->execute([$newQty, $newStatus, $listing['id']]);

        logActivity($user['id'], 'order_placed', "Placed order $orderNum for {$listing['title']}");
        redirect(BASE_URL . '/orders/view.php?id=' . $newOrderId, "Order $orderNum placed successfully! The seller has been notified.", 'success');
    }
}

$active_page = 'marketplace';
$page_title  = $listing['title'] . ' – Marketplace';
$breadcrumb  = [
    ['label' => 'Marketplace', 'url' => BASE_URL . '/marketplace/index.php'],
    ['label' => $listing['title']]
];

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title"><?= htmlspecialchars($listing['title']) ?></div>
      <div class="page-subtitle">
        Listed <?= timeAgo($listing['listed_at']) ?> · 
        <i class="fas fa-eye" style="margin-left:4px;margin-right:2px;"></i> <?= number_format($listing['views']) ?> views
      </div>
    </div>
    <div class="d-flex gap-8">
      <?php if ($user['role'] === 'admin' || $user['id'] == $listing['seller_id']): ?>
      <a href="edit.php?id=<?= $listing['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> Edit Listing</a>
      <a href="delete.php?id=<?= $listing['id'] ?>" class="btn btn-outline-danger" data-confirm="Are you sure you want to delete this listing?"><i class="fas fa-trash"></i> Delete</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Marketplace</a>
    </div>
  </div>

  <?php if (!empty($orderError)): ?>
  <div class="alert alert-danger mb-24">
    <i class="fas fa-exclamation-circle"></i>
    <div><?= htmlspecialchars($orderError) ?></div>
  </div>
  <?php endif; ?>

  <div class="grid-2 mb-24">
    <!-- Listing Details Main Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <i class="fas fa-<?= $listing['type'] === 'crop' ? 'leaf' : 'box' ?>" style="color:var(--primary);margin-right:8px;"></i>
          Listing Specifications
        </div>
        <div>
          <span class="badge <?= $listing['type'] === 'crop' ? 'badge-primary' : 'badge-secondary' ?>" style="margin-right:6px;">
            <?= ucfirst($listing['type']) ?>
          </span>
          <?php $sCls = ['active'=>'badge-success','sold'=>'badge-danger','expired'=>'badge-secondary','inactive'=>'badge-secondary'][$listing['status']] ?? 'badge-secondary'; ?>
          <span class="badge <?= $sCls ?>"><?= ucfirst($listing['status']) ?></span>
        </div>
      </div>
      <div class="card-body">
        
        <!-- Pricing Banner -->
        <div style="background:linear-gradient(135deg, var(--secondary), var(--primary));color:white;padding:20px;border-radius:var(--radius);margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:12px;opacity:0.85;text-transform:uppercase;font-weight:600;">Offer Price</div>
            <div style="font-size:28px;font-weight:800;"><?= formatCurrency($listing['price']) ?> <span style="font-size:14px;font-weight:400;opacity:0.9;">/ <?= htmlspecialchars($listing['unit']) ?></span></div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:12px;opacity:0.85;text-transform:uppercase;font-weight:600;">Available Stock</div>
            <div style="font-size:22px;font-weight:700;"><?= number_format($listing['quantity']) ?> <?= htmlspecialchars($listing['unit']) ?></div>
          </div>
        </div>

        <div class="grid-2 gap-16 mb-24" style="font-size:13px;">
          <div>
            <span class="text-muted">Origin / Location:</span><br>
            <strong style="font-size:14px;"><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:4px;"></i><?= htmlspecialchars($listing['location'] ?: 'Not specified') ?></strong>
          </div>
          <div>
            <span class="text-muted">Listing ID:</span><br>
            <strong style="font-size:14px;">#MKT-<?= str_pad($listing['id'], 5, '0', STR_PAD_LEFT) ?></strong>
          </div>
        </div>

        <hr class="divider">

        <h4 style="font-size:15px;font-weight:600;margin-bottom:10px;color:var(--gray-900);">Product / Crop Details</h4>
        <p style="color:var(--gray-600);line-height:1.7;margin-bottom:20px;">
          <?= nl2br(htmlspecialchars($listing['description'] ?: 'No additional description provided.')) ?>
        </p>

        <?php if (!empty($listing['item_id'])): ?>
        <div style="background:var(--gray-50);padding:14px;border-radius:var(--radius);font-size:13px;">
          <strong>Linked Inventory Source:</strong> 
          <?php if ($listing['type'] === 'crop'): ?>
          <a href="../crops/view.php?id=<?= $listing['item_id'] ?>" style="color:var(--primary);font-weight:600;margin-left:6px;">
            View Master Crop Record #<?= $listing['item_id'] ?> →
          </a>
          <?php else: ?>
          <a href="../products/view.php?id=<?= $listing['item_id'] ?>" style="color:var(--primary);font-weight:600;margin-left:6px;">
            View Master Product Record #<?= $listing['item_id'] ?> →
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Right Column: Buy Order Form & Seller Profile -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      
      <!-- Instant Purchase / Order Form -->
      <div class="card" id="orderForm" style="border: 2px solid var(--primary);">
        <div class="card-header" style="background: var(--primary-pale);">
          <div class="card-title" style="color: var(--secondary);"><i class="fas fa-shopping-cart" style="color:var(--primary);margin-right:8px;"></i>Place Direct Order</div>
        </div>
        <div class="card-body">
          <?php if ($listing['status'] !== 'active' || $listing['quantity'] <= 0): ?>
          <div class="alert alert-secondary">
            <i class="fas fa-info-circle"></i>
            <div>This listing is currently not available for purchase (status: <?= htmlspecialchars($listing['status']) ?>).</div>
          </div>
          <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="place_order">
            
            <div class="form-group">
              <label class="form-label">Order Quantity (in <?= htmlspecialchars($listing['unit']) ?>) <span class="req">*</span></label>
              <div class="input-group">
                <input type="number" name="quantity" id="buyQty" class="form-control" 
                       placeholder="e.g. 50" min="1" max="<?= $listing['quantity'] ?>" step="any"
                       value="1" required oninput="calcTotal(this.value, <?= $listing['price'] ?>)">
                <span style="background:var(--gray-100);padding:10px 14px;border:1px solid var(--gray-300);border-left:none;border-radius:0 8px 8px 0;font-weight:600;">
                  <?= htmlspecialchars($listing['unit']) ?>
                </span>
              </div>
              <div class="text-sm text-muted" style="margin-top:4px;">Available in stock: <?= number_format($listing['quantity']) ?> <?= htmlspecialchars($listing['unit']) ?></div>
            </div>

            <!-- Price Calculation Display -->
            <div style="background:var(--gray-50);border:1px dashed var(--gray-300);padding:12px;border-radius:8px;margin-bottom:16px;">
              <div class="d-flex justify-between" style="font-size:13px;color:var(--gray-600);margin-bottom:4px;">
                <span>Unit Price:</span>
                <span><?= formatCurrency($listing['price']) ?></span>
              </div>
              <div class="d-flex justify-between" style="font-size:15px;font-weight:700;color:var(--secondary);">
                <span>Estimated Total:</span>
                <span id="calculatedTotal"><?= formatCurrency($listing['price']) ?></span>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Delivery Destination Address <span class="req">*</span></label>
              <textarea name="delivery_address" class="form-control" rows="2" placeholder="Enter delivery street address, city, pin code..." required></textarea>
            </div>

            <div class="form-group">
              <label class="form-label">Order Instructions / Delivery Notes</label>
              <input type="text" name="notes" class="form-control" placeholder="e.g. Call before delivery, urgent requirement">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding:12px;font-size:15px;">
              <i class="fas fa-check-circle"></i> Confirm & Place Order
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Seller Info Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i>Seller Profile</div>
        </div>
        <div class="card-body">
          <div class="d-flex align-center gap-12 mb-16">
            <div class="avatar-initials" style="width:48px;height:48px;font-size:18px;">
              <?= strtoupper(substr($listing['seller_name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:700;font-size:16px;"><?= htmlspecialchars($listing['seller_name']) ?></div>
              <?php if (!empty($listing['farm_name'])): ?>
              <div class="text-muted text-sm"><?= htmlspecialchars($listing['farm_name']) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
            <div><i class="fas fa-envelope" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($listing['seller_email']) ?></div>
            <div><i class="fas fa-phone" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($listing['seller_phone'] ?: 'Phone on order') ?></div>
            <?php if (!empty($listing['farm_location'])): ?>
            <div><i class="fas fa-map-marker-alt" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($listing['farm_location']) ?></div>
            <?php endif; ?>
            <?php if (!empty($listing['farmer_id'])): ?>
            <div style="margin-top:8px;">
              <a href="../farmers/view.php?id=<?= $listing['farmer_id'] ?>" style="color:var(--primary);font-weight:600;">View Full Farmer Profile →</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function calcTotal(qty, unitPrice) {
    const q = parseFloat(qty) || 0;
    const total = q * unitPrice;
    document.getElementById('calculatedTotal').innerText = '₹ ' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<?php include '../includes/footer.php'; ?>
