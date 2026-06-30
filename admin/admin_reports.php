<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/block_staff.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Laporan';

$rid = activeRestaurantId();
$range = $_GET['range'] ?? 'week';
$where = match($range) {
    'today' => "DATE(o.created_at)=CURDATE()",
    'month' => "MONTH(o.created_at)=MONTH(CURDATE()) AND YEAR(o.created_at)=YEAR(CURDATE())",
    default => "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
};
$where .= " AND o.restaurant_id=$rid";

$revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) rev, COUNT(*) cnt FROM orders o WHERE $where AND status='delivered'"));
$by_status = mysqli_query($conn, "SELECT status, COUNT(*) c FROM orders o WHERE $where GROUP BY status");
$top_items = mysqli_query($conn, "SELECT oi.item_name, SUM(oi.quantity) qty, SUM(oi.subtotal) rev FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $where GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5");

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Laporan</h4>
    <p>Ringkasan jualan dan prestasi</p>
  </div>
  <div class="page-header-right">
    <div class="orders-status-nav" style="margin-bottom:0">
      <?php foreach (['today'=>'Hari Ini','week'=>'Minggu Ini','month'=>'Bulan Ini'] as $k=>$l): ?>
      <a href="?range=<?= $k ?>" class="status-tab <?= $range===$k?'active':'' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <button class="btn-icon" onclick="window.print()"><i class="ti ti-printer"></i> Print</button>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-cash"></i></div>
      <div>
        <div class="stat-label">Revenue</div>
        <div class="stat-value"><?= formatPrice((float)$revenue['rev']) ?></div>
        <div class="stat-sub">Order delivered sahaja</div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-circle-check"></i></div>
      <div>
        <div class="stat-label">Delivered Orders</div>
        <div class="stat-value"><?= (int)$revenue['cnt'] ?></div>
        <div class="stat-sub">Dalam tempoh dipilih</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-12 col-lg-7">
    <div class="card">
      <div class="card-header">Orders by Status</div>
      <div class="card-body" style="height:280px;position:relative"><canvas id="statusChart"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card h-100">
      <div class="card-header">Top Selling Items</div>
      <div class="table-responsive">
        <table class="table mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Revenue</th></tr></thead><tbody>
        <?php while ($t = mysqli_fetch_assoc($top_items)): ?>
        <tr>
          <td style="font-weight:600;color:var(--text)"><?= e($t['item_name']) ?></td>
          <td style="color:var(--muted)"><?= (int)$t['qty'] ?></td>
          <td style="font-weight:700;color:var(--primary)"><?= formatPrice((float)$t['rev']) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels = []; const data = [];
<?php mysqli_data_seek($by_status, 0); while ($s = mysqli_fetch_assoc($by_status)): ?>
labels.push('<?= ucfirst(e($s['status'])) ?>'); data.push(<?= (int)$s['c'] ?>);
<?php endwhile; ?>
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: { labels, datasets: [{ data, backgroundColor: ['#F59E0B','#3B82F6','#7C3AED','#06B6D4','#1D9E75','#EF4444'], borderWidth: 0, hoverOffset: 8 }] },
  options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: '#6B7280', font: { size: 12, family: 'Inter' }, padding: 16 } } } }
});
</script>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
