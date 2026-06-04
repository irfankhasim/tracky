<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$restaurant = getRestaurant($conn);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Troli — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/customer.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/tracky/"><i class="ti ti-bike text-success"></i> Tracky</a>
    <a href="/tracky/" class="btn btn-outline-secondary btn-sm">Menu</a>
  </div>
</nav>
<div class="container py-4">
  <h4 class="mb-4">Troli Pesanan</h4>
  <div id="empty-cart" class="text-center py-5 d-none">
    <p class="text-muted">Troli kosong.</p>
    <a href="/tracky/" class="btn btn-success">Lihat Menu</a>
  </div>
  <div class="row g-4 cart-layout" id="cart-content">
    <div class="col-lg-7"><div id="cart-items"></div></div>
    <div class="col-lg-5 cart-summary-col">
      <div class="order-summary-card mb-4">
        <h6>Ringkasan</h6>
        <div class="d-flex justify-content-between mt-3"><span>Subtotal</span><span id="subtotal">RM 0.00</span></div>
        <div class="d-flex justify-content-between mt-2"><span>Penghantaran</span><span id="delivery">RM 0.00</span></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Jumlah</span><span class="text-success" id="grand-total">RM 0.00</span></div>
      </div>
      <div class="order-summary-card">
        <h6 class="mb-3">Maklumat Penghantaran</h6>
        <form id="checkout-form">
          <div class="mb-3"><label class="form-label">Nama *</label><input name="customer_name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Telefon *</label><input name="customer_phone" class="form-control" required placeholder="0123456789"></div>
          <div class="mb-3"><label class="form-label">Alamat *</label><textarea name="delivery_address" class="form-control" rows="3" required></textarea></div>
          <div class="mb-3"><label class="form-label">Nota</label><textarea name="notes" class="form-control" rows=2 placeholder="Nota untuk runner..."></textarea></div>
          <div class="mb-3">
            <label class="form-label">Bayaran *</label>
            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="cash" checked id="pm-cash"><label class="form-check-label" for="pm-cash">Tunai (COD)</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="payment_method" value="online" id="pm-online"><label class="form-check-label" for="pm-online">Pindahan Online</label></div>
          </div>
          <div class="alert alert-danger d-none" id="checkout-err"></div>
          <button type="submit" class="btn btn-success w-100 btn-lg" id="btn-order">Buat Pesanan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/tracky/assets/js/validation.js"></script>
<script src="/tracky/assets/js/customer.js"></script>
<script>
const freeMin = <?= (float)($restaurant['free_delivery_min'] ?? 30) ?>;
const deliveryFee = <?= (float)($restaurant['delivery_fee'] ?? 5) ?>;

function calcSummary(total) {
  const fee = total >= freeMin ? 0 : (total > 0 ? deliveryFee : 0);
  document.getElementById('subtotal').textContent = 'RM ' + total.toFixed(2);
  document.getElementById('delivery').textContent = fee ? 'RM ' + fee.toFixed(2) : 'PERCUMA';
  document.getElementById('grand-total').textContent = 'RM ' + (total + fee).toFixed(2);
}

async function refreshCart() {
  const data = await getCart();
  const empty = !data.items || !data.items.length;
  document.getElementById('empty-cart').classList.toggle('d-none', !empty);
  document.getElementById('cart-content').classList.toggle('d-none', empty);
  if (empty) return;
  document.getElementById('cart-items').innerHTML = data.items.map(item => `
    <div class="cart-item-row" data-id="${item.id}">
      <div class="d-flex justify-content-between"><div><h6>${item.name}</h6><small class="text-muted">RM ${parseFloat(item.price).toFixed(2)}</small></div>
      <div class="text-end"><div class="fw-bold text-success line-total">RM ${(item.price*item.quantity).toFixed(2)}</div>
      <button class="btn btn-link btn-sm text-danger p-0 btn-remove">Buang</button></div></div>
      <div class="qty-control mt-2"><button type="button" class="btn-minus">−</button><span class="qty px-2">${item.quantity}</span><button type="button" class="btn-plus">+</button></div>
    </div>`).join('');
  calcSummary(parseFloat(data.total));
  bindCartEvents();
}

function bindCartEvents() {
  document.querySelectorAll('.cart-item-row').forEach(row => {
    const id = row.dataset.id;
    const price = parseFloat(row.querySelector('.text-muted').textContent.replace('RM ',''));
    row.querySelector('.btn-minus').onclick = async () => {
      const q = Math.max(0, parseInt(row.querySelector('.qty').textContent)-1);
      await cartAction('update', { item_id: id, quantity: q }); refreshCart();
    };
    row.querySelector('.btn-plus').onclick = async () => {
      const q = parseInt(row.querySelector('.qty').textContent)+1;
      await cartAction('update', { item_id: id, quantity: q }); refreshCart();
    };
    row.querySelector('.btn-remove').onclick = async () => { await cartAction('remove', { item_id: id }); refreshCart(); };
  });
}

document.getElementById('checkout-form').addEventListener('submit', async e => {
  e.preventDefault();
  const form = e.target;
  const errEl = document.getElementById('checkout-err');
  errEl.classList.add('d-none');
  errEl.textContent = '';
  if (!TrackyValidate.validateCheckout(form)) return;
  const btn = document.getElementById('btn-order');
  btn.disabled = true;
  try {
    const fd = new FormData(form);
    const res = await fetch('/tracky/api/place_order.php', { method:'POST', body: fd });
    const data = await res.json();
    if (data.success) location.href = '/tracky/order_success.php?order_no=' + encodeURIComponent(data.order_no);
    else { errEl.textContent = data.message || 'Gagal membuat pesanan'; errEl.classList.remove('d-none'); btn.disabled=false; }
  } catch {
    errEl.textContent = 'Ralat sambungan. Sila cuba lagi.';
    errEl.classList.remove('d-none');
    btn.disabled = false;
  }
});

refreshCart();
</script>
</body>
</html>
