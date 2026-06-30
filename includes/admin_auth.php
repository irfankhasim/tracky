<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin', 'staff'], true)) {
    header('Location: /tracky/login.php');
    exit();
}

require_once __DIR__ . '/db.php';

// ── Multi-tenant context ──
if (($_SESSION['role'] ?? '') === 'superadmin') {
    // Superadmin manages one restaurant at a time. Allow switching via
    // ?manage_restaurant=ID; default to the first restaurant when none chosen.
    if (isset($_GET['manage_restaurant'])) {
        $wanted = (int) $_GET['manage_restaurant'];
        $chk = mysqli_query($conn, 'SELECT id FROM restaurants WHERE id = ' . $wanted . ' LIMIT 1');
        if ($chk && mysqli_num_rows($chk) > 0) {
            $_SESSION['sa_acting_restaurant'] = $wanted;
        }
    }
    if (empty($_SESSION['sa_acting_restaurant'])) {
        $first = mysqli_query($conn, 'SELECT id FROM restaurants ORDER BY id ASC LIMIT 1');
        $row = $first ? mysqli_fetch_assoc($first) : null;
        if ($row) {
            $_SESSION['sa_acting_restaurant'] = (int) $row['id'];
        }
    }
} else {
    // Admin must belong to a restaurant.
    if (empty($_SESSION['restaurant_id'])) {
        session_destroy();
        header('Location: /tracky/login.php');
        exit();
    }
}
