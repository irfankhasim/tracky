<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Tracking';

$order_id = (int)($_GET['order_id'] ?? 0);
$orders_list = mysqli_query($conn, "SELECT id, order_no, customer_name, status FROM orders ORDER BY created_at DESC LIMIT 50");
if ($order_id < 1) {
    $first = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM orders WHERE status NOT IN ('delivered','cancelled') ORDER BY created_at DESC LIMIT 1"));
    $order_id = (int)($first['id'] ?? 0);
}

$order = null; $delivery = null; $items = []; $logs = [];
if ($order_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($order) {
        $items = getOrderItems($conn, $order_id);
        $dstmt = mysqli_prepare($conn, "SELECT d.*, u.name runner_name, r.phone runner_phone, r.vehicle_no FROM deliveries d JOIN runners r ON r.id=d.runner_id JOIN users u ON u.id=r.user_id WHERE d.order_id=? ORDER BY d.id DESC LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'i', $order_id);
        mysqli_stmt_execute($dstmt);
        $delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($dstmt));
        mysqli_stmt_close($dstmt);
        $lstmt = mysqli_prepare($conn, 'SELECT * FROM status_logs WHERE order_id=? ORDER BY changed_at ASC');
        mysqli_stmt_bind_param($lstmt, 'i', $order_id);
        mysqli_stmt_execute($lstmt);
        $lres = mysqli_stmt_get_result($lstmt);
        while ($l = mysqli_fetch_assoc($lres)) $logs[] = $l;
        mysqli_stmt_close($lstmt);
    }
}
$steps = ['pending','assigned','picked_up','in_transit','delivered'];
$map = $order ? urlencode($order['delivery_address']) : '';

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="mb-3">
  <select class="form-select w-100" id="order-select" style="max-width:100%">
    <?php mysqli_data_seek($orders_list, 0); while ($ol = mysqli_fetch_assoc($orders_list)): ?>
    <option value="<?= (int)$ol['id'] ?>" <?= $ol['id']==$order_id?'selected':'' ?>><?= e($ol['order_no']) ?> — <?= e($ol['customer_name']) ?> (<?= e($ol['status']) ?>)</option>
    <?php endwhile; ?>
  </select>
</div>
<?php if (!$order): ?>
<p class="text-muted">Tiada order untuk dipaparkan.</p>
<?php else: ?>
<div class="two-col-layout" id="tracking-panel">
  <div>
    <div class="card"><div class="card-header">Timeline — <?= e($order['order_no']) ?></div><div class="card-body">
      <ul class="timeline">
        <?php if ($order['status'] === 'cancelled'): ?>
        <li class="done active"><strong><?= getStatusLabel('cancelled') ?></strong></li>
        <?php else:
          $statusIdx = array_search($order['status'], $steps, true);
          if ($statusIdx === false) $statusIdx = -1;
          foreach ($steps as $s):
          $done = $statusIdx >= array_search($s, $steps, true);
        ?>
        <li class="<?= $done ? 'done active' : '' ?>"><strong><?= getStatusLabel($s) ?></strong>
          <?php foreach ($logs as $log): if ($log['new_status'] === $s): ?><div class="small text-muted"><?= date('d/m/Y H:i', strtotime($log['changed_at'])) ?></div><?php endif; endforeach; ?>
        </li>
        <?php endforeach; endif; ?>
      </ul>
    </div></div>
  </div>
  <div>
    <div class="card mb-3"><div class="card-header">Order Details</div><div class="card-body">
      <p><strong><?= e($order['customer_name']) ?></strong><br><?= e($order['customer_phone']) ?><br><span class="text-muted"><?= e($order['delivery_address']) ?></span></p>
      <?php foreach ($items as $i): ?><div class="small"><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?> — <?= formatPrice((float)$i['subtotal']) ?></div><?php endforeach; ?>
      <div class="fw-bold mt-2"><?= formatPrice((float)$order['total_amount']) ?> · <?= getStatusBadge($order['status']) ?></div>
      <?php if (in_array($order['status'], ['assigned','picked_up','in_transit'], true)): ?>
      <button type="button" class="btn btn-success btn-sm mt-3" id="btn-mark-delivered"><i class="ti ti-check"></i> Mark as Delivered</button>
      <?php endif; ?>
    </div></div>
    <?php if ($delivery): ?>
    <div class="card mb-3"><div class="card-header">Runner</div><div class="card-body">
      <p class="mb-2"><strong><?= e($delivery['runner_name']) ?></strong><br><?= e($delivery['runner_phone']) ?> · <?= e($delivery['vehicle_no']) ?></p>
      <a href="tel:<?= e(preg_replace('/\s+/','',$delivery['runner_phone'])) ?>" class="btn btn-outline-success btn-sm"><i class="ti ti-phone"></i> Call</a>
    </div></div>
    <?php endif; ?>
    <?php if ($map): ?><div class="map-container"><iframe src="https://maps.google.com/maps?q=<?= $map ?>&output=embed"></iframe></div><?php endif; ?>
  </div>
</div>
<script>
document.getElementById('order-select').onchange = e => location.href = '?order_id=' + e.target.value;
const markBtn = document.getElementById('btn-mark-delivered');
if (markBtn) {
  markBtn.onclick = async () => {
    if (!confirm('Tandakan order ini sebagai dihantar?')) return;
    markBtn.disabled = true;
    const res = await apiPost('/tracky/api/admin_mark_delivered.php', { order_id: <?= (int)$order_id ?> });
    if (res.success) { showToast(res.message); setTimeout(() => location.reload(), 600); }
    else { showToast(res.message || 'Gagal', 'danger'); markBtn.disabled = false; }
  };
}
setInterval(async () => {
  const id = document.getElementById('order-select').value;
  const r = await fetch('/tracky/api/get_tracking.php?order_id=' + id);
  const d = await r.json();
  if (d.success && !['delivered','cancelled'].includes(d.order.status)) location.reload();
}, 10000);
</script>
<?php endif; require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
