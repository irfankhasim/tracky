<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Runners';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name']); $email = trim($_POST['email']); $pass = $_POST['password'];
        $phone = trim($_POST['phone']); $vehicle = trim($_POST['vehicle_no']);
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $ustmt = mysqli_prepare($conn, "INSERT INTO users (name,email,password,role,phone) VALUES (?,?,?,'runner',?)");
        mysqli_stmt_bind_param($ustmt, 'ssss', $name, $email, $hash, $phone);
        mysqli_stmt_execute($ustmt);
        $uid = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($ustmt);
        $rstmt = mysqli_prepare($conn, 'INSERT INTO runners (user_id, vehicle_no, phone, status) VALUES (?,?,?,?)');
        $st = 'offline';
        mysqli_stmt_bind_param($rstmt, 'isss', $uid, $vehicle, $phone, $st);
        mysqli_stmt_execute($rstmt);
        mysqli_stmt_close($rstmt);
    } elseif ($action === 'toggle_active') {
        $rid = (int)$_POST['runner_id'];
        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT u.id, u.is_active FROM runners r JOIN users u ON u.id=r.user_id WHERE r.id=$rid"));
        if ($row) {
            $newActive = (int)$row['is_active'] ? 0 : 1;
            $uid = (int)$row['id'];
            $stmt = mysqli_prepare($conn, 'UPDATE users SET is_active=? WHERE id=?');
            mysqli_stmt_bind_param($stmt, 'ii', $newActive, $uid);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    header('Location: /tracky/admin/runners.php'); exit;
}

$runners = mysqli_query($conn, "SELECT r.*, u.name, u.email, u.is_active FROM runners r JOIN users u ON u.id=r.user_id ORDER BY u.name");
require_once __DIR__ . '/../includes/admin_layout_start.php';

function runnerStatusBadge(string $status): array
{
    return match ($status) {
        'online' => ['success', 'Online'],
        'busy' => ['warning text-dark', 'Busy'],
        default => ['secondary', 'Offline'],
    };
}
?>
<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">Senarai Runner</h5>
  <button class="btn btn-tracky btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">+ Tambah Runner</button>
</div>
<div class="card w-100"><div class="table-responsive"><table class="table mb-0 table-responsive-cards"><thead><tr><th>Nama</th><th>Phone</th><th>Kenderaan</th><th>Status</th><th>Akaun</th><th>Actions</th></tr></thead><tbody>
<?php while ($r = mysqli_fetch_assoc($runners)):
  [$badgeClass, $badgeLabel] = runnerStatusBadge($r['status']);
  $inactive = !(int)$r['is_active'];
?>
<tr class="<?= $inactive ? 'opacity-50' : '' ?>">
  <td data-label="Nama"><?= e($r['name']) ?><br><small class="text-muted"><?= e($r['email']) ?></small></td>
  <td data-label="Phone"><?= e($r['phone']) ?></td>
  <td data-label="Kenderaan"><?= e($r['vehicle_no']) ?></td>
  <td data-label="Status"><span class="badge bg-<?= $badgeClass ?>"><?= e($badgeLabel) ?></span></td>
  <td data-label="Akaun"><?= $inactive ? '<span class="badge bg-danger">Inactive</span>' : '<span class="badge bg-success">Active</span>' ?></td>
  <td data-label="Actions" class="text-nowrap td-actions">
    <button type="button" class="btn btn-sm btn-outline-primary btn-edit-runner"
      data-id="<?= (int)$r['id'] ?>"
      data-name="<?= e($r['name']) ?>"
      data-email="<?= e($r['email']) ?>"
      data-phone="<?= e($r['phone'] ?? '') ?>"
      data-vehicle="<?= e($r['vehicle_no'] ?? '') ?>">
      <i class="ti ti-edit"></i> Edit
    </button>
    <form method="post" class="d-inline" onsubmit="return confirm('<?= $inactive ? 'Aktifkan' : 'Nyahaktifkan' ?> runner ini?')">
      <input type="hidden" name="action" value="toggle_active">
      <input type="hidden" name="runner_id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-sm btn-outline-<?= $inactive ? 'success' : 'danger' ?>"><?= $inactive ? 'Activate' : 'Deactivate' ?></button>
    </form>
  </td>
</tr>
<?php endwhile; ?>
</tbody></table></div></div>

<div class="modal fade" id="addModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5>Tambah Runner</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="add">
    <div class="mb-2"><label class="form-label">Nama</label><input name="name" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
    <div class="mb-2"><label class="form-label">Phone</label><input name="phone" class="form-control" required></div>
    <div class="mb-2"><label class="form-label">No Kenderaan</label><input name="vehicle_no" class="form-control"></div>
    <p class="small text-muted mb-0">Runner baru bermula dengan status Offline.</p>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-tracky">Simpan</button></div>
</form></div></div>

<div class="modal fade" id="editRunnerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:16px">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold">Edit Runner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editRunnerId">
        <div class="mb-3">
          <label class="form-label fw-medium">Nama Penuh</label>
          <input type="text" class="form-control" id="editRunnerName" required>
          <div class="invalid-feedback">Nama diperlukan</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">E-mel</label>
          <input type="email" class="form-control" id="editRunnerEmail" readonly style="background:#f9fafb">
          <small class="text-muted">E-mel tidak boleh diubah</small>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">No. Telefon</label>
          <input type="text" class="form-control" id="editRunnerPhone" placeholder="01x-xxxxxxx" required>
          <div class="invalid-feedback">No. telefon diperlukan</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">No. Kenderaan</label>
          <input type="text" class="form-control" id="editRunnerVehicle" placeholder="cth: MKM 1234" required>
          <div class="invalid-feedback">No. kenderaan diperlukan</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Tetapkan Semula Kata Laluan (optional)</label>
          <input type="password" class="form-control" id="editRunnerPassword" placeholder="Kosongkan jika tidak mahu tukar">
          <small class="text-muted">Isi hanya jika mahu tukar kata laluan</small>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnSaveEditRunner">
          <i class="ti ti-device-floppy"></i> Simpan Perubahan
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$page_scripts = <<<'HTML'
<script>
(function () {
  const modalEl = document.getElementById('editRunnerModal');
  if (!modalEl) return;

  const editModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const saveBtn = document.getElementById('btnSaveEditRunner');

  document.querySelectorAll('.btn-edit-runner').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editRunnerId').value = btn.dataset.id;
      document.getElementById('editRunnerName').value = btn.dataset.name || '';
      document.getElementById('editRunnerEmail').value = btn.dataset.email || '';
      document.getElementById('editRunnerPhone').value = btn.dataset.phone || '';
      document.getElementById('editRunnerVehicle').value = btn.dataset.vehicle || '';
      document.getElementById('editRunnerPassword').value = '';
      modalEl.querySelectorAll('.form-control').forEach(f => f.classList.remove('is-invalid', 'is-valid'));
      editModal.show();
    });
  });

  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      const id = document.getElementById('editRunnerId').value;
      const name = document.getElementById('editRunnerName').value.trim();
      const phone = document.getElementById('editRunnerPhone').value.trim();
      const vehicle = document.getElementById('editRunnerVehicle').value.trim();
      const password = document.getElementById('editRunnerPassword').value;

      let valid = true;
      const nameEl = document.getElementById('editRunnerName');
      const phoneEl = document.getElementById('editRunnerPhone');
      const vehicleEl = document.getElementById('editRunnerVehicle');

      nameEl.classList.toggle('is-invalid', !name);
      phoneEl.classList.toggle('is-invalid', !phone);
      vehicleEl.classList.toggle('is-invalid', !vehicle);
      if (!name || !phone || !vehicle) return;

      const defaultHtml = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="ti ti-loader-2"></i> Menyimpan...';
      saveBtn.disabled = true;

      const body = new URLSearchParams({ runner_id: id, name, phone, vehicle, password });

      fetch('/tracky/api/edit_runner.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: body.toString(),
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          editModal.hide();
          showToast('Runner berjaya dikemaskini!', 'success');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.message || data.error || 'Gagal kemaskini runner', 'danger');
        }
      })
      .catch(() => showToast('Ralat sambungan', 'danger'))
      .finally(() => {
        saveBtn.innerHTML = defaultHtml;
        saveBtn.disabled = false;
      });
    });
  }
})();
</script>
HTML;
require_once __DIR__ . '/../includes/admin_layout_end.php';
?>
