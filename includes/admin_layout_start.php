<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($page_title)) {
    $page_title = 'Admin';
}

$active_rid = activeRestaurantId();
$restaurant = getRestaurant($conn, $active_rid);
$restaurant_name = $restaurant['name'] ?? 'Restoran Tracky';
$restaurant_logo = restaurantAsset($restaurant['logo'] ?? null);
$pending_count = 0;
$pc = mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE status = 'pending' AND restaurant_id = $active_rid");
if ($pc) {
    $pending_count = (int) mysqli_fetch_assoc($pc)['c'];
}
$is_superadmin = ($_SESSION['role'] ?? '') === 'superadmin';
$is_staff = ($_SESSION['role'] ?? '') === 'staff';
$all_restaurants = [];
if ($is_superadmin) {
    $rrs = mysqli_query($conn, 'SELECT id, name FROM restaurants ORDER BY name ASC');
    while ($rrs && $rr = mysqli_fetch_assoc($rrs)) {
        $all_restaurants[] = $rr;
    }
}
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title><?= e($page_title) ?> — Tracky Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="<?= asset('assets/css/admin.css') ?>" rel="stylesheet">
  <script>(function(){ if(localStorage.getItem('tracky-theme')==='light') document.documentElement.classList.add('pre-light'); })();</script>
  <style>html.pre-light body { background: #F1F5F9 !important; }</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="wrapper d-flex">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <img src="/tracky/assets/img/icon.png" alt="Tracky" class="brand-img">
      <span>Tracky</span>
    </div>
    <?php if ($is_superadmin): ?>
    <div class="sidebar-restaurant sa-switcher">
      <label>Mengurus Restoran</label>
      <select onchange="location.href='/tracky/admin/admin_dashboard.php?manage_restaurant='+this.value">
        <?php foreach ($all_restaurants as $rr): ?>
        <option value="<?= (int)$rr['id'] ?>" <?= (int)$rr['id'] === $active_rid ? 'selected' : '' ?>><?= e($rr['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php else: ?>
    <div class="sidebar-restaurant">
      <?php if ($restaurant_logo): ?><img src="<?= e($restaurant_logo) ?>" alt="" style="width:18px;height:18px;border-radius:4px;object-fit:cover;margin-right:6px;vertical-align:middle"><?php endif; ?><?= e($restaurant_name) ?>
    </div>
    <?php endif; ?>
    <nav class="sidebar-nav">
      <div class="nav-label">Utama</div>
      <a href="/tracky/admin/admin_dashboard.php" class="nav-item <?= $current === 'admin_dashboard.php' ? 'active' : '' ?>">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
      <a href="/tracky/admin/admin_orders.php" class="nav-item <?= $current === 'admin_orders.php' ? 'active' : '' ?>">
        <i class="ti ti-shopping-bag"></i> Order
        <?php if ($pending_count > 0): ?><span style="background:var(--primary);color:white;border-radius:100px;font-size:10px;font-weight:800;padding:2px 7px;margin-left:4px"><?= $pending_count ?></span><?php endif; ?>
      </a>
      <a href="/tracky/admin/admin_assign.php" class="nav-item <?= $current === 'admin_assign.php' ? 'active' : '' ?>">
        <i class="ti ti-route"></i> Assign
      </a>
      <a href="/tracky/admin/admin_tracking.php" class="nav-item <?= $current === 'admin_tracking.php' ? 'active' : '' ?>">
        <i class="ti ti-map-pin"></i> Tracking
      </a>
      <?php if (!$is_staff): ?>
      <div class="nav-label">Pengurusan</div>
      <a href="/tracky/admin/admin_runners.php" class="nav-item <?= $current === 'admin_runners.php' ? 'active' : '' ?>">
        <i class="ti ti-users"></i> Runners
      </a>
      <a href="/tracky/admin/admin_menu.php" class="nav-item <?= $current === 'admin_menu.php' ? 'active' : '' ?>">
        <i class="ti ti-tools-kitchen-2"></i> Menu
      </a>
      <a href="/tracky/admin/admin_reports.php" class="nav-item <?= $current === 'admin_reports.php' ? 'active' : '' ?>">
        <i class="ti ti-chart-bar"></i> Laporan
      </a>
      <?php endif; ?>
      <div class="nav-label">Akaun</div>
      <?php if (($_SESSION['role'] ?? '') === 'superadmin'): ?>
      <a href="/tracky/superadmin/superadmin_dashboard.php" class="nav-item">
        <i class="ti ti-shield-lock"></i> Panel Superadmin
      </a>
      <?php endif; ?>
      <a href="/tracky/admin/admin_profile.php" class="nav-item <?= $current === 'admin_profile.php' ? 'active' : '' ?>">
        <i class="ti ti-user"></i> Profil
      </a>
      <a href="/tracky/logout.php" class="nav-item" style="color:rgba(255,255,255,0.4)">
        <i class="ti ti-logout"></i> Log Keluar
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><i class="ti ti-user-circle"></i></div>
        <div>
          <div class="sidebar-user-name"><?= e($_SESSION['name'] ?? '') ?></div>
        </div>
      </div>
    </div>
  </aside>
  <main class="main" id="main-content">
    <div class="topbar">
      <button class="hamburger" id="sidebarToggle" type="button"><i class="ti ti-menu-2"></i></button>
      <div class="topbar-actions">
        <button class="theme-toggle-btn" id="themeToggle" onclick="toggleTheme()">
          <i class="ti ti-sun" id="themeIcon"></i>
        </button>
        <a href="/tracky/admin/admin_notifications.php" class="notif-btn" title="Notifikasi">
          <i class="ti ti-bell"></i>
          <span id="notif-count"></span>
        </a>
      </div>
    </div>
    <div class="content">
