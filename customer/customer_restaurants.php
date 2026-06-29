<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
$active_cart_rid = custRestaurantId();

$restaurants = [];
$res = mysqli_query($conn, 'SELECT * FROM restaurants WHERE is_active = 1 ORDER BY name ASC');
while ($res && $row = mysqli_fetch_assoc($res)) {
    $restaurants[] = $row;
}

$total = count($restaurants);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1D9E75">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Tracky — Pilih Restoran</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="<?= asset('assets/css/customer.css') ?>" rel="stylesheet">
  <script>(function(){ if(localStorage.getItem('tracky-theme')==='light') document.documentElement.classList.add('pre-light'); })();</script>
  <style>html.pre-light body{background:#F8FAFC!important}</style>
  <style>
    /* ── RESTO DIRECTORY (landing-style) ── */
    .resto-hero {
      position: relative;
      overflow: hidden;
      padding: 64px 20px 54px;
      text-align: center;
      border-bottom: 1px solid var(--border);
    }
    .resto-hero-bg {
      position: absolute; inset: 0; pointer-events: none; z-index: 0;
      background:
        radial-gradient(ellipse 80% 60% at 50% -10%, rgba(29,158,117,0.20) 0%, transparent 70%),
        radial-gradient(ellipse 45% 45% at 85% 30%, rgba(29,158,117,0.10) 0%, transparent 60%);
    }
    .resto-hero-grid {
      position: absolute; inset: 0; pointer-events: none; z-index: 0;
      background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
      background-size: 54px 54px;
      -webkit-mask-image: radial-gradient(ellipse 65% 75% at 50% 40%, #000 10%, transparent 100%);
              mask-image: radial-gradient(ellipse 65% 75% at 50% 40%, #000 10%, transparent 100%);
    }
    .resto-hero-inner { position: relative; z-index: 1; max-width: 720px; margin: 0 auto; }
    .resto-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--green-dim);
      border: 1px solid var(--green-border);
      color: var(--green);
      font-size: 0.72rem; font-weight: 700;
      padding: 6px 14px; border-radius: 100px;
      letter-spacing: 0.08em; text-transform: uppercase;
      margin-bottom: 20px;
    }
    .resto-title {
      font-size: clamp(2rem, 5.5vw, 3.2rem);
      font-weight: 900; line-height: 1.06; letter-spacing: -1.5px;
      color: var(--text); margin: 0 0 16px;
    }
    .resto-title .hl {
      background: linear-gradient(135deg, var(--green), #34d399);
      -webkit-background-clip: text; background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .resto-sub {
      font-size: 1.02rem; color: var(--muted); line-height: 1.7;
      max-width: 520px; margin: 0 auto 30px;
    }
    .resto-search {
      position: relative; max-width: 460px; margin: 0 auto 26px;
    }
    .resto-search i {
      position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
      color: var(--muted); font-size: 1.15rem; pointer-events: none;
    }
    .resto-search input {
      width: 100%;
      background: var(--bg2);
      border: 1.5px solid var(--border);
      border-radius: 100px;
      padding: 14px 20px 14px 46px;
      color: var(--text); font-size: 0.95rem; font-weight: 500;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .resto-search input::placeholder { color: var(--muted); }
    .resto-search input:focus {
      border-color: var(--green);
      box-shadow: 0 0 0 4px var(--green-dim);
    }
    .resto-section { max-width: 1100px; margin: 0 auto; padding: 40px 20px 60px; }
    .resto-section-head {
      display: flex; align-items: flex-end; justify-content: space-between;
      gap: 12px; margin-bottom: 22px; flex-wrap: wrap;
    }
    .resto-section-label { font-size: 0.7rem; font-weight: 800; color: var(--green); letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 6px; }
    .resto-section-title { font-size: clamp(1.4rem, 3vw, 1.9rem); font-weight: 800; letter-spacing: -0.8px; color: var(--text); margin: 0; }
    .resto-count { font-size: 0.82rem; color: var(--muted); font-weight: 600; }

    .resto-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }

    .resto-card {
      --accent: var(--green);
      position: relative;
      display: flex; flex-direction: column;
      background: var(--bg2);
      border: 1px solid var(--border);
      border-radius: 20px;
      overflow: hidden;
      text-decoration: none;
      opacity: 0;
      transform: translateY(22px);
      animation: restoRise 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
      transition: transform 0.32s cubic-bezier(0.22,1,0.36,1), box-shadow 0.32s, border-color 0.32s;
      will-change: transform;
    }
    .resto-card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, transparent, var(--accent), transparent);
      transform: scaleX(0);
      transition: transform 0.4s ease;
      z-index: 3;
    }
    .resto-card:hover {
      transform: translateY(-8px);
      border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
      box-shadow: 0 20px 44px rgba(0,0,0,0.30), 0 0 0 1px color-mix(in srgb, var(--accent) 22%, transparent);
    }
    .resto-card:hover::before { transform: scaleX(1); }

    .resto-cover {
      position: relative;
      aspect-ratio: 16 / 8.4;
      background:
        linear-gradient(180deg, transparent 40%, rgba(0,0,0,0.45) 100%),
        color-mix(in srgb, var(--accent) 22%, var(--bg3));
      background-size: cover; background-position: center;
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .resto-cover-img {
      position: absolute; inset: 0;
      background-size: cover; background-position: center;
      transform: scale(1.02);
      transition: transform 0.55s cubic-bezier(0.22,1,0.36,1);
    }
    .resto-card:hover .resto-cover-img { transform: scale(1.1); }
    .resto-cover-fallback { position: relative; z-index: 1; color: color-mix(in srgb, var(--accent) 75%, #fff); font-size: 3rem; opacity: 0.7; }
    .resto-cover-shade {
      position: absolute; inset: 0; z-index: 2;
      background: linear-gradient(180deg, transparent 45%, rgba(0,0,0,0.5) 100%);
    }
    .resto-status {
      position: absolute; top: 12px; right: 12px; z-index: 3;
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 11px; font-weight: 800;
      padding: 5px 11px; border-radius: 100px;
      color: #fff; backdrop-filter: blur(6px);
    }
    .resto-status.open { background: rgba(29,158,117,0.92); }
    .resto-status.closed { background: rgba(107,114,128,0.92); }
    .resto-status .dot { width: 6px; height: 6px; border-radius: 50%; background: #fff; }
    .resto-status.open .dot { animation: restoPulse 1.8s infinite; }

    .resto-body { display: flex; gap: 14px; align-items: center; padding: 16px 16px 8px; }
    .resto-logo {
      width: 52px; height: 52px; flex-shrink: 0;
      border-radius: 14px;
      background: var(--accent); color: #fff;
      font-weight: 800; font-size: 22px;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
      box-shadow: 0 6px 16px color-mix(in srgb, var(--accent) 35%, transparent);
      transition: transform 0.3s;
    }
    .resto-card:hover .resto-logo { transform: scale(1.06) rotate(-3deg); }
    .resto-logo img { width: 100%; height: 100%; object-fit: cover; }
    .resto-info { min-width: 0; }
    .resto-name { font-size: 17px; font-weight: 800; color: var(--text); margin-bottom: 4px; line-height: 1.2; letter-spacing: -0.3px; }
    .resto-meta {
      font-size: 12.5px; color: var(--muted);
      display: flex; align-items: center; gap: 6px; margin-top: 3px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .resto-meta i { color: var(--accent); font-size: 14px; flex-shrink: 0; }

    .resto-foot {
      display: flex; align-items: center; justify-content: space-between;
      gap: 10px; margin-top: auto;
      padding: 14px 16px;
      border-top: 1px solid var(--border);
      font-size: 12.5px; color: var(--muted); font-weight: 600;
    }
    .resto-foot .fee i { color: var(--accent); }
    .resto-go {
      display: inline-flex; align-items: center; gap: 5px;
      color: var(--accent); font-weight: 800; font-size: 13px;
    }
    .resto-go i { transition: transform 0.3s; }
    .resto-card:hover .resto-go i { transform: translateX(4px); }

    .resto-empty, .resto-noresult {
      grid-column: 1 / -1;
      text-align: center; padding: 56px 20px; color: var(--muted);
    }
    .resto-empty i, .resto-noresult i { font-size: 2.6rem; display: block; margin-bottom: 12px; color: var(--green); opacity: 0.7; }

    @keyframes restoRise { to { opacity: 1; transform: translateY(0); } }
    @keyframes restoPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.25; } }
    @keyframes restoHeroIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    .resto-hero-inner > * { animation: restoHeroIn 0.7s cubic-bezier(0.22,1,0.36,1) both; }
    .resto-hero-inner > *:nth-child(1) { animation-delay: 0.02s; }
    .resto-hero-inner > *:nth-child(2) { animation-delay: 0.08s; }
    .resto-hero-inner > *:nth-child(3) { animation-delay: 0.14s; }
    .resto-hero-inner > *:nth-child(4) { animation-delay: 0.20s; }
    .resto-hero-inner > *:nth-child(5) { animation-delay: 0.26s; }

    @media (prefers-reduced-motion: reduce) {
      .resto-card, .resto-hero-inner > * { animation: none !important; opacity: 1 !important; transform: none !important; }
    }
    @media (max-width: 560px) {
      .resto-hero { padding: 44px 16px 38px; }
      .resto-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<nav class="customer-navbar">
  <a class="customer-brand" href="/tracky/" title="Laman Utama Tracky">
    <img src="/tracky/assets/img/icon.png" alt="Tracky" class="brand-img"> Tracky
  </a>
  <div class="customer-nav-actions">
    <a href="/tracky/" class="nav-icon-btn" title="Laman Utama"><i class="ti ti-home"></i></a>
    <a href="/tracky/customer/customer_track.php" class="nav-icon-btn" title="Track pesanan"><i class="ti ti-map-pin"></i></a>
    <button class="customer-theme-btn" id="themeToggle" onclick="toggleTheme()"><i class="ti ti-sun" id="themeIcon"></i></button>
    <?php if ($cart_count): ?>
    <a href="/tracky/customer/customer_cart.php" class="nav-cart-btn">
      <i class="ti ti-shopping-cart"></i>
      <span class="nav-cart-count" data-cart-count><?= $cart_count ?></span>
    </a>
    <?php endif; ?>
  </div>
</nav>

<header class="resto-hero">
  <div class="resto-hero-bg"></div>
  <div class="resto-hero-grid"></div>
  <div class="resto-hero-inner">
    <span class="resto-badge"><i class="ti ti-tools-kitchen-2"></i> Tracky Food</span>
    <h1 class="resto-title">Pesan dari restoran <span class="hl">kegemaran anda</span></h1>
    <p class="resto-sub">Pelbagai restoran, satu platform. Pilih kedai, terokai menu, dan kami hantar terus ke pintu rumah anda.</p>
    <div class="resto-search">
      <i class="ti ti-search"></i>
      <input type="text" id="restoSearch" placeholder="Cari restoran..." autocomplete="off" aria-label="Cari restoran">
    </div>
  </div>
</header>

<section class="resto-section">
  <div class="resto-section-head">
    <div>
      <div class="resto-section-label">Terokai</div>
      <h2 class="resto-section-title">Restoran Tersedia</h2>
    </div>
    <span class="resto-count" id="restoCount"><?= $total ?> restoran</span>
  </div>

  <div class="resto-grid" id="restoGrid">
    <?php if (!$restaurants): ?>
    <div class="resto-empty">
      <i class="ti ti-mood-empty"></i>
      Tiada restoran tersedia buat masa ini.
    </div>
    <?php else: ?>
    <?php foreach ($restaurants as $i => $r):
      $accent = $r['accent_color'] ?: '#1D9E75';
      $cover = restaurantAsset($r['cover_image'] ?? null);
      $logo = restaurantAsset($r['logo'] ?? null);
      $open = (int) ($r['is_open'] ?? 1) === 1;
      $delay = min($i * 0.07, 0.5);
    ?>
    <a class="resto-card" data-name="<?= e(strtolower($r['name'])) ?>"
       href="/tracky/customer/customer_menu.php?restaurant=<?= (int)$r['id'] ?>"
       style="--accent: <?= e($accent) ?>; animation-delay: <?= $delay ?>s">
      <div class="resto-cover">
        <?php if ($cover): ?>
        <div class="resto-cover-img" style="background-image:url('<?= e($cover) ?>')"></div>
        <div class="resto-cover-shade"></div>
        <?php else: ?>
        <i class="ti ti-tools-kitchen-2 resto-cover-fallback"></i>
        <?php endif; ?>
        <span class="resto-status <?= $open ? 'open' : 'closed' ?>"><span class="dot"></span><?= $open ? 'Dibuka' : 'Ditutup' ?></span>
      </div>
      <div class="resto-body">
        <div class="resto-logo">
          <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php else: ?><?= strtoupper(substr($r['name'], 0, 1)) ?><?php endif; ?>
        </div>
        <div class="resto-info">
          <div class="resto-name"><?= e($r['name']) ?></div>
          <div class="resto-meta"><i class="ti ti-clock"></i> <?= e($r['operating_hours'] ?? '8:00 AM - 10:00 PM') ?></div>
          <?php if (!empty($r['address'])): ?><div class="resto-meta"><i class="ti ti-map-pin"></i> <?= e($r['address']) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="resto-foot">
        <span class="fee"><i class="ti ti-truck"></i> Penghantaran RM <?= number_format((float)($r['delivery_fee'] ?? 5), 2) ?></span>
        <span class="resto-go">Lihat Menu <i class="ti ti-arrow-right"></i></span>
      </div>
    </a>
    <?php endforeach; ?>
    <div class="resto-noresult" id="restoNoResult" style="display:none">
      <i class="ti ti-search-off"></i>
      Tiada restoran sepadan dengan carian anda.
    </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function () {
  const input = document.getElementById('restoSearch');
  const grid = document.getElementById('restoGrid');
  if (!input || !grid) return;
  const cards = Array.from(grid.querySelectorAll('.resto-card'));
  const countEl = document.getElementById('restoCount');
  const noResult = document.getElementById('restoNoResult');

  function plural(n) { return n + ' restoran'; }

  input.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    let visible = 0;
    cards.forEach(card => {
      const match = !q || (card.dataset.name || '').includes(q);
      card.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    if (countEl) countEl.textContent = plural(visible);
    if (noResult) noResult.style.display = visible === 0 ? '' : 'none';
  });
})();
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
