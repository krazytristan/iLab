<?php
// functions.php - reusable database functions

// Get total count from a table with optional condition
function getCount($conn, $table, $condition = '1') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM $table WHERE $condition");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'] ?? 0;
}

// Get recent lab activities
function getRecentActivities($conn, $limit = 5) {
    $stmt = $conn->prepare("SELECT action, user, pc_no, timestamp FROM lab_activities ORDER BY timestamp DESC LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get latest PC reservations
function getLatestReservations($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT r.id, s.fullname, p.pc_name, r.reservation_time, r.status 
        FROM pc_reservations r
        JOIN students s ON r.student_id = s.id
        JOIN pcs p ON r.pc_id = p.id
        ORDER BY r.reservation_time DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get user logs
function getUserLogs($conn, $limit = 50) {
    $stmt = $conn->prepare("
        SELECT u.fullname, l.action, l.pc_no, l.timestamp 
        FROM user_logs l 
        JOIN students u ON l.user_id = u.id 
        ORDER BY l.timestamp DESC LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get student requests
function getStudentRequests($conn, $limit = 20) {
    $stmt = $conn->prepare("
        SELECT r.id, s.fullname, r.subject, r.message, r.status, r.created_at
        FROM student_requests r
        JOIN students s ON r.student_id = s.id
        ORDER BY r.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get maintenance requests
function getMaintenanceRequests($conn, $limit = 20) {
    $stmt = $conn->prepare("
        SELECT m.id, m.pc_id, p.pc_name, m.issue, m.status, m.created_at
        FROM maintenance_requests m
        JOIN pcs p ON m.pc_id = p.id
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Get admin notifications
function getAdminNotifications($conn, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT n.message, n.created_at, s.fullname
        FROM notifications n
        LEFT JOIN students s ON n.recipient_id = s.id
        WHERE n.recipient_type = 'admin'
        ORDER BY n.created_at DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
