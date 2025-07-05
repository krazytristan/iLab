document.addEventListener("DOMContentLoaded", () => {
  const dateEl = document.getElementById("date");
  const today = new Date();
  dateEl.textContent = today.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });

  const sidebar = document.querySelector(".sidebar");
  const hamburger = document.getElementById("hamburger");
  const overlay = document.getElementById("overlay");
  const header = document.querySelector(".main-header");
  const menuLinks = document.querySelectorAll(".menu a");
  const sections = document.querySelectorAll(".section");
  const body = document.body;

  // Toggle sidebar and overlay
  function toggleSidebar(show) {
    sidebar.classList.toggle("show", show);
    overlay.classList.toggle("show", show);
    body.classList.toggle("overlay-active", show);
  }

  // Hamburger click
  hamburger.addEventListener("click", () => {
    const isVisible = sidebar.classList.contains("show");
    toggleSidebar(!isVisible);
  });

  // Overlay click closes sidebar
  overlay.addEventListener("click", () => {
    toggleSidebar(false);
  });

  // ESC key closes sidebar
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      toggleSidebar(false);
    }
  });

  // Section switching
  menuLinks.forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const target = link.getAttribute("data-link");

      // Save current section to localStorage
      localStorage.setItem("activeSection", target);

      // Update active menu link
      menuLinks.forEach((l) => l.classList.remove("active"));
      link.classList.add("active");

      // Show the selected section
      sections.forEach((section) => {
        section.classList.remove("active");
        if (section.id === target) {
          section.classList.add("active");
        }
      });

      // Close sidebar on mobile
      if (window.innerWidth <= 768) {
        toggleSidebar(false);
      }
    });
  });

  // Restore last active section
  const savedSection = localStorage.getItem("activeSection");
  if (savedSection) {
    const savedLink = document.querySelector(`.menu a[data-link="${savedSection}"]`);
    if (savedLink) savedLink.click();
  }

  // Auto-hide header on scroll
  let lastScroll = 0;
  window.addEventListener("scroll", () => {
    const current = window.pageYOffset;
    if (current > lastScroll) {
      header.classList.add("hidden");
    } else {
      header.classList.remove("hidden");
    }
    lastScroll = current <= 0 ? 0 : current;
  });
});
