<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role'] ?? '') {
        case 'superadmin':
            header('Location: /tracky/superadmin/superadmin_dashboard.php');
            break;
        case 'admin':
            header('Location: /tracky/admin/admin_dashboard.php');
            break;
        case 'staff':
            header('Location: /tracky/admin/admin_dashboard.php');
            break;
        case 'runner':
            header('Location: /tracky/runner/runner_orders.php');
            break;
        default:
            header('Location: /tracky/login.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Sila isi semua maklumat.';
    } else {
        $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, role, is_active, phone, restaurant_id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'E-mel tidak dijumpai dalam sistem.';
        } elseif (!$user['is_active']) {
            $error = 'Akaun anda telah digantung. Sila hubungi admin.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Kata laluan tidak betul.';
        } elseif ($user['role'] === 'runner') {
            $rstmt = mysqli_prepare($conn, 'SELECT id, status FROM runners WHERE user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($rstmt, 'i', $user['id']);
            mysqli_stmt_execute($rstmt);
            $r = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));
            mysqli_stmt_close($rstmt);
            if (!$r) {
                $error = 'Akaun runner tidak lengkap. Sila hubungi admin.';
            }
        }

        if (!$error) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['phone'] = $user['phone'];
            $_SESSION['restaurant_id'] = $user['restaurant_id'] !== null ? (int) $user['restaurant_id'] : null;

            if ($user['role'] === 'runner') {
                $rstmt = mysqli_prepare($conn, 'SELECT id, status FROM runners WHERE user_id = ? LIMIT 1');
                mysqli_stmt_bind_param($rstmt, 'i', $user['id']);
                mysqli_stmt_execute($rstmt);
                $r = mysqli_fetch_assoc(mysqli_stmt_get_result($rstmt));
                mysqli_stmt_close($rstmt);
                $_SESSION['runner_id'] = (int) $r['id'];
                $_SESSION['runner_status'] = $r['status'];
            }

            switch ($user['role']) {
                case 'superadmin':
                    header('Location: /tracky/superadmin/superadmin_dashboard.php');
                    break;
                case 'admin':
                    header('Location: /tracky/admin/admin_dashboard.php');
                    break;
                case 'staff':
                    header('Location: /tracky/admin/admin_dashboard.php');
                    break;
                case 'runner':
                    header('Location: /tracky/runner/runner_orders.php');
                    break;
                default:
                    header('Location: /tracky/login.php');
            }
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1D9E75">
  <link rel="icon" type="image/png" href="/tracky/assets/img/favicon-64.png">
  <link rel="apple-touch-icon" href="/tracky/assets/img/apple-touch-icon.png">
  <title>Log Masuk — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.44.0/dist/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green: #1D9E75;
      --green-dark: #0F6E56;
      --green-dim: rgba(29,158,117,0.12);
      --dark: #080B0F;
      --dark2: #0E1117;
      --dark3: #141820;
      --border: rgba(255,255,255,0.07);
      --border-hover: rgba(29,158,117,0.3);
      --text: #E5E7EB;
      --muted: #6B7280;
    }

    body.light-mode {
      --dark: #F8FAFC;
      --dark2: #F1F5F9;
      --dark3: #FFFFFF;
      --border: rgba(0,0,0,0.08);
      --text: #1E293B;
      --muted: #64748B;
      --green-dim: rgba(29,158,117,0.1);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--dark);
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1fr 1fr;
      color: var(--text);
      transition: background 0.3s, color 0.3s;
    }

    /* ── THEME TOGGLE ── */
    .theme-toggle {
      position: fixed;
      top: 20px; right: 24px;
      width: 38px; height: 38px;
      background: var(--dark3);
      border: 1px solid var(--border);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      color: var(--muted);
      font-size: 1.1rem;
      transition: all 0.2s;
      z-index: 100;
    }
    .theme-toggle:hover { border-color: var(--green); color: var(--green); }

    /* ── LEFT PANEL ── */
    .left-panel {
      background: var(--dark2);
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 48px;
      position: relative;
      overflow: hidden;
    }
    .left-panel::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 30%, rgba(29,158,117,0.15) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 80% 80%, rgba(29,158,117,0.06) 0%, transparent 50%);
      pointer-events: none;
    }
    .left-panel-grid {
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(var(--border) 1px, transparent 1px),
        linear-gradient(90deg, var(--border) 1px, transparent 1px);
      background-size: 48px 48px;
      mask-image: radial-gradient(ellipse 80% 80% at 30% 40%, black 20%, transparent 100%);
    }

    .left-brand {
      position: relative;
      z-index: 1;
    }
    .brand-logo {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 1.3rem;
      font-weight: 800;
      color: #fff;
      text-decoration: none;
      letter-spacing: -0.5px;
    }
    body.light-mode .brand-logo { color: #0F172A; }
    .brand-logo .logo-icon {
      width: 40px; height: 40px;
      border-radius: 10px;
      object-fit: cover;
      box-shadow: 0 0 20px rgba(29,158,117,0.4);
    }

    .left-hero {
      position: relative;
      z-index: 1;
    }
    .left-hero h2 {
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      font-weight: 800;
      letter-spacing: -1px;
      line-height: 1.15;
      color: #fff;
      margin-bottom: 16px;
    }
    body.light-mode .left-hero h2 { color: #0F172A; }
    .left-hero h2 span {
      background: linear-gradient(135deg, var(--green), #34d399);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .left-hero p {
      font-size: 0.9rem;
      color: var(--muted);
      line-height: 1.7;
      max-width: 340px;
    }

    .feature-list {
      margin-top: 36px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .feature-item {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 0.85rem;
      color: #9CA3AF;
    }
    .feature-dot {
      width: 28px; height: 28px;
      background: var(--green-dim);
      border: 1px solid rgba(29,158,117,0.2);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      color: var(--green);
      font-size: 0.85rem;
      flex-shrink: 0;
    }

    .left-footer {
      position: relative;
      z-index: 1;
      font-size: 0.75rem;
      color: var(--muted);
    }

    /* ── RIGHT PANEL ── */
    .right-panel {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      background: var(--dark);
    }

    .login-box {
      width: 100%;
      max-width: 400px;
    }

    .login-header {
      margin-bottom: 36px;
    }
    .login-header h1 {
      font-size: 1.7rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.5px;
    }
    body.light-mode .login-header h1 { color: #0F172A; }
    body.light-mode .field-label { color: #374151; }
    body.light-mode .form-input { color: #0F172A; }
    body.light-mode .form-input::placeholder { color: #9CA3AF; }
    body.light-mode .role-card-text span { color: #0F172A; }
    body.light-mode .divider { color: #9CA3AF; }
    body.light-mode .link-back { color: #9CA3AF; }
    .login-header p {
      font-size: 0.875rem;
      color: var(--muted);
      margin-top: 6px;
    }

    /* Error */
    .error-alert {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: rgba(220,38,38,0.08);
      border: 1px solid rgba(220,38,38,0.25);
      border-radius: 12px;
      padding: 14px 16px;
      color: #F87171;
      font-size: 0.85rem;
      margin-bottom: 24px;
      animation: shake 0.4s ease;
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-4px); }
      40%, 80% { transform: translateX(4px); }
    }
    .error-alert i { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

    /* Form */
    .field-group { margin-bottom: 20px; }
    .field-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: #D1D5DB;
      margin-bottom: 8px;
      letter-spacing: 0.02em;
    }
    .field-wrap { position: relative; }
    .field-icon {
      position: absolute;
      left: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 1rem;
      pointer-events: none;
      transition: color 0.2s;
    }
    .form-input {
      width: 100%;
      background: var(--dark3);
      border: 1.5px solid var(--border);
      border-radius: 12px;
      padding: 12px 14px 12px 42px;
      font-size: 0.9rem;
      font-family: 'Inter', sans-serif;
      color: #fff;
      outline: none;
      transition: all 0.2s;
    }
    .form-input::placeholder { color: #4B5563; }
    .form-input:focus {
      border-color: var(--green);
      background: rgba(29,158,117,0.04);
      box-shadow: 0 0 0 3px rgba(29,158,117,0.12);
    }
    .form-input:focus + .field-icon { color: var(--green); }
    .form-input.is-invalid {
      border-color: rgba(220,38,38,0.6);
      background: rgba(220,38,38,0.04);
    }
    .form-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(220,38,38,0.1); }
    .invalid-msg {
      font-size: 0.75rem;
      color: #F87171;
      margin-top: 6px;
      display: none;
      align-items: center;
      gap: 4px;
    }
    .form-input.is-invalid ~ .invalid-msg { display: flex; }

    .toggle-pw {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 4px;
      font-size: 1rem;
      transition: color 0.2s;
      z-index: 1;
    }
    .toggle-pw:hover { color: #fff; }

    /* Submit */
    .btn-submit {
      width: 100%;
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 12px;
      padding: 13px;
      font-size: 0.95rem;
      font-weight: 700;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      transition: all 0.25s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
      box-shadow: 0 0 24px rgba(29,158,117,0.3);
    }
    .btn-submit:hover:not(:disabled) {
      background: var(--green-dark);
      transform: translateY(-1px);
      box-shadow: 0 0 32px rgba(29,158,117,0.45);
    }
    .btn-submit:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    .spinner {
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 28px 0 20px;
      color: var(--muted);
      font-size: 0.75rem;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* Role cards */
    .role-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .role-card {
      background: var(--dark3);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: border-color 0.2s;
    }
    .role-card:hover { border-color: rgba(29,158,117,0.25); }
    .role-card-icon {
      width: 32px; height: 32px;
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.95rem;
      flex-shrink: 0;
    }
    .role-card-icon.admin { background: rgba(29,158,117,0.12); color: var(--green); }
    .role-card-icon.runner { background: rgba(59,130,246,0.12); color: #60A5FA; }
    .role-card-text span {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: #fff;
    }
    .role-card-text small { font-size: 0.7rem; color: var(--muted); }

    /* Bottom links */
    .bottom-links {
      margin-top: 28px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
    }
    .link-customer {
      font-size: 0.82rem;
      color: var(--muted);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: color 0.2s;
    }
    .link-customer span { color: var(--green); font-weight: 500; }
    .link-customer:hover { color: #fff; }
    .link-back {
      font-size: 0.8rem;
      color: #374151;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
      transition: color 0.2s;
    }
    .link-back:hover { color: var(--muted); }

    /* Responsive */
    @media (max-width: 768px) {
      body { grid-template-columns: 1fr; }
      .left-panel { display: none; }
      .right-panel { padding: 40px 24px; }
    }
  </style>
</head>
<body>

<button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Tukar tema">
  <i class="ti ti-moon" id="themeIcon"></i>
</button>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="left-panel-grid"></div>

  <div class="left-brand">
    <a href="/tracky/" class="brand-logo">
      <img src="/tracky/assets/img/icon.png" alt="Tracky" class="logo-icon">
      Tracky
    </a>
  </div>

  <div class="left-hero">
    <h2>Selamat Kembali<br>ke <span>Platform Anda.</span></h2>
    <p>Log masuk untuk mengurus pesanan, assign runner, dan pantau penghantaran secara real-time.</p>
    <div class="feature-list">
      <div class="feature-item">
        <div class="feature-dot"><i class="ti ti-layout-dashboard"></i></div>
        Dashboard admin dengan analytics lengkap
      </div>
      <div class="feature-item">
        <div class="feature-dot"><i class="ti ti-route"></i></div>
        Assign runner dengan satu klik
      </div>
      <div class="feature-item">
        <div class="feature-dot"><i class="ti ti-map-pin"></i></div>
        Track semua pesanan secara real-time
      </div>
      <div class="feature-item">
        <div class="feature-dot"><i class="ti ti-bell"></i></div>
        Notifikasi pesanan baru secara segera
      </div>
    </div>
  </div>

  <div class="left-footer">
    &copy; 2026 Tracky. Platform Penghantaran Makanan, Melaka.
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="login-box">

    <div class="login-header">
      <h1>Log Masuk</h1>
      <p>Masukkan kelayakan anda untuk meneruskan</p>
    </div>

    <?php if ($error): ?>
    <div class="error-alert">
      <i class="ti ti-alert-circle"></i>
      <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <div class="field-group">
        <label class="field-label" for="emailInput">Alamat E-mel</label>
        <div class="field-wrap">
          <input
            type="email"
            id="emailInput"
            name="email"
            class="form-input"
            placeholder="contoh@email.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            autocomplete="email"
            autofocus>
          <i class="ti ti-mail field-icon"></i>
          <div class="invalid-msg"><i class="ti ti-alert-circle"></i> Sila masukkan e-mel yang sah.</div>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label" for="passwordInput">Kata Laluan</label>
        <div class="field-wrap">
          <input
            type="password"
            id="passwordInput"
            name="password"
            class="form-input"
            placeholder="••••••••"
            autocomplete="current-password">
          <i class="ti ti-lock field-icon"></i>
          <button type="button" class="toggle-pw" onclick="togglePassword()" tabindex="-1">
            <i class="ti ti-eye" id="eyeIcon"></i>
          </button>
          <div class="invalid-msg"><i class="ti ti-alert-circle"></i> Kata laluan sekurang-kurangnya 6 aksara.</div>
        </div>
      </div>

      <button type="submit" class="btn-submit" id="loginBtn">
        <div class="spinner" id="spinner"></div>
        <i class="ti ti-login" id="loginIcon"></i>
        <span id="loginText">Log Masuk</span>
      </button>
    </form>

    <div class="bottom-links">
      <a href="/tracky/customer/customer_track.php" class="link-customer">
        <i class="ti ti-package"></i>
        Pelanggan? <span>Semak status pesanan anda →</span>
      </a>
      <a href="/tracky/" class="link-back">
        <i class="ti ti-arrow-left"></i> Kembali ke laman utama
      </a>
    </div>

  </div>
</div>

<script src="/tracky/assets/js/theme.js"></script>
<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const icon = document.getElementById('eyeIcon');
  input.type = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-off';
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const email = document.getElementById('emailInput');
  const password = document.getElementById('passwordInput');
  let valid = true;

  if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
    email.classList.add('is-invalid');
    valid = false;
  } else {
    email.classList.remove('is-invalid');
  }

  if (!password.value || password.value.length < 6) {
    password.classList.add('is-invalid');
    valid = false;
  } else {
    password.classList.remove('is-invalid');
  }

  if (!valid) { e.preventDefault(); return; }

  const btn = document.getElementById('loginBtn');
  btn.disabled = true;
  document.getElementById('spinner').style.display = 'block';
  document.getElementById('loginIcon').style.display = 'none';
  document.getElementById('loginText').textContent = 'Sedang log masuk...';
});
</script>

</body>
</html>
