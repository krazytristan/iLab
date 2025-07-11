<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clean inputs
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "All fields are required.";
        header("Location: adminlogin.php");
        exit();
    }

    // Fetch user from DB
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user found
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session and redirect
            $_SESSION['admin'] = $user['username'];
            $_SESSION['login_success'] = "Welcome, {$user['username']}!";
            header("Location: admindashboard.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Incorrect password.";
        }
    } else {
        $_SESSION['login_error'] = "Username not found.";
    }

    header("Location: adminlogin.php");
    exit();
}
?>
