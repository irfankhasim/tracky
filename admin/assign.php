<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Assign Runner';

$pending = [];
$pstmt = mysqli_query($conn, "SELECT o.* FROM orders o WHERE o.status='pending' AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.order_id=o.id) ORDER BY o.created_at DESC");
while ($row = mysqli_fetch_assoc($pstmt)) {
    $row['items'] = getOrderItems($conn, (int)$row['id']);
    $row['time_ago'] = timeAgo($row['created_at']);
    $pending[] = $row;
}

$activeSql = "(SELECT COUNT(*) FROM deliveries d WHERE d.runner_id = r.id AND d.status IN ('assigned','picked_up','in_transit'))";

$available = [];
$available_ids = [];
$availRes = mysqli_query($conn, "SELECT r.id, u.name, u.phone, r.vehicle_no, r.status,
  $activeSql AS active_deliveries,
  (SELECT COUNT(*) FROM deliveries d WHERE d.runner_id = r.id AND d.status = 'delivered' AND DATE(d.delivered_at) = CURDATE()) AS deliveries_today
  FROM runners r
  JOIN users u ON r.user_id = u.id
  WHERE r.status = 'online' AND u.is_active = 1 AND $activeSql = 0
  ORDER BY u.name ASC");
while ($r = mysqli_fetch_assoc($availRes)) {
    $rid = (int) $r['id'];
    if (isset($available_ids[$rid])) {
        continue;
    }
    $available_ids[$rid] = true;
    $available[] = $r;
}

$busy = [];
$busyRes = mysqli_query($conn, "SELECT r.id, u.name, r.vehicle_no,
  (SELECT o.order_no FROM deliveries d JOIN orders o ON o.id = d.order_id
   WHERE d.runner_id = r.id AND d.status IN ('assigned','picked_up','in_transit')
   ORDER BY d.assigned_at DESC LIMIT 1) AS order_no
  FROM runners r
  JOIN users u ON u.id = r.user_id
  WHERE u.is_active = 1 AND $activeSql > 0
  ORDER BY u.name ASC");
while ($r = mysqli_fetch_assoc($busyRes)) {
    $busy[] = $r;
}

$offline = [];
$offRes = mysqli_query($conn, "SELECT r.id, u.name, r.vehicle_no
  FROM runners r
  JOIN users u ON r.user_id = u.id
  WHERE r.status = 'offline' AND u.is_active = 1 AND $activeSql = 0
  ORDER BY u.name ASC");
while ($r = mysqli_fetch_assoc($offRes)) {
    $offline[] = $r;
}

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="alert alert-warning <?= $available ? 'd-none' : '' ?>" id="no-runners-warn">Tiada runner tersedia sekarang. Runner perlu aktifkan status online sendiri.</div>
<div class="assign-tab-buttons">
  <button type="button" class="assign-tab-btn active" data-panel="assign-panel-orders">Order Pending</button>
  <button type="button" class="assign-tab-btn" data-panel="assign-panel-runners">Runner Tersedia</button>
</div>
<div class="assign-layout">
  <div class="assign-panel active" id="assign-panel-orders">
    <div class="card"><div class="card-header">Pending Orders (<span id="pending-count"><?= count($pending) ?></span>)</div><div class="card-body" id="pending-list">
      <?php if (!$pending): ?><p class="text-muted mb-0">Tiada order pending.</p><?php endif; ?>
      <?php foreach ($pending as $o): ?>
      <div class="order-card mb-3" data-order-id="<?= (int)$o['id'] ?>">
        <div class="d-flex justify-content-between"><strong><?= e($o['order_no']) ?></strong><span class="badge bg-light text-dark"><?= e($o['time_ago']) ?></span></div>
        <p class="mb-1 fw-semibold"><?= e($o['customer_name']) ?> · <?= e($o['customer_phone']) ?></p>
        <p class="small text-muted"><?= e($o['delivery_address']) ?></p>
        <div class="small bg-light rounded p-2 mb-2"><?php foreach ($o['items'] as $i): ?><div><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div><?php endforeach; ?></div>
        <div class="d-flex gap-1 mb-2">
          <span class="badge bg-<?= $o['payment_method']==='online'?'info':'dark' ?>"><?= e($o['payment_method']) ?></span>
          <span class="badge bg-warning text-dark"><?= e($o['payment_status']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <strong class="text-success"><?= formatPrice((float)$o['total_amount']) ?></strong>
          <button class="btn btn-tracky btn-sm btn-assign" <?= $available ? '' : 'disabled' ?>>Assign</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div></div>
  </div>
  <div class="assign-panel" id="assign-panel-runners">
    <div class="card mb-3">
      <div class="card-header bg-success text-white">Runner Tersedia (<span id="available-count"><?= count($available) ?></span>)</div>
      <div class="card-body" id="available-list">
        <?php if (!$available): ?><p class="text-muted small mb-0">Tiada runner online yang tersedia.</p><?php endif; ?>
        <?php foreach ($available as $r): ?>
        <div class="order-card mb-2">
          <strong><?= e($r['name']) ?></strong> <span class="badge bg-success">Online</span>
          <div class="small text-muted"><?= e($r['phone']) ?> · <?= e($r['vehicle_no']) ?> · <?= (int)$r['deliveries_today'] ?> hari ini</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card mb-3">
      <div class="card-header bg-warning text-dark">Runner Sibuk (<span id="busy-count"><?= count($busy) ?></span>)</div>
      <div class="card-body" id="busy-list">
        <?php if (!$busy): ?><p class="text-muted small mb-0">Tiada runner sedang menghantar.</p><?php endif; ?>
        <?php foreach ($busy as $r): ?>
        <div class="order-card mb-2">
          <strong><?= e($r['name']) ?></strong> <span class="badge bg-warning text-dark">Busy</span>
          <div class="small text-muted"><?= e($r['vehicle_no']) ?></div>
          <div class="small text-warning-emphasis mt-1"><i class="ti ti-bike"></i> Sedang hantar <?= e($r['order_no']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header bg-secondary text-white">Runner Offline (<span id="offline-count"><?= count($offline) ?></span>)</div>
      <div class="card-body" id="offline-list">
        <?php if (!$offline): ?><p class="text-muted small mb-0">Semua runner aktif atau sibuk.</p><?php endif; ?>
        <?php foreach ($offline as $r): ?>
        <div class="order-card mb-2">
          <strong><?= e($r['name']) ?></strong> <span class="badge bg-secondary">Offline</span>
          <div class="small text-muted"><?= e($r['vehicle_no']) ?></div>
          <div class="small text-muted mt-1">Runner sedang tidak bertugas</div>
        </div>
        <?php endforeach; ?>
        <p class="small text-muted mb-0 mt-2"><i class="ti ti-info-circle"></i> Runner perlu aktifkan status online sendiri.</p>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="assignModal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5>Assign Runner</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="assign-order-id">
    <div id="assign-preview" class="mb-3 p-2 bg-light rounded"></div>
    <select id="assign-runner-id" class="form-select"><?php foreach ($available as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select>
    <?php if (!$available): ?><p class="small text-muted mt-2 mb-0">Hanya runner berstatus Online boleh di-assign.</p><?php endif; ?>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-tracky" id="btn-confirm-assign" <?= $available ? '' : 'disabled' ?>>Assign</button></div>
</div></div></div>
<?php
$runners_json = json_encode(array_values(array_map(
    fn($r) => ['id' => (int)$r['id'], 'name' => $r['name']],
    $available
)));
$page_scripts = <<<HTML
<script>
(function () {
  const modalEl = document.getElementById('assignModal');
  if (!modalEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  let runnersData = {$runners_json};
  const confirmBtn = document.getElementById('btn-confirm-assign');

  function bindAssign() {
    document.querySelectorAll('.btn-assign').forEach(btn => {
      btn.onclick = () => {
        if (!runnersData.length) {
          showToast('Tiada runner tersedia', 'warning');
          return;
        }
        const card = btn.closest('.order-card');
        if (!card) return;
        document.getElementById('assign-order-id').value = card.dataset.orderId;
        document.getElementById('assign-preview').textContent = card.querySelector('strong').textContent;
        const sel = document.getElementById('assign-runner-id');
        sel.innerHTML = runnersData.map(r => `<option value="\${r.id}">\${r.name}</option>`).join('');
        modal.show();
      };
    });
  }

  bindAssign();

  if (confirmBtn) {
    confirmBtn.addEventListener('click', async () => {
      const orderId = parseInt(document.getElementById('assign-order-id').value, 10);
      const runnerId = parseInt(document.getElementById('assign-runner-id').value, 10);
      if (!orderId || !runnerId) {
        showToast('Sila pilih runner', 'danger');
        return;
      }

      confirmBtn.disabled = true;
      try {
        const res = await apiPost('/tracky/api/assign_runner.php', {
          order_id: orderId,
          runner_id: runnerId,
        });
        if (res.success) {
          modal.hide();
          showToast(res.message || 'Runner berjaya di-assign', 'success');
          setTimeout(() => location.reload(), 600);
        } else {
          showToast(res.message || res.error || 'Gagal assign runner', 'danger');
          confirmBtn.disabled = false;
        }
      } catch {
        showToast('Ralat sambungan', 'danger');
        confirmBtn.disabled = false;
      }
    });
  }

  function renderRunnerCard(r, type) {
    if (type === 'available') {
      return `<div class="order-card mb-2"><strong>\${r.name}</strong> <span class="badge bg-success">Online</span>
        <div class="small text-muted">\${r.phone || r.user_phone || ''} · \${r.vehicle_no || ''} · \${r.deliveries_today || 0} hari ini</div></div>`;
    }
    if (type === 'busy') {
      return `<div class="order-card mb-2"><strong>\${r.name}</strong> <span class="badge bg-warning text-dark">Busy</span>
        <div class="small text-muted">\${r.vehicle_no || ''}</div>
        <div class="small mt-1"><i class="ti ti-bike"></i> Sedang hantar \${r.current_order_no || r.order_no || ''}</div></div>`;
    }
    return `<div class="order-card mb-2"><strong>\${r.name}</strong> <span class="badge bg-secondary">Offline</span>
      <div class="small text-muted">\${r.vehicle_no || ''}</div>
      <div class="small text-muted mt-1">Runner sedang tidak bertugas</div></div>`;
  }

  function renderPendingOrder(o) {
    const items = (o.items || []).map(i => `<div>\${i.item_name} × \${i.quantity}</div>`).join('');
    const pay = o.payment_method === 'online' ? 'info' : 'dark';
    return `<div class="order-card mb-3" data-order-id="\${o.id}">
      <div class="d-flex justify-content-between"><strong>\${o.order_no}</strong><span class="badge bg-light text-dark">\${o.created_at ? o.created_at.slice(0,16).replace('T',' ') : ''}</span></div>
      <p class="mb-1 fw-semibold">\${o.customer_name} · \${o.customer_phone || ''}</p>
      <p class="small text-muted">\${o.delivery_address || ''}</p>
      <div class="small bg-light rounded p-2 mb-2">\${items}</div>
      <div class="d-flex gap-1 mb-2"><span class="badge bg-\${pay}">\${o.payment_method || ''}</span></div>
      <div class="d-flex justify-content-between align-items-center">
        <strong class="text-success">RM \${parseFloat(o.total_amount).toFixed(2)}</strong>
        <button type="button" class="btn btn-tracky btn-sm btn-assign" \${runnersData.length ? '' : 'disabled'}>Assign</button>
      </div>
    </div>`;
  }

  async function refreshBoard() {
    try {
      const [oRes, rRes] = await Promise.all([
        fetch('/tracky/api/get_orders.php?status=pending', { credentials: 'same-origin' }),
        fetch('/tracky/api/get_runners.php', { credentials: 'same-origin' }),
      ]);
      const orders = (await oRes.json()).orders || [];
      const data = await rRes.json();
      const available = data.available || [];
      const busy = data.busy || [];
      const offline = data.offline || [];

      runnersData = available.map(r => ({ id: parseInt(r.id, 10), name: r.name }));
      document.getElementById('pending-count').textContent = orders.length;
      document.getElementById('available-count').textContent = available.length;
      document.getElementById('busy-count').textContent = busy.length;
      document.getElementById('offline-count').textContent = offline.length;
      document.getElementById('no-runners-warn').classList.toggle('d-none', available.length > 0);

      document.getElementById('pending-list').innerHTML = orders.length
        ? orders.map(renderPendingOrder).join('')
        : '<p class="text-muted mb-0">Tiada order pending.</p>';
      bindAssign();

      document.getElementById('available-list').innerHTML = available.length
        ? available.map(r => renderRunnerCard(r, 'available')).join('')
        : '<p class="text-muted small mb-0">Tiada runner online yang tersedia.</p>';
      document.getElementById('busy-list').innerHTML = busy.length
        ? busy.map(r => renderRunnerCard(r, 'busy')).join('')
        : '<p class="text-muted small mb-0">Tiada runner sedang menghantar.</p>';
      document.getElementById('offline-list').innerHTML = (offline.length
        ? offline.map(r => renderRunnerCard(r, 'offline')).join('')
        : '<p class="text-muted small mb-0">Semua runner aktif atau sibuk.</p>')
        + '<p class="small text-muted mb-0 mt-2"><i class="ti ti-info-circle"></i> Runner perlu aktifkan status online sendiri.</p>';

      if (confirmBtn) confirmBtn.disabled = !runnersData.length;
    } catch (_) {}
  }

  setInterval(refreshBoard, 20000);

  const urlOrderId = new URLSearchParams(location.search).get('order_id');
  if (urlOrderId) {
    setTimeout(() => {
      const card = document.querySelector(`.order-card[data-order-id="\${urlOrderId}"]`);
      if (card && runnersData.length) {
        document.getElementById('assign-order-id').value = urlOrderId;
        document.getElementById('assign-preview').textContent = card.querySelector('strong').textContent;
        const sel = document.getElementById('assign-runner-id');
        sel.innerHTML = runnersData.map(r => `<option value="\${r.id}">\${r.name}</option>`).join('');
        modal.show();
      }
    }, 150);
  }
})();
</script>
HTML;
require_once __DIR__ . '/../includes/admin_layout_end.php';
?>
