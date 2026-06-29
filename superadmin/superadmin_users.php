<?php
require_once __DIR__ . '/../includes/superadmin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Pengguna';
$me = (int) $_SESSION['user_id'];
$tab = ($_GET['tab'] ?? 'admins') === 'runners' ? 'runners' : 'admins';
$filter_rid = (int) ($_GET['restaurant'] ?? 0);

// Restaurant options for assignment + filtering.
$restaurant_options = [];
$ro = mysqli_query($conn, 'SELECT id, name FROM restaurants ORDER BY name ASC');
while ($ro && $r = mysqli_fetch_assoc($ro)) {
    $restaurant_options[] = $r;
}

function saFlash(string $msg, string $type = 'success'): void
{
    $_SESSION['sa_flash'] = ['msg' => $msg, 'type' => $type];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $post_tab = ($_POST['tab'] ?? 'admins') === 'runners' ? 'runners' : 'admins';

    try {
        if ($action === 'add_user') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'staff'], true) ? $_POST['role'] : 'staff';
            $pass = $_POST['password'] ?? '';
            $r_id = (int) ($_POST['restaurant_id'] ?? 0);
            if ($name === '' || $email === '' || strlen($pass) < 6) {
                throw new RuntimeException('Sila isi nama, email dan kata laluan (min 6 aksara).');
            }
            if ($r_id < 1) {
                throw new RuntimeException('Sila pilih restoran untuk akaun ini.');
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, 'INSERT INTO users (name, email, password, role, phone, restaurant_id, is_active) VALUES (?,?,?,?,?,?,1)');
            mysqli_stmt_bind_param($stmt, 'sssssi', $name, $email, $hash, $role, $phone, $r_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException(mysqli_errno($conn) === 1062 ? 'Email tersebut sudah digunakan.' : 'Gagal menambah pengguna.');
            }
            mysqli_stmt_close($stmt);
            saFlash('Akaun ' . e($name) . ' berjaya ditambah.');

        } elseif ($action === 'add_runner') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone'] ?? '');
            $vehicle = trim($_POST['vehicle_no'] ?? '');
            $pass = $_POST['password'] ?? '';
            $r_id = (int) ($_POST['restaurant_id'] ?? 0);
            if ($name === '' || $email === '' || strlen($pass) < 6) {
                throw new RuntimeException('Sila isi nama, email dan kata laluan (min 6 aksara).');
            }
            if ($r_id < 1) {
                throw new RuntimeException('Sila pilih restoran untuk runner ini.');
            }
            mysqli_begin_transaction($conn);
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, phone, restaurant_id, is_active) VALUES (?,?,?,'runner',?,?,1)");
            mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $hash, $phone, $r_id);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_rollback($conn);
                throw new RuntimeException(mysqli_errno($conn) === 1062 ? 'Email tersebut sudah digunakan.' : 'Gagal menambah runner.');
            }
            $new_uid = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            $rstmt = mysqli_prepare($conn, "INSERT INTO runners (user_id, restaurant_id, vehicle_no, phone, status) VALUES (?,?,?,?,'offline')");
            mysqli_stmt_bind_param($rstmt, 'iiss', $new_uid, $r_id, $vehicle, $phone);
            mysqli_stmt_execute($rstmt);
            mysqli_stmt_close($rstmt);
            mysqli_commit($conn);
            saFlash('Runner ' . e($name) . ' berjaya ditambah.');

        } elseif ($action === 'edit_user') {
            $id = (int) $_POST['id'];
            $name = trim($_POST['name']);
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $r_id = (int) ($_POST['restaurant_id'] ?? 0);
            if ($name === '' || $email === '') {
                throw new RuntimeException('Sila isi nama dan email.');
            }
            if ($r_id < 1) {
                throw new RuntimeException('Sila pilih restoran untuk akaun ini.');
            }
            $stmt = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, phone=?, restaurant_id=? WHERE id=? AND role!='superadmin'");
            mysqli_stmt_bind_param($stmt, 'sssii', $name, $email, $phone, $r_id, $id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new RuntimeException(mysqli_errno($conn) === 1062 ? 'Email tersebut sudah digunakan.' : 'Gagal mengemaskini pengguna.');
            }
            mysqli_stmt_close($stmt);
            // Keep the runner row's restaurant in sync.
            mysqli_query($conn, "UPDATE runners SET restaurant_id=$r_id WHERE user_id=$id");
            saFlash('Maklumat pengguna dikemaskini.');

        } elseif ($action === 'reset_password') {
            $id = (int) $_POST['id'];
            $pass = $_POST['password'] ?? '';
            if (strlen($pass) < 6) {
                throw new RuntimeException('Kata laluan baru mesti sekurang-kurangnya 6 aksara.');
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=? AND role!='superadmin'");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            saFlash('Kata laluan berjaya ditetapkan semula.');

        } elseif ($action === 'toggle_active') {
            $id = (int) $_POST['id'];
            if ($id === $me) {
                throw new RuntimeException('Anda tidak boleh menggantung akaun sendiri.');
            }
            mysqli_query($conn, "UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=$id AND role!='superadmin'");
            saFlash('Status akaun dikemaskini.');

        } elseif ($action === 'delete_user') {
            $id = (int) $_POST['id'];
            if ($id === $me) {
                throw new RuntimeException('Anda tidak boleh memadam akaun sendiri.');
            }
            $chk = mysqli_query($conn, "SELECT role FROM users WHERE id=$id");
            $row = $chk ? mysqli_fetch_assoc($chk) : null;
            if (!$row || $row['role'] === 'superadmin') {
                throw new RuntimeException('Akaun ini tidak boleh dipadam.');
            }
            mysqli_begin_transaction($conn);
            try {
                if ($row['role'] === 'runner') {
                    $r = mysqli_query($conn, "SELECT id FROM runners WHERE user_id=$id");
                    $runnerRow = $r ? mysqli_fetch_assoc($r) : null;
                    if ($runnerRow) {
                        $rid = (int) $runnerRow['id'];
                        $d = mysqli_query($conn, "SELECT COUNT(*) c FROM deliveries WHERE runner_id=$rid");
                        if ((int) mysqli_fetch_assoc($d)['c'] > 0) {
                            throw new RuntimeException('Runner ini ada rekod penghantaran — gantung akaun, jangan padam.');
                        }
                        mysqli_query($conn, "DELETE FROM runners WHERE id=$rid");
                    }
                } else {
                    $d = mysqli_query($conn, "SELECT COUNT(*) c FROM deliveries WHERE assigned_by=$id");
                    if ((int) mysqli_fetch_assoc($d)['c'] > 0) {
                        throw new RuntimeException('Admin ini pernah assign penghantaran — gantung akaun, jangan padam.');
                    }
                }
                mysqli_query($conn, "UPDATE status_logs SET changed_by=NULL WHERE changed_by=$id");
                if (!mysqli_query($conn, "DELETE FROM users WHERE id=$id")) {
                    throw new RuntimeException('Gagal memadam akaun (mungkin ada rekod berkaitan).');
                }
                mysqli_commit($conn);
                saFlash('Akaun berjaya dipadam.');
            } catch (RuntimeException $inner) {
                mysqli_rollback($conn);
                throw $inner;
            }
        }
    } catch (RuntimeException $ex) {
        saFlash($ex->getMessage(), 'danger');
    }

    header('Location: /tracky/superadmin/superadmin_users.php?tab=' . $post_tab);
    exit;
}

