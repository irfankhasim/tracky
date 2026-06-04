async function loadMenu(categoryId = 'all') {
  const url = categoryId === 'all'
    ? '/tracky/api/get_menu.php'
    : `/tracky/api/get_menu.php?category=${encodeURIComponent(categoryId)}`;
  const res = await fetch(url);
  return res.json();
}

async function cartAction(action, payload = {}) {
  const body = new URLSearchParams({ action, ...payload });
  const res = await fetch('/tracky/api/cart.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
  });
  return res.json();
}

async function getCart() {
  const res = await fetch('/tracky/api/cart.php?action=get');
  return res.json();
}

function updateCartBadge(count) {
  document.querySelectorAll('[data-cart-count]').forEach(el => {
    el.textContent = count;
    el.style.display = count > 0 ? '' : 'none';
  });
  const floatEl = document.getElementById('cart-float');
  if (floatEl) floatEl.style.display = count > 0 ? 'block' : 'none';
}
