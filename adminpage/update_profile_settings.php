<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? 'admin';

$email = trim($_POST['email'] ?? '');
$new_pass = trim($_POST['new_password'] ?? '');

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_message'] = "❌ Invalid email address!";
    header("Location: admindashboard.php#profilesettings");
    exit();
}

// Restrict super admin from editing their own details (optional)
if ($admin_role === 'super_admin') {
    $_SESSION['flash_message'] = "⚠️ Super Admin details are locked.";
    header("Location: admindashboard.php#profilesettings");
    exit();
}

// Update email and password if given
if (!empty($new_pass)) {
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin_users SET email = ?, password = ? WHERE id = ?");
    $stmt->bind_param("ssi", $email, $hashed, $admin_id);
} else {
    $stmt = $conn->prepare("UPDATE admin_users SET email = ? WHERE id = ?");
    $stmt->bind_param("si", $email, $admin_id);
}

if ($stmt->execute()) {
    $_SESSION['flash_message'] = "✅ Profile updated successfully!";
} else {
    $_SESSION['flash_message'] = "❌ Update failed. Please try again.";
}

$stmt->close();
header("Location: admindashboard.php#profilesettings");
exit();
