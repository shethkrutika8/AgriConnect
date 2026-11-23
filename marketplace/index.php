<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'marketplace';
$page_title  = 'Marketplace';
$breadcrumb  = [['label' => 'Marketplace']];

$pdo = getDB();

// ── Filters & Search ──
$search   = trim($_GET['search'] ?? '');
$type     = trim($_GET['type'] ?? '');
$status   = trim($_GET['status'] ?? 'active'); // default to active listings
if (isset($_GET['status']) && $_GET['status'] === 'all') $status = '';

$sort     = trim($_GET['sort'] ?? 'latest');
$per_page = 9;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(ml.title LIKE ? OR ml.description LIKE ? OR ml.location LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($type && in_array($type, ['crop', 'product'])) {
    $where[] = "ml.type = ?";
    $params[] = $type;
}
if ($status) {
    $where[] = "ml.status = ?";
    $params[] = $status;
}

$orderBy = "ml.listed_at DESC";
if ($sort === 'price_asc')  $orderBy = "ml.price ASC";
if ($sort === 'price_desc') $orderBy = "ml.price DESC";
if ($sort === 'popular')    $orderBy = "ml.views DESC";

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM marketplace_listings ml
    JOIN users u ON ml.seller_id = u.id
    WHERE $whereStr
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

// Fetch
$stmt = $pdo->prepare("
    SELECT ml.*, u.name as seller_name, u.email as seller_email, u.phone as seller_phone,
           f.farm_name, f.location as farm_location
    FROM marketplace_listings ml
    JOIN users u ON ml.seller_id = u.id
    LEFT JOIN farmers f ON f.user_id = u.id
    WHERE $whereStr
    ORDER BY $orderBy
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Summary Stats
$stats = [
    'total_active' => $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE status='active'")->fetchColumn(),
    'crops_count'  => $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE type='crop' AND status='active'")->fetchColumn(),
    'prods_count'  => $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE type='product' AND status='active'")->fetchColumn(),
];

$user = currentUser();

include '../includes/header.php';
?>

<div class="page-content">
  <!-- Header -->
  <div class="page-header">
    <div>
      <div class="page-title">AgriConnect Marketplace</div>
      <div class="page-subtitle">Buy direct from certified farmers and suppliers</div>
    </div>
    <div class="d-flex gap-8">
      <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Listing</a>
    </div>
  </div>

  <!-- Quick Stats -->
  <div class="stats-grid mb-24" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="fas fa-store"></i></div>
      <div class="stat-content">
        <div class="label">Active Listings</div>
        <div class="value"><?= number_format($stats['total_active']) ?></div>
      </div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="fas fa-leaf"></i></div>
      <div class="stat-content">
        <div class="label">Fresh Crops Available</div>
        <div class="value"><?= number_format($stats['crops_count']) ?></div>
      </div>
    </div>
    <div class="stat-card amber">
      <div class="stat-icon amber"><i class="fas fa-box-open"></i></div>
      <div class="stat-content">
        <div class="label">Farm Products & Seeds</div>
        <div class="value"><?= number_format($stats['prods_count']) ?></div>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar mb-24">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search crops, products, sellers, location…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="type">
      <option value="">All Categories (Crops & Products)</option>
      <option value="crop" <?= $type === 'crop' ? 'selected' : '' ?>>🌾 Crops</option>
      <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>📦 Products / Supplies</option>
    </select>
    <select name="status">
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
      <option value="sold" <?= $status === 'sold' ? 'selected' : '' ?>>Sold</option>
      <option value="all" <?= $status === '' ? 'selected' : '' ?>>All Statuses</option>
    </select>
    <select name="sort">
      <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Sort: Latest</option>
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
      <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Viewed</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
    <?php if ($search || $type || $status !== 'active' || $sort !== 'latest'): ?>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset</a>
    <?php endif; ?>
  </form>

  <!-- Listings Grid -->
  <?php if (empty($listings)): ?>
  <div class="card">
    <div class="empty-state" style="padding: 48px;">
      <i class="fas fa-store-slash" style="font-size: 48px; color: var(--gray-400); margin-bottom: 12px;"></i>
      <h3>No marketplace listings found</h3>
      <p class="text-muted">Try adjusting your filters or search terms, or create a new listing.</p>
      <div style="margin-top: 16px;">
        <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> List an Item Now</a>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;" class="mb-24">
    <?php foreach ($listings as $l): ?>
    <div class="card" style="display:flex;flex-direction:column;transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='var(--shadow-lg)';" onmouseout="this.style.transform='none';this.style.boxShadow='var(--shadow)';">
      <div class="card-body" style="flex:1;display:flex;flex-direction:column;padding:20px;">
        
        <!-- Header: Type badge & Status -->
        <div class="d-flex justify-between align-center mb-12">
          <span class="badge <?= $l['type'] === 'crop' ? 'badge-primary' : 'badge-secondary' ?>">
            <i class="fas fa-<?= $l['type'] === 'crop' ? 'leaf' : 'box' ?>" style="margin-right:4px;"></i>
            <?= ucfirst($l['type']) ?>
          </span>
          <?php 
          $sCls = ['active' => 'badge-success', 'sold' => 'badge-danger', 'expired' => 'badge-secondary', 'inactive' => 'badge-secondary'][$l['status']] ?? 'badge-secondary';
          ?>
          <span class="badge <?= $sCls ?>"><?= ucfirst($l['status']) ?></span>
        </div>

        <!-- Title -->
        <h3 style="font-size:17px;font-weight:700;margin-bottom:8px;line-height:1.3;">
          <a href="view.php?id=<?= $l['id'] ?>" style="color:var(--gray-900);">
            <?= htmlspecialchars($l['title']) ?>
          </a>
        </h3>

        <!-- Description Snippet -->
        <p class="text-muted text-sm mb-16" style="flex:1;line-height:1.5;">
          <?= htmlspecialchars(substr($l['description'] ?? '', 0, 95)) ?><?= strlen($l['description'] ?? '') > 95 ? '…' : '' ?>
        </p>

        <!-- Location & Seller -->
        <div style="font-size:13px;color:var(--gray-600);margin-bottom:14px;display:flex;flex-direction:column;gap:4px;">
          <div><i class="fas fa-map-marker-alt" style="color:var(--primary);width:16px;"></i> <?= htmlspecialchars($l['location'] ?: ($l['farm_location'] ?: 'Location on request')) ?></div>
          <div><i class="fas fa-user" style="color:var(--gray-400);width:16px;"></i> <?= htmlspecialchars($l['farm_name'] ?: $l['seller_name']) ?></div>
        </div>

        <!-- Price & Qty Row -->
        <div class="d-flex justify-between align-center" style="background:var(--gray-50);padding:10px 14px;border-radius:var(--radius);margin-bottom:16px;">
          <div>
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;">Price</div>
            <div style="font-size:18px;font-weight:800;color:var(--secondary);">
              <?= formatCurrency($l['price']) ?> <span style="font-size:12px;font-weight:400;color:var(--gray-500);">/ <?= htmlspecialchars($l['unit']) ?></span>
            </div>
          </div>
          <div style="text-align:right;">
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;font-weight:600;">Available</div>
            <div style="font-size:15px;font-weight:700;color:var(--gray-800);">
              <?= number_format($l['quantity']) ?> <?= htmlspecialchars($l['unit']) ?>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex gap-8">
          <a href="view.php?id=<?= $l['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1;text-align:center;">
            <i class="fas fa-eye"></i> View Details
          </a>
          <?php if ($l['status'] === 'active'): ?>
          <a href="view.php?id=<?= $l['id'] ?>#orderForm" class="btn btn-primary btn-sm" style="flex:1;text-align:center;">
            <i class="fas fa-shopping-cart"></i> Buy Now
          </a>
          <?php endif; ?>
          <?php if ($user['role'] === 'admin' || $user['id'] == $l['seller_id']): ?>
          <a href="edit.php?id=<?= $l['id'] ?>" class="btn btn-outline-primary btn-sm" data-tooltip="Edit">
            <i class="fas fa-edit"></i>
          </a>
          <a href="delete.php?id=<?= $l['id'] ?>" class="btn btn-outline-danger btn-sm" data-confirm="Remove this listing from marketplace?" data-tooltip="Delete">
            <i class="fas fa-trash"></i>
          </a>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pag['total_pages'] > 1): ?>
  <div class="card p-16 d-flex align-center justify-between" style="padding:16px;">
    <div class="text-muted text-sm">
      Showing <?= $pag['offset'] + 1 ?>–<?= min($pag['offset'] + $per_page, $total) ?> of <?= $total ?> listings
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

  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
