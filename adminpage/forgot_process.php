<?php
session_start();
require 'db.php'; // Your DB connection

// Include Composer's autoloader
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim($_POST["username"]);

  // Step 1: Check if user exists
  $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    $_SESSION['forgot_error'] = "Username not found.";
    header("Location: forgot_password.php");
    exit();
  }

  $user = $result->fetch_assoc();
  if (empty($user['email'])) {
    $_SESSION['forgot_error'] = "No email address associated with this account.";
    header("Location: forgot_password.php");
    exit();
  }

  // Step 2: Generate token and expiry
  $token = bin2hex(random_bytes(32));
  $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

  // Step 3: Store token and expiry
  $stmt = $conn->prepare("UPDATE admin_users SET reset_token = ?, reset_expires = ? WHERE id = ?");
  $stmt->bind_param("ssi", $token, $expires, $user['id']);
  $stmt->execute();

  // Step 4: Prepare reset link
  $resetLink = "http://localhost/yourproject/reset_password.php?token=$token";

  // Step 5: Email setup
  $mail = new PHPMailer(true);

  try {
    // SMTP config
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server if not Gmail
    $mail->SMTPAuth = true;
    $mail->Username = 'your-email@gmail.com'; // ✅ your Gmail address
    $mail->Password = 'your-app-password';     // ✅ App Password, not Gmail password!
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    // Email content
    $mail->setFrom('your-email@gmail.com', 'iLab Support');
    $mail->addAddress($user['email'], $user['username']);
    $mail->isHTML(true);
    $mail->Subject = 'iLab Password Reset Link';
    $mail->Body = "
      <p>Hello <strong>{$user['username']}</strong>,</p>
      <p>Click the link below to reset your password. This link expires in 30 minutes.</p>
      <p><a href='{$resetLink}'>{$resetLink}</a></p>
      <p>If you did not request this, please ignore this email.</p>
    ";

    $mail->send();
    $_SESSION['forgot_success'] = "Reset link has been sent to your email.";
  } catch (Exception $e) {
    $_SESSION['forgot_error'] = "Mailer Error: " . $mail->ErrorInfo;
  }

  header("Location: forgot_password.php");
}
