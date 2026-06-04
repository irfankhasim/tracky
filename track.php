<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$order_no = trim($_GET['order_no'] ?? $_POST['order_no'] ?? '');
$order = null; $delivery = null; $items = [];
if ($order_no) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE order_no = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $order_no);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($order) {
        $oid = (int)$order['id'];
        $items = getOrderItems($conn, $oid);
        $dstmt = mysqli_prepare($conn, "SELECT d.*, u.name AS runner_name, r.phone AS runner_phone FROM deliveries d JOIN runners r ON r.id=d.runner_id JOIN users u ON u.id=r.user_id WHERE d.order_id=? ORDER BY d.id DESC LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'i', $oid);
        mysqli_stmt_execute($dstmt);
        $delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($dstmt));
        mysqli_stmt_close($dstmt);
    }
}
$steps = ['pending','assigned','picked_up','in_transit','delivered'];
$status = $order['status'] ?? '';
$map_addr = $order ? urlencode($order['delivery_address']) : '';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Order — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/customer.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-light bg-white border-bottom"><div class="container"><a class="navbar-brand fw-bold" href="/tracky/"><i class="ti ti-bike text-success"></i> Tracky</a></div></nav>
<div class="container py-4">
  <div class="row justify-content-center mb-4"><div class="col-md-6">
    <form method="get" class="input-group">
      <input type="text" name="order_no" class="form-control" placeholder="Masukkan nombor pesanan (ORD-...)" value="<?= e($order_no) ?>" required>
      <button class="btn btn-success">Cari</button>
    </form>
  </div></div>

  <?php if ($order_no && !$order): ?>
    <div class="alert alert-warning text-center">Pesanan tidak dijumpai. Sila semak nombor pesanan.</div>
  <?php elseif ($order): ?>
    <div class="track-banner <?= e($status) ?> mb-4">
      <h4 class="mb-1"><?= getStatusLabel($status) ?></h4>
      <p class="mb-0"><?= e($order['order_no']) ?> · <?= timeAgo($order['created_at']) ?></p>
    </div>
    <div class="track-layout">
      <div>
        <div class="order-summary-card">
          <h6 class="mb-3">Status Timeline</h6>
          <ul class="timeline">
            <?php if ($status === 'cancelled'): ?>
          <li class="done active"><strong><?= getStatusLabel('cancelled') ?></strong></li>
          <?php else:
            $statusIdx = array_search($status, $steps, true);
            if ($statusIdx === false) $statusIdx = -1;
            foreach ($steps as $s):
            if ($s === 'cancelled') continue;
            $done = $statusIdx >= array_search($s, $steps, true);
          ?>
          <li class="<?= $done?'done active':'' ?>">
            <strong><?= getStatusLabel($s) ?></strong>
          </li>
          <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>
      <div>
        <div class="order-summary-card mb-3">
          <h6>Butiran Pesanan</h6>
          <p class="mb-1"><strong><?= e($order['customer_name']) ?></strong></p>
          <p class="small mb-1"><?= e($order['customer_phone']) ?></p>
          <p class="small text-muted"><?= e($order['delivery_address']) ?></p>
          <hr>
          <?php foreach ($items as $i): ?><div class="small"><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div><?php endforeach; ?>
          <div class="fw-bold text-success mt-2"><?= formatPrice((float)$order['total_amount']) ?></div>
        </div>
        <?php if ($delivery): ?>
        <div class="order-summary-card mb-3">
          <h6>Runner</h6>
          <p class="mb-2"><strong><?= e($delivery['runner_name']) ?></strong></p>
          <a href="tel:<?= e(preg_replace('/\s+/','',$delivery['runner_phone'])) ?>" class="btn btn-outline-success btn-sm"><i class="ti ti-phone"></i> Hubungi Runner</a>
        </div>
        <?php endif; ?>
        <?php if ($map_addr): ?>
        <div class="map-container">
          <iframe loading="lazy" src="https://maps.google.com/maps?q=<?= $map_addr ?>&output=embed"></iframe>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php if ($order && $status !== 'delivered' && $status !== 'cancelled'): ?>
<script>setInterval(()=>location.reload(),15000);</script>
<?php endif; ?>
</body>
</html>
