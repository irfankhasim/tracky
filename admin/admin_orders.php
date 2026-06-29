<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Orders';
$rid = activeRestaurantId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = (int)$_POST['cancel_order'];
    $stmt = mysqli_prepare($conn, "UPDATE orders SET status='cancelled' WHERE id=? AND status='pending' AND restaurant_id=?");
    mysqli_stmt_bind_param($stmt, 'ii', $oid, $rid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: /tracky/admin/admin_orders.php');
    exit;
}

$status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;
$where = 'WHERE o.restaurant_id=' . $rid;
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
<div class="page-header">
  <div class="page-header-left">
    <h4>Orders</h4>
    <p><?= $total ?> order <?= $status !== 'all' ? "· ditapis: $status" : '' ?><?= $search ? " · carian: \"$search\"" : '' ?></p>
  </div>
</div>

<div class="filter-bar">
  <div class="filter-bar-tabs">
    <?php foreach (['all'=>'Semua','pending'=>'Pending','assigned'=>'Assigned','in_transit'=>'In Transit','delivered'=>'Delivered','cancelled'=>'Cancelled'] as $k=>$l): ?>
    <a href="?status=<?= $k ?>" class="filter-tab <?= $status===$k?'active':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-search">
    <form class="d-flex gap-2" method="get">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <input name="search" class="form-control" style="min-width:200px" placeholder="Cari order / nama..." value="<?= e($search) ?>">
      <button class="btn-tracky" style="white-space:nowrap">Cari</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>Order No</th><th>Customer</th><th>Phone</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Masa</th><th>Tindakan</th></tr></thead><tbody>
    <?php while ($o = mysqli_fetch_assoc($res)): ?>
    <tr>
      <td><span style="font-size:13px;font-weight:700;color:var(--text)"><?= e($o['order_no']) ?></span></td>
      <td>
        <div style="font-weight:600;color:var(--text)"><?= e($o['customer_name']) ?></div>
        <div style="font-size:11px;color:var(--muted)"><?= e($o['delivery_address']) ?></div>
      </td>
      <td style="color:var(--muted);font-size:13px"><?= e($o['customer_phone']) ?></td>
      <td style="color:var(--muted)"><?= (int)$o['ic'] ?> item</td>
      <td style="font-weight:700;color:var(--primary)"><?= formatPrice((float)$o['total_amount']) ?></td>
      <td><span class="badge-status <?= $o['payment_method']==='online'?'status-assigned':'status-cancelled' ?>"><?= ucfirst(e($o['payment_method'])) ?></span></td>
      <td><?= getStatusBadge($o['status']) ?></td>
      <td style="color:var(--muted);font-size:12px;white-space:nowrap"><?= timeAgo($o['created_at']) ?></td>
      <td><div class="btn-actions">
        <a href="/tracky/admin/admin_tracking.php?order_id=<?= (int)$o['id'] ?>" class="btn-icon"><i class="ti ti-eye"></i> View</a>
        <?php if ($o['status']==='pending'): ?>
        <a href="/tracky/admin/admin_assign.php?order_id=<?= (int)$o['id'] ?>" class="btn-icon primary"><i class="ti ti-route"></i> Assign</a>
        <form method="post" class="d-inline" onsubmit="return confirm('Batalkan order ini?')">
          <input type="hidden" name="cancel_order" value="<?= (int)$o['id'] ?>">
          <button class="btn-icon danger"><i class="ti ti-x"></i> Cancel</button>
        </form>
        <?php endif; ?>
      </div></td>
    </tr>
    <?php endwhile; ?>
    </tbody></table>
  </div>
</div>

<?php $pages = max(1, ceil($total/$limit)); if ($pages>1): ?>
<nav class="mt-3"><ul class="pagination justify-content-end">
  <?php for($p=1;$p<=$pages;$p++): ?>
  <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a></li>
  <?php endfor; ?>
</ul></nav>
<?php endif;
require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
