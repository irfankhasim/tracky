<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$item_id = (int) ($_POST['item_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$category_id = (int) ($_POST['category_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$price = (float) ($_POST['price'] ?? 0);
$is_available = (int) ($_POST['is_available'] ?? 1);
$is_available = $is_available ? 1 : 0;

if ($item_id < 1 || $name === '' || $category_id < 1 || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sila isi semua maklumat yang diperlukan']);
    exit;
}

$catCheck = mysqli_prepare($conn, 'SELECT id FROM categories WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($catCheck, 'i', $category_id);
mysqli_stmt_execute($catCheck);
$cat = mysqli_fetch_assoc(mysqli_stmt_get_result($catCheck));
mysqli_stmt_close($catCheck);

if (!$cat) {
    echo json_encode(['success' => false, 'message' => 'Kategori tidak sah']);
    exit;
}

$stmt = mysqli_prepare($conn, 'UPDATE menu_items SET name = ?, category_id = ?, description = ?, price = ?, is_available = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'sisdii', $name, $category_id, $description, $price, $is_available, $item_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    echo json_encode(['success' => true, 'message' => 'Item menu berjaya dikemaskini']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal kemaskini item']);
}
