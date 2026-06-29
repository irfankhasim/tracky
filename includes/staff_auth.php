<?php
// Access guard for the dedicated Staff interface.
// Only users with the 'staff' role and a restaurant assignment may enter.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'staff') {
    // Route other known roles to their own home; everyone else to login.
    switch ($_SESSION['role'] ?? '') {
        case 'superadmin':
            header('Location: /tracky/superadmin/superadmin_dashboard.php');
            break;
        case 'admin':
            header('Location: /tracky/admin/admin_dashboard.php');
            break;
        case 'runner':
            header('Location: /tracky/runner/runner_orders.php');
            break;
        default:
            header('Location: /tracky/login.php');
    }
    exit();
}

require_once __DIR__ . '/db.php';

// Staff must belong to a restaurant.
if (empty($_SESSION['restaurant_id'])) {
    session_destroy();
    header('Location: /tracky/login.php');
    exit();
}
