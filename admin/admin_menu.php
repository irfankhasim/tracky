<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Menu';
$tab = $_GET['tab'] ?? 'items';
$rid = activeRestaurantId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category') {
        $n = trim($_POST['name']); $d = trim($_POST['description']); $so = (int)$_POST['sort_order'];
        $stmt = mysqli_prepare($conn, 'INSERT INTO categories (restaurant_id, name, description, sort_order) VALUES (?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'issi', $rid, $n, $d, $so);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'delete_category') {
        $stmt = mysqli_prepare($conn, 'DELETE FROM categories WHERE id=? AND restaurant_id=?');
        $cid = (int)$_POST['id'];
        mysqli_stmt_bind_param($stmt, 'ii', $cid, $rid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'add_item') {
        $cid = (int)$_POST['category_id']; $n = trim($_POST['name']); $d = trim($_POST['description']); $p = (float)$_POST['price'];
        $avail = isset($_POST['is_available']) ? 1 : 0;
        // Ensure the category belongs to this restaurant.
        $ck = mysqli_query($conn, "SELECT id FROM categories WHERE id=$cid AND restaurant_id=$rid LIMIT 1");
        if (!$ck || mysqli_num_rows($ck) === 0) {
            $_SESSION['flash_error'] = 'Kategori tidak sah.';
            header('Location: /tracky/admin/admin_menu.php?tab=' . urlencode($tab)); exit;
        }
        $img = null;
        try {
            $img = uploadMenuImage($_FILES['image'] ?? []);
        } catch (RuntimeException $ex) {
            $_SESSION['flash_error'] = $ex->getMessage();
            header('Location: /tracky/admin/admin_menu.php?tab=' . urlencode($tab)); exit;
        }
        $stmt = mysqli_prepare($conn, 'INSERT INTO menu_items (restaurant_id, category_id, name, description, image, price, is_available) VALUES (?,?,?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'iisssdi', $rid, $cid, $n, $d, $img, $p, $avail);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'edit_category') {
        $id = (int)$_POST['id']; $n = trim($_POST['name']); $d = trim($_POST['description']); $so = (int)$_POST['sort_order'];
        $stmt = mysqli_prepare($conn, 'UPDATE categories SET name=?, description=?, sort_order=? WHERE id=? AND restaurant_id=?');
        mysqli_stmt_bind_param($stmt, 'ssiii', $n, $d, $so, $id, $rid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'toggle_category') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE categories SET is_active = IF(is_active=1,0,1) WHERE id=$id AND restaurant_id=$rid");
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    } elseif ($action === 'toggle_item') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE menu_items SET is_available = IF(is_available=1,0,1) WHERE id=$id AND restaurant_id=$rid");
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    } elseif ($action === 'delete_item') {
        $stmt = mysqli_prepare($conn, 'DELETE FROM menu_items WHERE id=? AND restaurant_id=?');
        $iid = (int)$_POST['id'];
        mysqli_stmt_bind_param($stmt, 'ii', $iid, $rid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: /tracky/admin/admin_menu.php?tab=' . urlencode($tab)); exit;
}

$categories = mysqli_query($conn, "SELECT * FROM categories WHERE restaurant_id=$rid ORDER BY sort_order");
$cat_filter = (int)($_GET['category'] ?? 0);
$item_sql = "SELECT m.*, c.name cat_name FROM menu_items m JOIN categories c ON c.id=m.category_id WHERE m.restaurant_id=$rid";
if ($cat_filter > 0) $item_sql .= " AND m.category_id=$cat_filter";
$item_sql .= ' ORDER BY c.sort_order, m.name';
$items = mysqli_query($conn, $item_sql);

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="alert-tracky alert-danger" style="margin-bottom:16px"><i class="ti ti-alert-circle"></i> <?= e($_SESSION['flash_error']) ?></div>
<?php unset($_SESSION['flash_error']); endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <h4>Menu</h4>
    <p>Urus item dan kategori menu restoran</p>
  </div>
  <div class="page-header-right">
    <?php if ($tab === 'items'): ?>
    <button class="btn-tracky" data-bs-toggle="modal" data-bs-target="#addItemModal" style="display:inline-flex;align-items:center;gap:8px;font-size:14px;padding:11px 22px">
      <i class="ti ti-plus" style="font-size:18px"></i> Tambah Item
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="orders-status-nav">
  <a href="?tab=items" class="status-tab <?= $tab==='items'?'active':'' ?>"><i class="ti ti-tools-kitchen-2"></i> Menu Items</a>
  <a href="?tab=categories" class="status-tab <?= $tab==='categories'?'active':'' ?>"><i class="ti ti-tag"></i> Categories</a>
</div>

<?php if ($tab === 'categories'): ?>
<div class="row g-4">
  <div class="col-md-4">
    <div class="card">
      <div class="card-header">Tambah Kategori</div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="add_category">
          <div class="mb-3"><label class="form-label">Nama Kategori</label><input name="name" class="form-control" placeholder="cth: Minuman" required></div>
          <div class="mb-3"><label class="form-label">Penerangan</label><input name="description" class="form-control" placeholder="Penerangan ringkas"></div>
          <div class="mb-3"><label class="form-label">Susunan</label><input name="sort_order" type="number" class="form-control" value="0"></div>
          <button class="btn-tracky"><i class="ti ti-plus"></i> Tambah</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">Senarai Kategori</div>
      <div class="table-responsive">
        <table class="table mb-0"><thead><tr><th>Nama</th><th>Urutan</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
        <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--text)"><?= e($c['name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($c['description']) ?></div>
          </td>
          <td style="color:var(--muted)"><?= (int)$c['sort_order'] ?></td>
          <td>
            <button type="button" class="btn-icon btn-toggle-cat <?= $c['is_active']?'primary':'' ?>" data-id="<?= (int)$c['id'] ?>">
              <?= $c['is_active']?'<i class="ti ti-check"></i> Active':'<i class="ti ti-eye-off"></i> Off' ?>
            </button>
          </td>
          <td><div class="btn-actions">
            <button type="button" class="btn-icon primary btn-edit-cat" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-desc="<?= e($c['description']) ?>" data-sort="<?= (int)$c['sort_order'] ?>"><i class="ti ti-edit"></i> Edit</button>
            <form method="post" class="d-inline" onsubmit="return confirm('Padam kategori ini?')">
              <input type="hidden" name="action" value="delete_category">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn-icon danger"><i class="ti ti-trash"></i> Padam</button>
            </form>
          </div></td>
        </tr>
        <?php endwhile; ?>
        </tbody></table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editCatModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Edit Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" name="action" value="edit_category"><input type="hidden" name="id" id="edit-cat-id">
    <div class="mb-3"><label class="form-label">Nama</label><input name="name" id="edit-cat-name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Penerangan</label><input name="description" id="edit-cat-desc" class="form-control"></div>
    <div class="mb-3"><label class="form-label">Susunan</label><input name="sort_order" id="edit-cat-sort" type="number" class="form-control"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn-tracky">Simpan</button></div>
</form></div></div>

<?php
$page_scripts = <<<'HTML'
<script>
(function () {
  document.querySelectorAll('.btn-toggle-cat').forEach(btn => btn.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'toggle_category');
    fd.append('id', btn.dataset.id);
    fd.append('ajax', '1');
    const res = await fetch(location.pathname + '?tab=categories', { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.success) {
      const on = btn.classList.contains('primary');
      btn.classList.toggle('primary', !on);
      btn.innerHTML = !on ? '<i class="ti ti-check"></i> Active' : '<i class="ti ti-eye-off"></i> Off';
    }
  }));
  const catModalEl = document.getElementById('editCatModal');
  if (catModalEl) {
    const editCatModal = bootstrap.Modal.getOrCreateInstance(catModalEl);
    document.querySelectorAll('.btn-edit-cat').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById('edit-cat-id').value = btn.dataset.id;
        document.getElementById('edit-cat-name').value = btn.dataset.name || '';
        document.getElementById('edit-cat-desc').value = btn.dataset.desc || '';
        document.getElementById('edit-cat-sort').value = btn.dataset.sort || '0';
        editCatModal.show();
      });
    });
  }
})();
</script>
HTML;
?>
<?php else: ?>
<div class="filter-bar">
  <div class="filter-bar-tabs">
    <a href="?tab=items" class="filter-tab <?= !$cat_filter?'active':'' ?>">Semua</a>
    <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
    <a href="?tab=items&category=<?= (int)$c['id'] ?>" class="filter-tab <?= $cat_filter==$c['id']?'active':'' ?>"><?= e($c['name']) ?></a>
    <?php endwhile; ?>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0"><thead><tr><th>Item</th><th>Kategori</th><th>Harga</th><th>Status</th><th>Tindakan</th></tr></thead><tbody>
    <?php while ($m = mysqli_fetch_assoc($items)): ?>
    <tr>
      <td>
        <div class="d-flex align-items-center gap-3">
          <?php if (!empty($m['image'])): ?>
          <div class="menu-img-icon" style="padding:0;overflow:hidden"><img src="<?= e(menuImageUrl($m['image'])) ?>" alt="<?= e($m['name']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
          <?php else: ?>
          <div class="menu-img-icon"><i class="ti ti-tools-kitchen-2"></i></div>
          <?php endif; ?>
          <div>
            <div style="font-weight:700;color:var(--text)"><?= e($m['name']) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= e($m['description']) ?></div>
          </div>
        </div>
      </td>
      <td><span class="badge-status status-assigned" style="font-size:11px"><?= e($m['cat_name']) ?></span></td>
      <td style="font-weight:700;color:var(--primary)"><?= formatPrice((float)$m['price']) ?></td>
      <td>
        <button type="button" class="btn-icon btn-toggle-avail <?= $m['is_available']?'primary':'' ?>" data-id="<?= (int)$m['id'] ?>">
          <?= $m['is_available']?'<i class="ti ti-check"></i> Tersedia':'<i class="ti ti-eye-off"></i> Habis' ?>
        </button>
      </td>
      <td><div class="btn-actions">
        <button type="button" class="btn-icon primary btn-edit-menu"
          data-id="<?= (int)$m['id'] ?>"
          data-name="<?= e($m['name']) ?>"
          data-cat="<?= (int)$m['category_id'] ?>"
          data-desc="<?= e($m['description'] ?? '') ?>"
          data-price="<?= (float)$m['price'] ?>"
          data-img="<?= e(menuImageUrl($m['image'] ?? '')) ?>"
          data-avail="<?= (int)$m['is_available'] ?>">
          <i class="ti ti-edit"></i> Edit
        </button>
        <form method="post" class="d-inline" onsubmit="return confirm('Padam item ini?')">
          <input type="hidden" name="action" value="delete_item">
          <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn-icon danger"><i class="ti ti-trash"></i> Padam</button>
        </form>
      </div></td>
    </tr>
    <?php endwhile; ?>
    </tbody></table>
  </div>
