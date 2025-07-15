<?php
session_start();
// Ensure only admin can mark their notifications
if (!isset($_SESSION['admin'])) { http_response_code(403); exit; }
require_once '../includes/db.php';

// Mark all admin notifications as read (set read_at to NOW() if not already set)
$conn->query("UPDATE notifications SET read_at = NOW() WHERE recipient_type = 'admin' AND read_at IS NULL");
echo json_encode(['success' => true]);
?>
