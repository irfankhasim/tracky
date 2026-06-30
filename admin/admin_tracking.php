<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Tracking';
$rid = activeRestaurantId();

$order_id = (int)($_GET['order_id'] ?? 0);
$orders_list = mysqli_query($conn, "SELECT id, order_no, customer_name, status FROM orders WHERE restaurant_id=$rid ORDER BY created_at DESC LIMIT 50");
if ($order_id < 1) {
    $first = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM orders WHERE restaurant_id=$rid AND status NOT IN ('delivered','cancelled') ORDER BY created_at DESC LIMIT 1"));
    $order_id = (int)($first['id'] ?? 0);
}

$order = null; $delivery = null; $items = []; $logs = [];
if ($order_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM orders WHERE id = ? AND restaurant_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $rid);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($order) {
        $items = getOrderItems($conn, $order_id);
        $dstmt = mysqli_prepare($conn, "SELECT d.*, u.name runner_name, r.phone runner_phone, r.vehicle_no FROM deliveries d JOIN runners r ON r.id=d.runner_id JOIN users u ON u.id=r.user_id WHERE d.order_id=? ORDER BY d.id DESC LIMIT 1");
        mysqli_stmt_bind_param($dstmt, 'i', $order_id);
        mysqli_stmt_execute($dstmt);
        $delivery = mysqli_fetch_assoc(mysqli_stmt_get_result($dstmt));
        mysqli_stmt_close($dstmt);
        $lstmt = mysqli_prepare($conn, 'SELECT * FROM status_logs WHERE order_id=? ORDER BY changed_at ASC');
        mysqli_stmt_bind_param($lstmt, 'i', $order_id);
        mysqli_stmt_execute($lstmt);
        $lres = mysqli_stmt_get_result($lstmt);
        while ($l = mysqli_fetch_assoc($lres)) $logs[] = $l;
        mysqli_stmt_close($lstmt);
    }
}
$steps = ['pending','assigned','picked_up','in_transit','delivered'];
$map = $order ? mapEmbedUrl($order['delivery_address'], $order['delivery_lat'] ?? null, $order['delivery_lng'] ?? null) : '';

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Tracking</h4>
    <p>Pantau status penghantaran order</p>
  </div>
</div>

<div class="tracking-select-wrap">
  <label>Pilih Order</label>
  <select class="form-select" id="order-select" style="max-width:480px">
    <?php mysqli_data_seek($orders_list, 0); while ($ol = mysqli_fetch_assoc($orders_list)): ?>
    <option value="<?= (int)$ol['id'] ?>" <?= $ol['id']==$order_id?'selected':'' ?>><?= e($ol['order_no']) ?> — <?= e($ol['customer_name']) ?> (<?= e($ol['status']) ?>)</option>
    <?php endwhile; ?>
  </select>
</div>

<?php if (!$order): ?>
<div style="text-align:center;padding:60px;color:var(--muted)">
  <i class="ti ti-map-pin" style="font-size:40px"></i>
  <p style="margin-top:16px;font-weight:700;color:var(--text)">Tiada order dipilih</p>
  <p style="font-size:13px">Pilih order dari senarai di atas</p>
