<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT c.*, cc.name as category_name, cc.description as category_desc,
           f.id as farmer_profile_id, f.farm_name, f.location, f.farm_size, f.size_unit, f.soil_type, f.irrigation_type,
           u.name as farmer_name, u.email as farmer_email, u.phone as farmer_phone
    FROM crops c
    LEFT JOIN crop_categories cc ON c.category_id = cc.id
    LEFT JOIN farmers f ON c.farmer_id = f.id
    LEFT JOIN users u ON f.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$crop = $stmt->fetch();

if (!$crop) {
    redirect(BASE_URL . '/crops/index.php', 'Crop not found.', 'danger');
}

// Check marketplace listing
$listingStmt = $pdo->prepare("SELECT * FROM marketplace_listings WHERE type='crop' AND item_id = ?");
$listingStmt->execute([$id]);
$listing = $listingStmt->fetch();

$active_page = 'crops';
$page_title  = $crop['name'] . ' – Crop Details';
$breadcrumb  = [
    ['label' => 'Crops', 'url' => BASE_URL . '/crops/index.php'],
    ['label' => $crop['name']]
];

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title"><?= htmlspecialchars($crop['name']) ?> <?= $crop['variety'] ? '(' . htmlspecialchars($crop['variety']) . ')' : '' ?></div>
      <div class="page-subtitle">Season: <?= htmlspecialchars($crop['season']) ?> · Harvest: <?= $crop['harvest_date'] ? date('d M Y', strtotime($crop['harvest_date'])) : 'Not scheduled' ?></div>
    </div>
    <div class="d-flex gap-8">
      <a href="edit.php?id=<?= $crop['id'] ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit Crop</a>
      <?php if (!$listing): ?>
      <a href="../marketplace/add.php?type=crop&item_id=<?= $crop['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-store"></i> List on Marketplace</a>
      <?php else: ?>
      <a href="../marketplace/view.php?id=<?= $listing['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-external-link-alt"></i> View Listing</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Crops</a>
    </div>
  </div>

  <div class="grid-2 mb-24">
    <!-- Main Crop Details Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i>Crop Overview</div>
        <?php $cls = ['available'=>'badge-success','sold_out'=>'badge-danger','coming_soon'=>'badge-warning','inactive'=>'badge-secondary'][$crop['status']] ?? 'badge-secondary'; ?>
        <span class="badge <?= $cls ?>"><?= str_replace('_', ' ', ucfirst($crop['status'])) ?></span>
      </div>
      <div class="card-body" style="padding:0;">
        <img src="<?= cropImage($crop) ?>" alt="<?= htmlspecialchars($crop['name']) ?>" class="img-cover-lg" width="640" height="280">
        <div style="padding:20px;">
        <div class="grid-2 gap-16 mb-24">
          <div>
            <div class="text-muted text-sm">Crop Name & Variety</div>
            <div style="font-size:18px;font-weight:700;color:var(--gray-900);"><?= htmlspecialchars($crop['name']) ?></div>
            <?php if ($crop['variety']): ?><div class="text-muted text-sm">Variety: <?= htmlspecialchars($crop['variety']) ?></div><?php endif; ?>
          </div>
          <div>
            <div class="text-muted text-sm">Category</div>
            <div style="font-size:15px;font-weight:600;color:var(--primary);"><?= htmlspecialchars($crop['category_name'] ?? 'Uncategorized') ?></div>
          </div>
          <div>
            <div class="text-muted text-sm">Price per <?= htmlspecialchars($crop['unit']) ?></div>
            <div style="font-size:22px;font-weight:800;color:var(--secondary);"><?= formatCurrency($crop['price']) ?></div>
          </div>
          <div>
            <div class="text-muted text-sm">Harvested / Available Quantity</div>
            <div style="font-size:20px;font-weight:700;"><?= number_format($crop['quantity']) ?> <?= htmlspecialchars($crop['unit']) ?></div>
          </div>
        </div>

        <div class="grid-3 gap-12 mb-24" style="background:var(--gray-50);padding:14px;border-radius:var(--radius);">
          <div>
            <div class="text-muted text-sm">Season</div>
            <div style="font-weight:600;"><span class="badge badge-primary"><?= htmlspecialchars($crop['season']) ?></span></div>
          </div>
          <div>
            <div class="text-muted text-sm">Planting Date</div>
            <div style="font-weight:600;"><?= $crop['planting_date'] ? date('d M Y', strtotime($crop['planting_date'])) : '—' ?></div>
          </div>
          <div>
            <div class="text-muted text-sm">Harvest Date</div>
            <div style="font-weight:600;"><?= $crop['harvest_date'] ? date('d M Y', strtotime($crop['harvest_date'])) : '—' ?></div>
          </div>
        </div>

        <div>
          <h4 style="font-size:14px;font-weight:600;margin-bottom:8px;color:var(--gray-800);">Description / Field Notes</h4>
          <p style="color:var(--gray-600);line-height:1.7;">
            <?= nl2br(htmlspecialchars($crop['description'] ?: 'No notes or description provided for this crop.')) ?>
          </p>
        </div>

        <hr class="divider">

        <div class="d-flex justify-between align-center text-sm text-muted">
          <div>Recorded on: <strong><?= date('d M Y', strtotime($crop['created_at'])) ?></strong></div>
          <div>Last update: <strong><?= timeAgo($crop['updated_at'] ?? $crop['created_at']) ?></strong></div>
        </div>
        </div>
      </div>
      <div class="card-footer d-flex justify-between">
        <a href="delete.php?id=<?= $crop['id'] ?>" class="btn btn-outline-danger btn-sm" data-confirm="Are you sure you want to permanently delete this crop?">
          <i class="fas fa-trash"></i> Delete Crop
        </a>
        <a href="edit.php?id=<?= $crop['id'] ?>" class="btn btn-primary btn-sm">
          <i class="fas fa-edit"></i> Edit Details
        </a>
      </div>
    </div>

    <!-- Farmer & Marketplace Info -->
    <div style="display:flex;flex-direction:column;gap:20px;">
      <!-- Farm Details Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i>Cultivating Farmer</div>
          <?php if (!empty($crop['farmer_id'])): ?>
          <a href="../farmers/view.php?id=<?= $crop['farmer_id'] ?>" class="text-sm" style="color:var(--primary);font-weight:600;">View Profile →</a>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (!empty($crop['farm_name'])): ?>
          <div style="font-size:16px;font-weight:700;"><?= htmlspecialchars($crop['farm_name']) ?></div>
          <div class="text-muted text-sm mb-12"><i class="fas fa-map-marker-alt" style="margin-right:6px;color:var(--primary);"></i><?= htmlspecialchars($crop['location'] ?: 'Location not specified') ?></div>

          <div style="font-size:13px;display:flex;flex-direction:column;gap:8px;">
            <div><strong>Farmer:</strong> <?= htmlspecialchars($crop['farmer_name'] ?? '—') ?></div>
            <div><strong>Email:</strong> <?= htmlspecialchars($crop['farmer_email'] ?? '—') ?></div>
            <div><strong>Phone:</strong> <?= htmlspecialchars($crop['farmer_phone'] ?: '—') ?></div>
            <div><strong>Farm Size:</strong> <?= $crop['farm_size'] ? $crop['farm_size'] . ' ' . $crop['size_unit'] : '—' ?></div>
            <div><strong>Soil Type:</strong> <?= htmlspecialchars($crop['soil_type'] ?: '—') ?></div>
            <div><strong>Irrigation:</strong> <?= htmlspecialchars($crop['irrigation_type'] ?: '—') ?></div>
          </div>
          <?php else: ?>
          <p class="text-muted text-sm">No farmer currently assigned to this crop record.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Marketplace Listing Status Card -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px;"></i>Marketplace Status</div>
        </div>
        <div class="card-body">
          <?php if ($listing): ?>
          <div class="alert alert-success" style="margin-bottom:12px;">
            <i class="fas fa-check-circle"></i>
            <div>Listed on public marketplace.</div>
          </div>
          <div style="font-size:13px;display:flex;flex-direction:column;gap:6px;">
            <div><strong>Listing Title:</strong> <?= htmlspecialchars($listing['title']) ?></div>
            <div><strong>Listing Price:</strong> <?= formatCurrency($listing['price']) ?> / <?= htmlspecialchars($listing['unit']) ?></div>
            <div><strong>Available Qty:</strong> <?= number_format($listing['quantity']) ?> <?= htmlspecialchars($listing['unit']) ?></div>
          </div>
          <div style="margin-top:14px;">
            <a href="../marketplace/view.php?id=<?= $listing['id'] ?>" class="btn btn-outline-primary btn-sm btn-block">Open Marketplace Listing</a>
          </div>
          <?php else: ?>
          <p class="text-muted text-sm mb-16">This crop is in farm records only and is not currently listed for buyers on the marketplace.</p>
          <a href="../marketplace/add.php?type=crop&item_id=<?= $crop['id'] ?>" class="btn btn-primary btn-sm btn-block">
            <i class="fas fa-plus"></i> List This Crop on Marketplace
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
