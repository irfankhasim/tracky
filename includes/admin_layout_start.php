<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($page_title)) {
    $page_title = 'Admin';
}

$restaurant = getRestaurant($conn);
$restaurant_name = $restaurant['name'] ?? 'Restoran Tracky';
$pending_count = 0;
$pc = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'");
if ($pc) {
    $pending_count = (int) mysqli_fetch_assoc($pc)['c'];
}
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> — Tracky Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="wrapper d-flex">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <i class="ti ti-bike"></i>
      <span>Tracky</span>
    </div>
    <div class="sidebar-restaurant"><?= e($restaurant_name) ?></div>
    <nav class="sidebar-nav">
      <div class="nav-label">Utama</div>
      <a href="/tracky/admin/dashboard.php" class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
      <a href="/tracky/admin/orders.php" class="nav-item <?= $current === 'orders.php' ? 'active' : '' ?>">
        <i class="ti ti-shopping-bag"></i> Orders
        <?php if ($pending_count > 0): ?><span class="nav-badge"><?= $pending_count ?></span><?php endif; ?>
      </a>
      <a href="/tracky/admin/assign.php" class="nav-item <?= $current === 'assign.php' ? 'active' : '' ?>">
        <i class="ti ti-route"></i> Assign Runner
      </a>
      <a href="/tracky/admin/tracking.php" class="nav-item <?= $current === 'tracking.php' ? 'active' : '' ?>">
        <i class="ti ti-map-pin"></i> Tracking
      </a>
      <div class="nav-label">Pengurusan</div>
      <a href="/tracky/admin/runners.php" class="nav-item <?= $current === 'runners.php' ? 'active' : '' ?>">
        <i class="ti ti-users"></i> Runners
      </a>
      <a href="/tracky/admin/menu.php" class="nav-item <?= $current === 'menu.php' ? 'active' : '' ?>">
        <i class="ti ti-tools-kitchen-2"></i> Menu
      </a>
      <a href="/tracky/admin/reports.php" class="nav-item <?= $current === 'reports.php' ? 'active' : '' ?>">
        <i class="ti ti-chart-bar"></i> Laporan
      </a>
      <a href="/tracky/admin/notifications.php" class="nav-item <?= $current === 'notifications.php' ? 'active' : '' ?>">
        <i class="ti ti-bell"></i> Notifikasi
      </a>
      <div class="nav-label">Akaun</div>
      <a href="/tracky/admin/profile.php" class="nav-item <?= $current === 'profile.php' ? 'active' : '' ?>">
        <i class="ti ti-user"></i> Profil
      </a>
      <a href="/tracky/logout.php" class="nav-item text-danger">
        <i class="ti ti-logout"></i> Log Keluar
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar"><?= e(strtoupper(substr($_SESSION['name'] ?? 'AD', 0, 2))) ?></div>
        <div>
          <div class="user-name"><?= e($_SESSION['name'] ?? '') ?></div>
          <div class="user-role"><?= e(ucfirst($_SESSION['role'] ?? '')) ?></div>
        </div>
      </div>
    </div>
  </aside>
  <main class="main-content" id="main-content">
    <div class="topbar">
      <button class="hamburger" id="sidebarToggle" type="button"><i class="ti ti-menu-2"></i></button>
      <div class="topbar-title"><?= e($page_title) ?></div>
      <div class="topbar-actions">
        <a href="/tracky/admin/notifications.php" class="btn btn-light btn-sm position-relative">
          <i class="ti ti-bell"></i>
          <span class="notif-badge" id="notif-count"></span>
        </a>
      </div>
    </div>
    <div class="page-content">
