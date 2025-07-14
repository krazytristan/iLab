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
  // Modal logic for dynamically added buttons
  document.querySelectorAll('.open-modal').forEach(btn => {
    btn.onclick = function () {
      const lab = btn.dataset.lab;
      const pcs = JSON.parse(btn.dataset.pcs);
      document.getElementById('modalLabName').textContent = lab;
      const modalPcList = document.getElementById('modalPcList');
      modalPcList.innerHTML = "";
      pcs.forEach(pc => {
        const color = pc.status === 'available' ? 'green' : (pc.status === 'in_use' ? 'yellow' : 'red');
        const icon = pc.status === 'available' ? 'fa-check-circle' : (pc.status === 'in_use' ? 'fa-hourglass-half' : 'fa-times-circle');
        const pcCard = document.createElement("div");
        pcCard.className = "bg-gray-50 border rounded shadow-sm p-3 text-center";
        pcCard.innerHTML = `
          <div class="text-sm font-semibold mb-1">${pc.pc_name}</div>
          <div class="text-${color}-600 text-xs flex items-center justify-center gap-1">
            <i class="fas ${icon}"></i> ${pc.status.replace('_', ' ').toUpperCase()}
          </div>
        `;
        modalPcList.appendChild(pcCard);
      });
      document.getElementById('pcModal').classList.remove("hidden");
    }
  });
}

async function fetchAndRenderPCStatus() {
  const container = document.getElementById('pc-status-container');
  container.innerHTML = '<div class="col-span-3 text-center text-gray-600 py-10">Loading PC status...</div>';
  try {
    const res = await fetch('get_pc_status.php', { cache: "no-store" });
    if (!res.ok) throw new Error("Failed to fetch PC status");
    const data = await res.json();
    renderPCStatus(data);
  } catch {
    container.innerHTML = '<div class="col-span-3 text-center text-red-600">Failed to load PC status. Please refresh.</div>';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Load when opening PC Status section or when clicking Refresh
  document.querySelectorAll('.nav-link[data-target="pcstatus"]').forEach(link => {
    link.addEventListener('click', fetchAndRenderPCStatus);
  });
  const refreshBtn = document.getElementById('refresh-pc-status');
  refreshBtn?.addEventListener('click', fetchAndRenderPCStatus);
});
// Reserve PC: Enable time fields after selecting date and PC
document.addEventListener("DOMContentLoaded", () => {
  const pcBoxes = document.getElementById("pc-boxes");
  const reservePcId = document.getElementById("reserve_pc_id");
  const reservationDate = document.getElementById("reservation_date");
  const timeStart = document.getElementById("reservation_time_start");
  const timeEnd = document.getElementById("reservation_time_end");
  const dateWarning = document.getElementById("date-warning");
  let selectedPC = null;

  function enableTimeInputs() {
    if (reservationDate.value && selectedPC) {
      timeStart.removeAttribute("disabled");
      timeEnd.removeAttribute("disabled");
    } else {
      timeStart.setAttribute("disabled", "disabled");
      timeEnd.setAttribute("disabled", "disabled");
      timeStart.value = "";
      timeEnd.value = "";
    }
  }

  reservationDate.addEventListener("input", enableTimeInputs);

  // Inside your box-creation code
  // (Replace your box.onclick with this to also enable time when a box is clicked)
  document.addEventListener('click', function (event) {
    if (event.target.closest('#pc-boxes div')) {
      selectedPC = reservePcId.value;
      enableTimeInputs();
    }
  });

  // You may also want to validate time (start < end)
  timeEnd.addEventListener("input", () => {
    if (timeStart.value && timeEnd.value && timeStart.value >= timeEnd.value) {
      timeEnd.setCustomValidity("End time must be later than start time.");
    } else {
      timeEnd.setCustomValidity("");
    }
  });
});
