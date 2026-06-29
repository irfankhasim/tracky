<?php
require_once __DIR__ . '/../includes/superadmin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Kedai';

function saFlash(string $msg, string $type = 'success'): void
{
    $_SESSION['sa_flash'] = ['msg' => $msg, 'type' => $type];
}

// The "primary" store is the one operations currently use (getRestaurant = lowest id).
$primaryRes = mysqli_query($conn, 'SELECT id FROM restaurants ORDER BY id ASC LIMIT 1');
$primary_id = $primaryRes && ($row = mysqli_fetch_assoc($primaryRes)) ? (int) $row['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add_store' || $action === 'edit_store') {
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $hours = trim($_POST['operating_hours'] ?? '');
            $fee = (float) ($_POST['delivery_fee'] ?? 0);
            $free_min = (float) ($_POST['free_delivery_min'] ?? 0);
            $is_open = isset($_POST['is_open']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $accent = trim($_POST['accent_color'] ?? '') ?: '#1D9E75';
            $slug = strtolower(trim($_POST['slug'] ?? ''));
            $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
            $slug = trim($slug, '-');
            if ($name === '') {
                throw new RuntimeException('Nama kedai diperlukan.');
            }
            if ($slug === '') {
                $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($name)), '-') ?: 'kedai';
            }

            $logo = uploadImageTo($_FILES['logo'] ?? [], 'restaurants', 'logo');
            $cover = uploadImageTo($_FILES['cover_image'] ?? [], 'restaurants', 'cover');

            if ($action === 'add_store') {
                // Ensure unique slug.
                $base = $slug; $i = 1;
                while (true) {
                    $sEsc = mysqli_real_escape_string($conn, $slug);
                    $chk = mysqli_query($conn, "SELECT id FROM restaurants WHERE slug='$sEsc' LIMIT 1");
                    if (!$chk || mysqli_num_rows($chk) === 0) break;
                    $slug = $base . '-' . (++$i);
                }
                $stmt = mysqli_prepare($conn, 'INSERT INTO restaurants (name, slug, address, phone, email, operating_hours, delivery_fee, free_delivery_min, is_open, is_active, accent_color, logo, cover_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                mysqli_stmt_bind_param($stmt, 'ssssssddiisss', $name, $slug, $address, $phone, $email, $hours, $fee, $free_min, $is_open, $is_active, $accent, $logo, $cover);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                saFlash('Kedai "' . e($name) . '" berjaya ditambah.');
            } else {
                $id = (int) $_POST['id'];
                // Ensure unique slug excluding self.
                $base = $slug; $i = 1;
                while (true) {
                    $sEsc = mysqli_real_escape_string($conn, $slug);
                    $chk = mysqli_query($conn, "SELECT id FROM restaurants WHERE slug='$sEsc' AND id<>$id LIMIT 1");
                    if (!$chk || mysqli_num_rows($chk) === 0) break;
                    $slug = $base . '-' . (++$i);
                }
                $stmt = mysqli_prepare($conn, 'UPDATE restaurants SET name=?, slug=?, address=?, phone=?, email=?, operating_hours=?, delivery_fee=?, free_delivery_min=?, is_open=?, is_active=?, accent_color=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'ssssssddiisi', $name, $slug, $address, $phone, $email, $hours, $fee, $free_min, $is_open, $is_active, $accent, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                if ($logo !== null) {
                    $u = mysqli_prepare($conn, 'UPDATE restaurants SET logo=? WHERE id=?');
                    mysqli_stmt_bind_param($u, 'si', $logo, $id);
                    mysqli_stmt_execute($u);
                    mysqli_stmt_close($u);
                }
                if ($cover !== null) {
                    $u = mysqli_prepare($conn, 'UPDATE restaurants SET cover_image=? WHERE id=?');
                    mysqli_stmt_bind_param($u, 'si', $cover, $id);
                    mysqli_stmt_execute($u);
                    mysqli_stmt_close($u);
                }
                saFlash('Kedai "' . e($name) . '" dikemaskini.');
            }
        } elseif ($action === 'toggle_open') {
            $id = (int) $_POST['id'];
            mysqli_query($conn, "UPDATE restaurants SET is_open = IF(is_open=1,0,1) WHERE id=$id");
            saFlash('Status kedai dikemaskini.');
        } elseif ($action === 'toggle_active') {
            $id = (int) $_POST['id'];
            mysqli_query($conn, "UPDATE restaurants SET is_active = IF(is_active=1,0,1) WHERE id=$id");
            saFlash('Status aktif kedai dikemaskini.');
        } elseif ($action === 'manage') {
            $id = (int) $_POST['id'];
            $_SESSION['sa_acting_restaurant'] = $id;
            header('Location: /tracky/admin/admin_dashboard.php');
            exit;
        } elseif ($action === 'delete_store') {
            $id = (int) $_POST['id'];
            $cntRes = mysqli_query($conn, 'SELECT COUNT(*) c FROM restaurants');
            $cnt = (int) mysqli_fetch_assoc($cntRes)['c'];
            if ($cnt <= 1) {
                throw new RuntimeException('Tidak boleh padam kedai terakhir — sistem perlu sekurang-kurangnya satu kedai.');
            }
            mysqli_query($conn, "DELETE FROM restaurants WHERE id=$id");
            saFlash('Kedai berjaya dipadam.');
        }
    } catch (RuntimeException $ex) {
        saFlash($ex->getMessage(), 'danger');
    }
    header('Location: /tracky/superadmin/superadmin_stores.php');
    exit;
}

