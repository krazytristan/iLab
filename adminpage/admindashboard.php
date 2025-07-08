<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['admin'])) {
  header("Location: admin.php"); // Changed from admin.html
  exit();
}

// Connect to database
require_once 'db.php';

// ============================
// Dynamic Values from Database
// ============================

// Total PCs (Assuming you have a pcs table)
$totalPCsResult = $conn->query("SELECT COUNT(*) AS total FROM pcs");
$totalPCs = $totalPCsResult ? $totalPCsResult->fetch_assoc()['total'] : 40; // fallback

// PCs in use
$pcUsedResult = $conn->query("SELECT COUNT(*) AS used FROM lab_sessions WHERE status = 'active'");
$pcUsed = $pcUsedResult ? $pcUsedResult->fetch_assoc()['used'] : 0;

// Pending Maintenance
$maintenanceResult = $conn->query("SELECT COUNT(*) AS pending FROM maintenance_requests WHERE status = 'pending'");
$pendingMaintenance = $maintenanceResult ? $maintenanceResult->fetch_assoc()['pending'] : 0;

// Active Sessions
$sessionResult = $conn->query("SELECT COUNT(*) AS active FROM lab_sessions WHERE status = 'active'");
$activeSessions = $sessionResult ? $sessionResult->fetch_assoc()['active'] : 0;

// Total Students
$totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'] ?? 0;

// Total Faculty
$totalFaculty = $conn->query("SELECT COUNT(*) AS count FROM faculty")->fetch_assoc()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Computer Lab Dashboard - iLab</title>
  <link rel="stylesheet" href="/css/admindashboard.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="logo">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Admin" />
      <h1>Lab Admin</h1>
    </div>
    <nav class="menu">
      <a href="#" class="active" data-link="dashboard"><i class="fas fa-desktop"></i> Dashboard</a>
      <a href="#" data-link="userlogs"><i class="fas fa-users"></i> User Logs</a>
      <a href="#" data-link="requests"><i class="fas fa-inbox"></i> Students Requests</a>
      <a href="#" data-link="reports"><i class="fas fa-file-alt"></i> Reports</a>
      <a href="#" data-link="maintenance"><i class="fas fa-tools"></i> Maintenance</a>
      <a href="#" data-link="settings"><i class="fas fa-cog"></i> Lab Settings</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <header class="main-header">
      <h2>Welcome, <?php echo htmlspecialchars($_SESSION['admin']); ?></h2>
      <p>System check as of <span id="date"></span></p>
    </header>

    <section id="dashboard" class="section active">
      <div class="cards">
        <div class="card pc-usage">
          <i class="fas fa-plug"></i>
          <div>
            <h3><?php echo "$pcUsed / $totalPCs"; ?></h3>
            <p>PCs in Use</p>
          </div>
        </div>
        <div class="card maintenance">
          <i class="fas fa-exclamation-triangle"></i>
          <div>
            <h3><?php echo $pendingMaintenance; ?></h3>
            <p>Pending Maintenance</p>
          </div>
        </div>
        <div class="card sessions">
          <i class="fas fa-clock"></i>
          <div>
            <h3><?php echo $activeSessions; ?></h3>
            <p>Active Sessions</p>
          </div>
        </div>
        <div class="card students">
          <i class="fas fa-user-graduate"></i>
          <div>
            <h3><?php echo $totalStudents; ?></h3>
            <p>Total Students</p>
          </div>
        </div>
        <div class="card faculty">
          <i class="fas fa-chalkboard-teacher"></i>
          <div>
            <h3><?php echo $totalFaculty; ?></h3>
            <p>Total Faculty</p>
          </div>
        </div>
      </div>

      <div class="activity mt-8">
        <h3>Recent Lab Activities</h3>
        <ul>
          <li><i class="fas fa-user-check text-green-600"></i> Student Maria logged in at PC 10.</li>
          <li><i class="fas fa-tools text-yellow-500"></i> Scheduled maintenance for PC 3.</li>
          <li><i class="fas fa-user-times text-red-600"></i> User John logged out from PC 21.</li>
        </ul>
      </div>
    </section>

    <!-- Other Sections -->
    <section id="userlogs" class="section"><h3>User Logs</h3></section>
    <section id="requests" class="section"><h3>Students Requests</h3></section>
    <section id="reports" class="section"><h3>Reports</h3></section>
    <section id="maintenance" class="section"><h3>Maintenance</h3></section>
    <section id="settings" class="section"><h3>Lab Settings</h3></section>
    <section id="logout" class="section"><h3>Logout</h3><p>You are now logged out.</p></section>
  </main>

  <script>
    // Show current date
    document.addEventListener("DOMContentLoaded", () => {
      const date = new Date();
      document.getElementById("date").textContent = date.toLocaleDateString("en-US", {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
      });
    });
  </script>

  <script src="/js/admindashboard.js"></script>
</body>
</html>
