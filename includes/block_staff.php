<?php
/*
 * Management-page guard. Include AFTER admin_auth.php on pages that are
 * restricted to admin/superadmin only (Menu, Runners, Reports, restaurant
 * settings). Staff have operational access only and are redirected away.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (($_SESSION['role'] ?? '') === 'staff') {
    header('Location: /tracky/admin/admin_dashboard.php');
    exit();
}
