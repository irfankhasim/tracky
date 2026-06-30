<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($page_title)) {
    $page_title = 'Superadmin';
}

$restaurant = getRestaurant($conn);
$restaurant_name = $restaurant['name'] ?? 'Restoran Tracky';
$current = basename($_SERVER['PHP_SELF']);

// Keep the displayed name in sync with the database (e.g. after a rename)
if (!empty($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    if ($ures = mysqli_query($conn, "SELECT name FROM users WHERE id = $uid LIMIT 1")) {
        if ($urow = mysqli_fetch_assoc($ures)) {
            $_SESSION['name'] = $urow['name'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title><?= e($page_title) ?> — Tracky Superadmin</title>
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
    <nav class="sidebar-nav">
      <div class="nav-label">Utama</div>
      <a href="/tracky/superadmin/superadmin_dashboard.php" class="nav-item <?= $current === 'superadmin_dashboard.php' ? 'active' : '' ?>">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
      <div class="nav-label">Pengurusan</div>
      <a href="/tracky/superadmin/superadmin_users.php" class="nav-item <?= $current === 'superadmin_users.php' ? 'active' : '' ?>">
        <i class="ti ti-users-group"></i> Pengguna
      </a>
      <a href="/tracky/superadmin/superadmin_stores.php" class="nav-item <?= $current === 'superadmin_stores.php' ? 'active' : '' ?>">
        <i class="ti ti-building-store"></i> Kedai
      </a>
      <div class="nav-label">Akaun</div>
      <a href="/tracky/superadmin/superadmin_profile.php" class="nav-item <?= $current === 'superadmin_profile.php' ? 'active' : '' ?>">
        <i class="ti ti-user"></i> Profil
      </a>
      <a href="/tracky/logout.php" class="nav-item" style="color:rgba(255,255,255,0.4)">
        <i class="ti ti-logout"></i> Log Keluar
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><i class="ti ti-shield-lock"></i></div>
        <div class="sidebar-user-name"><?= e($_SESSION['name'] ?? '') ?></div>
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
      </div>
    </div>
    <div class="content">
