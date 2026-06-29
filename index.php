<?php
session_start();
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role'] ?? '') {
        case 'admin':
            header('Location: /tracky/admin/admin_dashboard.php');
            exit();
        case 'staff':
            header('Location: /tracky/staff/staff_dashboard.php');
            exit();
        case 'runner':
            header('Location: /tracky/runner/runner_orders.php');
            exit();
        default:
            session_destroy();
    }
}
include __DIR__ . '/landing/index.html';
