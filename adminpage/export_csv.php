<?php
session_start();
if (!isset($_SESSION['admin_username'])) {
    die("Unauthorized.");
}
require_once '../includes/db.php';

$type = $_GET['type'] ?? '';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $type . '_' . date('Ymd_His') . '.csv"');
$output = fopen('php://output', 'w');

switch ($type) {
    case 'problematic_pcs':
        fputcsv($output, ['PC Name', 'Issues Reported', 'Current Status', 'Last Issue Date']);
        $q = $conn->query("
            SELECT pcs.pc_name, pcs.status, COUNT(mr.id) AS issue_count, MAX(mr.created_at) AS last_issue_date
            FROM maintenance_requests mr
            JOIN pcs ON mr.pc_id = pcs.id
            GROUP BY pcs.id
            ORDER BY issue_count DESC
            LIMIT 5
        ");
        while ($row = $q->fetch_assoc()) {
            fputcsv($output, [
                $row['pc_name'],
                $row['issue_count'],
                $row['status'],
                $row['last_issue_date'] ? date('M d, Y h:i A', strtotime($row['last_issue_date'])) : '-'
            ]);
        }
        break;

    case 'reports':
        fputcsv($output, ['Type', 'Details', 'Student', 'PC Name', 'Lab', 'Date']);
        $q = $conn->query("
            SELECT 
                r.report_type, r.details, s.fullname AS student, 
                pcs.pc_name, l.lab_name, r.created_at
            FROM reports r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN pcs ON r.pc_id = pcs.id
            LEFT JOIN labs l ON pcs.lab_id = l.id
            ORDER BY r.created_at DESC
        ");
        while ($row = $q->fetch_assoc()) {
            fputcsv($output, [
                $row['report_type'],
                $row['details'],
                $row['student'] ?? 'Unknown',
                $row['pc_name'] ?? 'N/A',
                $row['lab_name'] ?? 'N/A',
                date('M d, Y h:i A', strtotime($row['created_at']))
            ]);
        }
        break;

    case 'userlogs':
        fputcsv($output, ['Student', 'PC', 'Login', 'Logout', 'Status']);
        $q = $conn->query("
            SELECT s.fullname, pcs.pc_name, ls.login_time, ls.logout_time, ls.status
            FROM lab_sessions ls
            JOIN students s ON ls.user_id = s.id AND ls.user_type = 'student'
            JOIN pcs ON pcs.id = ls.pc_id
            ORDER BY ls.login_time DESC
            LIMIT 100
        ");
        while ($row = $q->fetch_assoc()) {
            fputcsv($output, [
                $row['fullname'],
                $row['pc_name'],
                $row['login_time'] ? date('M d, Y h:i A', strtotime($row['login_time'])) : '',
                $row['logout_time'] ? date('M d, Y h:i A', strtotime($row['logout_time'])) : '',
                $row['status']
            ]);
        }
        break;

    case 'reservations':
        fputcsv($output, ['Student', 'PC', 'Reservation Date', 'Requested At', 'Status', 'Reason']);
        $q = $conn->query("
            SELECT s.fullname, p.pc_name, r.reservation_date, r.reservation_time, r.status, r.reason
            FROM pc_reservations r
            JOIN students s ON r.student_id = s.id
            JOIN pcs p ON r.pc_id = p.id
            ORDER BY r.reservation_time DESC
            LIMIT 100
        ");
        while ($row = $q->fetch_assoc()) {
            fputcsv($output, [
                $row['fullname'],
                $row['pc_name'],
                $row['reservation_date'] ? date('M d, Y', strtotime($row['reservation_date'])) : '',
                $row['reservation_time'] ? date('M d, Y h:i A', strtotime($row['reservation_time'])) : '',
                $row['status'],
                $row['reason'] ?? ''
            ]);
        }
        break;

    case 'maintenance':
        fputcsv($output, ['PC Name', 'Issue', 'Status', 'Reported At']);
        $q = $conn->query("
            SELECT p.pc_name, m.issue, m.status, m.created_at
            FROM maintenance_requests m
            JOIN pcs p ON m.pc_id = p.id
            ORDER BY m.created_at DESC
            LIMIT 100
        ");
        while ($row = $q->fetch_assoc()) {
            fputcsv($output, [
                $row['pc_name'],
                $row['issue'],
                $row['status'],
                date('M d, Y h:i A', strtotime($row['created_at']))
            ]);
        }
        break;

    default:
        fputcsv($output, ['No data type specified or invalid type']);
        break;
}

fclose($output);
exit;
