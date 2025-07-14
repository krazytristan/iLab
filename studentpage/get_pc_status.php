<?php
require_once '../includes/db.php';

// Get all PCs with their lab group
$result = $conn->query("SELECT pcs.id, pcs.pc_name, pcs.status, labs.lab_name
                        FROM pcs
                        JOIN labs ON pcs.lab_id = labs.id
                        ORDER BY labs.lab_name, pcs.pc_name");

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $lab = $row['lab_name'] ?: 'Other';
    $grouped[$lab][] = [
        'id' => $row['id'],
        'pc_name' => $row['pc_name'],
        'status' => $row['status'],
    ];
}
header('Content-Type: application/json');
echo json_encode($grouped);
