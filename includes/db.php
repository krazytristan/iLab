<?php
// ==========================
// Database Configuration
// ==========================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ilab_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', 3306);          // Default MySQL port
define('DEBUG_MODE', true);       // Set to false in production

// ==========================
// Enable strict error reporting for mysqli
// ==========================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Create a new MySQLi instance
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // Set character encoding
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    if (DEBUG_MODE) {
        // Development: Show error on screen
        die("❌ Database connection failed: " . $e->getMessage());
    } else {
        // Production: Log error and show user-friendly message
        error_log("Database connection error: " . $e->getMessage());
        die("⚠️ System error. Please try again later.");
    }
}
?>
