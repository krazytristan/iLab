<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['student_id'])) {
  header("Location: ../studentpage/student_login.php");
  exit();
}

$student_id = (int)$_SESSION['student_id'];

/* ---------------------------
 * Helpers
 * --------------------------- */
function format_duration($mins): string {
  if ($mins === null) return '-';
  $mins = (int)$mins;
  if ($mins < 0) $mins = 0; // guard
  $h = intdiv($mins, 60);
  $m = $mins % 60;
  if ($h > 0) return "{$h}h {$m}m";
  return "{$m}m";
}

/**
 * Auto-start helper
 */
function start_lab_session(mysqli $conn, int $student_id, int $reservation_id, int $pc_id): void
{
    $conn->begin_transaction();
    try {
        // 1) Insert active session
        $stmt = $conn->prepare("
            INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time, reservation_id)
            VALUES ('student', ?, ?, 'active', NOW(), ?)
        ");
        $stmt->bind_param("iii", $student_id, $pc_id, $reservation_id);
        $stmt->execute();
        $stmt->close();

        // 2) Set PC to in_use
        $stmt = $conn->prepare("UPDATE pcs SET status = 'in_use' WHERE id = ?");
        $stmt->bind_param("i", $pc_id);
        $stmt->execute();
        $stmt->close();

        // 3) Mark reservation as reserved
        $stmt = $conn->prepare("UPDATE pc_reservations SET status = 'reserved' WHERE id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['flash_message'] = "✅ Session started automatically. Enjoy your reserved PC!";
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('AUTO START ERROR: ' . $e->getMessage());
        $_SESSION['flash_message'] = "❌ Could not auto-start your session. Please click Start Session.";
    }
}

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

/* =========================================================
 * 1) Handle "Start Session" (manual)
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session'])) {
    $reservation_id = (int)$_POST['start_reservation_id'];
    $pc_id = (int)$_POST['start_pc_id'];

    $now_date = date('Y-m-d');
    $now_time = date('H:i:s');
    $check_stmt = $conn->prepare("
        SELECT id FROM pc_reservations 
        WHERE id = ? AND student_id = ? 
          AND status = 'approved'
          AND reservation_date = ?
          AND ? BETWEEN time_start AND time_end
        LIMIT 1
    ");
    $check_stmt->bind_param("iiss", $reservation_id, $student_id, $now_date, $now_time);
    $check_stmt->execute();
    $is_valid = $check_stmt->get_result()->fetch_assoc();

    if ($is_valid) {
        start_lab_session($conn, $student_id, $reservation_id, $pc_id);
    } else {
        $_SESSION['flash_message'] = "❌ Session could not be started. Please check your reservation time.";
    }
    header("Location: student_dashboard.php");
    exit();
}

/* =========================================================
 * 2) Handle Time Out
 * ========================================================= */
if (isset($_POST['time_out'])) {
  // 1. End the session
  $timeout_stmt = $conn->prepare("
      UPDATE lab_sessions 
      SET status = 'inactive', logout_time = NOW() 
      WHERE user_type='student' AND user_id = ? AND status = 'active'
  ");
  $timeout_stmt->bind_param("i", $student_id);
  $timeout_stmt->execute();

  // 2. Find the last PC used by this student
  $find_pc = $conn->prepare("
    SELECT pc_id 
    FROM lab_sessions 
    WHERE user_type='student' AND user_id = ? 
    ORDER BY login_time DESC LIMIT 1
  ");
  $find_pc->bind_param("i", $student_id);
  $find_pc->execute();
  $res = $find_pc->get_result()->fetch_assoc();

  if ($res && isset($res['pc_id'])) {
    $used_pc_id = (int)$res['pc_id'];

    // 3. Update the PC status back to available
    $update_pc = $conn->prepare("UPDATE pcs SET status = 'available' WHERE id = ?");
    $update_pc->bind_param("i", $used_pc_id);
    $update_pc->execute();

    // 4. OPTIONAL: Mark the corresponding reservation as completed
    $update_res = $conn->prepare("
        UPDATE pc_reservations 
        SET status = 'completed' 
        WHERE pc_id = ? AND student_id = ? AND status IN ('approved', 'reserved')
    ");
    $update_res->bind_param("ii", $used_pc_id, $student_id);
    $update_res->execute();
  }

  $_SESSION['flash_message'] = "✅ You have logged out from the session.";
  header("Location: student_dashboard.php");
  exit();
}

/* =========================================================
 * 3) Assistance Request
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_description'])) {
  $desc   = trim($_POST['help_description']);
  $pc_id  = (int)($_POST['help_pc_id'] ?? 0);

  if (!$pc_id || $desc === '') {
    $_SESSION['flash_message'] = "⚠️ Please choose a PC and describe the issue.";
    header("Location: student_dashboard.php#request");
    exit();
  }

  $request_stmt = $conn->prepare("
      INSERT INTO maintenance_requests (pc_id, student_id, issue, status, created_at)
      VALUES (?, ?, ?, 'pending', NOW())
  ");
  $request_stmt->bind_param("iis", $pc_id, $student_id, $desc);
  $request_stmt->execute();

  // Notify admin
  $admin_id = 1; // You can update if multiple admins exist
  $admin_message = "🛠️ Assistance request from Student #$student_id on PC #$pc_id.";
  $admin_notif = $conn->prepare("
      INSERT INTO notifications (recipient_id, recipient_type, message) 
      VALUES (?, 'admin', ?)
  ");
  $admin_notif->bind_param("is", $admin_id, $admin_message);
  $admin_notif->execute();

  $_SESSION['flash_message'] = "✅ Assistance request submitted!";
  header("Location: student_dashboard.php#request");
  exit();
}

/* =========================================================
 * 4) Reservation Request (legacy/fallback – AJAX will hit reserve_handler.php)
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_pc_id']) && !isset($_POST['is_ajax'])) {
  $reserve_pc_id    = (int)($_POST['reserve_pc_id'] ?? 0);
  $reservation_date = $_POST['reservation_date'] ?? null;
  $time_start       = $_POST['reservation_time_start'] ?? null;
  $time_end         = $_POST['reservation_time_end'] ?? null;

  if (!$reserve_pc_id || !$reservation_date || !$time_start || !$time_end) {
    $_SESSION['flash_message'] = "⚠️ Missing reservation details.";
    header("Location: student_dashboard.php#reserve");
    exit();
  }

  // 1) Check maintenance
  $maintenance_check = $conn->prepare("
    SELECT 1 FROM maintenance_requests 
    WHERE pc_id = ? AND status IN ('pending','in_progress') LIMIT 1
  ");
  $maintenance_check->bind_param("i", $reserve_pc_id);
  $maintenance_check->execute();
  $is_maintenance = $maintenance_check->get_result()->fetch_assoc();

  // 2) Check overlapping reservations
  $conflict_sql = "
    SELECT 1
    FROM pc_reservations
    WHERE pc_id = ?
      AND reservation_date = ?
      AND status IN ('pending','approved','reserved')
      AND NOT (time_end <= ? OR time_start >= ?)
    LIMIT 1
  ";
  $conflict_stmt = $conn->prepare($conflict_sql);
  $conflict_stmt->bind_param("isss", $reserve_pc_id, $reservation_date, $time_start, $time_end);
  $conflict_stmt->execute();
  $conflict = $conflict_stmt->get_result()->fetch_assoc();

  if (!$is_maintenance && !$conflict) {
    // 3) Insert reservation
    $reserve_stmt = $conn->prepare("
      INSERT INTO pc_reservations 
        (student_id, pc_id, reservation_date, time_start, time_end, status, reservation_time)
      VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $reserve_stmt->bind_param("iisss", $student_id, $reserve_pc_id, $reservation_date, $time_start, $time_end);
    $reserve_stmt->execute();

    // 4) Notify admin
    $admin_id = 1;
    $admin_notif = $conn->prepare("
      INSERT INTO notifications (recipient_id, recipient_type, message) 
      VALUES (?, 'admin', ?)
    ");
    $admin_msg = "🖥️ Student requested to reserve PC #$reserve_pc_id for $reservation_date, $time_start to $time_end.";
    $admin_notif->bind_param("is", $admin_id, $admin_msg);
    $admin_notif->execute();

    $_SESSION['flash_message'] = "⌚ Reservation request submitted. Please wait for admin approval.";
  } else {
    $_SESSION['flash_message'] = "⚠️ Reservation failed. PC is currently unavailable.";
  }

  header("Location: student_dashboard.php#reserve");
  exit();
}

/* =========================================================
 * 5) Student + PC status data
 * ========================================================= */
$student_stmt = $conn->prepare("SELECT fullname, email FROM students WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

$pcsData = [];
$query = "
  SELECT pcs.id, pcs.pc_name, labs.lab_name,
         CASE
           WHEN pcs.status = 'maintenance' OR EXISTS (
             SELECT 1 FROM maintenance_requests 
             WHERE pc_id = pcs.id AND status IN ('pending', 'in_progress')
           ) THEN 'maintenance'
           WHEN EXISTS (
             SELECT 1 FROM pc_reservations 
             WHERE pc_id = pcs.id 
               AND status IN ('reserved', 'approved') 
               AND reservation_date = CURDATE() 
               AND CURTIME() BETWEEN time_start AND time_end
           ) THEN 'reserved'
           ELSE pcs.status
         END AS status
  FROM pcs
  INNER JOIN labs ON pcs.lab_id = labs.id
  ORDER BY labs.lab_name, pcs.pc_name
";
$pcsResult = $conn->query($query);
if (!$pcsResult) {
    die("Query failed: " . $conn->error);
}
while ($row = $pcsResult->fetch_assoc()) {
    $pcsData[] = $row;
}
$grouped_pcs = [];
foreach ($pcsData as $pc) {
    // derive number from the tailing digits of pc_name or fallback to id
    $pc_number = null;
    if (preg_match('/(\d+)\s*$/', $pc['pc_name'], $m)) {
        $pc_number = (int)$m[1];
    } else {
        $pc_number = (int)$pc['id'];
    }

    $grouped_pcs[$pc['lab_name']][] = [
        'id'        => (int)$pc['id'],
        'pc_name'   => $pc['pc_name'],
        'pc_number' => $pc_number,
        'status'    => $pc['status']
    ];
}

// Reserved Dates Per PC
$reservedDatesPerPC = [];
$reservedDatesSQL = $conn->query("
  SELECT pc_id, reservation_date 
  FROM pc_reservations 
  WHERE status IN ('pending','reserved','approved','completed')
");
while ($row = $reservedDatesSQL->fetch_assoc()) {
  if ($row['reservation_date']) {
    $reservedDatesPerPC[$row['pc_id']][] = $row['reservation_date'];
  }
}

/* =========================================================
 * 6) Active session (if any)
 * ========================================================= */
$active_stmt = $conn->prepare("
  SELECT id, pc_id, login_time 
  FROM lab_sessions 
  WHERE user_type='student' AND user_id = ? AND status = 'active' 
  ORDER BY login_time DESC LIMIT 1
");
$active_stmt->bind_param("i", $student_id);
$active_stmt->execute();
$sessionResult = $active_stmt->get_result()->fetch_assoc();

$session_status = $sessionResult ? 'Active' : 'Not in Session';
$assigned_pc = ($sessionResult && isset($sessionResult['pc_id'])) ? 'PC #' . $sessionResult['pc_id'] : '-';
$login_ts = $sessionResult['login_time'] ?? null;
$session_time = null;
if ($login_ts) {
  $diffSecs = time() - strtotime($login_ts);
  $session_time = $diffSecs > 0 ? round($diffSecs / 60) : 0;
}

/* =========================================================
 * 7) Approved reservation “ready” right now
 * ========================================================= */
$now_date = date('Y-m-d');
$now_time = date('H:i:s');
$res_stmt = $conn->prepare("
    SELECT id, pc_id, time_start, time_end 
    FROM pc_reservations 
    WHERE student_id = ? AND status = 'approved' 
      AND reservation_date = ? 
      AND ? BETWEEN time_start AND time_end
    LIMIT 1
");
$res_stmt->bind_param("iss", $student_id, $now_date, $now_time);
$res_stmt->execute();
$reservation_ready = $res_stmt->get_result()->fetch_assoc();

/* ===== AUTO-START (if approved window & no active session) ===== */
if (!$sessionResult && $reservation_ready) {
    start_lab_session(
        $conn,
        $student_id,
        (int)$reservation_ready['id'],
        (int)$reservation_ready['pc_id']
    );
    header("Location: student_dashboard.php");
    exit();
}

/* =========================================================
 * 8) Recent logs (with duration)
 * ========================================================= */
$log_stmt = $conn->prepare("
  SELECT 
      pcs.pc_name, 
      ls.login_time, 
      ls.logout_time, 
      ls.status,
      TIMESTAMPDIFF(MINUTE, ls.login_time, IFNULL(ls.logout_time, NOW())) AS duration_minutes
  FROM lab_sessions ls 
  JOIN pcs ON pcs.id = ls.pc_id 
  WHERE ls.user_type='student' AND ls.user_id = ?
  ORDER BY ls.login_time DESC 
  LIMIT 20
");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$logs = $log_stmt->get_result();

/* =========================================================
 * 9) Notifications
 * ========================================================= */
$query = "
  SELECT id, message, is_read, created_at 
  FROM notifications 
  WHERE recipient_type = 'student' AND recipient_id = ? 
  ORDER BY created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
  $notifications[] = $row;
}
$unread_count = 0;
foreach ($notifications as $n) {
  if (empty($n['is_read'])) $unread_count++;
}

/* =========================================================
 * 10) My Assistance Requests (+ summary)
 * ========================================================= */
$assist_stmt = $conn->prepare("
  SELECT 
    mr.id,
    pcs.pc_name,
    mr.issue,
    mr.status,
    mr.created_at,
    mr.updated_at
  FROM maintenance_requests mr
  JOIN pcs ON pcs.id = mr.pc_id
  WHERE mr.student_id = ?
  ORDER BY mr.created_at DESC
");
$assist_stmt->bind_param("i", $student_id);
$assist_stmt->execute();
$assist_requests = $assist_stmt->get_result();

$assist_counts = [
  'pending' => 0,
  'in_progress' => 0,
  'completed' => 0,
  'resolved' => 0,
  'rejected' => 0
];
$assist_requests->data_seek(0);
while ($tmp = $assist_requests->fetch_assoc()) {
  $status_l = strtolower($tmp['status']);
  if (isset($assist_counts[$status_l])) $assist_counts[$status_l]++;
}
$assist_requests->data_seek(0);

/* =========================================================
 * 11) My Reservations (for listing)
 * ========================================================= */
$my_res = $conn->prepare("
  SELECT r.id, p.pc_name, r.reservation_date, r.time_start, r.time_end, r.status
  FROM pc_reservations r
  JOIN pcs p ON r.pc_id = p.id
  WHERE r.student_id = ?
  ORDER BY r.reservation_date DESC, r.time_start DESC
");
$my_res->bind_param("i", $student_id);
$my_res->execute();
$my_reservations = $my_res->get_result();

/* For in-page countdown if you want: find active/reserved reservation */
$active_reservation_remaining_secs = null;
if ($session_status === 'Active' && $login_ts) {
  $find_end = $conn->prepare("
    SELECT TIMESTAMPDIFF(SECOND, NOW(), CONCAT(reservation_date, ' ', time_end)) AS remaining
    FROM pc_reservations
    WHERE student_id = ?
      AND reservation_date = CURDATE()
      AND status IN ('reserved','approved')
      AND CURTIME() < time_end
    ORDER BY time_end DESC
    LIMIT 1
  ");
  $find_end->bind_param("i", $student_id);
  $find_end->execute();
  $remaining = $find_end->get_result()->fetch_assoc();
  if ($remaining && $remaining['remaining'] !== null) {
    $active_reservation_remaining_secs = (int)$remaining['remaining'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="/ilab/css/studentdashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex">

<!-- Overlay for mobile sidebar -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden md:hidden"></div>

<!-- SIDEBAR -->
<aside id="sidebar" class="w-64 bg-blue-900 text-white p-5 space-y-2 min-h-screen fixed md:static top-0 left-0 z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 overflow-y-auto hide-scrollbar">
  <div class="text-center mb-6">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="w-16 mx-auto mb-2" />
    <h2 class="text-lg font-bold"><?= htmlspecialchars($student['fullname']) ?></h2>
    <p class="text-sm text-blue-300">Student Panel</p>
  </div>
  <nav class="space-y-2">
    <?php
      $navItems = [
        'dashboard' => ['fa-home', 'Dashboard'],
        'pcstatus'  => ['fa-desktop', 'PC Status'],
        'reserve'   => ['fa-calendar-check', 'Reserve PC'],
        'request'   => ['fa-tools', 'Request Assistance'],
        'mylogs'    => ['fa-history', 'My Logs'],
        'settings'  => ['fa-cog', 'Settings'],
      ];
      foreach ($navItems as $key => [$icon, $label]) {
        echo "<a href=\"#\" class=\"nav-link px-3 py-2 rounded hover:bg-blue-700 flex items-center gap-2\" data-target=\"$key\"><i class=\"fas $icon\"></i> $label</a>";
      }
    ?>
    <a href="../adminpage/logout.php" class="px-3 py-2 rounded hover:bg-red-600 flex items-center gap-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </nav>
</aside>

<!-- MAIN CONTENT -->
<main class="flex-1 p-6 space-y-10 overflow-y-auto relative">

  <!-- Flash Message -->
  <?php if (!empty($flash_message)): ?>
    <div class="bg-yellow-100 text-yellow-800 border border-yellow-300 p-4 rounded mb-4" id="flash-message">
      <?= htmlspecialchars($flash_message) ?>
    </div>
  <?php endif; ?>

  <!-- Header with Hamburger -->
  <div class="flex justify-between items-center relative">
    <div class="flex items-center">
      <button id="toggleSidebar" class="md:hidden text-blue-800 text-2xl mr-4 focus:outline-none">
        <i class="fas fa-bars"></i>
      </button>
      <h1 class="text-2xl font-bold">Welcome, <?= htmlspecialchars($student['fullname']) ?></h1>
    </div>

    <!-- Clock & Notifications -->
    <div class="flex items-center gap-4">
      <div id="clock" class="text-lg font-mono text-gray-800"></div>

      <div class="relative">
        <!-- Bell -->
        <button id="notif-btn" class="relative text-gray-600 hover:text-blue-700 focus:outline-none">
          <i class="fas fa-bell text-xl"></i>
          <?php if ($unread_count > 0): ?>
            <span id="notif-dot-outer" class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full animate-ping"></span>
            <span id="notif-dot-inner" class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
          <?php endif; ?>
        </button>

        <!-- Notification sound (optional) -->
        <audio id="notifSound" src="/iLab/assets/notif.mp3" preload="auto"></audio>

        <!-- Dropdown -->
        <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-80 bg-white border rounded shadow-md z-50">
          <div class="flex items-center justify-between p-3 border-b">
            <span class="font-semibold text-sm text-gray-700">Notifications</span>
            <button id="mark-all-read" class="text-xs text-blue-600 hover:underline <?= $unread_count ? '' : 'hidden' ?>">
              Mark all as read
            </button>
          </div>
          <ul id="notif-list" class="max-h-64 overflow-y-auto text-sm">
            <?php if (!empty($notifications)): ?>
              <?php foreach ($notifications as $notif): ?>
                <li class="p-3 border-b hover:bg-gray-100 cursor-pointer <?= $notif['is_read'] ? 'text-gray-400' : 'font-semibold' ?>"
                    data-id="<?= $notif['id'] ?>">
                  <?= htmlspecialchars($notif['message']) ?><br>
                  <small class="<?= $notif['is_read'] ? 'text-gray-400' : 'text-gray-500' ?>">
                    <?= date('M d, h:i A', strtotime($notif['created_at'])) ?>
                  </small>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li class="p-3 text-gray-500">No notifications</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- DASHBOARD -->
  <section id="dashboard" class="section">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
      <div class="bg-green-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">Session Status</h3>
        <p><?= $session_status ?></p>
      </div>
      <div class="bg-blue-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">PC Assigned</h3>
        <p><?= $assigned_pc ?></p>
      </div>
      <div class="bg-yellow-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">Time Used</h3>
        <p id="liveDuration">
          <?= $login_ts ? format_duration($session_time) : '-' ?>
        </p>
      </div>
      <div class="bg-purple-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">Assistance Requests</h3>
        <p>
          <span class="mr-2 text-yellow-700">Pending: <?= $assist_counts['pending'] ?></span>
          <span class="mr-2 text-blue-700">In Progress: <?= $assist_counts['in_progress'] ?></span>
          <span class="mr-2 text-green-700">Done: <?= $assist_counts['completed'] + $assist_counts['resolved'] ?></span>
          <span class="text-red-700">Rejected: <?= $assist_counts['rejected'] ?></span>
        </p>
      </div>
    </div>

    <!-- If eligible, manual start button (fallback) -->
    <?php if (!$sessionResult && $reservation_ready): ?>
      <form method="POST" class="mt-6">
        <input type="hidden" name="start_reservation_id" value="<?= $reservation_ready['id'] ?>">
        <input type="hidden" name="start_pc_id" value="<?= $reservation_ready['pc_id'] ?>">
        <button type="submit" name="start_session" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
          Start Session (<?= date('h:i A', strtotime($reservation_ready['time_start'])) ?> - <?= date('h:i A', strtotime($reservation_ready['time_end'])) ?>)
        </button>
      </form>
    <?php endif; ?>

    <?php if ($session_status === 'Active'): ?>
      <form method="POST" class="mt-6">
        <button name="time_out" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Time Out</button>
      </form>
    <?php endif; ?>

    <?php if ($active_reservation_remaining_secs !== null && $active_reservation_remaining_secs > 0): ?>
      <div class="mt-4 bg-indigo-100 text-indigo-800 p-3 rounded shadow">
        <strong>Time remaining in your reserved slot:</strong>
        <span id="countdown" data-remaining="<?= $active_reservation_remaining_secs ?>"></span>
      </div>
    <?php endif; ?>
  </section>

  <!-- PC STATUS -->
  <section id="pcstatus" class="section hidden">
    <h2 class="text-2xl font-semibold mb-6 text-gray-800">PC Status by Laboratory</h2>
    <div id="pc-status-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
    <div class="flex mt-4">
      <button id="refresh-pc-status" class="ml-auto px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">
        <i class="fas fa-sync-alt"></i> Refresh Status
      </button>
    </div>
  </section>

  <!-- Modal for PC list -->
  <div id="pcModal" class="fixed inset-0 hidden bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg overflow-y-auto max-h-[80vh]">
      <div class="flex justify-between items-center p-4 border-b">
        <h3 id="modalLabName" class="text-xl font-bold text-blue-800">Lab Name</h3>
        <button id="closeModal" class="text-gray-500 hover:text-red-600 text-2xl font-bold">&times;</button>
      </div>
      <div id="modalPcList" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-4"></div>
    </div>
  </div>

  <!-- RESERVE PC -->
  <section id="reserve" class="section hidden">
    <div class="bg-white shadow rounded-xl p-6 dark:bg-gray-800 dark:text-white">
      <h2 class="text-2xl font-bold mb-6 text-blue-800 dark:text-blue-300 flex items-center gap-2">
        <i class="fas fa-desktop"></i> Reserve a PC
      </h2>

      <form method="POST" action="/iLab/studentpage/reserve_handler.php" class="space-y-6" id="reserveForm">
        <!-- Lab Selection -->
        <div>
          <label for="lab-select" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Select Laboratory</label>
          <select id="lab-select"
                  class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:outline-none transition dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  required>
            <option value="">-- Choose Lab --</option>
            <?php foreach ($grouped_pcs as $lab => $pcs): ?>
              <option value="<?= htmlspecialchars($lab) ?>"><?= htmlspecialchars($lab) ?> (<?= count($pcs) ?> PCs)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- PC Selection -->
        <div id="pc-boxes"
             class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 hidden max-h-64 overflow-y-auto pr-2 border border-gray-300 rounded-lg p-3 bg-gray-50 shadow-inner dark:bg-gray-700 dark:border-gray-600">
          <!-- Dynamic buttons -->
        </div>

        <!-- Date & Time -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div>
            <label for="reservation_date" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Date</label>
            <input type="date" name="reservation_date" id="reservation_date"
                   class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                   required min="<?= date('Y-m-d') ?>" disabled>
          </div>
          <div>
          <label for="reservation_time_start" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">Start Time</label>
            <input type="time" name="reservation_time_start" id="reservation_time_start"
                   class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                   required disabled>
          </div>
          <div>
            <label for="reservation_time_end" class="block mb-1 font-medium text-gray-700 dark:text-gray-300">End Time</label>
            <input type="time" name="reservation_time_end" id="reservation_time_end"
                   class="w-full px-4 py-2 border rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                   required disabled>
          </div>
        </div>

        <input type="hidden" name="reserve_pc_id" id="reserve_pc_id" />

        <div class="text-right mt-6">
          <button type="submit"
                  class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-lg font-medium hover:from-blue-600 hover:to-blue-800 transition shadow-lg disabled:opacity-50"
                  id="submit-reserve-btn" disabled>
            <i class="fas fa-calendar-check mr-2"></i> Reserve PC
          </button>
        </div>
      </form>

      <!-- My Reservations -->
      <div class="mt-10">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">My Reservations</h3>
        <div class="overflow-x-auto">
          <table class="w-full table-auto border border-gray-300 rounded shadow-sm text-sm">
            <thead class="bg-gray-100 text-gray-800">
              <tr>
                <th class="px-3 py-2 border">PC</th>
                <th class="px-3 py-2 border">Date</th>
                <th class="px-3 py-2 border">Start</th>
                <th class="px-3 py-2 border">End</th>
                <th class="px-3 py-2 border">Status</th>
                <th class="px-3 py-2 border">Countdown</th>
                <th class="px-3 py-2 border">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($my_reservations->num_rows > 0): ?>
                <?php while ($res = $my_reservations->fetch_assoc()):
                  $statusColor = match (strtolower($res['status'])) {
                    'pending'   => 'bg-yellow-100 text-yellow-800',
                    'approved'  => 'bg-blue-100 text-blue-800',
                    'reserved'  => 'bg-green-100 text-green-800',
                    'completed' => 'bg-gray-200 text-gray-700',
                    default     => 'bg-red-100 text-red-800'
                  };
                  $remainingCell = '-';
                  if (in_array($res['status'], ['approved','reserved']) && $res['reservation_date'] === date('Y-m-d')) {
                    $end_ts = strtotime($res['reservation_date'].' '.$res['time_end']);
                    $remaining_secs = $end_ts - time();
                    if ($remaining_secs > 0) {
                      $remainingCell = '<span class="countdown" data-remaining="'.$remaining_secs.'"></span>';
                    }
                  }
                ?>
                  <tr class="text-center">
                    <td class="border px-3 py-2"><?= htmlspecialchars($res['pc_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($res['reservation_date']) ?></td>
                    <td class="border px-3 py-2"><?= date('h:i A', strtotime($res['time_start'])) ?></td>
                    <td class="border px-3 py-2"><?= date('h:i A', strtotime($res['time_end'])) ?></td>
                    <td class="border px-3 py-2">
                      <span class="px-2 py-1 rounded <?= $statusColor ?>"><?= ucfirst($res['status']) ?></span>
                    </td>
                    <td class="border px-3 py-2"><?= $remainingCell ?></td>
                    <td class="border px-3 py-2">
                      <?php if (in_array($res['status'], ['pending', 'approved'])): ?>
                        <form method="POST" action="/iLab/studentpage/cancel_reservation.php" onsubmit="return confirm('Cancel this reservation?');">
                          <input type="hidden" name="cancel_reservation_id" value="<?= $res['id'] ?>">
                          <button type="submit" class="text-red-600 hover:underline">Cancel</button>
                        </form>
                      <?php else: ?>
                        <span class="text-gray-400 italic">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-gray-500">No reservations found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  <!-- REQUEST ASSISTANCE -->
  <section id="request" class="section hidden">
    <h2 class="text-2xl font-semibold mb-4 text-blue-900">Request Assistance</h2>

    <form method="POST" class="space-y-4 bg-white p-4 rounded shadow-sm">
      <select name="help_pc_id" class="p-2 border rounded w-full" required>
        <option value="">-- Choose PC --</option>
        <?php foreach ($pcsData as $pc): ?>
          <option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['pc_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <textarea name="help_description" class="w-full p-2 border rounded min-h-[120px]" placeholder="Describe the issue..." required></textarea>

      <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
        Submit Request
      </button>
    </form>

    <div class="mt-8 bg-white p-4 rounded shadow-sm">
      <h3 class="text-lg font-semibold mb-3 text-gray-800">My Assistance Requests</h3>

      <?php if ($assist_requests->num_rows > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full table-auto border border-gray-200 rounded text-sm">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="px-3 py-2 border">PC Name</th>
                <th class="px-3 py-2 border">Issue</th>
                <th class="px-3 py-2 border">Status</th>
                <th class="px-3 py-2 border">Requested At</th>
                <th class="px-3 py-2 border">Updated At</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($ar = $assist_requests->fetch_assoc()): ?>
                <?php
                  $badge = match (strtolower($ar['status'])) {
                    'pending'     => 'bg-yellow-100 text-yellow-800',
                    'in_progress' => 'bg-blue-100 text-blue-800',
                    'resolved', 
                    'completed'   => 'bg-green-100 text-green-800',
                    'rejected'    => 'bg-red-100 text-red-800',
                    default       => 'bg-gray-100 text-gray-700'
                  };
                ?>
                <tr class="text-center">
                  <td class="border px-3 py-2"><?= htmlspecialchars($ar['pc_name']) ?></td>
                  <td class="border px-3 py-2 text-left"><?= nl2br(htmlspecialchars($ar['issue'])) ?></td>
                  <td class="border px-3 py-2">
                    <span class="px-2 py-1 rounded <?= $badge ?>"><?= ucfirst($ar['status']) ?></span>
                  </td>
                  <td class="border px-3 py-2">
                    <?= date('M d, Y h:i A', strtotime($ar['created_at'])) ?>
                  </td>
                  <td class="border px-3 py-2">
                    <?= date('M d, Y h:i A', strtotime($ar['updated_at'])) ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-gray-500 text-sm italic">No assistance requests submitted yet.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- LOGS -->
  <section id="mylogs" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">My Logs</h2>
    <div class="overflow-x-auto rounded shadow border border-gray-300">
      <table class="w-full border-collapse text-sm">
        <thead class="bg-gray-200">
          <tr>
            <th class="border px-4 py-2 text-left">PC</th>
            <th class="border px-4 py-2 text-left">Login</th>
            <th class="border px-4 py-2 text-left">Logout</th>
            <th class="border px-4 py-2 text-left">Status</th>
            <th class="border px-4 py-2 text-left">Duration</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs->num_rows > 0): ?>
            <?php while ($log = $logs->fetch_assoc()): ?>
              <tr class="text-center hover:bg-gray-50">
                <td class="border px-4 py-2 text-left"><?= htmlspecialchars($log['pc_name']) ?></td>
                <td class="border px-4 py-2 text-left"><?= htmlspecialchars($log['login_time']) ?></td>
                <td class="border px-4 py-2 text-left">
                  <?= $log['logout_time'] ? htmlspecialchars($log['logout_time']) : '<span class="text-red-500 italic">Active</span>' ?>
                </td>
                <td class="border px-4 py-2 text-left"><?= htmlspecialchars($log['status']) ?></td>
                <td class="border px-4 py-2 text-left">
                  <?php if ($log['status'] === 'active' && !$log['logout_time']): ?>
                    <span class="live-log-duration" data-start="<?= strtotime($log['login_time']) * 1000 ?>"></span>
                  <?php else: ?>
                    <?= format_duration($log['duration_minutes']) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="text-center text-gray-500 py-4">No user logs available.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- SETTINGS -->
  <section id="settings" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Settings</h2>
    <p class="mb-4">Email: <?= htmlspecialchars($student['email']) ?></p>
    <form method="POST" action="update_password.php" class="space-y-4 max-w-md">
      <input type="password" name="current_password" class="w-full border p-2 rounded" placeholder="Current Password" required>
      <input type="password" name="new_password" class="w-full border p-2 rounded" placeholder="New Password" required>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Password</button>
    </form>
  </section>
</main>

<?php
// Advanced reservation slot checking (date+time)
$reservedSlots = [];
$reservedSlotsSQL = $conn->query("
    SELECT pc_id, reservation_date, time_start, time_end, status 
    FROM pc_reservations 
    WHERE status IN ('pending','reserved','approved')
");
while ($row = $reservedSlotsSQL->fetch_assoc()) {
  $reservedSlots[$row['pc_id']][] = [
    'date'   => $row['reservation_date'],
    'start'  => $row['time_start'],
    'end'    => $row['time_end'],
    'status' => $row['status'],
  ];
}

// Re-expose for JS
$reservedDatesPerPC = [];
$reservedDatesSQL = $conn->query("
  SELECT pc_id, reservation_date 
  FROM pc_reservations 
  WHERE status IN ('pending','reserved','approved','completed')
");
while ($row = $reservedDatesSQL->fetch_assoc()) {
  if ($row['reservation_date']) {
    $reservedDatesPerPC[$row['pc_id']][] = $row['reservation_date'];
  }
}
?>

<script>
  window.groupedPCs         = <?= json_encode($grouped_pcs) ?>;
  window.reservedDatesPerPC = <?= json_encode($reservedDatesPerPC) ?>;
  window.reservedSlots      = <?= json_encode($reservedSlots) ?>;
</script>

<!-- Your main JS files (if you have them) -->
<script src="/iLab/js/studentdashboard.js"></script>
<script src="/iLab/js/pcstatus_realtime.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = (id) => document.getElementById(id);

  const sidebar        = $('sidebar');
  const sidebarOverlay = $('sidebarOverlay');
  const toggleBtn      = $('toggleSidebar');

  const notifBtn       = $('notif-btn');
  const notifDropdown  = $('notif-dropdown');
  const notifList      = $('notif-list');
  const notifDotOuter  = $('notif-dot-outer');
  const notifDotInner  = $('notif-dot-inner');
  const markAllBtn     = $('mark-all-read');
  const notifSound     = $('notifSound');

  const ENABLE_SOUND = true; // toggle sound
  let lastKnownIds = new Set(
    Array.from(notifList?.querySelectorAll('li[data-id]') || [])
      .map(li => li.dataset.id)
  );

  /* ============================
   * Sidebar toggle (mobile)
   * ============================ */
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('-translate-x-full');
      if (sidebarOverlay) {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        sidebarOverlay.classList.toggle('hidden', !isOpen);
      }
    });
  }

  if (sidebarOverlay && sidebar) {
    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.add('-translate-x-full');
      sidebarOverlay.classList.add('hidden');
    });
  }

  /* ============================
   * Notifications dropdown
   * ============================ */
  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
      if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
        notifDropdown.classList.add('hidden');
      }
    });

    // Single mark as read
    bindNotificationClickHandlers();
  }

  // Mark all as read
  if (markAllBtn) {
    markAllBtn.addEventListener('click', async () => {
      const res = await fetch('/iLab/studentpage/mark_all_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      }).then(r => r.json());

      if (res.success) {
        notifList.querySelectorAll('li[data-id]').forEach(li => {
          li.classList.remove('font-semibold');
          li.classList.add('text-gray-400');
          const time = li.querySelector('small');
          if (time) {
            time.classList.remove('text-gray-500');
            time.classList.add('text-gray-400');
          }
        });
        hideUnreadDot();
        markAllBtn.classList.add('hidden');
      }
    });
  }

  function bindNotificationClickHandlers() {
    notifList?.querySelectorAll('li[data-id]')?.forEach(li => {
      if (li.dataset.bound === '1') return; // prevent double binding
      li.dataset.bound = '1';

      li.addEventListener('click', async () => {
        const id = li.dataset.id;
        const res = await fetch('/iLab/studentpage/mark_notification_read.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${id}`
        }).then(r => r.json());

        if (res.success) {
          li.classList.remove('font-semibold');
          li.classList.add('text-gray-400');
          const time = li.querySelector('small');
          if (time) {
            time.classList.remove('text-gray-500');
            time.classList.add('text-gray-400');
          }

          if (!notifList.querySelector('li.font-semibold')) {
            hideUnreadDot();
            markAllBtn?.classList.add('hidden');
          }
        }
      });
    });
  }

  function showUnreadDot() {
    notifDotOuter?.classList.remove('hidden');
    notifDotInner?.classList.remove('hidden');
  }
  function hideUnreadDot() {
    notifDotOuter?.classList.add('hidden');
    notifDotInner?.classList.add('hidden');
  }

  /* ============================
   * Live polling for new notifications
   * ============================ */
  async function pollNotifications() {
    try {
      const res = await fetch('/iLab/studentpage/fetch_notifications.php', {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      const data = await res.json();
      if (!data || !data.notifications) return;

      const { notifications, unread_count } = data;

      const currentIds = new Set(notifications.map(n => String(n.id)));
      const newOnes = notifications.filter(n => !lastKnownIds.has(String(n.id)));
      if (newOnes.length && ENABLE_SOUND && notifSound) {
        notifSound.currentTime = 0;
        notifSound.play().catch(() => {});
      }
      lastKnownIds = currentIds;

      renderNotifList(notifications);

      if (unread_count > 0) {
        showUnreadDot();
        markAllBtn?.classList.remove('hidden');
      } else {
        hideUnreadDot();
        markAllBtn?.classList.add('hidden');
      }

      bindNotificationClickHandlers();

    } catch (e) {
      console.error('Polling error:', e);
    }
  }

  function renderNotifList(notifs) {
    if (!notifList) return;

    if (!notifs.length) {
      notifList.innerHTML = '<li class="p-3 text-gray-500">No notifications</li>';
      return;
    }

    notifList.innerHTML = notifs.map(n => `
      <li class="p-3 border-b hover:bg-gray-100 cursor-pointer ${n.is_read == 1 ? 'text-gray-400' : 'font-semibold'}"
          data-id="${n.id}">
        ${escapeHtml(n.message)}<br>
        <small class="${n.is_read == 1 ? 'text-gray-400' : 'text-gray-500'}">
          ${n.display_date}
        </small>
      </li>
    `).join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  // Poll every 30s
  setInterval(pollNotifications, 30000);

  /* ============================
   * Clock
   * ============================ */
  function updateClock() {
    const now = new Date();
    const el = $('clock');
    if (el) el.textContent = now.toLocaleString();
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ============================
   * Nav sections
   * ============================ */
  const sections = document.querySelectorAll('.section');
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const t = link.getAttribute('data-target');
      sections.forEach(s => s.classList.add('hidden'));
      document.getElementById(t)?.classList.remove('hidden');
      window.location.hash = t;
    });
  });

  const hash = window.location.hash.replace('#', '');
  if (hash && document.getElementById(hash)) {
    sections.forEach(s => s.classList.add('hidden'));
    document.getElementById(hash).classList.remove('hidden');
  }

  /* ============================
   * Flash auto-hide
   * ============================ */
  setTimeout(() => {
    const fm = $('flash-message');
    if (fm) fm.remove();
  }, 5000);

  /* ============================
   * Live session duration
   * ============================ */
  const liveDurationEl = $('liveDuration');
  const loginTsPHP = "<?= $login_ts ? strtotime($login_ts) * 1000 : '' ?>";
  if (liveDurationEl && loginTsPHP) {
    const startMs = parseInt(loginTsPHP, 10);
    const tick = () => {
      const diffMs = Date.now() - startMs;
      const mins = Math.floor(diffMs / 60000);
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      liveDurationEl.textContent = (h > 0 ? h + 'h ' : '') + m + 'm';
    };
    tick();
    setInterval(tick, 1000);
  }

  // My Logs live duration
  document.querySelectorAll('.live-log-duration').forEach(el => {
    const start = parseInt(el.dataset.start, 10);
    const run = () => {
      const mins = Math.floor((Date.now() - start) / 60000);
      const h = Math.floor(mins / 60);
      const m = mins % 60;
      el.textContent = (h > 0 ? h + 'h ' : '') + m + 'm';
    };
    run();
    setInterval(run, 1000);
  });

  function fmtSeconds(s) {
    if (s < 0) return "0m";
    const m = Math.floor(s / 60);
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return (h > 0 ? h + "h " : "") + mm + "m";
  }

  // Reservation countdown per row
  document.querySelectorAll('.countdown').forEach(el => {
    let remaining = parseInt(el.dataset.remaining, 10);
    const update = () => {
      remaining--;
      if (remaining <= 0) {
        el.textContent = "Finished";
        clearInterval(interval);
      } else {
        el.textContent = fmtSeconds(remaining);
      }
    };
    el.textContent = fmtSeconds(remaining);
    const interval = setInterval(update, 1000);
  });

  // Top dashboard countdown
  const cEl = $('countdown');
  if (cEl) {
    let remain = parseInt(cEl.dataset.remaining, 10);
    const h = setInterval(() => {
      remain--;
      if (remain <= 0) {
        cEl.textContent = "Finished";
        clearInterval(h);
      } else {
        cEl.textContent = fmtSeconds(remain);
      }
    }, 1000);
    cEl.textContent = fmtSeconds(remain);
  }

  /* ============================
   * Reserve form logic (PC grid)
   * ============================ */
  const groupedPcs = window.groupedPCs || {};
  const labSelect = document.getElementById('lab-select');
  const pcBoxes = document.getElementById('pc-boxes');
  const reservePcIdInput = document.getElementById('reserve_pc_id');
  const dateInput = document.getElementById('reservation_date');
  const timeStartInput = document.getElementById('reservation_time_start');
  const timeEndInput = document.getElementById('reservation_time_end');
  const submitBtn = document.getElementById('submit-reserve-btn');

  if (labSelect) {
    labSelect.addEventListener('change', () => {
      const selectedLab = labSelect.value;
      pcBoxes.innerHTML = '';
      reservePcIdInput.value = '';
      submitBtn.disabled = true;
      dateInput.disabled = true;
      timeStartInput.disabled = true;
      timeEndInput.disabled = true;

      if (selectedLab && groupedPcs[selectedLab]) {
        pcBoxes.classList.remove('hidden');
        groupedPcs[selectedLab].forEach(pc => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = `w-full px-3 py-2 rounded-lg text-sm font-semibold border transition ${
            pc.status === 'available'
              ? 'bg-green-100 text-green-800 border-green-300 hover:bg-green-200'
              : 'bg-gray-200 text-gray-500 border-gray-300 cursor-not-allowed'
          }`;
          btn.textContent = `${pc.pc_name}`; // you can also show `PC #${pc.pc_number}`
          btn.disabled = pc.status !== 'available';

          if (pc.status === 'available') {
            btn.addEventListener('click', () => {
              reservePcIdInput.value = pc.id;
              dateInput.disabled = false;
              timeStartInput.disabled = false;
              timeEndInput.disabled = false;
              submitBtn.disabled = false;

              // Highlight selected
              document.querySelectorAll('#pc-boxes button').forEach(b => b.classList.remove('ring', 'ring-blue-500'));
              btn.classList.add('ring', 'ring-blue-500');
            });
          }

          pcBoxes.appendChild(btn);
        });
      } else {
        pcBoxes.classList.add('hidden');
      }
    });
  }

  /* ============================
   * AJAX submit reserve form
   * ============================ */
  const reserveForm = document.getElementById('reserveForm');
  if (reserveForm) {
    reserveForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const formData = new FormData(this);
      // mark it's ajax so PHP won't re-run fallback legacy handler
      formData.append('is_ajax', '1');
      formData.append('student_id', '<?= $student_id ?>');

      submitBtn.disabled = true;

      try {
        const response = await fetch(this.action, {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          Swal.fire('✅ Success!', result.message, 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          Swal.fire(result.status === 'conflict' ? '⚠️ Conflict' : '❌ Error', result.message, result.status === 'conflict' ? 'warning' : 'error');
        }
      } catch (error) {
        console.error('Error submitting reservation:', error);
        Swal.fire('❌ Error', 'An unexpected error occurred.', 'error');
      }

      submitBtn.disabled = false;
    });
  }
});
</script>
</body>
</html>
