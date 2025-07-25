<?php
session_start();
require_once '../adminpage/db.php';

if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

$student_id = $_SESSION['student_id'];

// Retrieve and sanitize input
$pc_id = isset($_POST['pc_id']) ? intval($_POST['pc_id']) : 0;
$reservation_date = trim($_POST['reservation_date'] ?? '');
$time_start = trim($_POST['time_start'] ?? '');
$time_end = trim($_POST['time_end'] ?? '');

// Validate inputs
if ($pc_id <= 0 || empty($reservation_date) || empty($time_start) || empty($time_end)) {
  echo "Missing reservation details.";
  exit;
}

// Ensure start time is before end time
if (strtotime($time_start) >= strtotime($time_end)) {
  echo "Start time must be before end time.";
  exit;
}

// Check if PC is under maintenance
$maintenance_check = $conn->prepare("
  SELECT id FROM maintenance_requests 
  WHERE pc_id = ? AND status = 'pending'
");
$maintenance_check->bind_param("i", $pc_id);
$maintenance_check->execute();
$maintenance_result = $maintenance_check->get_result();
if ($maintenance_result->num_rows > 0) {
  echo "This PC is currently under maintenance.";
  exit;
}

// Check for time conflict with existing reservations
$conflict_query = "
  SELECT id FROM pc_reservations
  WHERE pc_id = ?
    AND reservation_date = ?
    AND status IN ('reserved', 'approved')
    AND (
      (? BETWEEN time_start AND time_end)
      OR (? BETWEEN time_start AND time_end)
      OR (time_start BETWEEN ? AND ?)
    )
";
$conflict_stmt = $conn->prepare($conflict_query);
$conflict_stmt->bind_param("isssss", $pc_id, $reservation_date, $time_start, $time_end, $time_start, $time_end);
$conflict_stmt->execute();
$conflict_result = $conflict_stmt->get_result();
if ($conflict_result->num_rows > 0) {
  echo "This PC is already reserved during the selected time.";
  exit;
}

// Insert reservation into database
$insert = $conn->prepare("
  INSERT INTO pc_reservations (
    student_id, pc_id, reservation_date, time_start, time_end, status
  ) VALUES (?, ?, ?, ?, ?, 'reserved')
");
$insert->bind_param("iisss", $student_id, $pc_id, $reservation_date, $time_start, $time_end);

if ($insert->execute()) {
  // Optional: notify admin
  $notif = $conn->prepare("
    INSERT INTO notifications (recipient_type, recipient_id, message)
    VALUES ('admin', 1, CONCAT('New reservation by Student ID: ', ?))
  ");
  $notif->bind_param("i", $student_id);
  $notif->execute();

  header("Location: student_dashboard.php?msg=Reservation Successful");
  exit;
} else {
  echo "Reservation failed: " . $insert->error;
}
?>