</div>
<div class="modal fade" id="addItemModal"><div class="modal-dialog"><form method="post" class="modal-content" enctype="multipart/form-data">
  <div class="modal-header"><h5>Tambah Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="add_item">
    <div class="mb-2"><select name="category_id" class="form-select" required><?php mysqli_data_seek($categories,0); while($c=mysqli_fetch_assoc($categories)): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endwhile; ?></select></div>
    <div class="mb-2"><input name="name" class="form-control" placeholder="Nama item" required></div>
    <div class="mb-2"><textarea name="description" class="form-control" placeholder="Penerangan"></textarea></div>
    <div class="mb-2"><input name="price" type="number" step="0.01" min="0" class="form-control" placeholder="Harga" required></div>
    <div class="mb-3">
      <label class="form-label fw-medium">Gambar Item</label>
      <input type="file" name="image" id="addItemImage" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
      <div class="form-text" style="color:var(--muted)">Pilihan. JPG/PNG/WEBP/GIF, maksimum 3MB.</div>
      <img id="addItemPreview" src="" alt="" style="display:none;margin-top:10px;width:100%;max-height:160px;object-fit:cover;border-radius:10px;border:1.5px solid var(--border)">
    </div>
    <div class="mb-2"><label class="form-check"><input type="checkbox" name="is_available" value="1" class="form-check-input" checked> Tersedia</label></div>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-tracky">Simpan</button></div>
