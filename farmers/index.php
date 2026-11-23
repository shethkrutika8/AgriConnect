<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'farmers';
$page_title  = 'Farmer Management';
$breadcrumb  = [['label' => 'Farmers']];
$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(f.farm_name LIKE ? OR u.name LIKE ? OR f.location LIKE ?)"; $params = ["%$search%","%$search%","%$search%"]; }
if ($status) { $where[] = "f.status = ?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM farmers f JOIN users u ON f.user_id=u.id WHERE $whereStr");
$total->execute($params); $total = (int)$total->fetchColumn();
$pag = paginate($total, $per_page, $page);

$stmt = $pdo->prepare("
    SELECT f.*, u.name as user_name, u.email, u.phone,
           (SELECT COUNT(*) FROM crops WHERE farmer_id=f.id) as crop_count
    FROM farmers f
    JOIN users u ON f.user_id=u.id
    WHERE $whereStr ORDER BY f.created_at DESC
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$farmers = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Farmer Management</div><div class="page-subtitle"><?= number_format($total) ?> farmers registered</div></div>
    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Farmer</a>
  </div>

  <form method="GET" class="filter-bar">
    <div class="search-box"><i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search farmers, farms, locations…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status">
      <option value="">All Status</option>
      <option value="active"   <?= $status==='active'   ?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $status==='inactive' ?'selected':'' ?>>Inactive</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $status): ?><a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
  </form>

  <div class="card">
    <div class="table-wrapper">
      <table class="table">
        <thead><tr>
          <th>Farmer / Farm</th><th>Contact</th><th>Location</th>
          <th>Farm Size</th><th>Crops</th><th>Experience</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($farmers)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="fas fa-user-tie"></i><h3>No farmers found</h3><p>Add a farmer to get started.</p><a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Farmer</a></div></td></tr>
          <?php else: foreach ($farmers as $f): ?>
          <tr data-searchable>
            <td>
              <a href="view.php?id=<?= $f['id'] ?>" style="font-weight:600;color:var(--primary);"><?= htmlspecialchars($f['farm_name'] ?? 'N/A') ?></a>
              <div class="text-muted text-sm"><?= htmlspecialchars($f['user_name']) ?></div>
            </td>
            <td>
              <div><?= htmlspecialchars($f['email']) ?></div>
              <div class="text-muted text-sm"><?= htmlspecialchars($f['phone'] ?? '—') ?></div>
            </td>
            <td><?= htmlspecialchars($f['location'] ?? '—') ?></td>
            <td><?= $f['farm_size'] ? $f['farm_size'] . ' ' . $f['size_unit'] : '—' ?></td>
            <td><span class="badge badge-primary"><?= $f['crop_count'] ?> crops</span></td>
            <td><?= $f['experience_years'] ? $f['experience_years'] . ' yrs' : '—' ?></td>
            <td><span class="badge <?= $f['status']==='active'?'badge-success':'badge-secondary' ?>"><?= ucfirst($f['status']) ?></span></td>
            <td>
              <div class="d-flex gap-8">
                <a href="view.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-secondary" data-tooltip="View"><i class="fas fa-eye"></i></a>
                <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                <a href="delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete farmer profile for '<?= htmlspecialchars(addslashes($f['farm_name'] ?? '')) ?>'?" data-tooltip="Delete"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex align-center justify-between">
      <div class="text-muted text-sm">Showing <?= $pag['offset']+1 ?>–<?= min($pag['offset']+$per_page,$total) ?> of <?= $total ?></div>
      <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$pag['current']-1])) ?>" class="page-link <?= !$pag['has_prev']?'disabled':'' ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for($p=1;$p<=$pag['total_pages'];$p++): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="page-link <?= $p==$pag['current']?'active':'' ?>"><?= $p ?></a><?php endfor; ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$pag['current']+1])) ?>" class="page-link <?= !$pag['has_next']?'disabled':'' ?>"><i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
