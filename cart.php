<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$restaurant = getRestaurant($conn);
$freeMin = (float) ($restaurant['free_delivery_min'] ?? 30);
$deliveryFee = (float) ($restaurant['delivery_fee'] ?? 5);
$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
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
    <a href="/tracky/cart.php" class="btn btn-success btn-sm position-relative">
      <i class="ti ti-shopping-cart"></i> Troli
      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-cart-count style="<?= $cart_count ? '' : 'display:none' ?>"><?= $cart_count ?></span>
    </a>
  </div>
</nav>

<div class="container py-4 pb-5">
  <div id="empty-cart" class="text-center py-5 d-none">
    <i class="ti ti-shopping-cart-off fs-1 text-muted"></i>
    <p class="text-muted mt-3 mb-3">Troli anda kosong.</p>
    <a href="/tracky/" class="btn btn-success">Kembali ke Menu</a>
  </div>

  <div class="row g-4 cart-checkout-layout d-none" id="cart-content">
    <div class="col-lg-7">
      <div class="order-summary-card">
        <h5 class="fw-semibold mb-3">Pesanan Anda</h5>
        <div id="cart-items"></div>
        <div id="free-delivery-banner" class="alert alert-success py-2 small d-none mt-2 mb-0">
          <i class="ti ti-truck-delivery"></i> Tahniah! Anda layak penghantaran PERCUMA.
        </div>
        <hr>
        <div class="d-flex justify-content-between"><span>Subtotal</span><span id="subtotal">RM 0.00</span></div>
        <div class="d-flex justify-content-between mt-2"><span>Penghantaran</span><span id="delivery">RM 0.00</span></div>
        <div class="d-flex justify-content-between fw-bold fs-5 mt-3"><span>Jumlah</span><span class="text-success" id="grand-total">RM 0.00</span></div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="order-summary-card checkout-form">
        <h5 class="fw-semibold mb-3">Maklumat Penghantaran</h5>
        <form id="checkout-form" onsubmit="return false;">
          <div class="mb-3">
            <label class="form-label" for="customer_name">Nama Penuh</label>
            <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Nama penuh anda" required minlength="3">
            <div class="invalid-feedback"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="customer_phone">No. Telefon</label>
            <input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="cth: 0123456789" pattern="^(01)[0-9]{8,9}$" required>
            <div class="form-text">Format: 01x-xxxxxxxx</div>
            <div class="invalid-feedback"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="delivery_address">Alamat Penghantaran</label>
            <textarea name="delivery_address" id="delivery_address" class="form-control" rows="3" placeholder="No rumah, jalan, taman, poskod, bandar" required minlength="10"></textarea>
            <div class="invalid-feedback"></div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="notes">Nota untuk Runner <span class="text-muted">(pilihan)</span></label>
            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="cth: Rumah pagar biru, tingkat 2, dll"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label d-block mb-2">Kaedah Pembayaran</label>
            <div class="payment-options">
              <label class="payment-option">
                <input type="radio" name="payment_method" value="cash" checked>
                <div class="payment-card">
                  <i class="ti ti-cash"></i>
                  <div>
                    <div class="payment-title">Tunai (COD)</div>
                    <div class="payment-desc">Bayar kepada runner semasa penghantaran</div>
                  </div>
                </div>
              </label>
              <label class="payment-option">
                <input type="radio" name="payment_method" value="online">
                <div class="payment-card">
                  <i class="ti ti-building-bank"></i>
                  <div>
                    <div class="payment-title">Pindahan Bank</div>
                    <div class="payment-desc">Butiran akaun akan dipaparkan selepas order</div>
                  </div>
                </div>
              </label>
            </div>
            <div class="invalid-feedback d-block" id="payment-error"></div>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="agreeTerms" required>
            <label class="form-check-label" for="agreeTerms">Saya mengesahkan maklumat di atas adalah betul</label>
            <div class="invalid-feedback" id="terms-error"></div>
          </div>
          <button type="button" class="btn btn-success btn-lg w-100" id="submitBtn" disabled>Sahkan Pesanan →</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/tracky/assets/js/customer.js"></script>
<script>
const freeMin = <?= $freeMin ?>;
const deliveryFee = <?= $deliveryFee ?>;
let cartItemsCache = [];