</form></div></div>

<div class="modal fade" id="editMenuModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="border-radius:16px">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-semibold">Edit Item Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editMenuId">
        <div class="mb-3">
          <label class="form-label fw-medium">Nama Item <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="editMenuName" placeholder="cth: Nasi Lemak Ayam" required>
          <div class="invalid-feedback">Nama item diperlukan</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Kategori <span class="text-danger">*</span></label>
          <select class="form-select" id="editMenuCategory" required>
            <?php mysqli_data_seek($categories, 0); while ($cat = mysqli_fetch_assoc($categories)): ?>
            <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Penerangan</label>
          <textarea class="form-control" id="editMenuDesc" rows="2" placeholder="Penerangan ringkas item ini..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Harga (RM) <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">RM</span>
            <input type="number" class="form-control" id="editMenuPrice" step="0.50" min="0.50" placeholder="0.00" required>
          </div>
          <div class="invalid-feedback">Harga mesti lebih dari 0</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Gambar Item</label>
          <div class="d-flex align-items-center gap-3">
            <img id="editMenuPreview" src="" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:1.5px solid var(--border);background:var(--bg3)">
            <input type="file" class="form-control" id="editMenuImage" accept="image/png,image/jpeg,image/webp,image/gif">
          </div>
          <div class="form-text" style="color:var(--muted)">Biar kosong untuk kekalkan gambar sedia ada. Maksimum 3MB.</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-medium">Status</label>
          <select class="form-select" id="editMenuAvailable">
            <option value="1">Tersedia</option>
            <option value="0">Tidak Tersedia (Habis)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-success" id="btnSaveEditMenu">
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
  const modalEl = document.getElementById('editMenuModal');
  if (!modalEl) return;

  const editModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const saveBtn = document.getElementById('btnSaveEditMenu');

  const imgInput = document.getElementById('editMenuImage');
  const imgPreview = document.getElementById('editMenuPreview');
  const placeholderImg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" fill="%23222"/></svg>';

  document.querySelectorAll('.btn-edit-menu').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editMenuId').value = btn.dataset.id;
      document.getElementById('editMenuName').value = btn.dataset.name || '';
      document.getElementById('editMenuCategory').value = btn.dataset.cat || '';
      document.getElementById('editMenuDesc').value = btn.dataset.desc || '';
      document.getElementById('editMenuPrice').value = parseFloat(btn.dataset.price || 0).toFixed(2);
      document.getElementById('editMenuAvailable').value = btn.dataset.avail || '1';
      if (imgInput) imgInput.value = '';
      if (imgPreview) imgPreview.src = btn.dataset.img || placeholderImg;
      modalEl.querySelectorAll('.form-control, .form-select').forEach(f => f.classList.remove('is-invalid', 'is-valid'));
      editModal.show();
    });
  });

  if (imgInput && imgPreview) {
    imgInput.addEventListener('change', () => {
      const f = imgInput.files[0];
      if (f) imgPreview.src = URL.createObjectURL(f);
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      const id = document.getElementById('editMenuId').value;
      const name = document.getElementById('editMenuName').value.trim();
      const category = document.getElementById('editMenuCategory').value;
      const desc = document.getElementById('editMenuDesc').value.trim();
      const price = parseFloat(document.getElementById('editMenuPrice').value);
      const available = document.getElementById('editMenuAvailable').value;

      const nameEl = document.getElementById('editMenuName');
      const priceEl = document.getElementById('editMenuPrice');
      nameEl.classList.toggle('is-invalid', !name);
      priceEl.classList.toggle('is-invalid', !price || price <= 0);
      if (!name || !price || price <= 0) return;

      const defaultHtml = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="ti ti-loader-2"></i> Menyimpan...';
      saveBtn.disabled = true;

      const body = new FormData();
      body.append('item_id', id);
      body.append('name', name);
      body.append('category_id', category);
      body.append('description', desc);
      body.append('price', String(price));
      body.append('is_available', available);
      if (imgInput && imgInput.files[0]) body.append('image', imgInput.files[0]);

      fetch('/tracky/api/admin_edit_menu.php', {
        method: 'POST',
        credentials: 'same-origin',
        body,
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          editModal.hide();
          showToast('Item menu berjaya dikemaskini!', 'success');
          setTimeout(() => location.reload(), 1000);
        } else {
          showToast(data.message || data.error || 'Gagal kemaskini item', 'danger');
        }
      })
      .catch(() => showToast('Ralat sambungan', 'danger'))
      .finally(() => {
        saveBtn.innerHTML = defaultHtml;
        saveBtn.disabled = false;
      });
    });
  }

  const addImg = document.getElementById('addItemImage');
  const addPreview = document.getElementById('addItemPreview');
  if (addImg && addPreview) {
    addImg.addEventListener('change', () => {
      const f = addImg.files[0];
      if (f) { addPreview.src = URL.createObjectURL(f); addPreview.style.display = 'block'; }
      else { addPreview.style.display = 'none'; }
    });
  }

  document.querySelectorAll('.btn-toggle-avail').forEach(btn => btn.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'toggle_item');
    fd.append('id', btn.dataset.id);
    fd.append('ajax', '1');
    const res = await fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.success) {
      const on = btn.classList.contains('primary');
      btn.classList.toggle('primary', !on);
      btn.innerHTML = !on ? '<i class="ti ti-check"></i> Tersedia' : '<i class="ti ti-eye-off"></i> Habis';
    }
  }));
})();
</script>
HTML;
?>
<?php endif; require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
