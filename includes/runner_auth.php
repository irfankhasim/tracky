<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$__role = $_SESSION['role'] ?? '';

if (!isset($_SESSION['user_id']) || !in_array($__role, ['runner', 'superadmin'], true)) {
    header('Location: /tracky/login.php');
    exit();
}

// Superadmin (system owner) may view the runner interface by impersonating a
// runner context. Pick the runner from ?runner=ID, the current session, or the
// first available runner.
if ($__role === 'superadmin') {
    require_once __DIR__ . '/db.php';

    $__wanted = isset($_GET['runner']) ? (int) $_GET['runner'] : (int) ($_SESSION['runner_id'] ?? 0);

    $__rid = 0;
    if ($__wanted > 0) {
        $__chk = mysqli_prepare($conn, 'SELECT id, status FROM runners WHERE id = ? LIMIT 1');
        mysqli_stmt_bind_param($__chk, 'i', $__wanted);
        mysqli_stmt_execute($__chk);
        $__row = mysqli_fetch_assoc(mysqli_stmt_get_result($__chk));
        mysqli_stmt_close($__chk);
        if ($__row) {
            $__rid = (int) $__row['id'];
            $_SESSION['runner_status'] = $__row['status'];
        }
    }

    if ($__rid === 0) {
        $__first = mysqli_query($conn, 'SELECT id, status FROM runners ORDER BY id ASC LIMIT 1');
        $__row = $__first ? mysqli_fetch_assoc($__first) : null;
        if ($__row) {
            $__rid = (int) $__row['id'];
            $_SESSION['runner_status'] = $__row['status'];
        }
    }

    if ($__rid === 0) {
        // No runners exist to view.
        header('Location: /tracky/superadmin/superadmin_users.php?tab=runners');
        exit();
    }

    $_SESSION['runner_id'] = $__rid;
}
