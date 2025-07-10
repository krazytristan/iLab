<?php
session_start();
if (!isset($_SESSION['faculty'])) {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Faculty Dashboard - iLab</title>
  <link rel="stylesheet" href="/css/facultydashboard.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="logo">
      <img src="https://cdn-icons-png.flaticon.com/512/1053/1053244.png" alt="Faculty" />
      <h1>Faculty</h1>
    </div>
    <nav class="menu">
      <a href="#" class="active" data-link="dashboard"><i class="fas fa-chalkboard-teacher"></i> Dashboard</a>
      <a href="#" data-link="classes"><i class="fas fa-book-open"></i> My Classes</a>
      <a href="#" data-link="schedule"><i class="fas fa-calendar-alt"></i> Schedule</a>
      <a href="#" data-link="submissions"><i class="fas fa-file-upload"></i> Submissions</a>
      <a href="#" data-link="feedback"><i class="fas fa-comment-dots"></i> Feedback</a>
      <a href="#" data-link="settings"><i class="fas fa-cog"></i> Settings</a>
      <a href="#" data-link="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <header class="main-header">
      <h2>Welcome to Faculty Dashboard</h2>
      <p>System check as of <span id="date"></span></p>
    </header>

    <section id="dashboard" class="section active">
      <div class="cards">
        <div class="card tasks">
          <i class="fas fa-clipboard-list"></i>
          <div>
            <h3>4</h3>
            <p>Pending Grades</p>
          </div>
        </div>
        <div class="card pending">
          <i class="fas fa-book-reader"></i>
          <div>
            <h3>3</h3>
            <p>Active Classes</p>
          </div>
        </div>
        <div class="card done">
          <i class="fas fa-check-circle"></i>
          <div>
            <h3>12</h3>
            <p>Graded Submissions</p>
          </div>
        </div>
      </div>

      <div class="activity">
        <h3>Recent Activity</h3>
        <ul>
          <li><i class="fas fa-check text-green-600"></i> Final grades submitted for BSCS 1A</li>
          <li><i class="fas fa-upload text-blue-600"></i> Uploaded materials for AI Fundamentals</li>
          <li><i class="fas fa-comment text-yellow-600"></i> Feedback given to BSECE 2C students</li>
        </ul>
      </div>
    </section>

    <section id="classes" class="section">
      <h3>My Classes</h3>
      <p>View and manage your assigned classes.</p>
    </section>

    <section id="schedule" class="section">
      <h3>Schedule</h3>
      <p>Weekly teaching and consultation schedule.</p>
    </section>

    <section id="submissions" class="section">
      <h3>Student Submissions</h3>
      <p>Review submitted assignments or activities.</p>
    </section>

    <section id="feedback" class="section">
      <h3>Feedback</h3>
      <p>Messages and comments from students.</p>
    </section>

    <section id="settings" class="section">
      <h3>Settings</h3>
      <p>Manage account settings or change password.</p>
    </section>

    <section id="logout" class="section">
      <h3>Logout</h3>
      <p>You have been logged out.</p>
    </section>
  </main>

  <script>
    // Display today's date
    document.addEventListener("DOMContentLoaded", () => {
      const dateEl = document.getElementById("date");
      if (dateEl) {
        const today = new Date();
        dateEl.textContent = today.toLocaleDateString("en-US", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        });
      }

      // Sidebar navigation logic
      const links = document.querySelectorAll(".menu a");
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
    });
  </script>

</body>
</html>
