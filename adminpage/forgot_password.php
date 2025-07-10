<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png" />
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-blue-100 via-white to-gray-200 font-sans relative overflow-hidden">

  <!-- Session Messages -->
  <?php if (isset($_SESSION['forgot_error'])): ?>
    <div id="errorBox" class="fixed top-5 right-5 z-50 bg-red-100 border border-red-300 text-red-700 px-6 py-3 rounded-lg shadow-lg transition-opacity duration-500">
      <div class="flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $_SESSION['forgot_error']; unset($_SESSION['forgot_error']); ?></span>
      </div>
    </div>
  <?php elseif (isset($_SESSION['forgot_success'])): ?>
    <div id="successBox" class="fixed top-5 right-5 z-50 bg-green-100 border border-green-300 text-green-700 px-6 py-3 rounded-lg shadow-lg transition-opacity duration-500">
      <div class="flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span><?= $_SESSION['forgot_success']; unset($_SESSION['forgot_success']); ?></span>
      </div>
    </div>
  <?php endif; ?>

  <!-- Forgot Password Box -->
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md z-30">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="iLab Logo" class="mx-auto w-16 mb-2">
      <h2 class="text-2xl font-bold text-gray-700">Forgot Password</h2>
      <p class="text-sm text-gray-500">We'll help you reset your credentials</p>
    </div>

    <form action="forgot_process.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-gray-600 text-sm mb-1" for="username">Enter your Username</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400"><i class="fas fa-user"></i></span>
          <input type="text" name="username" id="username" placeholder="Enter your username"
                 class="pl-12 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200 focus:outline-none" required>
        </div>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition duration-200 flex items-center justify-center gap-2">
        <span>Send Reset Link</span>
        <i class="fas fa-paper-plane"></i>
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-4">
      Back to <a href="adminlogin.php" class="text-blue-600 hover:underline">Login</a>
    </p>
  </div>

  <!-- Animated Background -->
  <div class="absolute top-0 left-0 w-full h-full bg-gradient-to-tr from-blue-200 to-transparent animate-pulse opacity-10 z-0 pointer-events-none"></div>

  <!-- JS Enhancements -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const errorBox = document.getElementById('errorBox');
      const successBox = document.getElementById('successBox');

      [errorBox, successBox].forEach(box => {
        if (box) {
          setTimeout(() => {
            box.classList.add('opacity-0');
            setTimeout(() => box.remove(), 500);
          }, 3000);
        }
      });
    });
  </script>
</body>
</html>
