<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>iLab Student Registration</title>
  <link rel="stylesheet" href="/ilab/css/register.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="https://cdn-icons-png.flaticon.com/512/2306/2306164.png" />
  <style>
    html, body {
      margin: 0;
      padding: 0;
      overflow-x: hidden;
    }
    * {
      box-sizing: border-box;
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#f0f4ff] to-[#e0e7ff] font-sans overflow-x-hidden">

  <!-- Page wrapper -->
  <div class="w-full min-h-screen flex items-center justify-center px-4 py-10 overflow-y-auto">
    <div class="form-card w-full max-w-4xl relative">

      <!-- Decorative blur background -->
      <div class="absolute -top-20 -right-20 w-80 h-80 bg-blue-100 rounded-full blur-3xl opacity-50 -z-10"></div>

      <!-- Form header -->
      <div class="text-center mb-8">
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Register Icon" class="w-16 mx-auto mb-3">
        <h1 class="text-3xl font-bold text-gray-800">Register Your iLab Account</h1>
        <p class="text-sm text-gray-500">Please complete the form to continue</p>
      </div>

      <!-- Registration Form -->
      <form id="registerForm" action="register.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div>
          <label class="form-label">Full Name</label>
          <input type="text" name="fullname" class="form-input" required />
        </div>

        <div>
          <label class="form-label">USN / LRN</label>
          <input type="text" name="usn" id="usnInput" class="form-input" required />
        </div>

        <div>
          <label class="form-label">Birthday</label>
          <input type="date" name="birthday" class="form-input" required />
        </div>

        <div>
          <label class="form-label">Level</label>
          <select id="levelType" class="form-input" required>
            <option value="">Select Level</option>
            <option value="shs">Senior High School (SHS)</option>
            <option value="college">College</option>
          </select>
        </div>

        <div>
          <label class="form-label">Grade / Year Level</label>
          <select name="year_level" id="yearLevel" class="form-input" required>
            <option value="">Select Grade/Year</option>
          </select>
        </div>

        <div>
          <label class="form-label">Strand / Course</label>
          <select name="strand" id="strandCourse" class="form-input" required>
            <option value="">Select Strand/Course</option>
          </select>
        </div>

        <div>
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-input" required />
        </div>

        <div>
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact" class="form-input" required />
        </div>

        <div>
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-input" required />
        </div>

        <div>
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-input" required />
        </div>

        <div class="col-span-1 md:col-span-2">
          <button type="submit" id="registerBtn"
                  class="w-full bg-blue-600 hover:bg-blue-700 transition text-white font-semibold py-3 rounded-lg flex justify-center items-center gap-2">
            <span id="btnText">Register</span>
            <svg id="spinner" class="hidden w-5 h-5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>

          <p class="text-sm text-center text-gray-500 mt-4">
            Already have an account?
            <a href="student_login.php" class="text-blue-600 font-medium hover:underline">Login here</a>
          </p>
        </div>
      </form>
    </div>
  </div>

  <script>
    const levelType = document.getElementById("levelType");
    const yearLevel = document.getElementById("yearLevel");
    const strandCourse = document.getElementById("strandCourse");

    const data = {
      shs: {
        years: ["Grade 11", "Grade 12"],
        strands: ["STEM", "ABM", "HUMSS", "GAS", "TVL"]
      },
      college: {
        years: ["1st Year", "2nd Year", "3rd Year", "4th Year"],
        strands: ["BSCS", "BSBA-FM", "BSBA-MM", "BSCpE", "BSECE", "BSIT"]
      }
    };

    levelType.addEventListener("change", () => {
      const selected = levelType.value;
      yearLevel.innerHTML = '<option value="">Select Grade/Year</option>';
      strandCourse.innerHTML = '<option value="">Select Strand/Course</option>';

      if (selected && data[selected]) {
        data[selected].years.forEach(yr => {
          yearLevel.innerHTML += `<option value="${yr}">${yr}</option>`;
        });
        data[selected].strands.forEach(str => {
          strandCourse.innerHTML += `<option value="${str}">${str}</option>`;
        });
      }
    });

    document.getElementById("usnInput").addEventListener("input", function () {
      this.value = this.value.replace(/\D/g, "");
    });

    const form = document.getElementById("registerForm");
    const spinner = document.getElementById("spinner");
    const btnText = document.getElementById("btnText");

    form.addEventListener("submit", function () {
      spinner.classList.remove("hidden");
      btnText.textContent = "Registering...";
    });
  </script>
</body>
</html>