$stores = mysqli_query($conn, 'SELECT * FROM restaurants ORDER BY id ASC');
$flash = $_SESSION['sa_flash'] ?? null;
unset($_SESSION['sa_flash']);

require_once __DIR__ . '/../includes/superadmin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Pengurusan Kedai</h4>
    <p>Urus semua kedai / restoran dalam sistem</p>
  </div>
  <div class="page-header-right">
    <button class="btn-tracky" data-bs-toggle="modal" data-bs-target="#addStoreModal" style="display:inline-flex;align-items:center;gap:8px"><i class="ti ti-plus"></i> Tambah Kedai</button>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert-tracky <?= $flash['type'] === 'danger' ? 'alert-danger' : 'alert-success' ?>" style="margin-bottom:16px">
  <i class="ti ti-<?= $flash['type'] === 'danger' ? 'alert-circle' : 'circle-check' ?>"></i> <?= e($flash['msg']) ?>
</div>
<?php endif; ?>

<div class="alert-tracky alert-success" style="margin-bottom:16px;background:var(--primary-dim);color:var(--text-2);border-color:var(--border)">
  <i class="ti ti-info-circle"></i> Kedai bertanda <strong>Utama</strong> ialah kedai yang digunakan untuk operasi (menu &amp; order pelanggan) buat masa ini.
</div>

<div class="stores-grid">
  <?php while ($s = mysqli_fetch_assoc($stores)): ?>
  <div class="card store-card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start" style="margin-bottom:8px">
        <div class="d-flex align-items-center gap-2">
          <?php $logoUrl = restaurantAsset($s['logo'] ?? null); ?>
          <span style="width:34px;height:34px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#fff;flex-shrink:0;background:<?= e($s['accent_color'] ?: '#1D9E75') ?>;overflow:hidden">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><?= strtoupper(substr($s['name'],0,1)) ?><?php endif; ?>
          </span>
          <div style="font-size:16px;font-weight:800;color:var(--text)"><?= e($s['name']) ?></div>
        </div>
        <div class="d-flex gap-1">
          <?php if ((int)$s['id'] === $primary_id): ?><span class="badge-status status-assigned" style="font-size:10px">Utama</span><?php endif; ?>
          <span class="badge-status <?= !empty($s['is_active']) ? 'status-delivered' : 'status-cancelled' ?>" style="font-size:10px"><?= !empty($s['is_active']) ? 'Aktif' : 'Nyahaktif' ?></span>
        </div>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-bottom:10px;min-height:32px"><i class="ti ti-map-pin"></i> <?= e($s['address'] ?: '-') ?></div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
        <span class="badge-status <?= !empty($s['is_open']) ? 'status-delivered' : 'status-cancelled' ?>" style="font-size:11px"><?= !empty($s['is_open']) ? 'Dibuka' : 'Ditutup' ?></span>
        <span class="badge-status status-assigned" style="font-size:11px"><i class="ti ti-clock"></i> <?= e($s['operating_hours'] ?: '-') ?></span>
      </div>
      <div style="font-size:12px;color:var(--muted);line-height:1.9;border-top:1px solid var(--border);padding-top:10px">
        <div><i class="ti ti-phone"></i> <?= e($s['phone'] ?: '-') ?></div>
        <div><i class="ti ti-mail"></i> <?= e($s['email'] ?: '-') ?></div>
        <div><i class="ti ti-truck"></i> Yuran <?= formatPrice((float)$s['delivery_fee']) ?> · Percuma ≥ <?= formatPrice((float)$s['free_delivery_min']) ?></div>
      </div>
      <div class="btn-actions" style="margin-top:14px">
        <form method="post" class="d-inline">
          <input type="hidden" name="action" value="manage"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn-icon primary" title="Urus panel kedai ini"><i class="ti ti-settings"></i> Urus</button>
        </form>
        <button type="button" class="btn-icon btn-edit-store"
          data-id="<?= (int)$s['id'] ?>"
          data-name="<?= e($s['name']) ?>"
          data-slug="<?= e($s['slug'] ?? '') ?>"
          data-address="<?= e($s['address']) ?>"
          data-phone="<?= e($s['phone']) ?>"
          data-email="<?= e($s['email']) ?>"
          data-hours="<?= e($s['operating_hours']) ?>"
          data-fee="<?= e(number_format((float)$s['delivery_fee'], 2, '.', '')) ?>"
          data-freemin="<?= e(number_format((float)$s['free_delivery_min'], 2, '.', '')) ?>"
          data-open="<?= (int)$s['is_open'] ?>"
          data-active="<?= (int)($s['is_active'] ?? 1) ?>"><i class="ti ti-edit"></i> Edit</button>
        <form method="post" class="d-inline">
          <input type="hidden" name="action" value="toggle_open"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn-icon" title="Buka/Tutup"><i class="ti ti-<?= !empty($s['is_open']) ? 'door-off' : 'door' ?>"></i></button>
        </form>
        <form method="post" class="d-inline" onsubmit="return confirm('Padam kedai ini?')">
          <input type="hidden" name="action" value="delete_store"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button class="btn-icon danger"><i class="ti ti-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endwhile; ?>
