document.addEventListener("DOMContentLoaded", () => {
  const dateEl = document.getElementById("date");
  const today = new Date();
  dateEl.textContent = today.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  const menuLinks = document.querySelectorAll(".menu a");
  const sections = document.querySelectorAll(".section");

  menuLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();

      // Remove active class from all links
      menuLinks.forEach((l) => l.classList.remove("active"));

      // Add active class to clicked link
      link.classList.add("active");

      // Get target section ID
      const target = link.getAttribute("data-link");

      // Show the target section and hide others
      sections.forEach((sec) => {
        sec.classList.remove("active");
        if (sec.id === target) {
          sec.classList.add("active");
        }
      });
    });
  });
});
document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".menu a");
  const sections = document.querySelectorAll(".section");

  links.forEach(link => {
    link.addEventListener("click", function(e) {
      const target = this.getAttribute("data-link");

      // Skip for actual logout
      if (target === "logout") return;

      e.preventDefault();

      // Remove active class from all
      links.forEach(l => l.classList.remove("active"));
      sections.forEach(s => s.classList.remove("active"));

      // Activate selected
      this.classList.add("active");
      document.getElementById(target).classList.add("active");
    });
  });

  // Optional: Add spinner when logout is clicked
  const logoutBtn = document.getElementById("logoutBtn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", () => {
      logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
    });
  }
});
