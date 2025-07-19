<?php
session_start(); // Start the session

// Store user type before destroying the session
$isAdmin = isset($_SESSION['admin']);
$isStudent = isset($_SESSION['student_id']);

session_unset();   // Remove all session variables
session_destroy(); // Destroy the session

// Redirect to correct login page based on user type
if ($isAdmin) {
    header("Location: ../adminpage/adminlogin.php");
} elseif ($isStudent) {
    header("Location: ../studentpage/student_login.php");
} else {
    // Default fallback (in case session expired or neither was set)
    header("Location: ../studentpage/student_login.php");
}
exit();
