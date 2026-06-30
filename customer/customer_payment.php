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
// Cash orders or already-paid orders go straight to the receipt.
if ($order['payment_method'] !== 'online' || $order['payment_status'] === 'paid') {
    header('Location: /tracky/customer/customer_order_success.php?order_no=' . urlencode($order['order_no']));
    exit;
}

$restaurant   = getRestaurant($conn, (int) ($order['restaurant_id'] ?? 0));
$accent_color = $restaurant['accent_color'] ?? '#1D9E75';
$merchant     = $restaurant['name'] ?? 'Tracky';
$total        = (float) $order['total_amount'];

$banks = ['Maybank2u', 'CIMB Clicks', 'Public Bank', 'RHB Now', 'Bank Islam', 'Hong Leong Connect', 'AmBank', 'Bank Rakyat', 'BSN', 'Affin Bank'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <title>TrackyPay — Pembayaran Selamat</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="<?= asset('assets/css/customer.css') ?>" rel="stylesheet">
  <script>(function(){ if(localStorage.getItem('tracky-theme')==='light') document.documentElement.classList.add('pre-light'); })();</script>
  <style>html.pre-light body{background:#F8FAFC!important}</style>
  <style>:root{--green: <?= e($accent_color) ?>;}</style>
</head>
<body>

<div class="paygw-wrap">
  <div class="paygw-card">

    <div class="paygw-head">
      <div class="paygw-brand"><i class="ti ti-shield-lock"></i> TrackyPay</div>
      <div class="paygw-secure"><i class="ti ti-lock"></i> Pembayaran selamat</div>
    </div>

    <div class="paygw-amount">
      <div class="paygw-amount-label">Jumlah perlu dibayar</div>
      <div class="paygw-amount-value">RM <?= number_format($total, 2) ?></div>
      <div class="paygw-merchant">kepada <strong><?= e($merchant) ?></strong> · Ruj: <?= e($order['order_no']) ?></div>
    </div>

    <div class="paygw-tabs">
      <button type="button" class="paygw-tab active" data-tab="fpx"><i class="ti ti-building-bank"></i> Online Banking (FPX)</button>
      <button type="button" class="paygw-tab" data-tab="card"><i class="ti ti-credit-card"></i> Kad Kredit / Debit</button>
    </div>

    <!-- FPX -->
    <div class="paygw-panel" id="panel-fpx">
      <label class="form-label" for="bank">Pilih Bank</label>
      <select id="bank" class="form-select">
        <option value="">— Pilih bank anda —</option>
        <?php foreach ($banks as $b): ?>
        <option value="<?= e($b) ?>"><?= e($b) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Card -->
    <div class="paygw-panel d-none" id="panel-card">
      <label class="form-label" for="card_no">Nombor Kad</label>
      <input type="text" id="card_no" class="form-control" inputmode="numeric" maxlength="19" placeholder="4242 4242 4242 4242">
      <div class="row g-2 mt-1">
        <div class="col-7">
          <label class="form-label" for="card_exp">Tarikh Luput</label>
          <input type="text" id="card_exp" class="form-control" maxlength="5" placeholder="MM/YY">
        </div>
        <div class="col-5">
          <label class="form-label" for="card_cvv">CVV</label>
          <input type="text" id="card_cvv" class="form-control" inputmode="numeric" maxlength="4" placeholder="123">
        </div>
      </div>
    </div>

    <div id="pay-error" class="paygw-error d-none"></div>

    <button type="button" id="payBtn" class="btn-tracky" style="margin-top:18px">
      <i class="ti ti-lock"></i> Bayar RM <?= number_format($total, 2) ?>
    </button>

    <a href="/tracky/customer/customer_cart.php" class="paygw-cancel">Batal &amp; kembali ke troli</a>

    <div class="paygw-note">
      <i class="ti ti-info-circle"></i> Ini ialah gateway pembayaran <strong>simulasi</strong> untuk tujuan demo. Tiada caj sebenar dikenakan.
    </div>
  </div>
</div>

<!-- Processing overlay -->
<div class="paygw-overlay d-none" id="overlay">
  <div class="paygw-spinner"></div>
  <div class="paygw-overlay-text" id="overlay-text">Memproses pembayaran...</div>
</div>

<script>
const ORDER_NO = <?= json_encode($order['order_no']) ?>;
let activeTab = 'fpx';

document.querySelectorAll('.paygw-tab').forEach(tab => {
  tab.onclick = () => {
    activeTab = tab.dataset.tab;
    document.querySelectorAll('.paygw-tab').forEach(t => t.classList.toggle('active', t === tab));
    document.getElementById('panel-fpx').classList.toggle('d-none', activeTab !== 'fpx');
    document.getElementById('panel-card').classList.toggle('d-none', activeTab !== 'card');
    hideError();
  };
});

function showError(msg) {
  const el = document.getElementById('pay-error');
  el.textContent = msg;
  el.classList.remove('d-none');
}
function hideError() { document.getElementById('pay-error').classList.add('d-none'); }

// Light cosmetic formatting for the card fields.
document.getElementById('card_no').addEventListener('input', e => {
  e.target.value = e.target.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
});
document.getElementById('card_exp').addEventListener('input', e => {
  let v = e.target.value.replace(/\D/g, '');
  if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2, 4);
  e.target.value = v;
});

function validate() {
  if (activeTab === 'fpx') {
    if (!document.getElementById('bank').value) { showError('Sila pilih bank anda.'); return false; }
  } else {
    const no = document.getElementById('card_no').value.replace(/\s/g, '');
    const exp = document.getElementById('card_exp').value;
    const cvv = document.getElementById('card_cvv').value;
    if (no.length < 12) { showError('Nombor kad tidak sah.'); return false; }
    if (!/^\d{2}\/\d{2}$/.test(exp)) { showError('Tarikh luput tidak sah (MM/YY).'); return false; }
    if (cvv.length < 3) { showError('CVV tidak sah.'); return false; }
  }
  return true;
}

document.getElementById('payBtn').onclick = async () => {
  hideError();
  if (!validate()) return;

  const overlay = document.getElementById('overlay');
  const overlayText = document.getElementById('overlay-text');
  overlay.classList.remove('d-none');

  // Simulated bank/redirect delay for realism.
  setTimeout(async () => {
    overlayText.textContent = 'Mengesahkan dengan bank...';
    try {
      const res = await fetch('/tracky/api/customer_pay.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ order_no: ORDER_NO, method: activeTab }),
      }).then(r => r.json());

      if (res.success) {
        overlayText.textContent = 'Pembayaran berjaya! Mengalih...';
        setTimeout(() => { window.location.href = res.redirect || ('/tracky/customer/customer_order_success.php?order_no=' + encodeURIComponent(ORDER_NO)); }, 700);
      } else {
        overlay.classList.add('d-none');
        showError(res.message || 'Pembayaran gagal. Sila cuba lagi.');
      }
    } catch (e) {
      overlay.classList.add('d-none');
      showError('Ralat sambungan. Sila cuba lagi.');
    }
  }, 1200);
};
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
