<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$rid = (int) ($_GET['restaurant'] ?? custRestaurantId());
if ($rid < 1) {
    echo json_encode([]);
    exit;
}
$category = isset($_GET['category']) ? (int) $_GET['category'] : 0;

$sql = "SELECT m.*, c.name AS category_name
        FROM menu_items m
        JOIN categories c ON m.category_id = c.id
        WHERE m.is_available = 1 AND c.is_active = 1 AND m.restaurant_id = ?";
if ($category > 0) {
    $stmt = mysqli_prepare($conn, $sql . ' AND m.category_id = ? ORDER BY c.sort_order, m.name');
    mysqli_stmt_bind_param($stmt, 'ii', $rid, $category);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $stmt = mysqli_prepare($conn, $sql . ' ORDER BY c.sort_order, m.name');
    mysqli_stmt_bind_param($stmt, 'i', $rid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

if (!$result) {
    echo json_encode([]);
    exit;
}

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}
if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
echo json_encode($items);
