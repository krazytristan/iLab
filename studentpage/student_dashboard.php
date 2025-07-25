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
            INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time)
            VALUES ('student', ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("ii", $student_id, $pc_id);
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
 * 3) Assistance Request (save also student_id)
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
  $admin_id = 1; // adjust if you support multiple admins
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
 * 4) Reservation Request
 * ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_pc_id'])) {
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
    $grouped_pcs[$pc['lab_name']][] = [
        'id' => $pc['id'],
        'pc_name' => $pc['pc_name'],
        'status' => $pc['status']
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
$session_time = $login_ts ? round((time() - strtotime($login_ts)) / 60) : null;

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
  // find any reservation for today that is reserved/approved and ends in the future to show countdown
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
  <link rel="stylesheet" href="/css/studentdashboard.css">
</head>
<body class="bg-gray-100 font-sans min-h-screen flex">

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

    <!-- Clock and Notification -->
    <div class="flex items-center gap-4 relative">
      <div id="clock" class="text-lg font-mono text-gray-800"></div>
      <button id="notif-btn" class="relative text-gray-600 hover:text-blue-700">
        <i class="fas fa-bell text-xl"></i>
        <?php if (!empty($notifications)): ?>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full animate-ping"></span>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
        <?php endif; ?>
      </button>
    </div>
  </div>

  <!-- Notifications Dropdown -->
  <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-72 bg-white border rounded shadow-md z-50">
    <div class="p-3 font-semibold text-sm text-gray-700 border-b">Notifications</div>
    <ul class="max-h-64 overflow-y-auto text-sm">
      <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $notif): ?>
          <li class="p-3 border-b hover:bg-gray-100">
            <?= htmlspecialchars($notif['message']) ?><br>
            <small class="text-gray-500">
              <?= date('M d, h:i A', strtotime($notif['created_at'])) ?>
            </small>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li class="p-3 text-gray-500">No notifications</li>
      <?php endif; ?>
    </ul>
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
    <div id="pc-status-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- JS will populate this -->
    </div>
    <div class="flex mt-4">
      <button id="refresh-pc-status" class="ml-auto px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">
        <i class="fas fa-sync-alt"></i> Refresh Status
      </button>
    </div>
  </section>

  <!-- Modal -->
  <div id="pcModal" class="fixed inset-0 hidden bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white w-full max-w-3xl rounded-lg shadow-lg overflow-y-auto max-h-[80vh]">
      <div class="flex justify-between items-center p-4 border-b">
        <h3 id="modalLabName" class="text-xl font-bold text-blue-800">Lab Name</h3>
        <button id="closeModal" class="text-gray-500 hover:text-red-600 text-2xl font-bold">&times;</button>
      </div>
      <div id="modalPcList" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-4">
        <!-- PCs will be injected here -->
      </div>
    </div>
  </div>

  <!-- RESERVE PC -->
  <section id="reserve" class="section hidden">
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-2xl font-bold mb-4 text-blue-800">Reserve a PC</h2>

      <!-- Reservation Form -->
      <form method="POST" class="space-y-6">
        <!-- Lab Selection -->
        <div>
          <label for="lab-select" class="block mb-1 font-medium text-gray-700">Select Laboratory</label>
          <select id="lab-select" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none transition" required>
            <option value="">-- Choose Lab --</option>
            <?php foreach ($grouped_pcs as $lab => $pcs): ?>
              <option value="<?= htmlspecialchars($lab) ?>">
                <?= htmlspecialchars($lab) ?> (<?= count($pcs) ?> <?= count($pcs) === 1 ? 'PC' : 'PCs' ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- PC Boxes -->
        <div id="pc-boxes" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 hidden">
          <!-- JS renders PCs here -->
        </div>

        <!-- Date & Time Selection -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
          <div>
            <label for="reservation_date" class="block mb-1 font-medium text-gray-700">Reservation Date</label>
            <input type="date" name="reservation_date" id="reservation_date"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-400 focus:outline-none"
                  required min="<?= date('Y-m-d') ?>" disabled>
            <small id="date-warning" class="text-sm text-red-600 hidden"></small>
          </div>
          <div>
            <label for="reservation_time_start" class="block mb-1 font-medium text-gray-700">Start Time</label>
            <input type="time" name="reservation_time_start" id="reservation_time_start"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-400 focus:outline-none"
                  required disabled>
          </div>
          <div>
            <label for="reservation_time_end" class="block mb-1 font-medium text-gray-700">End Time</label>
            <input type="time" name="reservation_time_end" id="reservation_time_end"
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-400 focus:outline-none"
                  required disabled>
          </div>
        </div>

        <!-- Submit -->
        <input type="hidden" name="reserve_pc_id" id="reserve_pc_id" />
        <div class="text-right mt-6">
          <button type="submit"
                  class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-6 py-2 rounded-lg font-medium hover:from-blue-600 hover:to-blue-800 transition shadow-lg">
            <i class="fas fa-calendar-check mr-2"></i>Reserve PC
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
                  $countdownAttr = '';
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

  <!-- Request form -->
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

  <!-- My assistance requests -->
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
// --- For advanced reservation slot checking (date+time) ---
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

<!-- Your main JS files -->
<script src="/iLab/js/studentdashboard.js"></script>
<script src="/iLab/js/pcstatus_realtime.js"></script>

<!-- Scripts for sidebar toggle, notifications, nav, clock, timers, etc. -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = (id) => document.getElementById(id);

  const sidebar        = $('sidebar');
  const sidebarOverlay = $('sidebarOverlay');
  const toggleBtn      = $('toggleSidebar');
  const notifBtn       = $('notif-btn');
  const notifDropdown  = $('notif-dropdown');

  // Sidebar toggle (mobile)
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

  // Notifications dropdown
document.addEventListener('DOMContentLoaded', () => {
  const notifBtn = document.getElementById('notif-btn');
  const notifDropdown = document.getElementById('notif-dropdown');

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
  }
});

  // Clock
  function updateClock() {
    const now = new Date();
    const el = $('clock');
    if (el) {
      el.textContent = now.toLocaleString();
    }
  }
  updateClock();
  setInterval(updateClock, 1000);

  // Nav Sections
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

  // Flash message auto-hide
  setTimeout(() => {
    const fm = $('flash-message');
    if (fm) fm.remove();
  }, 5000);

  // Live session duration (student top header)
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

  // Live duration (My Logs)
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

  // Format seconds as h/m
  function fmtSeconds(s) {
    if (s < 0) return "0m";
    const m = Math.floor(s / 60);
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return (h > 0 ? h + "h " : "") + mm + "m";
  }

  // Countdown timers (per reservation row)
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
    const loop = () => {
      remain--;
      if (remain <= 0) {
        cEl.textContent = "Finished";
        clearInterval(h);
      } else {
        cEl.textContent = fmtSeconds(remain);
      }
    };
    cEl.textContent = fmtSeconds(remain);
    const h = setInterval(loop, 1000);
  }
});
</script>
</body>
</html>
