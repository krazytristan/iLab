<?php
session_start(); // Start session

// ✅ Store user role before destroying session
$isStudent = isset($_SESSION['student_id']);
$isAdmin = isset($_SESSION['admin_username']);

// ✅ Clear session
session_unset();   // Unset all session variables
session_destroy(); // Destroy the session

// ✅ Redirect based on previous session type
if ($isStudent) {
    header("Location: ../studentpage/student_login.php");
    exit();
} elseif ($isAdmin) {
    header("Location: ../adminpage/adminlogin.php");
    exit();
} else {
    // Default redirect (e.g., session expired or direct access)
    header("Location: ../index.php");
    exit();
}