</div>
<?php else: ?>
<div class="two-col-layout" id="tracking-panel">
  <div>
    <div class="card">
      <div class="card-header">Timeline — <?= e($order['order_no']) ?></div>
      <div class="card-body">
        <ul class="timeline">
          <?php if ($order['status'] === 'cancelled'): ?>
          <li class="done active"><strong><?= getStatusLabel('cancelled') ?></strong></li>
          <?php else:
            $statusIdx = array_search($order['status'], $steps, true);
            if ($statusIdx === false) $statusIdx = -1;
            foreach ($steps as $s):
              $done = $statusIdx >= array_search($s, $steps, true);
          ?>
          <li class="<?= $done ? 'done active' : '' ?>">
            <strong><?= getStatusLabel($s) ?></strong>
            <?php foreach ($logs as $log): if ($log['new_status'] === $s): ?>
            <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= date('d/m/Y H:i', strtotime($log['changed_at'])) ?></div>
            <?php endif; endforeach; ?>
          </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div>
    <div class="card mb-3">
      <div class="card-header">Butiran Order</div>
      <div class="card-body">
        <div style="margin-bottom:14px">
          <div style="font-weight:700;font-size:15px;color:var(--text)"><?= e($order['customer_name']) ?></div>
          <div style="font-size:13px;color:var(--muted);margin:3px 0"><?= e($order['customer_phone']) ?></div>
          <div style="font-size:13px;color:var(--muted)"><?= e($order['delivery_address']) ?></div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:12px;margin-bottom:12px">
          <?php foreach ($items as $i): ?>
          <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-2);margin-bottom:4px">
            <span><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></span>
            <span><?= formatPrice((float)$i['subtotal']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);padding-top:12px">
          <span style="font-size:16px;font-weight:800;color:var(--primary)"><?= formatPrice((float)$order['total_amount']) ?></span>
          <?= getStatusBadge($order['status']) ?>
        </div>
        <?php if (in_array($order['status'], ['assigned','picked_up','in_transit'], true)): ?>
        <button type="button" class="btn-tracky mt-3" id="btn-mark-delivered" style="width:100%"><i class="ti ti-check"></i> Mark as Delivered</button>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($delivery): ?>
    <div class="card mb-3">
      <div class="card-header">Runner</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="runner-avatar"><?= strtoupper(substr($delivery['runner_name'],0,2)) ?></div>
          <div>
            <div style="font-weight:700;color:var(--text)"><?= e($delivery['runner_name']) ?></div>
            <div style="font-size:13px;color:var(--muted)"><?= e($delivery['runner_phone']) ?> · <?= e($delivery['vehicle_no']) ?></div>
          </div>
        </div>
        <a href="tel:<?= e(preg_replace('/\s+/','',$delivery['runner_phone'])) ?>" class="btn-icon primary" style="display:inline-flex"><i class="ti ti-phone"></i> Hubungi Runner</a>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($order): ?>
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <div class="map-container"><div id="amap" style="width:100%;height:100%;min-height:260px;border-radius:10px;overflow:hidden"></div></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<script>
document.getElementById('order-select').onchange = e => location.href = '?order_id=' + e.target.value;
<?php if ($order): ?>
const markBtn = document.getElementById('btn-mark-delivered');
if (markBtn) {
  markBtn.onclick = async () => {
    if (!confirm('Tandakan order ini sebagai dihantar?')) return;
    markBtn.disabled = true;
    const res = await apiPost('/tracky/api/admin_mark_delivered.php', { order_id: <?= (int)$order_id ?> });
    if (res.success) { showToast(res.message); setTimeout(() => location.reload(), 600); }
    else { showToast(res.message || 'Gagal', 'danger'); markBtn.disabled = false; }
  };
}
const _trackedStatus = '<?= e($order['status'] ?? '') ?>';
setInterval(async () => {
  try {
    const id = document.getElementById('order-select').value;
    const r = await fetch('/tracky/api/customer_get_tracking.php?order_id=' + id);
    const d = await r.json();
    if (d.success && d.order && d.order.status !== _trackedStatus) location.reload();
  } catch (_) {}
}, 10000);
<?php endif; ?>
</script>
<?php if ($order): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
  const el = document.getElementById('amap');
  if (!el || typeof L === 'undefined') return;
  const lat = <?= is_numeric($order['delivery_lat'] ?? null) ? $order['delivery_lat'] : 'null' ?>;
  const lng = <?= is_numeric($order['delivery_lng'] ?? null) ? $order['delivery_lng'] : 'null' ?>;
  const address = <?= json_encode($order['delivery_address']) ?>;
  function draw(la, lo) {
    const map = L.map('amap').setView([la, lo], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    L.marker([la, lo]).addTo(map).bindPopup('Lokasi penghantaran');
    setTimeout(() => map.invalidateSize(), 200);
  }
  if (lat !== null && lng !== null) { draw(lat, lng); }
  else if (address) {
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=my&q=' + encodeURIComponent(address), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { if (d && d.length) draw(+d[0].lat, +d[0].lon); else el.style.display = 'none'; })
      .catch(() => { el.style.display = 'none'; });
  } else { el.style.display = 'none'; }
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
