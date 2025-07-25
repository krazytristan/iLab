<?php
session_start();
require_once '../includes/db.php'; // assumes your DB connection is in this file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT fullname, password FROM faculty WHERE email = ?");
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $stmt->bind_result($fullname, $hashed_password);
    $stmt->fetch();
    if (password_verify($password, $hashed_password)) {
      $_SESSION['faculty'] = $fullname;
      header("Location: facultydashboard.php");
      exit();
    } else {
      $error = "Invalid password.";
    }
  } else {
    $error = "Email not found.";
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Faculty</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-100 flex items-center justify-center min-h-screen">
  <div class="bg-white p-8 rounded shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center mb-6">Faculty Login</h2>
    <?php if (isset($error)): ?>
      <p class="text-red-500 mb-4"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Email" required class="w-full p-2 border rounded">
      <input type="password" name="password" placeholder="Password" required class="w-full p-2 border rounded">
      <button type="submit" class="w-full bg-blue-700 text-white p-2 rounded hover:bg-blue-800">Login</button>
    </form>
    <p class="text-sm text-center mt-4">Don't have an account? <a href="facultyregister.php" class="text-blue-600">Register here</a></p>
  </div>
</body>
</html>
