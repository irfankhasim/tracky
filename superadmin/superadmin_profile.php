<?php
require_once __DIR__ . '/../includes/superadmin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Profil';
$uid = (int) $_SESSION['user_id'];
$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $stmt = mysqli_prepare($conn, 'UPDATE users SET name=?, phone=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $phone, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['name'] = $name;
        $msg = 'Profil dikemaskini.';
    } elseif (($_POST['action'] ?? '') === 'password') {
        $cur = $_POST['current_password'];
        $new = $_POST['new_password'];
        if (strlen($new) < 6) {
            $msg = 'Kata laluan baru mesti sekurang-kurangnya 6 aksara.';
            $msg_type = 'danger';
        } else {
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
}

$ustmt = mysqli_prepare($conn, 'SELECT * FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($ustmt, 'i', $uid);
mysqli_stmt_execute($ustmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($ustmt));
mysqli_stmt_close($ustmt);

$restaurant_count = (int) mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS c FROM restaurants'))['c'];
$user_count = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role IN ('admin','staff','runner')"))['c'];

$roleLabel = 'Sistem Owner';
$initials = strtoupper(mb_substr(trim($user['name']), 0, 2));

require_once __DIR__ . '/../includes/superadmin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Profil Saya</h4>
    <p>Urus maklumat akaun anda</p>
  </div>
</div>

<?php if ($msg): ?>
<div class="alert-tracky <?= $msg_type === 'danger' ? 'alert-danger' : 'alert-success' ?>">
  <i class="ti ti-<?= $msg_type === 'danger' ? 'alert-circle' : 'circle-check' ?>"></i> <?= e($msg) ?>
</div>
<?php endif; ?>

<!-- Profile hero -->
<div class="card profile-hero">
  <div class="card-body profile-hero-body">
    <div class="profile-avatar"><?= e($initials) ?></div>
    <div class="profile-hero-info">
      <h4><?= e($user['name']) ?></h4>
      <span class="profile-role-badge"><i class="ti ti-shield-check"></i> <?= e($roleLabel) ?></span>
      <div class="profile-meta">
        <span><i class="ti ti-mail"></i> <?= e($user['email']) ?></span>
        <?php if (!empty($user['phone'])): ?><span><i class="ti ti-phone"></i> <?= e($user['phone']) ?></span><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid profile-stats">
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon blue"><i class="ti ti-building-store"></i></div>
      <div>
        <div class="stat-label">Jumlah Restoran</div>
        <div class="stat-value"><?= $restaurant_count ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon green"><i class="ti ti-users"></i></div>
      <div>
        <div class="stat-label">Jumlah Pengguna</div>
        <div class="stat-value"><?= $user_count ?></div>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="d-flex align-items-center gap-3">
      <div class="stat-icon amber"><i class="ti ti-user-check"></i></div>
      <div>
        <div class="stat-label">Peranan</div>
        <div class="stat-value" style="font-size:1.05rem;line-height:1.3"><?= e($roleLabel) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="two-col-layout profile-forms">
  <div>
    <div class="card">
      <div class="card-header">Maklumat Profil</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="profile">
          <div class="mb-3">
            <label class="form-label">Nama Penuh</label>
            <input name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" value="<?= e($user['email']) ?>" disabled style="opacity:0.6">
          </div>
          <div class="mb-4">
            <label class="form-label">No. Telefon</label>
            <input name="phone" class="form-control" value="<?= e($user['phone']) ?>">
          </div>
          <button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan Perubahan</button>
        </form>
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-header">Tukar Kata Laluan</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="password">
          <div class="mb-3">
            <label class="form-label">Kata Laluan Semasa</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Kata Laluan Baru</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
          </div>
          <button class="btn-tracky"><i class="ti ti-lock"></i> Kemaskini Kata Laluan</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/superadmin_layout_end.php'; ?>
