<?php
// ============================================================
// AgriConnect – Database Configuration & Core Helpers
// ============================================================

if (!defined('DB_HOST'))
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT'))
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME'))
    define('DB_NAME', getenv('DB_NAME') ?: 'agriconnect');
if (!defined('DB_USER'))
    define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS'))
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET'))
    define('DB_CHARSET', 'utf8mb4');

if (!defined('BASE_URL')) {
    // Determine dynamic base URL or fallback
    $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $proto . $host . '/AgriConnect');
}
if (!defined('SITE_NAME'))
    define('SITE_NAME', 'AgriConnect');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $hosts = array_unique([DB_HOST, '127.0.0.1', 'localhost']);
        $lastException = null;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ];

        foreach ($hosts as $h) {
            try {
                $dsn = "mysql:host={$h};port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                try {
                    $pdo->exec("SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
                } catch (Exception $e) {
                }
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                // If database does not exist (1049), attempt to create it
                if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                    try {
                        $rootDsn = "mysql:host={$h};port=" . DB_PORT . ";charset=" . DB_CHARSET;
                        $rootPdo = new PDO($rootDsn, DB_USER, DB_PASS, $options);
                        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                        importSqlFile($pdo, dirname(__DIR__) . '/database/agriconnect.sql');
                        break;
                    } catch (Exception $e2) {
                        $lastException = $e2;
                    }
                }
            }
        }

        if ($pdo === null) {
            if (php_sapi_name() === 'cli') {
                throw $lastException;
            }
            $setupUrl = (defined('BASE_URL') ? BASE_URL : '/AgriConnect') . '/database/setup.php';
            die('<div style="background:#fee2e2;color:#b91c1c;padding:24px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;border-radius:10px;margin:30px auto;max-width:680px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);border-left:6px solid #dc2626;">
                <h2 style="margin-top:0;font-size:18px;">⚠️ Database Connection Notice</h2>
                <p style="margin-bottom:12px;"><strong>Error:</strong> ' . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unable to connect to MySQL') . '</p>
                <p style="font-size:14px;color:#7f1d1d;line-height:1.5;">Please ensure MySQL is running in your environment (e.g. Laragon or XAMPP) on port <strong>' . htmlspecialchars(DB_PORT) . '</strong>.</p>
                <div style="margin-top:16px;">
                    <a href="' . htmlspecialchars($setupUrl) . '" style="display:inline-block;background:#dc2626;color:white;padding:10px 18px;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">Open Database Setup & Diagnostics</a>
                </div>
            </div>');
        }

        // Schema integrity check: ensure users table exists, but NEVER drop or overwrite existing data
        try {
            $userTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
            if (!$userTable) {
                importSqlFile($pdo, dirname(__DIR__) . '/database/agriconnect.sql');
            } else {
                // Ensure is_2fa_enabled column exists
                $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_2fa_enabled'")->fetch();
                if (!$col) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN is_2fa_enabled TINYINT(1) DEFAULT 0 AFTER is_verified");
                }
            }
        } catch (Exception $ex) {
        }
    }
    return $pdo;
}

// Start session if not started.
// Use an application-specific session name so AgriConnect's session is NOT shared
// with other PHP apps on localhost (PHPSESSID path=/ is sent to every app on the
// domain). Previously, any other Laragon project calling session_destroy() or
// logging in/out overwrote this app's session file, which logged users out and
// made the dashboard flip between Farmer/Buyer/Admin layouts.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_name('AGRI_CONNECT_SESSID');
    session_start();
}

// Helper: check if user is logged in
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper: require login
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

// Helper: require admin
function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

// Helper: current user
function currentUser(): array
{
    return [
        'id' => $_SESSION['user_id'] ?? 0,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'buyer',
        'avatar' => $_SESSION['user_avatar'] ?? 'default.png',
    ];
}

// Helper: sanitize input
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper: redirect with message
function redirect(string $url, string $msg = '', string $type = 'success'): void
{
    if ($msg) {
        $_SESSION['flash_msg'] = $msg;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

// Helper: flash message
function getFlash(): array
{
    $msg = $_SESSION['flash_msg'] ?? '';
    $type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
    return ['msg' => $msg, 'type' => $type];
}

// Helper: generate OTP
function generateOTP(int $length = 6): string
{
    return str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
}

// Helper: log activity
function logActivity(int $userId, string $action, string $desc): void
{
    try {
        $pdo = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?,?,?,?)");
        $stmt->execute([$userId ?: null, $action, $desc, $ip]);
    } catch (Exception $e) { /* silent */
    }
}

// Helper: format currency
function formatCurrency(float $amount): string
{
    $symbol = getSetting('currency_symbol', '₹');
    return $symbol . ' ' . number_format($amount, 2);
}

// Helper: time ago
function timeAgo(string $datetime): string
{
    try {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
        if ($diff->y > 0)
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0)
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0)
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0)
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0)
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'just now';
    } catch (Exception $e) {
        return $datetime;
    }
}

