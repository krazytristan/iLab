<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';  // ← fixed path
require_once __DIR__ . '/../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable debug (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    if ($email === '') {
        $alert = 'Enter your registered email or USN/LRN.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, email FROM students WHERE email = ? OR usn_or_lrn = ? LIMIT 1'
        );
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $token   = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $ins = $conn->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
            );
            $ins->bind_param('iss', $user['id'], $token, $expires);
            if (!$ins->execute()) {
                die('Error inserting reset token: ' . $conn->error);
            }
            $ins->close();

            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                       ? 'https://' : 'http://';
            $baseUrl   = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $resetLink = $baseUrl . '/studentresetpass.php?token=' . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your@gmail.com';
                $mail->Password   = 'your-app-password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@yourdomain.com', 'iLab');
                $mail->addAddress($user['email']);

                $mail->Subject = 'iLab Password Reset';
                $mail->Body    = <<<EOT
Hi,

Click the link below to reset your password (valid for 1 hour):

$resetLink

If you didn't request this, please ignore this email.
EOT;

                $mail->send();
                $alert = 'If that email is registered, you will receive a password reset link shortly.';
            } catch (Exception $e) {
                error_log('Mailer Error: ' . $mail->ErrorInfo);
                $alert = 'Could not send reset link. Please try again later.';
            }
        } else {
            $alert = 'No matching account found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="/ilab/css/style.css">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="user-page">
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8
              w-full max-w-md backdrop-blur-md mx-auto mt-16">
    <h2 class="text-2xl font-bold text-gray-700 text-center mb-4">
      Forgot Password
    </h2>

    <?php if ($alert): ?>
      <div class="alert mb-4 text-red-800 bg-red-100 border border-red-300 rounded-md px-4 py-2">
        <?= htmlspecialchars($alert) ?>
      </div>
    <?php endif; ?>

    <form action="studentforgotpass.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-gray-600 text-sm mb-1">Email or USN/LRN</label>
        <input
          type="text"
          name="email"
          placeholder="Enter your email or USN"
          class="w-full px-4 py-2 border rounded-lg focus:ring"
          required
        >
      </div>
      <button
        type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold
               py-2 rounded-lg transition"
      >
        Send Reset Link
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      <a href="student_login.php" class="text-blue-600 hover:underline">
        Back to Login
      </a>
    </p>
  </div>
</body>
</html>
