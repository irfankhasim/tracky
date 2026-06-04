<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRunnerApi();
header('Content-Type: application/json; charset=utf-8');

$new_status = trim($_POST['status'] ?? '');

if (!in_array($new_status, ['online', 'offline'], true)) {
    echo json_encode(['success' => false, 'message' => 'Status tidak sah']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, 'SELECT r.id, r.status FROM runners r WHERE r.user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$runner = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$runner) {
    echo json_encode(['success' => false, 'message' => 'Runner tidak dijumpai']);
    exit;
}

if ($runner['status'] === 'busy') {
    echo json_encode([
        'success' => false,
        'message' => 'Tidak boleh tukar status semasa menghantar order',
    ]);
    exit;
}

$runner_id = (int) $runner['id'];
$ustmt = mysqli_prepare($conn, 'UPDATE runners SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($ustmt, 'si', $new_status, $runner_id);
mysqli_stmt_execute($ustmt);
mysqli_stmt_close($ustmt);

$name = $_SESSION['name'] ?? 'Runner';
$msg = "$name kini $new_status";
addNotification($conn, 'Runner Status Update', $msg, 'system');

$_SESSION['runner_status'] = $new_status;
if (!isset($_SESSION['runner_id'])) {
    $_SESSION['runner_id'] = $runner_id;
}

echo json_encode([
    'success' => true,
    'status' => $new_status,
    'message' => 'Status berjaya dikemaskini',
]);
