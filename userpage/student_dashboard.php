<?php
session_start();
if (!isset($_SESSION['student_id'])) {
  header("Location: student_login.php");
  exit();
}

require_once '../adminpage/db.php';

$student_id = $_SESSION['student_id'];

// Get student info
$stmt = $conn->prepare("SELECT fullname FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

// Handle assistance request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['help_description'])) {
  $desc = trim($_POST['help_description']);
  $pc_id = $_POST['help_pc_id'];
  $stmt = $conn->prepare("INSERT INTO maintenance_requests (pc_id, issue_description, requested_by) VALUES (?, ?, ?)");
  $stmt->bind_param("iss", $pc_id, $desc, $student['fullname']);
  $stmt->execute();
  $request_msg = "Assistance request submitted successfully!";
}

// Handle PC reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_pc_id'])) {
  $reserve_pc_id = $_POST['reserve_pc_id'];
  $check = $conn->prepare("SELECT status FROM pcs WHERE id = ?");
  $check->bind_param("i", $reserve_pc_id);
  $check->execute();
  $status = $check->get_result()->fetch_assoc()['status'];

  if ($status === 'available') {
    $reserve = $conn->prepare("UPDATE pcs SET status = 'in_use' WHERE id = ?");
    $reserve->bind_param("i", $reserve_pc_id);
    $reserve->execute();

    $conn->query("INSERT INTO lab_sessions (user_type, user_id, pc_id, status) VALUES ('student', $student_id, $reserve_pc_id, 'active')");
    $reserve_msg = "PC reserved successfully!";
  } else {
    $reserve_msg = "Selected PC is not available.";
  }
}

// Fetch PCs
$pcs = $conn->query("SELECT id, pc_name, status FROM pcs");

// Fetch recent student logs
$stmt = $conn->prepare("SELECT pc_id, login_time, logout_time, status FROM lab_sessions WHERE user_type='student' AND user_id = ? ORDER BY login_time DESC LIMIT 10");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Dashboard - iLab</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
</head>
<body class="bg-gray-100 font-sans min-h-screen flex">

<!-- Sidebar -->
<aside class="w-64 bg-blue-800 text-white min-h-screen p-5 space-y-2">
  <div class="text-center mb-6">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="w-16 mx-auto mb-2" alt="Avatar">
    <h2 class="text-lg font-bold"><?= htmlspecialchars($student['fullname']) ?></h2>
    <p class="text-sm text-blue-200">Student Panel</p>
  </div>
  <nav class="flex flex-col space-y-2">
    <a href="#" class="nav-link bg-blue-900 font-bold px-3 py-2 rounded" data-target="dashboard">Dashboard</a>
    <a href="#" class="nav-link px-3 py-2 rounded hover:bg-blue-700" data-target="pcstatus">PC Status</a>
    <a href="#" class="nav-link px-3 py-2 rounded hover:bg-blue-700" data-target="reserve">Reserve PC</a>
    <a href="#" class="nav-link px-3 py-2 rounded hover:bg-blue-700" data-target="request">Request Assistance</a>
    <a href="#" class="nav-link px-3 py-2 rounded hover:bg-blue-700" data-target="mylogs">My Logs</a>
    <a href="#" class="nav-link px-3 py-2 rounded hover:bg-blue-700" data-target="settings">Settings</a>
    <a href="student_login.php" class="px-3 py-2 rounded hover:bg-blue-700">Logout</a>
  </nav>
</aside>

<!-- Main Content -->
<main class="flex-1 p-6 space-y-10 bg-white overflow-y-auto">

  <!-- Dashboard -->
  <section id="dashboard" class="section">
    <h2 class="text-2xl font-bold mb-4">Welcome, <?= htmlspecialchars($student['fullname']) ?></h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-green-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">Session Status</h3>
        <p>Active</p>
      </div>
      <div class="bg-blue-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">PC Assigned</h3>
        <p>PC #12</p>
      </div>
      <div class="bg-yellow-100 p-4 rounded shadow">
        <h3 class="text-lg font-semibold">Time Used</h3>
        <p>48 minutes</p>
      </div>
    </div>
  </section>

  <!-- PC Status -->
  <section id="pcstatus" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">PC Status</h2>
    <ul class="space-y-2">
      <?php
      $pcs->data_seek(0);
      while ($pc = $pcs->fetch_assoc()): ?>
        <li class="p-2 border rounded <?= $pc['status'] === 'available' ? 'bg-green-100' : 'bg-red-100' ?>">
          <?= htmlspecialchars($pc['pc_name']) ?> - <strong><?= $pc['status'] ?></strong>
        </li>
      <?php endwhile; ?>
    </ul>
  </section>

  <!-- Reserve PC -->
  <section id="reserve" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Reserve a PC</h2>
    <?php if (isset($reserve_msg)): ?>
      <p class="mb-4 text-blue-600 font-medium"><?= htmlspecialchars($reserve_msg) ?></p>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
      <label class="block">
        Select PC:
        <select name="reserve_pc_id" class="mt-1 p-2 border rounded w-full" required>
          <option value="">-- Choose PC --</option>
          <?php
          $pcs->data_seek(0);
          while ($pc = $pcs->fetch_assoc()):
            if ($pc['status'] === 'available'): ?>
              <option value="<?= $pc['id'] ?>"><?= $pc['pc_name'] ?></option>
          <?php endif; endwhile; ?>
        </select>
      </label>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Reserve
      </button>
    </form>
  </section>

  <!-- Request Assistance -->
  <section id="request" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Request Assistance</h2>
    <?php if (isset($request_msg)): ?>
      <p class="mb-4 text-green-600"><?= htmlspecialchars($request_msg) ?></p>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
      <label class="block">
        PC:
        <select name="help_pc_id" class="mt-1 p-2 border rounded w-full" required>
          <option value="">-- Choose PC --</option>
          <?php
          $pcs->data_seek(0);
          while ($pc = $pcs->fetch_assoc()): ?>
            <option value="<?= $pc['id'] ?>"><?= $pc['pc_name'] ?></option>
          <?php endwhile; ?>
        </select>
      </label>
      <label class="block">
        Issue:
        <textarea name="help_description" class="w-full p-2 border rounded" placeholder="Describe the issue..." required></textarea>
      </label>
      <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Submit Request</button>
    </form>
  </section>

  <!-- My Logs -->
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
            <td class="border px-4 py-2"><?= htmlspecialchars($log['pc_id']) ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($log['login_time']) ?></td>
            <td class="border px-4 py-2"><?= $log['logout_time'] ?? '-' ?></td>
            <td class="border px-4 py-2"><?= htmlspecialchars($log['status']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </section>

  <!-- Settings -->
  <section id="settings" class="section hidden">
    <h2 class="text-xl font-semibold mb-4">Settings</h2>
    <p class="text-gray-600">Profile update features coming soon...</p>
  </section>
</main>

<!-- JS for Sidebar Navigation -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const navLinks = document.querySelectorAll(".nav-link");
  const sections = document.querySelectorAll(".section");

  navLinks.forEach(link => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const target = link.getAttribute("data-target");

      // Hide all sections
      sections.forEach(sec => sec.classList.add("hidden"));

      // Show selected section
      document.getElementById(target)?.classList.remove("hidden");

      // Highlight active nav link
      navLinks.forEach(l => l.classList.remove("bg-blue-900", "font-bold"));
      link.classList.add("bg-blue-900", "font-bold");
    });
  });
});
</script>

</body>
</html>
