<?php
require_once '../config/db.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();
$user = currentUser();

$stmt = $pdo->prepare("SELECT * FROM marketplace_listings WHERE id = ?");
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    redirect(BASE_URL . '/marketplace/index.php', 'Listing not found.', 'danger');
}

// Check permission: seller or admin
if ($user['role'] !== 'admin' && $user['id'] != $listing['seller_id']) {
    redirect(BASE_URL . '/marketplace/view.php?id=' . $id, 'Unauthorized to edit this listing.', 'danger');
}

$active_page = 'marketplace';
$page_title  = 'Edit Marketplace Listing';
$breadcrumb  = [
    ['label' => 'Marketplace', 'url' => BASE_URL . '/marketplace/index.php'],
    ['label' => $listing['title'], 'url' => BASE_URL . '/marketplace/view.php?id=' . $id],
    ['label' => 'Edit']
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $price    = (float)($_POST['price'] ?? 0);
    $qty      = (float)($_POST['quantity'] ?? 0);
    $unit     = trim($_POST['unit'] ?? 'kg');
    $location = trim($_POST['location'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $status   = trim($_POST['status'] ?? 'active');

    if (!$title)     $errors['title']    = 'Title is required.';
    if ($price <= 0) $errors['price']    = 'Price must be greater than 0.';
    if ($qty < 0)    $errors['quantity'] = 'Quantity cannot be negative.';

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE marketplace_listings
            SET title = ?, price = ?, quantity = ?, unit = ?, location = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $title, $price, $qty, $unit, $location, $desc, $status, $id
        ]);

        logActivity($user['id'], 'listing_updated', "Updated marketplace listing: $title");
        redirect(BASE_URL . '/marketplace/view.php?id=' . $id, 'Marketplace listing updated successfully!', 'success');
    }

    $listing = array_merge($listing, [
        'title'       => $title,
        'price'       => $price,
        'quantity'    => $qty,
        'unit'        => $unit,
        'location'    => $location,
        'description' => $desc,
        'status'      => $status,
    ]);
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Edit Marketplace Listing</div>
      <div class="page-subtitle"><?= htmlspecialchars($listing['title']) ?></div>
    </div>
    <div class="d-flex gap-8">
      <a href="view.php?id=<?= $listing['id'] ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> View Listing</a>
      <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Marketplace</a>
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
      <div class="card-title"><i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Listing Information</div>
      <span class="badge badge-primary"><?= ucfirst($listing['type']) ?></span>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Listing Title <span class="req">*</span></label>
          <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($listing['title']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($listing['location'] ?? '') ?>">
        </div>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Price per Unit (₹) <span class="req">*</span></label>
          <input type="number" name="price" class="form-control" value="<?= htmlspecialchars((string)$listing['price']) ?>" step="0.01" min="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Unit</label>
          <select name="unit" class="form-control">
            <?php foreach (['kg', 'quintal', 'tonne', 'litre', 'dozen', 'piece', 'bunch', 'box'] as $u): ?>
            <option value="<?= $u ?>" <?= ($listing['unit'] === $u) ? 'selected' : '' ?>><?= $u ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Quantity Available</label>
          <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars((string)$listing['quantity']) ?>" min="0" step="any" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= ($listing['status'] === 'active') ? 'selected' : '' ?>>Active (Visible on marketplace)</option>
            <option value="sold" <?= ($listing['status'] === 'sold') ? 'selected' : '' ?>>Sold (Out of stock)</option>
            <option value="inactive" <?= ($listing['status'] === 'inactive') ? 'selected' : '' ?>>Inactive (Hidden)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Description & Quality Details</label>
        <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($listing['description'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="card-footer d-flex justify-between">
      <a href="view.php?id=<?= $listing['id'] ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </form>
</div>

<?php include '../includes/footer.php'; ?>
