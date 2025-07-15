<?php
session_start();
require_once '../includes/db.php';
if (!isset($_SESSION['admin'])) {
    header("Location: adminlogin.php");
    exit();
}

$username = $_SESSION['admin'];
$email = $_POST['email'];
$new_pass = $_POST['new_password'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_message'] = "Invalid email address!";
    header("Location: admindashboard.php#profilesettings");
    exit();
}

// Update email and password if given
if (!empty($new_pass)) {
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin_users SET email = ?, password = ? WHERE username = ?");
    $stmt->bind_param("sss", $email, $hashed, $username);
} else {
    $stmt = $conn->prepare("UPDATE admin_users SET email = ? WHERE username = ?");
    $stmt->bind_param("ss", $email, $username);
}

$stmt->execute();
$_SESSION['flash_message'] = "Profile updated successfully!";
header("Location: admindashboard.php#profilesettings");
exit();
?>
