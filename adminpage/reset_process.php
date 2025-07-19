<?php
session_start();
require '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $token = $_POST['token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if (!$token) {
    $_SESSION['reset_error'] = "Invalid reset request.";
    header("Location: adminlogin.php");
    exit();
  }

  if ($password !== $confirm) {
    $_SESSION['reset_error'] = "Passwords do not match.";
    header("Location: reset_password.php?token=$token");
    exit();
  }

  // Check token validity
  $stmt = $conn->prepare("SELECT id FROM admin_users WHERE reset_token = ? AND reset_expires > NOW()");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $_SESSION['reset_error'] = "Reset link is invalid or has expired.";
    header("Location: adminlogin.php");
    exit();
  }

  $user = $result->fetch_assoc();
  $hashed = password_hash($password, PASSWORD_DEFAULT);

  // Update password and clear reset token
  $update = $conn->prepare("UPDATE admin_users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
  $update->bind_param("si", $hashed, $user['id']);
  $update->execute();

  $_SESSION['reset_success'] = "Password updated successfully. You can now log in.";
  header("Location: adminlogin.php");
  exit();
}
?>
