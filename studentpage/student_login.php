<?php
// student_login.php
session_start();

// 1) Redirect if already logged in
if (isset($_SESSION['student_id'])) {
    header('Location: student_dashboard.php');
    exit;
}

// 2) Include database connection
require_once __DIR__ . '/../includes/db.php';

// 3) Handle alert messages (flash)
$alert = $_SESSION['login_alert'] ?? '';
unset($_SESSION['login_alert']);

// 4) Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === '' || $password === '') {
        $_SESSION['login_alert'] = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("
            SELECT id, fullname, usn_or_lrn, email, password
            FROM students
            WHERE email = ? OR usn_or_lrn = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['student_id']   = $user['id'];
            $_SESSION['student_name'] = $user['fullname'];
            $_SESSION['usn_or_lrn']   = $user['usn_or_lrn'];
            $_SESSION['email']        = $user['email'];

            header('Location: student_dashboard.php');
            exit;
        } else {
            $_SESSION['login_alert'] = 'Incorrect credentials.';
        }
    }

    // Redirect to clear POST and show alert
    header('Location: student_login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iLab Student Login</title>
  <link rel="stylesheet" href="/ilab/css/style.css"/>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="user-page h-screen flex items-center justify-center font-sans relative overflow-hidden">
  <!-- Background Layers -->
  <div class="absolute inset-0 bg-gradient-to-br from-blue-100 to-blue-300 opacity-30 z-0"></div>
  <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10 z-0"></div>

  <!-- Login Box -->
  <div class="bg-white bg-opacity-90 shadow-2xl rounded-2xl p-8 w-full max-w-md backdrop-blur-md z-10">
    <div class="text-center mb-6">
      <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png"
           alt="User Icon" class="mx-auto w-16 mb-2">
      <h2 class="text-2xl font-bold text-gray-700">iLab Management System</h2>
      <p class="text-sm text-gray-500">Login to manage your activity!</p>
    </div>

    <!-- Alert -->
    <?php if ($alert): ?>
      <div class="alert-message text-red-800 bg-red-100 border border-red-300
                  rounded-md px-4 py-2 mb-4 transition-opacity duration-500">
        <?= htmlspecialchars($alert) ?>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form action="student_login.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-gray-600 text-sm mb-1">Email or USN/LRN</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-user"></i>
          </span>
          <input type="text" name="username" placeholder="Enter your email or USN"
                 class="pl-12 w-full px-4 py-2 border rounded-lg focus:ring"
                 required>
        </div>
      </div>

      <div>
        <label class="block text-gray-600 text-sm mb-1">Password</label>
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
            <i class="fas fa-lock"></i>
          </span>
          <input type="password" name="password" placeholder="Enter your password"
                 class="pl-12 w-full px-4 py-2 border rounded-lg focus:ring"
                 required>
        </div>
      </div>

      <div class="text-right text-sm">
        <a href="studentforgotpass.php" class="text-blue-600 hover:underline">
          Forgot password?
        </a>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white
                     font-semibold py-2 rounded-lg transition duration-200
                     flex items-center justify-center gap-2">
        <span>Login</span>
      </button>
    </form>

    <p class="text-center text-sm text-gray-500 mt-6">
      Don’t have an account?
      <a href="student_register.php" class="text-blue-600 hover:underline">
        Sign Up Here!
      </a>
    </p>

    <p class="text-center text-sm text-gray-500 mt-2">
      Powered by <span class="font-semibold text-blue-700">AMACC Lipa</span>
    </p>
  </div>

  <!-- Auto-hide alert -->
  <script>
    const alertBox = document.querySelector('.alert-message');
    if (alertBox) {
      setTimeout(() => {
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
      }, 3000);
    }
  </script>
</body>
</html>
