<?php
session_start();
require_once __DIR__ . '/data/config.php';
require_once __DIR__ . '/data/db.php';  // this gives us $con (mysqli)

// Always return JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Must be logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'You must be logged in.']);
        exit;
    }

    $user_id = intval($_SESSION['user_id']);

    // Step 1: Fetch stored password hash
    $stmt = mysqli_prepare($con, "SELECT password FROM users WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $stored_hash);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$stored_hash) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // Step 2: Verify current password
    if (!password_verify($current_password, $stored_hash)) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    // Step 3: Confirm new passwords match
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }

    // Step 4: Hash new password
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    // Step 5: Update password in DB
    $stmt = mysqli_prepare($con, "UPDATE users SET password = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'DB prepare failed (update).']);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "si", $hashed, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating password.']);
    }
}
?>
