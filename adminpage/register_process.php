<?php
session_start();
require_once '../includes/db.php'; // database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate fields
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['register_error'] = "All fields are required.";
        header('Location: admin_register.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format.";
        header('Location: admin_register.php');
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match.";
        header('Location: admin_register.php');
        exit();
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['register_error'] = "Username or email already taken.";
        $stmt->close();
        header('Location: admin_register.php');
        exit();
    }
    $stmt->close();

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert the user
    $insert_stmt = $conn->prepare("INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)");
    $insert_stmt->bind_param("sss", $username, $email, $hashed_password);

    if ($insert_stmt->execute()) {
        $_SESSION['login_success'] = "Registration successful! You can now log in.";
        header('Location: adminlogin.php');
        exit();
    } else {
        $_SESSION['register_error'] = "Registration failed. Please try again.";
        header('Location: admin_register.php');
        exit();
    }
} else {
    header('Location: admin_register.php');
    exit();
}
?>
