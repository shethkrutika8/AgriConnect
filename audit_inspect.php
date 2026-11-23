<?php
require_once __DIR__ . '/config/db.php';
$pdo = getDB();

echo "=== DATABASE IMAGE AUDIT ===\n";

echo "\n--- CROPS ---\n";
foreach ($pdo->query('SELECT id, name, image FROM crops')->fetchAll() as $r) {
    $img = $r['image'];
    $exists1 = file_exists(__DIR__ . "/assets/images/crops/$img");
    $exists2 = file_exists(__DIR__ . "/assets/images/$img");
    echo "{$r['id']}: {$r['name']} => '$img' (in crops/: " . ($exists1 ? 'YES' : 'NO') . ", in images/: " . ($exists2 ? 'YES' : 'NO') . ")\n";
}

echo "\n--- PRODUCTS ---\n";
foreach ($pdo->query('SELECT id, name, image FROM products')->fetchAll() as $r) {
    $img = $r['image'];
    $exists1 = file_exists(__DIR__ . "/assets/images/products/$img");
    $exists2 = file_exists(__DIR__ . "/assets/images/$img");
    echo "{$r['id']}: {$r['name']} => '$img' (in products/: " . ($exists1 ? 'YES' : 'NO') . ", in images/: " . ($exists2 ? 'YES' : 'NO') . ")\n";
}

echo "\n--- MARKETPLACE LISTINGS ---\n";
foreach ($pdo->query('SELECT id, title, image FROM marketplace_listings')->fetchAll() as $r) {
    $img = $r['image'];
    $exists1 = file_exists(__DIR__ . "/assets/images/listings/$img");
    $exists2 = file_exists(__DIR__ . "/assets/images/crops/$img");
    $exists3 = file_exists(__DIR__ . "/assets/images/products/$img");
    $exists4 = file_exists(__DIR__ . "/assets/images/$img");
    echo "{$r['id']}: {$r['title']} => '$img' (listings: " . ($exists1 ? 'YES' : 'NO') . ", crops: " . ($exists2 ? 'YES' : 'NO') . ", prods: " . ($exists3 ? 'YES' : 'NO') . ", root: " . ($exists4 ? 'YES' : 'NO') . ")\n";
}
