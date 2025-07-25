<?php
require_once '../includes/db.php';

// ---- Inputs (with sane defaults) ----
$slotDate  = $_GET['date']  ?? date('Y-m-d');
$slotStart = $_GET['start'] ?? date('H:i:s');
$slotEnd   = $_GET['end']   ?? $slotStart; // instant check if not supplied
$includeFuture = isset($_GET['include_future']) && $_GET['include_future'] == '1';

// ---- Build SQL ----
// We always compute effective_status. We optionally compute next reservations if include_future=1.
$sql = "
  SELECT 
    pcs.id,
    pcs.pc_name,
    pcs.status AS base_status,
    labs.lab_name,

    -- Effective status priority:
    CASE
      WHEN EXISTS (
        SELECT 1 FROM maintenance_requests mr 
        WHERE mr.pc_id = pcs.id 
          AND mr.status IN ('pending','in_progress')
      ) THEN 'maintenance'

      WHEN EXISTS (
        SELECT 1 FROM lab_sessions ls
        WHERE ls.pc_id = pcs.id
          AND ls.status = 'active'
      ) THEN 'in_use'

      WHEN EXISTS (
        SELECT 1 FROM pc_reservations r
        WHERE r.pc_id = pcs.id
          AND r.status IN ('approved','reserved')
          AND r.reservation_date = ?
          AND NOT (r.time_end <= ? OR r.time_start >= ?)
      ) THEN 'reserved'

      ELSE pcs.status
    END AS effective_status
";

if ($includeFuture) {
  $sql .= ",
    (
      SELECT CONCAT(r3.reservation_date, ' ', r3.time_start)
      FROM pc_reservations r3
      WHERE r3.pc_id = pcs.id
        AND r3.status IN ('approved','reserved','pending')
        AND (r3.reservation_date > ? OR (r3.reservation_date = ? AND r3.time_start > ?))
      ORDER BY r3.reservation_date, r3.time_start
      LIMIT 1
    ) AS next_res_start,
    (
      SELECT CONCAT(r4.reservation_date, ' ', r4.time_end)
      FROM pc_reservations r4
      WHERE r4.pc_id = pcs.id
        AND r4.status IN ('approved','reserved','pending')
        AND (r4.reservation_date > ? OR (r4.reservation_date = ? AND r4.time_start > ?))
      ORDER BY r4.reservation_date, r4.time_start
      LIMIT 1
    ) AS next_res_end
  ";
}

$sql .= "
  FROM pcs
  INNER JOIN labs ON pcs.lab_id = labs.id
  ORDER BY labs.lab_name ASC, pcs.pc_name ASC
";

// ---- Prepare / bind ----
$stmt = $conn->prepare($sql);

if ($includeFuture) {
  // we have 3 + 6 = 9 params, all strings
  $stmt->bind_param(
    "sssssssss",
    $slotDate, $slotStart, $slotEnd,
    $slotDate, $slotDate, $slotStart,
    $slotDate, $slotDate, $slotStart
  );
} else {
  // only first 3 params
  $stmt->bind_param("sss", $slotDate, $slotStart, $slotEnd);
}

$stmt->execute();
$result = $stmt->get_result();

// ---- Group PCs by lab ----
$grouped = [];
while ($row = $result->fetch_assoc()) {
    $labName = $row['lab_name'] ?: 'Other';

    $pc = [
      'id'               => (int)$row['id'],
      'pc_name'          => $row['pc_name'],
      'status'           => $row['effective_status'], // what the UI should use
      'base_status'      => $row['base_status'],      // the raw pcs.status (for debugging/analytics)
    ];

    if ($includeFuture) {
      $pc['next_res_start'] = $row['next_res_start'] ?? null;
      $pc['next_res_end']   = $row['next_res_end'] ?? null;
    }

    $grouped[$labName][] = $pc;
}

header('Content-Type: application/json');
echo json_encode($grouped, JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
