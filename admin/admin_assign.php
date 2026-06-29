<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$page_title = 'Assign Runner';
$restId = activeRestaurantId();

$pending = [];
$pstmt = mysqli_query($conn, "SELECT o.* FROM orders o WHERE o.status='pending' AND o.restaurant_id=$restId AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.order_id=o.id) ORDER BY o.created_at DESC");
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
  WHERE r.status = 'online' AND u.is_active = 1 AND r.restaurant_id = $restId AND $activeSql = 0
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
  WHERE u.is_active = 1 AND r.restaurant_id = $restId AND $activeSql > 0
  ORDER BY u.name ASC");
while ($r = mysqli_fetch_assoc($busyRes)) {
    $busy[] = $r;
}

$offline = [];
$offRes = mysqli_query($conn, "SELECT r.id, u.name, r.vehicle_no
  FROM runners r
  JOIN users u ON r.user_id = u.id
  WHERE r.status = 'offline' AND u.is_active = 1 AND r.restaurant_id = $restId AND $activeSql = 0
  ORDER BY u.name ASC");
while ($r = mysqli_fetch_assoc($offRes)) {
    $offline[] = $r;
}

require_once __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-header">
  <div class="page-header-left">
    <h4>Assign Runner</h4>
    <p>Assign runner kepada order pending</p>
  </div>
</div>

<?php if (!$available): ?>
<div class="alert-warn" id="no-runners-warn"><i class="ti ti-alert-triangle"></i> Tiada runner tersedia sekarang. Runner perlu aktifkan status online sendiri.</div>
<?php else: ?>
<div class="alert-warn d-none" id="no-runners-warn"><i class="ti ti-alert-triangle"></i> Tiada runner tersedia sekarang.</div>
<?php endif; ?>

<div class="assign-tab-buttons">
  <button type="button" class="assign-tab-btn active" data-panel="assign-panel-orders">Pending (<span id="pending-count"><?= count($pending) ?></span>)</button>
  <button type="button" class="assign-tab-btn" data-panel="assign-panel-runners">Runner Status</button>
</div>

<div class="assign-layout">
  <div class="assign-panel active" id="assign-panel-orders">
    <div class="card">
      <div class="card-header">Pending Orders <span style="color:var(--muted);font-size:12px;margin-left:6px" id="pending-count-label"><?= count($pending) ?> order</span></div>
      <div class="card-body" id="pending-list">
        <?php if (!$pending): ?>
        <div class="assign-empty" style="text-align:center;padding:40px;color:var(--muted)">
          <i class="ti ti-circle-check" style="font-size:40px;color:var(--primary)"></i>
          <p style="margin-top:12px;font-weight:700;color:var(--text)">Tiada order pending</p>
          <p style="font-size:13px;margin-bottom:0">Semua order telah diproses</p>
        </div>
        <?php endif; ?>
        <?php foreach ($pending as $o): ?>
        <div class="pending-card" data-order-id="<?= (int)$o['id'] ?>">
          <div class="pending-card-header">
            <span class="pending-order-no"><?= e($o['order_no']) ?></span>
            <span class="pending-time"><?= e($o['time_ago']) ?></span>
          </div>
          <div class="pending-customer"><?= e($o['customer_name']) ?> · <?= e($o['customer_phone']) ?></div>
          <div class="pending-address"><?= e($o['delivery_address']) ?></div>
          <div class="pending-items">
            <?php foreach ($o['items'] as $i): ?><div><?= e($i['item_name']) ?> × <?= (int)$i['quantity'] ?></div><?php endforeach; ?>
          </div>
          <div class="pending-footer">
            <div>
              <span class="pending-amount"><?= formatPrice((float)$o['total_amount']) ?></span>
              <span class="badge-status <?= $o['payment_method']==='online'?'status-assigned':'status-cancelled' ?>" style="margin-left:6px"><?= ucfirst(e($o['payment_method'])) ?></span>
            </div>
            <button class="btn-tracky btn-assign" <?= $available ? '' : 'disabled' ?> style="font-size:13px;padding:7px 16px"><i class="ti ti-route"></i> Assign</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="assign-panel" id="assign-panel-runners">
    <div class="runner-status-section">
      <div class="runner-status-header">
        <span class="dot dot-green"></span>
        <span class="runner-status-title">Online</span>
        <span class="runner-status-count" id="available-count"><?= count($available) ?></span>
      </div>
      <div class="runner-status-body" id="available-list">
        <?php if (!$available): ?>
        <div class="runner-empty">Tiada runner online yang tersedia</div>
        <?php else: foreach ($available as $r): ?>
        <div class="runner-row">
          <div class="runner-avatar"><?= strtoupper(substr($r['name'],0,2)) ?></div>
          <div class="flex-grow-1">
            <div class="runner-row-name"><?= e($r['name']) ?></div>
            <div class="runner-row-meta"><?= e($r['vehicle_no']) ?> · <?= (int)$r['deliveries_today'] ?> hantar hari ini</div>
          </div>
          <span class="badge-status status-assigned">Online</span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="runner-status-section">
      <div class="runner-status-header">
        <span class="dot dot-amber"></span>
        <span class="runner-status-title">Delivery</span>
        <span class="runner-status-count" id="busy-count"><?= count($busy) ?></span>
      </div>
      <div class="runner-status-body" id="busy-list">
        <?php if (!$busy): ?>
        <div class="runner-empty">Tiada runner sedang menghantar</div>
        <?php else: foreach ($busy as $r): ?>
        <div class="runner-row">
          <div class="runner-avatar"><?= strtoupper(substr($r['name'],0,2)) ?></div>
          <div class="flex-grow-1">
            <div class="runner-row-name"><?= e($r['name']) ?></div>
            <div class="runner-row-meta"><?= e($r['vehicle_no']) ?> · <i class="ti ti-bike"></i> <?= e($r['order_no']) ?></div>
          </div>
          <span class="badge-status status-assigned">Online</span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="runner-status-section">
      <div class="runner-status-header">
        <span class="dot dot-gray"></span>
        <span class="runner-status-title">Offline</span>
        <span class="runner-status-count" id="offline-count"><?= count($offline) ?></span>
      </div>
      <div class="runner-status-body" id="offline-list">
        <?php if (!$offline): ?>
        <div class="runner-empty">Semua runner aktif</div>
        <?php else: foreach ($offline as $r): ?>
        <div class="runner-row" style="opacity:0.6">
          <div class="runner-avatar"><?= strtoupper(substr($r['name'],0,2)) ?></div>
          <div class="flex-grow-1">
            <div class="runner-row-name"><?= e($r['name']) ?></div>
            <div class="runner-row-meta"><?= e($r['vehicle_no']) ?></div>
          </div>
          <span class="badge-status status-cancelled">Offline</span>
        </div>
        <?php endforeach; endif; ?>
        <div style="padding:10px 16px;font-size:12px;color:var(--muted);border-top:1px solid var(--border)"><i class="ti ti-info-circle"></i> Runner perlu aktifkan status online sendiri.</div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="assignModal"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Assign Runner ke Order</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="assign-order-id">
    <div id="assign-preview" class="mb-3" style="font-size:14px;font-weight:700;color:var(--primary);padding:10px 14px;background:var(--primary-dim);border:1.5px solid var(--primary-border);border-radius:9px"></div>
    <div class="mb-2"><label class="form-label">Pilih Runner</label></div>
    <select id="assign-runner-id" class="form-select">
      <?php foreach ($available as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?> · <?= e($r['vehicle_no']) ?></option><?php endforeach; ?>
    </select>
    <?php if (!$available): ?><p style="font-size:12px;color:var(--muted);margin-top:10px;margin-bottom:0"><i class="ti ti-info-circle"></i> Hanya runner berstatus Online boleh di-assign.</p><?php endif; ?>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
    <button class="btn-tracky" id="btn-confirm-assign" <?= $available ? '' : 'disabled' ?>><i class="ti ti-route"></i> Confirm Assign</button>
  </div>
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
        const card = btn.closest('[data-order-id]');
        if (!card) return;
        document.getElementById('assign-order-id').value = card.dataset.orderId;
        document.getElementById('assign-preview').textContent = card.querySelector('.pending-order-no')?.textContent || card.dataset.orderId;
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
        const res = await apiPost('/tracky/api/admin_assign_runner.php', {
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

  function initials(name) { return name ? name.substring(0,2).toUpperCase() : '?'; }

  function renderRunnerCard(r, type) {
    const badges = {
      available: '<span class="badge-status status-assigned">Online</span>',
      busy: '<span class="badge-status status-assigned">Online</span>',
      offline: '<span class="badge-status status-cancelled">Offline</span>',
    };
    const meta = type === 'available'
      ? `\${r.vehicle_no || ''} · \${r.deliveries_today || 0} hantar hari ini`
      : type === 'busy'
      ? `\${r.vehicle_no || ''} · <i class="ti ti-bike"></i> \${r.current_order_no || r.order_no || ''}`
      : `\${r.vehicle_no || ''}`;
    return `<div class="runner-row" style="\${type==='offline'?'opacity:0.6':''}">
      <div class="runner-avatar">\${initials(r.name)}</div>
      <div class="flex-grow-1">
        <div class="runner-row-name">\${r.name}</div>
        <div class="runner-row-meta">\${meta}</div>
      </div>
      \${badges[type] || ''}
    </div>`;
  }

  function renderPendingOrder(o) {
    const items = (o.items || []).map(i => `<div>\${i.item_name} × \${i.quantity}</div>`).join('');
    const payClass = o.payment_method === 'online' ? 'status-assigned' : 'status-cancelled';
    return `<div class="pending-card" data-order-id="\${o.id}">
      <div class="pending-card-header">
        <span class="pending-order-no">\${o.order_no}</span>
        <span class="pending-time">\${o.created_at ? o.created_at.slice(0,16).replace('T',' ') : ''}</span>
      </div>
      <div class="pending-customer">\${o.customer_name} · \${o.customer_phone || ''}</div>
      <div class="pending-address">\${o.delivery_address || ''}</div>
      <div class="pending-items">\${items}</div>
      <div class="pending-footer">
        <div>
          <span class="pending-amount">RM \${parseFloat(o.total_amount).toFixed(2)}</span>
          <span class="badge-status \${payClass}" style="margin-left:6px">\${o.payment_method || ''}</span>
        </div>
        <button type="button" class="btn-tracky btn-assign" \${runnersData.length ? '' : 'disabled'} style="font-size:13px;padding:7px 16px"><i class="ti ti-route"></i> Assign</button>
      </div>
    </div>`;
  }

  async function refreshBoard() {
    try {
      const [oRes, rRes] = await Promise.all([
        fetch('/tracky/api/admin_get_orders.php?status=pending', { credentials: 'same-origin' }),
        fetch('/tracky/api/admin_get_runners.php', { credentials: 'same-origin' }),
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
        : '<div class="assign-empty" style="text-align:center;padding:40px;color:var(--muted)"><i class="ti ti-circle-check" style="font-size:40px;color:var(--primary)"></i><p style="margin-top:12px;font-weight:700">Tiada order pending</p><p style="font-size:13px;margin-bottom:0">Semua order telah diproses</p></div>';
      bindAssign();

      document.getElementById('available-list').innerHTML = available.length
        ? available.map(r => renderRunnerCard(r, 'available')).join('')
        : '<div class="runner-empty">Tiada runner online yang tersedia</div>';
      document.getElementById('busy-list').innerHTML = busy.length
        ? busy.map(r => renderRunnerCard(r, 'busy')).join('')
        : '<div class="runner-empty">Tiada runner sedang menghantar</div>';
      document.getElementById('offline-list').innerHTML = (offline.length
        ? offline.map(r => renderRunnerCard(r, 'offline')).join('')
        : '<div class="runner-empty">Semua runner aktif</div>')
        + '<div style="padding:10px 16px;font-size:12px;color:var(--muted);border-top:1px solid var(--border)"><i class="ti ti-info-circle"></i> Runner perlu aktifkan status online sendiri.</div>';

      if (confirmBtn) confirmBtn.disabled = !runnersData.length;
    } catch (_) {}
  }

  setInterval(refreshBoard, 20000);

  const urlOrderId = new URLSearchParams(location.search).get('order_id');
  if (urlOrderId) {
    setTimeout(() => {
      const card = document.querySelector(`[data-order-id="\${urlOrderId}"]`);
      if (card && runnersData.length) {
        document.getElementById('assign-order-id').value = urlOrderId;
        document.getElementById('assign-preview').textContent = card.querySelector('.pending-order-no')?.textContent || urlOrderId;
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
