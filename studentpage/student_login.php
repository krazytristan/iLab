<?php
session_start();
require_once '../adminpage/db.php'; // Adjust the path if needed

$alert = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']); // Accepts email or USN/LRN
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $alert = '<div class="text-red-800 bg-red-100 border border-red-300 rounded-md px-4 py-2 mb-4">Please fill in all fields.</div>';
    } else {
        $stmt = $conn->prepare("SELECT * FROM students WHERE email = ? OR usn_or_lrn = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_name'] = $user['fullname'];
                $_SESSION['usn_or_lrn'] = $user['usn_or_lrn'];
                $_SESSION['email'] = $user['email'];

                header("Location: student_dashboard.php");
                exit();
            } else {
                $alert = '<div class="text-red-800 bg-red-100 border border-red-300 rounded-md px-4 py-2 mb-4">Incorrect password.</div>';
            }
        } else {
            $alert = '<div class="text-red-800 bg-red-100 border border-red-300 rounded-md px-4 py-2 mb-4">Account not found.</div>';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iLab User Login</title>
  <link rel="stylesheet" href="/ilab/css/style.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="user-page h-screen flex items-center justify-center font-sans relative overflow-hidden">

  <!-- Background Layers -->
  <div class="absolute inset-0 bg-gradient-to-br from-blue-100 to-blue-300 opacity-30 z-0"></div>
  <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10 z-0"></div>

  <!-- Login Box -->
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md z-10">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="User Icon" class="mx-auto w-16 mb-2">
      <h2 class="text-2xl font-bold text-gray-700">iLAB Management System</h2>
      <p class="text-sm text-gray-500">Login to manage your activity!</p>
    </div>

    <!-- PHP Alert -->
    <?= $alert ?>

    <!-- Login Form -->
    <form action="student_login.php" method="POST" class="space-y-4">
      <!-- Username -->
      <div>
        <label class="block text-gray-600 text-sm mb-1">Email or USN/LRN</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-user"></i>
          </span>
          <input type="text" name="username" placeholder="Enter your email or USN"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200" required>
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-gray-600 text-sm mb-1">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-lock"></i>
          </span>
          <input type="password" name="password" placeholder="Enter your password"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200" required>
        </div>
      </div>

      <!-- Forgot Password -->
      <div class="text-right text-sm text-blue-600 hover:underline cursor-pointer">
        Forgot password?
      </div>

      <!-- Login Button -->
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition duration-200 flex items-center justify-center gap-2">
        <span>Login</span>
      </button>
    </form>

    <!-- Register Prompt -->
    <p class="text-center text-sm text-gray-500 mt-6">
      Don’t have an account?
      <a href="student_register.php" class="text-blue-600 font-medium hover:underline">Sign Up Here!</a>
    </p>

    <!-- Footer -->
    <p class="text-center text-sm text-gray-500 mt-2">
      Powered by <span class="font-semibold text-blue-700">AMACC Lipa</span>
    </p>
  </div>
</body>
</html>
