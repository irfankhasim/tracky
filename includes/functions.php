<?php

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
        'pending'    => '<span class="badge bg-warning text-dark">Pending</span>',
        'assigned'   => '<span class="badge bg-primary">Assigned</span>',
        'picked_up'  => '<span class="badge bg-purple">Picked Up</span>',
        'in_transit' => '<span class="badge bg-info">In Transit</span>',
        'delivered'  => '<span class="badge bg-success">Delivered</span>',
        'cancelled'  => '<span class="badge bg-danger">Cancelled</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
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

function addNotification(mysqli $conn, string $title, string $message, string $type = 'order'): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO notifications (title, message, type, is_read) VALUES (?, ?, ?, 0)');
    mysqli_stmt_bind_param($stmt, 'sss', $title, $message, $type);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getRestaurant(mysqli $conn): ?array
{
    $res = mysqli_query($conn, 'SELECT * FROM restaurants ORDER BY id ASC LIMIT 1');
    return $res ? mysqli_fetch_assoc($res) : null;
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
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true)) {
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
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'runner') {
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
