<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $lab_name = trim($_POST['lab_name']);
  $default_session_length = trim($_POST['default_session_length']);
  $max_reservations = (int) $_POST['max_reservations'];

  $stmt = $conn->prepare("UPDATE lab_settings SET lab_name = ?, default_session_length = ?, max_reservations = ? WHERE id = 1");
  $stmt->bind_param("ssi", $lab_name, $default_session_length, $max_reservations);
  $stmt->execute();

  $_SESSION['success_msg'] = "Lab settings updated.";
  header("Location: admindashboard.php#settings");
  exit();
}
?>
