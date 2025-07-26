<?php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['notifications' => [], 'unread_count' => 0]);
    exit();
}

$student_id = (int)$_SESSION['student_id'];

$stmt = $conn->prepare("
    SELECT id, message, is_read, created_at
    FROM notifications
    WHERE recipient_type = 'student' AND recipient_id = ?
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
$unread = 0;
while ($row = $res->fetch_assoc()) {
    if ((int)$row['is_read'] === 0) $unread++;
    $row['display_date'] = date('M d, h:i A', strtotime($row['created_at']));
    $notifications[] = $row;
}

echo json_encode([
  'notifications' => $notifications,
  'unread_count'  => $unread
]);
