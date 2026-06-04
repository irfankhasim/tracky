<?php
require_once __DIR__ . '/../includes/runner_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$runner_id = (int)$_SESSION['runner_id'];
$uid = (int)$_SESSION['user_id'];

$ustmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($ustmt, 'i', $uid);
mysqli_stmt_execute($ustmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($ustmt));
mysqli_stmt_close($ustmt);

$rstmt = mysqli_prepare($conn, 'SELECT * FROM runners WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($rstmt, 'i', $runner_id);
mysqli_stmt_execute($rstmt);
$runner = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));
mysqli_stmt_close($rstmt);

$tstmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURDATE()");
mysqli_stmt_bind_param($tstmt, 'i', $runner_id);
mysqli_stmt_execute($tstmt);
$today = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($tstmt))['c'];
mysqli_stmt_close($tstmt);

$total_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM deliveries WHERE runner_id = ? AND status = 'delivered'");
mysqli_stmt_bind_param($total_stmt, 'i', $runner_id);
mysqli_stmt_execute($total_stmt);
$total_delivered = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt))['c'];
mysqli_stmt_close($total_stmt);

$phone = $user['phone'] ?: ($runner['phone'] ?? '');
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile — Tracky Runner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="/tracky/assets/css/runner.css" rel="stylesheet">
</head>
<body class="runner-app">
<header class="runner-header"><div class="runner-brand"><i class="ti ti-bike"></i> Tracky</div><a href="/tracky/logout.php" class="small">Logout</a></header>
<div class="container py-3 runner-profile-wrap">
  <div class="runner-card text-center mb-3">
    <div class="user-avatar mx-auto mb-2" style="width:64px;height:64px;border-radius:50%;background:#1D9E75;color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700"><?= e(strtoupper(substr($user['name'],0,2))) ?></div>
    <h5><?= e($user['name']) ?></h5>
    <p class="text-muted mb-0"><?= e($phone) ?> · <?= e($runner['vehicle_no'] ?? '') ?></p>
  </div>
  <div class="row g-2">
    <div class="col-6"><div class="runner-card text-center"><div class="text-muted small">Hari Ini</div><div class="fs-4 fw-bold"><?= $today ?></div></div></div>
    <div class="col-6"><div class="runner-card text-center"><div class="text-muted small">Total</div><div class="fs-4 fw-bold"><?= $total_delivered ?></div></div></div>
  </div>
  <div class="runner-card mt-3"><div class="text-muted small">Anggaran pendapatan hari ini</div><div class="fs-5 fw-bold text-success"><?= formatPrice($today * 5) ?></div><div class="small text-muted">*Anggaran RM5 per delivery</div></div>
</div>
<?php require_once __DIR__ . '/../includes/runner_bottom_nav.php'; ?>
</body>
</html>