</div>

<!-- Add Store Modal -->
<div class="modal fade" id="addStoreModal"><div class="modal-dialog"><form method="post" class="modal-content" enctype="multipart/form-data">
  <div class="modal-header"><h5 class="modal-title">Tambah Kedai</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="add_store">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Nama Kedai</label><input name="name" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Slug URL <span class="text-muted" style="font-weight:500">(pilihan)</span></label><input name="slug" class="form-control" placeholder="auto dari nama"></div>
      <div class="col-md-6"><label class="form-label">No. Telefon</label><input name="phone" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control"></div>
      <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
      <div class="col-12"><label class="form-label">Waktu Operasi</label><input name="operating_hours" class="form-control" placeholder="8:00 AM - 10:00 PM"></div>
      <div class="col-md-6"><label class="form-label">Yuran Penghantaran (RM)</label><input name="delivery_fee" type="number" step="0.50" min="0" class="form-control" value="5.00"></div>
      <div class="col-md-6"><label class="form-label">Percuma Minimum (RM)</label><input name="free_delivery_min" type="number" step="0.50" min="0" class="form-control" value="30.00"></div>
      <div class="col-md-6"><label class="form-label">Logo</label><input name="logo" type="file" accept="image/*" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Gambar Kulit (Cover)</label><input name="cover_image" type="file" accept="image/*" class="form-control"></div>
      <div class="col-12 d-flex gap-4">
        <label class="form-check d-inline-flex align-items-center gap-2"><input type="checkbox" name="is_open" value="1" class="form-check-input" checked> Dibuka</label>
        <label class="form-check d-inline-flex align-items-center gap-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked> Aktif (papar kepada pelanggan)</label>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan</button></div>
</form></div></div>

<!-- Edit Store Modal -->
<div class="modal fade" id="editStoreModal"><div class="modal-dialog"><form method="post" class="modal-content" enctype="multipart/form-data">
  <div class="modal-header"><h5 class="modal-title">Edit Kedai</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="edit_store"><input type="hidden" name="id" id="es-id">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Nama Kedai</label><input name="name" id="es-name" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Slug URL</label><input name="slug" id="es-slug" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">No. Telefon</label><input name="phone" id="es-phone" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Email</label><input name="email" id="es-email" type="email" class="form-control"></div>
      <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" id="es-address" class="form-control" rows="2"></textarea></div>
      <div class="col-12"><label class="form-label">Waktu Operasi</label><input name="operating_hours" id="es-hours" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Yuran Penghantaran (RM)</label><input name="delivery_fee" id="es-fee" type="number" step="0.50" min="0" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Percuma Minimum (RM)</label><input name="free_delivery_min" id="es-freemin" type="number" step="0.50" min="0" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Logo Baharu <span class="text-muted" style="font-weight:500">(pilihan)</span></label><input name="logo" type="file" accept="image/*" class="form-control"></div>
      <div class="col-md-6"><label class="form-label">Cover Baharu <span class="text-muted" style="font-weight:500">(pilihan)</span></label><input name="cover_image" type="file" accept="image/*" class="form-control"></div>
      <div class="col-12 d-flex gap-4">
        <label class="form-check d-inline-flex align-items-center gap-2"><input type="checkbox" name="is_open" id="es-open" value="1" class="form-check-input"> Dibuka</label>
        <label class="form-check d-inline-flex align-items-center gap-2"><input type="checkbox" name="is_active" id="es-active" value="1" class="form-check-input"> Aktif (papar kepada pelanggan)</label>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn-tracky"><i class="ti ti-device-floppy"></i> Simpan</button></div>
</form></div></div>

<?php
$page_scripts = <<<'HTML'
<script>
(function () {
  const editModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editStoreModal'));
  document.querySelectorAll('.btn-edit-store').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('es-id').value = btn.dataset.id;
    document.getElementById('es-name').value = btn.dataset.name || '';
    document.getElementById('es-slug').value = btn.dataset.slug || '';
    document.getElementById('es-phone').value = btn.dataset.phone || '';
    document.getElementById('es-address').value = btn.dataset.address || '';
    document.getElementById('es-email').value = btn.dataset.email || '';
    document.getElementById('es-hours').value = btn.dataset.hours || '';
    document.getElementById('es-fee').value = btn.dataset.fee || '0.00';
    document.getElementById('es-freemin').value = btn.dataset.freemin || '0.00';
    document.getElementById('es-open').checked = btn.dataset.open === '1';
    document.getElementById('es-active').checked = btn.dataset.active === '1';
    editModal.show();
  }));
})();
</script>
HTML;
require_once __DIR__ . '/../includes/superadmin_layout_end.php';
?>
