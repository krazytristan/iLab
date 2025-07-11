<?php
require_once '../adminpage/db.php';

if (isset($_GET['lab_id'])) {
  $lab_id = intval($_GET['lab_id']);
  $stmt = $conn->prepare("
    SELECT id, pc_name FROM pcs
    WHERE lab_id = ? AND status = 'available'
    AND id NOT IN (SELECT pc_id FROM maintenance_requests WHERE status = 'pending')
    AND id NOT IN (SELECT pc_id FROM pc_reservations WHERE status = 'reserved')
  ");
  $stmt->bind_param("i", $lab_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  $pcs = [];
  while ($row = $result->fetch_assoc()) {
    $pcs[] = $row;
  }

  echo json_encode($pcs);
}
?>
