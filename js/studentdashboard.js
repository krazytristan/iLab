// studentdashboard.js

// Digital Clock (real-time)
function updateClock() {
  const now = new Date();
  const clock = document.getElementById("clock");
  if (clock) clock.textContent = now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

document.addEventListener("DOMContentLoaded", () => {
  // Sidebar & Navigation
  const navLinks = document.querySelectorAll(".nav-link");
  const sections = document.querySelectorAll(".section");
  navLinks.forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const target = link.dataset.target;
      sections.forEach(sec => sec.classList.add("hidden"));
      document.getElementById(target)?.classList.remove("hidden");
      navLinks.forEach(l => l.classList.remove("bg-blue-900", "font-bold"));
      link.classList.add("bg-blue-900", "font-bold");
    });
  });

  // Sidebar mobile toggle
  const toggleSidebar = document.getElementById("toggleSidebar");
  const sidebar = document.getElementById("sidebar");
  toggleSidebar?.addEventListener("click", () => {
    sidebar.classList.toggle("-translate-x-full");
  });
  navLinks.forEach(link => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 768) {
        sidebar.classList.add("-translate-x-full");
      }
    });
  });

  // Flash message timeout
  const flash = document.getElementById("flash-message");
  if (flash) setTimeout(() => flash.style.display = 'none', 5000);

  // Notification dropdown
  const notifBtn = document.getElementById("notif-btn");
  const notifDropdown = document.getElementById("notif-dropdown");
  notifBtn?.addEventListener("click", (e) => {
    e.stopPropagation();
    notifDropdown.classList.toggle("hidden");
  });
  document.addEventListener("click", (e) => {
    if (
      notifDropdown && notifBtn &&
      !notifBtn.contains(e.target) &&
      !notifDropdown.contains(e.target)
    ) {
      notifDropdown.classList.add("hidden");
    }
  });

  // Modal (Lab PCs)
  const modal = document.getElementById("pcModal");
  const closeModal = document.getElementById("closeModal");
  closeModal?.addEventListener("click", () => modal.classList.add("hidden"));
  modal?.addEventListener("click", e => {
    if (e.target === modal) modal.classList.add("hidden");
  });
});
// reservepc.js

// Make sure groupedPCs and reservedDatesPerPC are loaded as global variables from PHP

document.addEventListener("DOMContentLoaded", () => {
  const pcBoxes = document.getElementById("pc-boxes");
  const labSelect = document.getElementById("lab-select");
  const reservePcId = document.getElementById("reserve_pc_id");
  const reservationDate = document.getElementById("reservation_date");
  const timeStart = document.getElementById("reservation_time_start");
  const timeEnd = document.getElementById("reservation_time_end");
  const dateWarning = document.getElementById("date-warning");
  let selectedPC = null;

  if (labSelect) {
    labSelect.addEventListener("change", () => {
      const lab = labSelect.value;
      pcBoxes.innerHTML = "";
      reservePcId.value = "";
      reservationDate.value = "";
      reservationDate.setAttribute("disabled", "disabled");
      timeStart.value = "";
      timeEnd.value = "";
      timeStart.setAttribute("disabled", "disabled");
      timeEnd.setAttribute("disabled", "disabled");
      dateWarning.classList.add("hidden");

      if (!lab || !window.groupedPCs[lab] || window.groupedPCs[lab].length === 0) {
        pcBoxes.classList.add("hidden");
        return;
      }
      pcBoxes.classList.remove("hidden");

      window.groupedPCs[lab].forEach(pc => {
        const box = document.createElement("div");
        box.setAttribute("data-pcid", pc.id);
        box.className = `cursor-pointer p-2 text-center text-xs rounded font-semibold ${
          pc.status === 'available'
            ? 'bg-green-100 text-green-800'
            : pc.status === 'in_use'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-red-100 text-red-800'
        }`;
        box.textContent = pc.pc_name;
        if (pc.status === 'available') {
          box.addEventListener("click", () => {
            reservePcId.value = pc.id;
            document.querySelectorAll("#pc-boxes div").forEach(el =>
              el.classList.remove("ring", "ring-2", "ring-blue-600")
            );
            box.classList.add("ring", "ring-2", "ring-blue-600");
            reservationDate.removeAttribute("disabled");
            timeStart.removeAttribute("disabled");
            timeEnd.removeAttribute("disabled");
            selectedPC = pc.id;
            // Reserved date logic
            const reservedDates = (window.reservedDatesPerPC && window.reservedDatesPerPC[selectedPC]) || [];
            reservationDate.oninput = function () {
              if (reservedDates.includes(this.value)) {
                dateWarning.textContent = "That date is already reserved for this PC.";
                dateWarning.classList.remove("hidden");
                this.value = "";
              } else {
                dateWarning.classList.add("hidden");
              }
            };
          });
        }
        pcBoxes.appendChild(box);
      });
    });
  }
  if (labSelect) {
    labSelect.addEventListener("change", () => {
      reservationDate.value = "";
      timeStart.value = "";
      timeEnd.value = "";
      reservationDate.setAttribute("disabled", "disabled");
      timeStart.setAttribute("disabled", "disabled");
      timeEnd.setAttribute("disabled", "disabled");
      dateWarning.classList.add("hidden");
    });
  }
});
