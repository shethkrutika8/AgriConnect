<?php
require_once '../config/db.php';
requireAdmin();

$active_page = 'users';
$page_title  = 'User Accounts Management';
$breadcrumb  = [
    ['label' => 'Settings', 'url' => BASE_URL . '/settings/general.php'],
    ['label' => 'Users']
];

$pdo = getDB();

// Filters & Search
$search   = trim($_GET['search'] ?? '');
$role     = trim($_GET['role'] ?? '');
$status   = trim($_GET['status'] ?? '');
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($role && in_array($role, ['admin', 'farmer', 'buyer'])) {
    $where[] = "role = ?";
    $params[] = $role;
}
if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereStr = implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

// Fetch
$stmt = $pdo->prepare("
    SELECT id, name, email, role, phone, avatar, is_verified, is_2fa_enabled, status, last_login, created_at
    FROM users
    WHERE $whereStr
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET {$pag['offset']}
");
$stmt->execute($params);
$usersList = $stmt->fetchAll();

// Add User Processing
$createError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $newName  = trim($_POST['name'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newRole  = trim($_POST['role'] ?? 'farmer');
    $newPass  = trim($_POST['password'] ?? '');
    $newStat  = trim($_POST['status'] ?? 'active');

    if (empty($newName) || empty($newEmail) || empty($newPass)) {
        $createError = 'Name, email, and password are required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $createError = 'Invalid email address format.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$newEmail]);
        if ($chk->fetch()) {
            $createError = 'This email is already registered in the system.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins = $pdo->prepare("
                INSERT INTO users (name, email, phone, role, password, status, is_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $ins->execute([$newName, $newEmail, $newPhone, $newRole, $hash, $newStat]);
            $newUid = $pdo->lastInsertId();

            if ($newRole === 'farmer') {
                $pdo->prepare("INSERT INTO farmers (user_id, farm_name, status) VALUES (?, ?, 'active')")
                    ->execute([$newUid, "$newName's Farm"]);
            }

            logActivity(currentUser()['id'], 'user_created', "Admin created user: $newEmail ($newRole)");
            redirect(BASE_URL . '/settings/users.php', "User account for $newName created successfully!", 'success');
        }
    }
}

include '../includes/header.php';
?>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">User Accounts Administration</div>
      <div class="page-subtitle"><?= number_format($total) ?> total registered users</div>
    </div>
    <div class="d-flex gap-8">
      <button class="btn btn-primary" data-modal-open="addUserModal">
        <i class="fas fa-user-plus"></i> Add New User
      </button>
      <a href="general.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> System Settings
      </a>
    </div>
  </div>

  <?php if (!empty($createError)): ?>
  <div class="alert alert-danger mb-24">
    <i class="fas fa-exclamation-circle"></i>
    <div><?= htmlspecialchars($createError) ?></div>
  </div>
  <?php endif; ?>

  <!-- Filter Bar -->
  <form method="GET" class="filter-bar mb-24">
    <div class="search-box">
      <i class="fas fa-search"></i>
      <input type="text" name="search" placeholder="Search name, email, phone…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="role">
      <option value="">All Roles</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
      <option value="farmer" <?= $role === 'farmer' ? 'selected' : '' ?>>Farmer</option>
      <option value="buyer" <?= $role === 'buyer' ? 'selected' : '' ?>>Buyer</option>
    </select>
    <select name="status">
      <option value="">All Statuses</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
    <?php if ($search || $role || $status): ?>
    <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
    <?php endif; ?>
  </form>

  <!-- Users Table Card -->
  <div class="card">
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Phone</th>
            <th>Security / 2FA</th>
            <th>Status</th>
            <th>Last Active</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($usersList)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">No user accounts found matching your filters.</td></tr>
          <?php else: foreach ($usersList as $u): ?>
          <tr data-searchable>
            <td>
              <div class="d-flex align-center gap-10">
                <div class="avatar-initials avatar-sm" style="font-size:12px;">
                  <?= strtoupper(substr($u['name'], 0, 1)) ?>
                </div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></div>
                  <div class="text-muted text-sm"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <?php 
              $rCls = ['admin' => 'badge-danger', 'farmer' => 'badge-primary', 'buyer' => 'badge-info'][$u['role']] ?? 'badge-secondary';
              ?>
              <span class="badge <?= $rCls ?>"><?= ucfirst($u['role']) ?></span>
            </td>
            <td><?= htmlspecialchars($u['phone'] ?: '—') ?></td>
            <td>
              <?php if (!empty($u['is_2fa_enabled'])): ?>
              <span class="badge badge-success"><i class="fas fa-shield-alt"></i> 2FA Active</span>
              <?php else: ?>
              <span class="badge badge-secondary" style="opacity:0.7;">Password Only</span>
              <?php endif; ?>
            </td>
            <td>
              <?php 
              $sCls = ['active' => 'badge-success', 'inactive' => 'badge-secondary', 'suspended' => 'badge-danger'][$u['status']] ?? 'badge-secondary';
              ?>
              <span class="badge <?= $sCls ?>"><?= ucfirst($u['status']) ?></span>
            </td>
            <td>
              <div class="text-sm"><?= !empty($u['last_login']) ? timeAgo($u['last_login']) : 'Never' ?></div>
            </td>
            <td>
              <div class="text-sm"><?= date('d M Y', strtotime($u['created_at'])) ?></div>
            </td>
            <td>
              <div class="d-flex gap-8">
                <a href="user_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" data-tooltip="Edit User">
                  <i class="fas fa-edit"></i>
                </a>
                <?php if ($u['id'] != currentUser()['id']): ?>
                <a href="user_delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete user account '<?= htmlspecialchars(addslashes($u['name'])) ?>'?" data-tooltip="Delete User">
                  <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pag['total_pages'] > 1): ?>
    <div class="card-footer d-flex align-center justify-between">
      <div class="text-muted text-sm">
        Showing <?= $pag['offset'] + 1 ?>–<?= min($pag['offset'] + $per_page, $total) ?> of <?= $total ?> users
      </div>
      <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['current'] - 1])) ?>" class="page-link <?= !$pag['has_prev'] ? 'disabled' : '' ?>"><i class="fas fa-chevron-left"></i></a>
        <?php for ($p = 1; $p <= $pag['total_pages']; $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="page-link <?= $p == $pag['current'] ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pag['current'] + 1])) ?>" class="page-link <?= !$pag['has_next'] ? 'disabled' : '' ?>"><i class="fas fa-chevron-right"></i></a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Add New User Account</div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="req">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Patel" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Address <span class="req">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="+91-9876543210">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">User Role <span class="req">*</span></label>
            <select name="role" class="form-control">
              <option value="farmer">🌾 Farmer</option>
              <option value="buyer">🛒 Buyer</option>
              <option value="admin">⚙️ Administrator</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Account Status</label>
            <select name="status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Initial Password <span class="req">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required>
        </div>
      </div>
      <div class="modal-footer d-flex justify-between">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Account</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
