<?php
session_start();
if (!isset($_SESSION['faculty'])) {
  header("Location: login.php");
  exit();
}
$faculty_name = $_SESSION['faculty']; // assuming full name is stored
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Faculty Dashboard - iLab</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
  <style>
    .sidebar {
      @apply w-64 bg-blue-900 text-white min-h-screen p-6 fixed;
    }
    .sidebar img {
      @apply w-16 mx-auto mb-2;
    }
    .menu a {
      @apply flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-700 transition;
    }
    .menu a.active {
      @apply bg-blue-800 font-semibold;
    }
    .main {
      margin-left: 16rem;
      @apply p-6;
    }
    .section {
      @apply hidden;
    }
    .section.active {
      @apply block;
    }
    .cards {
      @apply grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 my-6;
    }
    .card {
      @apply bg-white p-5 rounded shadow flex items-center gap-4;
    }
    .card i {
      @apply text-4xl text-blue-600;
    }
  </style>
</head>
<body class="bg-gray-100">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/1053/1053244.png" alt="Faculty" />
      <h1 class="text-xl font-bold">Faculty</h1>
      <p class="text-sm text-blue-300"><?php echo htmlspecialchars($faculty_name); ?></p>
    </div>
    <nav class="menu space-y-2">
      <a href="#" class="active" data-link="dashboard"><i class="fas fa-chalkboard-teacher"></i> Dashboard</a>
      <a href="#" data-link="classes"><i class="fas fa-book-open"></i> My Classes</a>
      <a href="#" data-link="schedule"><i class="fas fa-calendar-alt"></i> Schedule</a>
      <a href="#" data-link="submissions"><i class="fas fa-file-upload"></i> Submissions</a>
      <a href="#" data-link="feedback"><i class="fas fa-comment-dots"></i> Feedback</a>
      <a href="#" data-link="settings"><i class="fas fa-cog"></i> Settings</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <header class="mb-6 flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($faculty_name); ?></h2>
        <p class="text-gray-600">System check as of <span id="date"></span></p>
      </div>
      <div class="relative">
        <button id="notif-btn" class="relative text-gray-600 hover:text-blue-700">
          <i class="fas fa-bell text-2xl"></i>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full animate-ping"></span>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
        </button>
        <!-- Notification Dropdown -->
        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-72 bg-white border rounded shadow z-50">
          <div class="p-3 font-semibold text-sm text-gray-700 border-b">Notifications</div>
          <ul class="max-h-64 overflow-y-auto text-sm">
            <li class="p-3 border-b hover:bg-gray-100">No new notifications</li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Dashboard Section -->
    <section id="dashboard" class="section active">
      <div class="cards">
        <div class="card">
          <i class="fas fa-clipboard-list text-yellow-600"></i>
          <div>
            <h3 class="text-xl font-bold">4</h3>
            <p class="text-gray-700">Pending Grades</p>
          </div>
        </div>
        <div class="card">
          <i class="fas fa-book-reader text-blue-600"></i>
          <div>
            <h3 class="text-xl font-bold">3</h3>
            <p class="text-gray-700">Active Classes</p>
          </div>
        </div>
        <div class="card">
          <i class="fas fa-check-circle text-green-600"></i>
          <div>
            <h3 class="text-xl font-bold">12</h3>
            <p class="text-gray-700">Graded Submissions</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-5 rounded shadow mt-6">
        <h3 class="text-lg font-semibold mb-3">Recent Activity</h3>
        <ul class="list-disc ml-5 text-gray-700 space-y-1">
          <li><i class="fas fa-check text-green-600 mr-1"></i> Final grades submitted for BSCS 1A</li>
          <li><i class="fas fa-upload text-blue-600 mr-1"></i> Uploaded materials for AI Fundamentals</li>
          <li><i class="fas fa-comment text-yellow-600 mr-1"></i> Feedback given to BSECE 2C students</li>
        </ul>
      </div>
    </section>

    <section id="classes" class="section">
      <h3 class="text-xl font-semibold mb-4">My Classes</h3>
      <p class="text-gray-600">View and manage your assigned classes here.</p>
    </section>

    <section id="schedule" class="section">
      <h3 class="text-xl font-semibold mb-4">Schedule</h3>
      <p class="text-gray-600">Your weekly teaching and consultation schedule.</p>
    </section>

    <section id="submissions" class="section">
      <h3 class="text-xl font-semibold mb-4">Student Submissions</h3>
      <p class="text-gray-600">Review submitted assignments or activities.</p>
    </section>

    <section id="feedback" class="section">
      <h3 class="text-xl font-semibold mb-4">Feedback</h3>
      <p class="text-gray-600">Read messages and feedback from students.</p>
    </section>

    <section id="settings" class="section">
      <h3 class="text-xl font-semibold mb-4">Account Settings</h3>
      <form method="POST" action="update_faculty_password.php" class="space-y-4">
        <input type="password" name="current_password" class="w-full border p-2 rounded" placeholder="Current Password" required>
        <input type="password" name="new_password" class="w-full border p-2 rounded" placeholder="New Password" required>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Password</button>
      </form>
    </section>
  </main>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("date").textContent = new Date().toLocaleDateString("en-US", {
        weekday: "long",
        year: "numeric",
        month: "long",
        day: "numeric"
      });

      const links = document.querySelectorAll(".menu a[data-link]");
      const sections = document.querySelectorAll(".section");

      links.forEach(link => {
        link.addEventListener("click", e => {
          e.preventDefault();
          links.forEach(l => l.classList.remove("active"));
          link.classList.add("active");

          sections.forEach(section => section.classList.remove("active"));
          const target = document.getElementById(link.dataset.link);
          if (target) target.classList.add("active");
        });
      });

      const notifBtn = document.getElementById("notif-btn");
      const notifDropdown = document.getElementById("notif-dropdown");

      notifBtn.addEventListener("click", () => {
        notifDropdown.classList.toggle("hidden");
      });

      document.addEventListener("click", (e) => {
        if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
          notifDropdown.classList.add("hidden");
        }
      });
    });
  </script>
</body>
</html>
