<?php
/**
 * Idempotent demo seed: adds a second restaurant (Warung Pak Abu) with its own
 * admin, runner, categories and menu so multi-tenant isolation can be tested.
 * Run:  php database/seed_restaurant2.php
 */
require __DIR__ . '/../includes/db.php';

$slug = 'warung-pak-abu';
$chk = mysqli_query($conn, "SELECT id FROM restaurants WHERE slug = '$slug' LIMIT 1");
if ($chk && mysqli_num_rows($chk) > 0) {
    echo "Restaurant '$slug' already exists. Nothing to do.\n";
    exit;
}

mysqli_begin_transaction($conn);
try {
    mysqli_query($conn, "INSERT INTO restaurants (name, slug, address, phone, email, accent_color)
        VALUES ('Warung Pak Abu', '$slug', 'No 12, Jalan Hang Tuah, 75300 Melaka', '+60 6-987 6543', 'hello@warungpakabu.com', '#E0701A')");
    $rid = (int) mysqli_insert_id($conn);

    $adminHash  = '$2y$10$pg/rjTa3qk.Pdi1QYjqrHOcgA3MCsnP9eGVGZiKwFp7eMdCMRkJaG'; // admin123
    $runnerHash = '$2y$10$JBO8zIgcyHKj..9v1PlzSuhbkuQZWOEbBCcW0pxOUorQIb4CBB8jK'; // runner123

    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, phone, restaurant_id) VALUES (?,?,?,?,?,?)");
    $name='Admin Pak Abu'; $email='admin2@tracky.com'; $role='admin'; $phone='+60 12-444 4444';
    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $adminHash, $role, $phone, $rid);
    mysqli_stmt_execute($stmt);

    $name='Zaki Runner'; $email='runner3@tracky.com'; $role='runner'; $phone='+60 12-555 5555';
    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $runnerHash, $role, $phone, $rid);
    mysqli_stmt_execute($stmt);
    $runnerUid = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    mysqli_query($conn, "INSERT INTO runners (user_id, restaurant_id, vehicle_no, phone, status) VALUES ($runnerUid, $rid, 'MBA 4321', '+60 12-555 5555', 'online')");

    mysqli_query($conn, "INSERT INTO categories (restaurant_id, name, description, is_active, sort_order) VALUES ($rid, 'Western', 'Hidangan western pilihan', 1, 1)");
    $cat1 = (int) mysqli_insert_id($conn);
    mysqli_query($conn, "INSERT INTO categories (restaurant_id, name, description, is_active, sort_order) VALUES ($rid, 'Minuman', 'Minuman sejuk dan panas', 1, 2)");
    $cat2 = (int) mysqli_insert_id($conn);

    $items = [
        [$cat1, 'Chicken Chop', 'Ayam dengan kentang dan sos lada hitam', 15.00],
        [$cat1, 'Spaghetti Bolognese', 'Spaghetti dengan sos daging', 13.00],
        [$cat1, 'Grilled Fish & Chips', 'Ikan panggang dengan kentang goreng', 16.00],
        [$cat2, 'Iced Lemon Tea', 'Teh lemon sejuk', 4.50],
        [$cat2, 'Kopi O Ais', 'Kopi hitam sejuk', 3.00],
    ];
    $istmt = mysqli_prepare($conn, "INSERT INTO menu_items (restaurant_id, category_id, name, description, price, is_available) VALUES (?,?,?,?,?,1)");
    foreach ($items as $it) {
        mysqli_stmt_bind_param($istmt, 'iissd', $rid, $it[0], $it[1], $it[2], $it[3]);
        mysqli_stmt_execute($istmt);
    }
    mysqli_stmt_close($istmt);

    mysqli_query($conn, "INSERT INTO notifications (restaurant_id, title, message, type, is_read) VALUES ($rid, 'Selamat Datang', 'Warung Pak Abu kini di atas talian', 'system', 0)");

    mysqli_commit($conn);
    echo "Seeded restaurant '$slug' (id=$rid) with admin2@tracky.com / admin123 and runner3@tracky.com / runner123.\n";
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo "FAILED: " . $e->getMessage() . "\n";
}
