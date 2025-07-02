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
      menuLinks.forEach((l) => l.classList.remove("active"));
      link.classList.add("active");
      const target = link.dataset.link;
      sections.forEach((sec) => {
        sec.classList.remove("active");
        if (sec.id === target) sec.classList.add("active");
      });
    });
  });
});
