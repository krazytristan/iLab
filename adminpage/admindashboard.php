<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once '../includes/db.php';

// ==== RESERVATION ACTION HANDLER ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_action'], $_POST['res_id'])) {
    $res_id = intval($_POST['res_id']);
    $action = $_POST['reservation_action'];
    $reason = $_POST['reason'] ?? null;

    // Fetch reservation info
    $stmt = $conn->prepare("SELECT pc_id, student_id FROM pc_reservations WHERE id = ?");
    $stmt->bind_param("i", $res_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $pc_id = $res['pc_id'];
        $student_id = $res['student_id'];
        $msg = "";

        if ($action === 'approve') {
            $conn->query("UPDATE pcs SET status = 'in_use' WHERE id = $pc_id");
            $conn->query("INSERT INTO lab_sessions (user_type, user_id, pc_id, status, login_time) VALUES ('student', $student_id, $pc_id, 'active', NOW())");
            $conn->query("UPDATE pc_reservations SET status = 'approved', reason = NULL, updated_at = NOW() WHERE id = $res_id");
            $msg = "✅ Your PC reservation (PC #$pc_id) has been approved.";
        }
        elseif ($action === 'reject') {
            $conn->query("UPDATE pc_reservations SET status = 'rejected', reason = '".addslashes($reason)."', updated_at = NOW() WHERE id = $res_id");
            $msg = "❌ Your PC reservation (PC #$pc_id) was rejected by the admin." . ($reason ? " Reason: $reason" : "");
        }
        elseif ($action === 'cancel') {
            $conn->query("UPDATE pc_reservations SET status = 'cancelled', reason = '".addslashes($reason)."', updated_at = NOW() WHERE id = $res_id");
            $msg = "❌ Your PC reservation (PC #$pc_id) was cancelled by the admin." . ($reason ? " Reason: $reason" : "");
        }
        elseif ($action === 'completed') {
            $conn->query("UPDATE pc_reservations SET status = 'completed', updated_at = NOW() WHERE id = $res_id");
            $msg = "✅ Your PC reservation (PC #$pc_id) has been marked as completed by the admin.";
        }

        // Notify student if needed
        if ($msg) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'student', ?)");
            $notif_stmt->bind_param("is", $student_id, $msg);
            $notif_stmt->execute();
        }
    }

    header("Location: admindashboard.php#reservations");
    exit();
}

// ==== MAINTENANCE STATUS UPDATE HANDLER ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maint_action'], $_POST['maint_id'])) {
    $mid = intval($_POST['maint_id']);
    $action = $_POST['maint_action'];
    $allowed = ['in_progress', 'completed', 'rejected', 'resolved'];

    if (in_array($action, $allowed)) {
        $updateStmt = $conn->prepare("UPDATE maintenance_requests SET status = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("si", $action, $mid);
        $updateStmt->execute();

        // Notify student if student_id exists for maintenance request
        $userInfo = $conn->query("SELECT student_id, pc_id FROM maintenance_requests WHERE id = $mid")->fetch_assoc();
        if ($userInfo && $userInfo['student_id']) {
            $pc_id = $userInfo['pc_id'];
            $msg = "🔧 Your maintenance request for PC #$pc_id has been updated to: " . ucfirst(str_replace('_', ' ', $action));
            $notif_stmt = $conn->prepare("INSERT INTO notifications (recipient_id, recipient_type, message) VALUES (?, 'student', ?)");
            $notif_stmt->bind_param("is", $userInfo['student_id'], $msg);
            $notif_stmt->execute();
        }
    }
    header("Location: admindashboard.php#maintenance");
    exit();
}

