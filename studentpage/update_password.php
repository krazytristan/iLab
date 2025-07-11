<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

require_once '../adminpage/db.php';
$student_id = $_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    // Get the current hashed password from the database
    $stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if (!$student) {
        // Student not found
        $_SESSION['password_error'] = "Account not found.";
        header("Location: student_dashboard.php");
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $student['password'])) {
        $_SESSION['password_error'] = "Incorrect current password.";
        header("Location: student_dashboard.php");
        exit();
    }

    // Hash and update new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $student_id);
    $update_stmt->execute();

    $_SESSION['password_success'] = "Password updated successfully.";
    header("Location: student_dashboard.php");
    exit();
}
?>
