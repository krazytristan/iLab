<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit();
}
require_once 'db.php';
if (isset($_POST['reservation_action'])) {
  $res_id = $_POST['res_id'];
  $action = $_POST['reservation_action'];

  // Fetch reservation info
  $stmt = $conn->prepare("SELECT pc_id, student_id FROM pc_reservations WHERE id = ? AND status = 'reserved'");
  $stmt->bind_param("i", $res_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();

  if ($res) {
    $pc_id = $res['pc_id'];
    $student_id = $res['student_id'];

    if ($action === 'approve') {
      // 1. Set PC status to in_use
      $conn->query("UPDATE pcs SET status = 'in_use' WHERE id = $pc_id");

      // 2. Insert to lab_sessions
      $conn->query("INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time) VALUES ('student', $student_id, $pc_id, 'active', NOW())");

      // 3. Update reservation status
      $conn->query("UPDATE pc_reservations SET status = 'approved' WHERE id = $res_id");

      // 4. Notify student
      $msg = "✅ Your PC reservation (PC #$pc_id) has been approved.";
      $notif_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'student', ?)");
      $notif_stmt->bind_param("is", $student_id, $msg);
      $notif_stmt->execute();

    } elseif ($action === 'cancel') {
      $conn->query("UPDATE pc_reservations SET status = 'cancelled' WHERE id = $res_id");

      $msg = "❌ Your PC reservation (PC #$pc_id) was cancelled by the admin.";
      $notif_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'student', ?)");
      $notif_stmt->bind_param("is", $student_id, $msg);
      $notif_stmt->execute();
    }
  }
  header("Location: admin_dashboard.php#reservations");
  exit();
}
// Summary Cards
$totalPCs = $conn->query("SELECT COUNT(*) AS total FROM pcs")->fetch_assoc()['total'] ?? 40;
$pcUsed = $conn->query("SELECT COUNT(*) AS used FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['used'] ?? 0;
$pendingMaintenance = $conn->query("SELECT COUNT(*) AS pending FROM maintenance_requests WHERE status = 'pending'")->fetch_assoc()['pending'] ?? 0;
$activeSessions = $conn->query("SELECT COUNT(*) AS active FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['active'] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'] ?? 0;
$totalFaculty = $conn->query("SELECT COUNT(*) AS count FROM faculty")->fetch_assoc()['count'] ?? 0;
$totalReservations = $conn->query("SELECT COUNT(*) AS total FROM pc_reservations WHERE status = 'reserved'")->fetch_assoc()['total'] ?? 0;

// Activities
$recentActivities = $conn->query("SELECT action, user, pc_no, timestamp FROM lab_activities ORDER BY timestamp DESC LIMIT 5");

// Reservations
$reservations = $conn->query("
  SELECT r.id, s.fullname, p.pc_name, r.reservation_time, r.status 
  FROM pc_reservations r
  JOIN students s ON r.student_id = s.id
  JOIN pcs p ON r.pc_id = p.id
  ORDER BY r.reservation_time DESC LIMIT 10
");

// UserLogs
$userLogs = $conn->query("
  SELECT u.fullname, l.action, l.pc_no, l.timestamp 
  FROM user_logs l 
  JOIN students u ON l.user_id = u.id 
  ORDER BY l.timestamp DESC LIMIT 50
");

// Student Requests
$studentRequests = $conn->query("
  SELECT r.id, s.fullname, r.subject, r.message, r.status, r.created_at
  FROM student_requests r
  JOIN students s ON r.student_id = s.id
  ORDER BY r.created_at DESC
");

// Reports Section
$reports = $conn->query("SELECT r.id, r.report_type, r.details, r.created_at, s.fullname AS student
                          FROM reports r
                          LEFT JOIN students s ON r.student_id = s.id
                          ORDER BY r.created_at DESC");

// Maintenance Section
$maintenanceList = $conn->query("SELECT m.id, m.pc_id, p.pc_name, m.issue, m.status, m.created_at
                                  FROM maintenance_requests m
                                  JOIN pcs p ON m.pc_id = p.id
                                  ORDER BY m.created_at DESC");

// Settings Section (Placeholder)
$labSettingsResult = $conn->query("SELECT * FROM lab_settings LIMIT 1");
$labSettings = $labSettingsResult->fetch_assoc();

// Notifications
$notifications_sql = "
  SELECT n.message, n.created_at, s.fullname
  FROM notifications n
  LEFT JOIN students s ON n.recipient_id = s.id
  WHERE n.recipient_type = 'admin'
  ORDER BY n.created_at DESC
";

$notifications = $conn->query($notifications_sql);
$newNotiCount = $notifications->num_rows ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - iLab System</title>
  <link rel="stylesheet" href="/ilab/css/admindashboard.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
</head>
<body class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-white flex">

<!-- Sidebar -->
<aside id="sidebar" class="sidebar lg:static lg:w-64 bg-white dark:bg-gray-800 p-4 transition-all">
  <div class="logo text-center mb-6">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="w-16 mx-auto mb-2" alt="Admin Icon" />
    <h1 class="text-xl font-bold">Lab Admin</h1>
  </div>
  <nav class="menu flex flex-col gap-2">
    <a href="#" class="active" data-link="dashboard"><i class="fas fa-desktop mr-2"></i>Dashboard</a>
    <a href="#" data-link="userlogs"><i class="fas fa-users mr-2"></i>User Logs</a>
    <a href="#" data-link="requests"><i class="fas fa-inbox mr-2"></i>Student Requests</a>
    <a href="#" data-link="reservations"><i class="fas fa-calendar-check mr-2"></i>Reservations</a>
    <a href="#" data-link="reports"><i class="fas fa-file-alt mr-2"></i>Reports</a>
    <a href="#" data-link="maintenance"><i class="fas fa-tools mr-2"></i>Maintenance</a>
    <a href="#" data-link="settings"><i class="fas fa-cog mr-2"></i>Lab Settings</a>
  </nav>
</aside>

<!-- Main Content -->
<main class="main flex flex-col w-full">
  <header class="main-header flex justify-between items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700 relative">
    <div>
      <h2 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($_SESSION['admin']); ?></h2>
      <p>System check as of <span id="date"></span></p>
    </div>
    <div class="flex gap-4 items-center relative">
      <div class="relative">
        <button id="notifToggle" class="relative text-xl">
          <i class="fas fa-bell"></i>
          <?php if ($newNotiCount > 0): ?>
            <span class="absolute -top-1 -right-2 text-xs bg-red-600 text-white rounded-full px-1"><?= $newNotiCount ?></span>
          <?php endif; ?>
        </button>
        <div id="notifDropdown" class="notification-dropdown hidden absolute mt-2 right-0 bg-white dark:bg-gray-800 text-sm rounded shadow-lg">
          <ul class="divide-y divide-gray-300 dark:divide-gray-600">
            <?php if ($notifications->num_rows > 0): ?>
              <?php while($n = $notifications->fetch_assoc()): ?>
                <li class="p-3 hover:bg-gray-100 dark:hover:bg-gray-700">
                  <p><strong><?= htmlspecialchars($n['fullname']) ?></strong>: <?= htmlspecialchars($n['message']) ?></p>
                  <small class="text-gray-500 dark:text-gray-400 block"><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></small>
                </li>
              <?php endwhile; ?>
            <?php else: ?>
              <li class="p-3">No notifications</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <button id="toggleSidebar" class="px-3 py-2 bg-gray-200 dark:bg-gray-700 rounded lg:hidden">
        <i class="fas fa-bars"></i>
      </button>

      <button id="darkModeToggle" class="dark-toggle flex items-center gap-2 transition-all">
        <i id="darkIcon" class="fas fa-moon"></i>
        <span id="darkLabel" class="hidden sm:inline">Dark Mode</span>
      </button>

      <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded flex items-center gap-2">
        <i class="fas fa-sign-out-alt"></i>
        <span class="hidden sm:inline">Logout</span>
      </a>
    </div>
  </header>

  <!-- Dashboard -->
  <div class="content px-6 pb-6">
    <section id="dashboard" class="section active">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
      <!-- PCs in Use -->
      <div class="flex items-center gap-4 p-4 bg-indigo-50 border-l-4 border-indigo-500 rounded shadow">
        <i class="fas fa-plug text-2xl text-indigo-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= "$pcUsed / $totalPCs" ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">PCs in Use</p>
        </div>
      </div>

      <!-- Pending Maintenance -->
      <div class="flex items-center gap-4 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded shadow">
        <i class="fas fa-exclamation-triangle text-2xl text-yellow-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= $pendingMaintenance ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">Pending Maintenance</p>
        </div>
      </div>

      <!-- Active Sessions -->
      <div class="flex items-center gap-4 p-4 bg-purple-50 border-l-4 border-purple-500 rounded shadow">
        <i class="fas fa-clock text-2xl text-purple-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= $activeSessions ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">Active Sessions</p>
        </div>
      </div>

      <!-- Total Students -->
      <div class="flex items-center gap-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded shadow">
        <i class="fas fa-user-graduate text-2xl text-blue-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= $totalStudents ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">Total Students</p>
        </div>
      </div>

      <!-- Total Faculty -->
      <div class="flex items-center gap-4 p-4 bg-pink-50 border-l-4 border-pink-500 rounded shadow">
        <i class="fas fa-chalkboard-teacher text-2xl text-pink-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= $totalFaculty ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">Total Faculty</p>
        </div>
      </div>

      <!-- PC Reservations -->
      <div class="flex items-center gap-4 p-4 bg-green-50 border-l-4 border-green-500 rounded shadow">
        <i class="fas fa-calendar-check text-2xl text-green-600"></i>
        <div>
          <h3 class="text-lg font-semibold"><?= "$totalReservations / $totalPCs" ?></h3>
          <p class="text-sm text-gray-700 dark:text-gray-300">PC Reservations</p>
        </div>
      </div>


      <div class="activity">
        <h3 class="text-lg font-semibold mb-2">Recent Lab Activities</h3>
        <ul class="space-y-2">
          <?php if ($recentActivities->num_rows > 0): ?>
            <?php while($a = $recentActivities->fetch_assoc()): ?>
              <li>
                <i class="fas <?= $a['action'] === 'login' ? 'fa-sign-in-alt text-green-500' : ($a['action'] === 'logout' ? 'fa-sign-out-alt text-red-500' : 'fa-tools text-yellow-500') ?>"></i>
                <?= htmlspecialchars($a['user']) ?> <strong><?= strtoupper($a['action']) ?></strong> on PC <?= $a['pc_no'] ?> - <span><?= date('M d, Y h:i A', strtotime($a['timestamp'])) ?></span>
              </li>
            <?php endwhile; ?>
          <?php else: ?>
            <li>No recent activities.</li>
          <?php endif; ?>
        </ul>
      </div>
    </section>
    
<section id="reservations" class="section">
  <h3 class="text-lg font-semibold mb-4">Latest PC Reservations</h3>
  <div class="flex justify-between items-center mb-3">
    <input type="text" id="resSearch" placeholder="Search..." class="border px-2 py-1 rounded w-1/2">
    <a href="export_reservations.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>
  <table class="w-full border border-gray-300 dark:border-gray-700" id="resTable">
    <thead class="bg-gray-200 dark:bg-gray-800">
      <tr>
        <th class="px-4 py-2 text-left">Student</th>
        <th class="px-4 py-2 text-left">PC</th>
        <th class="px-4 py-2 text-left">Time</th>
        <th class="px-4 py-2 text-left">Status</th>
        <th class="px-4 py-2 text-left">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while($r = $reservations->fetch_assoc()): ?>
        <tr class="border-t border-gray-300 dark:border-gray-700" data-row="<?= strtolower($r['fullname'] . ' ' . $r['pc_name']) ?>">
          <td class="px-4 py-2"><?= htmlspecialchars($r['fullname']) ?></td>
          <td class="px-4 py-2"><?= htmlspecialchars($r['pc_name']) ?></td>
          <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($r['reservation_time'])) ?></td>
          <td class="px-4 py-2 status"><?= ucfirst($r['status']) ?></td>
          <td class="px-4 py-2">
            <?php if (in_array($r['status'], ['reserved', 'pending'])): ?>
              <button data-id="<?= $r['id'] ?>" data-action="approve" class="res-action bg-blue-600 text-white px-2 py-1 rounded mr-1 hover:bg-blue-700">Approve</button>
              <button data-id="<?= $r['id'] ?>" data-action="cancel" class="res-action bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">Cancel</button>
            <?php else: ?>
              <span class="text-gray-400 italic">N/A</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</section>

<!-- Modal Template -->
<div id="confirmationModal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-50 z-50">
  <div class="bg-white p-6 rounded shadow-xl w-full max-w-md">
    <p class="mb-4 text-gray-800">Are you sure you want to <span id="modalAction" class="font-bold"></span> this reservation?</p>
    <div class="flex justify-end space-x-2">
      <button id="cancelModalBtn" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
      <button id="confirmModalBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Confirm</button>
    </div>
  </div>
</div>

        <!-- User Logs -->
        <section id="userlogs" class="section">
          <h3 class="text-lg font-semibold mb-4">User Logs</h3>
          <div class="flex justify-between items-center mb-3">
            <input type="text" id="logSearch" placeholder="Search logs..." class="border px-2 py-1 rounded w-1/2">
            <a href="export_user_logs.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
              <i class="fas fa-file-csv"></i> Export CSV
            </a>
          </div>
          <table class="w-full border border-gray-300 dark:border-gray-700" id="logTable">
            <thead class="bg-gray-200 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-2 text-left">User</th>
                <th class="px-4 py-2 text-left">Action</th>
                <th class="px-4 py-2 text-left">PC No</th>
                <th class="px-4 py-2 text-left">Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($userLogs && $userLogs->num_rows > 0): ?>
                <?php while ($log = $userLogs->fetch_assoc()): ?>
                  <tr class="border-t border-gray-300 dark:border-gray-700" data-log="<?= strtolower($log['fullname'] . ' ' . $log['action'] . ' ' . $log['pc_no']) ?>">
                    <td class="px-4 py-2"><?= htmlspecialchars($log['fullname']) ?></td>
                    <td class="px-4 py-2">
                      <span class="inline-block px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">
                        <?= strtoupper($log['action']) ?>
                      </span>
                    </td>
                    <td class="px-4 py-2"><?= htmlspecialchars($log['pc_no']) ?></td>
                    <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($log['timestamp'])) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="4" class="px-4 py-3 text-center text-gray-500">No user logs available.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </section>

   <section id="requests" class="section">
  <h3 class="text-lg font-semibold mb-4">Student Requests</h3>
  <?php if ($studentRequests->num_rows > 0): ?>
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-300 dark:border-gray-700 text-sm">
        <thead class="bg-gray-200 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-2 text-left">Student</th>
            <th class="px-4 py-2 text-left">Subject</th>
            <th class="px-4 py-2 text-left">Message</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-left">Date</th>
            <th class="px-4 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($req = $studentRequests->fetch_assoc()): ?>
            <tr class="border-t border-gray-300 dark:border-gray-700">
              <td class="px-4 py-2"><?= htmlspecialchars($req['fullname']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($req['subject']) ?></td>
              <td class="px-4 py-2"><?= nl2br(htmlspecialchars($req['message'])) ?></td>
              <td class="px-4 py-2"><?= ucfirst($req['status']) ?></td>
              <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($req['created_at'])) ?></td>
              <td class="px-4 py-2">
                <?php if ($req['status'] === 'pending'): ?>
                  <button data-id="<?= $req['id'] ?>" data-action="approve" class="req-action bg-blue-600 text-white px-2 py-1 rounded mr-1 hover:bg-blue-700">Approve</button>
                  <button data-id="<?= $req['id'] ?>" data-action="reject" class="req-action bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">Reject</button>
                <?php else: ?>
                  <span class="italic text-gray-400">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="text-gray-600 dark:text-gray-400">No student requests found.</p>
  <?php endif; ?>
</section>

      <!-- ==== REPORTS SECTION ==== -->
    <section id="reports" class="section">
      <h3 class="text-lg font-semibold mb-4">System Reports</h3>
      <?php if ($reports->num_rows > 0): ?>
        <table class="w-full text-sm border border-gray-300 dark:border-gray-700">
          <thead class="bg-gray-200 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-left">Details</th>
              <th class="px-4 py-2 text-left">Student</th>
              <th class="px-4 py-2 text-left">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($rep = $reports->fetch_assoc()): ?>
              <tr class="border-t border-gray-300 dark:border-gray-700">
                <td class="px-4 py-2"><?= htmlspecialchars($rep['report_type']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($rep['details']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($rep['student']) ?></td>
                <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($rep['created_at'])) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-600 dark:text-gray-400">No reports available.</p>
      <?php endif; ?>
    </section>

    <!-- ==== MAINTENANCE SECTION ==== -->
    <section id="maintenance" class="section">
      <h3 class="text-lg font-semibold mb-4">PC Maintenance Requests</h3>
      <?php if ($maintenanceList->num_rows > 0): ?>
        <table class="w-full text-sm border border-gray-300 dark:border-gray-700">
          <thead class="bg-gray-200 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-2 text-left">PC Name</th>
              <th class="px-4 py-2 text-left">Issue</th>
              <th class="px-4 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-left">Reported At</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($m = $maintenanceList->fetch_assoc()): ?>
              <tr class="border-t border-gray-300 dark:border-gray-700">
                <td class="px-4 py-2"><?= htmlspecialchars($m['pc_name']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($m['issue']) ?></td>
                <td class="px-4 py-2"><?= ucfirst($m['status']) ?></td>
                <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-600 dark:text-gray-400">No maintenance records.</p>
      <?php endif; ?>
    </section>

    <!-- ==== LAB SETTINGS SECTION ==== -->
    <section id="settings" class="section">
      <h3 class="text-lg font-semibold mb-4">Lab Settings</h3>
      <form action="update_lab_settings.php" method="POST" class="space-y-4 max-w-md">
        <div>
          <label class="block font-semibold">Lab Name</label>
          <input type="text" name="lab_name" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($labSettings['lab_name']) ?>">
        </div>
        <div>
          <label class="block font-semibold">Default Session Length</label>
          <input type="text" name="default_session_length" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($labSettings['default_session_length']) ?>">
        </div>
        <div>
          <label class="block font-semibold">Max Reservations per Student</label>
          <input type="number" name="max_reservations" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($labSettings['max_reservations']) ?>">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Settings</button>
      </form>
    </section>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  // === Date Display ===
  const dateEl = document.getElementById("date");
  if (dateEl) {
    dateEl.textContent = new Date().toLocaleDateString("en-US", {
      weekday: "long", year: "numeric", month: "long", day: "numeric"
    });
  }

  // === Sidebar Toggle ===
  const toggleSidebar = document.getElementById("toggleSidebar");
  const sidebar = document.getElementById("sidebar");
  if (toggleSidebar && sidebar) {
    toggleSidebar.addEventListener("click", () => {
      sidebar.classList.toggle("mobile-visible");

      if (!document.getElementById("sidebarOverlay")) {
        const overlay = document.createElement("div");
        overlay.id = "sidebarOverlay";
        overlay.className = "fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden";
        overlay.onclick = () => {
          sidebar.classList.remove("mobile-visible");
          overlay.remove();
        };
        document.body.appendChild(overlay);
      } else {
        document.getElementById("sidebarOverlay").remove();
      }
    });
  }

  // === Dark Mode Toggle ===
  const darkToggle = document.getElementById("darkModeToggle");
  const darkIcon = document.getElementById("darkIcon");
  const darkLabel = document.getElementById("darkLabel");

  const enableDarkMode = () => {
    document.body.classList.add("dark");
    darkIcon.classList.replace("fa-moon", "fa-sun");
    darkLabel.textContent = "Light Mode";
    localStorage.setItem("darkMode", "enabled");
  };

  const disableDarkMode = () => {
    document.body.classList.remove("dark");
    darkIcon.classList.replace("fa-sun", "fa-moon");
    darkLabel.textContent = "Dark Mode";
    localStorage.setItem("darkMode", "disabled");
  };

  if (localStorage.getItem("darkMode") === "enabled") enableDarkMode();

  darkToggle?.addEventListener("click", () => {
    document.body.classList.contains("dark") ? disableDarkMode() : enableDarkMode();
  });

  // === Sidebar Navigation ===
  document.querySelectorAll(".menu a[data-link]").forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const target = link.getAttribute("data-link");

      document.querySelectorAll(".menu a").forEach(l => l.classList.remove("active"));
      link.classList.add("active");

      document.querySelectorAll(".section").forEach(section => {
        section.classList.remove("active");
        section.style.display = 'none';
        if (section.id === target) {
          section.classList.add("active");
          section.style.display = 'block';
        }
      });
    });
  });

  // === Notification Toggle ===
  const notifToggle = document.getElementById("notifToggle");
  const notifDropdown = document.getElementById("notifDropdown");

  notifToggle?.addEventListener("click", (e) => {
    e.stopPropagation();
    notifDropdown?.classList.toggle("hidden");
  });

  document.addEventListener("click", function (event) {
    if (!notifToggle.contains(event.target) && !notifDropdown.contains(event.target)) {
      notifDropdown?.classList.add("hidden");
    }
  });

  // === Reservation Search Filter ===
  const resSearch = document.getElementById('resSearch');
  if (resSearch) {
    resSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#resTable tbody tr').forEach(row => {
        row.style.display = row.dataset.row.includes(term) ? '' : 'none';
      });
    });
  }

    // === User Log Search ===
  const logSearch = document.getElementById('logSearch');
  if (logSearch) {
    logSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#logTable tbody tr').forEach(row => {
        row.style.display = row.dataset.log.includes(term) ? '' : 'none';
      });
    });
  }

  // === Approve/Cancel Reservation Buttons ===
  document.querySelectorAll('.res-action').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const action = button.dataset.action;

      button.disabled = true;
      fetch('reservation_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&action=${action}`
      })
      .then(res => res.json())
      .then(data => {
        Swal.fire({
          icon: data.success ? 'success' : 'error',
          title: data.success ? 'Success' : 'Error',
          text: data.msg,
          confirmButtonColor: '#2563eb'
        }).then(() => {
          if (data.success) location.reload();
          else button.disabled = false;
        });
      })
      .catch(() => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Something went wrong. Please try again.',
        });
        button.disabled = false;
      });
    });
  });
});
</script>
