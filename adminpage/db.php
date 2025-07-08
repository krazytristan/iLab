<?php
$host = "localhost";
$db   = "ilab_system";
$user = "root";
$pass = "";

// Enable strict error reporting for mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create the MySQLi connection
    $conn = new mysqli($host, $user, $pass, $db);
    
    // Set character encoding to UTF-8
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Show detailed error in dev only (comment in production)
    die("Database connection failed: " . $e->getMessage());
}
?>
