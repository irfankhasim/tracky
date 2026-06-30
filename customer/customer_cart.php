<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$cust_rid = custRestaurantId();
$restaurant = getRestaurant($conn, $cust_rid);
$freeMin = (float) ($restaurant['free_delivery_min'] ?? 30);
$deliveryFee = (float) ($restaurant['delivery_fee'] ?? 5);
$accent_color = $restaurant['accent_color'] ?? '#1D9E75';
$menu_back = $cust_rid > 0 ? '/tracky/customer/customer_menu.php?restaurant=' . $cust_rid : '/tracky/customer/customer_restaurants.php';
$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Troli — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
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
    <a href="<?= e($menu_back) ?>" class="nav-icon-btn" title="Kembali ke menu"><i class="ti ti-arrow-left"></i></a>
    <button class="customer-theme-btn" id="themeToggle" onclick="toggleTheme()"><i class="ti ti-sun" id="themeIcon"></i></button>
  </div>
</nav>

<div class="customer-page-wrap customer-page-wrap--wide">

  <div id="empty-cart" class="d-none" style="text-align:center;padding:60px 20px">
    <i class="ti ti-shopping-cart-off" style="font-size:3rem;color:var(--muted);display:block;margin-bottom:12px"></i>
    <p style="color:var(--muted);margin-bottom:20px">Troli anda kosong.</p>
    <a href="<?= e($menu_back) ?>" class="btn-tracky" style="display:inline-flex;width:auto;padding:12px 24px">
      <i class="ti ti-arrow-left"></i> Kembali ke Menu
    </a>
  </div>

  <div id="cart-content" class="d-none">
    <div class="row g-3">
      <div class="col-lg-7">

        <!-- Cart Items -->
        <div class="section-card">
          <div class="section-card-title"><i class="ti ti-shopping-cart"></i> Pesanan Anda</div>
          <div id="cart-items"></div>
          <div id="free-delivery-banner" class="d-none" style="margin-top:12px;padding:10px 14px;background:rgba(29,158,117,0.08);border:1px solid rgba(29,158,117,0.2);border-radius:10px;font-size:13px;color:var(--green);font-weight:600">
            <i class="ti ti-truck-delivery"></i> Tahniah! Anda layak penghantaran PERCUMA.
          </div>
        </div>

        <!-- Summary -->
        <div class="section-card">
          <div class="section-card-title"><i class="ti ti-receipt"></i> Ringkasan</div>
          <div class="summary-row"><span>Subtotal</span><span id="subtotal">RM 0.00</span></div>
          <div class="summary-row"><span>Penghantaran</span><span id="delivery">RM 0.00</span></div>
          <div class="summary-total"><span>Jumlah</span><span class="summary-total-amount" id="grand-total">RM 0.00</span></div>
        </div>

      </div>
      <div class="col-lg-5">

        <!-- Checkout Form -->
        <div class="section-card checkout-form">
          <div class="section-card-title"><i class="ti ti-map-pin"></i> Maklumat Penghantaran</div>
          <form id="checkout-form" onsubmit="return false;">
            <div class="mb-3">
              <label class="form-label" for="customer_name">Nama Penuh</label>
              <input type="text" name="customer_name" id="customer_name" class="form-control" placeholder="Nama penuh anda" required minlength="3">
              <div class="invalid-feedback"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="customer_phone">No. Telefon</label>
              <input type="tel" name="customer_phone" id="customer_phone" class="form-control" placeholder="cth: 0123456789" pattern="^(01)[0-9]{8,9}$" required>
              <div class="invalid-feedback"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="addr_search">Cari Alamat</label>
              <div class="addr-autocomplete">
                <input type="text" id="addr_search" class="form-control" placeholder="Taip nama jalan / taman / bandar..." autocomplete="off">
                <div id="addr_suggestions" class="addr-suggestions d-none"></div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="delivery_address">Alamat Penghantaran Penuh</label>
              <textarea name="delivery_address" id="delivery_address" class="form-control" rows="3" placeholder="No rumah, jalan, taman, poskod, bandar" required minlength="10"></textarea>
              <div class="invalid-feedback"></div>
            </div>
            <div id="map_block" class="mb-3 d-none">
              <div id="delivery-map" class="delivery-map"></div>
              <div id="coord_label" class="d-none" style="font-size:11px;color:var(--green);font-weight:600;margin-top:6px">
                <i class="ti ti-map-pin-check"></i> Lokasi ditetapkan
              </div>
            </div>
            <input type="hidden" name="delivery_lat" id="delivery_lat">
            <input type="hidden" name="delivery_lng" id="delivery_lng">
            <div class="mb-3">
              <label class="form-label" for="notes">Nota untuk Runner <span style="color:var(--muted);font-weight:500;text-transform:none">(pilihan)</span></label>
              <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="cth: Rumah pagar biru, tingkat 2, dll"></textarea>
            </div>

            <!-- Payment -->
            <div class="mb-3">
              <label class="form-label">Kaedah Pembayaran</label>
              <div class="payment-options">
                <label class="payment-option selected" onclick="selectPayment(this)">
                  <input type="radio" name="payment_method" value="cash" checked>
                  <div class="payment-icon"><i class="ti ti-cash"></i></div>
                  <div>
                    <div class="payment-label">Tunai (COD)</div>
                    <div class="payment-desc">Bayar kepada runner semasa penghantaran</div>
                  </div>
                </label>
                <label class="payment-option" onclick="selectPayment(this)">
                  <input type="radio" name="payment_method" value="online">
                  <div class="payment-icon"><i class="ti ti-building-bank"></i></div>
                  <div>
                    <div class="payment-label">Pindahan Bank</div>
                    <div class="payment-desc">Butiran akaun akan dipaparkan selepas order</div>
                  </div>
                </label>
              </div>
              <div class="invalid-feedback d-block" id="payment-error"></div>
            </div>

            <div class="mb-4" style="display:flex;align-items:center;gap:10px">
              <input type="checkbox" class="form-check-input" id="agreeTerms" required style="width:16px;height:16px;accent-color:var(--green);flex-shrink:0">
              <label class="form-check-label" for="agreeTerms" style="font-size:13px;color:var(--text-2);cursor:pointer">Saya mengesahkan maklumat di atas adalah betul</label>
              <div class="invalid-feedback" id="terms-error"></div>
            </div>

            <button type="button" class="btn-tracky" id="submitBtn" disabled>
              <i class="ti ti-check"></i> Sahkan Pesanan
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?= asset('assets/js/customer.js') ?>"></script>
<script>
const freeMin = <?= $freeMin ?>;
const deliveryFee = <?= $deliveryFee ?>;
let cartItemsCache = [];

