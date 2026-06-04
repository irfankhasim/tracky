<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$order_no = trim($_GET['order_no'] ?? $_SESSION['last_order']['order_no'] ?? '');
$order = null; $items = [];
if ($order_no) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE order_no = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $order_no);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($order) $items = getOrderItems($conn, (int)$order['id']);
}
$pm = $order['payment_method'] ?? ($_SESSION['last_order']['payment_method'] ?? 'cash');
$total = $order ? (float)$order['total_amount'] : (float)($_SESSION['last_order']['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pesanan Berjaya — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/customer.css" rel="stylesheet">
</head>
<body>
<div class="container py-5 success-page-wrap">
  <?php if (!$order_no): ?>
    <div class="text-center"><p class="text-muted">Pesanan tidak dijumpai.</p><a href="/tracky/" class="btn btn-success">Menu</a></div>
  <?php elseif (!$order): ?>
    <div class="text-center"><p class="text-muted">Nombor pesanan tidak sah atau tidak dijumpai.</p><a href="/tracky/track.php" class="btn btn-outline-secondary me-2">Semak Order</a><a href="/tracky/" class="btn btn-success">Menu</a></div>
  <?php else: ?>
  <div class="success-card text-center mb-4">
    <div class="success-icon"><i class="ti ti-check"></i></div>
    <h2 class="fw-bold">Pesanan Berjaya!</h2>
    <p class="text-muted">Anggaran masa: 30-45 minit</p>
    <div class="order-no-display"><?= e($order_no) ?></div>
  </div>
  <div class="success-card mb-3">
      <?php foreach ($items as $i): ?>
      <div class="d-flex justify-content-between mb-2"><span><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></span><span>RM <?= number_format($i['subtotal'],2) ?></span></div>
      <?php endforeach; ?>
      <hr>
      <div class="d-flex justify-content-between fw-bold"><span>Jumlah</span><span class="text-success">RM <?= number_format($total,2) ?></span></div>
    </div>
    <?php if ($pm === 'cash'): ?>
      <div class="alert alert-info payment-info-stack">Sila sediakan wang tunai kepada runner semasa penghantaran.</div>
    <?php else: ?>
      <div class="bank-details mb-3 payment-info-stack">
        <h6>Butiran Pindahan</h6>
        <p class="mb-1">Bank: <strong>Maybank</strong></p>
        <p class="mb-1">Akaun: <strong>1234567890</strong></p>
        <p class="mb-1">Nama: <strong>Tracky Food Delivery</strong></p>
        <p class="mb-1">Jumlah: <strong>RM <?= number_format($total,2) ?></strong></p>
        <p class="mb-0">Rujukan: <strong><?= e($order_no) ?></strong></p>
      </div>
    <?php endif; ?>
    <a href="/tracky/track.php?order_no=<?= urlencode($order_no) ?>" class="btn btn-success btn-lg w-100 mb-2">Track My Order</a>
    <a href="/tracky/" class="btn btn-outline-secondary w-100">Pesan Lagi</a>
  <?php endif; ?>
</div>
</body>
</html>
