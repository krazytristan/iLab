<?php
session_start();
require 'db.php';

$token = $_GET['token'] ?? '';

// Handle invalid/missing token early
if (!$token) {
  $_SESSION['reset_error'] = "Invalid or missing reset token.";
  header("Location: adminlogin.php");
  exit();
}

// Check if token is valid and not expired
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['reset_error'] = "Reset link is invalid or has expired.";
  header("Location: adminlogin.php");
  exit();
}

$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="h-screen flex items-center justify-center bg-gradient-to-br from-blue-100 via-white to-gray-200 font-sans">

  <div class="bg-white shadow-lg rounded-xl p-8 max-w-md w-full z-30">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" class="w-16 mx-auto mb-2" alt="Logo">
      <h2 class="text-2xl font-bold text-gray-700">Reset Your Password</h2>
      <p class="text-sm text-gray-500">Enter your new password below.</p>
    </div>

    <form action="reset_process.php" method="POST" class="space-y-4">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div>
        <label for="password" class="block text-sm text-gray-600">New Password</label>
        <input type="password" name="password" id="password" required
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-200 focus:outline-none">
      </div>

      <div>
        <label for="confirm_password" class="block text-sm text-gray-600">Confirm Password</label>
        <input type="password" name="confirm_password" id="confirm_password" required
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-200 focus:outline-none">
      </div>

      <button type="submit"
              class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg hover:bg-blue-700 transition">
        Reset Password
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-4">
      Back to <a href="adminlogin.php" class="text-blue-600 hover:underline">Login</a>
    </p>
  </div>

</body>
</html>
