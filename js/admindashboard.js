document.addEventListener("DOMContentLoaded", () => {
  // === Date Display ===
  const dateEl = document.getElementById("date");
  if (dateEl) {
    dateEl.textContent = new Date().toLocaleDateString("en-US", {
      weekday: "long", year: "numeric", month: "long", day: "numeric"
    });
  }

  // === Sidebar Toggle ===
  const toggleSidebar = document.getElementById("toggleSidebar");
  const sidebar = document.getElementById("sidebar");
  if (toggleSidebar && sidebar) {
    toggleSidebar.addEventListener("click", () => {
      sidebar.classList.toggle("mobile-visible");
      if (!document.getElementById("sidebarOverlay")) {
        const overlay = document.createElement("div");
        overlay.id = "sidebarOverlay";
        overlay.className = "fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden";
        overlay.onclick = () => {
          sidebar.classList.remove("mobile-visible");
          overlay.remove();
        };
        document.body.appendChild(overlay);
      } else {
        document.getElementById("sidebarOverlay").remove();
      }
    });
  }

  // === Dark Mode Toggle ===
  const darkToggle = document.getElementById("darkModeToggle");
  const darkIcon = document.getElementById("darkIcon");
  const darkLabel = document.getElementById("darkLabel");
  const enableDarkMode = () => {
    document.body.classList.add("dark");
    darkIcon.classList.replace("fa-moon", "fa-sun");
    darkLabel.textContent = "Light Mode";
    localStorage.setItem("darkMode", "enabled");
  };
  const disableDarkMode = () => {
    document.body.classList.remove("dark");
    darkIcon.classList.replace("fa-sun", "fa-moon");
    darkLabel.textContent = "Dark Mode";
    localStorage.setItem("darkMode", "disabled");
  };
  if (localStorage.getItem("darkMode") === "enabled") enableDarkMode();
  darkToggle?.addEventListener("click", () => {
    document.body.classList.contains("dark") ? disableDarkMode() : enableDarkMode();
  });

  // === Sidebar Navigation ===
  document.querySelectorAll(".menu a[data-link]").forEach(link => {
    link.addEventListener("click", e => {
      e.preventDefault();
      const target = link.getAttribute("data-link");
      document.querySelectorAll(".menu a").forEach(l => l.classList.remove("active"));
      link.classList.add("active");
      document.querySelectorAll(".section").forEach(section => {
        section.classList.remove("active");
        section.style.display = 'none';
        if (section.id === target) {
          section.classList.add("active");
          section.style.display = 'block';
        }
      });
    });
  });

  // === Notification Dropdown and Mark as Read ===
  const notifToggle = document.getElementById("notifToggle");
  const notifDropdown = document.getElementById("notifDropdown");
  const badge = notifToggle?.querySelector('span'); // Red badge
  let notifMarked = false;

  if (notifToggle && notifDropdown) {
    notifToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      notifDropdown.classList.toggle('hidden');
      // Mark as read (remove badge) only if visible, badge present, and not already marked
      if (!notifDropdown.classList.contains('hidden') && badge && badge.style.display !== 'none' && !notifMarked) {
        fetch('/ilab/mark_admin_notif_read.php', { method: 'POST' })
          .then(res => res.json())
          .then(() => {
            badge.style.display = 'none';
            notifMarked = true;
          });
      }
    });
    // Hide dropdown when clicking outside
    document.addEventListener('click', (event) => {
      if (!notifDropdown.contains(event.target) && !notifToggle.contains(event.target)) {
        notifDropdown.classList.add('hidden');
      }
    });
  }

  // === Reservation Search Filter ===
  const resSearch = document.getElementById('resSearch');
  if (resSearch) {
    resSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#resTable tbody tr').forEach(row => {
        row.style.display = row.dataset.row.includes(term) ? '' : 'none';
      });
    });
  }

  // === User Log Search ===
  const logSearch = document.getElementById('logSearch');
  if (logSearch) {
    logSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#logTable tbody tr').forEach(row => {
        row.style.display = row.dataset.log.includes(term) ? '' : 'none';
      });
    });
  }

  // === Maintenance Table Search ===
  const maintSearch = document.getElementById('maintSearch');
  if (maintSearch) {
    maintSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#maintTable tbody tr').forEach(row => {
        row.style.display = row.dataset.maint.includes(term) ? '' : 'none';
      });
    });
  }

  // === Approve/Cancel Reservation Buttons (AJAX) ===
  document.querySelectorAll('.res-action').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const action = button.dataset.action;
      button.disabled = true;
      fetch('reservation_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&action=${action}`
      })
      .then(res => res.json())
      .then(data => {
        Swal.fire({
          icon: data.success ? 'success' : 'error',
          title: data.success ? 'Success' : 'Error',
          text: data.msg,
          confirmButtonColor: '#2563eb'
        }).then(() => {
          if (data.success) location.reload();
          else button.disabled = false;
        });
      })
      .catch(() => {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Something went wrong. Please try again.',
        });
        button.disabled = false;
      });
    });
  });
});

// Prompt for reason before reject/cancel
function promptReason(btn) {
  const reason = prompt('Please provide a reason for this action (it will be sent to the student):');
  if (!reason) return false;
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'reason';
  input.value = reason;
  btn.form.appendChild(input);
  return true;
}
// User Log Search
const logSearch = document.getElementById('logSearch');
if (logSearch) {
  logSearch.addEventListener('input', function () {
    const term = this.value.toLowerCase();
    document.querySelectorAll('#logTable tbody tr').forEach(row => {
      row.style.display = row.dataset.log.includes(term) ? '' : 'none';
    });
  });
}
 const notifToggle = document.getElementById("notifToggle");
    const notifDropdown = document.getElementById("notifDropdown");

    notifToggle.addEventListener("click", () => {
      notifDropdown.classList.toggle("hidden");
    });

    document.addEventListener("click", (e) => {
      if (!notifToggle.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.add("hidden");
      }
    });

  function handleNotificationClick(id, message) {
    // Mark notification as read
    fetch('../adminpage/mark_notification_read.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'id=' + encodeURIComponent(id)
    }).then(() => {
      // Determine where to redirect
      let lowerMsg = message.toLowerCase();
      if (lowerMsg.includes('reservation')) {
        window.location.href = '#reservations';
      } else if (lowerMsg.includes('maintenance')) {
        window.location.href = '#maintenance';
      } else if (lowerMsg.includes('report')) {
        window.location.href = '#reports';
      } else {
        window.location.href = '#dashboard';
      }
    });
  }

