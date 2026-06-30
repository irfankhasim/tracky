<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
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
$map_embed = $order ? mapEmbedUrl($order['delivery_address'], $order['delivery_lat'] ?? null, $order['delivery_lng'] ?? null) : '';
$order_restaurant = $order ? getRestaurant($conn, (int) ($order['restaurant_id'] ?? 0)) : null;
$accent_color = $order_restaurant['accent_color'] ?? '#1D9E75';

$statusIcons = ['pending'=>'ti-clock','assigned'=>'ti-user-check','picked_up'=>'ti-package','in_transit'=>'ti-bike','delivered'=>'ti-circle-check'];
$statusIcon = $statusIcons[$status] ?? 'ti-clock';
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Track Pesanan — Tracky</title>
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
    <a href="/tracky/customer/customer_restaurants.php" class="nav-icon-btn" title="Pilih restoran"><i class="ti ti-building-store"></i></a>
    <button class="customer-theme-btn" id="themeToggle" onclick="toggleTheme()"><i class="ti ti-sun" id="themeIcon"></i></button>
  </div>
</nav>

<div class="customer-page-wrap track-wrap">

  <?php if (!$order_no): // ── INITIAL / EMPTY STATE ── ?>
  <div class="track-landing">
    <div class="track-landing-illustration">
      <span class="pulse-ring"></span>
      <span class="pulse-ring delay"></span>
      <div class="track-landing-icon"><i class="ti ti-map-pin"></i></div>
    </div>

    <span class="hero-label"><i class="ti ti-route"></i> Jejak Penghantaran</span>
    <h1 class="track-landing-title">Jejaki Pesanan Anda <span>Secara Langsung</span></h1>
    <p class="track-landing-sub">Masukkan nombor pesanan untuk melihat status penghantaran, lokasi, dan butiran pesanan — dikemas kini secara real-time.</p>

    <form method="get" class="track-search-form">
      <div class="track-search-input">
        <i class="ti ti-receipt"></i>
        <input type="text" name="order_no" placeholder="Cth: ORD-20260628-1234" value="<?= e($order_no) ?>" required autofocus>
      </div>
      <button type="submit" class="btn-tracky track-search-btn"><i class="ti ti-search"></i> Jejak Sekarang</button>
    </form>
    <p class="track-search-hint"><i class="ti ti-info-circle"></i> Nombor pesanan terdapat pada halaman pengesahan selepas anda membuat pesanan.</p>

    <div class="track-steps">
      <div class="track-step">
        <div class="track-step-icon"><i class="ti ti-receipt"></i></div>
        <div class="track-step-title">Masukkan No. Pesanan</div>
        <div class="track-step-desc">Taip nombor pesanan yang anda terima</div>
      </div>
      <div class="track-step">
        <div class="track-step-icon"><i class="ti ti-timeline"></i></div>
        <div class="track-step-title">Lihat Status</div>
        <div class="track-step-desc">Pantau setiap peringkat penghantaran</div>
      </div>
      <div class="track-step">
        <div class="track-step-icon"><i class="ti ti-bike"></i></div>
        <div class="track-step-title">Jejak Runner</div>
        <div class="track-step-desc">Tahu siapa yang menghantar pesanan</div>
      </div>
    </div>

    <div class="track-landing-foot">
      <span>Belum buat pesanan?</span>
      <a href="/tracky/customer/customer_restaurants.php" class="btn-tracky-outline track-foot-btn"><i class="ti ti-shopping-bag"></i> Lihat Restoran</a>
    </div>
  </div>

  <?php elseif ($order_no && !$order): // ── NOT FOUND ── ?>
  <div class="track-landing">
    <div class="track-landing-illustration">
      <div class="track-landing-icon not-found"><i class="ti ti-search-off"></i></div>
    </div>
    <h1 class="track-landing-title">Pesanan Tidak Dijumpai</h1>
    <p class="track-landing-sub">Kami tidak menemui pesanan dengan nombor <strong style="color:var(--text)"><?= e($order_no) ?></strong>. Sila semak semula nombor anda dan cuba lagi.</p>

    <form method="get" class="track-search-form">
      <div class="track-search-input">
        <i class="ti ti-receipt"></i>
        <input type="text" name="order_no" placeholder="Cth: ORD-20260628-1234" value="<?= e($order_no) ?>" required autofocus>
      </div>
      <button type="submit" class="btn-tracky track-search-btn"><i class="ti ti-search"></i> Cuba Lagi</button>
    </form>

    <div class="track-landing-foot">
      <a href="/tracky/customer/customer_restaurants.php" class="btn-tracky-outline track-foot-btn"><i class="ti ti-arrow-left"></i> Kembali ke Restoran</a>
    </div>
  </div>

  <?php else: // ── ORDER FOUND ── ?>

  <form method="get" class="track-search-bar">
    <div class="track-search-input">
      <i class="ti ti-receipt"></i>
      <input type="text" name="order_no" placeholder="No. pesanan" value="<?= e($order_no) ?>" required>
    </div>
    <button type="submit" class="btn-tracky" style="width:auto;padding:0 18px;white-space:nowrap"><i class="ti ti-search"></i></button>
  </form>

  <div class="track-page-wrap">
    <!-- Status Hero -->
    <div class="track-hero" style="margin-bottom:12px">
      <div class="track-status-icon <?= in_array($status,['delivered']) ? 'delivered' : (in_array($status,['assigned','picked_up','in_transit']) ? 'delivering' : 'pending') ?>">
        <i class="ti <?= $statusIcon ?>"></i>
      </div>
      <div class="track-status-label">Status Pesanan</div>
      <div class="track-status-val"><?= getStatusLabel($status) ?></div>
      <div class="track-order-no"><?= e($order['order_no']) ?> · <?= timeAgo($order['created_at']) ?></div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <!-- Timeline -->
        <div class="section-card">
          <div class="section-card-title"><i class="ti ti-timeline"></i> Timeline</div>
          <ul class="timeline">
            <?php if ($status === 'cancelled'): ?>
            <li class="done active"><div class="timeline-title"><?= getStatusLabel('cancelled') ?></div></li>
            <?php else:
              $statusIdx = array_search($status, $steps, true);
              if ($statusIdx === false) $statusIdx = -1;
              foreach ($steps as $s):
                if ($s === 'cancelled') continue;
                $sIdx = array_search($s, $steps, true);
                $isDone = $statusIdx >= $sIdx;
                $isActive = $statusIdx === $sIdx;
            ?>
            <li class="<?= $isDone ? ($isActive ? 'active' : 'done') : '' ?>">
              <div class="timeline-title"><?= getStatusLabel($s) ?></div>
            </li>
            <?php endforeach; endif; ?>
          </ul>
        </div>
      </div>

      <div class="col-md-6">
        <!-- Order Details -->
        <div class="section-card" style="margin-bottom:0;height:100%">
          <div class="section-card-title"><i class="ti ti-receipt"></i> Butiran</div>
          <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px"><?= e($order['customer_name']) ?></div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:4px"><?= e($order['customer_phone']) ?></div>
          <div style="font-size:12px;color:var(--muted);margin-bottom:12px"><?= e($order['delivery_address']) ?></div>
          <div style="border-top:1px solid var(--border);padding-top:10px">
            <?php foreach ($items as $i): ?>
            <div style="font-size:13px;color:var(--text-2);padding:3px 0"><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div>
            <?php endforeach; ?>
            <div style="font-size:16px;font-weight:800;color:var(--green);margin-top:8px"><?= formatPrice((float)$order['total_amount']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($delivery): ?>
    <div class="section-card" style="margin-top:12px;margin-bottom:0">
      <div class="section-card-title"><i class="ti ti-bike"></i> Runner</div>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div style="font-size:15px;font-weight:700;color:var(--text)"><?= e($delivery['runner_name']) ?></div>
        <a href="tel:<?= e(preg_replace('/\s+/','',$delivery['runner_phone'])) ?>" class="btn-tracky-outline" style="display:inline-flex;width:auto;padding:10px 18px">
          <i class="ti ti-phone"></i> Hubungi Runner
        </a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($order): ?>
    <div style="margin-top:12px">
      <div id="trk-map" style="width:100%;height:260px;border-radius:14px;overflow:hidden;border:1.5px solid var(--border);background:var(--bg3)"></div>
      <div id="trk-map-fallback" class="d-none small text-muted" style="margin-top:6px"><i class="ti ti-map-off"></i> Lokasi peta tidak tersedia untuk alamat ini.</div>
    </div>
    <?php endif; ?>

    <div style="margin-top:16px">
      <a href="/tracky/customer/customer_menu.php?restaurant=<?= (int)($order['restaurant_id'] ?? 0) ?>" class="btn-tracky-outline">
        <i class="ti ti-arrow-left"></i> Kembali ke Menu
      </a>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php if ($order): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  const el = document.getElementById('trk-map');
  if (!el || typeof L === 'undefined') return;
  const lat = <?= is_numeric($order['delivery_lat'] ?? null) ? $order['delivery_lat'] : 'null' ?>;
  const lng = <?= is_numeric($order['delivery_lng'] ?? null) ? $order['delivery_lng'] : 'null' ?>;
  const address = <?= json_encode($order['delivery_address']) ?>;
  let map;

  function draw(la, lo) {
    map = L.map('trk-map').setView([la, lo], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    L.marker([la, lo]).addTo(map).bindPopup('Lokasi penghantaran');
    setTimeout(() => map.invalidateSize(), 200);
  }
  function fail() {
    el.classList.add('d-none');
    document.getElementById('trk-map-fallback').classList.remove('d-none');
  }

  if (lat !== null && lng !== null) {
    draw(lat, lng);
  } else if (address) {
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=my&q=' + encodeURIComponent(address), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(d => { if (d && d.length) draw(+d[0].lat, +d[0].lon); else fail(); })
      .catch(fail);
  } else {
    fail();
  }
})();
</script>
<?php endif; ?>
<?php if ($order && $status !== 'delivered' && $status !== 'cancelled'): ?>
<script>setInterval(()=>location.reload(),15000);</script>
<?php endif; ?>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
