<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Dashboard';
$rid = activeRestaurantId();

$today = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE restaurant_id=$rid AND DATE(created_at)=CURDATE()");
$pending = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE restaurant_id=$rid AND status='pending'");
$transit = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE restaurant_id=$rid AND status IN ('picked_up','in_transit')");
$delivered = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE restaurant_id=$rid AND status='delivered' AND DATE(created_at)=CURDATE()");

$stats = [
    'today' => (int)mysqli_fetch_assoc($today)['c'],
    'pending' => (int)mysqli_fetch_assoc($pending)['c'],
    'transit' => (int)mysqli_fetch_assoc($transit)['c'],
    'delivered' => (int)mysqli_fetch_assoc($delivered)['c'],
];

$recent = mysqli_query($conn, "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count FROM orders o WHERE o.restaurant_id=$rid ORDER BY created_at DESC LIMIT 10");
$runners = mysqli_query($conn, "SELECT r.*, u.name FROM runners r JOIN users u ON u.id=r.user_id WHERE u.is_active=1 AND r.restaurant_id=$rid ORDER BY u.name");

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Dashboard</h4>
    <p>Ringkasan operasi hari ini</p>
  </div>
  <div class="page-header-right">
    <a href="/tracky/admin/admin_orders.php?status=pending" class="btn-icon primary"><i class="ti ti-shopping-bag"></i> Lihat Pending</a>
    <a href="/tracky/admin/admin_assign.php" class="btn-icon"><i class="ti ti-route"></i> Assign Runner</a>
  </div>
</div>

<div class="stats-grid" id="stats-row">
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-shopping-bag"></i></div>
      <div>
        <div class="stat-label">Orders Hari Ini</div>
        <div class="stat-value" id="stat-today"><?= $stats['today'] ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon amber"><i class="ti ti-clock-hour-4"></i></div>
      <div>
        <div class="stat-label">Pending</div>
        <div class="stat-value" id="stat-pending"><?= $stats['pending'] ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-bike"></i></div>
      <div>
        <div class="stat-label">In Transit</div>
        <div class="stat-value" id="stat-transit"><?= $stats['transit'] ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-circle-check"></i></div>
      <div>
        <div class="stat-label">Delivered Today</div>
        <div class="stat-value" id="stat-delivered"><?= $stats['delivered'] ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Recent Orders</span>
        <a href="/tracky/admin/admin_orders.php" class="btn-icon" style="font-size:11px">Lihat Semua <i class="ti ti-arrow-right"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Time</th><th></th></tr></thead><tbody id="recent-orders">
          <?php mysqli_data_seek($recent, 0); while ($o = mysqli_fetch_assoc($recent)): ?>
          <tr>
            <td><span style="font-size:13px;font-weight:700;color:var(--text)"><?= e($o['order_no']) ?></span></td>
            <td><?= e($o['customer_name']) ?></td>
            <td style="color:var(--muted)"><?= (int)$o['item_count'] ?> item</td>
            <td style="font-weight:700;color:var(--primary)"><?= formatPrice((float)$o['total_amount']) ?></td>
            <td><?= getStatusBadge($o['status']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= timeAgo($o['created_at']) ?></td>
            <td><div class="btn-actions">
              <a href="/tracky/admin/admin_tracking.php?order_id=<?= (int)$o['id'] ?>" class="btn-icon"><i class="ti ti-eye"></i></a>
              <?php if ($o['status']==='pending'): ?><a href="/tracky/admin/admin_assign.php?order_id=<?= (int)$o['id'] ?>" class="btn-icon primary"><i class="ti ti-route"></i></a><?php endif; ?>
            </div></td>
          </tr>
          <?php endwhile; ?>
        </tbody></table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Runner Status</div>
      <div class="card-body p-0">
        <?php mysqli_data_seek($runners, 0); $runner_list = []; while ($r = mysqli_fetch_assoc($runners)) $runner_list[] = $r; ?>
        <?php if (!$runner_list): ?>
        <div class="runner-empty">Tiada runner berdaftar</div>
        <?php else: ?>
        <?php foreach ($runner_list as $r): ?>
        <div class="runner-row">
          <div class="runner-avatar"><?= strtoupper(substr($r['name'],0,2)) ?></div>
          <div class="flex-grow-1">
            <div class="runner-row-name"><?= e($r['name']) ?></div>
            <div class="runner-row-meta"><?= e($r['vehicle_no']) ?></div>
          </div>
          <div class="runner-row-badge">
            <?php if ($r['status']==='online'): ?>
              <span class="badge-status status-assigned">Online</span>
            <?php elseif ($r['status']==='busy'): ?>
              <span class="badge-status status-assigned">Online</span>
            <?php else: ?>
              <span class="badge-status status-cancelled">Offline</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'HTML'
<script>
const statusBadgeMap = {
  pending: '<span class="badge-status status-pending">Pending</span>',
  assigned: '<span class="badge-status status-assigned">Assigned</span>',
  picked_up: '<span class="badge-status status-picked_up">Picked Up</span>',
  in_transit: '<span class="badge-status status-in_transit">In Transit</span>',
  delivered: '<span class="badge-status status-delivered">Delivered</span>',
  cancelled: '<span class="badge-status status-cancelled">Cancelled</span>'
};
async function refreshDashboard() {
  try {
    const r = await fetch('/tracky/api/admin_get_stats.php');
    const d = await r.json();
    if (!d.success) return;
    document.getElementById('stat-today').textContent = d.stats.today;
    document.getElementById('stat-pending').textContent = d.stats.pending;
    document.getElementById('stat-transit').textContent = d.stats.transit;
    document.getElementById('stat-delivered').textContent = d.stats.delivered;
    const tbody = document.getElementById('recent-orders');
    tbody.innerHTML = (d.recent || []).map(o => `
      <tr>
        <td><span style="font-size:13px;font-weight:700;color:var(--text)">${o.order_no}</span></td>
        <td>${o.customer_name}</td>
        <td style="color:var(--muted)">${o.item_count} item</td>
        <td style="font-weight:700;color:var(--primary)">RM ${parseFloat(o.total_amount).toFixed(2)}</td>
        <td>${statusBadgeMap[o.status] || o.status}</td>
        <td style="color:var(--muted);font-size:12px">${o.time_ago}</td>
        <td><div class="btn-actions">
          <a href="/tracky/admin/admin_tracking.php?order_id=${o.id}" class="btn-icon"><i class="ti ti-eye"></i></a>
          ${o.status==='pending'?`<a href="/tracky/admin/admin_assign.php?order_id=${o.id}" class="btn-icon primary"><i class="ti ti-route"></i></a>`:''}
        </div></td>
      </tr>`).join('');
  } catch (_) {}
}
setInterval(refreshDashboard, 30000);
</script>
HTML;
require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
