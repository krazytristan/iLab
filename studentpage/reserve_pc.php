<?php
session_start();
require_once '../adminpage/db.php';

if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

$student_id = $_SESSION['student_id'];
$pc_id = $_POST['pc_id'];
$reservation_time = $_POST['reservation_time'];

// Optional: Check if the PC is still available
$check = $conn->prepare("
  SELECT id FROM pc_reservations 
  WHERE pc_id = ? AND status = 'reserved'
");
$check->bind_param("i", $pc_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
  echo "PC already reserved.";
  exit;
}

// Insert the reservation
$stmt = $conn->prepare("INSERT INTO pc_reservations (student_id, pc_id, reservation_time, status) VALUES (?, ?, ?, 'reserved')");
$stmt->bind_param("iis", $student_id, $pc_id, $reservation_time);
if ($stmt->execute()) {
  header("Location: student_dashboard.php?msg=Reservation Successful");
} else {
  echo "Error: " . $stmt->error;
}
?>