// Helper: pagination
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
    $currentPage = max(1, min($currentPage, $totalPages));
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => ($currentPage - 1) * $perPage,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

// Helper: get setting value from settings table
function getSetting(string $key, string $default = ''): string
{
    static $cachedSettings = null;
    try {
        $pdo = getDB();
        if ($cachedSettings === null) {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $cachedSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }
        return $cachedSettings[$key] ?? $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Helper: update or insert setting
function updateSetting(string $key, string $value): bool
{
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// Append the file's modification time so browsers refetch an image whenever the file is replaced
function imgVer(string $absPath): string
{
    $t = @filemtime($absPath);
    return $t ? '?v=' . $t : '';
}

// Helper: resolve a local image file inside assets/images/{folder} or null if absent
function resolveImage(string $folder, ?string $file): ?string
{
    if (empty($file))
        return null;
    $file = basename($file);
    if ($file === '')
        return null;
    $abs = dirname(__DIR__) . '/assets/images/' . trim($folder, '/') . '/' . $file;
    return file_exists($abs) ? BASE_URL . '/assets/images/' . trim($folder, '/') . '/' . $file . imgVer($abs) : null;
}

// Helper: pick the first bundled image whose keyword occurs in $haystack (existence-checked across folders)
function pickImage(array $folders, array $map, string $haystack, string $fallback): string
{
    $h = strtolower($haystack);
    foreach ($map as $keyword => $file) {
        if (!str_contains($h, $keyword))
            continue;
        foreach ($folders as $folder) {
            $abs = dirname(__DIR__) . '/assets/images/' . $folder . '/' . $file;
            if (file_exists($abs)) {
                return BASE_URL . '/assets/images/' . $folder . '/' . $file . imgVer($abs);
            }
        }
    }
    $fbAbs = dirname(__DIR__) . '/assets/images/' . $folders[0] . '/' . $fallback;
    return BASE_URL . '/assets/images/' . $folders[0] . '/' . $fallback . imgVer($fbAbs);
}

// Helper: URL for any file under assets/images (cache-busted by filemtime)
function assetImg(string $relPath): string
{
    $relPath = ltrim($relPath, '/');
    $abs = dirname(__DIR__) . '/assets/images/' . $relPath;
    return BASE_URL . '/assets/images/' . $relPath . imgVer($abs);
}

// Helper: image URL for a crop record (falls back to a crop-relevant bundled image)
function cropImage(array $crop): string
{
    $explicit = resolveImage('crops', $crop['image'] ?? null);
    $isDefault = empty($crop['image']) || str_starts_with($crop['image'], 'default');
    if ($explicit && !$isDefault)
        return $explicit;
    return pickImage(['crops'], [
        'wheat' => 'wheat.jpg', 'basmati' => 'basmati-rice.jpg', 'rice' => 'basmati-rice.jpg',
        'paddy' => 'basmati-rice.jpg', 'tomato' => 'tomato.jpg', 'potato' => 'potato.jpg',
        'sugarcane' => 'sugarcane.jpg', 'onion' => 'onion.jpg', 'cotton' => 'cotton.jpg', 'kapas' => 'cotton.jpg',
        'soybean' => 'soybean.jpg', 'soya' => 'soybean.jpg', 'maize' => 'maize.jpg',
        'pea' => 'green-peas.jpg', 'mango' => 'mango.jpg', 'turmeric' => 'turmeric.jpg',
        'spinach' => 'spinach.jpg', 'gourd' => 'bitter-gourd.jpg', 'corn' => 'sweet-corn.jpg',
    ], ($crop['name'] ?? '') . ' ' . ($crop['variety'] ?? ''), 'default_crop.jpg');
}

// Helper: resolve a stored image filename against several asset folders (first existing file wins)
function resolveImageInFolders(array $folders, ?string $file): ?string
{
    if (empty($file))
        return null;
    $file = basename($file);
    if ($file === '')
        return null;
    foreach ($folders as $folder) {
        $abs = dirname(__DIR__) . '/assets/images/' . trim($folder, '/') . '/' . $file;
        if (file_exists($abs)) {
            return BASE_URL . '/assets/images/' . trim($folder, '/') . '/' . $file . imgVer($abs);
        }
    }
    return null;
}

// Helper: image URL for a product record
function productImage(array $product): string
{
    $explicit = resolveImageInFolders(['products', 'crops'], $product['image'] ?? null);
    $isDefault = empty($product['image']) || str_starts_with($product['image'], 'default');
    if ($explicit && !$isDefault)
        return $explicit;
    return pickImage(['products', 'crops'], [
        'wheat' => 'wheat.jpg', 'basmati' => 'basmati-rice.jpg', 'rice' => 'basmati-rice.jpg',
        'paddy' => 'basmati-rice.jpg', 'tomato' => 'tomato.jpg', 'potato' => 'potato.jpg',
        'onion' => 'onion.jpg', 'cotton' => 'cotton.jpg', 'kapas' => 'cotton.jpg',
        'turmeric' => 'turmeric.jpg', 'mango' => 'mango.jpg', 'maize' => 'maize-seeds.jpg',
        'corn' => 'sweet-corn.jpg', 'pea' => 'green-peas.jpg', 'gourd' => 'bitter-gourd.jpg',
        'spinach' => 'spinach.jpg', 'sugarcane' => 'sugarcane.jpg', 'soybean' => 'soybean.jpg',
        'compost' => 'vermicompost.jpg', 'vermi' => 'vermicompost.jpg',
        'neem' => 'neem-oil.jpg', 'sprayer' => 'hand-sprayer.jpg', 'spray' => 'hand-sprayer.jpg',
        'seed' => 'maize-seeds.jpg',
    ], ($product['name'] ?? '') . ' ' . ($product['description'] ?? ''), 'default_product.jpg');
}

// Helper: image URL for a marketplace listing
function listingImage(array $listing): string
{
    $explicit = resolveImageInFolders(['listings', 'crops', 'products'], $listing['image'] ?? null);
    $isDefault = empty($listing['image']) || str_starts_with($listing['image'], 'default');
    if ($explicit && !$isDefault)
        return $explicit;
    return pickImage(['listings', 'crops', 'products'], [
        'wheat' => 'wheat.jpg', 'basmati' => 'basmati-rice.jpg', 'rice' => 'basmati-rice.jpg',
        'tomato' => 'tomato.jpg', 'potato' => 'potato.jpg', 'sugarcane' => 'sugarcane.jpg',
        'onion' => 'onion.jpg', 'cotton' => 'cotton.jpg', 'soybean' => 'soybean.jpg',
        'maize' => 'maize-seeds.jpg', 'corn' => 'sweet-corn.jpg', 'pea' => 'green-peas.jpg',
        'mango' => 'mango.jpg', 'turmeric' => 'turmeric.jpg', 'spinach' => 'spinach.jpg',
        'gourd' => 'bitter-gourd.jpg', 'neem' => 'neem-oil.jpg', 'sprayer' => 'hand-sprayer.jpg',
        'compost' => 'vermicompost.jpg', 'seed' => 'maize-seeds.jpg',
    ], ($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''), 'default_listing.jpg');
}

// Helper: user avatar URL with graceful fallback
function avatarImage(?string $file): string
{
    $url = resolveImage('avatars', $file);
    return $url ?: BASE_URL . '/assets/images/avatars/default.png';
}

// Helper: handle an uploaded image; returns stored filename or null on failure
function handleImageUpload(string $field, string $folder, array &$errors): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errors[$field] = 'Image upload failed (error code ' . $f['error'] . ').';
        return null;
    }
    if ($f['size'] > 3 * 1024 * 1024) {
        $errors[$field] = 'Image must be smaller than 3 MB.';
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        $errors[$field] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
        return null;
    }
    $dir = dirname(__DIR__) . '/assets/images/' . trim($folder, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        $errors[$field] = 'Unable to create the image directory.';
        return null;
    }
    $name = 'up_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info[2]];
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        $errors[$field] = 'Unable to save the uploaded image.';
        return null;
    }
    return $name;
}

// Helper: import SQL file safely statement by statement
function importSqlFile(PDO $pdo, string $filePath): bool
{
    if (!file_exists($filePath))
        return false;
    $rawSql = file_get_contents($filePath);
    $cleanLines = [];
    foreach (explode("\n", $rawSql) as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
            continue;
        }
        $cleanLines[] = $line;
    }
    $cleanSql = implode("\n", $cleanLines);
    $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
    foreach ($statements as $st) {
        if (!empty($st)) {
            try {
                $pdo->exec($st);
            } catch (Exception $e) {
                // Ignore benign notices (e.g. database exists)
            }
        }
    }
    return true;
}
