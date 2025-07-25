// ========================
// Student Dashboard JS (Full Script, Updated July 2025)
// ========================

// === Digital Clock ===
function updateClock() {
  const now = new Date();
  const clock = document.getElementById("clock");
  if (clock) clock.textContent = now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

// === DOM Content Loaded ===
document.addEventListener("DOMContentLoaded", function () {

  // Sidebar Navigation
  const navLinks = document.querySelectorAll(".nav-link");
  const sections = document.querySelectorAll(".section");
  const sidebar = document.getElementById("sidebar");
  const toggleSidebar = document.getElementById("toggleSidebar");

  navLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();
      const target = link.dataset.target;

      sections.forEach((sec) => sec.classList.add("hidden"));
      document.getElementById(target)?.classList.remove("hidden");

      navLinks.forEach((l) => l.classList.remove("bg-blue-900", "font-bold"));
      link.classList.add("bg-blue-900", "font-bold");

      if (window.innerWidth < 768) {
        sidebar.classList.add("-translate-x-full");
      }
    });
  });

  if (toggleSidebar) {
    toggleSidebar.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
    });
  }

  // Flash Message Auto-hide
  const flash = document.getElementById("flash-message");
  if (flash) setTimeout(() => (flash.style.display = "none"), 5000);

  // Notification Dropdown
  const notifBtn = document.getElementById("notif-btn");
  const notifDropdown = document.getElementById("notif-dropdown");
  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle("hidden");
    });
    document.addEventListener("click", (e) => {
      if (!notifDropdown.contains(e.target)) {
        notifDropdown.classList.add("hidden");
      }
    });
  }

  // Modal Management (PC Details)
  const modal = document.getElementById("pcModal");
  const closeModal = document.getElementById("closeModal");
  if (closeModal && modal) {
    closeModal.addEventListener("click", () => modal.classList.add("hidden"));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.add("hidden");
    });
  }

  // === PC Reservation Section ===
  const labSelect = document.getElementById("lab-select");
  const pcBoxes = document.getElementById("pc-boxes");
  const reservePcId = document.getElementById("reserve_pc_id");
  const reservationDate = document.getElementById("reservation_date");
  const timeStart = document.getElementById("reservation_time_start");
  const timeEnd = document.getElementById("reservation_time_end");
  const dateWarning = document.getElementById("date-warning");

  function isPCReserved(pcId, selectedDate, selectedStart, selectedEnd) {
    const slots = window.reservedSlots?.[pcId] || [];
    return slots.some(slot => 
      slot.date === selectedDate && 
      (!selectedStart || !selectedEnd || (selectedStart < slot.end && selectedEnd > slot.start))
    );
  }

  function drawPCBoxes() {
    const lab = labSelect.value;
    const pcs = window.groupedPCs?.[lab] || [];
    const selectedDate = reservationDate.value;
    const selectedStart = timeStart.value;
    const selectedEnd = timeEnd.value;

    pcBoxes.innerHTML = "";
    pcBoxes.classList.toggle("hidden", pcs.length === 0);

    pcs.forEach(pc => {
      let unavailable = pc.status !== "available";

      if (!unavailable && selectedDate && selectedStart && selectedEnd) {
        unavailable = isPCReserved(pc.id, selectedDate, selectedStart, selectedEnd);
      }

      const div = document.createElement("div");
      div.textContent = pc.pc_name;

      div.className = `border p-2 rounded text-center text-xs font-semibold ${
        unavailable
          ? (pc.status === 'maintenance' ? "bg-red-200 text-red-800 cursor-not-allowed" : "bg-gray-300 text-gray-500 cursor-not-allowed")
          : "bg-green-100 text-green-800 cursor-pointer hover:bg-green-200"
      }`;

      if (!unavailable) {
        div.addEventListener("click", () => {
          reservePcId.value = pc.id;
          [...pcBoxes.children].forEach(c => c.classList.remove("ring-2", "ring-blue-600"));
          div.classList.add("ring-2", "ring-blue-600");
          reservationDate.disabled = false;
          timeStart.disabled = false;
          timeEnd.disabled = false;
        });
      } else {
        div.style.pointerEvents = "none";
      }
      pcBoxes.appendChild(div);
    });
  }

  labSelect?.addEventListener("change", () => {
    reservationDate.value = "";
    timeStart.value = "";
    timeEnd.value = "";
    reservePcId.value = "";
    reservationDate.disabled = true;
    timeStart.disabled = true;
    timeEnd.disabled = true;
    dateWarning.classList.add("hidden");
    drawPCBoxes();
  });

  reservationDate?.addEventListener("change", () => {
    timeStart.value = "";
    timeEnd.value = "";
    reservePcId.value = "";
    dateWarning.classList.add("hidden");
    drawPCBoxes();

    const allUnavailable = [...pcBoxes.children].every(div => 
      div.classList.contains("cursor-not-allowed")
    );

    if (allUnavailable) {
      dateWarning.textContent = "No available PCs on this date.";
      dateWarning.classList.remove("hidden");
    }
  });

  [timeStart, timeEnd].forEach(inp => {
    inp?.addEventListener("change", () => {
      reservePcId.value = "";
      dateWarning.classList.add("hidden");
      drawPCBoxes();
    });
  });

  // === PC Status Rendering ===
  function renderPCStatus() {
    const container = document.getElementById('pc-status-container');
    container.innerHTML = '';

    Object.entries(window.groupedPCs).forEach(([lab, pcs]) => {
      const labCard = document.createElement('div');
      labCard.className = 'bg-white shadow rounded p-4';

      const title = document.createElement('h3');
      title.textContent = lab;
      title.className = 'font-bold text-lg mb-3';
      labCard.appendChild(title);

      pcs.forEach(pc => {
        const pcItem = document.createElement('div');
        pcItem.className = 'mb-2 text-sm';

        let statusColor = {
          'available': 'text-green-600',
          'reserved': 'text-yellow-600',
          'in_use': 'text-blue-600',
          'maintenance': 'text-red-600'
        }[pc.status] || 'text-gray-600';

        pcItem.innerHTML = `<span class="font-medium">${pc.pc_name}</span>: <span class="${statusColor}">${pc.status.toUpperCase()}</span>`;
        labCard.appendChild(pcItem);
      });

      container.appendChild(labCard);
    });
  }

  // Initial PC Status Load
  renderPCStatus();

  // Refresh button logic
  document.getElementById('refresh-pc-status')?.addEventListener('click', renderPCStatus);

});
