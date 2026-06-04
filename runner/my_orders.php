<?php
require_once __DIR__ . '/../includes/runner_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$runner_id = (int)$_SESSION['runner_id'];

$rstmt = mysqli_prepare($conn, 'SELECT * FROM runners WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($rstmt, 'i', $runner_id);
mysqli_stmt_execute($rstmt);
$runner = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));
mysqli_stmt_close($rstmt);
if (!$runner) {
    header('Location: /tracky/login.php');
    exit;
}

$active = [];
$astmt = mysqli_prepare($conn, "SELECT d.*, o.order_no, o.customer_name, o.customer_phone, o.delivery_address, o.total_amount, o.notes
  FROM deliveries d JOIN orders o ON o.id=d.order_id
  WHERE d.runner_id=? AND d.status IN ('assigned','picked_up','in_transit') ORDER BY d.assigned_at DESC");
mysqli_stmt_bind_param($astmt, 'i', $runner_id);
mysqli_stmt_execute($astmt);
$ares = mysqli_stmt_get_result($astmt);
while ($row = mysqli_fetch_assoc($ares)) {
    $row['items'] = getOrderItems($conn, (int)$row['order_id']);
    $row['action'] = nextRunnerAction($row['status']);
    $active[] = $row;
}
mysqli_stmt_close($astmt);

$done = [];
$dstmt = mysqli_prepare($conn, "SELECT d.*, o.order_no, o.customer_name, o.total_amount FROM deliveries d JOIN orders o ON o.id=d.order_id WHERE d.runner_id=? AND d.status='delivered' AND DATE(d.delivered_at)=CURDATE() ORDER BY d.delivered_at DESC");
mysqli_stmt_bind_param($dstmt, 'i', $runner_id);
mysqli_stmt_execute($dstmt);
$dres = mysqli_stmt_get_result($dstmt);
while ($row = mysqli_fetch_assoc($dres)) $done[] = $row;
mysqli_stmt_close($dstmt);

$status = $runner['status'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Tracky Runner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/runner.css" rel="stylesheet">
</head>
<body class="runner-app">
<header class="runner-header">
  <div class="runner-brand"><i class="ti ti-bike"></i> Tracky</div>
  <div class="small"><?= e($_SESSION['name']) ?> · <a href="/tracky/logout.php">Logout</a></div>
</header>
<div class="container py-3 runner-page-wrap">
  <div class="availability-card">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="avail-title">Status Kerja Saya</div>
        <div class="avail-sub" id="avail-text">
          <?php if ($status === 'online'): ?>
            Anda sedang online — boleh terima order
          <?php elseif ($status === 'busy'): ?>
            Anda sedang menghantar order
          <?php else: ?>
            Anda sedang offline — tidak boleh terima order
          <?php endif; ?>
        </div>
      </div>
      <div>
        <?php if ($status === 'busy'): ?>
          <div class="busy-badge"><i class="ti ti-bike"></i> Sedang Hantar</div>
        <?php else: ?>
          <label class="toggle-switch">
            <input type="checkbox" id="availToggle"
              <?= $status === 'online' ? 'checked' : '' ?>
              onchange="toggleAvailability(this)">
            <span class="toggle-slider"></span>
          </label>
        <?php endif; ?>
      </div>
    </div>
    <div class="status-bar mt-2" id="status-bar">
      <div class="status-dot <?= e($status) ?>" id="status-dot"></div>
      <span class="status-text-<?= e($status) ?>" id="status-label">
        <?= $status === 'online' ? 'ONLINE' : ($status === 'busy' ? 'SIBUK' : 'OFFLINE') ?>
      </span>
    </div>
  </div>

  <h5 class="mb-3">Active Orders</h5>
  <?php if (!$active): ?><p class="text-muted">Tiada order aktif.</p><?php endif; ?>
  <?php foreach ($active as $o): ?>
  <div class="runner-card">
    <div class="d-flex justify-content-between mb-2"><strong><?= e($o['order_no']) ?></strong><?= getStatusBadge($o['status']) ?></div>
    <p class="mb-1"><a href="tel:<?= e(preg_replace('/\s+/','',$o['customer_phone'])) ?>"><?= e($o['customer_name']) ?> · <?= e($o['customer_phone']) ?></a></p>
    <p class="small text-muted mb-2"><?= e($o['delivery_address']) ?></p>
    <?php foreach ($o['items'] as $i): ?><div class="small"><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div><?php endforeach; ?>
    <div class="fw-bold text-success my-2"><?= formatPrice((float)$o['total_amount']) ?></div>
    <?php if ($o['action']): ?>
    <button class="btn w-100 runner-action-btn mb-2 <?= e($o['action']['class']) ?> btn-status" data-id="<?= (int)$o['id'] ?>" data-next="<?= e($o['action']['next']) ?>"><?= e($o['action']['label']) ?></button>
    <?php endif; ?>
    <a href="https://maps.google.com/?q=<?= urlencode($o['delivery_address']) ?>" target="_blank" class="btn btn-outline-secondary w-100"><i class="ti ti-map-pin"></i> Navigate</a>
    <a href="/tracky/runner/order_detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-link btn-sm w-100 mt-1">Butiran penuh</a>
  </div>
  <?php endforeach; ?>

  <h5 class="mb-3 mt-4">Completed Today</h5>
  <?php if (!$done): ?><p class="text-muted small">Tiada lagi hari ini.</p><?php endif; ?>
  <?php foreach ($done as $o): ?>
  <div class="runner-card"><strong><?= e($o['order_no']) ?></strong> — <?= e($o['customer_name']) ?> · <?= formatPrice((float)$o['total_amount']) ?></div>
  <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/runner_bottom_nav.php'; ?>
<div class="toast-runner" id="toast"></div>
<script>
function toggleAvailability(checkbox) {
  const isOnline = checkbox.checked;
  const newStatus = isOnline ? 'online' : 'offline';
  checkbox.disabled = true;

  fetch('/tracky/api/runner_toggle.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'status=' + newStatus
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const text = document.getElementById('avail-text');
      const dot = document.getElementById('status-dot');
      const label = document.getElementById('status-label');
      if (newStatus === 'online') {
        text.textContent = 'Anda sedang online — boleh terima order';
        dot.className = 'status-dot online';
        label.className = 'status-text-online';
        label.textContent = 'ONLINE';
        showToast('Anda kini ONLINE', 'success');
      } else {
        text.textContent = 'Anda sedang offline — tidak boleh terima order';
        dot.className = 'status-dot offline';
        label.className = 'status-text-offline';
        label.textContent = 'OFFLINE';
        showToast('Anda kini OFFLINE', 'warning');
      }
    } else {
      checkbox.checked = !isOnline;
      showToast(data.message || 'Gagal update status', 'danger');
    }
    checkbox.disabled = false;
  })
  .catch(() => {
    checkbox.checked = !isOnline;
    checkbox.disabled = false;
    showToast('Ralat sambungan', 'danger');
  });
}

function showToast(message, type) {
  const toast = document.createElement('div');
  toast.className = 'toast-notif toast-' + type;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.classList.add('show'), 100);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

document.querySelectorAll('.btn-status').forEach(btn => btn.addEventListener('click', async () => {
  btn.disabled = true;
  try {
    const res = await fetch('/tracky/api/update_status.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ delivery_id: parseInt(btn.dataset.id), new_status: btn.dataset.next })
    }).then(r=>r.json());
    showToast(res.message || (res.success ? 'Status dikemaskini' : 'Gagal'), res.success ? 'success' : 'danger');
    setTimeout(() => { if (res.success) location.reload(); else btn.disabled=false; }, res.success ? 800 : 1200);
  } catch {
    showToast('Ralat sambungan', 'danger');
    btn.disabled = false;
  }
}));
setInterval(() => location.reload(), 20000);
</script>
</body>
</html>
