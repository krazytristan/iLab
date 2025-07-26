<?php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$student_id = (int)$_SESSION['student_id'];

$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE recipient_type = 'student' AND recipient_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();

echo json_encode(['success' => true]);
