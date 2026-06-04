<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Profil';
$uid = (int)$_SESSION['user_id'];
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['name']); $phone = trim($_POST['phone']);
        $stmt = mysqli_prepare($conn, 'UPDATE users SET name=?, phone=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $phone, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['name'] = $name;
        $msg = 'Profil dikemaskini.';
    } elseif (($_POST['action'] ?? '') === 'password') {
        $cur = $_POST['current_password']; $new = $_POST['new_password'];
        $pstmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($pstmt, 'i', $uid);
        mysqli_stmt_execute($pstmt);
        $u = mysqli_fetch_assoc(mysqli_stmt_get_result($pstmt));
        mysqli_stmt_close($pstmt);
        if ($u && password_verify($cur, $u['password'])) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'UPDATE users SET password=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'si', $hash, $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Kata laluan dikemaskini.';
        } else {
            $msg = 'Kata laluan semasa salah.';
            $msg_type = 'danger';
        }
    }
}

$ustmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($ustmt, 'i', $uid);
mysqli_stmt_execute($ustmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($ustmt));
mysqli_stmt_close($ustmt);

$cstmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM orders');
mysqli_stmt_execute($cstmt);
$order_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($cstmt))['c'];
mysqli_stmt_close($cstmt);

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= e($msg_type) ?>"><?= e($msg) ?></div><?php endif; ?>
<div class="two-col-layout profile-layout">
  <div><div class="card"><div class="card-header">Profil</div><div class="card-body">
    <form method="post"><input type="hidden" name="action" value="profile">
      <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" value="<?= e($user['name']) ?>" required></div>
      <div class="mb-2"><label class="form-label">Email</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($user['phone']) ?>"></div>
      <button class="btn btn-tracky">Simpan</button>
    </form>
  </div></div></div>
  <div>
    <div class="card mb-3"><div class="card-header">Tukar Kata Laluan</div><div class="card-body">
    <form method="post"><input type="hidden" name="action" value="password">
      <div class="mb-2"><label class="form-label">Semasa</label><input type="password" name="current_password" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Baru</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
      <button class="btn btn-tracky">Kemaskini</button>
    </form>
  </div></div>
  <div class="stat-card"><div class="stat-label">Total Orders (System)</div><div class="stat-value"><?= $order_count ?></div></div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
