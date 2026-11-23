<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'products';
$page_title  = 'Product Management';
$breadcrumb  = [['label' => 'Products']];
$pdo = getDB();

$search = trim($_GET['search'] ?? '');
$cat    = (int)($_GET['category'] ?? 0);
$status = trim($_GET['status'] ?? '');
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($search) { $where[] = "(p.name LIKE ? OR f.farm_name LIKE ?)"; $params = ["%$search%","%$search%"]; }
if ($cat)    { $where[] = "p.category_id = ?"; $params[] = $cat; }
if ($status) { $where[] = "p.status = ?"; $params[] = $status; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN farmers f ON p.farmer_id=f.id WHERE $whereStr");
$total->execute($params); $total = (int)$total->fetchColumn();
$pag = paginate($total, $per_page, $page);

$stmt = $pdo->prepare("
    SELECT p.*, pc.name as cat_name, f.farm_name, f.location
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id=pc.id
    LEFT JOIN farmers f ON p.farmer_id=f.id
    WHERE $whereStr ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$products = $stmt->fetchAll();
$categories = $pdo->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();

include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Product Management</div><div class="page-subtitle"><?= $total ?> products</div></div>
    <a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
  </div>

  <form method="GET" class="filter-bar">
    <div class="search-box"><i class="fas fa-search"></i><input type="text" name="search" placeholder="Search products…" value="<?= htmlspecialchars($search) ?>"></div>
    <select name="category"><option value="">All Categories</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
    <select name="status"><option value="">All Status</option><option value="active" <?= $status==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option><option value="out_of_stock" <?= $status==='out_of_stock'?'selected':'' ?>>Out of Stock</option></select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <?php if($search||$cat||$status): ?><a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
  </form>

  <div class="card">
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th><input type="checkbox" id="selectAll"></th><th>Product</th><th>Category</th><th>Farm</th><th>Price</th><th>Qty</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if(empty($products)): ?>
          <tr><td colspan="8"><div class="empty-state"><i class="fas fa-box-open"></i><h3>No products found</h3><p>Add a product to get started.</p><a href="add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a></div></td></tr>
          <?php else: foreach($products as $p): ?>
          <tr data-searchable>
            <td><input type="checkbox" class="row-check"></td>
            <td>
              <a href="view.php?id=<?= $p['id'] ?>" style="font-weight:600;color:var(--primary);"><?= htmlspecialchars($p['name']) ?></a>
              <div class="text-muted text-sm"><?= htmlspecialchars(substr($p['description']??'',0,50)) ?><?= strlen($p['description']??'')>50?'…':'' ?></div>
            </td>
            <td><?= htmlspecialchars($p['cat_name']??'—') ?></td>
            <td><?= htmlspecialchars($p['farm_name']??'—') ?><br><span class="text-muted text-sm"><?= htmlspecialchars($p['location']??'') ?></span></td>
            <td><strong><?= formatCurrency($p['price']) ?></strong>/<?= $p['unit'] ?></td>
            <td><?= number_format($p['quantity']) ?> <?= $p['unit'] ?></td>
            <td><?php $cls=['active'=>'badge-success','inactive'=>'badge-secondary','out_of_stock'=>'badge-danger'][$p['status']]??'badge-secondary'; ?><span class="badge <?= $cls ?>"><?= str_replace('_',' ',ucfirst($p['status'])) ?></span></td>
            <td>
              <div class="d-flex gap-8">
                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary" data-tooltip="View"><i class="fas fa-eye"></i></a>
                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" data-tooltip="Edit"><i class="fas fa-edit"></i></a>
                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete this product?" data-tooltip="Delete"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($pag['total_pages']>1): ?>
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
