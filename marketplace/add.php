<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'marketplace';
$page_title  = 'Create Marketplace Listing';
$breadcrumb  = [
    ['label' => 'Marketplace', 'url' => BASE_URL . '/marketplace/index.php'],
    ['label' => 'Add Listing']
];

$pdo  = getDB();
$user = currentUser();

// Pre-fill parameters if redirected from crop/product page
$preType   = trim($_GET['type'] ?? 'crop');
$preItemId = (int)($_GET['item_id'] ?? 0);

$preTitle = '';
$prePrice = '';
$preQty   = '';
$preUnit  = 'kg';
$preDesc  = '';
$preLoc   = '';

if ($preItemId > 0) {
    if ($preType === 'crop') {
        $c = $pdo->prepare("SELECT c.*, f.location as farm_loc FROM crops c LEFT JOIN farmers f ON c.farmer_id=f.id WHERE c.id = ?");
        $c->execute([$preItemId]);
        if ($crop = $c->fetch()) {
            $preTitle = $crop['name'] . ($crop['variety'] ? ' – ' . $crop['variety'] : '');
            $prePrice = $crop['price'];
            $preQty   = $crop['quantity'];
            $preUnit  = $crop['unit'];
            $preDesc  = $crop['description'];
            $preLoc   = $crop['farm_loc'] ?? '';
        }
    } elseif ($preType === 'product') {
        $p = $pdo->prepare("SELECT p.*, f.location as farm_loc FROM products p LEFT JOIN farmers f ON p.farmer_id=f.id WHERE p.id = ?");
        $p->execute([$preItemId]);
        if ($prod = $p->fetch()) {
            $preTitle = $prod['name'];
            $prePrice = $prod['price'];
            $preQty   = $prod['quantity'];
            $preUnit  = $prod['unit'];
            $preDesc  = $prod['description'];
            $preLoc   = $prod['farm_loc'] ?? '';
        }
    }
}

