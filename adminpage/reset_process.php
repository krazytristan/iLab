<?php
session_start();
require '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token    = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    // Step 1: Validate token
    if (empty($token)) {
        $_SESSION['reset_error'] = "Invalid reset request. Token is missing.";
        header("Location: adminlogin.php");
        exit();
    }

    // Step 2: Validate password input
    if (empty($password) || empty($confirm)) {
        $_SESSION['reset_error'] = "Both password fields are required.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }

    if ($password !== $confirm) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit();
    }

    // Step 3: Verify token (from `admin_password_resets` if applicable)
    $stmt = $conn->prepare("
        SELECT au.id AS user_id
        FROM admin_users au
        JOIN admin_password_resets apr ON apr.user_id = au.id
        WHERE apr.token = ? AND apr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['reset_error'] = "Reset link is invalid or has expired.";
        header("Location: adminlogin.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $userId = $user['user_id'];
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    // Step 4: Update password
    $update = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed, $userId);
    $update->execute();

    // Step 5: Delete the used reset token
    $delete = $conn->prepare("DELETE FROM admin_password_resets WHERE token = ?");
    $delete->bind_param("s", $token);
    $delete->execute();

    $_SESSION['reset_success'] = "✅ Password reset successfully. You may now log in.";
    header("Location: adminlogin.php");
    exit();
}
?>
