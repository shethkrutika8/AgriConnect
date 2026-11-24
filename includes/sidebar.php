<?php
// ============================================================
// AgriConnect – Sidebar Include
// Usage: include('../includes/sidebar.php');
// Requires: $active_page variable to highlight active link
// ============================================================
require_once dirname(__DIR__) . '/config/db.php';
$user = currentUser();
$active = $active_page ?? '';
?>
<div id="mobileOverlay" class="mobile-overlay"></div>
<aside id="sidebar" class="sidebar">
  <!-- Logo -->
  <div class="sidebar-logo">
    <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="fas fa-seedling" style="color:white;font-size:18px;"></i>
    </div>
    <div class="logo-text-wrap">
      <div class="logo-text">AgriConnect</div>
      <div class="logo-sub">Farm Management</div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">

    <!-- Main -->
    <div class="nav-section-label">Main</div>

    <a href="<?= BASE_URL ?>/dashboard.php"
       class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>"
       data-label="Dashboard">
      <i class="fas fa-th-large"></i>
      <span>Dashboard</span>
    </a>

    <!-- Farm Management -->
    <div class="nav-section-label">Farm Management</div>

    <a href="<?= BASE_URL ?>/crops/index.php"
       class="nav-item <?= str_starts_with($active, 'crops') ? 'active' : '' ?>"
       data-label="Crops">
      <i class="fas fa-leaf"></i>
      <span>Crops</span>
    </a>

    <a href="<?= BASE_URL ?>/farmers/index.php"
       class="nav-item <?= str_starts_with($active, 'farmers') ? 'active' : '' ?>"
       data-label="Farmers">
      <i class="fas fa-user-tie"></i>
      <span>Farmers</span>
    </a>

    <a href="<?= BASE_URL ?>/products/index.php"
       class="nav-item <?= str_starts_with($active, 'products') ? 'active' : '' ?>"
       data-label="Products">
      <i class="fas fa-box-open"></i>
      <span>Products</span>
    </a>

    <!-- Commerce -->
    <div class="nav-section-label">Commerce</div>

    <a href="<?= BASE_URL ?>/marketplace/index.php"
       class="nav-item <?= str_starts_with($active, 'marketplace') ? 'active' : '' ?>"
       data-label="Marketplace">
      <i class="fas fa-store"></i>
      <span>Marketplace</span>
    </a>

    <a href="<?= BASE_URL ?>/orders/index.php"
       class="nav-item <?= str_starts_with($active, 'orders') ? 'active' : '' ?>"
       data-label="Orders">
      <i class="fas fa-shopping-cart"></i>
      <span>Orders</span>
      <?php
      try {
        $pdo = getDB();
        if ($user['role'] === 'buyer') {
          $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending' AND buyer_id=?");
          $st->execute([$user['id']]);
          $pendingOrders = (int)$st->fetchColumn();
        } elseif ($user['role'] === 'farmer') {
          $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status='pending' AND seller_id=?");
          $st->execute([$user['id']]);
          $pendingOrders = (int)$st->fetchColumn();
        } else {
          $pendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
        }
        if ($pendingOrders > 0): ?>
        <span class="sidebar-badge"><?= $pendingOrders ?></span>
      <?php endif; } catch(Exception $e) {} ?>
    </a>

    <!-- Analytics -->
    <div class="nav-section-label">Analytics</div>

    <a href="<?= BASE_URL ?>/reports/index.php"
       class="nav-item <?= str_starts_with($active, 'reports') ? 'active' : '' ?>"
       data-label="Reports">
      <i class="fas fa-chart-bar"></i>
      <span>Reports</span>
    </a>

    <a href="<?= BASE_URL ?>/weather/index.php"
       class="nav-item <?= str_starts_with($active, 'weather') ? 'active' : '' ?>"
       data-label="Weather">
      <i class="fas fa-cloud-sun"></i>
      <span>Weather</span>
    </a>

    <?php if ($user['role'] === 'admin'): ?>
    <!-- Admin -->
    <div class="nav-section-label">Admin</div>

    <a href="<?= BASE_URL ?>/settings/users.php"
       class="nav-item <?= $active === 'users' ? 'active' : '' ?>"
       data-label="Users">
      <i class="fas fa-users"></i>
      <span>Users</span>
    </a>
    <?php endif; ?>

    <!-- Settings -->
    <div class="nav-section-label">Account</div>

    <a href="<?= BASE_URL ?>/settings/profile.php"
       class="nav-item <?= $active === 'profile' ? 'active' : '' ?>"
       data-label="Profile">
      <i class="fas fa-user-cog"></i>
      <span>Profile</span>
    </a>

    <a href="<?= BASE_URL ?>/settings/general.php"
       class="nav-item <?= $active === 'settings' ? 'active' : '' ?>"
       data-label="Settings">
      <i class="fas fa-cog"></i>
      <span>Settings</span>
    </a>

    <a href="<?= BASE_URL ?>/auth/logout.php"
       class="nav-item"
       data-label="Logout"
       onclick="return confirm('Are you sure you want to logout?')">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>

  </nav>

  <!-- User Footer -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <img src="<?= avatarImage($user['avatar']) ?>"
           width="36" height="36"
           alt="<?= htmlspecialchars($user['name']) ?>">
      <div class="user-info">
        <div class="name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="role"><?= htmlspecialchars(ucfirst($user['role'])) ?></div>
      </div>
    </div>
  </div>
</aside>
