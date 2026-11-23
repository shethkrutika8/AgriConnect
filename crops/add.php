<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'crops';
$page_title  = 'Add Crop';
$breadcrumb  = [['label'=>'Crops','url'=>BASE_URL.'/crops/index.php'],['label'=>'Add Crop']];

$pdo = getDB();
$categories = $pdo->query("SELECT * FROM crop_categories ORDER BY name")->fetchAll();
$farmers    = $pdo->query("SELECT f.id, f.farm_name, u.name FROM farmers f JOIN users u ON f.user_id=u.id WHERE f.status='active' ORDER BY f.farm_name")->fetchAll();

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'farmer_id'   => (int)($_POST['farmer_id'] ?? 0),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'name'        => trim($_POST['name'] ?? ''),
        'variety'     => trim($_POST['variety'] ?? ''),
        'season'      => trim($_POST['season'] ?? ''),
        'price'       => (float)($_POST['price'] ?? 0),
        'unit'        => trim($_POST['unit'] ?? 'kg'),
        'quantity'    => (float)($_POST['quantity'] ?? 0),
        'harvest_date'=> trim($_POST['harvest_date'] ?? ''),
        'planting_date'=>trim($_POST['planting_date'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status'      => trim($_POST['status'] ?? 'available'),
    ];

    if (empty($data['name']))  $errors['name'] = 'Crop name is required.';
    if ($data['price'] <= 0)   $errors['price'] = 'Price must be greater than 0.';
    if (!$data['farmer_id'])   $errors['farmer_id'] = 'Select a farmer.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO crops (farmer_id, category_id, name, variety, season, price, unit, quantity, harvest_date, planting_date, description, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $data['farmer_id'], $data['category_id'] ?: null,
            $data['name'], $data['variety'], $data['season'],
            $data['price'], $data['unit'], $data['quantity'],
            $data['harvest_date'] ?: null, $data['planting_date'] ?: null,
            $data['description'], $data['status']
        ]);
        logActivity(currentUser()['id'], 'crop_added', "Added crop: {$data['name']}");
        redirect(BASE_URL . '/crops/index.php', 'Crop added successfully!', 'success');
    }
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Add New Crop</div>
      <div class="page-subtitle">Fill in the crop details below</div>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>$e</div>"; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i>Crop Information</div></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Crop Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control <?= isset($errors['name'])?'error':'' ?>"
                 placeholder="e.g. Wheat" value="<?= htmlspecialchars($data['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Variety / Type</label>
          <input type="text" name="variety" class="form-control" placeholder="e.g. HD-2967"
                 value="<?= htmlspecialchars($data['variety'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Farmer <span class="req">*</span></label>
          <select name="farmer_id" class="form-control <?= isset($errors['farmer_id'])?'error':'' ?>">
            <option value="">-- Select Farmer --</option>
            <?php foreach ($farmers as $f): ?>
            <option value="<?= $f['id'] ?>" <?= ($data['farmer_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>>
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
            <option value="<?= $c['id'] ?>" <?= ($data['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Season</label>
          <select name="season" class="form-control">
            <?php foreach (['Kharif','Rabi','Zaid','Year-round'] as $s): ?>
            <option value="<?= $s ?>" <?= ($data['season'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Price <span class="req">*</span></label>
          <div class="input-group">
            <span class="input-icon">₹</span>
            <input type="number" name="price" class="form-control <?= isset($errors['price'])?'error':'' ?>"
                   placeholder="0.00" step="0.01" min="0" value="<?= $data['price'] ?? '' ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select name="unit" class="form-control">
            <?php foreach (['kg','quintal','tonne','litre','dozen','piece','bunch'] as $u): ?>
            <option value="<?= $u ?>" <?= ($data['unit'] ?? 'kg') === $u ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Available Quantity</label>
          <input type="number" name="quantity" class="form-control" placeholder="0" min="0" step="0.01" value="<?= $data['quantity'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="available"   <?= ($data['status'] ?? '') === 'available'   ? 'selected' : '' ?>>Available</option>
            <option value="coming_soon" <?= ($data['status'] ?? '') === 'coming_soon' ? 'selected' : '' ?>>Coming Soon</option>
            <option value="inactive"    <?= ($data['status'] ?? '') === 'inactive'    ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Planting Date</label>
          <input type="date" name="planting_date" class="form-control" value="<?= $data['planting_date'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Expected Harvest Date</label>
          <input type="date" name="harvest_date" class="form-control" value="<?= $data['harvest_date'] ?? '' ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Describe the crop, growing methods, quality, etc."><?= htmlspecialchars($data['description'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Crop</button>
    </div>
  </form>
</div>

<?php include '../includes/footer.php'; ?>
