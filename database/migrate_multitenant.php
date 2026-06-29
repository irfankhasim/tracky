<?php
/**
 * One-time (re-runnable) migration: single-restaurant -> multi-tenant.
 * Adds restaurant_id tenant keys + restaurant branding columns, then backfills
 * existing data to the primary restaurant (lowest id).
 *
 * Run from CLI:  php database/migrate_multitenant.php
 */
require __DIR__ . '/../includes/db.php';

function columnExists(mysqli $conn, string $table, string $col): bool
{
    $t = mysqli_real_escape_string($conn, $table);
    $c = mysqli_real_escape_string($conn, $col);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $res && mysqli_num_rows($res) > 0;
}

function fkExists(mysqli $conn, string $name): bool
{
    $n = mysqli_real_escape_string($conn, $name);
    $res = mysqli_query($conn, "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = '$n' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
    return $res && mysqli_num_rows($res) > 0;
}

function run(mysqli $conn, string $sql, string $label): void
{
    if (mysqli_query($conn, $sql)) {
        echo "OK   $label\n";
    } else {
        echo "FAIL $label -> " . mysqli_error($conn) . "\n";
    }
}

// ── 1. Branding columns on restaurants ──
$brand = [
    'slug'         => "ADD COLUMN slug VARCHAR(80) NULL",
    'logo'         => "ADD COLUMN logo VARCHAR(255) NULL",
    'cover_image'  => "ADD COLUMN cover_image VARCHAR(255) NULL",
    'accent_color' => "ADD COLUMN accent_color VARCHAR(20) NOT NULL DEFAULT '#1D9E75'",
    'is_active'    => "ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1",
];
foreach ($brand as $col => $clause) {
    if (!columnExists($conn, 'restaurants', $col)) {
        run($conn, "ALTER TABLE restaurants $clause", "restaurants.$col");
    } else {
        echo "SKIP restaurants.$col (exists)\n";
    }
}

// ── 2. tenant key on operational tables ──
$tenantTables = ['users', 'runners', 'categories', 'menu_items', 'orders', 'notifications'];
foreach ($tenantTables as $t) {
    if (!columnExists($conn, $t, 'restaurant_id')) {
        run($conn, "ALTER TABLE `$t` ADD COLUMN restaurant_id INT NULL", "$t.restaurant_id");
        run($conn, "ALTER TABLE `$t` ADD INDEX idx_{$t}_restaurant (restaurant_id)", "$t index");
    } else {
        echo "SKIP $t.restaurant_id (exists)\n";
    }
}

// ── 3. Determine primary restaurant (lowest id) ──
$primaryRes = mysqli_query($conn, 'SELECT id FROM restaurants ORDER BY id ASC LIMIT 1');
$primary = $primaryRes ? mysqli_fetch_assoc($primaryRes) : null;
if (!$primary) {
    echo "No restaurant row found; cannot backfill. Insert a restaurant first.\n";
    exit(1);
}
$pid = (int) $primary['id'];
echo "Primary restaurant id = $pid\n";

// ── 4. Backfill ──
run($conn, "UPDATE categories   SET restaurant_id = $pid WHERE restaurant_id IS NULL", "backfill categories");
run($conn, "UPDATE menu_items   SET restaurant_id = $pid WHERE restaurant_id IS NULL", "backfill menu_items");
run($conn, "UPDATE orders       SET restaurant_id = $pid WHERE restaurant_id IS NULL", "backfill orders");
run($conn, "UPDATE notifications SET restaurant_id = $pid WHERE restaurant_id IS NULL", "backfill notifications");
// Admin/staff/runner -> primary; superadmin stays global (NULL)
run($conn, "UPDATE users SET restaurant_id = $pid WHERE restaurant_id IS NULL AND role IN ('admin','staff','runner')", "backfill users (non-superadmin)");
run($conn, "UPDATE users SET restaurant_id = NULL WHERE role = 'superadmin'", "superadmin -> global");
run($conn, "UPDATE runners r JOIN users u ON u.id = r.user_id SET r.restaurant_id = u.restaurant_id WHERE r.restaurant_id IS NULL", "backfill runners from users");

// ── 5. slug for restaurants missing one ──
$rs = mysqli_query($conn, "SELECT id, name FROM restaurants WHERE slug IS NULL OR slug = ''");
while ($r = mysqli_fetch_assoc($rs)) {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $r['name']), '-'));
    if ($base === '') { $base = 'kedai'; }
    $slug = $base;
    $i = 1;
    while (true) {
        $sEsc = mysqli_real_escape_string($conn, $slug);
        $chk = mysqli_query($conn, "SELECT id FROM restaurants WHERE slug = '$sEsc' AND id <> " . (int)$r['id'] . " LIMIT 1");
        if (!$chk || mysqli_num_rows($chk) === 0) break;
        $slug = $base . '-' . (++$i);
    }
    $sEsc = mysqli_real_escape_string($conn, $slug);
    run($conn, "UPDATE restaurants SET slug = '$sEsc' WHERE id = " . (int)$r['id'], "slug for restaurant #{$r['id']} -> $slug");
}

// ── 6. Foreign keys (after backfill) ──
$fks = [
    'fk_users_restaurant'        => "ALTER TABLE users ADD CONSTRAINT fk_users_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
    'fk_runners_restaurant'      => "ALTER TABLE runners ADD CONSTRAINT fk_runners_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
    'fk_categories_restaurant'   => "ALTER TABLE categories ADD CONSTRAINT fk_categories_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
    'fk_menu_items_restaurant'   => "ALTER TABLE menu_items ADD CONSTRAINT fk_menu_items_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
    'fk_orders_restaurant'       => "ALTER TABLE orders ADD CONSTRAINT fk_orders_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
    'fk_notifications_restaurant' => "ALTER TABLE notifications ADD CONSTRAINT fk_notifications_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(id)",
];
foreach ($fks as $name => $sql) {
    if (!fkExists($conn, $name)) {
        run($conn, $sql, "FK $name");
    } else {
        echo "SKIP FK $name (exists)\n";
    }
}

echo "Migration complete.\n";
