<?php
require_once '../includes/db.php';
session_start();

// Only super admin can delete
if ($_SESSION['admin_username'] !== 'admin') {
  die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])) {
  $adminId = intval($_POST['admin_id']);

  // Prevent deleting super admin
  $check = $conn->prepare("SELECT username FROM admin_users WHERE id = ?");
  $check->bind_param("i", $adminId);
  $check->execute();
  $check->bind_result($username);
  $check->fetch();
  $check->close();

  if ($username === 'admin') {
    $_SESSION['flash_message'] = "⚠️ Cannot delete super admin.";
  } else {
    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    if ($stmt->execute()) {
      $_SESSION['flash_message'] = "🗑️ Admin deleted.";
    } else {
      $_SESSION['flash_message'] = "❌ Failed to delete admin.";
    }
    $stmt->close();
  }
}

header("Location: admindashboard.php");
exit;
?>
