<?php
require_once '../config/db.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$farmer = $pdo->prepare("SELECT f.*, u.name as user_name, u.email, u.phone, u.status as user_status, u.created_at as member_since FROM farmers f JOIN users u ON f.user_id=u.id WHERE f.id=?");
$farmer->execute([$id]);
$farmer = $farmer->fetch();
if (!$farmer) redirect(BASE_URL . '/farmers/index.php', 'Farmer not found.', 'danger');

$crops = $pdo->prepare("SELECT c.*, cc.name as cat_name FROM crops c LEFT JOIN crop_categories cc ON c.category_id=cc.id WHERE c.farmer_id=? ORDER BY c.created_at DESC");
$crops->execute([$id]);
$crops = $crops->fetchAll();

$products = $pdo->prepare("SELECT p.*, pc.name as cat_name FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.farmer_id=? ORDER BY p.created_at DESC LIMIT 5");
$products->execute([$id]);
$products = $products->fetchAll();

$active_page = 'farmers';
$page_title  = 'Farmer Profile';
$breadcrumb  = [['label'=>'Farmers','url'=>BASE_URL.'/farmers/index.php'],['label'=>$farmer['farm_name'] ?? $farmer['user_name']]];
include '../includes/header.php';
?>
<div class="page-content">
  <div class="page-header">
    <div><div class="page-title">Farmer Profile</div></div>
    <div class="d-flex gap-8">
      <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
      <a href="delete.php?id=<?= $id ?>" class="btn btn-outline-danger" data-confirm="Are you sure you want to delete this farmer profile?"><i class="fas fa-trash"></i> Delete</a>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <div class="grid-2 mb-24">
    <!-- Profile Card -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:32px;">
        <img src="<?= assetImg('crops/default_crop.jpg') ?>" alt="<?= htmlspecialchars($farmer['farm_name'] ?? 'Farm') ?>" class="thumb-56" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin:0 auto 16px;display:block;" loading="lazy">
        <div class="avatar-initials avatar-xl" style="margin:-52px auto 16px;font-size:28px;position:relative;">
          <?= strtoupper(substr($farmer['user_name'],0,1)) ?>
        </div>
        <h2 style="font-size:20px;font-weight:700;margin-bottom:4px;"><?= htmlspecialchars($farmer['farm_name'] ?? 'N/A') ?></h2>
        <p class="text-muted"><?= htmlspecialchars($farmer['user_name']) ?></p>
        <span class="badge <?= $farmer['status']==='active'?'badge-success':'badge-secondary' ?>" style="margin-top:8px;">
          <?= ucfirst($farmer['status']) ?>
        </span>
        <hr class="divider">
        <div style="text-align:left;">
          <?php $rows = [['fas fa-envelope','Email',$farmer['email']],['fas fa-phone','Phone',$farmer['phone']??'—'],['fas fa-map-marker-alt','Location',$farmer['location']??'—'],['fas fa-expand','Farm Size',($farmer['farm_size']?' '.$farmer['farm_size'].' '.$farmer['size_unit']:'—')],['fas fa-tint','Irrigation',$farmer['irrigation_type']??'—'],['fas fa-layer-group','Soil Type',$farmer['soil_type']??'—'],['fas fa-calendar','Experience',$farmer['experience_years'].' years'],['fas fa-user-clock','Member Since',date('d M Y', strtotime($farmer['member_since']))]];
          foreach($rows as [$icon,$label,$val]): ?>
          <div class="d-flex gap-12 mb-8">
            <i class="<?= $icon ?>" style="color:var(--primary);width:16px;margin-top:2px;"></i>
            <div><div class="text-muted text-sm"><?= $label ?></div><div style="font-weight:500;"><?= htmlspecialchars((string)$val) ?></div></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($farmer['bio']): ?>
        <hr class="divider">
        <p class="text-sm text-muted" style="text-align:left;"><?= htmlspecialchars($farmer['bio']) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats -->
    <div style="display:flex;flex-direction:column;gap:16px;">
      <?php
      $activeCrops = count(array_filter($crops, fn($c)=>$c['status']==='available'));
      $totalCropVal= array_sum(array_map(fn($c)=>$c['price']*$c['quantity'], $crops));
      $stats = [
        ['Total Crops','fas fa-leaf',$activeCrops . ' available / ' . count($crops) . ' total','green'],
        ['Products','fas fa-box-open',count($products) . ' products listed','blue'],
        ['Crop Value','fas fa-rupee-sign',formatCurrency($totalCropVal),'amber'],
        ['Experience','fas fa-award',$farmer['experience_years'] . ' years farming','green'],
      ];
      foreach($stats as [$lbl,$icon,$val,$color]): ?>
      <div class="stat-card <?= $color ?>">
        <div class="stat-icon <?= $color ?>"><i class="<?= $icon ?>"></i></div>
        <div class="stat-content">
          <div class="label"><?= $lbl ?></div>
          <div class="value" style="font-size:16px;"><?= htmlspecialchars($val) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Crops Table -->
  <div class="card mb-24">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i>Crops (<?= count($crops) ?>)</div>
      <a href="../crops/add.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Add Crop</a>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>Crop</th><th>Season</th><th>Price</th><th>Quantity</th><th>Harvest</th><th>Status</th></tr></thead>
        <tbody>
          <?php if(empty($crops)): ?><tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No crops added yet.</td></tr>
          <?php else: foreach($crops as $c): ?>
          <tr>
            <td>
              <div class="d-flex align-center gap-10">
                <img src="<?= cropImage($c) ?>" alt="<?= htmlspecialchars($c['name']) ?>" class="thumb-40" loading="lazy" width="44" height="44">
                <div><strong><?= htmlspecialchars($c['name']) ?></strong><?php if($c['variety']): ?><br><span class="text-muted text-sm"><?= htmlspecialchars($c['variety']) ?></span><?php endif; ?></div>
              </div>
            </td>
            <td><span class="badge badge-primary"><?= htmlspecialchars($c['season']) ?></span></td>
            <td><strong><?= formatCurrency($c['price']) ?></strong>/<?= $c['unit'] ?></td>
            <td><?= number_format($c['quantity']) ?> <?= $c['unit'] ?></td>
            <td><?= $c['harvest_date']?date('d M Y',strtotime($c['harvest_date'])):'—' ?></td>
            <td><?php $cls=['available'=>'badge-success','sold_out'=>'badge-danger','coming_soon'=>'badge-warning','inactive'=>'badge-secondary'][$c['status']]??'badge-secondary'; ?>
              <span class="badge <?= $cls ?>"><?= str_replace('_',' ',ucfirst($c['status'])) ?></span></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
