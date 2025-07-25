<?php
require_once '../adminpage/db.php';

header('Content-Type: application/json');

if (!isset($_GET['lab_id'])) {
  echo json_encode([
    'error' => true,
    'message' => 'Missing lab_id parameter.'
  ]);
  exit;
}

$lab_id   = (int) $_GET['lab_id'];
$slotDate = $_GET['date']  ?? date('Y-m-d');
$slotStart= $_GET['start'] ?? date('H:i:s');
$slotEnd  = $_GET['end']   ?? $slotStart; // instant check if not provided

// Basic input sanity (optional, but helpful)
$validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $slotDate);
$validTimeStart = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $slotStart);
$validTimeEnd   = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $slotEnd);

if (!$validDate || !$validTimeStart || !$validTimeEnd) {
  echo json_encode([
    'error' => true,
    'message' => 'Invalid date/time format.'
  ]);
  exit;
}

// Normalize HH:MM to HH:MM:SS
if (strlen($slotStart) === 5) $slotStart .= ':00';
if (strlen($slotEnd)   === 5) $slotEnd   .= ':00';

// If end < start, treat as invalid
if ($slotEnd < $slotStart) {
  echo json_encode([
    'error' => true,
    'message' => 'End time must be later than start time.'
  ]);
  exit;
}

/**
 * Rule: a PC is available iff:
 *   - pcs.status = 'available'
 *   - NOT in maintenance_requests (pending, in_progress)
 *   - NOT in an active lab_session
 *   - NOT reserved for this date/time overlap (pending, approved, reserved)
 */
$sql = "
  SELECT p.id, p.pc_name
  FROM pcs p
  WHERE p.lab_id = ?
    AND p.status = 'available'
    AND NOT EXISTS (
      SELECT 1 FROM maintenance_requests mr
      WHERE mr.pc_id = p.id
        AND mr.status IN ('pending','in_progress')
    )
    AND NOT EXISTS (
      SELECT 1 FROM lab_sessions ls
      WHERE ls.pc_id = p.id
        AND ls.status = 'active'
    )
    AND NOT EXISTS (
      SELECT 1 FROM pc_reservations r
      WHERE r.pc_id = p.id
        AND r.status IN ('pending','approved','reserved')
        AND r.reservation_date = ?
        AND NOT (r.time_end <= ? OR r.time_start >= ?)
    )
  ORDER BY p.pc_name ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  echo json_encode([
    'error'   => true,
    'message' => 'Prepare failed: ' . $conn->error
  ]);
  exit;
}

$stmt->bind_param("isss", $lab_id, $slotDate, $slotStart, $slotEnd);
$stmt->execute();
$result = $stmt->get_result();

$pcs = [];
while ($row = $result->fetch_assoc()) {
  $pcs[] = $row;
}

echo json_encode([
  'error' => false,
  'lab_id' => $lab_id,
  'date' => $slotDate,
  'start' => $slotStart,
  'end' => $slotEnd,
  'available_pcs_count' => count($pcs),
  'available_pcs' => $pcs
], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
