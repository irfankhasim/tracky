<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$required = ['customer_name', 'customer_phone', 'delivery_address', 'payment_method'];
foreach ($required as $field) {
    if (empty(trim($_POST[$field] ?? ''))) {
        echo json_encode(['success' => false, 'message' => 'Sila lengkapkan semua maklumat yang diperlukan']);
        exit;
    }
}

$cust_rid = custRestaurantId();
if ($cust_rid < 1) {
    echo json_encode(['success' => false, 'message' => 'Sila pilih restoran dahulu']);
    exit;
}

$customer_name = trim($_POST['customer_name']);
$customer_phone = preg_replace('/[-\s]/', '', trim($_POST['customer_phone']));
$delivery_address = trim($_POST['delivery_address']);
$notes = trim($_POST['notes'] ?? '');
$payment_method = in_array($_POST['payment_method'] ?? '', ['cash', 'online'], true)
    ? $_POST['payment_method']
    : 'cash';

if (strlen($customer_name) < 3) {
    echo json_encode(['success' => false, 'message' => 'Nama mesti sekurang-kurangnya 3 aksara']);
    exit;
}

if (strlen($delivery_address) < 10) {
    echo json_encode(['success' => false, 'message' => 'Sila masukkan alamat lengkap (minimum 10 aksara)']);
    exit;
}

if (!preg_match('/^(01)[0-9]{8,9}$/', $customer_phone)) {
    echo json_encode(['success' => false, 'message' => 'Format nombor telefon tidak sah']);
    exit;
}

$validatedCart = [];
$postedItems = json_decode($_POST['items'] ?? '[]', true);

if (is_array($postedItems) && !empty($postedItems)) {
    foreach ($postedItems as $item) {
        $id = (int) ($item['id'] ?? 0);
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        if ($id < 1) {
            continue;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, name, price, is_available FROM menu_items WHERE id = ? AND restaurant_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $cust_rid);
        mysqli_stmt_execute($stmt);
        $dbItem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$dbItem || !(int) $dbItem['is_available']) {
            $label = $dbItem['name'] ?? ($item['name'] ?? 'Item');
            echo json_encode(['success' => false, 'message' => 'Item "' . $label . '" tidak lagi tersedia']);
            exit;
        }
        $validatedCart[] = [
            'id' => $id,
            'name' => $dbItem['name'],
            'price' => (float) $dbItem['price'],
            'quantity' => $qty,
        ];
    }
}

if (empty($validatedCart)) {
    if (empty($_SESSION['cart'])) {
        echo json_encode(['success' => false, 'message' => 'Troli anda kosong']);
        exit;
    }
    foreach ($_SESSION['cart'] as $item_id => $item) {
        $id = (int) $item_id;
        $stmt = mysqli_prepare($conn, 'SELECT id, name, price, is_available FROM menu_items WHERE id = ? AND restaurant_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $cust_rid);
        mysqli_stmt_execute($stmt);
        $dbItem = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$dbItem || !(int) $dbItem['is_available']) {
            echo json_encode(['success' => false, 'message' => 'Item "' . ($item['name'] ?? '') . '" tidak lagi tersedia']);
            exit;
        }
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $validatedCart[] = [
            'id' => $id,
            'name' => $dbItem['name'],
            'price' => (float) $dbItem['price'],
            'quantity' => $qty,
        ];
    }
}

if (empty($validatedCart)) {
    echo json_encode(['success' => false, 'message' => 'Troli anda kosong']);
    exit;
}

$restaurant = getRestaurant($conn, $cust_rid);
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $validatedCart));
$delivery_fee = deliveryFeeForSubtotal($restaurant ?: [], $subtotal);
$total = $subtotal + $delivery_fee;
$order_no = generateOrderNo($conn);

mysqli_begin_transaction($conn);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO orders (restaurant_id, order_no, customer_name, customer_phone, delivery_address, subtotal, delivery_fee, total_amount, payment_method, payment_status, status, notes)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)"
);
mysqli_stmt_bind_param(
    $stmt,
    'issssdddss',
    $cust_rid,
    $order_no,
    $customer_name,
    $customer_phone,
    $delivery_address,
    $subtotal,
    $delivery_fee,
    $total,
    $payment_method,
    $notes
);

if (!mysqli_stmt_execute($stmt)) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal membuat pesanan']);
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

$notifTitle = 'Order Baru!';
$notifMsg = "Order $order_no daripada $customer_name — RM " . number_format($total, 2);
addNotification($conn, $notifTitle, $notifMsg, 'order', $cust_rid);
logStatusChange($conn, $order_id, null, 'pending', null);
mysqli_commit($conn);

$_SESSION['cart'] = [];

echo json_encode([
    'success' => true,
    'order_no' => $order_no,
    'order_id' => $order_id,
    'total' => number_format($total, 2, '.', ''),
    'payment_method' => $payment_method,
]);
