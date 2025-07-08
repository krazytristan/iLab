document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginBtn = document.getElementById("loginBtn");
  const loginText = document.getElementById("loginText");
  const spinner = document.getElementById("spinner");

  loginForm.addEventListener("submit", (e) => {
    // Optional: simple front-end validation check
    const username = loginForm.username.value.trim();
    const password = loginForm.password.value.trim();

    if (!username || !password) {
      e.preventDefault();
      alert("Please fill in both username and password.");
      return;
    }

    // Show loading spinner
    loginBtn.disabled = true;
    loginText.textContent = "Logging in...";
    spinner.classList.remove("hidden");
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");
  const loginBtn = document.getElementById("loginBtn");
  const loginText = document.getElementById("loginText");
  const spinner = document.getElementById("spinner");

  loginForm.addEventListener("submit", () => {
    loginBtn.disabled = true;
    loginText.textContent = "Logging in...";
    spinner.classList.remove("hidden");
  });
});
