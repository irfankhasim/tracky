<?php
require_once __DIR__ . '/../includes/superadmin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Dashboard';

function scalarCount(mysqli $conn, string $sql): int
{
    $res = mysqli_query($conn, $sql);
    return $res ? (int) (mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
}

$total_admins   = scalarCount($conn, "SELECT COUNT(*) c FROM users WHERE role IN ('admin','staff')");
$total_runners  = scalarCount($conn, "SELECT COUNT(*) c FROM users WHERE role='runner'");
$active_users   = scalarCount($conn, "SELECT COUNT(*) c FROM users WHERE is_active=1");
$suspended      = scalarCount($conn, "SELECT COUNT(*) c FROM users WHERE is_active=0");
$total_orders   = scalarCount($conn, "SELECT COUNT(*) c FROM orders");
$menu_items     = scalarCount($conn, "SELECT COUNT(*) c FROM menu_items");
$total_stores   = scalarCount($conn, "SELECT COUNT(*) c FROM restaurants");

$revenueRes = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount),0) s FROM orders WHERE status='delivered'");
$revenue = $revenueRes ? (float) mysqli_fetch_assoc($revenueRes)['s'] : 0;

$per_restaurant = mysqli_query($conn, "SELECT res.id, res.name, res.is_active,
    (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id = res.id) AS orders,
    (SELECT COALESCE(SUM(total_amount),0) FROM orders o WHERE o.restaurant_id = res.id AND o.status='delivered') AS revenue,
    (SELECT COUNT(*) FROM menu_items m WHERE m.restaurant_id = res.id) AS items
  FROM restaurants res ORDER BY res.name ASC");

$recent_users = mysqli_query($conn, "SELECT id, name, email, role, is_active, created_at FROM users WHERE role IN ('admin','staff') ORDER BY created_at DESC LIMIT 6");

require_once __DIR__ . '/../includes/superadmin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Dashboard Superadmin</h4>
    <p>Gambaran keseluruhan sistem Tracky</p>
  </div>
  <div class="page-header-right">
    <a href="/tracky/superadmin/superadmin_users.php" class="btn-icon primary"><i class="ti ti-user-plus"></i> Urus Pengguna</a>
    <a href="/tracky/admin/admin_dashboard.php" class="btn-icon"><i class="ti ti-external-link"></i> Buka Panel Admin</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-user-shield"></i></div>
      <div>
        <div class="stat-label">Admin & Staf</div>
        <div class="stat-value"><?= $total_admins ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-bike"></i></div>
      <div>
        <div class="stat-label">Runner</div>
        <div class="stat-value"><?= $total_runners ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon amber"><i class="ti ti-shopping-bag"></i></div>
      <div>
        <div class="stat-label">Jumlah Order</div>
        <div class="stat-value"><?= $total_orders ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-cash"></i></div>
      <div>
        <div class="stat-label">Jumlah Hasil (delivered)</div>
        <div class="stat-value"><?= formatPrice($revenue) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="two-col-layout" style="margin-top:20px">
  <div>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Admin & Staf Terkini</span>
        <a href="/tracky/superadmin/superadmin_users.php" style="font-size:12px;color:var(--primary);text-decoration:none;font-weight:700">Lihat semua</a>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Nama</th><th>Peranan</th><th>Status</th></tr></thead>
          <tbody>
          <?php if (mysqli_num_rows($recent_users) === 0): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:24px">Tiada akaun admin/staf lagi</td></tr>
          <?php else: while ($u = mysqli_fetch_assoc($recent_users)): ?>
            <tr>
              <td>
                <div style="font-weight:700;color:var(--text)"><?= e($u['name']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= e($u['email']) ?></div>
              </td>
              <td><span class="badge-status status-assigned" style="font-size:11px"><?= e(ucfirst($u['role'])) ?></span></td>
              <td>
                <?php if ($u['is_active']): ?>
                  <span class="badge-status status-delivered" style="font-size:11px">Aktif</span>
                <?php else: ?>
                  <span class="badge-status status-cancelled" style="font-size:11px">Digantung</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Prestasi Mengikut Restoran</span>
        <span style="font-size:12px;color:var(--muted)"><?= $total_stores ?> kedai · <?= $menu_items ?> item menu</span>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Restoran</th><th>Order</th><th>Hasil</th><th></th></tr></thead>
          <tbody>
          <?php if (!$per_restaurant || mysqli_num_rows($per_restaurant) === 0): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:24px">Tiada restoran lagi</td></tr>
          <?php else: while ($r = mysqli_fetch_assoc($per_restaurant)): ?>
            <tr>
              <td>
                <div style="font-weight:700;color:var(--text)"><?= e($r['name']) ?></div>
                <div style="font-size:11px;color:var(--muted)"><?= (int)$r['items'] ?> item menu<?= !$r['is_active'] ? ' · nyahaktif' : '' ?></div>
              </td>
              <td style="color:var(--text-2)"><?= (int)$r['orders'] ?></td>
              <td style="font-weight:700;color:var(--primary)"><?= formatPrice((float)$r['revenue']) ?></td>
              <td><a href="/tracky/admin/admin_dashboard.php?manage_restaurant=<?= (int)$r['id'] ?>" class="btn-icon" title="Urus kedai ini"><i class="ti ti-settings"></i></a></td>
            </tr>
          <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body">
        <a href="/tracky/superadmin/superadmin_stores.php" class="btn-tracky"><i class="ti ti-building-store"></i> Urus Kedai</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/superadmin_layout_end.php'; ?>
