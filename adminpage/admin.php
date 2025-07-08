<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iLab Login</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/css/admindashboard.css" />
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png" />
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-blue-100 via-white to-gray-200 font-sans relative overflow-hidden">

  <!-- Floating Error Toast -->
  <?php if (isset($_SESSION['login_error'])): ?>
    <div id="errorBox" class="fixed top-5 right-5 z-50 bg-red-100 border border-red-300 text-red-700 px-6 py-3 rounded-lg shadow-lg transition-opacity duration-500">
      <div class="flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $_SESSION['login_error']; unset($_SESSION['login_error']); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <!-- Login Box -->
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md z-30">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="iLab Logo" class="mx-auto w-16 mb-2">
      <h2 class="text-2xl font-bold text-gray-700">iLAB Management System</h2>
      <p class="text-sm text-gray-500">Login to manage your lab activity</p>
    </div>

    <!-- Login Form -->
    <form id="loginForm" action="login.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="username">Username</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-user"></i></span>
          <input id="username" type="text" name="username" placeholder="Enter your username"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200 focus:outline-none" required>
        </div>
      </div>

      <div>
        <label class="block text-gray-600 text-sm mb-1" for="password">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
          <input id="password" type="password" name="password" placeholder="Enter your password"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200 focus:outline-none pr-12" required>
        </div>
      </div>

      <div class="text-right text-sm">
        <a href="#" class="text-blue-600 hover:underline">Forgot password?</a>
      </div>

      <button type="submit" id="loginBtn"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition duration-200 flex items-center justify-center gap-2">
        <span id="loginText">Login</span>
        <i id="spinner" class="fas fa-spinner fa-spin hidden"></i>
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      Powered by <span class="font-semibold text-blue-700">AMACC Lipa</span>
    </p>
  </div>

  <!-- Animated Background -->
  <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-tr from-blue-200 to-transparent animate-pulse opacity-10 z-0 pointer-events-none"></div>

  <!-- External Script -->
  <script src="/js/admin.js"></script>

  <!-- JS Enhancements -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // Auto-hide error box
      const errorBox = document.getElementById('errorBox');
      if (errorBox) {
        setTimeout(() => {
          errorBox.classList.add('opacity-0');
          setTimeout(() => errorBox.remove(), 500);
        }, 3000);
      }


      // Handle form submission
      const loginForm = document.getElementById('loginForm');
      const loginBtn = document.getElementById('loginBtn');
      const loginText = document.getElementById('loginText');
      const spinner = document.getElementById('spinner');

      loginForm.addEventListener('submit', () => {
        loginBtn.disabled = true;
        spinner.classList.remove('hidden');
        loginText.textContent = 'Logging in...';
      });
    });
  </script>
</body>
</html>
