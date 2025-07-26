<?php
require_once '../includes/db.php'; // Adjust path if necessary
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitize input
    $fullname = trim($_POST['fullname'] ?? '');
    $usn = trim($_POST['usn'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $year_level = $_POST['year_level'] ?? '';
    $strand = $_POST['strand'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (
        empty($fullname) || empty($usn) || empty($birthday) ||
        empty($year_level) || empty($strand) || empty($email) ||
        empty($contact) || empty($password) || empty($confirm_password)
    ) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: student_register.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: student_register.php");
        exit();
    }

    // Validate password match
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: student_register.php");
        exit();
    }

    // Validate password length
    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
        header("Location: student_register.php");
        exit();
    }

    // Check for existing email or USN
    $checkStmt = $conn->prepare("SELECT id FROM students WHERE email = ? OR usn_or_lrn = ?");
    if (!$checkStmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: student_register.php");
        exit();
    }
    $checkStmt->bind_param("ss", $email, $usn);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $_SESSION['error'] = "Email or USN/LRN is already registered.";
        $checkStmt->close();
        header("Location: student_register.php");
        exit();
    }
    $checkStmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO students (fullname, usn_or_lrn, birthday, year_level, strand, email, contact, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: student_register.php");
        exit();
    }

    $stmt->bind_param("ssssssss", $fullname, $usn, $birthday, $year_level, $strand, $email, $contact, $hashed_password);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Registration successful! You may now log in.";
        $stmt->close();
        $conn->close();
        header("Location: student_login.php");
        exit();
    } else {
        $_SESSION['error'] = "Registration failed. Please try again later.";
        $stmt->close();
        $conn->close();
        header("Location: student_register.php");
        exit();
    }
} else {
    header("Location: student_register.php");
    exit();
}
