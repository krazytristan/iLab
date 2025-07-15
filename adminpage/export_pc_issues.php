<?php
session_start();
if (!isset($_SESSION['admin'])) { http_response_code(403); exit('Not authorized'); }
require_once '../includes/db.php';

$type = $_GET['type'] ?? '';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$type.'_'.date('Ymd_His').'.csv"');
$output = fopen('php://output', 'w');

if ($type === 'problematic_pcs') {
    fputcsv($output, ['PC Name', 'Issues Reported', 'Current Status', 'Last Issue Date']);
    $res = $conn->query("
        SELECT pcs.pc_name, pcs.status, COUNT(mr.id) AS issue_count, MAX(mr.created_at) AS last_issue_date
        FROM maintenance_requests mr
        JOIN pcs ON mr.pc_id = pcs.id
        GROUP BY pcs.id
        ORDER BY issue_count DESC
        LIMIT 10
    ");
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['pc_name'],
            $row['issue_count'],
            $row['status'],
            $row['last_issue_date'] ? date('Y-m-d H:i:s', strtotime($row['last_issue_date'])) : ''
        ]);
    }
} elseif ($type === 'reports') {
    fputcsv($output, ['Type', 'Details', 'Student', 'Date']);
    $res = $conn->query("
        SELECT r.report_type, r.details, s.fullname AS student, r.created_at
        FROM reports r
        LEFT JOIN students s ON r.student_id = s.id
        ORDER BY r.created_at DESC
    ");
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['report_type'],
            $row['details'],
            $row['student'],
            date('Y-m-d H:i:s', strtotime($row['created_at']))
        ]);
    }
}
fclose($output);
exit;
?>
