<?php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id']) || !isset($_POST['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$notif_id = (int)$_POST['id'];
$student_id = (int)$_SESSION['student_id'];

$stmt = $conn->prepare("
    UPDATE notifications 
    SET is_read = 1 
    WHERE id = ? AND recipient_type = 'student' AND recipient_id = ?
");
$stmt->bind_param("ii", $notif_id, $student_id);
$stmt->execute();

echo json_encode(['success' => true]);
