<?php
// ============================================================
// AgriConnect – Header / Topbar Include
// Requires: $page_title, $breadcrumb (optional)
// ============================================================
$page_title = $page_title ?? 'Dashboard';
$breadcrumb = $breadcrumb ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="AgriConnect – Smart Agriculture Management System">
  <title><?= htmlspecialchars($page_title) ?> | AgriConnect</title>

  <!-- Font Awesome (self-hosted to avoid CDN icon flash) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/fontawesome.min.css">

  <!-- Global CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>

  <?php if (isset($extra_css)): ?>
    <?php foreach ((array)$extra_css as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
<div class="app-wrapper">

<?php include __DIR__ . '/sidebar.php'; ?>

<div id="mainContent" class="main-content">

  <!-- Topbar -->
  <header class="topbar">
    <button id="sidebarToggle" class="topbar-toggle">
      <i class="fas fa-bars"></i>
    </button>

    <!-- Breadcrumb -->
    <nav class="topbar-breadcrumb">
      <a href="<?= BASE_URL ?>/dashboard.php">Home</a>
      <?php foreach ($breadcrumb as $bc): ?>
        <i class="fas fa-chevron-right" style="font-size:10px;"></i>
        <?php if (!empty($bc['url'])): ?>
          <a href="<?= htmlspecialchars($bc['url']) ?>"><?= htmlspecialchars($bc['label']) ?></a>
        <?php else: ?>
          <span class="current"><?= htmlspecialchars($bc['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (empty($breadcrumb)): ?>
        <i class="fas fa-chevron-right" style="font-size:10px;"></i>
        <span class="current"><?= htmlspecialchars($page_title) ?></span>
      <?php endif; ?>
    </nav>

    <div class="topbar-spacer"></div>

    <!-- Search -->
    <div class="topbar-search">
      <i class="fas fa-search"></i>
      <input type="text" placeholder="Search…" id="globalSearch" autocomplete="off">
    </div>

    <div class="topbar-actions">
      <!-- Notifications -->
      <div class="dropdown-wrapper">
        <button class="topbar-btn" data-toggle="dropdown" data-target="notifDropdown" title="Notifications">
          <i class="fas fa-bell"></i>
          <span class="badge"></span>
        </button>
        <div class="dropdown-menu" id="notifDropdown" style="min-width:300px;">
          <div class="dropdown-header">
            <div class="d-name">Notifications</div>
            <div class="d-email" style="font-size:11px;">3 unread</div>
          </div>
          <a class="dropdown-item" href="<?= BASE_URL ?>/orders/index.php">
            <i class="fas fa-shopping-cart" style="color:var(--primary);"></i>
            <div>
              <div style="font-weight:600;">Recent order activity</div>
              <div style="font-size:11px;color:var(--gray-400);">View latest order updates</div>
            </div>
          </a>
          <a class="dropdown-item" href="<?= BASE_URL ?>/crops/index.php">
            <i class="fas fa-leaf" style="color:var(--success);"></i>
            <div>
              <div style="font-weight:600;">Crop harvest schedule</div>
              <div style="font-size:11px;color:var(--gray-400);">Check upcoming crop harvests</div>
            </div>
          </a>
          <a class="dropdown-item" href="<?= BASE_URL ?>/weather/index.php">
            <i class="fas fa-cloud-rain" style="color:var(--info);"></i>
            <div>
              <div style="font-weight:600;">Weather advisory alert</div>
              <div style="font-size:11px;color:var(--gray-400);">View local agro forecasts</div>
            </div>
          </a>
          <hr class="dropdown-divider">
          <a class="dropdown-item" href="<?= BASE_URL ?>/orders/index.php" style="justify-content:center;color:var(--primary);font-weight:600;">View all orders & alerts</a>
        </div>
      </div>

      <!-- User Dropdown -->
      <div class="dropdown-wrapper">
        <?php $user = currentUser(); ?>
        <img src="<?= avatarImage($user['avatar']) ?>"
             width="36" height="36"
             class="topbar-avatar"
             data-toggle="dropdown" data-target="userDropdown"
             alt="<?= htmlspecialchars($user['name']) ?>">
        <div class="dropdown-menu" id="userDropdown">
          <div class="dropdown-header">
            <div class="d-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="d-email"><?= htmlspecialchars($user['email']) ?></div>
          </div>
          <a class="dropdown-item" href="<?= BASE_URL ?>/settings/profile.php">
            <i class="fas fa-user"></i> My Profile
          </a>
          <a class="dropdown-item" href="<?= BASE_URL ?>/settings/general.php">
            <i class="fas fa-cog"></i> Settings
          </a>
          <hr class="dropdown-divider">
          <a class="dropdown-item danger"
             href="<?= BASE_URL ?>/auth/logout.php"
             onclick="return confirm('Are you sure you want to logout?')">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Flash Message -->
  <?php $flash = getFlash(); if ($flash['msg']): ?>
  <div class="page-content" style="padding-bottom:0;padding-top:16px;">
    <div class="alert alert-<?= $flash['type'] ?>" data-dismiss="true">
      <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
      <span><?= htmlspecialchars($flash['msg']) ?></span>
    </div>
  </div>
  <?php endif; ?>
