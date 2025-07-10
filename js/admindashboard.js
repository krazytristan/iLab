document.addEventListener("DOMContentLoaded", () => {
  // ===== Display Current Date =====
  const dateEl = document.getElementById("date");
  if (dateEl) {
    const today = new Date();
    dateEl.textContent = today.toLocaleDateString("en-US", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }

  // ===== Sidebar Navigation Handling =====
  const menuLinks = document.querySelectorAll(".menu a[data-link]");
  const sections = document.querySelectorAll(".section");

  menuLinks.forEach(link => {
    link.addEventListener("click", e => {
      const target = link.getAttribute("data-link");

      if (!target || target === "logout") return;

      e.preventDefault();

      // Remove active class from all links and sections
      menuLinks.forEach(l => l.classList.remove("active"));
      sections.forEach(s => s.classList.remove("active"));

      // Activate selected link and section
      link.classList.add("active");
      const targetSection = document.getElementById(target);
      if (targetSection) {
        targetSection.classList.add("active");
      }
    });
  });

  // ===== Logout Button Spinner =====
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault(); // Prevent immediate redirect
      logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
      setTimeout(() => {
        window.location.href = "logout.php";
      }, 800); // Slight delay for visual feedback
    });
  }
});
