<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function cartResponse(): void
{
    $total_items = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $total = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $_SESSION['cart']));
    echo json_encode([
        'success' => true,
        'total_items' => $total_items,
        'total' => round($total, 2),
        'items' => array_values($_SESSION['cart']),
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
        $stmt = mysqli_prepare($conn, 'SELECT id, name, price FROM menu_items WHERE id = ? AND is_available = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $item_id);
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