function selectPayment(label) {
  document.querySelectorAll('.payment-option').forEach(l => l.classList.remove('selected'));
  label.classList.add('selected');
}

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

  if (!name || name.length < 3) { errors.customer_name = 'Sila masukkan nama penuh (minimum 3 aksara)'; valid = false; }
  const cleanPhone = phone.replace(/[-\s]/g, '');
  if (!cleanPhone || !/^(01)[0-9]{8,9}$/.test(cleanPhone)) { errors.customer_phone = 'Format telefon tidak sah. Contoh: 0123456789'; valid = false; }
  if (!address || address.length < 10) { errors.delivery_address = 'Sila masukkan alamat lengkap (minimum 10 aksara)'; valid = false; }
  if (!terms) { errors.terms = 'Sila sahkan maklumat anda'; valid = false; }
  if (!payment) { errors.payment = 'Sila pilih kaedah pembayaran'; valid = false; }

  ['customer_name', 'customer_phone', 'delivery_address'].forEach(id => {
    if (errors[id]) showFieldError(id, errors[id]); else clearFieldError(id);
  });

  const agreeEl = document.getElementById('agreeTerms');
  const termsErr = document.getElementById('terms-error');
  if (errors.terms) { agreeEl.classList.add('is-invalid'); if (termsErr) termsErr.textContent = errors.terms; }
  else { agreeEl.classList.remove('is-invalid'); if (termsErr) termsErr.textContent = ''; }

  const payErr = document.getElementById('payment-error');
  if (errors.payment) { if (payErr) { payErr.textContent = errors.payment; payErr.style.display = 'block'; } }
  else if (payErr) { payErr.textContent = ''; payErr.style.display = 'none'; }

  return valid;
}

