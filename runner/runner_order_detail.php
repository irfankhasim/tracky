<?php
require_once __DIR__ . '/../includes/runner_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$id = (int)($_GET['id'] ?? 0);
$runner_id = (int)$_SESSION['runner_id'];

$stmt = mysqli_prepare($conn, "SELECT d.*, o.* FROM deliveries d JOIN orders o ON o.id=d.order_id WHERE d.id=? AND d.runner_id=?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $runner_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) { header('Location: /tracky/runner/runner_orders.php'); exit; }

$items = getOrderItems($conn, (int)$row['order_id']);
$action = nextRunnerAction($row['status']);
$map = urlencode($row['delivery_address']);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title><?= e($row['order_no']) ?> — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="<?= asset('assets/css/runner.css') ?>" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script>(function(){ if(localStorage.getItem('tracky-theme')==='light') document.documentElement.classList.add('pre-light'); })();</script>
  <style>html.pre-light body{background:#F8FAFC!important}</style>
</head>
<body class="runner-app">
<header class="runner-header">
  <a href="/tracky/runner/runner_orders.php" class="text-decoration-none" style="color:inherit"><i class="ti ti-arrow-left"></i> Back</a>
  <strong><?= e($row['order_no']) ?></strong>
  <button class="runner-theme-btn" id="themeToggle" onclick="toggleTheme()" title="Tukar tema"><i class="ti ti-sun" id="themeIcon"></i></button>
</header>
<div class="container py-3 runner-page-wrap-wide">
  <div class="runner-detail-grid">
  <div>
  <div class="runner-card">
    <p><strong><?= e($row['customer_name']) ?></strong><br><a href="tel:<?= e(preg_replace('/\s+/','',$row['customer_phone'])) ?>"><?= e($row['customer_phone']) ?></a></p>
    <p class="text-muted"><?= e($row['delivery_address']) ?></p>
    <?php if ($row['notes']): ?><p class="small"><em>Nota: <?= e($row['notes']) ?></em></p><?php endif; ?>
    <hr>
    <?php foreach ($items as $i): ?><div><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div><?php endforeach; ?>
    <div class="fw-bold text-success mt-2"><?= formatPrice((float)$row['total_amount']) ?></div>
    <div class="mt-2"><?= getStatusBadge($row['status']) ?></div>
  </div>
  <?php if ($action): ?>
  <button class="btn w-100 runner-action-btn mt-2 <?= e($action['class']) ?>" id="btn-action" data-next="<?= e($action['next']) ?>"><?= e($action['label']) ?></button>
  <?php endif; ?>
  <a href="https://maps.google.com/?q=<?= urlencode($row['delivery_address']) ?>" target="_blank" class="btn btn-outline-secondary w-100 mt-2"><i class="ti ti-map-pin"></i> Navigate</a>
  </div>
  <div class="map-wrap">
  <iframe class="w-100 rounded border" height="260" src="https://maps.google.com/maps?q=<?= $map ?>&output=embed"></iframe>
  </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/runner_bottom_nav.php'; ?>
<div class="toast-runner" id="toast"></div>
<script>
function showToast(message, ok) {
  const t = document.getElementById('toast');
  t.textContent = message;
  t.className = 'toast-runner show' + (ok ? '' : ' bg-danger text-white');
  setTimeout(() => t.classList.remove('show'), 2500);
}
const btn = document.getElementById('btn-action');
if (btn) btn.onclick = async () => {
  btn.disabled = true;
  try {
    const res = await fetch('/tracky/api/runner_update_status.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ delivery_id: <?= (int)$id ?>, new_status: btn.dataset.next }) }).then(r=>r.json());
    if (res.success) { showToast(res.message || 'Status dikemaskini', true); setTimeout(() => location.href = '/tracky/runner/runner_orders.php', 800); }
    else { showToast(res.message || 'Gagal', false); btn.disabled = false; }
  } catch {
    showToast('Ralat sambungan', false);
    btn.disabled = false;
  }
};
</script>
<script src="<?= asset('assets/js/theme.js') ?>"></script>
</body>
</html>
