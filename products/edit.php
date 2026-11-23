<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    redirect(BASE_URL . '/products/index.php', 'Product not found.', 'danger');
}

$active_page = 'products';
$page_title  = 'Edit Product';
$breadcrumb  = [
    ['label' => 'Products', 'url' => BASE_URL . '/products/index.php'],
    ['label' => $product['name'], 'url' => BASE_URL . '/products/view.php?id=' . $id],
    ['label' => 'Edit']
];

$categories = $pdo->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();
$farmers    = $pdo->query("SELECT f.id, f.farm_name, u.name FROM farmers f JOIN users u ON f.user_id = u.id WHERE f.status = 'active' ORDER BY f.farm_name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $farId    = (int)($_POST['farmer_id'] ?? 0);
    $catId    = (int)($_POST['category_id'] ?? 0);
    $price    = (float)($_POST['price'] ?? 0);
    $unit     = trim($_POST['unit'] ?? 'kg');
    $qty      = (int)($_POST['quantity'] ?? 0);
    $desc     = trim($_POST['description'] ?? '');
    $status   = trim($_POST['status'] ?? 'active');

    if (!$name)       $errors['name']  = 'Product name is required.';
    if ($price <= 0)  $errors['price'] = 'Price must be greater than 0.';
    if (!in_array($status, ['active', 'inactive', 'out_of_stock'])) $status = 'active';

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE products 
            SET farmer_id = ?, category_id = ?, name = ?, description = ?, price = ?, unit = ?, quantity = ?, status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $farId ?: null,
            $catId ?: null,
            $name,
            $desc,
            $price,
            $unit,
            $qty,
            $status,
            $id
        ]);

        logActivity(currentUser()['id'], 'product_updated', "Updated product: $name");
        redirect(BASE_URL . '/products/view.php?id=' . $id, 'Product updated successfully!', 'success');
    }

    $product = array_merge($product, [
        'farmer_id'   => $farId,
        'category_id' => $catId,
        'name'        => $name,
        'description' => $desc,
        'price'       => $price,
        'unit'        => $unit,
        'quantity'    => $qty,
        'status'      => $status,
    ]);
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Edit Product</div>
      <div class="page-subtitle">Update details for <?= htmlspecialchars($product['name']) ?></div>
    </div>
    <div class="d-flex gap-8">
      <a href="view.php?id=<?= $product['id'] ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View</a>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Product Details</div>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Product Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                 value="<?= htmlspecialchars($product['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Farmer / Supplier</label>
          <select name="farmer_id" class="form-control">
            <option value="">-- Select Farmer --</option>
            <?php foreach ($farmers as $f): ?>
            <option value="<?= $f['id'] ?>" <?= ($product['farmer_id'] == $f['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($f['farm_name'] . ' (' . $f['name'] . ')') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($product['category_id'] == $c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= ($product['status'] === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= ($product['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
            <option value="out_of_stock" <?= ($product['status'] === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock</option>
          </select>
        </div>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Price (₹) <span class="req">*</span></label>
          <input type="number" name="price" class="form-control <?= isset($errors['price']) ? 'error' : '' ?>"
                 value="<?= htmlspecialchars((string)$product['price']) ?>" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select name="unit" class="form-control">
            <?php foreach (['kg', 'quintal', 'litre', 'dozen', 'piece', 'bunch', 'box', 'bottle'] as $u): ?>
            <option value="<?= $u ?>" <?= ($product['unit'] === $u) ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" class="form-control"
                 value="<?= htmlspecialchars((string)$product['quantity']) ?>" min="0">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Describe the product specifications, usage, features..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="view.php?id=<?= $product['id'] ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </form>
</div>

<?php include '../includes/footer.php'; ?>
