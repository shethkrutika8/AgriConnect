<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'crops';
$page_title  = 'Crop Management';
$breadcrumb  = [['label' => 'Crops']];

$pdo = getDB();

// ── Filters ──
$search   = trim($_GET['search'] ?? '');
$cat      = (int)($_GET['category'] ?? 0);
$status   = trim($_GET['status'] ?? '');
$season   = trim($_GET['season'] ?? '');
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = "(c.name LIKE ? OR c.variety LIKE ? OR f.farm_name LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($cat)    { $where[] = "c.category_id = ?"; $params[] = $cat; }
if ($status) { $where[] = "c.status = ?"; $params[] = $status; }
if ($season) { $where[] = "c.season = ?"; $params[] = $season; }

$whereStr = implode(' AND ', $where);
$total = $pdo->prepare("SELECT COUNT(*) FROM crops c LEFT JOIN farmers f ON c.farmer_id=f.id WHERE $whereStr");
$total->execute($params);
$total = (int)$total->fetchColumn();
$pag   = paginate($total, $per_page, $page);

$stmt = $pdo->prepare("
    SELECT c.*, cc.name as category_name, f.farm_name, f.location
    FROM crops c
    LEFT JOIN crop_categories cc ON c.category_id = cc.id
    LEFT JOIN farmers f ON c.farmer_id = f.id
    WHERE $whereStr
    ORDER BY c.created_at DESC
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$crops = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM crop_categories ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Crop Management</div>
      <div class="page-subtitle"><?= number_format($total) ?> crops found</div>
    </div>
    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Crop</a>
  </div>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search crops, farms…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="category">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">All Status</option>
      <option value="available"   <?= $status==='available'   ?'selected':'' ?>>Available</option>
      <option value="sold_out"    <?= $status==='sold_out'    ?'selected':'' ?>>Sold Out</option>
      <option value="coming_soon" <?= $status==='coming_soon' ?'selected':'' ?>>Coming Soon</option>
      <option value="inactive"    <?= $status==='inactive'    ?'selected':'' ?>>Inactive</option>
    </select>
    <select name="season">
      <option value="">All Seasons</option>
      <option value="Kharif"     <?= $season==='Kharif'     ?'selected':'' ?>>Kharif</option>
      <option value="Rabi"       <?= $season==='Rabi'       ?'selected':'' ?>>Rabi</option>
      <option value="Zaid"       <?= $season==='Zaid'       ?'selected':'' ?>>Zaid</option>
      <option value="Year-round" <?= $season==='Year-round' ?'selected':'' ?>>Year-round</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $cat || $status || $season): ?>
    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Table -->
  <div class="card">
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll"></th>
            <th>Crop Name</th>
            <th>Category</th>
            <th>Farm</th>
            <th>Season</th>
            <th>Price</th>
            <th>Quantity</th>
            <th>Harvest Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($crops)): ?>
          <tr><td colspan="10">
            <div class="empty-state">
              <i class="fas fa-leaf"></i>
              <h3>No crops found</h3>
              <p>Try adjusting your search filters or add a new crop.</p>
              <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add First Crop</a>
            </div>
          </td></tr>
          <?php else: foreach ($crops as $crop): ?>
          <tr data-searchable>
            <td><input type="checkbox" class="row-check"></td>
            <td>
              <a href="view.php?id=<?= $crop['id'] ?>" style="font-weight:600;color:var(--primary);"><?= htmlspecialchars($crop['name']) ?></a>
              <?php if ($crop['variety']): ?><div class="text-muted text-sm"><?= htmlspecialchars($crop['variety']) ?></div><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($crop['category_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($crop['farm_name'] ?? '—') ?><br><span class="text-muted text-sm"><?= htmlspecialchars($crop['location'] ?? '') ?></span></td>
            <td><span class="badge badge-primary"><?= htmlspecialchars($crop['season']) ?></span></td>
            <td><strong><?= formatCurrency($crop['price']) ?></strong>/<?= htmlspecialchars($crop['unit']) ?></td>
            <td><?= number_format($crop['quantity']) ?> <?= htmlspecialchars($crop['unit']) ?></td>
            <td><?= $crop['harvest_date'] ? date('d M Y', strtotime($crop['harvest_date'])) : '—' ?></td>
            <td>
              <?php $cls = ['available'=>'badge-success','sold_out'=>'badge-danger','coming_soon'=>'badge-warning','inactive'=>'badge-secondary'][$crop['status']] ?? 'badge-secondary'; ?>
              <span class="badge <?= $cls ?>"><?= str_replace('_',' ',ucfirst($crop['status'])) ?></span>
            </td>
            <td>
              <div class="d-flex gap-8">
                <a href="view.php?id=<?= $crop['id'] ?>" class="btn btn-sm btn-secondary" data-tooltip="View">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="edit.php?id=<?= $crop['id'] ?>" class="btn btn-sm btn-outline-primary" data-tooltip="Edit">
                  <i class="fas fa-edit"></i>
                </a>
                <a href="delete.php?id=<?= $crop['id'] ?>"
                   class="btn btn-sm btn-outline-danger"
                   data-confirm="Delete crop '<?= htmlspecialchars(addslashes($crop['name'])) ?>'? This cannot be undone."
                   data-tooltip="Delete">
                  <i class="fas fa-trash"></i>
                </a>
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
        Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$per_page, $total) ?> of <?= $total ?>
      </div>
      <div class="pagination">
        <?php
        $q = http_build_query(array_merge($_GET, ['page' => $pag['current'] - 1]));
        ?>
        <a href="?<?= $q ?>" class="page-link <?= !$pag['has_prev'] ? 'disabled' : '' ?>"><i class="fas fa-chevron-left"></i></a>
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