$admin_where = $filter_rid > 0 ? " AND u.restaurant_id = $filter_rid" : '';
$admins = mysqli_query($conn, "SELECT u.id, u.name, u.email, u.phone, u.role, u.is_active, u.created_at, u.restaurant_id, res.name AS restaurant_name
  FROM users u LEFT JOIN restaurants res ON res.id = u.restaurant_id
  WHERE u.role IN ('admin','staff')$admin_where ORDER BY u.created_at DESC");
$runner_where = $filter_rid > 0 ? " AND u.restaurant_id = $filter_rid" : '';
$runners = mysqli_query($conn, "SELECT u.id, u.name, u.email, u.phone, u.is_active, u.restaurant_id, res.name AS restaurant_name, r.id AS runner_id, r.vehicle_no, r.status
  FROM users u LEFT JOIN runners r ON r.user_id = u.id LEFT JOIN restaurants res ON res.id = u.restaurant_id
  WHERE u.role='runner'$runner_where ORDER BY u.name ASC");

$flash = $_SESSION['sa_flash'] ?? null;
unset($_SESSION['sa_flash']);

require_once __DIR__ . '/../includes/superadmin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Pengurusan Pengguna</h4>
    <p>Urus akaun admin, staf dan runner</p>
  </div>
  <div class="page-header-right">
    <?php if ($tab === 'admins'): ?>
    <button class="btn-tracky" data-bs-toggle="modal" data-bs-target="#addUserModal" style="display:inline-flex;align-items:center;gap:8px"><i class="ti ti-user-plus"></i> Tambah Admin/Staf</button>
    <?php else: ?>
    <button class="btn-tracky" data-bs-toggle="modal" data-bs-target="#addRunnerModal" style="display:inline-flex;align-items:center;gap:8px"><i class="ti ti-user-plus"></i> Tambah Runner</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert-tracky <?= $flash['type'] === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px">
  <i class="ti ti-<?= $flash['type'] === 'danger' ? 'alert-circle' : 'circle-check' ?>"></i> <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="margin-bottom:4px">
  <div class="orders-status-nav" style="margin-bottom:0">
    <a href="?tab=admins<?= $filter_rid ? '&restaurant=' . $filter_rid : '' ?>" class="status-tab <?= $tab === 'admins' ? 'active' : '' ?>"><i class="ti ti-user-shield"></i> Admin &amp; Staf</a>
    <a href="?tab=runners<?= $filter_rid ? '&restaurant=' . $filter_rid : '' ?>" class="status-tab <?= $tab === 'runners' ? 'active' : '' ?>"><i class="ti ti-bike"></i> Runner</a>
  </div>
  <form method="get" class="d-flex align-items-center gap-2">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <select name="restaurant" class="form-select" style="min-width:200px" onchange="this.form.submit()">
      <option value="0">Semua Restoran</option>
      <?php foreach ($restaurant_options as $r): ?>
      <option value="<?= (int)$r['id'] ?>" <?= $filter_rid === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($tab === 'admins'): ?>
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead><tr><th>Nama</th><th>Restoran</th><th>Peranan</th><th>Telefon</th><th>Status</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($admins) === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Tiada akaun admin/staf</td></tr>
      <?php else: while ($u = mysqli_fetch_assoc($admins)): ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--text)"><?= e($u['name']) ?><?= $u['id'] == $me ? ' <span style="font-size:10px;color:var(--primary)">(anda)</span>' : '' ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($u['email']) ?></div>
          </td>
          <td style="color:var(--text-2);font-size:13px"><?= e($u['restaurant_name'] ?: '-') ?></td>
          <td><span class="badge-status status-assigned" style="font-size:11px"><?= e(ucfirst($u['role'])) ?></span></td>
          <td style="color:var(--muted);font-size:13px"><?= e($u['phone'] ?: '-') ?></td>
          <td>
            <?php if ($u['is_active']): ?><span class="badge-status status-delivered" style="font-size:11px">Aktif</span>
            <?php else: ?><span class="badge-status status-cancelled" style="font-size:11px">Digantung</span><?php endif; ?>
          </td>
          <td><div class="btn-actions">
            <button type="button" class="btn-icon primary btn-edit-user" data-id="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>" data-email="<?= e($u['email']) ?>" data-phone="<?= e($u['phone']) ?>" data-restaurant="<?= (int)$u['restaurant_id'] ?>"><i class="ti ti-edit"></i></button>
            <button type="button" class="btn-icon btn-reset-pass" data-id="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>"><i class="ti ti-key"></i></button>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="toggle_active"><input type="hidden" name="tab" value="admins"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn-icon" <?= $u['id'] == $me ? 'disabled' : '' ?> title="Aktif/Gantung"><i class="ti ti-<?= $u['is_active'] ? 'ban' : 'circle-check' ?>"></i></button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Padam akaun ini?')">
              <input type="hidden" name="action" value="delete_user"><input type="hidden" name="tab" value="admins"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn-icon danger" <?= $u['id'] == $me ? 'disabled' : '' ?>><i class="ti ti-trash"></i></button>
            </form>
          </div></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead><tr><th>Nama</th><th>Restoran</th><th>Kenderaan</th><th>Status Kerja</th><th>Akaun</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php if (mysqli_num_rows($runners) === 0): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Tiada akaun runner</td></tr>
      <?php else: while ($u = mysqli_fetch_assoc($runners)): ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--text)"><?= e($u['name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($u['email']) ?></div>
          </td>
          <td style="color:var(--text-2);font-size:13px"><?= e($u['restaurant_name'] ?: '-') ?></td>
          <td style="color:var(--muted);font-size:13px"><?= e($u['vehicle_no'] ?: '-') ?></td>
          <td>
            <?php $st = $u['status'] ?? 'offline'; ?>
            <span class="badge-status <?= $st === 'offline' ? 'status-cancelled' : 'status-assigned' ?>" style="font-size:11px"><?= $st === 'offline' ? 'Offline' : 'Online' ?></span>
          </td>
          <td>
            <?php if ($u['is_active']): ?><span class="badge-status status-delivered" style="font-size:11px">Aktif</span>
            <?php else: ?><span class="badge-status status-cancelled" style="font-size:11px">Digantung</span><?php endif; ?>
          </td>
          <td><div class="btn-actions">
            <?php if (!empty($u['runner_id'])): ?>
            <a href="/tracky/runner/runner_orders.php?runner=<?= (int)$u['runner_id'] ?>" class="btn-icon" title="Lihat app runner" target="_blank"><i class="ti ti-eye"></i></a>
            <?php endif; ?>
            <button type="button" class="btn-icon primary btn-edit-user" data-id="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>" data-email="<?= e($u['email']) ?>" data-phone="<?= e($u['phone']) ?>" data-restaurant="<?= (int)$u['restaurant_id'] ?>"><i class="ti ti-edit"></i></button>
            <button type="button" class="btn-icon btn-reset-pass" data-id="<?= $u['id'] ?>" data-name="<?= e($u['name']) ?>"><i class="ti ti-key"></i></button>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="toggle_active"><input type="hidden" name="tab" value="runners"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn-icon" title="Aktif/Gantung"><i class="ti ti-<?= $u['is_active'] ? 'ban' : 'circle-check' ?>"></i></button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Padam akaun runner ini?')">
              <input type="hidden" name="action" value="delete_user"><input type="hidden" name="tab" value="runners"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn-icon danger"><i class="ti ti-trash"></i></button>
            </form>
          </div></td>
        </tr>
      <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Add Admin/Staff Modal -->
