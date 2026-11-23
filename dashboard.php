<?php
require_once 'config/db.php';
requireLogin();

$active_page = 'dashboard';
$user        = currentUser();
$role        = $user['role'] ?? 'buyer';
$page_title  = ucfirst($role) . ' Dashboard';

$pdo = getDB();

// ── Shared: Weather Data ──
$weather = $pdo->query("SELECT * FROM weather_data WHERE forecast_date = CURDATE() ORDER BY id DESC LIMIT 1")->fetch();
if (!$weather) {
    $weather = $pdo->query("SELECT * FROM weather_data ORDER BY forecast_date DESC LIMIT 1")->fetch();
}

// ── Role-Specific Data Retrieval ──
if ($role === 'farmer') {
    // ── Farmer Profile ──
    $farmerStmt = $pdo->prepare("SELECT * FROM farmers WHERE user_id = ? LIMIT 1");
    $farmerStmt->execute([$user['id']]);
    $myFarmer   = $farmerStmt->fetch();
    $farmerId   = $myFarmer['id'] ?? 0;

    // ── Farmer Stats ──
    if ($farmerId > 0) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM crops WHERE farmer_id = ?");
        $st->execute([$farmerId]);
        $totalMyCrops = (int)$st->fetchColumn();

        $st = $pdo->prepare("SELECT COUNT(*) FROM products WHERE farmer_id = ?");
        $st->execute([$farmerId]);
        $totalMyProducts = (int)$st->fetchColumn();
    } else {
        $totalMyCrops = 0;
        $totalMyProducts = 0;
    }

    $st = $pdo->prepare("SELECT COUNT(*) FROM marketplace_listings WHERE seller_id = ? AND status = 'active'");
    $st->execute([$user['id']]);
    $myActiveListings = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE seller_id = ? AND payment_status = 'paid'");
    $st->execute([$user['id']]);
    $myRevenue = (float)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ?");
    $st->execute([$user['id']]);
    $myOrdersTotal = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status IN ('pending', 'processing', 'confirmed')");
    $st->execute([$user['id']]);
    $myPendingOrders = (int)$st->fetchColumn();

    // ── Recent Incoming Orders ──
    $ordersStmt = $pdo->prepare("
        SELECT o.*, u_b.name as buyer_name, ml.title as listing_title
        FROM orders o
        LEFT JOIN users u_b ON o.buyer_id = u_b.id
        LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
        WHERE o.seller_id = ?
        ORDER BY o.created_at DESC LIMIT 6
    ");
    $ordersStmt->execute([$user['id']]);
    $recentOrders = $ordersStmt->fetchAll();

    // ── Farmer's Crops Inventory ──
    if ($farmerId > 0) {
        $cropStmt = $pdo->prepare("
            SELECT c.*, cc.name as category_name
            FROM crops c
            LEFT JOIN crop_categories cc ON c.category_id = cc.id
            WHERE c.farmer_id = ?
            ORDER BY c.created_at DESC LIMIT 6
        ");
        $cropStmt->execute([$farmerId]);
        $myCrops = $cropStmt->fetchAll();
    } else {
        $myCrops = [];
    }

    // ── Monthly Sales Chart (Last 6 Months) ──
    $chartStmt = $pdo->prepare("
        SELECT DATE_FORMAT(MIN(created_at), '%b') as month,
               COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE seller_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ");
    $chartStmt->execute([$user['id']]);
    $chartMonthly = $chartStmt->fetchAll();

    // ── Crops by Category ──
    if ($farmerId > 0) {
        $distStmt = $pdo->prepare("
            SELECT cc.name, COUNT(c.id) as count
            FROM crop_categories cc
            JOIN crops c ON cc.id = c.category_id AND c.farmer_id = ?
            GROUP BY cc.id, cc.name ORDER BY count DESC LIMIT 6
        ");
        $distStmt->execute([$farmerId]);
        $cropsByCategory = $distStmt->fetchAll();
    } else {
        $cropsByCategory = [];
    }

} elseif ($role === 'buyer') {
    // ── Buyer Stats ──
    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $st->execute([$user['id']]);
    $buyerTotalOrders = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')");
    $st->execute([$user['id']]);
    $buyerActiveDeliveries = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'delivered'");
    $st->execute([$user['id']]);
    $buyerDeliveredOrders = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE buyer_id = ? AND payment_status = 'paid'");
    $st->execute([$user['id']]);
    $buyerTotalSpent = (float)$st->fetchColumn();

    $availListings = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE status = 'active'")->fetchColumn();
    $availCrops    = (int)$pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'available'")->fetchColumn();

    // ── Buyer Recent Orders ──
    $ordersStmt = $pdo->prepare("
        SELECT o.*, u_s.name as seller_name, ml.title as listing_title
        FROM orders o
        LEFT JOIN users u_s ON o.seller_id = u_s.id
        LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
        WHERE o.buyer_id = ?
        ORDER BY o.created_at DESC LIMIT 6
    ");
    $ordersStmt->execute([$user['id']]);
    $recentOrders = $ordersStmt->fetchAll();

    // ── Featured Marketplace Produce ──
    $featuredProduce = $pdo->query("
        SELECT ml.*, u.name as seller_name
        FROM marketplace_listings ml
        LEFT JOIN users u ON ml.seller_id = u.id
        WHERE ml.status = 'active'
        ORDER BY ml.listed_at DESC LIMIT 6
    ")->fetchAll();

    // ── Buyer Monthly Spending Chart ──
    $chartStmt = $pdo->prepare("
        SELECT DATE_FORMAT(MIN(created_at), '%b') as month,
               COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE buyer_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ");
    $chartStmt->execute([$user['id']]);
    $chartMonthly = $chartStmt->fetchAll();

    // ── Orders Status Breakdown for Doughnut Chart ──
    $distStmt = $pdo->prepare("
        SELECT status as name, COUNT(*) as count
        FROM orders
        WHERE buyer_id = ?
        GROUP BY status
    ");
    $distStmt->execute([$user['id']]);
    $cropsByCategory = $distStmt->fetchAll();

} else {
    // ── Admin Dashboard Data (Default / Platform Wide) ──
    $totalFarmers   = (int)$pdo->query("SELECT COUNT(*) FROM farmers WHERE status = 'active'")->fetchColumn();
    $totalCrops     = (int)$pdo->query("SELECT COUNT(*) FROM crops WHERE status = 'available'")->fetchColumn();
    $totalProducts  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    $totalRevenue   = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
    $pendingOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $activeListings = (int)$pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE status = 'active'")->fetchColumn();

    // ── Platform Recent Orders ──
    $recentOrders = $pdo->query("
        SELECT o.*, u_b.name as buyer_name, u_s.name as seller_name, ml.title as listing_title
        FROM orders o
        LEFT JOIN users u_b ON o.buyer_id  = u_b.id
        LEFT JOIN users u_s ON o.seller_id = u_s.id
        LEFT JOIN marketplace_listings ml ON o.listing_id = ml.id
        ORDER BY o.created_at DESC LIMIT 6
    ")->fetchAll();

    // ── Platform Recent Activity ──
    $activity = $pdo->query("
        SELECT al.*, u.name as user_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC LIMIT 6
    ")->fetchAll();

    // ── Platform Monthly Revenue Chart ──
    $chartMonthly = $pdo->query("
        SELECT DATE_FORMAT(MIN(created_at), '%b') as month,
               COALESCE(SUM(total_amount), 0) as amount
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ")->fetchAll();

    // ── Crop Category Distribution ──
    $cropsByCategory = $pdo->query("
        SELECT cc.name, COUNT(c.id) as count
        FROM crop_categories cc
        LEFT JOIN crops c ON cc.id = c.category_id
        GROUP BY cc.id, cc.name ORDER BY count DESC LIMIT 6
    ")->fetchAll();

    // ── Top Platform Crops ──
    $topCrops = $pdo->query("
        SELECT c.id, c.name, c.price, c.unit, c.quantity, c.status,
               f.farm_name, f.location
        FROM crops c
        LEFT JOIN farmers f ON c.farmer_id = f.id
        ORDER BY c.created_at DESC LIMIT 5
    ")->fetchAll();
}

include 'includes/header.php';
?>

<div class="page-content">

  <!-- ── Page Header ── -->
  <div class="page-header">
    <div>
      <div class="page-title"><?= ucfirst($role) ?> Dashboard</div>
      <div class="page-subtitle">
        <?php if ($role === 'farmer'): ?>
          Welcome back, <?= htmlspecialchars($user['name']) ?>! Manage your harvest, marketplace listings, and buyer orders.
        <?php elseif ($role === 'buyer'): ?>
          Welcome back, <?= htmlspecialchars($user['name']) ?>! Discover farm-fresh produce and track your active purchases.
        <?php else: ?>
          Welcome back, <?= htmlspecialchars($user['name']) ?>! Platform-wide operational overview and controls.
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-8 flex-wrap">
      <?php if ($role === 'farmer'): ?>
        <a href="crops/add.php" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-plus"></i> Add Crop
        </a>
        <a href="marketplace/add.php" class="btn btn-primary btn-sm">
          <i class="fas fa-store"></i> Create Listing
        </a>
        <a href="orders/index.php" class="btn btn-secondary btn-sm">
          <i class="fas fa-box"></i> My Orders
        </a>
      <?php elseif ($role === 'buyer'): ?>
        <a href="marketplace/index.php" class="btn btn-primary btn-sm">
          <i class="fas fa-shopping-basket"></i> Browse Marketplace
        </a>
        <a href="orders/index.php" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-truck"></i> Track Orders
        </a>
      <?php else: ?>
        <a href="crops/add.php" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-plus"></i> Add Crop
        </a>
        <a href="settings/users.php" class="btn btn-secondary btn-sm">
          <i class="fas fa-user-plus"></i> Manage Users
        </a>
        <a href="reports/index.php" class="btn btn-primary btn-sm">
          <i class="fas fa-chart-bar"></i> Reports
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── KPI Stats Grid ── -->
  <div class="stats-grid">
    <?php if ($role === 'farmer'): ?>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-leaf"></i></div>
        <div class="stat-content">
          <div class="label">My Crops</div>
          <div class="value"><?= number_format($totalMyCrops) ?></div>
          <div class="change up"><i class="fas fa-check"></i> In harvest cycle</div>
        </div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-store"></i></div>
        <div class="stat-content">
          <div class="label">Active Listings</div>
          <div class="value"><?= number_format($myActiveListings) ?></div>
          <div class="change up"><i class="fas fa-globe"></i> On marketplace</div>
        </div>
      </div>
      <div class="stat-card amber">
        <div class="stat-icon amber"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-content">
          <div class="label">Sales Revenue</div>
          <div class="value"><?= '₹' . number_format($myRevenue) ?></div>
          <div class="change up"><i class="fas fa-arrow-up"></i> Paid orders</div>
        </div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-content">
          <div class="label">Total Orders</div>
          <div class="value"><?= number_format($myOrdersTotal) ?></div>
          <div class="change up"><i class="fas fa-users"></i> Lifetime buyers</div>
        </div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon red"><i class="fas fa-truck"></i></div>
        <div class="stat-content">
          <div class="label">Pending Shipments</div>
          <div class="value"><?= number_format($myPendingOrders) ?></div>
          <div class="change down"><i class="fas fa-clock"></i> Requires dispatch</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-box-open"></i></div>
        <div class="stat-content">
          <div class="label">Farm Products</div>
          <div class="value"><?= number_format($totalMyProducts) ?></div>
          <div class="change up"><i class="fas fa-seedling"></i> Farm inventory</div>
        </div>
      </div>

    <?php elseif ($role === 'buyer'): ?>
      <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-shopping-bag"></i></div>
        <div class="stat-content">
          <div class="label">Orders Placed</div>
          <div class="value"><?= number_format($buyerTotalOrders) ?></div>
          <div class="change up"><i class="fas fa-history"></i> Total purchases</div>
        </div>
      </div>
      <div class="stat-card amber">
        <div class="stat-icon amber"><i class="fas fa-shipping-fast"></i></div>
        <div class="stat-content">
          <div class="label">Active Deliveries</div>
          <div class="value"><?= number_format($buyerActiveDeliveries) ?></div>
          <div class="change up"><i class="fas fa-truck"></i> On the way</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
          <div class="label">Completed Orders</div>
          <div class="value"><?= number_format($buyerDeliveredOrders) ?></div>
          <div class="change up"><i class="fas fa-box"></i> Delivered safely</div>
        </div>
      </div>
      <div class="stat-card amber">
        <div class="stat-icon amber"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-content">
          <div class="label">Total Spent</div>
          <div class="value"><?= '₹' . number_format($buyerTotalSpent) ?></div>
          <div class="change up"><i class="fas fa-receipt"></i> Verified payments</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-store"></i></div>
        <div class="stat-content">
          <div class="label">Active Listings</div>
          <div class="value"><?= number_format($availListings) ?></div>
          <div class="change up"><i class="fas fa-tag"></i> Ready to order</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-seedling"></i></div>
        <div class="stat-content">
          <div class="label">Fresh Farm Crops</div>
          <div class="value"><?= number_format($availCrops) ?></div>
          <div class="change up"><i class="fas fa-leaf"></i> Direct from farms</div>
        </div>
      </div>

    <?php else: ?>
      <!-- Admin Stats -->
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-user-tie"></i></div>
        <div class="stat-content">
          <div class="label">Active Farmers</div>
          <div class="value"><?= number_format($totalFarmers) ?></div>
          <div class="change up"><i class="fas fa-arrow-up"></i> Verified growers</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-leaf"></i></div>
        <div class="stat-content">
          <div class="label">Crops Listed</div>
          <div class="value"><?= number_format($totalCrops) ?></div>
          <div class="change up"><i class="fas fa-arrow-up"></i> In database</div>
        </div>
      </div>
      <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-store"></i></div>
        <div class="stat-content">
          <div class="label">Active Listings</div>
          <div class="value"><?= number_format($activeListings) ?></div>
          <div class="change up"><i class="fas fa-shopping-basket"></i> In marketplace</div>
        </div>
      </div>
      <div class="stat-card amber">
        <div class="stat-icon amber"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-content">
          <div class="label">Platform Revenue</div>
          <div class="value"><?= '₹' . number_format($totalRevenue / 1000, 1) ?>K</div>
          <div class="change up"><i class="fas fa-arrow-up"></i> Total gross volume</div>
        </div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon red"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-content">
          <div class="label">Pending Orders</div>
          <div class="value"><?= number_format($pendingOrders) ?></div>
          <div class="change down"><i class="fas fa-clock"></i> Needs fulfillment</div>
        </div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-box-open"></i></div>
        <div class="stat-content">
          <div class="label">Products</div>
          <div class="value"><?= number_format($totalProducts) ?></div>
          <div class="change up"><i class="fas fa-check"></i> Active catalog</div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Weather & Agro Advisory Banner ── -->
  <?php if ($weather): ?>
  <div class="card mb-24" style="background:linear-gradient(135deg,var(--secondary),var(--primary-dark));border:none;color:white;box-shadow:var(--shadow-md);">
    <div class="card-body" style="padding:20px 24px;">
      <div class="d-flex align-center justify-between gap-16" style="flex-wrap:wrap;">
        <div class="d-flex align-center gap-16">
          <div style="font-size:46px;">
            <?php
            $cond = strtolower($weather['condition'] ?? '');
            if (str_contains($cond,'rain')) echo '🌧️';
            elseif (str_contains($cond,'cloud')) echo '⛅';
            elseif (str_contains($cond,'sun') || str_contains($cond,'clear')) echo '☀️';
            else echo '🌤️';
            ?>
          </div>
          <div>
            <div style="font-size:28px;font-weight:800;line-height:1.1;"><?= $weather['temperature'] ?>°C</div>
            <div style="opacity:.85;font-size:13px;"><?= htmlspecialchars($weather['condition']) ?> · <?= htmlspecialchars($weather['city']) ?></div>
            <?php if ($role === 'farmer'): ?>
              <div style="margin-top:4px;font-size:12px;background:rgba(255,255,255,.18);padding:2px 8px;border-radius:12px;display:inline-block;">
                <i class="fas fa-seedling"></i> Advisory: Optimal irrigation conditions today
              </div>
            <?php elseif ($role === 'buyer'): ?>
              <div style="margin-top:4px;font-size:12px;background:rgba(255,255,255,.18);padding:2px 8px;border-radius:12px;display:inline-block;">
                <i class="fas fa-truck"></i> Farm dispatch operations running smoothly
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="d-flex gap-20" style="flex-wrap:wrap;">
          <div style="text-align:center;"><div style="font-size:11px;opacity:.7;">Humidity</div><div style="font-weight:700;font-size:15px;"><?= $weather['humidity'] ?>%</div></div>
          <div style="text-align:center;"><div style="font-size:11px;opacity:.7;">Wind</div><div style="font-weight:700;font-size:15px;"><?= $weather['wind_speed'] ?> km/h</div></div>
          <div style="text-align:center;"><div style="font-size:11px;opacity:.7;">UV Index</div><div style="font-weight:700;font-size:15px;"><?= $weather['uv_index'] ?></div></div>
          <div style="text-align:center;"><div style="font-size:11px;opacity:.7;">Visibility</div><div style="font-weight:700;font-size:15px;"><?= $weather['visibility'] ?> km</div></div>
        </div>
        <a href="weather/index.php" class="btn btn-sm" style="background:rgba(255,255,255,.18);color:white;border:1px solid rgba(255,255,255,.35);font-weight:600;">
          <i class="fas fa-cloud-sun"></i> Weather Forecast
        </a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Charts Section ── -->
  <div class="grid-2 mb-24">
    <!-- Chart 1: Financial / Monthly Trend -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <i class="fas fa-chart-line" style="color:var(--primary);margin-right:8px;"></i>
          <?php if ($role === 'farmer'): ?>
            My Monthly Sales (Last 6 Months)
          <?php elseif ($role === 'buyer'): ?>
            My Monthly Purchases (Last 6 Months)
          <?php else: ?>
            Platform Revenue (Last 6 Months)
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="height:260px;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Chart 2: Category / Status Breakdown -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <i class="fas fa-chart-pie" style="color:var(--primary);margin-right:8px;"></i>
          <?php if ($role === 'farmer'): ?>
            My Crops Distribution
          <?php elseif ($role === 'buyer'): ?>
            My Orders by Status
          <?php else: ?>
            Crops by Category
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-container" style="height:260px;">
          <canvas id="cropCategoryChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Mid Row: Recent Orders & Context Actions ── -->
  <div class="grid-2 mb-24">
    <!-- Recent Orders Table -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <i class="fas fa-shopping-cart" style="color:var(--primary);margin-right:8px;"></i>
          <?php if ($role === 'farmer'): ?>
            Recent Orders Received
          <?php elseif ($role === 'buyer'): ?>
            My Recent Orders
          <?php else: ?>
            Recent Platform Orders
          <?php endif; ?>
        </div>
        <a href="orders/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Order #</th>
              <th><?= ($role === 'buyer') ? 'Seller' : 'Buyer' ?></th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentOrders)): ?>
              <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No recent orders found.</td></tr>
            <?php else: ?>
              <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td>
                  <a href="orders/view.php?id=<?= $order['id'] ?>" style="font-weight:700;color:var(--primary);">
                    <?= htmlspecialchars($order['order_number']) ?>
                  </a>
                  <br><span class="text-muted text-sm"><?= htmlspecialchars($order['listing_title'] ?? 'N/A') ?></span>
                </td>
                <td><?= htmlspecialchars(($role === 'buyer' ? ($order['seller_name'] ?? 'Farmer') : ($order['buyer_name'] ?? 'Buyer'))) ?></td>
                <td><strong><?= formatCurrency($order['total_amount']) ?></strong></td>
                <td>
                  <?php
                  $statusBadge = [
                    'pending'    => 'badge-warning',
                    'confirmed'  => 'badge-info',
                    'processing' => 'badge-info',
                    'shipped'    => 'badge-primary',
                    'delivered'  => 'badge-success',
                    'cancelled'  => 'badge-danger',
                  ];
                  $cls = $statusBadge[$order['status']] ?? 'badge-secondary';
                  ?>
                  <span class="badge <?= $cls ?>"><?= ucfirst($order['status']) ?></span>
                </td>
                <td>
                  <a href="orders/view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary" title="View Order">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Role-Tailored Right Panel -->
    <?php if ($role === 'farmer'): ?>
      <!-- Farmer Quick Actions & Farm Overview -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-tractor" style="color:var(--primary);margin-right:8px;"></i> Farm Management Quick Tools</div>
        </div>
        <div class="card-body" style="padding:16px 20px;">
          <div class="d-flex flex-column gap-12">
            <a href="crops/add.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:var(--primary-pale);color:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-seedling"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Add New Harvest / Crop</div>
                <div style="font-size:12px;color:var(--gray-500);">Record fresh crop yield, quantity, and seasonal price.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>

            <a href="marketplace/add.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:#dbeafe;color:var(--info);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-tag"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Post Marketplace Listing</div>
                <div style="font-size:12px;color:var(--gray-500);">Sell your produce directly to wholesale and retail buyers.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>

            <a href="weather/index.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:#fef3c7;color:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-cloud-sun-rain"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">7-Day Agro Weather Forecast</div>
                <div style="font-size:12px;color:var(--gray-500);">Monitor regional rainfall, humidity, and temperature.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>

            <a href="reports/index.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:#f3e8ff;color:#9333ea;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-chart-line"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Revenue & Performance Analytics</div>
                <div style="font-size:12px;color:var(--gray-500);">Review order trends, sales totals, and printable statements.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>
          </div>
        </div>
      </div>

    <?php elseif ($role === 'buyer'): ?>
      <!-- Buyer Services & Information -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-shield-alt" style="color:var(--primary);margin-right:8px;"></i> Buyer Direct Farm Network</div>
        </div>
        <div class="card-body" style="padding:16px 20px;">
          <div class="d-flex flex-column gap-12">
            <a href="marketplace/index.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:var(--primary-pale);color:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-shopping-basket"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Browse Marketplace</div>
                <div style="font-size:12px;color:var(--gray-500);">Explore fresh grains, fruits, vegetables, and seeds.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>

            <a href="orders/index.php" class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);text-decoration:none;">
              <div style="width:40px;height:40px;background:#dbeafe;color:var(--info);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-truck-loading"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Order Tracking & Invoices</div>
                <div style="font-size:12px;color:var(--gray-500);">View live order stages, delivery progress, and printable bills.</div>
              </div>
              <i class="fas fa-chevron-right text-muted"></i>
            </a>

            <div class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);">
              <div style="width:40px;height:40px;background:#dcfce7;color:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-award"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Direct Farm Sourcing</div>
                <div style="font-size:12px;color:var(--gray-500);">100% verified local farmers without middleman commissions.</div>
              </div>
            </div>

            <div class="card d-flex align-center gap-16" style="padding:14px;border:1px solid var(--gray-200);">
              <div style="width:40px;height:40px;background:#fef3c7;color:var(--accent);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;">
                <i class="fas fa-lock"></i>
              </div>
              <div style="flex:1;">
                <div style="font-weight:700;color:var(--gray-900);">Secure AgriConnect Transactions</div>
                <div style="font-size:12px;color:var(--gray-500);">Cash on Delivery and secure digital bank settlements.</div>
              </div>
            </div>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Admin Activity Feed -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-history" style="color:var(--primary);margin-right:8px;"></i> System Activity Log</div>
        </div>
        <div class="card-body" style="padding:12px 20px;">
          <?php
          $actionIcons = [
            'login'           => ['fas fa-sign-in-alt', 'green'],
            'logout'          => ['fas fa-sign-out-alt','gray'],
            'crop_added'      => ['fas fa-leaf',         'green'],
            'product_updated' => ['fas fa-box-open',     'blue'],
            'listing_created' => ['fas fa-store',        'amber'],
            'order_placed'    => ['fas fa-shopping-cart','blue'],
            'user_updated'    => ['fas fa-user',         'green'],
            'registered'      => ['fas fa-user-plus',    'green'],
          ];
          if (empty($activity)): ?>
            <div class="text-center text-muted" style="padding:24px;">No recent system logs.</div>
          <?php else:
            foreach ($activity as $log):
              [$icon, $color] = $actionIcons[$log['action']] ?? ['fas fa-info', 'gray'];
              $bg = $color === 'green' ? 'var(--primary-pale)' : ($color === 'blue' ? '#dbeafe' : ($color === 'amber' ? '#fef3c7' : 'var(--gray-100)'));
              $fg = $color === 'green' ? 'var(--primary)' : ($color === 'blue' ? 'var(--info)' : ($color === 'amber' ? 'var(--accent)' : 'var(--gray-500)'));
          ?>
          <div class="activity-item">
            <div class="activity-dot" style="background:<?= $bg ?>;color:<?= $fg ?>;">
              <i class="<?= $icon ?>"></i>
            </div>
            <div class="activity-content">
              <div class="title"><?= htmlspecialchars($log['description']) ?></div>
              <div class="time"><?= htmlspecialchars($log['user_name'] ?? 'System') ?> · <?= timeAgo($log['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ── Bottom Section: Tailored Tables ── -->
  <?php if ($role === 'farmer'): ?>
    <!-- Farmer's Crop Inventory Table -->
    <div class="card mb-24">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i> My Crop Inventory</div>
        <div class="d-flex gap-8">
          <a href="crops/add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Crop</a>
          <a href="crops/index.php" class="btn btn-sm btn-outline-primary">Manage Crops</a>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Crop Name</th>
              <th>Category</th>
              <th>Variety</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($myCrops)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No crops listed under your farm profile yet. Click "Add Crop" to get started!</td></tr>
            <?php else: ?>
              <?php foreach ($myCrops as $crop): ?>
              <tr>
                <td><strong><?= htmlspecialchars($crop['name']) ?></strong></td>
                <td><?= htmlspecialchars($crop['category_name'] ?? 'General') ?></td>
                <td><?= htmlspecialchars($crop['variety'] ?: 'Standard') ?></td>
                <td><strong><?= formatCurrency($crop['price']) ?></strong>/<?= htmlspecialchars($crop['unit']) ?></td>
                <td><?= number_format($crop['quantity']) ?> <?= htmlspecialchars($crop['unit']) ?></td>
                <td>
                  <?php $cls = ['available'=>'badge-success','sold_out'=>'badge-danger','coming_soon'=>'badge-warning','inactive'=>'badge-secondary'][$crop['status']] ?? 'badge-secondary'; ?>
                  <span class="badge <?= $cls ?>"><?= str_replace('_',' ',ucfirst($crop['status'])) ?></span>
                </td>
                <td>
                  <div class="d-flex gap-4">
                    <a href="crops/view.php?id=<?= $crop['id'] ?>" class="btn btn-sm btn-secondary" title="View"><i class="fas fa-eye"></i></a>
                    <a href="crops/edit.php?id=<?= $crop['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                    <a href="marketplace/add.php?type=crop&item_id=<?= $crop['id'] ?>" class="btn btn-sm btn-primary" title="List on Marketplace"><i class="fas fa-store"></i></a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php elseif ($role === 'buyer'): ?>
    <!-- Buyer: Featured Marketplace Produce -->
    <div class="card mb-24">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-store" style="color:var(--primary);margin-right:8px;"></i> Farm-Fresh Produce Available Now</div>
        <a href="marketplace/index.php" class="btn btn-sm btn-outline-primary">Browse All Listings</a>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Produce</th>
              <th>Farmer</th>
              <th>Location</th>
              <th>Price</th>
              <th>Available Qty</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($featuredProduce)): ?>
              <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No active listings currently available. Check back soon!</td></tr>
            <?php else: ?>
              <?php foreach ($featuredProduce as $listing): ?>
              <tr>
                <td><strong><?= htmlspecialchars($listing['title']) ?></strong></td>
                <td><?= htmlspecialchars($listing['seller_name'] ?? 'Farmer') ?></td>
                <td><i class="fas fa-map-marker-alt text-muted"></i> <?= htmlspecialchars($listing['location'] ?: 'Farm Direct') ?></td>
                <td><strong style="color:var(--primary);"><?= formatCurrency($listing['price']) ?></strong>/<?= htmlspecialchars($listing['unit']) ?></td>
                <td><?= number_format($listing['quantity']) ?> <?= htmlspecialchars($listing['unit']) ?></td>
                <td>
                  <a href="marketplace/view.php?id=<?= $listing['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-shopping-cart"></i> Buy Now
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php else: ?>
    <!-- Admin: Top Platform Crops Table -->
    <div class="card mb-24">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i> Latest Platform Crops</div>
        <div class="d-flex gap-8">
          <a href="crops/add.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Crop</a>
          <a href="crops/index.php" class="btn btn-sm btn-outline-primary">Manage Crops</a>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Crop Name</th>
              <th>Farm</th>
              <th>Location</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topCrops)): ?>
              <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No crops found in system.</td></tr>
            <?php else: ?>
              <?php foreach ($topCrops as $crop): ?>
              <tr>
                <td><strong><?= htmlspecialchars($crop['name']) ?></strong></td>
                <td><?= htmlspecialchars($crop['farm_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($crop['location'] ?? 'N/A') ?></td>
                <td><strong><?= formatCurrency($crop['price']) ?></strong>/<?= htmlspecialchars($crop['unit']) ?></td>
                <td><?= number_format($crop['quantity']) ?> <?= htmlspecialchars($crop['unit']) ?></td>
                <td>
                  <?php $cls = ['available'=>'badge-success','sold_out'=>'badge-danger','coming_soon'=>'badge-warning','inactive'=>'badge-secondary'][$crop['status']] ?? 'badge-secondary'; ?>
                  <span class="badge <?= $cls ?>"><?= str_replace('_',' ',ucfirst($crop['status'])) ?></span>
                </td>
                <td>
                  <a href="crops/view.php?id=<?= $crop['id'] ?>" class="btn btn-sm btn-secondary" title="View Crop">
                    <i class="fas fa-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div><!-- /.page-content -->

<?php
// Build chart data
$chartLabels = json_encode(array_column($chartMonthly, 'month') ?: ['Jun','Jul','Aug','Sep','Oct','Nov']);
$chartData   = json_encode(array_column($chartMonthly, 'amount') ?: [45000, 68000, 52000, 94000, 81000, 115000]);

$catLabelsArr = !empty($cropsByCategory) ? array_column($cropsByCategory, 'name') : ['Grains', 'Vegetables', 'Fruits', 'Pulses'];
$catDataArr   = !empty($cropsByCategory) ? array_column($cropsByCategory, 'count') : [12, 18, 9, 7];

$catLabels = json_encode($catLabelsArr);
$catData   = json_encode($catDataArr);

$datasetLabel = ($role === 'farmer') ? 'My Sales (₹)' : (($role === 'buyer') ? 'My Purchases (₹)' : 'Platform Revenue (₹)');

$inline_js = <<<JS
// Financial trend chart
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: {$chartLabels},
    datasets: [{
      label: '{$datasetLabel}',
      data: {$chartData},
      borderColor: '#16a34a',
      backgroundColor: 'rgba(22,163,74,.1)',
      borderWidth: 2.5,
      pointBackgroundColor: '#16a34a',
      pointRadius: 4,
      fill: true,
      tension: 0.4,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ' {$datasetLabel}: ₹' + Number(ctx.raw).toLocaleString('en-IN')
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#f3f4f6' },
        ticks: {
          callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0) + 'K' : v)
        }
      },
      x: { grid: { display: false } }
    }
  }
});

// Category/Status doughnut chart
new Chart(document.getElementById('cropCategoryChart'), {
  type: 'doughnut',
  data: {
    labels: {$catLabels},
    datasets: [{
      data: {$catData},
      backgroundColor: ['#16a34a', '#22c55e', '#3b82f6', '#f59e0b', '#86efac', '#dcfce7'],
      borderWidth: 2,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right',
        labels: { boxWidth: 12, font: { size: 12 } }
      }
    }
  }
});
JS;

include 'includes/footer.php';
?>
