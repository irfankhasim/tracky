<?php

/**
 * Build a local asset URL with a cache-busting version (file modified time).
 * Ensures browsers (e.g. Chrome) always fetch the latest CSS/JS after edits,
 * so the UI renders consistently across environments.
 */
function asset(string $rel): string
{
    $rel = ltrim($rel, '/');
    $fs = dirname(__DIR__) . '/' . $rel;
    $v = is_file($fs) ? filemtime($fs) : 1;
    return '/tracky/' . $rel . '?v=' . $v;
}

/**
 * Handle a menu item image upload.
 * Validates type/size, moves the file into assets/uploads/menu/, and returns
 * the web-relative path (e.g. "assets/uploads/menu/xyz.jpg") on success.
 * Returns null when no file was uploaded; throws RuntimeException on invalid file.
 */
function uploadMenuImage(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Muat naik gambar gagal. Cuba lagi.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Saiz gambar terlalu besar (maksimum 3MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format gambar tidak disokong (guna JPG, PNG, WEBP atau GIF).');
    }

    $dir = dirname(__DIR__) . '/assets/uploads/menu';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Tidak dapat mencipta folder muat naik.');
    }

    $filename = 'menu_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }

    return 'assets/uploads/menu/' . $filename;
}

/**
 * Build a web URL for a stored menu image, or empty string when none.
 */
function menuImageUrl(?string $path): string
{
    if (!$path) {
        return '';
    }
    return '/tracky/' . ltrim($path, '/');
}

/**
 * Generic image uploader. $subdir is relative to assets/uploads (e.g. "restaurants").
 * Returns the stored relative path, or null when no file was provided.
 */
function uploadImageTo(array $file, string $subdir, string $prefix): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Muat naik gambar gagal. Cuba lagi.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Saiz gambar terlalu besar (maksimum 3MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format gambar tidak disokong (guna JPG, PNG, WEBP atau GIF).');
    }

    $subdir = trim($subdir, '/');
    $dir = dirname(__DIR__) . '/assets/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Tidak dapat mencipta folder muat naik.');
    }

    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }

    return 'assets/uploads/' . $subdir . '/' . $filename;
}

function generateOrderNo(mysqli $conn): string
{
    do {
        $order_no = 'ORD-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = mysqli_prepare($conn, 'SELECT id FROM orders WHERE order_no = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $order_no);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    } while ($exists);
    return $order_no;
}

function getStatusBadge(string $status): string
{
    $badges = [
        'pending'    => '<span class="badge-status status-pending">Pending</span>',
        'assigned'   => '<span class="badge-status status-assigned">Assigned</span>',
        'picked_up'  => '<span class="badge-status status-picked_up">Picked Up</span>',
        'in_transit' => '<span class="badge-status status-in_transit">In Transit</span>',
        'delivered'  => '<span class="badge-status status-delivered">Delivered</span>',
        'cancelled'  => '<span class="badge-status status-cancelled">Cancelled</span>',
    ];
    return $badges[$status] ?? '<span class="badge-status status-cancelled">' . htmlspecialchars($status) . '</span>';
}

function getStatusLabel(string $status): string
{
    $labels = [
        'pending'    => 'Menunggu',
        'assigned'   => 'Runner Assigned',
        'picked_up'  => 'Dah Diambil',
        'in_transit' => 'Dalam Perjalanan',
        'delivered'  => 'Dah Dihantar',
        'cancelled'  => 'Dibatalkan',
    ];
    return $labels[$status] ?? $status;
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) {
        return max(1, $diff) . ' saat lepas';
    }
    if ($diff < 3600) {
        return round($diff / 60) . ' minit lepas';
    }
    if ($diff < 86400) {
        return round($diff / 3600) . ' jam lepas';
    }
    return date('d M Y', $time);
}

function formatPrice(float $price): string
{
    return 'RM ' . number_format($price, 2);
}

function isRunnerAvailable(mysqli $conn, int $runner_id): bool
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT r.id FROM runners r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.id = ?
           AND r.status = 'online'
           AND u.is_active = 1
           AND NOT EXISTS (
             SELECT 1 FROM deliveries d
             WHERE d.runner_id = r.id
               AND d.status IN ('assigned','picked_up','in_transit')
           )
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'i', $runner_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row !== null;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function addNotification(mysqli $conn, string $title, string $message, string $type = 'order', ?int $restaurant_id = null): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO notifications (restaurant_id, title, message, type, is_read) VALUES (?, ?, ?, ?, 0)');
    mysqli_stmt_bind_param($stmt, 'isss', $restaurant_id, $title, $message, $type);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Fetch a restaurant by id; when $id is null/0 falls back to the first (primary)
 * restaurant so legacy callers keep working.
 */
function getRestaurant(mysqli $conn, ?int $id = null): ?array
{
    if ($id !== null && $id > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM restaurants WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
    $res = mysqli_query($conn, 'SELECT * FROM restaurants ORDER BY id ASC LIMIT 1');
    return $res ? mysqli_fetch_assoc($res) : null;
}

/**
 * The restaurant the current session operates within.
 * - admin/staff/runner: their own restaurant_id (set at login)
 * - superadmin: the restaurant they are currently "acting as" (sa_acting_restaurant), else 0
 * Returns 0 when there is no tenant context.
 */
function activeRestaurantId(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $role = $_SESSION['role'] ?? '';
    if ($role === 'superadmin') {
        return (int) ($_SESSION['sa_acting_restaurant'] ?? 0);
    }
    return (int) ($_SESSION['restaurant_id'] ?? 0);
}

/**
 * Web URL for a stored restaurant asset (logo / cover), or '' when none.
 */
function restaurantAsset(?string $path): string
{
    if (!$path) {
        return '';
    }
    return '/tracky/' . ltrim($path, '/');
}

/**
 * The restaurant a guest customer is currently ordering from (0 when none picked).
 */
function custRestaurantId(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return (int) ($_SESSION['cust_restaurant_id'] ?? 0);
}

function deliveryFeeForSubtotal(array $restaurant, float $subtotal): float
{
    $fee = (float) ($restaurant['delivery_fee'] ?? 5);
    $min = (float) ($restaurant['free_delivery_min'] ?? 30);
    return $subtotal >= $min ? 0.0 : $fee;
}

function requireAdminApi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function requireStaffApi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function requireRunnerApi(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['runner', 'superadmin'], true)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

function getOrderItems(mysqli $conn, int $order_id): array
{
    $items = [];
    $stmt = mysqli_prepare($conn, 'SELECT * FROM order_items WHERE order_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

function logStatusChange(mysqli $conn, int $order_id, ?string $old, string $new, ?int $user_id): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO status_logs (order_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'issi', $order_id, $old, $new, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function nextRunnerAction(string $delivery_status): ?array
{
    $map = [
        'assigned'   => ['label' => 'Saya Dah Ambil Order', 'next' => 'picked_up', 'class' => 'btn-success'],
        'picked_up'  => ['label' => 'Saya Dalam Perjalanan', 'next' => 'in_transit', 'class' => 'btn-primary'],
        'in_transit' => ['label' => 'Order Dah Dihantar', 'next' => 'delivered', 'class' => 'btn-success'],
    ];
    return $map[$delivery_status] ?? null;
}

function mapDeliveryToOrderStatus(string $delivery_status): string
{
    $map = [
        'assigned'   => 'assigned',
        'picked_up'  => 'picked_up',
        'in_transit' => 'in_transit',
        'delivered'  => 'delivered',
    ];
    return $map[$delivery_status] ?? 'assigned';
}
