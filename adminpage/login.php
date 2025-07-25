<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $_SESSION['login_error'] = "⚠️ All fields are required.";
        header("Location: ../adminpage/adminlogin.php");
        exit();
    }

    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, username, password, role FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Successful login: set session data
                session_regenerate_id(true); // prevent session fixation
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['login_success'] = "✅ Welcome, {$user['username']}!";

                header("Location: ../adminpage/admindashboard.php");
                exit();
            } else {
                $_SESSION['login_error'] = "❌ Incorrect password.";
            }
        } else {
            $_SESSION['login_error'] = "❌ Username not found.";
        }
    } catch (Exception $e) {
        error_log("Login Exception: " . $e->getMessage());
        $_SESSION['login_error'] = "⚠️ System error. Please try again later.";
    }

    // Redirect back to login on error
    header("Location: adminlogin.php");
    exit();
}
?>
