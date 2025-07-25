<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$token = $_GET['token'] ?? '';
$alert = '';
$userId = null;

// Validate token and check expiry
if (!empty($token)) {
    $stmt = $conn->prepare('SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $userId = $res['user_id'] ?? null;
}

if (!$userId) {
    $alert = '⚠️ Invalid or expired reset token. Please request a new password reset.';
}

// Handle form submission if token is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($password) || empty($password2)) {
        $alert = '⚠️ Both fields are required.';
    } elseif ($password !== $password2) {
        $alert = '❌ Passwords do not match.';
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE students SET password = ? WHERE id = ?');
        $update->bind_param('si', $hashedPassword, $userId);
        $update->execute();
        $update->close();

        // Delete token
        $delete = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $delete->bind_param('i', $userId);
        $delete->execute();
        $delete->close();

        // Redirect to login
        header("Location: student_login.php?reset=success");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Reset Password</title>
  <link rel="stylesheet" href="/ilab/css/style.css" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="user-page h-screen flex items-center justify-center bg-gray-100">
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md mx-auto">
    <h2 class="text-2xl font-bold text-gray-700 text-center mb-4">Reset Password</h2>

    <?php if (!empty($alert)): ?>
      <div class="mb-4 px-4 py-2 border rounded-md <?= $userId ? 'bg-yellow-100 border-yellow-300 text-yellow-800' : 'bg-red-100 border-red-300 text-red-800' ?>">
        <?= htmlspecialchars($alert) ?>
      </div>
    <?php endif; ?>

    <?php if ($userId): ?>
      <form action="studentresetpass.php?token=<?= urlencode(htmlspecialchars($token)) ?>" method="POST" class="space-y-4">
        <div>
          <label class="block text-gray-600 text-sm mb-1">New Password</label>
          <input type="password" name="password" placeholder="New password" required class="w-full px-4 py-2 border rounded-lg focus:ring">
        </div>
        <div>
          <label class="block text-gray-600 text-sm mb-1">Confirm Password</label>
          <input type="password" name="password2" placeholder="Confirm password" required class="w-full px-4 py-2 border rounded-lg focus:ring">
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition">
          Reset Password
        </button>
      </form>
    <?php endif; ?>

    <p class="text-center text-sm text-gray-500 mt-4">
      <a href="student_login.php" class="text-blue-600 hover:underline">Back to Login</a>
    </p>
  </div>
</body>
</html>
