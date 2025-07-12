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
      e.preventDefault(); 
      logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging out...';
      setTimeout(() => {
        window.location.href = "logout.php";
      }, 800);
    });
  }

  // ===== Sidebar Mobile Toggle =====
  const sidebar = document.getElementById("sidebar");
  const toggleSidebar = document.getElementById("toggleSidebar");
  
  if (toggleSidebar && sidebar) {
    toggleSidebar.addEventListener("click", () => {
      sidebar.classList.toggle("mobile-visible");
      document.body.classList.toggle('sidebar-open');
    });
  }

  // ===== Dark Mode Toggle =====
  const darkModeToggle = document.getElementById('darkModeToggle');
  const darkIcon = document.getElementById('darkIcon');
  const enableDarkMode = () => {
    document.body.classList.add("dark");
    darkIcon.classList.replace("fa-moon", "fa-sun");
    localStorage.setItem("darkMode", "enabled");
  };
  const disableDarkMode = () => {
    document.body.classList.remove("dark");
    darkIcon.classList.replace("fa-sun", "fa-moon");
    localStorage.setItem("darkMode", "disabled");
  };
  if (localStorage.getItem("darkMode") === "enabled") enableDarkMode();

  darkModeToggle?.addEventListener("click", () => {
    document.body.classList.contains("dark") ? disableDarkMode() : enableDarkMode();
  });

  // ===== Notification Dropdown Toggle =====
  const notifToggle = document.getElementById("notifToggle");
  const notifDropdown = document.getElementById("notifDropdown");
  
  notifToggle?.addEventListener("click", (e) => {
    e.stopPropagation();
    notifDropdown?.classList.toggle("hidden");
  });

  document.addEventListener("click", function (event) {
    if (!notifToggle.contains(event.target) && !notifDropdown.contains(event.target)) {
      notifDropdown?.classList.add("hidden");
    }
  });

  // ===== Reservation Search Filter =====
  const resSearch = document.getElementById('resSearch');
  if (resSearch) {
    resSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#resTable tbody tr').forEach(row => {
        row.style.display = row.dataset.row.includes(term) ? '' : 'none';
      });
    });
  }

  // ===== User Log Search Filter =====
  const logSearch = document.getElementById('logSearch');
  if (logSearch) {
    logSearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#logTable tbody tr').forEach(row => {
        row.style.display = row.dataset.log.includes(term) ? '' : 'none';
      });
    });
  }

  // ===== Faculty Search Filter ===== (Newly Added)
  const facultySearch = document.getElementById('facultySearch');
  if (facultySearch) {
    facultySearch.addEventListener('input', function () {
      const term = this.value.toLowerCase();
      document.querySelectorAll('#facultyTable tbody tr').forEach(row => {
        row.style.display = row.dataset.faculty.includes(term) ? '' : 'none';
      });
    });
  }

  // ===== Approve/Cancel Reservation Actions =====
  document.querySelectorAll('.res-action').forEach(button => {
    button.addEventListener('click', () => {
      const id = button.dataset.id;
      const action = button.dataset.action;

      Swal.fire({
        title: `Confirm ${action}?`,
        text: `Are you sure you want to ${action} this reservation?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes'
      }).then((result) => {
        if (result.isConfirmed) {
          button.disabled = true;
          fetch('actions/reservation_action.php', {
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
            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
            button.disabled = false;
          });
        }
      });
    });
  });
});
