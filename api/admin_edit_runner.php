<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

$runner_id = (int) ($_POST['runner_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$vehicle = trim($_POST['vehicle'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($runner_id < 1 || $name === '' || $phone === '' || $vehicle === '') {
    echo json_encode(['success' => false, 'message' => 'Sila isi semua maklumat']);
    exit;
}

if ($password !== '' && strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Kata laluan mesti sekurang-kurangnya 6 aksara']);
    exit;
}

$rid = activeRestaurantId();
$stmt = mysqli_prepare($conn, 'SELECT user_id FROM runners WHERE id = ? AND restaurant_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $runner_id, $rid);
mysqli_stmt_execute($stmt);
$runner = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$runner) {
    echo json_encode(['success' => false, 'message' => 'Runner tidak dijumpai']);
    exit;
}

$user_id = (int) $runner['user_id'];

mysqli_begin_transaction($conn);

$ustmt = mysqli_prepare($conn, 'UPDATE users SET name = ?, phone = ? WHERE id = ?');
mysqli_stmt_bind_param($ustmt, 'ssi', $name, $phone, $user_id);
$ok1 = mysqli_stmt_execute($ustmt);
mysqli_stmt_close($ustmt);

if (!$ok1) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal kemaskini pengguna']);
    exit;
}

if ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pstmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
    mysqli_stmt_bind_param($pstmt, 'si', $hash, $user_id);
    $okPwd = mysqli_stmt_execute($pstmt);
    mysqli_stmt_close($pstmt);
    if (!$okPwd) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal kemaskini kata laluan']);
        exit;
    }
}

$rstmt = mysqli_prepare($conn, 'UPDATE runners SET vehicle_no = ?, phone = ? WHERE id = ?');
mysqli_stmt_bind_param($rstmt, 'ssi', $vehicle, $phone, $runner_id);
$ok2 = mysqli_stmt_execute($rstmt);
mysqli_stmt_close($rstmt);

if (!$ok2) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Gagal kemaskini runner']);
    exit;
}

mysqli_commit($conn);
echo json_encode(['success' => true, 'message' => 'Runner berjaya dikemaskini']);
