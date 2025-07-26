<?php
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['student_id'];
$pc_id = $_POST['reserve_pc_id'] ?? null;
$reservation_date = $_POST['reservation_date'] ?? null;
$time_start = $_POST['reservation_time_start'] ?? null;
$time_end = $_POST['reservation_time_end'] ?? null;

if (!$pc_id || !$reservation_date || !$time_start || !$time_end) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete reservation details.']);
    exit;
}

// Conflict check
$stmt = $conn->prepare("SELECT id FROM pc_reservations WHERE pc_id = ? AND reservation_date = ? AND (
  (time_start < ? AND time_end > ?) OR
  (time_start < ? AND time_end > ?) OR
  (time_start >= ? AND time_end <= ?)
) AND status IN ('pending', 'approved', 'reserved')");
$stmt->bind_param("isssssss", $pc_id, $reservation_date, $time_end, $time_end, $time_start, $time_start, $time_start, $time_end);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'conflict', 'message' => 'PC already reserved for that time.']);
    exit;
}
$stmt->close();

// Insert reservation
$stmt = $conn->prepare("INSERT INTO pc_reservations (student_id, pc_id, reservation_date, time_start, time_end) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iisss", $student_id, $pc_id, $reservation_date, $time_start, $time_end);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'PC successfully reserved.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to reserve.']);
}
