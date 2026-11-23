<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$crop = $pdo->prepare("SELECT * FROM crops WHERE id = ?");
$crop->execute([$id]);
$crop = $crop->fetch();
if (!$crop) redirect(BASE_URL . '/crops/index.php', 'Crop not found.', 'danger');

$active_page = 'crops';
$page_title  = 'Edit Crop';
$breadcrumb  = [['label'=>'Crops','url'=>BASE_URL.'/crops/index.php'],['label'=>'Edit']];

$categories = $pdo->query("SELECT * FROM crop_categories ORDER BY name")->fetchAll();
$farmers    = $pdo->query("SELECT f.id, f.farm_name, u.name FROM farmers f JOIN users u ON f.user_id=u.id WHERE f.status='active' ORDER BY f.farm_name")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmerId   = (int)($_POST['farmer_id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name       = trim($_POST['name'] ?? '');
    $variety    = trim($_POST['variety'] ?? '');
    $season     = trim($_POST['season'] ?? '');
    $price      = (float)($_POST['price'] ?? 0);
    $unit       = trim($_POST['unit'] ?? 'kg');
    $quantity   = (float)($_POST['quantity'] ?? 0);
    $harvestDate= trim($_POST['harvest_date'] ?? '');
    $plantingDate=trim($_POST['planting_date'] ?? '');
    $description= trim($_POST['description'] ?? '');
    $status     = trim($_POST['status'] ?? 'available');

    if (empty($name))  $errors['name'] = 'Crop name is required.';
    if ($price <= 0)   $errors['price'] = 'Price must be greater than 0.';

    if (empty($errors)) {
        $pdo->prepare("
            UPDATE crops SET farmer_id=?, category_id=?, name=?, variety=?, season=?,
            price=?, unit=?, quantity=?, harvest_date=?, planting_date=?, description=?, status=?
            WHERE id=?
        ")->execute([
            $farmerId ?: null, $categoryId ?: null,
            $name, $variety, $season,
            $price, $unit, $quantity,
            $harvestDate ?: null, $plantingDate ?: null,
            $description, $status, $id
        ]);
        logActivity(currentUser()['id'], 'crop_updated', "Updated crop: $name");
        redirect(BASE_URL . '/crops/index.php', 'Crop updated successfully!', 'success');
    }
    // Keep posted values
    $crop = array_merge($crop, ['farmer_id'=>$farmerId,'category_id'=>$categoryId,'name'=>$name,'variety'=>$variety,'season'=>$season,'price'=>$price,'unit'=>$unit,'quantity'=>$quantity,'harvest_date'=>$harvestDate,'planting_date'=>$plantingDate,'description'=>$description,'status'=>$status]);
}

include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Edit Crop</div><div class="page-subtitle">Update crop details</div></div>
    <div class="d-flex gap-8">
      <a href="view.php?id=<?= $crop['id'] ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View</a>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?php foreach($errors as $e) echo "<div>$e</div>"; ?></div></div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Edit: <?= htmlspecialchars($crop['name']) ?></div></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Crop Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($crop['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Variety / Type</label>
          <input type="text" name="variety" class="form-control" value="<?= htmlspecialchars($crop['variety'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Farmer</label>
          <select name="farmer_id" class="form-control">
            <option value="">-- Select Farmer --</option>
            <?php foreach ($farmers as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $crop['farmer_id'] == $f['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($f['farm_name'] . ' (' . $f['name'] . ')') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control">
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $crop['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Season</label>
          <select name="season" class="form-control">
            <?php foreach (['Kharif','Rabi','Zaid','Year-round'] as $s): ?>
            <option value="<?= $s ?>" <?= $crop['season'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Price (₹) <span class="req">*</span></label>
          <input type="number" name="price" class="form-control" value="<?= $crop['price'] ?>" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select name="unit" class="form-control">
            <?php foreach (['kg','quintal','tonne','litre','dozen','piece','bunch'] as $u): ?>
            <option value="<?= $u ?>" <?= $crop['unit'] === $u ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Quantity</label>
          <input type="number" name="quantity" class="form-control" value="<?= $crop['quantity'] ?>" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="available"   <?= $crop['status']==='available'   ?'selected':'' ?>>Available</option>
            <option value="sold_out"    <?= $crop['status']==='sold_out'    ?'selected':'' ?>>Sold Out</option>
            <option value="coming_soon" <?= $crop['status']==='coming_soon' ?'selected':'' ?>>Coming Soon</option>
            <option value="inactive"    <?= $crop['status']==='inactive'    ?'selected':'' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Planting Date</label>
          <input type="date" name="planting_date" class="form-control" value="<?= $crop['planting_date'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Harvest Date</label>
          <input type="date" name="harvest_date" class="form-control" value="<?= $crop['harvest_date'] ?? '' ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($crop['description'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Crop</button>
    </div>
  </form>
</div>
<?php include '../includes/footer.php'; ?>
