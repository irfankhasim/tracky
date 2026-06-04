<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'superadmin') {
        session_destroy();
        header('Location: /tracky/login.php');
        exit();
    }
    switch ($_SESSION['role']) {
        case 'admin':
        case 'staff':
            header('Location: /tracky/admin/dashboard.php');
            break;
        case 'runner':
            header('Location: /tracky/runner/my_orders.php');
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
        $stmt = mysqli_prepare($conn, 'SELECT id, name, email, password, role, is_active, phone FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'E-mel tidak dijumpai dalam sistem.';
        } elseif ($user['role'] === 'superadmin') {
            $error = 'Akaun ini tidak dibenarkan log masuk di sini.';
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
                case 'admin':
                case 'staff':
                    header('Location: /tracky/admin/dashboard.php');
                    break;
                case 'runner':
                    header('Location: /tracky/runner/my_orders.php');
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
  <title>Log Masuk — Tracky</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: #f0faf6;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .login-wrapper {
      width: 100%;
      max-width: 420px;
    }

    .login-card {
      background: white;
      border-radius: 20px;
      padding: 40px 36px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
      border: 1px solid #e5e7eb;
    }

    .login-brand {
      text-align: center;
      margin-bottom: 32px;
    }

    .brand-icon {
      width: 64px; height: 64px;
      background: #1D9E75;
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 12px;
      font-size: 32px;
      color: white;
    }

    .brand-name {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
    }

    .brand-tagline {
      font-size: 13px;
      color: #6b7280;
      margin-top: 4px;
    }

    .form-label {
      font-size: 13px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 6px;
    }

    .form-control {
      border-radius: 10px;
      border: 1.5px solid #e5e7eb;
      padding: 10px 14px;
      font-size: 14px;
      transition: border-color 0.2s;
    }

    .form-control:focus {
      border-color: #1D9E75;
      box-shadow: 0 0 0 3px rgba(29,158,117,0.1);
    }

    .btn-login {
      background: #1D9E75;
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px;
      font-size: 15px;
      font-weight: 600;
      width: 100%;
      cursor: pointer;
      transition: background 0.2s;
      margin-top: 8px;
    }

    .btn-login:hover { background: #0F6E56; }

    .error-alert {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 12px 16px;
      color: #dc2626;
      font-size: 13px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .role-info {
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid #f3f4f6;
    }

    .role-info-title {
      font-size: 11px;
      color: #9ca3af;
      text-align: center;
      margin-bottom: 12px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }

    .role-badges {
      display: flex;
      justify-content: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .role-badge {
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
    }

    .role-badge.admin { background: #E1F5EE; color: #0F6E56; }
    .role-badge.runner { background: #E6F1FB; color: #185FA5; }

    .track-link {
      display: block;
      text-align: center;
      margin-top: 16px;
      font-size: 13px;
      color: #6b7280;
    }

    .track-link a {
      color: #1D9E75;
      text-decoration: none;
      font-weight: 500;
    }

    .track-link a:hover { text-decoration: underline; }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 10px;
      font-size: 13px;
      color: #9ca3af;
      text-decoration: none;
    }

    .back-link:hover { color: #1D9E75; }

    .btn-login.loading {
      opacity: 0.7;
      pointer-events: none;
    }

    @media (max-width: 480px) {
      .login-card { padding: 28px 20px; }
    }
  </style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-card">

    <div class="login-brand">
      <div class="brand-icon">
        <i class="ti ti-bike"></i>
      </div>
      <div class="brand-name">Tracky</div>
      <div class="brand-tagline">Sistem Pengurusan Penghantaran</div>
    </div>

    <?php if ($error): ?>
    <div class="error-alert">
      <i class="ti ti-alert-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <div class="mb-3">
        <label class="form-label">Alamat E-mel</label>
        <input type="email" name="email" class="form-control"
               placeholder="email@tracky.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               required autofocus>
        <div class="invalid-feedback">Sila masukkan e-mel yang sah.</div>
      </div>

      <div class="mb-4">
        <label class="form-label">Kata Laluan</label>
        <div class="position-relative">
          <input type="password" name="password" id="passwordInput"
                 class="form-control" placeholder="••••••••" required>
          <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y pe-3"
                  onclick="togglePassword()" style="color:#9ca3af;text-decoration:none">
            <i class="ti ti-eye" id="eyeIcon"></i>
          </button>
        </div>
        <div class="invalid-feedback">Sila masukkan kata laluan.</div>
      </div>

      <button type="submit" class="btn-login" id="loginBtn">
        <i class="ti ti-login"></i> Log Masuk
      </button>
    </form>

    <div class="role-info">
      <div class="role-info-title">Log masuk sebagai</div>
      <div class="role-badges">
        <span class="role-badge admin">
          <i class="ti ti-layout-dashboard"></i> Admin
        </span>
        <span class="role-badge runner">
          <i class="ti ti-bike"></i> Runner
        </span>
      </div>
    </div>

    <div class="track-link">
      Pelanggan? <a href="/tracky/track.php">Semak status order anda →</a>
    </div>

    <a href="/tracky/" class="back-link">
      <i class="ti ti-arrow-left"></i> Kembali ke menu
    </a>

  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const icon = document.getElementById('eyeIcon');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'ti ti-eye-off';
  } else {
    input.type = 'password';
    icon.className = 'ti ti-eye';
  }
}

document.getElementById('loginForm').addEventListener('submit', function(e) {
  const email = this.email.value.trim();
  const password = this.password.value;
  let valid = true;

  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    this.email.classList.add('is-invalid');
    valid = false;
  } else {
    this.email.classList.remove('is-invalid');
  }

  if (!password || password.length < 6) {
    this.password.classList.add('is-invalid');
    valid = false;
  } else {
    this.password.classList.remove('is-invalid');
  }

  if (!valid) {
    e.preventDefault();
    return;
  }

  const btn = document.getElementById('loginBtn');
  btn.classList.add('loading');
  btn.innerHTML = '<i class="ti ti-loader-2"></i> Sedang log masuk...';
});
</script>

</body>
</html>
