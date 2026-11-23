<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'farmers';
$page_title  = 'Add Farmer';
$breadcrumb  = [['label'=>'Farmers','url'=>BASE_URL.'/farmers/index.php'],['label'=>'Add Farmer']];
$pdo = getDB();

// Get users without farmer profiles
$users = $pdo->query("SELECT u.id, u.name, u.email FROM users u WHERE u.role='farmer' AND u.id NOT IN (SELECT user_id FROM farmers) ORDER BY u.name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = (int)($_POST['user_id'] ?? 0);
    $farmName     = trim($_POST['farm_name'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $farmSize     = (float)($_POST['farm_size'] ?? 0);
    $sizeUnit     = $_POST['size_unit'] ?? 'acres';
    $soilType     = trim($_POST['soil_type'] ?? '');
    $irrigationType = trim($_POST['irrigation_type'] ?? '');
    $expYears     = (int)($_POST['experience_years'] ?? 0);
    $bio          = trim($_POST['bio'] ?? '');

    if (!$userId)    $errors['user_id']   = 'Please select a user.';
    if (!$farmName)  $errors['farm_name'] = 'Farm name is required.';

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO farmers (user_id, farm_name, location, farm_size, size_unit, soil_type, irrigation_type, experience_years, bio, status) VALUES (?,?,?,?,?,?,?,?,?,'active')")
            ->execute([$userId, $farmName, $location, $farmSize ?: null, $sizeUnit, $soilType, $irrigationType, $expYears, $bio]);
        // also update user role to farmer
        $pdo->prepare("UPDATE users SET role='farmer' WHERE id=?")->execute([$userId]);
        redirect(BASE_URL . '/farmers/index.php', 'Farmer added successfully!', 'success');
    }
}
include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Add Farmer</div></div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if (!empty($errors)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?php foreach($errors as $e) echo "<div>$e</div>"; ?></div></div><?php endif; ?>
  <form method="POST" class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i>Farmer Details</div></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Linked User Account <span class="req">*</span></label>
        <select name="user_id" class="form-control <?= isset($errors['user_id'])?'error':'' ?>">
          <option value="">-- Select User --</option>
          <?php foreach($users as $u): ?><option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name'] . ' (' . $u['email'] . ')') ?></option><?php endforeach; ?>
        </select>
        <div class="form-hint">Only users with 'farmer' role who don't have a profile yet are listed.</div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Farm Name <span class="req">*</span></label>
          <input type="text" name="farm_name" class="form-control" placeholder="e.g. Green Valley Farm" required>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Punjab, India">
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Farm Size</label>
          <input type="number" name="farm_size" class="form-control" placeholder="0" min="0" step="0.5">
        </div>
        <div class="form-group">
          <label class="form-label">Size Unit</label>
          <select name="size_unit" class="form-control">
            <option value="acres">Acres</option>
            <option value="hectares">Hectares</option>
            <option value="sq_meters">Sq. Meters</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Experience (years)</label>
          <input type="number" name="experience_years" class="form-control" placeholder="0" min="0">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Soil Type</label>
          <select name="soil_type" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach(['Alluvial','Black Cotton','Red Laterite','Sandy Loam','Clay','Loamy','Sandy','Silt'] as $s): ?><option><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Irrigation Type</label>
          <select name="irrigation_type" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach(['Drip Irrigation','Sprinkler','Canal','Flood','Rainfed','Borewell'] as $s): ?><option><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Bio / About</label>
        <textarea name="bio" class="form-control" rows="3" placeholder="Short description about the farmer…"></textarea>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Farmer</button>
    </div>
  </form>
</div>
<?php include '../includes/footer.php'; ?>
