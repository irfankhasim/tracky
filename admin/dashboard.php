<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Dashboard';

$today = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE DATE(created_at)=CURDATE()");
$pending = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='pending'");
$transit = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status IN ('picked_up','in_transit')");
$delivered = mysqli_query($conn, "SELECT COUNT(*) c FROM orders WHERE status='delivered' AND DATE(created_at)=CURDATE()");

$stats = [
    'today' => (int)mysqli_fetch_assoc($today)['c'],
    'pending' => (int)mysqli_fetch_assoc($pending)['c'],
    'transit' => (int)mysqli_fetch_assoc($transit)['c'],
    'delivered' => (int)mysqli_fetch_assoc($delivered)['c'],
];

$recent = mysqli_query($conn, "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_count FROM orders o ORDER BY created_at DESC LIMIT 10");
$runners = mysqli_query($conn, "SELECT r.*, u.name FROM runners r JOIN users u ON u.id=r.user_id WHERE u.is_active=1 ORDER BY u.name");

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="stats-grid" id="stats-row">
  <div class="stat-card"><div class="stat-label">Orders Hari Ini</div><div class="stat-value" id="stat-today"><?= $stats['today'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value text-warning" id="stat-pending"><?= $stats['pending'] ?></div></div>
  <div class="stat-card"><div class="stat-label">In Transit</div><div class="stat-value text-info" id="stat-transit"><?= $stats['transit'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Delivered Today</div><div class="stat-value text-success" id="stat-delivered"><?= $stats['delivered'] ?></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card"><div class="card-header">Recent Orders</div><div class="table-responsive">
      <table class="table mb-0 table-responsive-cards"><thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Time</th><th></th></tr></thead><tbody id="recent-orders">
        <?php mysqli_data_seek($recent, 0); while ($o = mysqli_fetch_assoc($recent)): ?>
        <tr>
          <td data-label="Order"><?= e($o['order_no']) ?></td>
          <td data-label="Customer"><?= e($o['customer_name']) ?></td>
          <td data-label="Items"><?= (int)$o['item_count'] ?></td>
          <td data-label="Total"><?= formatPrice((float)$o['total_amount']) ?></td>
          <td data-label="Status"><?= getStatusBadge($o['status']) ?></td>
          <td data-label="Time" class="small"><?= timeAgo($o['created_at']) ?></td>
          <td data-label="Action" class="td-actions"><a href="/tracky/admin/tracking.php?order_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon"><i class="ti ti-eye"></i> <span class="btn-text">View</span></a></td>
        </tr>
        <?php endwhile; ?>
      </tbody></table>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header">Active Runners</div><div class="card-body p-0">
      <div class="runners-scroll-mobile runners-scroll p-3">
        <?php mysqli_data_seek($runners, 0); while ($r = mysqli_fetch_assoc($runners)): ?>
        <div class="runner-chip">
          <div class="fw-semibold small"><?= e($r['name']) ?></div>
          <span class="badge bg-<?= $r['status']==='online'?'success':($r['status']==='busy'?'warning text-dark':'secondary') ?>"><?= e($r['status']) ?></span>
        </div>
        <?php endwhile; ?>
      </div>
      <div class="runners-scroll-desktop p-3">
        <?php mysqli_data_seek($runners, 0); while ($r = mysqli_fetch_assoc($runners)): ?>
        <div class="d-flex justify-content-between mb-2">
          <span><?= e($r['name']) ?></span>
          <span class="badge bg-<?= $r['status']==='online'?'success':($r['status']==='busy'?'warning text-dark':'secondary') ?>"><?= e($r['status']) ?></span>
        </div>
        <?php endwhile; ?>
      </div>
    </div></div>
  </div>
</div>
<script>
const statusBadges = {
  pending: '<span class="badge bg-warning text-dark">Pending</span>',
  assigned: '<span class="badge bg-primary">Assigned</span>',
  picked_up: '<span class="badge bg-purple">Picked Up</span>',
  in_transit: '<span class="badge bg-info">In Transit</span>',
  delivered: '<span class="badge bg-success">Delivered</span>',
  cancelled: '<span class="badge bg-danger">Cancelled</span>'
};

async function refreshDashboard() {
  try {
    const r = await fetch('/tracky/api/get_dashboard_stats.php');
    const d = await r.json();
    if (!d.success) return;
    document.getElementById('stat-today').textContent = d.stats.today;
    document.getElementById('stat-pending').textContent = d.stats.pending;
    document.getElementById('stat-transit').textContent = d.stats.transit;
    document.getElementById('stat-delivered').textContent = d.stats.delivered;
    const tbody = document.getElementById('recent-orders');
    tbody.innerHTML = (d.recent || []).map(o => `
      <tr>
        <td data-label="Order">${o.order_no}</td>
        <td data-label="Customer">${o.customer_name}</td>
        <td data-label="Items">${o.item_count}</td>
        <td data-label="Total">RM ${parseFloat(o.total_amount).toFixed(2)}</td>
        <td data-label="Status">${statusBadges[o.status] || o.status}</td>
        <td data-label="Time" class="small">${o.time_ago}</td>
        <td data-label="Action" class="td-actions"><a href="/tracky/admin/tracking.php?order_id=${o.id}" class="btn btn-sm btn-outline-secondary btn-action-icon"><i class="ti ti-eye"></i> <span class="btn-text">View</span></a></td>
      </tr>`).join('');
  } catch (_) {}
}
setInterval(refreshDashboard, 30000);
</script>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