function showAlert(message, type) {
  document.querySelectorAll('.checkout-form .alert-checkout').forEach(el => el.remove());
  const alert = document.createElement('div');
  alert.className = 'alert-checkout';
  alert.style.cssText = `padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:16px;background:${type==='danger'?'rgba(239,68,68,0.1)':'rgba(29,158,117,0.1)'};border:1px solid ${type==='danger'?'rgba(239,68,68,0.3)':'rgba(29,158,117,0.3)'};color:${type==='danger'?'#EF4444':'var(--green)'}`;
  alert.textContent = message;
  document.querySelector('.checkout-form').prepend(alert);
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
  if (empty) { updateCartBadge(0); return; }

  document.getElementById('cart-items').innerHTML = cartItemsCache.map(item => {
    const line = item.price * item.quantity;
    const thumb = item.image
      ? `<div class="cart-item-icon" style="padding:0;overflow:hidden"><img src="/tracky/${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" style="width:100%;height:100%;object-fit:cover"></div>`
      : `<div class="cart-item-icon">🍽️</div>`;
    return `<div class="cart-item-row" data-id="${item.id}">
      ${thumb}
      <div class="cart-item-info">
        <div class="cart-item-name">${escapeHtml(item.name)}</div>
        <div class="cart-item-unit">RM ${parseFloat(item.price).toFixed(2)} / unit</div>
        <div class="qty-control" style="margin-top:8px;gap:0">
          <button type="button" class="qty-btn btn-minus">−</button>
          <span class="qty-value">${item.quantity}</span>
          <button type="button" class="qty-btn btn-plus">+</button>
          <button type="button" class="btn-remove" style="margin-left:8px;background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;font-size:14px" title="Buang"><i class="ti ti-trash"></i></button>
        </div>
      </div>
      <div class="cart-item-price">RM ${line.toFixed(2)}</div>
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
  if (!cartItemsCache.length) { showAlert('Troli anda kosong!', 'danger'); return; }
  const btn = document.getElementById('submitBtn');
  const defaultHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2"></i> Memproses pesanan...';
  const formData = {
    customer_name: document.getElementById('customer_name').value.trim(),
    customer_phone: document.getElementById('customer_phone').value.trim().replace(/[-\s]/g, ''),
    delivery_address: document.getElementById('delivery_address').value.trim(),
    delivery_lat: document.getElementById('delivery_lat').value,
    delivery_lng: document.getElementById('delivery_lng').value,
    notes: document.getElementById('notes').value.trim(),
    payment_method: document.querySelector('input[name="payment_method"]:checked').value,
    items: JSON.stringify(cartItemsCache),
  };
  fetch('/tracky/api/customer_place_order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    credentials: 'same-origin',
    body: new URLSearchParams(formData),
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const dest = data.payment_method === 'online'
        ? '/tracky/customer/customer_payment.php?order_no=' + encodeURIComponent(data.order_no)
        : '/tracky/customer/customer_order_success.php?order_no=' + encodeURIComponent(data.order_no);
      fetch('/tracky/api/customer_cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: 'action=clear' })
      .finally(() => { window.location.href = dest; });
    } else {
      showAlert(data.message || 'Gagal membuat pesanan. Sila cuba semula.', 'danger');
      btn.disabled = false; btn.innerHTML = defaultHtml; updateSubmitState();
    }
  })
  .catch(() => {
    showAlert('Ralat sambungan. Sila semak internet anda.', 'danger');
    btn.disabled = false; btn.innerHTML = defaultHtml; updateSubmitState();
  });
}

document.getElementById('submitBtn').addEventListener('click', submitOrder);
['customer_name', 'customer_phone', 'delivery_address', 'agreeTerms'].forEach(id => {
  document.getElementById(id).addEventListener('input', updateSubmitState);
  document.getElementById(id).addEventListener('change', updateSubmitState);
});
document.querySelectorAll('input[name="payment_method"]').forEach(el => el.addEventListener('change', updateSubmitState));

/* ── Alamat: Autocomplete (Nominatim/OSM) + Peta (Leaflet) — PERCUMA, tanpa API key ── */
(function initAddressGeo() {
  const searchEl = document.getElementById('addr_search');
  const sugEl = document.getElementById('addr_suggestions');
  const addrEl = document.getElementById('delivery_address');
  const latEl = document.getElementById('delivery_lat');
  const lngEl = document.getElementById('delivery_lng');
  const mapBlock = document.getElementById('map_block');
  const coordLabel = document.getElementById('coord_label');
  let map = null, marker = null, debounce = null, controller = null;

  function setCoords(lat, lng) {
    latEl.value = (+lat).toFixed(7);
    lngEl.value = (+lng).toFixed(7);
    coordLabel.classList.remove('d-none');
  }

  function showMap(lat, lng) {
    mapBlock.classList.remove('d-none');
    if (!map) {
      map = L.map('delivery-map').setView([lat, lng], 17);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
      }).addTo(map);
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', () => {
        const p = marker.getLatLng();
        setCoords(p.lat, p.lng);
        reverseGeocode(p.lat, p.lng);
      });
    } else {
      map.setView([lat, lng], 17);
      marker.setLatLng([lat, lng]);
    }
    setTimeout(() => map.invalidateSize(), 150);
  }

  async function reverseGeocode(lat, lng) {
    try {
      const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&accept-language=ms&lat=${lat}&lon=${lng}`, { headers: { 'Accept': 'application/json' } });
      const d = await r.json();
      if (d && d.display_name) { addrEl.value = d.display_name; updateSubmitState(); }
    } catch (_) { /* fallback: kekalkan alamat sedia ada */ }
  }

  function renderSuggestions(list) {
    if (!list.length) { sugEl.classList.add('d-none'); sugEl.innerHTML = ''; return; }
    sugEl.innerHTML = list.map((p, i) =>
      `<div class="addr-suggestion" data-i="${i}"><i class="ti ti-map-pin"></i><span>${escapeHtml(p.display_name)}</span></div>`
    ).join('');
    sugEl.classList.remove('d-none');
    sugEl.querySelectorAll('.addr-suggestion').forEach(el => {
      el.onclick = () => {
        const p = list[+el.dataset.i];
        addrEl.value = p.display_name;
        setCoords(p.lat, p.lon);
        showMap(+p.lat, +p.lon);
        sugEl.classList.add('d-none');
        updateSubmitState();
      };
    });
  }

  async function searchAddr(q) {
    if (controller) controller.abort();
    controller = new AbortController();
    try {
      const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=5&countrycodes=my&accept-language=ms&q=${encodeURIComponent(q)}`;
      const r = await fetch(url, { signal: controller.signal, headers: { 'Accept': 'application/json' } });
      renderSuggestions(await r.json());
    } catch (e) { if (e.name !== 'AbortError') sugEl.classList.add('d-none'); }
  }

  searchEl.addEventListener('input', () => {
    const q = searchEl.value.trim();
    clearTimeout(debounce);
    if (q.length < 3) { sugEl.classList.add('d-none'); return; }
    debounce = setTimeout(() => searchAddr(q), 450);
  });
  document.addEventListener('click', (e) => {
    if (!sugEl.contains(e.target) && e.target !== searchEl) sugEl.classList.add('d-none');
  });
})();

refreshCart();
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
