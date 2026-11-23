<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'reports';
$page_title  = 'Reports & Farm Analytics';
$breadcrumb  = [['label' => 'Reports']];

$pdo  = getDB();
$user = currentUser();

// Time range filter
$range = trim($_GET['range'] ?? 'all');
$dateCondition = "1=1";
if ($range === 'month') {
    $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} elseif ($range === 'quarter') {
    $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
} elseif ($range === 'year') {
    $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
}

// ── Financial & Order Metrics ──
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid' AND $dateCondition")->fetchColumn();
$totalOrders  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE $dateCondition")->fetchColumn();
$avgOrderVal  = $totalOrders > 0 ? ($totalRevenue / max(1, $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid' AND $dateCondition")->fetchColumn())) : 0;
$pendingPay   = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'pending' AND $dateCondition")->fetchColumn();

// ── Order Status Breakdown ──
$orderStatuses = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM orders 
    WHERE $dateCondition 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

// ── Monthly Revenue (Last 6 Months) ──
$monthlyData = $pdo->query("
    SELECT DATE_FORMAT(MIN(created_at), '%b %Y') as month_label,
           COALESCE(SUM(total_amount), 0) as revenue,
           COUNT(*) as order_count
    FROM orders
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC
")->fetchAll();

$maxMonthlyRev = 1;
foreach ($monthlyData as $md) {
    if ($md['revenue'] > $maxMonthlyRev) $maxMonthlyRev = $md['revenue'];
}

// ── Crop Category Distribution ──
$cropCategories = $pdo->query("
    SELECT cc.name, COUNT(c.id) as crop_count, COALESCE(SUM(c.quantity), 0) as total_qty
    FROM crop_categories cc
    LEFT JOIN crops c ON cc.id = c.category_id
    GROUP BY cc.id, cc.name
    ORDER BY crop_count DESC
")->fetchAll();

$totalCropsCount = (int)$pdo->query("SELECT COUNT(*) FROM crops")->fetchColumn();

// ── Crops by Season ──
$seasons = $pdo->query("
    SELECT season, COUNT(*) as count, COALESCE(SUM(quantity), 0) as qty
    FROM crops
    GROUP BY season
    ORDER BY count DESC
")->fetchAll();

// ── Farmer Performance Leaderboard ──
$topFarmers = $pdo->query("
    SELECT f.id, f.farm_name, f.location, u.name as farmer_name,
           (SELECT COUNT(*) FROM crops WHERE farmer_id = f.id) as crop_count,
           COUNT(o.id) as fulfilled_orders,
           COALESCE(SUM(o.total_amount), 0) as total_sales
    FROM farmers f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN orders o ON o.seller_id = u.id AND o.payment_status = 'paid'
    GROUP BY f.id, f.farm_name, f.location, u.name
    ORDER BY total_sales DESC, fulfilled_orders DESC
    LIMIT 8
")->fetchAll();

// ── Top Performing Marketplace Items ──
$topItems = $pdo->query("
    SELECT ml.id, ml.title, ml.price, ml.unit, ml.type,
           COUNT(o.id) as order_count,
           COALESCE(SUM(o.quantity), 0) as total_sold_qty,
           COALESCE(SUM(o.total_amount), 0) as gross_sales
    FROM marketplace_listings ml
    JOIN orders o ON o.listing_id = ml.id
    WHERE o.status != 'cancelled'
    GROUP BY ml.id, ml.title, ml.price, ml.unit, ml.type
    ORDER BY gross_sales DESC
    LIMIT 6
")->fetchAll();

include '../includes/header.php';
?>

<style>
/* CSS Charts */
.chart-bar-group {
    display: flex;
    align-items: flex-end;
    gap: 16px;
    height: 180px;
    padding: 10px 0 0;
    border-bottom: 2px solid var(--gray-200);
}
.chart-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.chart-bar {
    width: 100%;
    max-width: 44px;
    background: var(--primary);
    border-radius: 6px 6px 0 0;
    transition: height 0.3s ease, background 0.2s;
    position: relative;
}
.chart-bar:hover {
    background: var(--primary-dark);
}
.chart-label {
    font-size: 11px;
    color: var(--gray-500);
    margin-top: 8px;
    white-space: nowrap;
}
.progress-container {
    background: var(--gray-100);
    border-radius: 999px;
    height: 8px;
    overflow: hidden;
    margin: 6px 0 12px;
}
.progress-fill {
    height: 100%;
    border-radius: 999px;
}
@media print {
    .sidebar, .topbar, .btn, .filter-bar, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

<div class="page-content">
  <!-- Header -->
  <div class="page-header">
    <div>
      <div class="page-title">Executive Reports & Statistics</div>
      <div class="page-subtitle">Commercial analytics, agricultural yields & order tracking</div>
    </div>
    <div class="d-flex gap-8 no-print">
      <button onclick="window.print()" class="btn btn-secondary">
        <i class="fas fa-print"></i> Print Report
      </button>
      <a href="../dashboard.php" class="btn btn-outline-primary">
        <i class="fas fa-th-large"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Time Range Selector -->
  <form method="GET" class="filter-bar mb-24 no-print">
    <div class="d-flex align-center gap-12">
      <span style="font-weight:600;font-size:13px;color:var(--gray-700);"><i class="fas fa-calendar-day" style="color:var(--primary);margin-right:6px;"></i>Reporting Window:</span>
      <select name="range" class="form-control" style="width:180px;" onchange="this.form.submit()">
        <option value="all" <?= $range === 'all' ? 'selected' : '' ?>>All Time Recorded</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
        <option value="quarter" <?= $range === 'quarter' ? 'selected' : '' ?>>Last 3 Months</option>
        <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>Last 12 Months</option>
      </select>
    </div>
  </form>

  <!-- Metric Summary Cards -->
  <div class="stats-grid mb-24">
    <div class="stat-card green">
      <div class="stat-icon green"><i class="fas fa-rupee-sign"></i></div>
      <div class="stat-content">
        <div class="label">Gross Settled Revenue</div>
        <div class="value"><?= formatCurrency($totalRevenue) ?></div>
        <div class="change up"><i class="fas fa-check"></i> From completed orders</div>
      </div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon blue"><i class="fas fa-shopping-bag"></i></div>
      <div class="stat-content">
        <div class="label">Total Orders Placed</div>
        <div class="value"><?= number_format($totalOrders) ?></div>
        <div class="change up"><i class="fas fa-arrow-up"></i> In selected timeframe</div>
      </div>
    </div>
    <div class="stat-card amber">
      <div class="stat-icon amber"><i class="fas fa-calculator"></i></div>
      <div class="stat-content">
        <div class="label">Avg Order Value</div>
        <div class="value"><?= formatCurrency($avgOrderVal) ?></div>
        <div class="change up"><i class="fas fa-chart-line"></i> Per paid transaction</div>
      </div>
    </div>
    <div class="stat-card amber">
      <div class="stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
      <div class="stat-content">
        <div class="label">Pending Payments</div>
        <div class="value"><?= formatCurrency($pendingPay) ?></div>
        <div class="change down"><i class="fas fa-clock"></i> Awaiting settlement</div>
      </div>
    </div>
  </div>

  <!-- Monthly Revenue Chart & Order Status Grid -->
  <div class="grid-2 mb-24">
    
    <!-- Monthly Revenue Bar Visual -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary);margin-right:8px;"></i>Revenue Trajectory (Last 6 Months)</div>
      </div>
      <div class="card-body">
        <?php if (empty($monthlyData)): ?>
        <div class="empty-state" style="padding:32px;">
          <i class="fas fa-chart-line" style="font-size:36px;color:var(--gray-300);margin-bottom:8px;"></i>
          <p class="text-muted">No completed order payments found in this timeframe.</p>
        </div>
        <?php else: ?>
        <div class="chart-bar-group">
          <?php foreach ($monthlyData as $row): 
              $pct = min(100, max(12, round(($row['revenue'] / $maxMonthlyRev) * 100)));
          ?>
          <div class="chart-col">
            <div style="font-size:10px;font-weight:700;color:var(--primary-dark);margin-bottom:4px;">
              ₹<?= number_format($row['revenue'] / 1000, 1) ?>K
            </div>
            <div class="chart-bar" style="height: <?= $pct ?>%;" title="<?= htmlspecialchars($row['month_label']) ?>: <?= formatCurrency($row['revenue']) ?> (<?= $row['order_count'] ?> orders)"></div>
            <div class="chart-label"><?= htmlspecialchars($row['month_label']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="d-flex justify-between text-sm text-muted" style="margin-top:14px;">
          <span>Bars scale relative to maximum monthly earnings</span>
          <strong>Total Trailing: <?= formatCurrency(array_sum(array_column($monthlyData, 'revenue'))) ?></strong>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Order Fulfillment Pipeline -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-tasks" style="color:var(--primary);margin-right:8px;"></i>Order Fulfillment Breakdown</div>
      </div>
      <div class="card-body">
        <?php 
        $pipeline = [
            'delivered'  => ['label' => 'Delivered (Complete)', 'color' => 'var(--primary)'],
            'shipped'    => ['label' => 'Shipped (In Transit)', 'color' => 'var(--info)'],
            'processing' => ['label' => 'Processing / Packed', 'color' => 'var(--primary-light)'],
            'confirmed'  => ['label' => 'Confirmed by Farmer', 'color' => 'var(--accent)'],
            'pending'    => ['label' => 'Pending Confirmation', 'color' => 'var(--warning)'],
            'cancelled'  => ['label' => 'Cancelled / Returned', 'color' => 'var(--danger)'],
        ];

        foreach ($pipeline as $statusKey => $meta):
            $count = $orderStatuses[$statusKey] ?? 0;
            $percent = $totalOrders > 0 ? round(($count / $totalOrders) * 100) : 0;
        ?>
        <div>
          <div class="d-flex justify-between align-center" style="font-size:13px;font-weight:600;">
            <span><?= $meta['label'] ?></span>
            <span><?= $count ?> orders (<?= $percent ?>%)</span>
          </div>
          <div class="progress-container">
            <div class="progress-fill" style="width: <?= $percent ?>%; background: <?= $meta['color'] ?>;"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Crop Production & Seasonal Distribution Grid -->
  <div class="grid-2 mb-24">
    <!-- Categories Breakdown -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-leaf" style="color:var(--primary);margin-right:8px;"></i>Crop Category Distribution</div>
      </div>
      <div class="card-body">
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Crops Listed</th>
                <th>Total Volume</th>
                <th>Share</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cropCategories as $cat): 
                  $share = $totalCropsCount > 0 ? round(($cat['crop_count'] / $totalCropsCount) * 100) : 0;
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                <td><?= $cat['crop_count'] ?> varieties</td>
                <td><?= number_format($cat['total_qty']) ?> kg</td>
                <td>
                  <div class="d-flex align-center gap-8">
                    <div class="progress-container" style="flex:1;margin:0;">
                      <div class="progress-fill" style="width:<?= $share ?>%;background:var(--primary);"></div>
                    </div>
                    <span style="font-size:12px;font-weight:600;min-width:32px;"><?= $share ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Seasonal Analysis -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-cloud-sun-rain" style="color:var(--primary);margin-right:8px;"></i>Seasonal Agricultural Production</div>
      </div>
      <div class="card-body">
        <div class="table-wrapper">
          <table class="table">
            <thead>
              <tr>
                <th>Harvest Season</th>
                <th>Crops</th>
                <th>Yield Volume</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($seasons as $s): ?>
              <tr>
                <td><span class="badge badge-primary"><?= htmlspecialchars($s['season']) ?></span></td>
                <td><strong><?= $s['count'] ?></strong> crop types</td>
                <td><?= number_format($s['qty']) ?> units</td>
                <td><span class="badge badge-success">In Production</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Farmer Performance Leaderboard -->
  <div class="card mb-24">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-award" style="color:var(--warning);margin-right:8px;"></i>Top Performing Farmers Leaderboard</div>
      <a href="../farmers/index.php" class="text-sm" style="color:var(--primary);font-weight:600;">View All Farmers →</a>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th style="width:60px;">Rank</th>
            <th>Farm & Farmer</th>
            <th>Location</th>
            <th>Crops Cataloged</th>
            <th>Orders Fulfilled</th>
            <th>Gross Sales Value</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          if (empty($topFarmers)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No farmer transaction data yet.</td></tr>
          <?php 
          else: 
            $rank = 1;
            foreach ($topFarmers as $tf): 
          ?>
          <tr>
            <td>
              <?php if ($rank === 1): ?>
                <span class="badge" style="background:#fef08a;color:#854d0e;font-weight:800;">🥇 #1</span>
              <?php elseif ($rank === 2): ?>
                <span class="badge" style="background:#e2e8f0;color:#334155;font-weight:800;">🥈 #2</span>
              <?php elseif ($rank === 3): ?>
                <span class="badge" style="background:#fed7aa;color:#9a3412;font-weight:800;">🥉 #3</span>
              <?php else: ?>
                <span style="font-weight:600;color:var(--gray-500);padding-left:8px;">#<?= $rank ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div style="font-weight:700;"><?= htmlspecialchars($tf['farm_name'] ?: $tf['farmer_name']) ?></div>
              <div class="text-muted text-sm"><?= htmlspecialchars($tf['farmer_name']) ?></div>
            </td>
            <td><i class="fas fa-map-marker-alt" style="color:var(--primary);margin-right:4px;"></i><?= htmlspecialchars($tf['location'] ?: '—') ?></td>
            <td><strong><?= $tf['crop_count'] ?></strong> crops</td>
            <td><span class="badge badge-primary"><?= $tf['fulfilled_orders'] ?> fulfilled</span></td>
            <td><strong style="color:var(--secondary);font-size:15px;"><?= formatCurrency($tf['total_sales']) ?></strong></td>
          </tr>
          <?php 
              $rank++;
            endforeach; 
          endif; 
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top Marketplace Items -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-star" style="color:var(--warning);margin-right:8px;"></i>Top Grossing Marketplace Items</div>
      <a href="../marketplace/index.php" class="text-sm" style="color:var(--primary);font-weight:600;">Open Marketplace →</a>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Type</th>
            <th>Unit Price</th>
            <th>Orders</th>
            <th>Volume Sold</th>
            <th>Total Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($topItems)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No item sales recorded yet.</td></tr>
          <?php else: foreach ($topItems as $item): ?>
          <tr>
            <td>
              <a href="../marketplace/view.php?id=<?= $item['id'] ?>" style="font-weight:700;color:var(--primary);">
                <?= htmlspecialchars($item['title']) ?>
              </a>
            </td>
            <td><span class="badge badge-primary"><?= ucfirst($item['type']) ?></span></td>
            <td><?= formatCurrency($item['price']) ?> / <?= htmlspecialchars($item['unit']) ?></td>
            <td><span class="badge badge-secondary"><?= $item['order_count'] ?> orders</span></td>
            <td><strong><?= number_format($item['total_sold_qty']) ?></strong> <?= htmlspecialchars($item['unit']) ?></td>
            <td><strong style="color:var(--secondary);font-size:15px;"><?= formatCurrency($item['gross_sales']) ?></strong></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include '../includes/footer.php'; ?>
