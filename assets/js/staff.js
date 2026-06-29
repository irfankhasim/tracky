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
    const res = await fetch('/tracky/api/staff_get_notifications.php?count_only=1');
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

async function apiPost(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return res.json();
}
