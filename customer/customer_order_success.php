<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$order_no = trim($_GET['order_no'] ?? '');
if ($order_no === '') {
    header('Location: /tracky/');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE order_no = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $order_no);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: /tracky/');
    exit;
}

$items = getOrderItems($conn, (int) $order['id']);
$subtotal = (float) $order['subtotal'];
$delivery_fee = (float) $order['delivery_fee'];
$total = (float) $order['total_amount'];
$pm = $order['payment_method'];
$order_restaurant = getRestaurant($conn, (int) ($order['restaurant_id'] ?? 0));
$accent_color = $order_restaurant['accent_color'] ?? '#1D9E75';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Pesanan Berjaya — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="<?= asset('assets/css/customer.css') ?>" rel="stylesheet">
  <script>(function(){ if(localStorage.getItem('tracky-theme')==='light') document.documentElement.classList.add('pre-light'); })();</script>
  <style>html.pre-light body{background:#F8FAFC!important}</style>
  <style>:root{--green: <?= e($accent_color) ?>;}</style>
</head>
<body>
<nav class="customer-navbar">
  <a class="customer-brand" href="/tracky/" title="Laman Utama Tracky">
    <img src="/tracky/assets/img/icon.png" alt="Tracky" class="brand-img"> Tracky
  </a>
  <div class="customer-nav-actions">
    <a href="/tracky/" class="nav-icon-btn" title="Laman Utama"><i class="ti ti-home"></i></a>
    <a href="/tracky/customer/customer_restaurants.php" class="nav-icon-btn" title="Pesan lagi"><i class="ti ti-building-store"></i></a>
    <button class="customer-theme-btn" id="themeToggle" onclick="toggleTheme()"><i class="ti ti-sun" id="themeIcon"></i></button>
  </div>
</nav>
<div class="customer-page-wrap success-page-wrap" style="padding-top:24px;padding-bottom:40px">
  <div class="success-card">
    <div class="text-center mb-4">
      <div class="success-icon"><i class="ti ti-check"></i></div>
      <h2 class="fw-bold text-success mb-2">Pesanan Berjaya Dihantar!</h2>
      <p class="text-muted mb-0">Terima kasih! Pesanan anda sedang diproses.</p>
    </div>

    <div class="order-no-box">
      <div class="order-no-label">Nombor Pesanan Anda</div>
      <div class="order-no-value" id="orderNoValue"><?= e($order['order_no']) ?></div>
      <button type="button" class="copy-btn" onclick="copyOrderNo()">
        <i class="ti ti-copy"></i> Salin
      </button>
    </div>
    <p class="text-center text-muted small mb-4">Simpan nombor ini untuk semak status penghantaran</p>

    <h6 class="fw-semibold mb-3">Butiran Pesanan</h6>
    <div class="mb-3 small">
      <div class="mb-1"><strong>Nama:</strong> <?= e($order['customer_name']) ?></div>
      <div class="mb-1"><strong>Telefon:</strong> <?= e($order['customer_phone']) ?></div>
      <div class="mb-1"><strong>Alamat:</strong> <?= e($order['delivery_address']) ?></div>
      <?php if (!empty($order['notes'])): ?>
      <div class="mb-1"><strong>Nota:</strong> <?= e($order['notes']) ?></div>
      <?php endif; ?>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($items as $i): ?>
          <tr>
            <td><?= e($i['item_name']) ?></td>
            <td class="text-center"><?= (int) $i['quantity'] ?></td>
            <td class="text-end">RM <?= number_format((float) $i['unit_price'], 2) ?></td>
            <td class="text-end">RM <?= number_format((float) $i['subtotal'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="border-top pt-3 mb-4">
      <div class="d-flex justify-content-between"><span>Subtotal</span><span>RM <?= number_format($subtotal, 2) ?></span></div>
      <div class="d-flex justify-content-between mt-1"><span>Penghantaran</span><span><?= $delivery_fee > 0 ? 'RM ' . number_format($delivery_fee, 2) : 'PERCUMA' ?></span></div>
      <div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>JUMLAH</span><span class="text-success">RM <?= number_format($total, 2) ?></span></div>
    </div>

    <?php if ($pm === 'cash'): ?>
    <div class="payment-info cash">
      <i class="ti ti-cash" style="color:#1D9E75;font-size:32px"></i>
      <h6>Pembayaran Tunai (COD)</h6>
      <p class="mb-0">Sila sediakan wang tunai sebanyak <strong>RM <?= number_format($total, 2) ?></strong> untuk dibayar kepada runner semasa penghantaran.</p>
    </div>
    <?php elseif ($order['payment_status'] === 'paid'): ?>
    <div class="payment-info online">
      <i class="ti ti-circle-check-filled" style="color:#1D9E75;font-size:32px"></i>
      <h6>Pembayaran Berjaya</h6>
      <table class="bank-details">
        <tr><td>Status</td><td><strong style="color:#1D9E75">DIBAYAR</strong></td></tr>
        <tr><td>Jumlah</td><td><strong style="color:#1D9E75">RM <?= number_format($total, 2) ?></strong></td></tr>
        <?php if (!empty($order['payment_ref'])): ?>
        <tr><td>No. Rujukan</td><td><strong><?= e($order['payment_ref']) ?></strong></td></tr>
        <?php endif; ?>
        <?php if (!empty($order['paid_at'])): ?>
        <tr><td>Masa Bayaran</td><td><strong><?= e(date('d M Y, h:i A', strtotime($order['paid_at']))) ?></strong></td></tr>
        <?php endif; ?>
      </table>
      <p class="mt-2 text-muted small mb-0"><i class="ti ti-shield-check"></i> Dibayar melalui TrackyPay (simulasi).</p>
    </div>
    <?php else: ?>
    <div class="payment-info online">
      <i class="ti ti-clock-dollar" style="color:#F59E0B;font-size:32px"></i>
      <h6>Pembayaran Belum Selesai</h6>
      <p class="mb-3">Pesanan anda belum dibayar. Sila selesaikan pembayaran sebanyak <strong>RM <?= number_format($total, 2) ?></strong> untuk diproses.</p>
      <a href="/tracky/customer/customer_payment.php?order_no=<?= urlencode($order['order_no']) ?>" class="btn-tracky" style="margin:0">
        <i class="ti ti-lock"></i> Bayar Sekarang
      </a>
    </div>
    <?php endif; ?>

    <div class="next-steps">
      <div class="step">
        <div class="step-num">1</div>
        <div>Pesanan anda sedang disemak oleh restoran</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div>Runner akan di-assign untuk menghantar pesanan anda</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div>Anggaran masa penghantaran: 30-45 minit</div>
      </div>
    </div>

    <a href="/tracky/customer/customer_track.php?order_no=<?= urlencode($order['order_no']) ?>" class="btn-tracky mb-3">
      <i class="ti ti-map-pin"></i> Semak Status Penghantaran
    </a>
    <a href="/tracky/customer/customer_menu.php?restaurant=<?= (int)($order['restaurant_id'] ?? 0) ?>" class="btn-tracky-outline">
      <i class="ti ti-arrow-left"></i> Kembali ke Menu
    </a>
  </div>
</div>
<script>
function copyOrderNo() {
  const text = document.getElementById('orderNoValue').textContent.trim();
  navigator.clipboard.writeText(text).then(() => {
    const btn = document.querySelector('.copy-btn');
    btn.innerHTML = '<i class="ti ti-check"></i> Disalin!';
    btn.style.background = '#1D9E75';
    btn.style.color = 'white';
    setTimeout(() => {
      btn.innerHTML = '<i class="ti ti-copy"></i> Salin';
      btn.style.background = '';
      btn.style.color = '';
    }, 2000);
  });
}
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
