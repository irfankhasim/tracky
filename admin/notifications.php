<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Notifikasi';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
    mysqli_query($conn, 'UPDATE notifications SET is_read = 1');
    header('Location: /tracky/admin/notifications.php'); exit;
}

$list = mysqli_query($conn, 'SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100');
require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">Senarai Notifikasi</h5>
  <form method="post"><input type="hidden" name="action" value="mark_all"><button class="btn btn-outline-secondary btn-sm w-100 w-sm-auto">Mark All Read</button></form>
</div>
<div class="card"><div class="list-group list-group-flush" id="notif-list">
<?php while ($n = mysqli_fetch_assoc($list)): ?>
  <div class="list-group-item d-flex gap-3 align-items-start <?= $n['is_read']?'':'bg-light' ?>">
    <?php if (!$n['is_read']): ?><span class="rounded-circle bg-success" style="width:8px;height:8px;margin-top:6px"></span><?php else: ?><span style="width:8px"></span><?php endif; ?>
    <div class="flex-grow-1">
      <div class="fw-semibold"><?= e($n['title']) ?></div>
      <div class="small text-muted"><?= e($n['message']) ?></div>
      <div class="small text-muted"><?= timeAgo($n['created_at']) ?> · <?= e($n['type']) ?></div>
    </div>
    <?php if (!$n['is_read']): ?>
    <button class="btn btn-sm btn-outline-secondary btn-mark" data-id="<?= (int)$n['id'] ?>">Read</button>
    <?php endif; ?>
  </div>
<?php endwhile; ?>
</div></div>
<script>
document.querySelectorAll('.btn-mark').forEach(btn => btn.addEventListener('click', async () => {
  await apiPost('/tracky/api/mark_notification.php', { notification_id: parseInt(btn.dataset.id) });
  location.reload();
}));
setInterval(() => location.reload(), 30000);
</script>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
