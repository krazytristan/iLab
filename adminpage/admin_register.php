<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Registration</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png" />
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-green-100 via-white to-gray-200 font-sans relative overflow-hidden">

  <!-- Floating Error Toast -->
  <?php if (isset($_SESSION['register_error'])): ?>
    <div id="errorBox" class="fixed top-5 right-5 z-50 bg-red-100 border border-red-300 text-red-700 px-6 py-3 rounded-lg shadow-lg transition-opacity duration-500">
      <div class="flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $_SESSION['register_error']; unset($_SESSION['register_error']); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <!-- Registration Box -->
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md z-30">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Admin Icon" class="mx-auto w-16 mb-2">
      <h2 class="text-2xl font-bold text-gray-700">Admin Registration</h2>
      <p class="text-sm text-gray-500">Create a new admin account</p>
    </div>

    <form id="registerForm" action="register_process.php" method="POST" class="space-y-4">
      <!-- Username -->
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="username">Username</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-user"></i></span>
          <input id="username" type="text" name="username" placeholder="Enter username"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-green-200 focus:outline-none" required>
        </div>
      </div>

      <!-- Email -->
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="email">Email</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-envelope"></i></span>
          <input id="email" type="email" name="email" placeholder="Enter email"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-green-200 focus:outline-none" required>
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="password">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
          <input id="password" type="password" name="password" placeholder="Enter password"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-green-200 focus:outline-none" required>
        </div>
      </div>

      <!-- Confirm Password -->
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="confirm_password">Confirm Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-lock"></i></span>
          <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirm password"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-green-200 focus:outline-none" required>
        </div>
      </div>

      <!-- Submit Button -->
      <button type="submit" id="registerBtn"
              class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-lg transition duration-200 flex items-center justify-center gap-2">
        <span id="registerText">Register</span>
        <i id="spinner" class="fas fa-spinner fa-spin hidden"></i>
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      Already have an account? <a href="adminlogin.php" class="text-green-600 hover:underline">Login here</a>
    </p>
  </div>

  <!-- Animated Background -->
  <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-tr from-green-200 to-transparent animate-pulse opacity-10 z-0 pointer-events-none"></div>

  <!-- JS Enhancements -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const errorBox = document.getElementById('errorBox');
      if (errorBox) {
        setTimeout(() => {
          errorBox.classList.add('opacity-0');
          setTimeout(() => errorBox.remove(), 500);
        }, 3000);
      }

      const registerForm = document.getElementById('registerForm');
      const registerBtn = document.getElementById('registerBtn');
      const registerText = document.getElementById('registerText');
      const spinner = document.getElementById('spinner');

      registerForm.addEventListener('submit', () => {
        registerBtn.disabled = true;
        spinner.classList.remove('hidden');
        registerText.textContent = 'Registering...';
      });
    });
  </script>
</body>
</html>
