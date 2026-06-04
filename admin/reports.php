<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Laporan';

$range = $_GET['range'] ?? 'week';
$where = match($range) {
    'today' => "DATE(o.created_at)=CURDATE()",
    'month' => "MONTH(o.created_at)=MONTH(CURDATE()) AND YEAR(o.created_at)=YEAR(CURDATE())",
    default => "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
};

$revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) rev, COUNT(*) cnt FROM orders o WHERE $where AND status='delivered'"));
$by_status = mysqli_query($conn, "SELECT status, COUNT(*) c FROM orders o WHERE $where GROUP BY status");
$top_items = mysqli_query($conn, "SELECT oi.item_name, SUM(oi.quantity) qty, SUM(oi.subtotal) rev FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $where GROUP BY oi.item_name ORDER BY qty DESC LIMIT 5");

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <div class="btn-group">
    <?php foreach (['today'=>'Hari Ini','week'=>'Minggu','month'=>'Bulan'] as $k=>$l): ?>
    <a href="?range=<?= $k ?>" class="btn btn-sm <?= $range===$k?'btn-tracky':'btn-outline-secondary' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="ti ti-printer"></i> Print</button>
</div>
<div class="stats-grid mb-4" style="grid-template-columns: repeat(2, 1fr);">
  <div class="stat-card"><div class="stat-label">Revenue</div><div class="stat-value text-success"><?= formatPrice((float)$revenue['rev']) ?></div><div class="stat-sub"><?= (int)$revenue['cnt'] ?> delivered orders</div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8"><div class="card"><div class="card-header">Orders by Status</div><div class="card-body chart-wrap" style="height:280px"><canvas id="statusChart"></canvas></div></div></div>
</div>
<div class="row g-4">
  <div class="col-lg-6"><div class="card"><div class="card-header">Top Selling Items</div><div class="table-responsive"><table class="table mb-0 table-responsive-cards"><thead><tr><th>Item</th><th>Qty</th><th>Revenue</th></tr></thead><tbody>
    <?php while ($t = mysqli_fetch_assoc($top_items)): ?><tr><td data-label="Item"><?= e($t['item_name']) ?></td><td data-label="Qty"><?= (int)$t['qty'] ?></td><td data-label="Revenue"><?= formatPrice((float)$t['rev']) ?></td></tr><?php endwhile; ?>
  </tbody></table></div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const labels = []; const data = [];
<?php mysqli_data_seek($by_status, 0); while ($s = mysqli_fetch_assoc($by_status)): ?>
labels.push('<?= e($s['status']) ?>'); data.push(<?= (int)$s['c'] ?>);
<?php endwhile; ?>
new Chart(document.getElementById('statusChart'), { type:'doughnut', data:{ labels, datasets:[{ data, backgroundColor:['#f59e0b','#3b82f6','#7c3aed','#06b6d4','#1D9E75','#ef4444'] }] }, options:{ maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } } });
</script>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
