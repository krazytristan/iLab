<?php
session_start();          // Start the session
session_unset();          // Remove all session variables
session_destroy();        // Destroy the session

// Redirect to correct login page based on user type
if (isset($_SESSION['admin'])) {
    header("Location: adminlogin.php");
} else {
    header("Location: ../studentpage/student_login.php");
}
exit();
