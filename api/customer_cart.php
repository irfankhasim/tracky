<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_rid = custRestaurantId();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function cartResponse(): void
{
    global $conn;
    $items = array_values($_SESSION['cart']);
    $ids = array_filter(array_map('intval', array_column($items, 'id')));
    if ($ids) {
        $in = implode(',', $ids);
        $res = mysqli_query($conn, "SELECT id, image FROM menu_items WHERE id IN ($in)");
        $imgMap = [];
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $imgMap[(int) $row['id']] = $row['image'];
        }
        foreach ($items as &$it) {
            $it['image'] = $imgMap[(int) $it['id']] ?? ($it['image'] ?? null);
        }
        unset($it);
    }
    $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $_SESSION['cart']));
    echo json_encode([
        'success' => true,
        'total_items' => $total_items,
        'total' => round($total, 2),
        'items' => $items,
    ]);
}

switch ($action) {
    case 'add':
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        if ($item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Item tidak sah']);
            exit;
        }
        if ($cart_rid < 1) {
            echo json_encode(['success' => false, 'message' => 'Sila pilih restoran dahulu']);
            exit;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, name, price FROM menu_items WHERE id = ? AND is_available = 1 AND restaurant_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $item_id, $cart_rid);
        mysqli_stmt_execute($stmt);
        $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item tidak tersedia']);
            exit;
        }
        $name = $item['name'];
        $price = (float) $item['price'];
        if (isset($_SESSION['cart'][$item_id])) {
            $_SESSION['cart'][$item_id]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$item_id] = [
                'id' => $item_id,
                'name' => $name,
                'price' => $price,
                'quantity' => $qty,
            ];
        }
        break;
    case 'remove':
        $item_id = (int) ($_POST['item_id'] ?? 0);
        if ($item_id <= 0 || !isset($_SESSION['cart'][$item_id])) {
            echo json_encode(['success' => false, 'message' => 'Item tidak dijumpai']);
            exit;
        }
        unset($_SESSION['cart'][$item_id]);
        break;
    case 'update':
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 0);
        if ($item_id <= 0 || !isset($_SESSION['cart'][$item_id])) {
            echo json_encode(['success' => false, 'message' => 'Item tidak dijumpai']);
            exit;
        }
        if ($qty <= 0) {
            unset($_SESSION['cart'][$item_id]);
        } else {
            $_SESSION['cart'][$item_id]['quantity'] = $qty;
        }
        break;
    case 'clear':
        $_SESSION['cart'] = [];
        break;
    case 'get':
        cartResponse();
        exit;
    default:
        echo json_encode(['success' => false, 'message' => 'Tindakan tidak sah']);
        exit;
}

cartResponse();
