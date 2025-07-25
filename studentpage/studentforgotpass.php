<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer
require_once __DIR__ . '/../includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('display_errors', 1);
error_reporting(E_ALL);

$alert = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_usn = trim($_POST['email']);

    if ($email_or_usn === '') {
        $alert = '⚠️ Enter your registered email or USN/LRN.';
    } else {
        $stmt = $conn->prepare('SELECT id, email FROM students WHERE email = ? OR usn_or_lrn = ? LIMIT 1');
        $stmt->bind_param('ss', $email_or_usn, $email_or_usn);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $token   = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $ins = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
            $ins->bind_param('iss', $user['id'], $token, $expires);
            if (!$ins->execute()) {
                $alert = '❌ Failed to process reset request. Please try again.';
            }
            $ins->close();

            // Build reset link
            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $basePath  = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $basePath . '/studentresetpass.php?token=' . $token;

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'trstnjorge@gmail.com';         // <-- your Gmail
                $mail->Password   = 'lqod cgbl zive ajvs';          // <-- app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@ilab.com', 'iLab System');
                $mail->addAddress($user['email']);

                $mail->Subject = 'iLab Password Reset';
                $mail->Body    = <<<EOT
Hi,

You requested to reset your password. Click the link below to proceed (valid for 1 hour):

$resetLink

If you did not request this, please ignore this email.
EOT;

                $mail->send();
                $alert = '✅ Reset link sent. Please check your email.';
                $success = true;
            } catch (Exception $e) {
                error_log('Mailer Error: ' . $mail->ErrorInfo);
                $alert = '❌ Could not send email. Try again later.';
            }
        } else {
            $alert = '❌ No matching account found.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Forgot Password</title>
  <link rel="stylesheet" href="/ilab/css/style.css" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="user-page bg-gray-100 min-h-screen flex items-center justify-center">
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md mx-auto mt-10">
    <h2 class="text-2xl font-bold text-gray-700 text-center mb-4">Forgot Password</h2>

    <?php if ($alert): ?>
      <div id="alertBox" class="<?= $success ? 'bg-green-100 border-green-400 text-green-800' : 'bg-red-100 border-red-400 text-red-800' ?> 
                  border px-4 py-2 rounded mb-4 transition-opacity duration-1000 ease-in-out">
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
          class="w-full px-4 py-2 border rounded-lg focus:ring focus:ring-blue-200"
          required
        />
      </div>
      <button
        type="submit"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition"
      >
        Send Reset Link
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      <a href="student_login.php" class="text-blue-600 hover:underline">Back to Login</a>
    </p>
  </div>

  <script>
    // Auto-dismiss alert after 3 seconds
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
      setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.style.display = 'none', 1000);
      }, 3000);
    }
  </script>
</body>
</html>
