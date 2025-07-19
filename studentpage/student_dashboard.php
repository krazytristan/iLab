<?php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

$student_id = $_SESSION['student_id'];

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// ============================
// 1. Handle "Start Session" if student clicks the button
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session'])) {
    $reservation_id = $_POST['start_reservation_id'];
    $pc_id = $_POST['start_pc_id'];

    // Double check reservation is still valid for now
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
        // 1. Insert active session
        $stmt = $conn->prepare("INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time) VALUES ('student', ?, ?, 'active', NOW())");
        $stmt->bind_param("ii", $student_id, $pc_id);
        $stmt->execute();

        // 2. Set PC to in_use
        $stmt2 = $conn->prepare("UPDATE pcs SET status = 'in_use' WHERE id = ?");
        $stmt2->bind_param("i", $pc_id);
        $stmt2->execute();

        // 3. Set reservation to 'reserved'
        $stmt3 = $conn->prepare("UPDATE pc_reservations SET status = 'reserved' WHERE id = ?");
        $stmt3->bind_param("i", $reservation_id);
        $stmt3->execute();

        $_SESSION['flash_message'] = "✅ Session started. Enjoy your reserved PC!";
    } else {
        $_SESSION['flash_message'] = "❌ Session could not be started. Please check your reservation time.";
    }
    header("Location: student_dashboard.php");
    exit();
}

// ============================
// 2. Handle Time Out (ends session)
// ============================
if (isset($_POST['time_out'])) {
  // 1. End the session
  $timeout_stmt = $conn->prepare("UPDATE lab_sessions SET status = 'inactive', logout_time = NOW() WHERE user_type='student' AND user_id = ? AND status = 'active'");
  $timeout_stmt->bind_param("i", $student_id);
  $timeout_stmt->execute();

  // 2. Find the last PC used by this student (their latest session)
  $find_pc = $conn->prepare("SELECT pc_id FROM lab_sessions WHERE user_type='student' AND user_id = ? ORDER BY login_time DESC LIMIT 1");
  $find_pc->bind_param("i", $student_id);
  $find_pc->execute();
  $res = $find_pc->get_result()->fetch_assoc();

  if ($res && isset($res['pc_id'])) {
    $used_pc_id = $res['pc_id'];

    // 3. Update the PC status back to available
    $update_pc = $conn->prepare("UPDATE pcs SET status = 'available' WHERE id = ?");
    $update_pc->bind_param("i", $used_pc_id);
    $update_pc->execute();

    // 4. OPTIONAL: Mark the corresponding reservation as completed
    $update_res = $conn->prepare("UPDATE pc_reservations SET status = 'completed' WHERE pc_id = ? AND student_id = ? AND status IN ('approved', 'reserved')");
    $update_res->bind_param("ii", $used_pc_id, $student_id);
    $update_res->execute();
  }

  $_SESSION['flash_message'] = "✅ You have logged out from the session.";
  header("Location: student_dashboard.php");
  exit();
}

// ============================
// 3. Handle assistance request
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_description'])) {
  $desc = trim($_POST['help_description']);
  $pc_id = $_POST['help_pc_id'];
  $request_stmt = $conn->prepare("INSERT INTO maintenance_requests (pc_id, issue, status, created_at) VALUES (?, ?, 'pending', NOW())");
  $request_stmt->bind_param("is", $pc_id, $desc);
  $request_stmt->execute();

  // Notify admin
  $admin_notif = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'admin', ?)");
  $admin_message = "Student requested assistance on PC #$pc_id.";
  $admin_id = 1;
  $admin_notif->bind_param("is", $admin_id, $admin_message);
  $admin_notif->execute();

  $_SESSION['flash_message'] = "✅ Assistance request submitted!";
  header("Location: student_dashboard.php#request");
  exit();
}

