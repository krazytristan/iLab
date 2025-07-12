<?php
session_start();
if (!isset($_SESSION['faculty'])) {
  header("Location: login.php");
  exit();
}
$faculty_name = $_SESSION['faculty'];
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
</head>
<body class="bg-gray-100 font-sans">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-lg fixed min-h-screen p-6">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/1053/1053244.png" class="w-16 mx-auto mb-2" alt="Faculty Icon" />
      <h1 class="text-xl font-bold text-blue-800">Faculty</h1>
      <p class="text-sm text-gray-500"><?php echo htmlspecialchars($faculty_name); ?></p>
    </div>
    <nav class="menu space-y-2">
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold" data-link="dashboard">
        <i class="fas fa-chalkboard-teacher"></i> Dashboard
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-50 text-gray-700" data-link="classes">
        <i class="fas fa-book-open"></i> My Classes
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-50 text-gray-700" data-link="schedule">
        <i class="fas fa-calendar-alt"></i> Schedule
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-50 text-gray-700" data-link="submissions">
        <i class="fas fa-file-upload"></i> Submissions
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-50 text-gray-700" data-link="feedback">
        <i class="fas fa-comment-dots"></i> Feedback
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-50 text-gray-700" data-link="settings">
        <i class="fas fa-cog"></i> Settings
      </a>
      <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded text-red-600 hover:bg-red-50">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="ml-64 p-8">
    <header class="flex justify-between items-center mb-8">
      <div>
        <h2 class="text-3xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($faculty_name); ?></h2>
        <p class="text-sm text-gray-500">System check as of <span id="date"></span></p>
      </div>
      <div class="relative">
        <button id="notif-btn" class="relative text-gray-600 hover:text-blue-700 focus:outline-none">
          <i class="fas fa-bell text-2xl"></i>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full animate-ping"></span>
          <span class="absolute top-0 right-0 w-2 h-2 bg-red-600 rounded-full"></span>
        </button>
        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-72 bg-white border rounded shadow z-50">
          <div class="p-3 text-gray-800 font-semibold border-b">Notifications</div>
          <ul class="max-h-64 overflow-y-auto text-sm text-gray-700">
            <li class="p-3 border-b hover:bg-gray-100">No new notifications</li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Section: Dashboard -->
    <section id="dashboard" class="section">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-lg shadow flex items-center gap-4">
          <i class="fas fa-clipboard-list text-4xl text-yellow-500"></i>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">4</h3>
            <p class="text-gray-500">Pending Grades</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow flex items-center gap-4">
          <i class="fas fa-book-reader text-4xl text-blue-600"></i>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">3</h3>
            <p class="text-gray-500">Active Classes</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow flex items-center gap-4">
          <i class="fas fa-check-circle text-4xl text-green-600"></i>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">12</h3>
            <p class="text-gray-500">Graded Submissions</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 mt-6 rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-3 text-gray-800">Recent Activity</h3>
        <ul class="list-disc ml-6 text-gray-700 space-y-1">
          <li>✔️ Final grades submitted for BSCS 1A</li>
          <li>📥 Uploaded materials for AI Fundamentals</li>
          <li>💬 Feedback given to BSECE 2C students</li>
        </ul>
      </div>
    </section>

    <!-- Other Sections -->
    <section id="classes" class="section hidden">
      <h3 class="text-2xl font-semibold mb-4">My Classes</h3>
      <p class="text-gray-600">View and manage your assigned classes here.</p>
    </section>

    <section id="schedule" class="section hidden">
      <h3 class="text-2xl font-semibold mb-4">Schedule</h3>
      <p class="text-gray-600">Your weekly teaching and consultation schedule.</p>
    </section>

    <section id="submissions" class="section hidden">
      <h3 class="text-2xl font-semibold mb-4">Student Submissions</h3>
      <p class="text-gray-600">Review submitted assignments or activities.</p>
    </section>

    <section id="feedback" class="section hidden">
      <h3 class="text-2xl font-semibold mb-4">Feedback</h3>
      <p class="text-gray-600">Read messages and feedback from students.</p>
    </section>

    <section id="settings" class="section hidden">
      <h3 class="text-2xl font-semibold mb-4">Account Settings</h3>
      <form method="POST" action="update_faculty_password.php" class="space-y-4">
        <input type="password" name="current_password" class="w-full border p-2 rounded" placeholder="Current Password" required>
        <input type="password" name="new_password" class="w-full border p-2 rounded" placeholder="New Password" required>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update Password</button>
      </form>
    </section>
  </main>

  <!-- JavaScript for Dynamic Tabs -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      // Display today's date
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

          // Reset active state for links
          links.forEach(l => l.classList.remove("bg-blue-100", "text-blue-700", "font-semibold"));
          link.classList.add("bg-blue-100", "text-blue-700", "font-semibold");

          // Show selected section only
          sections.forEach(section => section.classList.add("hidden"));
          const target = document.getElementById(link.dataset.link);
          if (target) target.classList.remove("hidden");
        });
      });

      // Notification dropdown toggle
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
