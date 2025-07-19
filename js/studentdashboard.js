// Digital Clock (real-time)
function updateClock() {
  const now = new Date();
  const clock = document.getElementById("clock");
  if (clock) clock.textContent = now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

document.addEventListener("DOMContentLoaded", function() {
  // Sidebar & Navigation
  const navLinks = document.querySelectorAll(".nav-link");
  const sections = document.querySelectorAll(".section");
  navLinks.forEach(link => {
    link.addEventListener("click", function(e) {
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
  if (toggleSidebar) {
    toggleSidebar.addEventListener("click", function() {
      sidebar.classList.toggle("-translate-x-full");
    });
  }
  navLinks.forEach(link => {
    link.addEventListener("click", function() {
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
  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      notifDropdown.classList.toggle("hidden");
    });
    document.addEventListener("click", function(e) {
      if (
        notifDropdown && notifBtn &&
        !notifBtn.contains(e.target) &&
        !notifDropdown.contains(e.target)
      ) {
        notifDropdown.classList.add("hidden");
      }
    });
  }

  // Modal (Lab PCs)
  const modal = document.getElementById("pcModal");
  const closeModal = document.getElementById("closeModal");
  if (closeModal && modal) {
    closeModal.addEventListener("click", () => modal.classList.add("hidden"));
    modal.addEventListener("click", e => {
      if (e.target === modal) modal.classList.add("hidden");
    });
  }

  // ================== RESERVATION SECTION ==================
  // Ensure window.groupedPCs, window.reservedSlots are available!
  const labSelect = document.getElementById("lab-select");
  const pcBoxes = document.getElementById("pc-boxes");
  const reservePcId = document.getElementById("reserve_pc_id");
  const reservationDate = document.getElementById("reservation_date");
  const timeStart = document.getElementById("reservation_time_start");
  const timeEnd = document.getElementById("reservation_time_end");
  const dateWarning = document.getElementById("date-warning");

  function isPCReserved(pcId, selectedDate, selectedStart, selectedEnd) {
    if (!window.reservedSlots || !window.reservedSlots[pcId]) return false;
    const slots = window.reservedSlots[pcId];
    for (const slot of slots) {
      if (slot.date === selectedDate) {
        // If times are not selected, just block if any reservation exists
        if (!selectedStart || !selectedEnd) return true;
        // Time overlap logic: [A,B] overlaps [X,Y] iff A < Y && B > X
        if (selectedStart < slot.end && selectedEnd > slot.start) return true;
      }
    }
    return false;
  }

  // Draw PC boxes according to current selections
  function drawPCBoxes() {
    if (!labSelect || !pcBoxes) return;
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
      div.dataset.pcId = pc.id;
      div.className = `border p-2 rounded text-center text-xs font-semibold ${unavailable ? "bg-gray-300 text-gray-500 cursor-not-allowed" : "bg-green-100 text-green-800 cursor-pointer hover:bg-green-200"}`;
      if (!unavailable) {
        div.addEventListener("click", function() {
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

  if (labSelect) {
    labSelect.addEventListener("change", function() {
      reservationDate.value = "";
      timeStart.value = "";
      timeEnd.value = "";
      reservePcId.value = "";
      reservationDate.setAttribute("disabled", "disabled");
      timeStart.setAttribute("disabled", "disabled");
      timeEnd.setAttribute("disabled", "disabled");
      dateWarning.classList.add("hidden");
      drawPCBoxes();
    });
  }

  if (reservationDate) {
    reservationDate.addEventListener("change", function() {
      timeStart.value = "";
      timeEnd.value = "";
      reservePcId.value = "";
      dateWarning.classList.add("hidden");
      drawPCBoxes();
      // Date check for all PCs: if all are reserved for this date, show warning
      if (pcBoxes && pcBoxes.childNodes.length > 0) {
        let allUnavailable = true;
        pcBoxes.childNodes.forEach(div => {
          if (!div.classList.contains("bg-gray-300")) allUnavailable = false;
        });
        if (allUnavailable) {
          dateWarning.textContent = "No available PCs on this date.";
          dateWarning.classList.remove("hidden");
        }
      }
    });
  }

  [timeStart, timeEnd].forEach(inp => {
    if (inp) {
      inp.addEventListener("change", function() {
        reservePcId.value = "";
        dateWarning.classList.add("hidden");
        drawPCBoxes();
      });
    }
  });

});
