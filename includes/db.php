<?php
// Database Configuration
$host = "localhost";
$db   = "ilab_system";
$user = "root";
$pass = "";

// Toggle Debug Mode (Set to false in production)
$debug_mode = true;

// Enable MySQLi strict reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Establish the connection
    $conn = new mysqli($host, $user, $pass, $db);

    // Set character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    if ($debug_mode) {
        // Development: Show full error message
        die("❌ Database connection failed: " . $e->getMessage());
    } else {
        // Production: Log error and show generic message
        error_log("Database connection error: " . $e->getMessage());
        die("We’re experiencing technical issues. Please try again later.");
    }
}
?>
