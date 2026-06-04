<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Troli kosong']);
    exit;
}

$required = ['customer_name', 'customer_phone', 'delivery_address', 'payment_method'];
foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Sila isi semua maklumat']);
        exit;
    }
}

$customer_name = trim($_POST['customer_name']);
$customer_phone = trim($_POST['customer_phone']);
$delivery_address = trim($_POST['delivery_address']);
$payment_method = in_array($_POST['payment_method'], ['cash', 'online'], true) ? $_POST['payment_method'] : 'cash';
$notes = trim($_POST['notes'] ?? '');

if (!preg_match('/^(\+?6?01)[0-9\-]{8,10}$/', preg_replace('/\s+/', '', $customer_phone))) {
    echo json_encode(['success' => false, 'message' => 'Format telefon tidak sah']);
    exit;
}

$validatedCart = [];
foreach ($_SESSION['cart'] as $item_id => $item) {
    $id = (int) $item_id;
    $stmt = mysqli_prepare($conn, 'SELECT id, name, price, is_available FROM menu_items WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $dbItem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$dbItem || !(int) $dbItem['is_available']) {
        echo json_encode(['success' => false, 'message' => 'Item "' . ($item['name'] ?? '') . '" tidak lagi tersedia']);
        exit;
    }
    $qty = max(1, (int) ($item['quantity'] ?? 1));
    $price = (float) $dbItem['price'];
    $validatedCart[] = [
        'id' => $id,
        'name' => $dbItem['name'],
        'price' => $price,
        'quantity' => $qty,
    ];
}

$restaurant = getRestaurant($conn);
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $validatedCart));
$delivery_fee = deliveryFeeForSubtotal($restaurant ?: [], $subtotal);
$total = $subtotal + $delivery_fee;
$order_no = generateOrderNo($conn);

mysqli_begin_transaction($conn);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO orders (order_no, customer_name, customer_phone, delivery_address, subtotal, delivery_fee, total_amount, payment_method, payment_status, status, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)"
);
mysqli_stmt_bind_param(
    $stmt, 'ssssdddss',
    $order_no, $customer_name, $customer_phone, $delivery_address,
    $subtotal, $delivery_fee, $total, $payment_method, $notes
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesanan']);
    exit;
}
$order_id = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

$item_stmt = mysqli_prepare($conn, 'INSERT INTO order_items (order_id, item_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
foreach ($validatedCart as $item) {
    $qty = (int) $item['quantity'];
    $price = (float) $item['price'];
    $line = $price * $qty;
    $name = $item['name'];
    mysqli_stmt_bind_param($item_stmt, 'isidd', $order_id, $name, $qty, $price, $line);
    if (!mysqli_stmt_execute($item_stmt)) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan item pesanan']);
        exit;
    }
}
mysqli_stmt_close($item_stmt);

addNotification($conn, 'Order Baru!', "Order baru dari $customer_name — $order_no", 'order');
logStatusChange($conn, $order_id, null, 'pending', null);
mysqli_commit($conn);

$_SESSION['last_order'] = [
    'order_no' => $order_no,
    'payment_method' => $payment_method,
    'total' => $total,
    'subtotal' => $subtotal,
    'delivery_fee' => $delivery_fee,
];
$_SESSION['cart'] = [];

echo json_encode([
    'success' => true,
    'order_no' => $order_no,
    'order_id' => $order_id,
    'total' => number_format($total, 2, '.', ''),
    'payment_method' => $payment_method,
]);
