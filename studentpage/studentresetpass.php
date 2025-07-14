<?php
// Start session if none exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';

// Enable debug (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$token  = $_GET['token'] ?? '';
$alert  = '';
$userId = null;

// 1) Validate token exists and is not expired
if ($token !== '') {
    $stmt = $conn->prepare(
        'SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $userId = $res['user_id'] ?? null;
}

// 2) Show error if token invalid
if (!$userId) {
    $alert = 'Invalid or expired token. Please request a new link.';
}

// 3) Handle form submission when token valid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId) {
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if ($pass === '' || $pass !== $pass2) {
        $alert = 'Passwords must match and not be empty.';
    } else {
        // 4) Update the user's password
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $upd  = $conn->prepare('UPDATE students SET password = ? WHERE id = ?');
        $upd->bind_param('si', $hash, $userId);
        $upd->execute();
        $upd->close();

        // 5) Remove the used token
        $del = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
        $del->bind_param('i', $userId);
        $del->execute();
        $del->close();

        // 6) Redirect to login with success flag
        header('Location: student_login.php?reset=success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password</title>
  <link rel="stylesheet" href="/ilab/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="/ilab/js/auth.js"></script>
</head>
<body class="user-page h-screen flex items-center justify-center">
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md mx-auto mt-16">
    <h2 class="text-2xl font-bold text-gray-700 text-center mb-4">Reset Password</h2>
    <?php if ($alert): ?>
      <div class="alert mb-4 text-red-800 bg-red-100 border border-red-300 rounded-md px-4 py-2">
        <?= htmlspecialchars($alert) ?>
      </div>
    <?php endif; ?>

    <?php if ($userId): ?>
      <form action="studentresetpass.php?token=<?= urlencode($token) ?>" method="POST" class="space-y-4">
        <div>
          <label class="block text-gray-600 text-sm mb-1">New Password</label>
          <input type="password" name="password" placeholder="New password" class="w-full px-4 py-2 border rounded-lg focus:ring" required>
        </div>
        <div>
          <label class="block text-gray-600 text-sm mb-1">Confirm Password</label>
          <input type="password" name="password2" placeholder="Confirm password" class="w-full px-4 py-2 border rounded-lg focus:ring" required>
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