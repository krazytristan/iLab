<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "All fields are required.";
        header("Location: adminlogin.php");
        exit();
    }

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // If user found
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // If using hashed passwords, use password_verify
        // if (password_verify($password, $user['password'])) {
        if ($password === $user['password']) { // ← plain text password match
            $_SESSION['admin'] = $user['username'];
            $_SESSION['login_success'] = "Welcome, {$user['username']}!";
            header("Location: admindashboard.php"); // ✅ Redirect to dashboard
            exit();
        }
    }

    // Invalid credentials
    $_SESSION['login_error'] = "Invalid username or password.";
    header("Location: adminlogin.php");
    exit();
}
?>
