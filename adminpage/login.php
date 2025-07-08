<?php
session_start();
require_once 'db.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Prepare and execute query
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // ✅ Use password_verify if password is hashed
            if (password_verify($password, $user['password'])) {
                // Success: Set session and redirect
                $_SESSION['admin'] = $user['username'];
                header("Location: admindashboard.php");
                exit();
            } else {
                $_SESSION['login_error'] = "Invalid password.";
            }
        } else {
            $_SESSION['login_error'] = "Username not found.";
        }
    } catch (Exception $e) {
        $_SESSION['login_error'] = "Login error: " . $e->getMessage();
    }

    // Redirect on error
    header("Location: admin.php");
    exit();

} else {
    // Block direct GET access
    header("Location: admin.php");
    exit();
}
