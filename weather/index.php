<?php
require_once '../config/db.php';
requireLogin();

$active_page = 'weather';
$page_title  = 'Weather Advisory & Forecast';
$breadcrumb  = [['label' => 'Weather']];

$pdo  = getDB();
$user = currentUser();

// Selected City
$selectedCity = trim($_GET['city'] ?? getSetting('weather_city', 'New Delhi'));

// Handle new weather data submission (record/update)
$msg = '';
$msgType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_weather') {
    $city     = trim($_POST['city'] ?? 'New Delhi');
    $temp     = (float)($_POST['temperature'] ?? 25);
    $feels    = (float)($_POST['feels_like'] ?? $temp);
    $cond     = trim($_POST['condition'] ?? 'Partly Cloudy');
    $humidity = (int)($_POST['humidity'] ?? 60);
    $windSpd  = (float)($_POST['wind_speed'] ?? 10);
    $windDir  = trim($_POST['wind_direction'] ?? 'N');
    $visib    = (float)($_POST['visibility'] ?? 8);
    $uv       = (int)($_POST['uv_index'] ?? 5);
    $fDate    = trim($_POST['forecast_date'] ?? date('Y-m-d'));

    $ins = $pdo->prepare("
        INSERT INTO weather_data (city, temperature, feels_like, `condition`, humidity, wind_speed, wind_direction, visibility, uv_index, forecast_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->execute([$city, $temp, $feels, $cond, $humidity, $windSpd, $windDir, $visib, $uv, $fDate]);
    $selectedCity = $city;
    $msg = "Weather observation for $city on " . date('d M Y', strtotime($fDate)) . " recorded successfully!";
}

// Fetch available cities in the database
$cities = $pdo->query("SELECT DISTINCT city FROM weather_data ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($cities)) {
    $cities = ['New Delhi', 'Amritsar', 'Karnal', 'Nashik', 'Nagpur', 'Jaipur', 'Lucknow'];
}

// Fetch current weather for the selected city
$currentStmt = $pdo->prepare("
    SELECT * FROM weather_data 
    WHERE city = ? 
    ORDER BY ABS(DATEDIFF(forecast_date, CURDATE())) ASC, forecast_date DESC 
    LIMIT 1
");
$currentStmt->execute([$selectedCity]);
$currentWeather = $currentStmt->fetch();

// If not found for city, fallback to any latest record
if (!$currentWeather) {
    $currentWeather = $pdo->query("SELECT * FROM weather_data ORDER BY forecast_date DESC LIMIT 1")->fetch();
    if ($currentWeather) $selectedCity = $currentWeather['city'];
}

// Fetch 7-day forecast for the selected city
$forecastStmt = $pdo->prepare("
    SELECT * FROM weather_data 
    WHERE city = ? 
    ORDER BY forecast_date ASC 
    LIMIT 7
");
$forecastStmt->execute([$selectedCity]);
$forecast = $forecastStmt->fetchAll();

// If fewer than 7 days, generate synthetic forecast days based on base
if (count($forecast) < 7 && $currentWeather) {
    // Forecast exists or base exists
}

// Agricultural Advisory Logic
$temp      = (float)($currentWeather['temperature'] ?? 28);
$humidity  = (int)($currentWeather['humidity'] ?? 65);
$condition = strtolower($currentWeather['condition'] ?? 'partly cloudy');
$windSpeed = (float)($currentWeather['wind_speed'] ?? 12);

$advisories = [];

if (str_contains($condition, 'rain') || str_contains($condition, 'storm')) {
    $advisories[] = [
        'title' => 'Hold Off on Spraying & Fertilizing',
        'type'  => 'danger',
        'icon'  => 'cloud-showers-heavy',
        'desc'  => 'Postpone pesticide spraying and top-dressing urea or fertilizers. Heavy rain will wash active chemicals away and cause nutrient runoff. Ensure drainage trenches in low-lying crop fields are clear to prevent water stagnation.'
    ];
} elseif ($temp > 33) {
    $advisories[] = [
        'title' => 'High Heat Stress Mitigation',
        'type'  => 'warning',
        'icon'  => 'temperature-high',
        'desc'  => 'Elevated ambient temperature will increase crop evapotranspiration. Irrigate crops during evening or night hours to reduce evaporative loss. Mulch inter-row spaces to conserve soil moisture.'
    ];
} else {
    $advisories[] = [
        'title' => 'Favorable Field Operations Window',
        'type'  => 'success',
        'icon'  => 'sun',
        'desc'  => 'Weather conditions are optimal for general intercultural operations, weed removal, sowing, and sun-drying of harvested grains. Good light and mild humidity favor crop photosynthesis.'
    ];
}

if ($humidity > 75) {
    $advisories[] = [
        'title' => 'Fungal Disease & Pest Surveillance',
        'type'  => 'warning',
        'icon'  => 'bug',
        'desc'  => 'High atmospheric humidity combined with warm temperatures creates favorable conditions for foliar blights, powdery mildew, and fungal rusts. Conduct close scouting of crops and prepare preventive biopesticides if lesions appear.'
    ];
}

if ($windSpeed > 20) {
    $advisories[] = [
        'title' => 'Wind Hazard Advisory',
        'type'  => 'warning',
        'icon'  => 'wind',
        'desc'  => 'High gusty winds can cause lodging in tall crops like maize, sugarcane, and heading wheat. Provide mechanical propping if feasible and avoid fine-droplet herbicide spraying.'
    ];
}

include '../includes/header.php';
?>

<div class="page-content">
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <div class="page-title">Agro-Meteorological Advisory</div>
      <div class="page-subtitle">Real-time weather insights, farm recommendations & 7-day outlook</div>
    </div>
    <div class="d-flex gap-8">
      <button class="btn btn-outline-primary" data-modal-open="addWeatherModal">
        <i class="fas fa-plus"></i> Record Weather Data
      </button>
    </div>
  </div>

  <?php if (!empty($msg)): ?>
  <div class="alert alert-<?= $msgType ?> mb-24">
    <i class="fas fa-check-circle"></i>
    <div><?= htmlspecialchars($msg) ?></div>
  </div>
  <?php endif; ?>

  <!-- City Filter / Switcher Bar -->
  <div class="card mb-24" style="background:var(--white);">
    <div class="card-body d-flex justify-between align-center" style="padding:14px 20px;flex-wrap:wrap;gap:12px;">
      <div class="d-flex align-center gap-12">
        <i class="fas fa-map-marked-alt" style="color:var(--primary);font-size:18px;"></i>
        <span style="font-weight:600;color:var(--gray-700);">Select Farm Region / City:</span>
        <form method="GET" id="cityForm" class="d-flex align-center gap-8">
          <select name="city" class="form-control" style="width:200px;font-weight:600;" onchange="document.getElementById('cityForm').submit()">
            <?php foreach ($cities as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>" <?= $selectedCity === $c ? 'selected' : '' ?>>
              <?= htmlspecialchars($c) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <div class="text-sm text-muted">
        Observation Date: <strong><?= !empty($currentWeather['forecast_date']) ? date('D, d M Y', strtotime($currentWeather['forecast_date'])) : date('D, d M Y') ?></strong>
      </div>
    </div>
  </div>

  <?php if ($currentWeather): ?>
  <!-- Hero Weather Banner -->
  <div class="card mb-24" style="background: linear-gradient(135deg, #064e3b 0%, #15803d 100%); color: white; border: none; box-shadow: var(--shadow-lg);">
    <div class="card-body" style="padding: 28px;">
      <div class="d-flex justify-between align-center" style="flex-wrap:wrap;gap:20px;">
        
        <!-- Left: Main Weather Display -->
        <div class="d-flex align-center gap-20">
          <div style="font-size: 64px; line-height: 1;">
            <?php
            $cond = strtolower($currentWeather['condition'] ?? '');
            if (str_contains($cond,'rain')) echo '🌧️';
            elseif (str_contains($cond,'cloud')) echo '⛅';
            elseif (str_contains($cond,'sun') || str_contains($cond,'clear')) echo '☀️';
            elseif (str_contains($cond,'storm')) echo '⛈️';
            else echo '🌤️';
            ?>
          </div>
          <div>
            <div style="font-size: 44px; font-weight: 800; line-height: 1.1;">
              <?= round($currentWeather['temperature']) ?>°C
            </div>
            <div style="font-size: 18px; font-weight: 600; opacity: 0.95; margin-top: 4px;">
              <?= htmlspecialchars($currentWeather['condition']) ?>
            </div>
            <div style="font-size: 13px; opacity: 0.8;">
              Feels like <?= round($currentWeather['feels_like'] ?? $currentWeather['temperature']) ?>°C · <?= htmlspecialchars($currentWeather['city']) ?>
            </div>
          </div>
        </div>

        <!-- Right: Atmospheric Parameters Grid -->
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px 24px; background: rgba(255,255,255,0.12); padding: 16px 20px; border-radius: var(--radius-lg); backdrop-filter: blur(4px);">
          <div class="d-flex align-center gap-10">
            <i class="fas fa-tint" style="font-size: 16px; opacity: 0.85;"></i>
            <div>
              <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase;">Humidity</div>
              <div style="font-size: 15px; font-weight: 700;"><?= $currentWeather['humidity'] ?>%</div>
            </div>
          </div>
          <div class="d-flex align-center gap-10">
            <i class="fas fa-wind" style="font-size: 16px; opacity: 0.85;"></i>
            <div>
              <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase;">Wind Speed</div>
              <div style="font-size: 15px; font-weight: 700;"><?= $currentWeather['wind_speed'] ?> km/h (<?= $currentWeather['wind_direction'] ?>)</div>
            </div>
          </div>
          <div class="d-flex align-center gap-10">
            <i class="fas fa-sun" style="font-size: 16px; opacity: 0.85;"></i>
            <div>
              <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase;">UV Index</div>
              <div style="font-size: 15px; font-weight: 700;"><?= $currentWeather['uv_index'] ?? 5 ?> / 10</div>
            </div>
          </div>
          <div class="d-flex align-center gap-10">
            <i class="fas fa-eye" style="font-size: 16px; opacity: 0.85;"></i>
            <div>
              <div style="font-size: 11px; opacity: 0.8; text-transform: uppercase;">Visibility</div>
              <div style="font-size: 15px; font-weight: 700;"><?= $currentWeather['visibility'] ?? 8 ?> km</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- 7-Day Forecast Grid -->
  <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;color:var(--gray-900);">
    <i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:8px;"></i>
    7-Day Regional Forecast (<?= htmlspecialchars($selectedCity) ?>)
  </h3>
  
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 28px;">
    <?php 
    if (!empty($forecast)):
      foreach ($forecast as $idx => $f): 
        $dayName = date('D', strtotime($f['forecast_date']));
        $fCond = strtolower($f['condition']);
        $icon = '🌤️';
        if (str_contains($fCond,'rain')) $icon = '🌧️';
        elseif (str_contains($fCond,'cloud')) $icon = '⛅';
        elseif (str_contains($fCond,'sun') || str_contains($fCond,'clear')) $icon = '☀️';
        $isToday = (date('Y-m-d', strtotime($f['forecast_date'])) === date('Y-m-d'));
    ?>
    <div class="card" style="text-align:center;padding:16px 10px;<?= $isToday ? 'border:2px solid var(--primary);background:var(--primary-pale);' : '' ?>">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:<?= $isToday ? 'var(--primary-dark)' : 'var(--gray-500)' ?>;">
        <?= $isToday ? 'Today' : $dayName ?>
      </div>
      <div style="font-size:11px;color:var(--gray-400);margin-bottom:8px;">
        <?= date('d M', strtotime($f['forecast_date'])) ?>
      </div>
      <div style="font-size:32px;margin-bottom:6px;">
        <?= $icon ?>
      </div>
      <div style="font-size:18px;font-weight:800;color:var(--gray-900);">
        <?= round($f['temperature']) ?>°C
      </div>
      <div style="font-size:11px;color:var(--gray-600);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <?= htmlspecialchars($f['condition']) ?>
      </div>
      <div style="font-size:11px;color:var(--primary);margin-top:6px;font-weight:600;">
        <i class="fas fa-tint"></i> <?= $f['humidity'] ?>%
      </div>
    </div>
    <?php 
      endforeach;
    endif; 
    ?>
  </div>

  <!-- Agricultural Advisories & Crop Impact Cards -->
  <div class="grid-2 mb-24">
    <!-- Active Advisories -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-lightbulb" style="color:var(--warning);margin-right:8px;"></i>Actionable Farm Recommendations</div>
      </div>
      <div class="card-body">
        <?php foreach ($advisories as $adv): ?>
        <div class="alert alert-<?= $adv['type'] ?> mb-16" style="align-items:flex-start;">
          <i class="fas fa-<?= $adv['icon'] ?>" style="font-size:20px;margin-top:2px;"></i>
          <div>
            <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?= htmlspecialchars($adv['title']) ?></div>
            <div style="font-size:13px;line-height:1.5;"><?= htmlspecialchars($adv['desc']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Crop Specific Advisories -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-seedling" style="color:var(--primary);margin-right:8px;"></i>Crop Specific Weather Advisory</div>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:14px;font-size:13px;">
        <div style="border-left:4px solid var(--primary);padding-left:12px;">
          <div style="font-weight:700;color:var(--gray-900);">🌾 Wheat & Barley (Rabi)</div>
          <p class="text-muted" style="margin-top:2px;">
            Ideal daytime temperature is 18°C–25°C. Keep soil moist during crown root initiation (CRI) and grain filling stages. Watch out for yellow rust if fog and overcast skies persist.
          </p>
        </div>

        <div style="border-left:4px solid var(--info);padding-left:12px;">
          <div style="font-weight:700;color:var(--gray-900);">🍚 Basmati & Paddy Rice</div>
          <p class="text-muted" style="margin-top:2px;">
            Ensure standing water of 2–3 cm during tillering stage. Drain fields 10–12 days prior to harvest for uniform ripening and machine access.
          </p>
        </div>

        <div style="border-left:4px solid var(--accent);padding-left:12px;">
          <div style="font-weight:700;color:var(--gray-900);">🍅 Vegetables (Tomato, Chilli, Onion)</div>
          <p class="text-muted" style="margin-top:2px;">
            Drip irrigation is recommended. Avoid overhead sprinkling during flowering to avoid blossom drop and early blight fungal disease.
          </p>
        </div>

        <div style="border-left:4px solid var(--secondary);padding-left:12px;">
          <div style="font-weight:700;color:var(--gray-900);">🌱 Cotton & Cash Crops</div>
          <p class="text-muted" style="margin-top:2px;">
            Dry sunny weather is beneficial during boll bursting. Avoid irrigation when 50% of the bolls have opened to maintain lint quality.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Record Weather Modal -->
<div class="modal-overlay" id="addWeatherModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-cloud-sun" style="color:var(--primary);margin-right:8px;"></i>Record Weather Observation</div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_weather">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">City / Region <span class="req">*</span></label>
            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($selectedCity) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Date <span class="req">*</span></label>
            <input type="date" name="forecast_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Temperature (°C) <span class="req">*</span></label>
            <input type="number" name="temperature" class="form-control" value="28" step="0.5" required>
          </div>
          <div class="form-group">
            <label class="form-label">Feels Like (°C)</label>
            <input type="number" name="feels_like" class="form-control" value="29" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Weather Condition</label>
            <select name="condition" class="form-control">
              <option>Sunny</option>
              <option selected>Partly Cloudy</option>
              <option>Cloudy</option>
              <option>Light Rain</option>
              <option>Rainy</option>
              <option>Thunderstorm</option>
            </select>
          </div>
        </div>

        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Humidity (%)</label>
            <input type="number" name="humidity" class="form-control" value="65" min="0" max="100">
          </div>
          <div class="form-group">
            <label class="form-label">Wind Speed (km/h)</label>
            <input type="number" name="wind_speed" class="form-control" value="12" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Wind Direction</label>
            <select name="wind_direction" class="form-control">
              <option>N</option><option>NE</option><option>E</option><option>SE</option>
              <option>S</option><option>SW</option><option>W</option><option>NW</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-between">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Weather Data</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