// ============================
// 4. Handle PC reservation request (pending approval)
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_pc_id'])) {
  $reserve_pc_id = $_POST['reserve_pc_id'];
  $reservation_date = $_POST['reservation_date'] ?? null;
  $time_start = $_POST['reservation_time_start'] ?? null;
  $time_end = $_POST['reservation_time_end'] ?? null;

  // Check if already reserved or in use
  $check_stmt = $conn->prepare("SELECT status FROM pcs WHERE id = ?");
  $check_stmt->bind_param("i", $reserve_pc_id);
  $check_stmt->execute();
  $status = $check_stmt->get_result()->fetch_assoc()['status'];

  if ($status === 'available') {
    // Insert pending reservation with date & time
    $reserve_stmt = $conn->prepare(
      "INSERT INTO pc_reservations (student_id, pc_id, reservation_date, time_start, time_end, status, created_at)
      VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
    );
    $reserve_stmt->bind_param("iisss", $student_id, $reserve_pc_id, $reservation_date, $time_start, $time_end);
    $reserve_stmt->execute();

    // Notify admin
    $admin_id = 1;
    $admin_notif = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'admin', ?)");
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

// ============================
// 5. Fetch student info and PC info
// ============================
$student_stmt = $conn->prepare("SELECT fullname, email FROM students WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// All PCs
$pcsData = [];
$pcsResult = $conn->query("SELECT id, pc_name, status FROM pcs");
while ($row = $pcsResult->fetch_assoc()) {
  $pcsData[] = $row;
}

// Group PCs by Lab
$grouped_pcs = [];
foreach ($pcsData as $pc) {
  preg_match('/^(.*?)-PC-(\d+)$/', $pc['pc_name'], $matches);
  $labGroup = isset($matches[1]) && $matches[1] !== '' ? $matches[1] : 'Other';
  $grouped_pcs[$labGroup][] = $pc;
}

// Reserved Dates Per PC
$reservedDatesPerPC = [];
$reservedDatesSQL = $conn->query("SELECT pc_id, reservation_date FROM pc_reservations WHERE status IN ('pending','reserved','approved','completed')");
while ($row = $reservedDatesSQL->fetch_assoc()) {
  if ($row['reservation_date']) {
    $reservedDatesPerPC[$row['pc_id']][] = $row['reservation_date'];
  }
}

// ============================
// 6. Check if the student has an active session
// ============================
$active_stmt = $conn->prepare("SELECT pc_id, login_time FROM lab_sessions WHERE user_type='student' AND user_id = ? AND status = 'active' ORDER BY login_time DESC LIMIT 1");
$active_stmt->bind_param("i", $student_id);
$active_stmt->execute();
$sessionResult = $active_stmt->get_result()->fetch_assoc();

$session_status = $sessionResult ? 'Active' : 'Not in Session';
$assigned_pc = ($sessionResult && isset($sessionResult['pc_id'])) ? 'PC #' . $sessionResult['pc_id'] : '-';
$session_time = ($sessionResult && isset($sessionResult['login_time']))
    ? round((time() - strtotime($sessionResult['login_time'])) / 60) . ' minutes'
    : '-';

// ============================
// 7. Find upcoming reservation ready to start (for this student)
// ============================
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

// ============================
// 8. Recent logs
// ============================
$log_stmt = $conn->prepare("SELECT pcs.pc_name, ls.login_time, ls.logout_time, ls.status FROM lab_sessions ls JOIN pcs ON pcs.id = ls.pc_id WHERE ls.user_type='student' AND ls.user_id = ? ORDER BY ls.login_time DESC LIMIT 10");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$logs = $log_stmt->get_result();

// ============================
// 9. Notifications
// ============================
$query = "SELECT id, message, is_read, created_at FROM notifications WHERE recipient_type = 'student' AND recipient_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
  $notifications[] = $row;
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

<!-- SIDEBAR (unchanged) -->
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
        'pcstatus' => ['fa-desktop', 'PC Status'],
        'reserve' => ['fa-calendar-check', 'Reserve PC'],
        'request' => ['fa-tools', 'Request Assistance'],
        'mylogs' => ['fa-history', 'My Logs'],
        'settings' => ['fa-cog', 'Settings'],
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

  <!-- Notifications Dropdown (unchanged) -->
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
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
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
        <p><?= $session_time ?></p>
      </div>
    </div>

    <!-- NEW: Start Session button if eligible -->
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
  <h2 class="text-2xl font-semibold mb-6 text-gray-800">Reserve a PC</h2>
  <form method="POST" class="space-y-6">
    <div>
      <label class="block mb-1 font-medium">Select Laboratory</label>
      <select id="lab-select" class="w-full px-3 py-2 border rounded" required>
        <option value="">-- Choose Lab --</option>
        <?php foreach ($grouped_pcs as $lab => $pcs): ?>
          <option value="<?= htmlspecialchars($lab) ?>"><?= htmlspecialchars($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="pc-boxes" class="grid grid-cols-4 gap-3 hidden"></div>
    <div>
      <label for="reservation_date" class="block mb-1 font-medium">Reservation Date</label>
      <input
        type="date"
        name="reservation_date"
        id="reservation_date"
        class="w-full px-3 py-2 border rounded"
        required
        min="<?= date('Y-m-d') ?>"
        disabled
      >
      <small id="date-warning" class="text-red-600 hidden"></small>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="reservation_time_start" class="block mb-1 font-medium">Start Time</label>
        <input
          type="time"
          name="reservation_time_start"
          id="reservation_time_start"
          class="w-full px-3 py-2 border rounded"
          required
          disabled
        >
      </div>
      <div>
        <label for="reservation_time_end" class="block mb-1 font-medium">End Time</label>
        <input
          type="time"
          name="reservation_time_end"
          id="reservation_time_end"
          class="w-full px-3 py-2 border rounded"
          required
          disabled
        >
      </div>
    </div>
    <input type="hidden" name="reserve_pc_id" id="reserve_pc_id" />
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
      Reserve
    </button>
  </form>
</section>

<!-- REQUEST ASSISTANCE -->
<section id="request" class="section hidden">
  <h2 class="text-xl font-semibold mb-4">Request Assistance</h2>
  <form method="POST" class="space-y-4">
    <select name="help_pc_id" class="p-2 border rounded w-full" required>
      <option value="">-- Choose PC --</option>
      <?php foreach ($pcsData as $pc): ?>
        <option value="<?= $pc['id'] ?>"><?= $pc['pc_name'] ?></option>
      <?php endforeach; ?>
    </select>
    <textarea name="help_description" class="w-full p-2 border rounded" placeholder="Describe the issue..." required></textarea>
    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Submit Request</button>
  </form>
</section>

<!-- LOGS -->
<section id="mylogs" class="section hidden">
  <h2 class="text-xl font-semibold mb-4">My Logs</h2>
  <table class="w-full border-collapse">
    <thead>
      <tr class="bg-gray-200">
        <th class="border px-4 py-2">PC</th>
        <th class="border px-4 py-2">Login</th>
        <th class="border px-4 py-2">Logout</th>
        <th class="border px-4 py-2">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($log = $logs->fetch_assoc()): ?>
        <tr class="text-center">
          <td class="border px-4 py-2"><?= htmlspecialchars($log['pc_name']) ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($log['login_time']) ?></td>
          <td class="border px-4 py-2"><?= $log['logout_time'] ?? '-' ?></td>
          <td class="border px-4 py-2"><?= htmlspecialchars($log['status']) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</section>

<!-- SETTINGS -->
<section id="settings" class="section hidden">
  <h2 class="text-xl font-semibold mb-4">Settings</h2>
  <p class="mb-4">Email: <?= htmlspecialchars($student['email']) ?></p>
  <form method="POST" action="update_password.php" class="space-y-4">
    <input type="password" name="current_password" class="w-full border p-2 rounded" placeholder="Current Password" required>
    <input type="password" name="new_password" class="w-full border p-2 rounded" placeholder="New Password" required>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Password</button>
  </form>
</section>
</main>

<script>
window.groupedPCs = <?= json_encode($grouped_pcs) ?>;
window.reservedDatesPerPC = <?= json_encode($reservedDatesPerPC) ?>;
</script>
<?php
// --- For advanced reservation slot checking (date+time) ---
$reservedSlots = [];
$reservedSlotsSQL = $conn->query("SELECT pc_id, reservation_date, time_start, time_end, status FROM pc_reservations WHERE status IN ('pending','reserved','approved')");
while ($row = $reservedSlotsSQL->fetch_assoc()) {
  $reservedSlots[$row['pc_id']][] = [
    'date' => $row['reservation_date'],
    'start' => $row['time_start'],
    'end' => $row['time_end'],
    'status' => $row['status'],
  ];
}
?>
<script>
window.reservedSlots = <?= json_encode($reservedSlots) ?>;
</script>
<!-- Only include your main JS ONCE here! -->
<script src="/ilab/js/studentdashboard.js"></script>
<script src="/ilab/js/pcstatus_realtime.js"></script>
</body>
</html>

