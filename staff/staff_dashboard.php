<?php
require_once __DIR__ . '/../includes/staff_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Dashboard';
$rid = activeRestaurantId();

$unread = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM notifications WHERE restaurant_id=$rid AND is_read=0"))['c'];
$today_notif = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM notifications WHERE restaurant_id=$rid AND DATE(created_at)=CURDATE()"))['c'];

$recent = mysqli_query($conn, "SELECT * FROM notifications WHERE restaurant_id=$rid ORDER BY created_at DESC LIMIT 6");
$recent_rows = [];
while ($recent && $n = mysqli_fetch_assoc($recent)) {
    $recent_rows[] = $n;
}

function staffNotifIcon(string $type): array
{
    return match (strtolower($type)) {
        'order', 'new_order'   => ['ti-shopping-bag', 'blue'],
        'delivered', 'success' => ['ti-circle-check', 'green'],
        'assign', 'assigned'   => ['ti-route', 'green'],
        'cancelled', 'error'   => ['ti-alert-circle', 'red'],
        'runner'               => ['ti-bike', 'amber'],
        default                => ['ti-bell', ''],
    };
}

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 19 ? 'Selamat petang' : 'Selamat malam');

require_once __DIR__ . '/../includes/staff_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4><?= $greeting ?>, <?= e($_SESSION['name'] ?? 'Staf') ?> 👋</h4>
    <p>Selamat datang ke panel staf <?= e($restaurant_name) ?></p>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon amber"><i class="ti ti-bell-ringing"></i></div>
      <div>
        <div class="stat-label">Notifikasi Belum Dibaca</div>
        <div class="stat-value"><?= $unread ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-calendar-event"></i></div>
      <div>
        <div class="stat-label">Notifikasi Hari Ini</div>
        <div class="stat-value"><?= $today_notif ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-building-store"></i></div>
      <div>
        <div class="stat-label">Restoran</div>
        <div class="stat-value" style="font-size:1.05rem;line-height:1.3"><?= e($restaurant_name) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Notifikasi Terkini</span>
        <a href="/tracky/staff/staff_notifications.php" class="btn-icon" style="font-size:11px">Lihat Semua <i class="ti ti-arrow-right"></i></a>
      </div>
      <div class="card-body p-0">
        <?php if (!$recent_rows): ?>
        <div class="notif-empty">
          <i class="ti ti-bell-off"></i>
          <p>Tiada notifikasi</p>
        </div>
        <?php else: foreach ($recent_rows as $n): [$icon, $color] = staffNotifIcon($n['type']); ?>
        <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
          <div class="notif-icon <?= $color ?>"><i class="ti <?= $icon ?>"></i></div>
          <div class="notif-body">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <div class="notif-text"><?= e($n['message']) ?></div>
            <div class="notif-meta"><?= timeAgo($n['created_at']) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Akses Pantas</div>
      <div class="card-body d-flex flex-column gap-2">
        <a href="/tracky/staff/staff_notifications.php" class="btn-icon" style="justify-content:flex-start"><i class="ti ti-bell"></i> Lihat Notifikasi</a>
        <a href="/tracky/staff/staff_profile.php" class="btn-icon" style="justify-content:flex-start"><i class="ti ti-user"></i> Tetapan Akaun</a>
        <a href="/tracky/logout.php" class="btn-icon danger" style="justify-content:flex-start"><i class="ti ti-logout"></i> Log Keluar</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/staff_layout_end.php'; ?>
