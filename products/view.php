<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT p.*, pc.name as cat_name, pc.description as cat_desc,
           f.farm_name, f.location, f.soil_type, f.irrigation_type,
           u.name as farmer_user_name, u.email as farmer_email, u.phone as farmer_phone
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN farmers f ON p.farmer_id = f.id
    LEFT JOIN users u ON f.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    redirect(BASE_URL . '/products/index.php', 'Product not found.', 'danger');
}

// Check if this product is listed on marketplace
$listingStmt = $pdo->prepare("SELECT * FROM marketplace_listings WHERE type='product' AND item_id = ?");
$listingStmt->execute([$id]);
$listing = $listingStmt->fetch();

$active_page = 'products';
$page_title  = $product['name'] . ' – Product Details';
$breadcrumb  = [
    ['label' => 'Products', 'url' => BASE_URL . '/products/index.php'],
    ['label' => $product['name']]
];

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title"><?= htmlspecialchars($product['name']) ?></div>
      <div class="page-subtitle">Product ID: #PRD-<?= str_pad($product['id'], 4, '0', STR_PAD_LEFT) ?></div>
    </div>
    <div class="d-flex gap-8">
      <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Product</a>
      <?php if (!$listing): ?>
      <a href="../marketplace/add.php?type=product&item_id=<?= $product['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-store"></i> List on Marketplace</a>
      <?php else: ?>
      <a href="../marketplace/view.php?id=<?= $listing['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> View Listing</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Products</a>
    </div>
  </div>

  <div class="grid-2 mb-24">
    <!-- Main Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-box-open" style="color:var(--primary);margin-right:8px;"></i>Product Specifications</div>
        <?php $cls = ['active'=>'badge-success','inactive'=>'badge-secondary','out_of_stock'=>'badge-danger'][$product['status']] ?? 'badge-secondary'; ?>
        <span class="badge <?= $cls ?>"><?= str_replace('_', ' ', ucfirst($product['status'])) ?></span>
      </div>
      <div class="card-body">
        <div class="grid-2 gap-16 mb-24">
          <div>
            <div class="text-muted text-sm">Product Name</div>
            <div style="font-size:18px;font-weight:700;color:var(--gray-900);"><?= htmlspecialchars($product['name']) ?></div>
          </div>
          <div>
            <div class="text-muted text-sm">Category</div>
            <div style="font-size:15px;font-weight:600;color:var(--primary);"><?= htmlspecialchars($product['cat_name'] ?? 'Uncategorized') ?></div>
          </div>
          <div>
            <div class="text-muted text-sm">Price per Unit</div>
            <div style="font-size:22px;font-weight:800;color:var(--secondary);"><?= formatCurrency($product['price']) ?> <span style="font-size:13px;font-weight:400;color:var(--gray-500);">/ <?= htmlspecialchars($product['unit']) ?></span></div>
          </div>
          <div>
            <div class="text-muted text-sm">In Stock Quantity</div>
            <div style="font-size:20px;font-weight:700;"><?= number_format($product['quantity']) ?> <?= htmlspecialchars($product['unit']) ?></div>
          </div>
        </div>

        <hr class="divider">

        <div>
          <h4 style="font-size:14px;font-weight:600;margin-bottom:8px;color:var(--gray-800);">Description</h4>
          <p style="color:var(--gray-600);line-height:1.7;">
            <?= nl2br(htmlspecialchars($product['description'] ?: 'No description provided.')) ?>
          </p>
        </div>

        <hr class="divider">

        <div class="d-flex justify-between align-center text-sm text-muted">
          <div>Added on: <strong><?= date('d M Y, h:i A', strtotime($product['created_at'])) ?></strong></div>
          <div>Last updated: <strong><?= timeAgo($product['updated_at'] ?? $product['created_at']) ?></strong></div>
        </div>
      </div>
      <div class="card-footer d-flex justify-between">
        <a href="delete.php?id=<?= $product['id'] ?>" class="btn btn-outline-danger btn-sm" data-confirm="Are you sure you want to permanently delete this product?">
          <i class="fas fa-trash"></i> Delete Product
        </a>
        <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-edit"></i> Edit Details
        </a>
      </div>
    </div>

    <!-- Farmer Card & Marketplace Status -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      <!-- Farmer Profile Mini Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i>Supplier / Farm</div>
          <?php if (!empty($product['farmer_id'])): ?>
          <a href="../farmers/view.php?id=<?= $product['farmer_id'] ?>" class="text-sm" style="color:var(--primary);font-weight:600;">View Farm →</a>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (!empty($product['farm_name'])): ?>
          <div style="font-size:16px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($product['farm_name']) ?></div>
          <div class="text-muted text-sm mb-12"><i class="fas fa-map-marker-alt" style="margin-right:6px;color:var(--primary);"></i><?= htmlspecialchars($product['location'] ?: 'Location not specified') ?></div>

          <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
            <div><strong>Farmer:</strong> <?= htmlspecialchars($product['farmer_user_name'] ?? '—') ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($product['farmer_email'] ?? '—') ?></div>
            <div><strong>Phone:</strong> <?= htmlspecialchars($product['farmer_phone'] ?: '—') ?></div>
            <?php if (!empty($product['soil_type'])): ?>
            <div><strong>Soil Type:</strong> <?= htmlspecialchars($product['soil_type']) ?></div>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <p class="text-muted text-sm">No farm currently linked to this product.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Marketplace Status Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px;"></i>Marketplace Status</div>
        </div>
        <div class="card-body">
          <?php if ($listing): ?>
          <div class="alert alert-success" style="margin-bottom:12px;">
            <i class="fas fa-check-circle"></i>
            <div>This product is currently listed on the marketplace.</div>
          </div>
          <div style="font-size:13px;display:flex;flex-direction:column;gap:6px;">
            <div><strong>Listing Title:</strong> <?= htmlspecialchars($listing['title']) ?></div>
            <div><strong>Listed Price:</strong> <?= formatCurrency($listing['price']) ?> / <?= htmlspecialchars($listing['unit']) ?></div>
            <div><strong>Status:</strong> <span class="badge badge-success"><?= ucfirst($listing['status']) ?></span></div>
          </div>
          <div style="margin-top:14px;">
            <a href="../marketplace/view.php?id=<?= $listing['id'] ?>" class="btn btn-outline-primary btn-sm btn-block">Open Marketplace Listing</a>
          </div>
          <?php else: ?>
          <p class="text-muted text-sm mb-16">This product is not currently listed for sale on the public marketplace.</p>
          <a href="../marketplace/add.php?type=product&item_id=<?= $product['id'] ?>" class="btn btn-primary btn-sm btn-block">
            <i class="fas fa-plus"></i> Create Marketplace Listing
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
