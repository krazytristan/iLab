<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iLab System – Welcome</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-100 via-white to-gray-200 flex flex-col items-center justify-center p-6">

  <div class="bg-white/90 shadow-xl rounded-3xl p-10 max-w-lg w-full text-center backdrop-blur-md">
    <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="iLab Logo" class="w-24 h-24 mx-auto mb-6">

    <h1 class="text-3xl font-bold text-blue-700 mb-2">Welcome to iLab System</h1>
    <p class="text-gray-600 mb-6">Please choose your login portal</p>

    <div class="flex flex-col gap-4">
      <a href="../iLab/adminpage/adminlogin.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition duration-200">
        <i class="fas fa-user-shield mr-2"></i> Admin Login
      </a>
      <a href="../iLab/studentpage/student_login.php" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition duration-200">
        <i class="fas fa-user-graduate mr-2"></i> Student Login
      </a>
    </div>

    <p class="mt-6 text-sm text-gray-500">Powered by <strong class="text-blue-700">AMACC Lipa</strong></p>
  </div>

</body>
</html>
