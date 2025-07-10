<?php
$host = "localhost";       // or use "127.0.0.1"
$db   = "ilab_system";     // your database name
$user = "root";            // default for XAMPP
$pass = "";                // default is empty in XAMPP

// Enable error reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create a new MySQLi connection
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4"); // Set character encoding
} catch (Exception $e) {
    // Handle connection errors gracefully
    die("Database connection failed: " . $e->getMessage());
}
?>
