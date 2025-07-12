<?php
session_start();
require_once '../adminpage/db.php'; // Adjust path if needed

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "❌ Passwords do not match. Please try again.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM faculty WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "⚠️ Email already exists. Please use a different one.";
        } else {
            // Insert into DB
            $stmt = $conn->prepare("INSERT INTO faculty (fullname, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $fullname, $email, $hashed_password);

            if ($stmt->execute()) {
                $success = "✅ Registration successful! Redirecting to login page...";
                echo "<script>
                        setTimeout(() => {
                          window.location.href = 'facultylogin.php';
                        }, 2000);
                      </script>";
            } else {
                $error = "❌ Registration failed. Please try again later.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Faculty Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-100 flex items-center justify-center min-h-screen">
  <div class="bg-white p-8 rounded shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center mb-6">Faculty Register</h2>

    <?php if (!empty($error)): ?>
      <div class="bg-red-100 text-red-700 border border-red-300 px-4 py-2 mb-4 rounded">
        <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
      <div class="bg-green-100 text-green-700 border border-green-300 px-4 py-2 mb-4 rounded">
        <?php echo $success; ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4" onsubmit="return validatePasswords()">
      <input type="text" name="fullname" placeholder="Full Name" required class="w-full p-2 border rounded">
      <input type="email" name="email" placeholder="Email" required class="w-full p-2 border rounded">
      <input type="password" name="password" id="password" placeholder="Password (min 6 chars)" required pattern=".{6,}" class="w-full p-2 border rounded">
      <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required class="w-full p-2 border rounded">
      <p id="matchMessage" class="text-sm"></p>
      <button type="submit" class="w-full bg-blue-700 text-white p-2 rounded hover:bg-blue-800">Register</button>
    </form>
    <p class="text-sm text-center mt-4">Already have an account? <a href="facultylogin.php" class="text-blue-600">Login here</a></p>
  </div>

  <script>
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const matchMessage = document.getElementById("matchMessage");

    function validatePasswords() {
      if (password.value !== confirmPassword.value) {
        matchMessage.textContent = "❌ Passwords do not match.";
        matchMessage.className = "text-red-500 text-sm";
        return false;
      }
      return true;
    }

    confirmPassword.addEventListener("input", () => {
      if (password.value === confirmPassword.value) {
        matchMessage.textContent = "✅ Passwords match.";
        matchMessage.className = "text-green-500 text-sm";
      } else {
        matchMessage.textContent = "❌ Passwords do not match.";
        matchMessage.className = "text-red-500 text-sm";
      }
    });
  </script>
</body>
</html>