function escapeHtml(str) {
  return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function calcSummary(total) {
  const fee = total >= freeMin ? 0 : (total > 0 ? deliveryFee : 0);
  document.getElementById('subtotal').textContent = 'RM ' + total.toFixed(2);
  document.getElementById('delivery').textContent = fee ? 'RM ' + fee.toFixed(2) : 'PERCUMA';
  document.getElementById('grand-total').textContent = 'RM ' + (total + fee).toFixed(2);
  document.getElementById('free-delivery-banner').classList.toggle('d-none', total < freeMin || total <= 0);
}

function getCartItems() {
  return cartItemsCache;
}

function showFieldError(fieldId, message) {
  const input = document.getElementById(fieldId);
  if (!input) return;
  input.classList.add('is-invalid');
  input.classList.remove('is-valid');
  const fb = input.parentElement.querySelector('.invalid-feedback');
  if (fb) fb.textContent = message;
}

function clearFieldError(fieldId) {
  const input = document.getElementById(fieldId);
  if (!input) return;
  input.classList.remove('is-invalid');
  input.classList.add('is-valid');
  const fb = input.parentElement.querySelector('.invalid-feedback');
  if (fb) fb.textContent = '';
}

function validateCart() {
  let valid = true;
  const errors = {};

  const name = document.getElementById('customer_name').value.trim();
  const phone = document.getElementById('customer_phone').value.trim();
  const address = document.getElementById('delivery_address').value.trim();
  const terms = document.getElementById('agreeTerms').checked;
  const payment = document.querySelector('input[name="payment_method"]:checked');

  if (!name || name.length < 3) {
    errors.customer_name = 'Sila masukkan nama penuh (minimum 3 aksara)';
    valid = false;
  }

  const phoneRegex = /^(01)[0-9]{8,9}$/;
  const cleanPhone = phone.replace(/[-\s]/g, '');
  if (!cleanPhone || !phoneRegex.test(cleanPhone)) {
    errors.customer_phone = 'Format telefon tidak sah. Contoh: 0123456789';
    valid = false;
  }

  if (!address || address.length < 10) {
    errors.delivery_address = 'Sila masukkan alamat lengkap (minimum 10 aksara)';
    valid = false;
  }

  if (!terms) {
    errors.terms = 'Sila sahkan maklumat anda';
    valid = false;
  }

  if (!payment) {
    errors.payment = 'Sila pilih kaedah pembayaran';
    valid = false;
  }

  ['customer_name', 'customer_phone', 'delivery_address'].forEach(id => {
    if (errors[id]) showFieldError(id, errors[id]);
    else clearFieldError(id);
  });

  const termsErr = document.getElementById('terms-error');
  const agreeEl = document.getElementById('agreeTerms');
  if (errors.terms) {
    agreeEl.classList.add('is-invalid');
    if (termsErr) termsErr.textContent = errors.terms;
  } else {
    agreeEl.classList.remove('is-invalid');
    if (termsErr) termsErr.textContent = '';
  }

  const payErr = document.getElementById('payment-error');
  if (errors.payment) {
    if (payErr) { payErr.textContent = errors.payment; payErr.style.display = 'block'; }
  } else if (payErr) {
    payErr.textContent = '';
    payErr.style.display = 'none';
  }

  return valid;
}

function showAlert(message, type) {
  document.querySelectorAll('.checkout-form .alert-checkout').forEach(el => el.remove());
  const alert = document.createElement('div');
  alert.className = `alert alert-${type} alert-dismissible alert-checkout`;
  alert.innerHTML = `${message}<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
  document.querySelector('.checkout-form').prepend(alert);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateSubmitState() {
  const btn = document.getElementById('submitBtn');
  const name = document.getElementById('customer_name').value.trim();
  const phone = document.getElementById('customer_phone').value.trim().replace(/[-\s]/g, '');
  const address = document.getElementById('delivery_address').value.trim();
  const terms = document.getElementById('agreeTerms').checked;
  const hasCart = cartItemsCache.length > 0;
  btn.disabled = !(hasCart && name.length >= 3 && /^(01)[0-9]{8,9}$/.test(phone) && address.length >= 10 && terms);
}

async function refreshCart() {
  const data = await getCart();
  const empty = !data.items || !data.items.length;
  cartItemsCache = data.items || [];
  document.getElementById('empty-cart').classList.toggle('d-none', !empty);
  document.getElementById('cart-content').classList.toggle('d-none', empty);
  if (empty) {
    updateCartBadge(0);
    return;
  }

  document.getElementById('cart-items').innerHTML = cartItemsCache.map(item => {
    const line = item.price * item.quantity;
    return `<div class="cart-item-row" data-id="${item.id}">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="flex-grow-1">
          <h6 class="mb-1">${escapeHtml(item.name)}</h6>
          <div class="small text-muted">RM ${parseFloat(item.price).toFixed(2)} / unit</div>
          <div class="qty-control mt-2">
            <button type="button" class="qty-btn btn-minus" aria-label="Kurang">−</button>
            <span class="qty-value">${item.quantity}</span>
            <button type="button" class="qty-btn btn-plus" aria-label="Tambah">+</button>
          </div>
        </div>
        <div class="text-end">
          <div class="fw-bold text-success line-total">RM ${line.toFixed(2)}</div>
          <button type="button" class="btn btn-link btn-sm text-danger p-0 btn-remove" title="Buang"><i class="ti ti-x"></i></button>
        </div>
      </div>
    </div>`;
  }).join('');

  calcSummary(parseFloat(data.total));
  updateCartBadge(data.total_items);
  bindCartEvents();
  updateSubmitState();
}

function bindCartEvents() {
  document.querySelectorAll('.cart-item-row').forEach(row => {
    const id = row.dataset.id;
    row.querySelector('.btn-minus').onclick = async () => {
      const q = Math.max(0, parseInt(row.querySelector('.qty-value').textContent, 10) - 1);
      await cartAction('update', { item_id: id, quantity: q });
      refreshCart();
    };
    row.querySelector('.btn-plus').onclick = async () => {
      const q = parseInt(row.querySelector('.qty-value').textContent, 10) + 1;
      await cartAction('update', { item_id: id, quantity: q });
      refreshCart();
    };
    row.querySelector('.btn-remove').onclick = async () => {
      await cartAction('remove', { item_id: id });
      refreshCart();
    };
  });
}

function submitOrder() {
  if (!validateCart()) return;

  if (!cartItemsCache.length) {
    showAlert('Troli anda kosong!', 'danger');
    return;
  }

  const btn = document.getElementById('submitBtn');
  const defaultHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2"></i> Memproses pesanan...';

  const formData = {
    customer_name: document.getElementById('customer_name').value.trim(),
    customer_phone: document.getElementById('customer_phone').value.trim().replace(/[-\s]/g, ''),
    delivery_address: document.getElementById('delivery_address').value.trim(),
    notes: document.getElementById('notes').value.trim(),
    payment_method: document.querySelector('input[name="payment_method"]:checked').value,
    items: JSON.stringify(cartItemsCache),
  };

  fetch('/tracky/api/place_order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    credentials: 'same-origin',
    body: new URLSearchParams(formData),
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      fetch('/tracky/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: 'action=clear',
      }).finally(() => {
        window.location.href = '/tracky/order_success.php?order_no=' + encodeURIComponent(data.order_no);
      });
    } else {
      showAlert(data.message || 'Gagal membuat pesanan. Sila cuba semula.', 'danger');
      btn.disabled = false;
      btn.innerHTML = defaultHtml;
      updateSubmitState();
    }
  })
  .catch(() => {
    showAlert('Ralat sambungan. Sila semak internet anda.', 'danger');
    btn.disabled = false;
    btn.innerHTML = defaultHtml;
    updateSubmitState();
  });
}

document.getElementById('submitBtn').addEventListener('click', submitOrder);
['customer_name', 'customer_phone', 'delivery_address', 'agreeTerms'].forEach(id => {
  document.getElementById(id).addEventListener('input', updateSubmitState);
  document.getElementById(id).addEventListener('change', updateSubmitState);
});
document.querySelectorAll('input[name="payment_method"]').forEach(el => {
  el.addEventListener('change', updateSubmitState);
});

refreshCart();
</script>
</body>
</html>
