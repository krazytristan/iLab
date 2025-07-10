<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if (empty($token) || empty($password) || empty($confirm)) {
    $_SESSION['reset_error'] = "All fields are required.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
  }

  if ($password !== $confirm) {
    $_SESSION['reset_error'] = "Passwords do not match.";
    header("Location: reset_password.php?token=" . urlencode($token));
    exit();
  }

  // Re-check token validity
  $stmt = $conn->prepare("SELECT * FROM admin_users WHERE reset_token = ? AND reset_expires > NOW()");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $_SESSION['reset_error'] = "Reset token is invalid or expired.";
    header("Location: adminlogin.php");
    exit();
  }

  // Hash and save new password
  $user = $result->fetch_assoc();
  $hashed = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $conn->prepare("UPDATE admin_users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
  $stmt->bind_param("si", $hashed, $user['id']);
  $stmt->execute();

  $_SESSION['login_success'] = "Your password has been reset. You may now log in.";
  header("Location: adminlogin.php");
}
