<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: login.php");
  exit();
}
require_once 'db.php';

// Summary Cards
$totalPCs = $conn->query("SELECT COUNT(*) AS total FROM pcs")->fetch_assoc()['total'] ?? 40;
$pcUsed = $conn->query("SELECT COUNT(*) AS used FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['used'] ?? 0;
$pendingMaintenance = $conn->query("SELECT COUNT(*) AS pending FROM maintenance_requests WHERE status = 'pending'")->fetch_assoc()['pending'] ?? 0;
$activeSessions = $conn->query("SELECT COUNT(*) AS active FROM lab_sessions WHERE status = 'active'")->fetch_assoc()['active'] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'] ?? 0;
$totalFaculty = $conn->query("SELECT COUNT(*) AS count FROM faculty")->fetch_assoc()['count'] ?? 0;

// Activities
$recentActivities = $conn->query("SELECT action, user, pc_no, timestamp FROM lab_activities ORDER BY timestamp DESC LIMIT 5");

// Reservations
$reservations = $conn->query("SELECT r.id, s.fullname, p.pc_name, r.reservation_time, r.status 
                              FROM pc_reservations r
                              JOIN students s ON r.student_id = s.id
                              JOIN pcs p ON r.pc_id = p.id
                              ORDER BY r.reservation_time DESC LIMIT 10");
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
  <style>
    @media (max-width: 1024px) {
      .sidebar {
        position: fixed;
        left: -100%;
        top: 0;
        bottom: 0;
        z-index: 50;
        background-color: white;
        width: 16rem;
        transition: left 0.3s ease-in-out;
      }
      .sidebar.mobile-visible {
        left: 0;
      }
    }
    body.dark .sidebar { background-color: #1f2937; }
    .dark-toggle { cursor: pointer; padding: 0.5rem; border-radius: 0.375rem; background-color: #e5e7eb; }
    body.dark .dark-toggle { background-color: #374151; }
    .section { display: none; }
    .section.active { display: block; }
  </style>
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
  <header class="main-header flex justify-between items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700">
    <div>
      <h2 class="text-xl font-semibold">Welcome, <?= htmlspecialchars($_SESSION['admin']); ?></h2>
      <p>System check as of <span id="date"></span></p>
    </div>
<div class="flex gap-4 items-center">
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
    <section id="dashboard" class="section active">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="card pc-usage"><i class="fas fa-plug"></i><div><h3><?= "$pcUsed / $totalPCs" ?></h3><p>PCs in Use</p></div></div>
        <div class="card maintenance"><i class="fas fa-exclamation-triangle"></i><div><h3><?= $pendingMaintenance ?></h3><p>Pending Maintenance</p></div></div>
        <div class="card sessions"><i class="fas fa-clock"></i><div><h3><?= $activeSessions ?></h3><p>Active Sessions</p></div></div>
        <div class="card students"><i class="fas fa-user-graduate"></i><div><h3><?= $totalStudents ?></h3><p>Total Students</p></div></div>
        <div class="card faculty"><i class="fas fa-chalkboard-teacher"></i><div><h3><?= $totalFaculty ?></h3><p>Total Faculty</p></div></div>
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
                <?php if ($r['status'] === 'reserved'): ?>
                  <button data-id="<?= $r['id'] ?>" data-action="approve" class="res-action bg-blue-600 text-white px-2 py-1 rounded mr-1 hover:bg-blue-700">Approve</button>
                  <button data-id="<?= $r['id'] ?>" data-action="cancel" class="res-action bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">Cancel</button>
                <?php else: ?>
                  <span class="text-gray-400 italic">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>

    <section id="userlogs" class="section"><h3>User Logs</h3><p>Coming soon...</p></section>
    <section id="requests" class="section"><h3>Student Requests</h3><p>Coming soon...</p></section>
    <section id="reports" class="section"><h3>Reports</h3><p>Coming soon...</p></section>
    <section id="maintenance" class="section"><h3>Maintenance</h3><p>Coming soon...</p></section>
    <section id="settings" class="section"><h3>Lab Settings</h3><p>Coming soon...</p></section>
  </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const dateEl = document.getElementById("date");
  dateEl.textContent = new Date().toLocaleDateString("en-US", {
    weekday: "long", year: "numeric", month: "long", day: "numeric"
  });

  // Sidebar Toggle
  document.getElementById("toggleSidebar").addEventListener("click", () => {
    document.getElementById("sidebar").classList.toggle("mobile-visible");
  });

  // Dark Mode
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

  darkToggle.addEventListener("click", () => {
    document.body.classList.contains("dark") ? disableDarkMode() : enableDarkMode();
  });

  // Section Switch
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

  // Reservation Filter
  document.getElementById('resSearch').addEventListener('input', function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#resTable tbody tr').forEach(row => {
      row.style.display = row.dataset.row.includes(term) ? '' : 'none';
    });
  });

  // Approve/Cancel Reservation
  document.querySelectorAll('.res-action').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const action = button.dataset.action;
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
        });
      });
    });
  });
});
</script>
</body>
</html>
