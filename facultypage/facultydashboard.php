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
  <style>
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: #cbd5e1;
      border-radius: 4px;
    }
    .section {
      transition: all 0.3s ease-in-out;
    }
    .card-hover:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 font-sans min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-xl fixed min-h-screen p-6 z-20 border-r border-blue-100">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/1053/1053244.png" class="w-16 mx-auto mb-2" alt="Faculty Icon" />
      <h1 class="text-xl font-bold text-blue-800">Faculty</h1>
      <p class="text-sm text-gray-500"><?php echo htmlspecialchars($faculty_name); ?></p>
    </div>
    <nav class="menu space-y-2">
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded bg-blue-100 text-blue-700 font-semibold" data-link="dashboard">
        <i class="fas fa-chalkboard-teacher"></i> Dashboard
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-100 text-gray-700" data-link="classes">
        <i class="fas fa-book-open"></i> My Classes
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-100 text-gray-700" data-link="schedule">
        <i class="fas fa-calendar-alt"></i> Schedule
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-100 text-gray-700" data-link="submissions">
        <i class="fas fa-file-upload"></i> Submissions
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-100 text-gray-700" data-link="feedback">
        <i class="fas fa-comment-dots"></i> Feedback
      </a>
      <a href="#" class="flex items-center gap-3 px-4 py-2 rounded hover:bg-blue-100 text-gray-700" data-link="settings">
        <i class="fas fa-cog"></i> Settings
      </a>
      <a href="logout.php" class="flex items-center gap-3 px-4 py-2 rounded text-red-600 hover:bg-red-100">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="ml-64 p-8 bg-gradient-to-br from-white via-blue-50 to-white min-h-screen">
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
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 card-hover transition-transform">
          <div class="bg-yellow-100 p-3 rounded-full"><i class="fas fa-clipboard-list text-xl text-yellow-600"></i></div>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">4</h3>
            <p class="text-sm text-gray-500">Pending Grades</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 card-hover transition-transform">
          <div class="bg-blue-100 p-3 rounded-full"><i class="fas fa-book-reader text-xl text-blue-600"></i></div>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">3</h3>
            <p class="text-sm text-gray-500">Active Classes</p>
          </div>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md flex items-center gap-4 card-hover transition-transform">
          <div class="bg-green-100 p-3 rounded-full"><i class="fas fa-check-circle text-xl text-green-600"></i></div>
          <div>
            <h3 class="text-2xl font-bold text-gray-800">12</h3>
            <p class="text-sm text-gray-500">Graded Submissions</p>
          </div>
        </div>
      </div>

      <div class="bg-white p-6 mt-6 rounded-xl shadow">
        <h3 class="text-lg font-semibold mb-4 text-gray-800">Recent Activity</h3>
        <ul class="space-y-4 border-l-2 border-blue-200 pl-4">
          <li class="relative">
            <span class="absolute -left-2 top-1 w-3 h-3 bg-green-500 rounded-full"></span>
            <p class="text-gray-700">✔️ Final grades submitted for <strong>BSCS 1A</strong></p>
          </li>
          <li class="relative">
            <span class="absolute -left-2 top-1 w-3 h-3 bg-blue-500 rounded-full"></span>
            <p class="text-gray-700">📥 Uploaded materials for <strong>AI Fundamentals</strong></p>
          </li>
          <li class="relative">
            <span class="absolute -left-2 top-1 w-3 h-3 bg-yellow-500 rounded-full"></span>
            <p class="text-gray-700">💬 Feedback given to <strong>BSECE 2C</strong> students</p>
          </li>
        </ul>
      </div>
    </section>

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

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      document.getElementById("date").textContent = new Date().toLocaleDateString("en-US", {
        weekday: "long", year: "numeric", month: "long", day: "numeric"
      });

      const links = document.querySelectorAll(".menu a[data-link]");
      const sections = document.querySelectorAll(".section");

      links.forEach(link => {
        link.addEventListener("click", e => {
          e.preventDefault();
          links.forEach(l => l.classList.remove("bg-blue-100", "text-blue-700", "font-semibold"));
          link.classList.add("bg-blue-100", "text-blue-700", "font-semibold");
          sections.forEach(section => section.classList.add("hidden"));
          const target = document.getElementById(link.dataset.link);
          if (target) target.classList.remove("hidden");
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
