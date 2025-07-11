<?php
session_start();
if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

require_once '../adminpage/db.php';
$student_id = $_SESSION['student_id'];

// Fetch student info
$student_stmt = $conn->prepare("SELECT fullname, email FROM students WHERE id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// Handle assistance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_description'])) {
  $desc = trim($_POST['help_description']);
  $pc_id = $_POST['help_pc_id'];
  $request_stmt = $conn->prepare("INSERT INTO maintenance_requests (pc_id, issue_description, requested_by) VALUES (?, ?, ?)");
  $request_stmt->bind_param("iss", $pc_id, $desc, $student['fullname']);
  $request_stmt->execute();
  $_SESSION['flash_message'] = "✅ Assistance request submitted!";
  header("Location: student_dashboard.php#request");
  exit();
}

// Handle PC reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_pc_id'])) {
  $reserve_pc_id = $_POST['reserve_pc_id'];
  $check_stmt = $conn->prepare("SELECT status FROM pcs WHERE id = ?");
  $check_stmt->bind_param("i", $reserve_pc_id);
  $check_stmt->execute();
  $status = $check_stmt->get_result()->fetch_assoc()['status'];

  if ($status === 'available') {
    $reserve_stmt = $conn->prepare("UPDATE pcs SET status = 'in_use' WHERE id = ?");
    $reserve_stmt->bind_param("i", $reserve_pc_id);
    $reserve_stmt->execute();

    $conn->query("INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time) VALUES ('student', $student_id, $reserve_pc_id, 'active', NOW())");

    $notif_msg = "✅ Your reservation for PC #$reserve_pc_id has been approved.";
    $notif_stmt = $conn->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)");
    $notif_stmt->bind_param("is", $student_id, $notif_msg);
    $notif_stmt->execute();

    $_SESSION['flash_message'] = $notif_msg;
  } else {
    $notif_msg = "⚠️ Your reservation request for PC #$reserve_pc_id was disapproved. PC not available.";
    $notif_stmt = $conn->prepare("INSERT INTO notifications (student_id, message) VALUES (?, ?)");
    $notif_stmt->bind_param("is", $student_id, $notif_msg);
    $notif_stmt->execute();

    $_SESSION['flash_message'] = $notif_msg;
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

// Fetch active session
$active_stmt = $conn->prepare("SELECT pc_id, login_time FROM lab_sessions WHERE user_type='student' AND user_id = ? AND status = 'active' ORDER BY login_time DESC LIMIT 1");
$active_stmt->bind_param("i", $student_id);
$active_stmt->execute();
$sessionResult = $active_stmt->get_result()->fetch_assoc();

$session_status = $sessionResult ? 'Active' : 'Not in Session';
$assigned_pc = ($sessionResult && isset($sessionResult['pc_id'])) ? 'PC #' . $sessionResult['pc_id'] : '-';
$session_time = ($sessionResult && isset($sessionResult['login_time'])) 
    ? round((time() - strtotime($sessionResult['login_time'])) / 60) . ' minutes' 
    : '-';

// Fetch PCs
$pcsData = [];
$pcsResult = $conn->query("SELECT id, pc_name, status FROM pcs");
while ($row = $pcsResult->fetch_assoc()) {
  $pcsData[] = $row;
}

// Fetch logs
$log_stmt = $conn->prepare("SELECT pcs.pc_name, ls.login_time, ls.logout_time, ls.status FROM lab_sessions ls JOIN pcs ON pcs.id = ls.pc_id WHERE ls.user_type='student' AND ls.user_id = ? ORDER BY ls.login_time DESC LIMIT 10");
$log_stmt->bind_param("i", $student_id);
$log_stmt->execute();
$logs = $log_stmt->get_result();

// Fetch notifications
$notif_stmt = $conn->prepare("SELECT id, message, created_at FROM notifications WHERE student_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $student_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// All PCs
$availablePCs = $conn->query("
  SELECT id, pc_name FROM pcs 
  WHERE id NOT IN (
    SELECT pc_id FROM pc_reservations WHERE status = 'reserved'
  ) 
  AND id NOT IN (
    SELECT pc_id FROM maintenance_requests WHERE status = 'pending'
  )
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans min-h-screen flex">

<!-- SIDEBAR -->
<aside class="w-64 bg-blue-900 text-white p-5 space-y-2 min-h-screen sticky top-0">
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
    <a href="student_login.php" class="px-3 py-2 rounded hover:bg-red-600 flex items-center gap-2"><i class="fas fa-sign-out-alt"></i> Logout</a>
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

  <div class="flex justify-between items-center relative">
    <h1 class="text-2xl font-bold">Welcome, <?= htmlspecialchars($student['fullname']) ?></h1>
    
    <!-- Clock and Notifications -->
    <div class="flex items-center gap-4 relative">
      <div id="clock" class="text-lg font-mono text-gray-800"></div>
      <button id="notif-btn" class="relative text-gray-600 hover:text-blue-700">
        <i class="fas fa-bell text-xl"></i>
        <?php if ($notifications->num_rows > 0): ?>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full animate-ping"></span>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
        <?php endif; ?>
      </button>

      <!-- Dropdown -->
      <div id="notif-dropdown" class="hidden absolute right-0 top-10 w-72 bg-white border rounded shadow-md z-50">
        <div class="p-3 font-semibold text-sm text-gray-700 border-b">Notifications</div>
        <ul class="max-h-64 overflow-y-auto text-sm">
          <?php if ($notifications->num_rows > 0): ?>
            <?php while ($notif = $notifications->fetch_assoc()): ?>
              <li class="p-3 border-b hover:bg-gray-100"><?= htmlspecialchars($notif['message']) ?><br>
                <small class="text-gray-500"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></small>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li class="p-3 text-gray-500">No notifications</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
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
    <h2 class="text-xl font-semibold mb-4">PC Status</h2>
    <ul class="space-y-2">
      <?php foreach ($pcsData as $pc): ?>
        <li class="p-2 border rounded <?= $pc['status'] === 'available' ? 'bg-green-100' : 'bg-red-100' ?>">
          <?= htmlspecialchars($pc['pc_name']) ?> - <strong><?= $pc['status'] ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- RESERVE PC -->
  <section id="reserve" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Reserve a PC</h2>
    <?php if (isset($reserve_msg)): ?>
      <p class="mb-4 text-blue-600 font-medium"><?= htmlspecialchars($reserve_msg) ?></p>
    <?php endif; ?>
    <form action="reserve_pc.php" method="POST" class="space-y-4">
      <label>Select PC to Reserve</label>
      <select name="pc_id" required class="w-full px-3 py-2 border rounded">
        <?php while ($pc = $availablePCs->fetch_assoc()): ?>
          <option value="<?= $pc['id'] ?>"><?= htmlspecialchars($pc['pc_name']) ?></option>
        <?php endwhile; ?>
      </select>

      <label>Reservation Time</label>
      <input type="datetime-local" name="reservation_time" class="w-full px-3 py-2 border rounded" required>

      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Reserve
      </button>
    </form>
  </section>

  <!-- REQUEST ASSISTANCE -->
  <section id="request" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Request Assistance</h2>
    <?php if (isset($request_msg)): ?>
      <p class="mb-4 text-green-600"><?= htmlspecialchars($request_msg) ?></p>
    <?php endif; ?>
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


<!-- Scripts -->
<script>
  function updateClock() {
    const now = new Date();
    document.getElementById("clock").textContent = now.toLocaleTimeString();
  }
  setInterval(updateClock, 1000);
  updateClock();

  // Navigation
  document.addEventListener("DOMContentLoaded", () => {
    const navLinks = document.querySelectorAll(".nav-link");
    const sections = document.querySelectorAll(".section");

    navLinks.forEach(link => {
      link.addEventListener("click", e => {
        e.preventDefault();
        const target = link.dataset.target;
        sections.forEach(sec => sec.classList.add("hidden"));
        document.getElementById(target)?.classList.remove("hidden");
        navLinks.forEach(l => l.classList.remove("bg-blue-900", "font-bold"));
        link.classList.add("bg-blue-900", "font-bold");
      });
    });

    // Flash message timeout
    const flash = document.getElementById("flash-message");
    if (flash) {
      setTimeout(() => flash.style.display = 'none', 5000);
    }

    // Notification dropdown toggle
    const notifBtn = document.getElementById("notif-btn");
    const notifDropdown = document.getElementById("notif-dropdown");
    notifBtn?.addEventListener("click", () => {
      notifDropdown.classList.toggle("hidden");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", (e) => {
      if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown?.classList.add("hidden");
      }
    });
  });
</script>
</body>
</html>