// ==== DASHBOARD DATA (SUMMARY CARDS) ====
$totalPCs = $conn->query("SELECT COUNT(*) AS total FROM pcs")->fetch_assoc()['total'] ?? 40;
$pcUsed = $conn->query("SELECT COUNT(*) AS used FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['used'] ?? 0;
$pendingMaintenance = $conn->query("SELECT COUNT(*) AS pending FROM maintenance_requests WHERE status = 'pending'")->fetch_assoc()['pending'] ?? 0;
$activeSessions = $conn->query("SELECT COUNT(*) AS active FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['active'] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'] ?? 0;
$totalReservations = $conn->query("SELECT COUNT(*) AS total FROM pc_reservations WHERE status = 'reserved'")->fetch_assoc()['total'] ?? 0;

// ==== RECENT ACTIVITIES ====
$recentActivities = $conn->query("
  SELECT 
    la.action, 
    la.user,
    la.pc_no, 
    la.timestamp,
    s.fullname AS student_name,
    pcs.pc_name
  FROM lab_activities la
  LEFT JOIN students s ON la.user = s.id
  LEFT JOIN pcs ON pcs.id = la.pc_no
  ORDER BY la.timestamp DESC
  LIMIT 10
");

// ==== RESERVATIONS TABLE ====
$reservations = $conn->query("
  SELECT r.id, s.fullname, p.pc_name, r.reservation_time, r.status, r.reason, r.reservation_date
  FROM pc_reservations r
  JOIN students s ON r.student_id = s.id
  JOIN pcs p ON r.pc_id = p.id
  ORDER BY r.reservation_time DESC LIMIT 30
");

// ==== USER LOGS ====
$userLogs = $conn->query("
  SELECT
    s.fullname,
    pcs.pc_name,
    ls.login_time,
    ls.logout_time,
    ls.status
  FROM lab_sessions ls
  JOIN students s ON ls.user_id = s.id AND ls.user_type = 'student'
  JOIN pcs ON pcs.id = ls.pc_id
  ORDER BY ls.login_time DESC
  LIMIT 50
");

// ==== REPORTS ====
$reports = $conn->query("
  SELECT r.id, r.report_type, r.details, r.created_at, s.fullname AS student
  FROM reports r
  LEFT JOIN students s ON r.student_id = s.id
  ORDER BY r.created_at DESC
");

// ==== MAINTENANCE REQUESTS ====
$maintenanceList = $conn->query("
  SELECT m.id, m.pc_id, m.student_id, p.pc_name, m.issue, m.status, m.created_at, m.updated_at
  FROM maintenance_requests m
  JOIN pcs p ON m.pc_id = p.id
  ORDER BY m.created_at DESC
");

// ==== ADMIN PROFILE (for Profile Settings) ====
$admin_username = $_SESSION['admin'];
$admin_stmt = $conn->prepare("SELECT username, email FROM admin_users WHERE username = ?");
$admin_stmt->bind_param("s", $admin_username);
$admin_stmt->execute();
$admin = $admin_stmt->get_result()->fetch_assoc();

// ==== NOTIFICATIONS FOR ADMIN ====
// Only count unread notifications for badge
$unreadQuery = "
  SELECT COUNT(*) as unread
  FROM notifications
  WHERE recipient_type = 'admin' AND (read_at IS NULL)
";
$newNotiCount = $conn->query($unreadQuery)->fetch_assoc()['unread'] ?? 0;

// Get ALL notifications (for display, including read_at)
$notifications_sql = "
  SELECT n.id, n.message, n.created_at, n.read_at, s.fullname
  FROM notifications n
  LEFT JOIN students s ON n.recipient_id = s.id
  WHERE n.recipient_type = 'admin'
  ORDER BY n.created_at DESC
";
$notifications = $conn->query($notifications_sql);

// ==== MOST PROBLEMATIC PCs (TOP 5) ====
$problematicPCs = $conn->query("
    SELECT 
        pcs.pc_name, 
        pcs.status, 
        COUNT(mr.id) AS issue_count,
        MAX(mr.created_at) AS last_issue_date
    FROM maintenance_requests mr
    JOIN pcs ON mr.pc_id = pcs.id
    GROUP BY pcs.id
    ORDER BY issue_count DESC
    LIMIT 5
");
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
    <a href="#" data-link="reservations"><i class="fas fa-calendar-check mr-2"></i>Reservations</a>
    <a href="#" data-link="reports"><i class="fas fa-file-alt mr-2"></i>Reports</a>
    <a href="#" data-link="maintenance"><i class="fas fa-tools mr-2"></i>Maintenance</a>
    <a href="#" data-link="profilesettings"><i class="fas fa-user-cog mr-2"></i>Profile Settings</a>
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

  <div class="content px-6 pb-6">
    <!-- DASHBOARD -->
    <section id="dashboard" class="section active">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="flex items-center gap-4 p-4 bg-indigo-50 border-l-4 border-indigo-500 rounded shadow">
          <i class="fas fa-plug text-2xl text-indigo-600"></i>
          <div>
            <h3 class="text-lg font-semibold"><?= "$pcUsed / $totalPCs" ?></h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">PCs in Use</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded shadow">
          <i class="fas fa-exclamation-triangle text-2xl text-yellow-600"></i>
          <div>
            <h3 class="text-lg font-semibold"><?= $pendingMaintenance ?></h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">Pending Maintenance</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 bg-purple-50 border-l-4 border-purple-500 rounded shadow">
          <i class="fas fa-clock text-2xl text-purple-600"></i>
          <div>
            <h3 class="text-lg font-semibold"><?= $activeSessions ?></h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">Active Sessions</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded shadow">
          <i class="fas fa-user-graduate text-2xl text-blue-600"></i>
          <div>
            <h3 class="text-lg font-semibold"><?= $totalStudents ?></h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">Total Students</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-4 bg-green-50 border-l-4 border-green-500 rounded shadow">
          <i class="fas fa-calendar-check text-2xl text-green-600"></i>
          <div>
            <h3 class="text-lg font-semibold"><?= "$totalReservations / $totalPCs" ?></h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">PC Reservations</p>
          </div>
        </div>
        <div class="activity col-span-full">
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
      </div>
    </section>
    
    <!-- RESERVATIONS -->
    <section id="reservations" class="section">
      <h3 class="text-lg font-semibold mb-4">PC Reservations</h3>
      <div class="flex justify-between items-center mb-3">
        <input type="text" id="resSearch" placeholder="Search by student, PC, or status..." class="border px-2 py-1 rounded w-1/2">
        <a href="export_csv.php?type=reservations" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" target="_blank">
        <i class="fas fa-file-csv"></i> Export CSV
      </a>
      </div>
      <table class="w-full border border-gray-300 dark:border-gray-700" id="resTable">
        <thead class="bg-gray-200 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-2 text-left">Student</th>
            <th class="px-4 py-2 text-left">PC</th>
            <th class="px-4 py-2 text-left">Reservation Date</th>
            <th class="px-4 py-2 text-left">Requested At</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-left">Reason</th>
            <th class="px-4 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          while($r = $reservations->fetch_assoc()):
            $status = strtolower($r['status']);
            $badge = match($status) {
              'pending'   => 'bg-yellow-200 text-yellow-800',
              'reserved'  => 'bg-blue-200 text-blue-800',
              'approved'  => 'bg-green-200 text-green-800',
              'cancelled' => 'bg-red-200 text-red-800',
              'rejected'  => 'bg-red-200 text-red-800',
              'expired'   => 'bg-gray-200 text-gray-700',
              'completed' => 'bg-gray-300 text-gray-900',
              default     => 'bg-gray-200 text-gray-700'
            };
          ?>
            <tr class="border-t border-gray-300 dark:border-gray-700" data-row="<?= strtolower($r['fullname'] . ' ' . $r['pc_name'] . ' ' . $r['status']) ?>">
              <td class="px-4 py-2"><?= htmlspecialchars($r['fullname']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['pc_name']) ?></td>
              <td class="px-4 py-2"><?= $r['reservation_date'] ? date('M d, Y', strtotime($r['reservation_date'])) : '<span class="text-gray-400">—</span>' ?></td>
              <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($r['reservation_time'])) ?></td>
              <td class="px-4 py-2">
                <span class="px-2 py-1 rounded <?= $badge ?> capitalize"><?= ucfirst($status) ?></span>
              </td>
              <td class="px-4 py-2"><?= $r['reason'] ? htmlspecialchars($r['reason']) : '<span class="text-gray-400">—</span>' ?></td>
              <td class="px-4 py-2">
                <?php if ($status === 'pending'): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                    <button name="reservation_action" value="approve" class="bg-blue-600 text-white px-2 py-1 rounded mr-1 hover:bg-blue-700">Approve</button>
                    <button name="reservation_action" value="reject" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700"
                            onclick="return promptReason(this)">Reject</button>
                  </form>
                <?php elseif ($status === 'reserved'): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                    <button name="reservation_action" value="approve" class="bg-blue-600 text-white px-2 py-1 rounded mr-1 hover:bg-blue-700">Approve</button>
                    <button name="reservation_action" value="cancel" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700"
                            onclick="return promptReason(this)">Cancel</button>
                  </form>
                <?php elseif ($status === 'approved'): ?>
                  <form method="POST" class="inline">
                    <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                    <button name="reservation_action" value="completed" class="bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">Mark Completed</button>
                  </form>
                <?php else: ?>
                  <span class="text-gray-400 italic">N/A</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>

    <!-- USER LOGS -->
<section id="userlogs" class="section">
  <h3 class="text-lg font-semibold mb-4">User Logs</h3>
  <div class="flex justify-between items-center mb-3">
    <input type="text" id="logSearch" placeholder="Search logs..." class="border px-2 py-1 rounded w-1/2">
    <a href="export_csv.php?type=userlogs" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" target="_blank">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>
  <table class="w-full border border-gray-300 dark:border-gray-700" id="logTable">
    <thead class="bg-gray-200 dark:bg-gray-800">
      <tr>
        <th class="px-4 py-2 text-left">Student</th>
        <th class="px-4 py-2 text-left">PC</th>
        <th class="px-4 py-2 text-left">Login</th>
        <th class="px-4 py-2 text-left">Logout</th>
        <th class="px-4 py-2 text-left">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($userLogs && $userLogs->num_rows > 0): ?>
        <?php while ($log = $userLogs->fetch_assoc()): ?>
          <tr data-log="<?= strtolower($log['fullname'] . ' ' . $log['pc_name'] . ' ' . $log['status']) ?>">
            <td class="px-4 py-2"><?= htmlspecialchars($log['fullname']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($log['pc_name']) ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($log['login_time']) ?></td>
            <td class="px-4 py-2"><?= $log['logout_time'] ?? '-' ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($log['status']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-center text-gray-500">No user logs available.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

    <!-- REPORTS -->
  <section id="reports" class="section">
  <h3 class="text-lg font-semibold mb-4">System Reports</h3>

  <!-- Top Problematic PCs Table -->
  <div class="mb-10">
    <div class="flex justify-between items-center mb-2">
      <h4 class="font-semibold text-base">Frequently Problematic PCs (Maintenance/Assistance Requests)</h4>
      <a href="export_csv.php?type=problematic_pcs" class="bg-green-600 text-white px-3 py-2 rounded hover:bg-green-700 text-xs" target="_blank">
        <i class="fas fa-file-csv"></i> Download CSV
      </a>
    </div>
    <div class="overflow-x-auto rounded shadow border border-gray-200">
      <table class="table-auto w-full text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 text-left">PC Name</th>
            <th class="px-4 py-2 text-left">Issues Reported</th>
            <th class="px-4 py-2 text-left">Current Status</th>
            <th class="px-4 py-2 text-left">Last Issue Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($problematicPCs && $problematicPCs->num_rows > 0): ?>
            <?php while ($row = $problematicPCs->fetch_assoc()): ?>
              <tr class="odd:bg-gray-50 hover:bg-gray-100">
                <td class="px-4 py-2"><?= htmlspecialchars($row['pc_name']) ?></td>
                <td class="px-4 py-2"><?= $row['issue_count'] ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['status']) ?></td>
                <td class="px-4 py-2">
                  <?= $row['last_issue_date'] ? date('M d, Y', strtotime($row['last_issue_date'])) : '-' ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" class="px-4 py-2 text-center text-gray-500">No issues reported.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Original Reports Table -->
  <div class="flex justify-between items-center mb-3">
    <span></span>
    <a href="export_csv.php?type=reports" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" target="_blank">
      <i class="fas fa-file-csv"></i> Export CSV
    </a>
  </div>
  <?php if ($reports->num_rows > 0): ?>
    <div class="overflow-x-auto rounded shadow border border-gray-200">
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
    </div>
  <?php else: ?>
    <p class="text-gray-600 dark:text-gray-400">No reports available.</p>
  <?php endif; ?>
</section>

    <!-- MAINTENANCE -->
    <section id="maintenance" class="section">
      <h3 class="text-lg font-semibold mb-4">PC Maintenance Requests</h3>
      <div class="flex items-center mb-4 gap-4">
        <input type="text" id="maintSearch" placeholder="Search by PC or Issue..." class="border px-2 py-1 rounded w-1/2">
        <a href="export_csv.php?type=maintenance" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700" target="_blank">
          <i class="fas fa-file-csv"></i> Export CSV
        </a>
      </div>
      <?php if ($maintenanceList->num_rows > 0): ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm border border-gray-300 dark:border-gray-700" id="maintTable">
            <thead class="bg-gray-200 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-2 text-left">PC Name</th>
                <th class="px-4 py-2 text-left">Issue</th>
                <th class="px-4 py-2 text-left">Status</th>
                <th class="px-4 py-2 text-left">Reported At</th>
                <th class="px-4 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($m = $maintenanceList->fetch_assoc()): ?>
                <tr class="border-t border-gray-300 dark:border-gray-700" data-maint="<?= strtolower($m['pc_name'].' '.$m['issue'].' '.$m['status']) ?>">
                  <td class="px-4 py-2"><?= htmlspecialchars($m['pc_name']) ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($m['issue']) ?></td>
                  <td class="px-4 py-2">
                    <?php
                      $status = strtolower($m['status']);
                      $badge = match($status) {
                        'pending' => 'bg-yellow-200 text-yellow-800',
                        'in_progress' => 'bg-blue-200 text-blue-800',
                        'completed' => 'bg-green-200 text-green-800',
                        'rejected' => 'bg-red-200 text-red-800',
                        default => 'bg-gray-200 text-gray-700'
                      };
                      echo "<span class='px-2 py-1 rounded $badge capitalize'>$status</span>";
                    ?>
                  </td>
                  <td class="px-4 py-2"><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></td>
                  <td class="px-4 py-2">
                    <?php if (in_array($status, ['pending','in_progress'])): ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="maint_id" value="<?= $m['id'] ?>">
                        <?php if ($status == 'pending'): ?>
                          <button name="maint_action" value="in_progress"
                            class="bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700 mr-1">Mark In Progress</button>
                          <button name="maint_action" value="rejected"
                            class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">Reject</button>
                        <?php endif; ?>
                        <?php if ($status == 'in_progress'): ?>
                          <button name="maint_action" value="completed"
                            class="bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">Mark Completed</button>
                        <?php endif; ?>
                      </form>
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
        <p class="text-gray-600 dark:text-gray-400">No maintenance records.</p>
      <?php endif; ?>
    </section>

    <!-- PROFILE SETTINGS -->
    <section id="profilesettings" class="section">
      <h3 class="text-lg font-semibold mb-4">Profile Settings</h3>
      <form action="update_profile_settings.php" method="POST" class="space-y-4 max-w-md">
        <div>
          <label class="block font-semibold">Username</label>
          <input type="text" name="username" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($admin['username']) ?>" required readonly>
        </div>
        <div>
          <label class="block font-semibold">Email</label>
          <input type="email" name="email" class="w-full px-3 py-2 border rounded" value="<?= htmlspecialchars($admin['email']) ?>" required>
        </div>
        <div>
          <label class="block font-semibold">New Password <span class="text-xs text-gray-500">(leave blank to keep current)</span></label>
          <input type="password" name="new_password" class="w-full px-3 py-2 border rounded" placeholder="Enter new password">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save Changes</button>
      </form>
    </section>
  </div>
</main>
<script src="/ilab/js/admindashboard.js"></script>
</body>
</html>
