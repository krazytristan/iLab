<?php
session_start();
require '../includes/db.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';

// === Validate token existence ===
if (!$token) {
    $_SESSION['reset_error'] = "Missing reset token.";
    header("Location: adminlogin.php");
    exit();
}

// === Check token validity (either from `admin_users` or `admin_password_resets`) ===
$stmt = $conn->prepare("
    SELECT au.id, au.username, au.email
    FROM admin_users au
    JOIN admin_password_resets apr ON apr.user_id = au.id
    WHERE apr.token = ? AND apr.expires_at > NOW()
    ORDER BY apr.created_at DESC
    LIMIT 1
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['reset_error'] = "Reset link is invalid or has expired.";
    header("Location: ../adminpage/reset_password.php");
    exit();
}

$user = $result->fetch_assoc();

// === Handle password update ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || $newPassword !== $confirmPassword) {
        $_SESSION['reset_error'] = "Passwords do not match or are empty.";
        header("Location: ../adminpage/reset_password.php?token=" . urlencode($token));
        exit();
    }

    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update admin password and delete token
    $update = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
    $update->bind_param("si", $hashed, $user['id']);
    $update->execute();

    // Clear the used token
    $delete = $conn->prepare("DELETE FROM admin_password_resets WHERE token = ?");
    $delete->bind_param("s", $token);
    $delete->execute();

    $_SESSION['reset_success'] = "✅ Password reset successfully. You may now log in.";
    header("Location: adminlogin.php");
    exit();
}
?>

<!-- HTML PART -->
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

    <?php if (isset($_SESSION['reset_error'])): ?>
      <div class="mb-4 bg-red-100 border border-red-300 text-red-700 px-4 py-2 rounded">
        <?= $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?>
      </div>
    <?php endif; ?>

    <form action="reset_password.php" method="POST" class="space-y-4">
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