<div class="modal fade" id="addUserModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Tambah Admin / Staf</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="add_user"><input type="hidden" name="tab" value="admins">
    <div class="mb-3"><label class="form-label">Nama Penuh</label><input name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">No. Telefon</label><input name="phone" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Peranan</label>
      <select name="role" class="form-select"><option value="admin">Admin</option><option value="staff">Staf</option></select>
    </div>
    <div class="mb-3"><label class="form-label">Restoran</label>
      <select name="restaurant_id" class="form-select" required>
        <option value="">— Pilih Restoran —</option>
        <?php foreach ($restaurant_options as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $filter_rid === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2"><label class="form-label">Kata Laluan</label><input name="password" type="text" class="form-control" minlength="6" required placeholder="Min 6 aksara"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan</button></div>
</form></div></div>

<!-- Add Runner Modal -->
<div class="modal fade" id="addRunnerModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Tambah Runner</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="add_runner"><input type="hidden" name="tab" value="runners">
    <div class="mb-3"><label class="form-label">Nama Penuh</label><input name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">No. Telefon</label><input name="phone" class="form-control"></div>
    <div class="mb-3"><label class="form-label">No. Kenderaan</label><input name="vehicle_no" class="form-control" placeholder="cth: MKM 1234"></div>
    <div class="mb-3"><label class="form-label">Restoran</label>
      <select name="restaurant_id" class="form-select" required>
        <option value="">— Pilih Restoran —</option>
        <?php foreach ($restaurant_options as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $filter_rid === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="mb-2"><label class="form-label">Kata Laluan</label><input name="password" type="text" class="form-control" minlength="6" required placeholder="Min 6 aksara"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan</button></div>
</form></div></div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Pengguna</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="edit_user"><input type="hidden" name="tab" value="<?= $tab ?>"><input type="hidden" name="id" id="edit-user-id">
    <div class="mb-3"><label class="form-label">Nama Penuh</label><input name="name" id="edit-user-name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input name="email" id="edit-user-email" type="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">No. Telefon</label><input name="phone" id="edit-user-phone" class="form-control"></div>
    <div class="mb-2"><label class="form-label">Restoran</label>
      <select name="restaurant_id" id="edit-user-restaurant" class="form-select" required>
        <?php foreach ($restaurant_options as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan</button></div>
</form></div></div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPassModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Tetapkan Semula Kata Laluan</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="reset_password"><input type="hidden" name="tab" value="<?= $tab ?>"><input type="hidden" name="id" id="reset-user-id">
    <p style="font-size:13px;color:var(--muted)">Akaun: <strong id="reset-user-name" style="color:var(--text)"></strong></p>
    <div class="mb-2"><label class="form-label">Kata Laluan Baru</label><input name="password" type="text" class="form-control" minlength="6" required placeholder="Min 6 aksara"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-key"></i> Tetapkan</button></div>
</form></div></div>

<?php
$page_scripts = <<<'HTML'
<script>
(function () {
  const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editUserModal'));
  document.querySelectorAll('.btn-edit-user').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('edit-user-id').value = btn.dataset.id;
    document.getElementById('edit-user-name').value = btn.dataset.name || '';
    document.getElementById('edit-user-email').value = btn.dataset.email || '';
    document.getElementById('edit-user-phone').value = btn.dataset.phone || '';
    const rsel = document.getElementById('edit-user-restaurant');
    if (rsel && btn.dataset.restaurant) rsel.value = btn.dataset.restaurant;
    editModal.show();
  }));

  const resetModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPassModal'));
  document.querySelectorAll('.btn-reset-pass').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('reset-user-id').value = btn.dataset.id;
    document.getElementById('reset-user-name').textContent = btn.dataset.name || '';
    resetModal.show();
  }));
})();
</script>
HTML;
require_once __DIR__ . '/../includes/superadmin_layout_end.php';
?>
