<?php
// student_dashboard.php
require_once '../includes/db.php';
session_start();

if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

$student_id = $_SESSION['student_id'];

// Fetch student info
$student_stmt = $conn->prepare("SELECT fullname, email FROM students WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// Get all PCs
$pcsData = [];
$pcsResult = $conn->query("SELECT id, pc_name, status FROM pcs");
while ($row = $pcsResult->fetch_assoc()) {
  $pcsData[] = $row;
}

// Build $grouped_pcs for lab dropdown & section
$grouped_pcs = [];
foreach ($pcsData as $pc) {
  preg_match('/^(.*?)-PC-(\d+)$/', $pc['pc_name'], $matches);
  $labGroup = isset($matches[1]) && $matches[1] !== '' ? $matches[1] : 'Other';
  $grouped_pcs[$labGroup][] = $pc;
}

// Get reserved dates for each PC (pending, reserved, approved, completed)
$reservedDatesPerPC = [];
$reservedDatesSQL = $conn->query("SELECT pc_id, reservation_date FROM pc_reservations WHERE status IN ('pending','reserved','approved','completed')");
while ($row = $reservedDatesSQL->fetch_assoc()) {
  if ($row['reservation_date']) {
    $reservedDatesPerPC[$row['pc_id']][] = $row['reservation_date'];
  }
}

// Handle assistance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_description'])) {
  $desc = trim($_POST['help_description']);
  $pc_id = $_POST['help_pc_id'];
  $request_stmt = $conn->prepare("INSERT INTO maintenance_requests (pc_id, issue, status, created_at) VALUES (?, ?, 'pending', NOW())");
  $request_stmt->bind_param("is", $pc_id, $desc);
  $request_stmt->execute();

  // Notify admin
  $admin_notif = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'admin', ?)");
  $admin_message = "Student {$student['fullname']} requested assistance on PC #$pc_id.";
  $admin_id = 1;
  $admin_notif->bind_param("is", $admin_id, $admin_message);
  $admin_notif->execute();

  $_SESSION['flash_message'] = "✅ Assistance request submitted!";
  header("Location: student_dashboard.php#request");
  exit();
}

// Handle PC reservation request (pending approval)
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
    $admin_msg = "🖥️ Student {$student['fullname']} requested to reserve PC #$reserve_pc_id for $reservation_date, $time_start to $time_end.";
    $admin_notif->bind_param("is", $admin_id, $admin_msg);
    $admin_notif->execute();

    $_SESSION['flash_message'] = "⌚ Reservation request submitted. Please wait for admin approval.";
  } else {
    $_SESSION['flash_message'] = "⚠️ Reservation failed. PC is currently unavailable.";
  }
  header("Location: student_dashboard.php#reserve");
  exit();
}

// Handle Time Out
if (isset($_POST['time_out'])) {
  $timeout_stmt = $conn->prepare("UPDATE lab_sessions SET status = 'inactive', logout_time = NOW() WHERE user_type='student' AND user_id = ? AND status = 'active'");
  $timeout_stmt->bind_param("i", $student_id);
  $timeout_stmt->execute();

  $_SESSION['flash_message'] = "✅ You have logged out from the session.";
  header("Location: student_dashboard.php");
  exit();
}

// Active session
$active_stmt = $conn->prepare("SELECT pc_id, login_time FROM lab_sessions WHERE user_type='student' AND user_id = ? AND status = 'active' ORDER BY login_time DESC LIMIT 1");
$active_stmt->bind_param("i", $student_id);
$active_stmt->execute();
$sessionResult = $active_stmt->get_result()->fetch_assoc();

$session_status = $sessionResult ? 'Active' : 'Not in Session';
$assigned_pc = ($sessionResult && isset($sessionResult['pc_id'])) ? 'PC #' . $sessionResult['pc_id'] : '-';
$session_time = ($sessionResult && isset($sessionResult['login_time']))
    ? round((time() - strtotime($sessionResult['login_time'])) / 60) . ' minutes'
    : '-';

// Recent logs
$log_stmt = $conn->prepare("SELECT pcs.pc_name, ls.login_time, ls.logout_time, ls.status FROM lab_sessions ls JOIN pcs ON pcs.id = ls.pc_id WHERE ls.user_type='student' AND ls.user_id = ? ORDER BY ls.login_time DESC LIMIT 10");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$logs = $log_stmt->get_result();

// Notifications
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

  <!-- Dropdown -->
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
<script src="/ilab/js/studentdashboard.js"></script>
<script src="/ilab/js/pcstatus_realtime.js"></script>

</body>
</html>
