<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'farmers';
$page_title  = 'Edit Farmer';
$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$farmer = $pdo->prepare("SELECT f.*, u.name, u.email FROM farmers f JOIN users u ON f.user_id=u.id WHERE f.id=?");
$farmer->execute([$id]);
$farmer = $farmer->fetch();
if (!$farmer) redirect(BASE_URL . '/farmers/index.php', 'Not found.', 'danger');
$breadcrumb = [['label'=>'Farmers','url'=>BASE_URL.'/farmers/index.php'],['label'=>'Edit']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE farmers SET farm_name=?,location=?,farm_size=?,size_unit=?,soil_type=?,irrigation_type=?,experience_years=?,bio=?,status=? WHERE id=?")
        ->execute([
            trim($_POST['farm_name']??''), trim($_POST['location']??''),
            (float)($_POST['farm_size']??0) ?: null, $_POST['size_unit']??'acres',
            trim($_POST['soil_type']??''), trim($_POST['irrigation_type']??''),
            (int)($_POST['experience_years']??0), trim($_POST['bio']??''),
            $_POST['status']??'active', $id
        ]);
    redirect(BASE_URL . '/farmers/index.php', 'Farmer updated.', 'success');
}
include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Edit Farmer</div><div class="page-subtitle"><?= htmlspecialchars($farmer['name']) ?></div></div>
    <div class="d-flex gap-8">
      <a href="view.php?id=<?= $farmer['id'] ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View Profile</a>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>
  <form method="POST" class="card">
    <div class="card-header"><div class="card-title"><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Edit Farmer Profile</div></div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Farm Name</label>
          <input type="text" name="farm_name" class="form-control" value="<?= htmlspecialchars($farmer['farm_name']??'') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($farmer['location']??'') ?>">
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Farm Size</label>
          <input type="number" name="farm_size" class="form-control" value="<?= $farmer['farm_size'] ?>" step="0.5">
        </div>
        <div class="form-group">
          <label class="form-label">Size Unit</label>
          <select name="size_unit" class="form-control">
            <?php foreach(['acres','hectares','sq_meters'] as $u): ?><option value="<?= $u ?>" <?= $farmer['size_unit']===$u?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$u)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Experience (years)</label>
          <input type="number" name="experience_years" class="form-control" value="<?= $farmer['experience_years'] ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Soil Type</label>
          <select name="soil_type" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach(['Alluvial','Black Cotton','Red Laterite','Sandy Loam','Clay','Loamy','Sandy','Silt'] as $s): ?><option <?= $farmer['soil_type']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Irrigation Type</label>
          <select name="irrigation_type" class="form-control">
            <option value="">-- Select --</option>
            <?php foreach(['Drip Irrigation','Sprinkler','Canal','Flood','Rainfed','Borewell'] as $s): ?><option <?= $farmer['irrigation_type']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= $farmer['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $farmer['status']==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Bio</label>
        <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($farmer['bio']??'') ?></textarea>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Farmer</button>
    </div>
  </form>
</div>
<?php include '../includes/footer.php'; ?>
