<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$restaurant = getRestaurant($conn);
$categories = mysqli_query($conn, 'SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
$cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1D9E75">
  <title>Tracky — Order Makanan</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/customer.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="/tracky/">
      <i class="ti ti-bike text-success fs-4"></i> <span>Tracky</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#customerNav" aria-controls="customerNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="customerNav">
      <div class="d-flex gap-2 ms-sm-auto mt-3 mt-sm-0">
        <a href="/tracky/track.php" class="btn btn-outline-secondary btn-sm"><i class="ti ti-map-pin"></i> <span class="d-none d-sm-inline">Track</span></a>
        <a href="/tracky/cart.php" class="btn btn-success btn-sm position-relative">
          <i class="ti ti-shopping-cart"></i> Troli
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-cart-count style="<?= $cart_count ? '' : 'display:none' ?>"><?= $cart_count ?></span>
        </a>
      </div>
    </div>
  </div>
</nav>
<div class="hero-banner">
  <div class="container">
    <h1><?= e($restaurant['name'] ?? 'Tracky Food') ?></h1>
    <p>Pesan makanan kegemaran anda — kami hantar terus ke pintu rumah</p>
    <div class="delivery-info">
      <span><i class="ti ti-clock"></i> <?= e($restaurant['operating_hours'] ?? '8:00 AM - 10:00 PM') ?></span>
      <span><i class="ti ti-truck"></i> Free delivery &gt; RM <?= number_format((float)($restaurant['free_delivery_min'] ?? 30), 0) ?></span>
    </div>
  </div>
</div>
<div class="container mt-4">
  <div class="category-scroll d-flex gap-2 overflow-auto pb-2">
    <button type="button" class="btn btn-success btn-sm category-btn active" data-cat="all">Semua</button>
    <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
    <button type="button" class="btn btn-outline-success btn-sm category-btn" data-cat="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></button>
    <?php endwhile; ?>
  </div>
</div>
<div class="container mt-3 mb-5 pb-5 cart-page-bottom">
  <div class="row g-3" id="menu-grid"><div class="col-12 text-center text-muted py-4">Memuatkan menu...</div></div>
</div>
<div class="cart-float" id="cart-float" style="<?= $cart_count ? '' : 'display:none' ?>">
  <a href="/tracky/cart.php" class="btn btn-success btn-lg rounded-pill shadow"><i class="ti ti-shopping-cart"></i> Lihat Troli (<span data-cart-count><?= $cart_count ?></span>)</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/tracky/assets/js/customer.js"></script>
<script>
async function renderMenu(cat='all') {
  const grid = document.getElementById('menu-grid');
  try {
    const items = await loadMenu(cat);
    if (!Array.isArray(items)) {
      grid.innerHTML = '<div class="col-12 text-center text-danger">Gagal memuatkan menu.</div>';
      return;
    }
    if (!items.length) { grid.innerHTML = '<div class="col-12 text-center text-muted">Tiada item.</div>'; return; }
  grid.innerHTML = items.map(item => `
    <div class="col-6 col-md-4 col-lg-3">
      <div class="card menu-card h-100">
        <div class="menu-img-placeholder"><i class="ti ti-bowl-chopsticks"></i></div>
        <div class="card-body">
          <h6 class="card-title">${item.name}</h6>
          <p class="card-text text-muted small text-truncate-2">${item.description||''}</p>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-bold text-success">RM ${parseFloat(item.price).toFixed(2)}</span>
            ${item.is_available==1 ? `
              <button class="btn btn-success btn-sm add-btn menu-add-desktop" data-id="${item.id}" data-name="${item.name.replace(/"/g,'&quot;')}" data-price="${item.price}"><i class="ti ti-plus"></i></button>
              <button class="btn btn-success btn-sm add-btn menu-add-mobile" data-id="${item.id}" data-name="${item.name.replace(/"/g,'&quot;')}" data-price="${item.price}"><i class="ti ti-plus"></i> Tambah</button>
            ` : '<span class="badge bg-secondary">Habis</span>'}
          </div>
        </div>
      </div>
    </div>`).join('');
  grid.querySelectorAll('.add-btn').forEach(btn => btn.addEventListener('click', async () => {
    const data = await cartAction('add', { item_id: btn.dataset.id, name: btn.dataset.name, price: btn.dataset.price, quantity: 1 });
    if (data.success) {
      updateCartBadge(data.total_items);
      const isMobile = btn.classList.contains('menu-add-mobile');
      btn.innerHTML = '<i class="ti ti-check"></i>' + (isMobile ? ' Ditambah' : '');
      setTimeout(() => {
        btn.innerHTML = isMobile ? '<i class="ti ti-plus"></i> Tambah' : '<i class="ti ti-plus"></i>';
      }, 800);
    }
  }));
  } catch {
    grid.innerHTML = '<div class="col-12 text-center text-danger">Ralat sambungan. Sila muat semula halaman.</div>';
  }
}
document.querySelectorAll('.category-btn').forEach(b => b.addEventListener('click', function(){
  document.querySelectorAll('.category-btn').forEach(x=>{x.classList.remove('active','btn-success');x.classList.add('btn-outline-success');});
  this.classList.add('active','btn-success'); this.classList.remove('btn-outline-success');
  renderMenu(this.dataset.cat);
}));
renderMenu();
</script>
</body>
</html>
