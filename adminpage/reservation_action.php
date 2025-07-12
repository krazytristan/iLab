<?php
session_start();

if (!isset($_SESSION['admin'])) {
  echo json_encode(['success' => false, 'msg' => 'Unauthorized access.']);
  exit();
}

require_once 'db.php';

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$id || !$action) {
  echo json_encode(['success' => false, 'msg' => 'Missing required parameters.']);
  exit();
}

try {
  if ($action === 'approve') {
    $stmt = $conn->prepare("UPDATE pc_reservations SET status = 'approved' WHERE id = ?");
  } elseif ($action === 'cancel') {
    $stmt = $conn->prepare("UPDATE pc_reservations SET status = 'cancelled' WHERE id = ?");
  } else {
    echo json_encode(['success' => false, 'msg' => 'Invalid action.']);
    exit();
  }

  $stmt->bind_param("i", $id);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'msg' => ucfirst($action) . ' successful.']);
  } else {
    echo json_encode(['success' => false, 'msg' => 'No rows affected. Possibly already processed.']);
  }

  $stmt->close();
  $conn->close();

} catch (Exception $e) {
  echo json_encode(['success' => false, 'msg' => 'Server error: ' . $e->getMessage()]);
}
?>
