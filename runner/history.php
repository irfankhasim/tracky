<?php
require_once __DIR__ . '/../includes/runner_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$runner_id = (int) $_SESSION['runner_id'];

$tstmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status = 'delivered'");
mysqli_stmt_bind_param($tstmt, 'i', $runner_id);
mysqli_stmt_execute($tstmt);
$total_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($tstmt))['c'];
mysqli_stmt_close($tstmt);

$tdstmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURDATE()");
mysqli_stmt_bind_param($tdstmt, 'i', $runner_id);
mysqli_stmt_execute($tdstmt);
$today_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($tdstmt))['c'];
mysqli_stmt_close($tdstmt);

$history = [];
$hstmt = mysqli_prepare($conn, "SELECT
    o.order_no,
    o.customer_name,
    o.customer_phone,
    o.delivery_address,
    o.total_amount,
    o.payment_method,
    o.notes,
    d.id AS delivery_id,
    d.assigned_at,
    d.picked_up_at,
    d.delivered_at,
    d.status,
    TIMESTAMPDIFF(MINUTE, d.assigned_at, d.delivered_at) AS duration_minutes,
    GROUP_CONCAT(CONCAT(oi.item_name, ' x', oi.quantity) SEPARATOR ', ') AS items_list
  FROM deliveries d
  JOIN orders o ON d.order_id = o.id
  LEFT JOIN order_items oi ON oi.order_id = o.id
  WHERE d.runner_id = ?
  AND d.status = 'delivered'
  GROUP BY d.id, o.order_no, o.customer_name, o.customer_phone, o.delivery_address,
           o.total_amount, o.payment_method, o.notes, d.assigned_at, d.picked_up_at,
           d.delivered_at, d.status
  ORDER BY d.delivered_at DESC");
mysqli_stmt_bind_param($hstmt, 'i', $runner_id);
mysqli_stmt_execute($hstmt);
$hres = mysqli_stmt_get_result($hstmt);
while ($row = mysqli_fetch_assoc($hres)) {
    $history[] = $row;
}
mysqli_stmt_close($hstmt);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>History — Tracky Runner</title>
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
  <h5 class="mb-3">Sejarah Penghantaran</h5>

  <div class="stat-mini-grid">
    <div class="stat-mini-card">
      <div class="stat-mini-value"><?= $total_count ?></div>
      <div class="stat-mini-label">Jumlah Penghantaran</div>
    </div>
    <div class="stat-mini-card">
      <div class="stat-mini-value"><?= $today_count ?></div>
      <div class="stat-mini-label">Hari Ini</div>
    </div>
  </div>

  <div class="filter-pills d-flex gap-2 overflow-auto pb-2 mb-3">
    <button type="button" class="btn btn-sm filter-pill active" data-filter="all">Semua</button>
    <button type="button" class="btn btn-sm filter-pill" data-filter="today">Hari Ini</button>
    <button type="button" class="btn btn-sm filter-pill" data-filter="week">Minggu Ini</button>
    <button type="button" class="btn btn-sm filter-pill" data-filter="month">Bulan Ini</button>
  </div>

  <div id="history-list">
    <?php if (!$history): ?>
    <div class="history-empty text-center py-5">
      <i class="ti ti-history fs-1 text-muted"></i>
      <p class="fw-semibold mt-3 mb-1">Belum ada penghantaran selesai</p>
      <p class="text-muted small mb-0">Penghantaran yang selesai akan dipaparkan di sini</p>
    </div>
    <?php else: ?>
      <?php foreach ($history as $row): ?>
      <?php
        $delivered = strtotime($row['delivered_at']);
        $duration = (int)($row['duration_minutes'] ?? 0);
        $payBadge = ($row['payment_method'] ?? '') === 'online'
          ? '<span class="badge bg-info">Online</span>'
          : '<span class="badge bg-dark">Cash</span>';
      ?>
      <div class="history-card">
        <div class="history-card-header">
          <div class="history-order-no"><?= e($row['order_no']) ?></div>
          <div class="history-date"><?= date('d M Y, H:i', $delivered) ?></div>
        </div>
        <div class="history-customer"><?= e($row['customer_name']) ?> · <?= e($row['customer_phone']) ?></div>
        <div class="history-address"><?= e($row['delivery_address']) ?></div>
        <div class="history-items"><?= e($row['items_list'] ?? '') ?></div>
        <div class="history-footer">
          <div>
            <div class="history-amount"><?= formatPrice((float)$row['total_amount']) ?></div>
            <div class="mt-1"><?= $payBadge ?> <span class="badge bg-success">Selesai</span></div>
          </div>
          <div class="text-end">
            <?php if ($duration > 0): ?>
            <div class="history-duration">Siap dalam <?= $duration ?> minit</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/runner_bottom_nav.php'; ?>
<script>
function formatDate(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ', 'T'));
  return d.toLocaleDateString('ms-MY', { day: 'numeric', month: 'short', year: 'numeric' })
    + ', ' + d.toLocaleTimeString('ms-MY', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(mins) {
  if (mins == null || mins < 1) return '';
  return 'Siap dalam ' + mins + ' minit';
}

function formatPrice(amount) {
  return 'RM ' + parseFloat(amount).toFixed(2);
}

function paymentBadge(method) {
  return method === 'online'
    ? '<span class="badge bg-info">Online</span>'
    : '<span class="badge bg-dark">Cash</span>';
}

function escapeHtml(str) {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

let currentFilter = 'all';

function renderHistory(rows, filtered) {
  const list = document.getElementById('history-list');
  if (!rows.length) {
    const title = filtered ? 'Tiada penghantaran untuk tempoh ini' : 'Belum ada penghantaran selesai';
    const sub = filtered ? 'Cuba pilih penapis lain' : 'Penghantaran yang selesai akan dipaparkan di sini';
    list.innerHTML = `
      <div class="history-empty text-center py-5">
        <i class="ti ti-history fs-1 text-muted"></i>
        <p class="fw-semibold mt-3 mb-1">${title}</p>
        <p class="text-muted small mb-0">${sub}</p>
      </div>`;
    return;
  }
  list.innerHTML = rows.map(row => `
    <div class="history-card">
      <div class="history-card-header">
        <div class="history-order-no">${escapeHtml(row.order_no)}</div>
        <div class="history-date">${formatDate(row.delivered_at)}</div>
      </div>
      <div class="history-customer">${escapeHtml(row.customer_name)} · ${escapeHtml(row.customer_phone)}</div>
      <div class="history-address">${escapeHtml(row.delivery_address || '')}</div>
      <div class="history-items">${escapeHtml(row.items_list || '')}</div>
      <div class="history-footer">
        <div>
          <div class="history-amount">${formatPrice(row.total_amount)}</div>
          <div class="mt-1">${paymentBadge(row.payment_method)} <span class="badge bg-success">Selesai</span></div>
        </div>
        <div class="text-end">
          ${row.duration_minutes > 0 ? `<div class="history-duration">${formatDuration(row.duration_minutes)}</div>` : ''}
        </div>
      </div>
    </div>
  `).join('');
}

document.querySelectorAll('.filter-pill').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-pill').forEach(b => {
      b.classList.remove('active', 'btn-success');
      b.classList.add('btn-outline-secondary');
    });
    btn.classList.add('active', 'btn-success');
    btn.classList.remove('btn-outline-secondary');
    currentFilter = btn.dataset.filter;

    fetch('/tracky/api/get_runner_history.php?filter=' + encodeURIComponent(currentFilter))
      .then(r => r.json())
      .then(data => {
        const rows = Array.isArray(data) ? data : (data.history || []);
        renderHistory(rows, currentFilter !== 'all');
      });
  });
});

document.querySelectorAll('.filter-pill').forEach(b => {
  if (b.classList.contains('active')) b.classList.add('btn-success');
  else b.classList.add('btn-outline-secondary');
});
</script>
</body>
</html>
