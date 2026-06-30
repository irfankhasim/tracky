<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Resolve the restaurant the customer is ordering from.
$req_rid = (int) ($_GET['restaurant'] ?? 0);
$active_rid = custRestaurantId();
if ($req_rid > 0) {
    $restaurant = getRestaurant($conn, $req_rid);
    if (!$restaurant || (int) ($restaurant['is_active'] ?? 1) !== 1) {
        header('Location: /tracky/customer/customer_restaurants.php');
        exit;
    }
    // Switching restaurants clears the cart (one restaurant per cart).
    if ($active_rid !== $req_rid) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cust_restaurant_id'] = $req_rid;
    $active_rid = $req_rid;
} else {
    if ($active_rid < 1) {
        header('Location: /tracky/customer/customer_restaurants.php');
        exit;
    }
    $restaurant = getRestaurant($conn, $active_rid);
    if (!$restaurant) {
        unset($_SESSION['cust_restaurant_id']);
        header('Location: /tracky/customer/customer_restaurants.php');
        exit;
    }
}

$accent_color = $restaurant['accent_color'] ?: '#1D9E75';
$categories = mysqli_query($conn, "SELECT * FROM categories WHERE is_active = 1 AND restaurant_id = $active_rid ORDER BY sort_order");
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1D9E75">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Tracky — Order Makanan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
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
    <a href="/tracky/customer/customer_restaurants.php" class="nav-icon-btn" title="Tukar restoran"><i class="ti ti-building-store"></i></a>
    <a href="/tracky/customer/customer_track.php" class="nav-icon-btn" title="Track pesanan"><i class="ti ti-map-pin"></i></a>
    <button class="customer-theme-btn" id="themeToggle" onclick="toggleTheme()"><i class="ti ti-sun" id="themeIcon"></i></button>
    <a href="/tracky/customer/customer_cart.php" class="nav-cart-btn">
      <i class="ti ti-shopping-cart"></i>
      <span class="nav-cart-count" data-cart-count style="<?= $cart_count ? '' : 'display:none' ?>"><?= $cart_count ?></span>
    </a>
  </div>
</nav>

<div class="hero-banner">
  <a href="/tracky/customer/customer_restaurants.php" class="hero-back"><i class="ti ti-arrow-left"></i> Semua Restoran</a>
  <h1 class="hero-title"><?= e($restaurant['name'] ?? 'Tracky') ?></h1>
  <p class="hero-sub">Pesan makanan kegemaran anda — kami hantar terus ke pintu rumah</p>
  <div class="hero-meta">
    <span><i class="ti ti-clock"></i> <?= e($restaurant['operating_hours'] ?? '8:00 AM - 10:00 PM') ?></span>
    <span><i class="ti ti-truck"></i> Free delivery &gt; RM <?= number_format((float)($restaurant['free_delivery_min'] ?? 30), 0) ?></span>
  </div>
</div>

<div class="category-scroll">
  <button type="button" class="category-pill active" data-cat="all"><i class="ti ti-layout-grid"></i> Semua</button>
  <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
  <button type="button" class="category-pill" data-cat="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></button>
  <?php endwhile; ?>
</div>

<div class="menu-section">
  <div class="menu-section-title">Menu Tersedia</div>
  <div class="menu-grid" id="menu-grid">
    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">
      <i class="ti ti-loader" style="font-size:2rem;display:block;margin-bottom:8px"></i>
      Memuatkan menu...
    </div>
  </div>
</div>

<div class="cart-float" id="cart-float" style="<?= $cart_count ? '' : 'display:none' ?>">
  <a href="/tracky/customer/customer_cart.php" class="cart-float-btn">
    <i class="ti ti-shopping-cart"></i> Lihat Troli
    <span data-cart-count><?= $cart_count ?></span>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('assets/js/customer.js') ?>"></script>
<script>
async function renderMenu(cat='all') {
  const grid = document.getElementById('menu-grid');
  try {
    const items = await loadMenu(cat);
    if (!Array.isArray(items) || !items.length) {
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">${!Array.isArray(items) ? 'Gagal memuatkan menu.' : 'Tiada item dalam kategori ini.'}</div>`;
      return;
    }
    grid.innerHTML = items.map(item => `
      <div class="menu-card">
        <div class="menu-card-img-wrap">${item.image
          ? `<img src="/tracky/${item.image}" alt="${item.name.replace(/"/g,'&quot;')}" loading="lazy">`
          : `<i class="ti ti-bowl-chopsticks" style="font-size:2rem;color:var(--muted)"></i>`}</div>
        <div class="menu-card-body">
          <div class="menu-card-name">${item.name}</div>
          ${item.description ? `<div class="menu-card-cat" style="white-space:normal;-webkit-line-clamp:2;overflow:hidden;display:-webkit-box;-webkit-box-orient:vertical">${item.description}</div>` : '<div class="menu-card-cat">&nbsp;</div>'}
          <div class="menu-card-footer">
            <span class="menu-card-price">RM ${parseFloat(item.price).toFixed(2)}</span>
            ${item.is_available==1
              ? `<button class="menu-card-add add-btn" data-id="${item.id}" data-name="${item.name.replace(/"/g,'&quot;')}" data-price="${item.price}"><i class="ti ti-plus"></i></button>`
              : `<span style="font-size:11px;color:var(--muted);font-weight:600">Habis</span>`}
          </div>
        </div>
      </div>`).join('');

    grid.querySelectorAll('.add-btn').forEach(btn => btn.addEventListener('click', async () => {
      const res = await cartAction('add', { item_id: btn.dataset.id, name: btn.dataset.name, price: btn.dataset.price, quantity: 1 });
      if (res.success) {
        updateCartBadge(res.total_items);
        btn.innerHTML = '<i class="ti ti-check"></i>';
        btn.style.background = '#0F6E56';
        setTimeout(() => { btn.innerHTML = '<i class="ti ti-plus"></i>'; btn.style.background = ''; }, 800);
      }
    }));
  } catch {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:#EF4444">Ralat sambungan. Sila muat semula halaman.</div>';
  }
}

document.querySelectorAll('.category-pill').forEach(b => b.addEventListener('click', function() {
  document.querySelectorAll('.category-pill').forEach(x => x.classList.remove('active'));
  this.classList.add('active');
  renderMenu(this.dataset.cat);
}));
renderMenu();
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
