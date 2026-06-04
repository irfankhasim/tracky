<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Menu';
$tab = $_GET['tab'] ?? 'items';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_category') {
        $n = trim($_POST['name']); $d = trim($_POST['description']); $so = (int)$_POST['sort_order'];
        $stmt = mysqli_prepare($conn, 'INSERT INTO categories (name, description, sort_order) VALUES (?,?,?)');
        mysqli_stmt_bind_param($stmt, 'ssi', $n, $d, $so);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'delete_category') {
        mysqli_query($conn, 'DELETE FROM categories WHERE id=' . (int)$_POST['id']);
    } elseif ($action === 'add_item') {
        $cid = (int)$_POST['category_id']; $n = trim($_POST['name']); $d = trim($_POST['description']); $p = (float)$_POST['price'];
        $avail = isset($_POST['is_available']) ? 1 : 0;
        $stmt = mysqli_prepare($conn, 'INSERT INTO menu_items (category_id, name, description, price, is_available) VALUES (?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'issdi', $cid, $n, $d, $p, $avail);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'edit_category') {
        $id = (int)$_POST['id']; $n = trim($_POST['name']); $d = trim($_POST['description']); $so = (int)$_POST['sort_order'];
        $stmt = mysqli_prepare($conn, 'UPDATE categories SET name=?, description=?, sort_order=? WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'ssii', $n, $d, $so, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'toggle_category') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE categories SET is_active = IF(is_active=1,0,1) WHERE id=$id");
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    } elseif ($action === 'toggle_item') {
        $id = (int)$_POST['id'];
        mysqli_query($conn, "UPDATE menu_items SET is_available = IF(is_available=1,0,1) WHERE id=$id");
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['success'=>true]); exit; }
    } elseif ($action === 'delete_item') {
        mysqli_query($conn, 'DELETE FROM menu_items WHERE id=' . (int)$_POST['id']);
    }
    header('Location: /tracky/admin/menu.php?tab=' . urlencode($tab)); exit;
}

$categories = mysqli_query($conn, 'SELECT * FROM categories ORDER BY sort_order');
$cat_filter = (int)($_GET['category'] ?? 0);
$item_sql = 'SELECT m.*, c.name cat_name FROM menu_items m JOIN categories c ON c.id=m.category_id';
if ($cat_filter > 0) $item_sql .= " WHERE m.category_id=$cat_filter";
$item_sql .= ' ORDER BY c.sort_order, m.name';
$items = mysqli_query($conn, $item_sql);

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<ul class="nav nav-tabs mb-3 menu-tabs-responsive flex-nowrap overflow-auto">
  <li class="nav-item"><a class="nav-link <?= $tab==='items'?'active':'' ?>" href="?tab=items">Menu Items</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab==='categories'?'active':'' ?>" href="?tab=categories">Categories</a></li>
