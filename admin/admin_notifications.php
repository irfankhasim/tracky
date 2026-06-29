<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Notifikasi';
$rid = activeRestaurantId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE restaurant_id = $rid");
    header('Location: /tracky/admin/admin_notifications.php'); exit;
}

$list = mysqli_query($conn, "SELECT * FROM notifications WHERE restaurant_id = $rid ORDER BY created_at DESC LIMIT 100");
require_once __DIR__ . '/../includes/admin_layout_start.php';

function notifIcon(string $type): array
{
    return match (strtolower($type)) {
        'order', 'new_order'      => ['ti-shopping-bag', 'blue'],
        'delivered', 'success'    => ['ti-circle-check', 'green'],
        'assign', 'assigned'      => ['ti-route', 'green'],
        'cancelled', 'error'      => ['ti-alert-circle', 'red'],
        'runner'                  => ['ti-bike', 'amber'],
        default                   => ['ti-bell', ''],
    };
}
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Notifikasi</h4>
    <p>Pemberitahuan sistem terkini</p>
  </div>
  <div class="page-header-right">
    <form method="post">
      <input type="hidden" name="action" value="mark_all">
      <button class="btn-icon"><i class="ti ti-checks"></i> Mark All Read</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Semua Notifikasi</div>
  <?php
  $notif_rows = [];
  while ($n = mysqli_fetch_assoc($list)) $notif_rows[] = $n;
  if (!$notif_rows): ?>
  <div class="notif-empty">
    <i class="ti ti-bell-off"></i>
    <p>Tiada notifikasi</p>
  </div>
  <?php else: foreach ($notif_rows as $n): [$icon, $color] = notifIcon($n['type']); ?>
  <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
    <div class="notif-icon <?= $color ?>"><i class="ti <?= $icon ?>"></i></div>
    <div class="notif-body">
      <div class="notif-title"><?= e($n['title']) ?></div>
      <div class="notif-text"><?= e($n['message']) ?></div>
      <div class="notif-meta"><?= timeAgo($n['created_at']) ?> <span class="notif-type"><?= e($n['type']) ?></span></div>
    </div>
    <?php if (!$n['is_read']): ?>
    <button class="btn-icon btn-mark" data-id="<?= (int)$n['id'] ?>"><i class="ti ti-check"></i> Read</button>
    <?php endif; ?>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php
$page_scripts = <<<'HTML'
<script>
document.querySelectorAll('.btn-mark').forEach(btn => btn.addEventListener('click', async () => {
  await apiPost('/tracky/api/admin_mark_notification.php', { notification_id: parseInt(btn.dataset.id) });
  location.reload();
}));
setInterval(() => location.reload(), 30000);
</script>
HTML;
require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
