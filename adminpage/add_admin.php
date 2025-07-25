<?php
require_once '../includes/db.php';
session_start();

// Only allow super admin
if ($_SESSION['admin_username'] !== 'admin') {
  die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['new_username']);
  $email = trim($_POST['new_email']);
  $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

  // Check for duplicate
  $check = $conn->prepare("SELECT 1 FROM admin_users WHERE username = ? OR email = ?");
  $check->bind_param("ss", $username, $email);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $_SESSION['flash_message'] = "⚠️ Username or email already exists.";
  } else {
    $stmt = $conn->prepare("INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $password);
    if ($stmt->execute()) {
      $_SESSION['flash_message'] = "✅ Admin added successfully.";
    } else {
      $_SESSION['flash_message'] = "❌ Failed to add admin.";
    }
    $stmt->close();
  }

  $check->close();
}

header("Location: admindashboard.php");
exit;
?>
