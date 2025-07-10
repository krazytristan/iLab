<?php
session_start();          // Start the session
session_unset();          // Remove all session variables
session_destroy();        // Destroy the session

// Redirect to login page
header("Location: adminlogin.php");
exit();
