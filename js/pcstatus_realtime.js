// ================== Digital Clock ==================
function updateClock() {
  const now = new Date();
  const clock = document.getElementById("clock");
  if (clock) clock.textContent = now.toLocaleTimeString();
}
setInterval(updateClock, 1000);
updateClock();

// ================== DOMContentLoaded Handler ==================
document.addEventListener("DOMContentLoaded", function () {
  // Sidebar Navigation
  const navLinks = document.querySelectorAll(".nav-link");
  const sections = document.querySelectorAll(".section");
  navLinks.forEach(link => {
    link.addEventListener("click", function (e) {
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
    toggleSidebar.addEventListener("click", () => {
      sidebar.classList.toggle("-translate-x-full");
    });
  }

  // Flash Message
  const flash = document.getElementById("flash-message");
  if (flash) setTimeout(() => flash.style.display = 'none', 5000);

  // Notification Dropdown
  const notifBtn = document.getElementById("notif-btn");
  const notifDropdown = document.getElementById("notif-dropdown");
  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle("hidden");
    });
    document.addEventListener("click", (e) => {
      if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.add("hidden");
      }
    });
  }

  // Modal Handling
  const modal = document.getElementById("pcModal");
  const closeModal = document.getElementById("closeModal");
  if (modal && closeModal) {
    closeModal.addEventListener("click", () => modal.classList.add("hidden"));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) modal.classList.add("hidden");
    });
  }

  // Refresh PC Status Section
  document.querySelectorAll('.nav-link[data-target="pcstatus"]').forEach(link => {
    link.addEventListener('click', fetchAndRenderPCStatus);
  });
  document.getElementById('refresh-pc-status')?.addEventListener('click', fetchAndRenderPCStatus);

  // ================== Reservation Logic ==================
  const pcBoxes = document.getElementById("pc-boxes");
  const reservePcId = document.getElementById("reserve_pc_id");
  const reservationDate = document.getElementById("reservation_date");
  const timeStart = document.getElementById("reservation_time_start");
  const timeEnd = document.getElementById("reservation_time_end");
  const labSelect = document.getElementById("lab-select");
  const dateWarning = document.getElementById("date-warning");

  function isPCReserved(pcId, selectedDate, selectedStart, selectedEnd) {
    if (!window.reservedSlots || !window.reservedSlots[pcId]) return false;
    const slots = window.reservedSlots[pcId];
    return slots.some(slot => slot.date === selectedDate &&
      (!selectedStart || !selectedEnd || (selectedStart < slot.end && selectedEnd > slot.start)));
  }

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
      div.className = `border p-2 rounded text-center text-xs font-semibold ${
        unavailable ? "bg-gray-300 text-gray-500 cursor-not-allowed" : "bg-green-100 text-green-800 cursor-pointer hover:bg-green-200"
      }`;
      if (!unavailable) {
        div.addEventListener("click", function () {
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
    reservationDate.value = timeStart.value = timeEnd.value = reservePcId.value = "";
    reservationDate.setAttribute("disabled", "disabled");
    timeStart.setAttribute("disabled", "disabled");
    timeEnd.setAttribute("disabled", "disabled");
    dateWarning.classList.add("hidden");
    drawPCBoxes();
  });

  reservationDate?.addEventListener("change", () => {
    timeStart.value = timeEnd.value = reservePcId.value = "";
    dateWarning.classList.add("hidden");
    drawPCBoxes();
  });

  [timeStart, timeEnd].forEach(input => {
    input?.addEventListener("change", () => {
      reservePcId.value = "";
      drawPCBoxes();
    });
  });

  timeEnd?.addEventListener("input", () => {
    if (timeStart.value && timeEnd.value && timeStart.value >= timeEnd.value) {
      timeEnd.setCustomValidity("End time must be later than start time.");
    } else {
      timeEnd.setCustomValidity("");
    }
  });
});

// ================== Fetch and Render PC Status ==================
async function fetchAndRenderPCStatus() {
  const container = document.getElementById('pc-status-container');
  container.innerHTML = '<div class="text-center text-gray-600 py-10">Loading PC status...</div>';
  try {
    const res = await fetch('get_pc_status.php', { cache: "no-store" });
    const data = await res.json();
    renderPCStatus(data);
  } catch {
    container.innerHTML = '<div class="text-red-600 text-center">Failed to load PC status. Please refresh.</div>';
  }
}

function renderPCStatus(groupedPCs) {
  const container = document.getElementById('pc-status-container');
  container.innerHTML = '';

  Object.entries(groupedPCs).forEach(([lab, pcs]) => {
    const card = document.createElement('div');
    card.className = 'bg-white shadow rounded-xl p-5 flex flex-col justify-between mb-4';

    const header = document.createElement('div');
    header.innerHTML = `
      <h3 class="text-xl font-bold text-blue-900 mb-2">${lab}</h3>
      <p class="text-sm text-gray-500">${pcs.length} PCs in this lab</p>
    `;

    const modalBtn = document.createElement('button');
    modalBtn.className = "open-modal mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm";
    modalBtn.innerText = 'View PCs';
    modalBtn.dataset.lab = lab;
    modalBtn.dataset.pcs = JSON.stringify(pcs);

    card.appendChild(header);
    card.appendChild(modalBtn);
    container.appendChild(card);
  });

  document.querySelectorAll('.open-modal').forEach(btn => {
    btn.onclick = function () {
      const lab = btn.dataset.lab;
      const pcs = JSON.parse(btn.dataset.pcs);
      document.getElementById('modalLabName').textContent = lab;
      const modalPcList = document.getElementById('modalPcList');
      modalPcList.innerHTML = "";

      pcs.forEach(pc => {
        let color, icon;
        if (pc.status === 'available') {
          color = 'green';
          icon = 'fa-check-circle';
        } else if (pc.status === 'maintenance') {
          color = 'red';
          icon = 'fa-tools';
        } else {
          color = 'yellow';
          icon = 'fa-hourglass-half';
        }

        const pcCard = document.createElement("div");
        pcCard.className = "bg-gray-50 border rounded shadow-sm p-3 text-center";
        pcCard.innerHTML = `
          <div class="text-sm font-semibold mb-1">${pc.pc_name}</div>
          <div class="text-${color}-600 text-xs flex items-center justify-center gap-1">
            <i class="fas ${icon}"></i> ${pc.status.toUpperCase()}
          </div>
        `;
        modalPcList.appendChild(pcCard);
      });

      document.getElementById('pcModal').classList.remove("hidden");
    }
  });
}
