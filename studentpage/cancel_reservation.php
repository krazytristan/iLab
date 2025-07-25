<?php
// cancel_reservation.php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit();
}

$student_id = (int) $_SESSION['student_id'];
$reservation_id = isset($_POST['cancel_reservation_id']) ? (int) $_POST['cancel_reservation_id'] : 0;
$reason = trim($_POST['cancel_reason'] ?? '');

// Basic validation
if ($reservation_id <= 0) {
    $_SESSION['flash_message'] = "⚠️ Invalid reservation selected.";
    header("Location: student_dashboard.php#reserve");
    exit();
}

// Get current date/time
$now_date = date('Y-m-d');
$now_time = date('H:i:s');

// 1) Fetch the reservation, ensure it belongs to this student and is cancellable
$sql = "
    SELECT id, pc_id, reservation_date, time_start, time_end, status
    FROM pc_reservations
    WHERE id = ? AND student_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $reservation_id, $student_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    $_SESSION['flash_message'] = "⚠️ Reservation not found.";
    header("Location: student_dashboard.php#reserve");
    exit();
}

$allowed_statuses = ['pending', 'approved']; // you can add 'reserved' if you want to allow pre-start cancel
if (!in_array($res['status'], $allowed_statuses, true)) {
    $_SESSION['flash_message'] = "❌ You can only cancel pending or approved reservations.";
    header("Location: student_dashboard.php#reserve");
    exit();
}

// Ensure it hasn't started yet
$has_started = ($res['reservation_date'] < $now_date) ||
               ($res['reservation_date'] === $now_date && $now_time >= $res['time_start']);

if ($has_started) {
    $_SESSION['flash_message'] = "❌ You cannot cancel a reservation that already started.";
    header("Location: student_dashboard.php#reserve");
    exit();
}

// 2) Cancel the reservation
$upd = $conn->prepare("
    UPDATE pc_reservations
    SET status = 'cancelled',
        reason = CONCAT(IFNULL(reason, ''), IF(? <> '', CONCAT('\nStudent note: ', ?), '')),
        updated_at = NOW()
    WHERE id = ? AND student_id = ?
    LIMIT 1
");
$upd->bind_param("ssii", $reason, $reason, $reservation_id, $student_id);
$ok = $upd->execute();
$upd->close();

if (!$ok) {
    $_SESSION['flash_message'] = "❌ Failed to cancel reservation. Please try again.";
    header("Location: student_dashboard.php#reserve");
    exit();
}

// 3) (Optional) Notify admin
$admin_id = 1;
$msg = "❌ Student cancelled reservation #{$reservation_id} (PC #{$res['pc_id']}) for {$res['reservation_date']} {$res['time_start']}–{$res['time_end']}.";
$notif = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'admin', ?)");
$notif->bind_param("is", $admin_id, $msg);
$notif->execute();
$notif->close();

$_SESSION['flash_message'] = "✅ Reservation cancelled successfully.";
header("Location: student_dashboard.php#reserve");
exit();
