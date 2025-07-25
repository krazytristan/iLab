<?php
session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);

    // Step 1: Check if user exists
    $stmt = $conn->prepare("SELECT id, username, email FROM admin_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['forgot_error'] = "⚠ Username not found.";
        header("Location: forgot_password.php");
        exit();
    }

    $user = $result->fetch_assoc();
    if (empty($user['email'])) {
        $_SESSION['forgot_error'] = "⚠ No email associated with this account.";
        header("Location: forgot_password.php");
        exit();
    }

    // Step 2: Generate token and expiry
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));

    // Step 3: Store token into a separate admin_password_resets table
    $insert = $conn->prepare("INSERT INTO admin_password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $insert->bind_param("iss", $user['id'], $token, $expires);
    if (!$insert->execute()) {
        $_SESSION['forgot_error'] = "❌ Failed to store reset token. Try again.";
        header("Location: forgot_password.php");
        exit();
    }

    // Step 4: Prepare reset link
    $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}";
    $resetLink = $baseUrl . "/iLab/adminpage/reset_password.php?token=" . urlencode($token);

    // Step 5: Send reset email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'trstnjorge@gmail.com';         // Your Gmail
        $mail->Password   = 'lqod cgbl zive ajvs';          // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('trstnjorge@gmail.com', 'iLab Support');
        $mail->addAddress($user['email'], $user['username']);

        $mail->isHTML(true);
        $mail->Subject = 'iLab Admin Password Reset';
        $mail->Body = "
            <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
            <p>You requested to reset your iLab admin password. Click the link below to proceed:</p>
            <p><a href='" . htmlspecialchars($resetLink) . "'>" . htmlspecialchars($resetLink) . "</a></p>
            <p>This link will expire in 30 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
        ";

        $mail->send();
        $_SESSION['forgot_success'] = "✅ A reset link has been sent to your email.";
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        $_SESSION['forgot_error'] = "❌ Failed to send email. Try again.";
    }

    header("Location: forgot_password.php");
    exit();
}
?>
