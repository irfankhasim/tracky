document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const toggle = document.getElementById('sidebarToggle');

  if (toggle && sidebar && overlay) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }

  document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth < 993 && sidebar && overlay) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      }
    });
  });

  document.querySelectorAll('.assign-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.assign-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.assign-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      const panel = document.getElementById(this.dataset.panel);
      if (panel) panel.classList.add('active');
    });
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth >= 993) {
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('show');
    }
  });

  refreshNotifCount();
  setInterval(refreshNotifCount, 30000);
});

async function refreshNotifCount() {
  const badge = document.getElementById('notif-count');
  if (!badge) return;
  try {
    const res = await fetch('/tracky/api/admin_get_notifications.php?count_only=1');
    const data = await res.json();
    const count = data.unread_count ?? data.count ?? 0;
    if (count > 0) {
      badge.textContent = count > 9 ? '9+' : count;
      badge.classList.add('show');
      badge.style.display = 'flex';
    } else {
      badge.classList.remove('show');
      badge.style.display = 'none';
    }
  } catch (_) {}
}

function showToast(message, type = 'success') {
  const colors = {
    success: '#1D9E75',
    danger: '#ef4444',
    warning: '#f59e0b',
    info: '#3b82f6',
  };
  const icons = {
    success: 'check',
    danger: 'alert-circle',
    warning: 'alert-triangle',
    info: 'info-circle',
  };

  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    background: ${colors[type] || colors.success};
    color: white;
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 200px;
    max-width: 360px;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
  `;
  toast.innerHTML = `<i class="ti ti-${icons[type] || icons.success}"></i> ${message}`;
  document.body.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '1';
    toast.style.transform = 'translateY(0)';
  }, 50);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}
