<?php
require_once '../config/db.php';
requireLogin();
$active_page = 'products';
$page_title  = 'Add Product';
$breadcrumb  = [['label'=>'Products','url'=>BASE_URL.'/products/index.php'],['label'=>'Add']];
$pdo = getDB();
$categories = $pdo->query("SELECT * FROM product_categories ORDER BY name")->fetchAll();
$farmers    = $pdo->query("SELECT f.id, f.farm_name, u.name FROM farmers f JOIN users u ON f.user_id=u.id WHERE f.status='active' ORDER BY f.farm_name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']??'');
    $farId    = (int)($_POST['farmer_id']??0);
    $catId    = (int)($_POST['category_id']??0);
    $price    = (float)($_POST['price']??0);
    $unit     = trim($_POST['unit']??'kg');
    $qty      = (int)($_POST['quantity']??0);
    $desc     = trim($_POST['description']??'');
    $status   = trim($_POST['status']??'active');

    if (!$name)   $errors['name']  = 'Product name is required.';
    if ($price<=0) $errors['price'] = 'Price must be > 0.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO products (farmer_id,category_id,name,description,price,unit,quantity,status) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$farId?:null,$catId?:null,$name,$desc,$price,$unit,$qty,$status]);
        logActivity(currentUser()['id'],'product_added',"Added product: $name");
        redirect(BASE_URL.'/products/index.php','Product added!','success');
    }
}
include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header"><div><div class="page-title">Add Product</div></div><a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
  <?php if(!empty($errors)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?php foreach($errors as $e) echo "<div>$e</div>"; ?></div></div><?php endif; ?>
  <form method="POST" class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-box-open" style="color:var(--primary);margin-right:8px;"></i>Product Details</div></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Product Name <span class="req">*</span></label><input type="text" name="name" class="form-control" placeholder="e.g. Organic Spinach" required></div>
        <div class="form-group"><label class="form-label">Farmer</label>
          <select name="farmer_id" class="form-control"><option value="">-- Select Farmer --</option><?php foreach($farmers as $f): ?><option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['farm_name']) ?></option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Category</label>
          <select name="category_id" class="form-control"><option value="">-- Select --</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option><option value="out_of_stock">Out of Stock</option></select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group"><label class="form-label">Price (₹) <span class="req">*</span></label><input type="number" name="price" class="form-control" placeholder="0.00" step="0.01" min="0" required></div>
        <div class="form-group"><label class="form-label">Unit</label>
          <select name="unit" class="form-control"><?php foreach(['kg','quintal','litre','dozen','piece','bunch','box'] as $u): ?><option value="<?= $u ?>"><?= $u ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label class="form-label">Quantity</label><input type="number" name="quantity" class="form-control" placeholder="0" min="0"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Describe the product…"></textarea></div>
    </div>
    <div class="card-footer d-flex justify-between"><a href="index.php" class="btn btn-secondary">Cancel</a><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Product</button></div>
  </form>
</div>
<?php include '../includes/footer.php'; ?>