// Available crops & products to choose from
$crops    = $pdo->query("SELECT id, name, variety, price, unit, quantity FROM crops ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, price, unit, quantity FROM products ORDER BY name")->fetchAll();
$sellers  = $pdo->query("SELECT id, name, email, role FROM users WHERE role IN ('farmer', 'admin') ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type     = trim($_POST['type'] ?? 'crop');
    $itemId   = (int)($_POST['item_id'] ?? 0);
    $sellerId = ($user['role'] === 'admin') ? (int)($_POST['seller_id'] ?? $user['id']) : $user['id'];
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $qty      = (float)($_POST['quantity'] ?? 0);
    $unit     = trim($_POST['unit'] ?? 'kg');
    $location = trim($_POST['location'] ?? '');
    $status   = trim($_POST['status'] ?? 'active');

    if (!$title)      $errors['title']    = 'Listing title is required.';
    if ($price <= 0)  $errors['price']    = 'Price must be greater than 0.';
    if ($qty <= 0)    $errors['quantity'] = 'Quantity must be greater than 0.';
    if (!$sellerId)   $errors['seller']   = 'Valid seller is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO marketplace_listings (type, item_id, seller_id, title, description, price, quantity, unit, location, status, listed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $type,
            $itemId ?: 0,
            $sellerId,
            $title,
            $desc,
            $price,
            $qty,
            $unit,
            $location,
            $status
        ]);
        $newId = $pdo->lastInsertId();

        logActivity($user['id'], 'listing_created', "Created marketplace listing: $title");
        redirect(BASE_URL . '/marketplace/view.php?id=' . $newId, 'Marketplace listing published successfully!', 'success');
    }
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Create Marketplace Listing</div>
      <div class="page-subtitle">Publish farm produce or agricultural items for buyers</div>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Marketplace</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px;"></i>Listing Information</div>
    </div>
    <div class="card-body">
      
      <!-- Type Selection -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Item Type <span class="req">*</span></label>
          <select name="type" id="listingType" class="form-control" onchange="toggleItemSelect(this.value)">
            <option value="crop" <?= $preType === 'crop' ? 'selected' : '' ?>>🌾 Farm Crop</option>
            <option value="product" <?= $preType === 'product' ? 'selected' : '' ?>>📦 Agricultural Product / Supply</option>
          </select>
        </div>

        <?php if ($user['role'] === 'admin'): ?>
        <div class="form-group">
          <label class="form-label">Seller Account <span class="req">*</span></label>
          <select name="seller_id" class="form-control">
            <?php foreach ($sellers as $s): ?>
            <option value="<?= $s['id'] ?>" <?= ($user['id'] == $s['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name'] . ' (' . ucfirst($s['role']) . ' - ' . $s['email'] . ')') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="seller_id" value="<?= $user['id'] ?>">
        <?php endif; ?>
      </div>

      <!-- Quick Item Linker (Optional) -->
      <div class="form-group" id="cropSelectGroup">
        <label class="form-label">Auto-fill from Crop Inventory (Optional)</label>
        <select class="form-control" onchange="autofillCrop(this)">
          <option value="">-- Choose an existing crop to auto-fill --</option>
          <?php foreach ($crops as $c): ?>
          <option value="<?= $c['id'] ?>" 
                  data-name="<?= htmlspecialchars($c['name'] . ($c['variety'] ? ' – ' . $c['variety'] : '')) ?>"
                  data-price="<?= $c['price'] ?>"
                  data-unit="<?= $c['unit'] ?>"
                  data-qty="<?= $c['quantity'] ?>">
            <?= htmlspecialchars($c['name']) ?> (₹<?= $c['price'] ?>/<?= $c['unit'] ?> - <?= $c['quantity'] ?> <?= $c['unit'] ?> available)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <input type="hidden" name="item_id" id="itemIdInput" value="<?= $preItemId ?>">

      <!-- Title & Location -->
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Listing Title <span class="req">*</span></label>
          <input type="text" name="title" id="titleInput" class="form-control" 
                 placeholder="e.g. Fresh Organic Basmati Rice – Amritsar" 
                 value="<?= htmlspecialchars($_POST['title'] ?? $preTitle) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Location (City, State)</label>
          <input type="text" name="location" id="locInput" class="form-control" 
                 placeholder="e.g. Amritsar, Punjab" 
                 value="<?= htmlspecialchars($_POST['location'] ?? $preLoc) ?>">
        </div>
      </div>

      <!-- Price, Quantity, Unit -->
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Price per Unit (₹) <span class="req">*</span></label>
          <input type="number" name="price" id="priceInput" class="form-control" 
                 placeholder="0.00" step="0.01" min="0" 
                 value="<?= htmlspecialchars((string)($_POST['price'] ?? $prePrice)) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select name="unit" id="unitSelect" class="form-control">
            <?php foreach (['kg', 'quintal', 'tonne', 'litre', 'dozen', 'piece', 'bunch', 'box'] as $u): ?>
            <option value="<?= $u ?>" <?= ($preUnit === $u) ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Total Quantity for Sale <span class="req">*</span></label>
          <input type="number" name="quantity" id="qtyInput" class="form-control" 
                 placeholder="100" min="0.1" step="0.01" 
                 value="<?= htmlspecialchars((string)($_POST['quantity'] ?? $preQty)) ?>" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active">Active (Visible to all buyers)</option>
            <option value="inactive">Inactive (Draft)</option>
          </select>
        </div>
      </div>

      <!-- Description -->
      <div class="form-group">
        <label class="form-label">Description & Quality Details</label>
        <textarea name="description" id="descInput" class="form-control" rows="4" 
                  placeholder="Detail the quality, harvest freshness, packaging, and shipping options..."><?= htmlspecialchars($_POST['description'] ?? $preDesc) ?></textarea>
      </div>

    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Listing</button>
    </div>
  </form>
</div>

<script>
function autofillCrop(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('itemIdInput').value = opt.value;
        document.getElementById('titleInput').value = opt.dataset.name || '';
        document.getElementById('priceInput').value = opt.dataset.price || '';
        document.getElementById('qtyInput').value   = opt.dataset.qty || '';
        if (opt.dataset.unit) document.getElementById('unitSelect').value = opt.dataset.unit;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