</ul>
<?php if ($tab === 'categories'): ?>
<div class="row g-4">
  <div class="col-md-5"><div class="card"><div class="card-header">Tambah Kategori</div><div class="card-body">
    <form method="post"><input type="hidden" name="action" value="add_category">
      <div class="mb-2"><input name="name" class="form-control" placeholder="Nama" required></div>
      <div class="mb-2"><input name="description" class="form-control" placeholder="Penerangan"></div>
      <div class="mb-2"><input name="sort_order" type="number" class="form-control" value="0"></div>
      <button class="btn btn-tracky btn-sm">Tambah</button>
    </form>
  </div></div></div>
  <div class="col-md-7 w-100"><div class="card w-100"><div class="table-responsive"><table class="table mb-0 table-responsive-cards"><thead><tr><th>Nama</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>
    <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
    <tr>
      <td data-label="Nama"><?= e($c['name']) ?><br><small class="text-muted"><?= e($c['description']) ?></small></td>
      <td data-label="Order"><?= (int)$c['sort_order'] ?></td>
      <td data-label="Status"><button type="button" class="btn btn-sm btn-toggle-cat" data-id="<?= (int)$c['id'] ?>"><?= $c['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Off</span>' ?></button></td>
      <td data-label="Actions" class="td-actions">
        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-cat" data-id="<?= (int)$c['id'] ?>" data-name="<?= e($c['name']) ?>" data-desc="<?= e($c['description']) ?>" data-sort="<?= (int)$c['sort_order'] ?>">Edit</button>
        <form method="post" class="d-inline" onsubmit="return confirm('Padam kategori?')"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody></table></div></div></div>
</div>
<div class="modal fade" id="editCatModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5>Edit Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="edit_category"><input type="hidden" name="id" id="edit-cat-id">
    <div class="mb-2"><input name="name" id="edit-cat-name" class="form-control" required></div>
    <div class="mb-2"><input name="description" id="edit-cat-desc" class="form-control"></div>
    <div class="mb-2"><input name="sort_order" id="edit-cat-sort" type="number" class="form-control"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-tracky">Simpan</button></div>
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
      const on = btn.innerHTML.includes('Active');
      btn.innerHTML = on ? '<span class="badge bg-secondary">Off</span>' : '<span class="badge bg-success">Active</span>';
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
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="?tab=items" class="btn btn-sm <?= !$cat_filter?'btn-tracky':'btn-outline-secondary' ?>">All</a>
  <?php mysqli_data_seek($categories, 0); while ($c = mysqli_fetch_assoc($categories)): ?>
  <a href="?tab=items&category=<?= (int)$c['id'] ?>" class="btn btn-sm <?= $cat_filter==$c['id']?'btn-tracky':'btn-outline-secondary' ?>"><?= e($c['name']) ?></a>
  <?php endwhile; ?>
  <button class="btn btn-tracky btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#addItemModal">+ Item</button>
</div>
<div class="card menu-list-mobile"><div class="table-responsive"><table class="table mb-0 table-responsive-cards"><thead><tr><th>Item</th><th>Kategori</th><th>Harga</th><th>Available</th><th></th></tr></thead><tbody>
<?php while ($m = mysqli_fetch_assoc($items)): ?>
<tr><td data-label="Item"><strong><?= e($m['name']) ?></strong><br><small class="text-muted"><?= e($m['description']) ?></small></td>
<td data-label="Kategori"><?= e($m['cat_name']) ?></td><td data-label="Harga"><?= formatPrice((float)$m['price']) ?></td>
<td data-label="Available"><button type="button" class="btn btn-sm btn-toggle-avail" data-id="<?= (int)$m['id'] ?>"><?= $m['is_available']?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>' ?></button></td>
<td data-label="Actions" class="td-actions">
  <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-edit-menu"
    data-id="<?= (int)$m['id'] ?>"
    data-name="<?= e($m['name']) ?>"
    data-cat="<?= (int)$m['category_id'] ?>"
    data-desc="<?= e($m['description'] ?? '') ?>"
    data-price="<?= (float)$m['price'] ?>"
    data-avail="<?= (int)$m['is_available'] ?>">
    <i class="ti ti-edit"></i> Edit
  </button>
  <form method="post" class="d-inline" onsubmit="return confirm('Padam item?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
</td></tr>
<?php endwhile; ?>
</tbody></table></div></div>
<div class="modal fade" id="addItemModal"><div class="modal-dialog"><form method="post" class="modal-content">
  <div class="modal-header"><h5>Tambah Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="add_item">
    <div class="mb-2"><select name="category_id" class="form-select" required><?php mysqli_data_seek($categories,0); while($c=mysqli_fetch_assoc($categories)): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endwhile; ?></select></div>
    <div class="mb-2"><input name="name" class="form-control" placeholder="Nama item" required></div>
    <div class="mb-2"><textarea name="description" class="form-control" placeholder="Penerangan"></textarea></div>
    <div class="mb-2"><input name="price" type="number" step="0.01" min="0" class="form-control" placeholder="Harga" required></div>
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

  document.querySelectorAll('.btn-edit-menu').forEach(btn => {
    btn.addEventListener('click', () => {
      document.getElementById('editMenuId').value = btn.dataset.id;
      document.getElementById('editMenuName').value = btn.dataset.name || '';
      document.getElementById('editMenuCategory').value = btn.dataset.cat || '';
      document.getElementById('editMenuDesc').value = btn.dataset.desc || '';
      document.getElementById('editMenuPrice').value = parseFloat(btn.dataset.price || 0).toFixed(2);
      document.getElementById('editMenuAvailable').value = btn.dataset.avail || '1';
      modalEl.querySelectorAll('.form-control, .form-select').forEach(f => f.classList.remove('is-invalid', 'is-valid'));
      editModal.show();
    });
  });

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

      const body = new URLSearchParams({
        item_id: id,
        name,
        category_id: category,
        description: desc,
        price: String(price),
        is_available: available,
      });

      fetch('/tracky/api/edit_menu_item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: body.toString(),
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

  document.querySelectorAll('.btn-toggle-avail').forEach(btn => btn.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action', 'toggle_item');
    fd.append('id', btn.dataset.id);
    fd.append('ajax', '1');
    const res = await fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' });
    const data = await res.json();
    if (data.success) {
      const yes = btn.innerHTML.includes('Yes');
      btn.innerHTML = yes ? '<span class="badge bg-secondary">No</span>' : '<span class="badge bg-success">Yes</span>';
    }
  }));
})();
</script>
HTML;
?>
<?php endif; require_once __DIR__ . '/../includes/admin_layout_end.php'; ?>
