<?php
require_once '../config/db.php';
requireAdmin();

$id  = (int)($_GET['id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    redirect(BASE_URL . '/settings/users.php', 'User account not found.', 'danger');
}

$active_page = 'users';
$page_title  = 'Edit User: ' . $targetUser['name'];
$breadcrumb  = [
    ['label' => 'Settings', 'url' => BASE_URL . '/settings/general.php'],
    ['label' => 'Users', 'url' => BASE_URL . '/settings/users.php'],
    ['label' => 'Edit User']
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = trim($_POST['role'] ?? 'farmer');
    $status   = trim($_POST['status'] ?? 'active');
    $newPass  = trim($_POST['new_password'] ?? '');
    $tfaState = !empty($_POST['is_2fa_enabled']) ? 1 : 0;

    if (empty($name))  $errors['name']  = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';

    // Check email uniqueness if changed
    if ($email !== $targetUser['email']) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $id]);
        if ($chk->fetch()) $errors['email'] = 'Email already in use by another account.';
    }

    if (empty($errors)) {
        if (!empty($newPass)) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, status=?, is_2fa_enabled=?, password=? WHERE id=?")
                ->execute([$name, $email, $phone, $role, $status, $tfaState, $hash, $id]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, role=?, status=?, is_2fa_enabled=? WHERE id=?")
                ->execute([$name, $email, $phone, $role, $status, $tfaState, $id]);
        }

        logActivity(currentUser()['id'], 'user_updated', "Admin updated user #$id: $email");
        redirect(BASE_URL . '/settings/users.php', "User {$name} updated successfully!", 'success');
    }

    $targetUser = array_merge($targetUser, [
        'name'           => $name,
        'email'          => $email,
        'phone'          => $phone,
        'role'           => $role,
        'status'         => $status,
        'is_2fa_enabled' => $tfaState,
    ]);
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">Edit User Account</div>
      <div class="page-subtitle"><?= htmlspecialchars($targetUser['name']) ?> (<?= htmlspecialchars($targetUser['email']) ?>)</div>
    </div>
    <a href="users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-24">
    <i class="fas fa-exclamation-circle"></i>
    <div><?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" class="card">
    <div class="card-header d-flex justify-between align-center">
      <div class="card-title"><i class="fas fa-user-cog" style="color:var(--primary);margin-right:8px;"></i>Account Credentials</div>
      <span class="badge badge-primary"><?= ucfirst($targetUser['role']) ?></span>
    </div>
    <div class="card-body">
      
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($targetUser['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address <span class="req">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($targetUser['email']) ?>" required>
        </div>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($targetUser['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Role</label>
          <select name="role" class="form-control">
            <option value="admin" <?= $targetUser['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
            <option value="farmer" <?= $targetUser['role'] === 'farmer' ? 'selected' : '' ?>>Farmer</option>
            <option value="buyer" <?= $targetUser['role'] === 'buyer' ? 'selected' : '' ?>>Buyer</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Account Status</label>
          <select name="status" class="form-control">
            <option value="active" <?= $targetUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $targetUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= $targetUser['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Reset Password (Leave blank to keep unchanged)</label>
          <input type="password" name="new_password" class="form-control" placeholder="New password">
        </div>
        <div class="form-group" style="display:flex;align-items:center;margin-top:24px;">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;">
            <input type="checkbox" name="is_2fa_enabled" value="1" <?= !empty($targetUser['is_2fa_enabled']) ? 'checked' : '' ?> style="width:18px;height:18px;">
            <span>Enable Two-Factor Authentication (2FA) for this user</span>
          </label>
        </div>
      </div>

    </div>
    <div class="card-footer d-flex justify-between">
      <a href="users.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </form>
</div>

<?php include '../includes/footer.php'; ?>
