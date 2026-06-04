<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = (int)$_POST['cancel_order'];
    $stmt = mysqli_prepare($conn, "UPDATE orders SET status='cancelled' WHERE id=? AND status='pending'");
    mysqli_stmt_bind_param($stmt, 'i', $oid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: /tracky/admin/orders.php');
    exit;
}

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;
$where = 'WHERE 1=1';
if ($status !== 'all') {
    if ($status === 'in_transit') {
        $where .= " AND o.status IN ('picked_up','in_transit')";
    } else {
        $where .= " AND o.status='" . mysqli_real_escape_string($conn, $status) . "'";
    }
}
if ($search !== '') { $s = mysqli_real_escape_string($conn, $search); $where .= " AND (o.order_no LIKE '%$s%' OR o.customer_name LIKE '%$s%')"; }
$total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM orders o $where"))['c'];
$res = mysqli_query($conn, "SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) ic FROM orders o $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<ul class="nav nav-pills mb-3 orders-status-nav">
  <?php foreach (['all'=>'All','pending'=>'Pending','assigned'=>'Assigned','in_transit'=>'In Transit','delivered'=>'Delivered'] as $k=>$l): ?>
  <li class="nav-item"><a class="nav-link <?= $status===$k?'active':'' ?>" href="?status=<?= $k ?>"><?= $l ?></a></li>
  <?php endforeach; ?>
</ul>
<form class="row g-2 mb-3" method="get">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="col-12 col-md-4"><input name="search" class="form-control" placeholder="Cari order no / nama..." value="<?= e($search) ?>"></div>
  <div class="col-12 col-md-auto"><button class="btn btn-tracky w-100 w-md-auto">Cari</button></div>
</form>
<div class="card"><div class="table-responsive">
<table class="table mb-0 table-responsive-cards"><thead><tr><th>Order</th><th>Customer</th><th>Phone</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Time</th><th>Actions</th></tr></thead><tbody>
<?php while ($o = mysqli_fetch_assoc($res)): ?>
<tr>
  <td data-label="Order"><?= e($o['order_no']) ?></td>
  <td data-label="Customer"><?= e($o['customer_name']) ?></td>
  <td data-label="Phone" class="td-hide-mobile"><?= e($o['customer_phone']) ?></td>
  <td data-label="Items"><?= (int)$o['ic'] ?></td>
  <td data-label="Total"><?= formatPrice((float)$o['total_amount']) ?></td>
  <td data-label="Payment"><span class="badge bg-secondary"><?= e($o['payment_method']) ?></span></td>
  <td data-label="Status"><?= getStatusBadge($o['status']) ?></td>
  <td data-label="Time" class="small"><?= timeAgo($o['created_at']) ?></td>
  <td data-label="Actions" class="text-nowrap td-actions">
    <a href="/tracky/admin/tracking.php?order_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action-icon"><i class="ti ti-eye"></i> <span class="btn-text">View</span></a>
    <?php if ($o['status']==='pending'): ?>
    <a href="/tracky/admin/assign.php?order_id=<?= (int)$o['id'] ?>" class="btn btn-sm btn-tracky btn-action-icon"><i class="ti ti-route"></i> <span class="btn-text">Assign</span></a>
    <form method="post" class="d-inline" onsubmit="return confirm('Batalkan order?')"><input type="hidden" name="cancel_order" value="<?= (int)$o['id'] ?>"><button class="btn btn-sm btn-outline-danger btn-action-icon"><i class="ti ti-x"></i> <span class="btn-text">Cancel</span></button></form>
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</tbody></table></div></div>
<?php $pages = max(1, ceil($total/$limit)); if ($pages>1): ?>
<nav class="mt-3"><ul class="pagination"><?php for($p=1;$p<=$pages;$p++): ?><li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a></li><?php endfor; ?></ul></nav>
<?php endif; require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
