// Safiya's Makeover — Bridal CRM Client Interactivity
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Bootstrap Tooltips
  if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  // Mobile sidebar toggle button
  const sidebarToggle = document.getElementById("sidebarToggle");
  const sidebarBackdrop = document.getElementById("sidebarBackdrop");
  const sidebarCloseBtn = document.getElementById("sidebarCloseBtn");

  function closeSidebar() {
    document.body.classList.remove("sidebar-toggled");
  }

  function toggleSidebar(e) {
    if (e) e.preventDefault();
    document.body.classList.toggle("sidebar-toggled");
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", toggleSidebar);
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener("click", closeSidebar);
  }

  if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener("click", closeSidebar);
  }

  // Auto-close sidebar on mobile when a link is clicked
  const sidebarNavLinks = document.querySelectorAll("#sidebar-wrapper .nav-link");
  sidebarNavLinks.forEach(function (link) {
    link.addEventListener("click", function () {
      if (window.innerWidth < 992) {
        closeSidebar();
      }
    });
  });

  // Close sidebar on ESC key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && document.body.classList.contains("sidebar-toggled")) {
      closeSidebar();
    }
  });

  // Auto-dismiss alerts after 6 seconds
  const autoDismissAlerts = document.querySelectorAll(".alert-dismissible");
  autoDismissAlerts.forEach(function (alertEl) {
    setTimeout(function () {
      if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
        if (bsAlert) {
          bsAlert.close();
        }
      }
    }, 6000);
  });

  // Phone input auto-formatter for Indian numbers
  const phoneInputs = document.querySelectorAll('input[name="mobile_number"]');
  phoneInputs.forEach(function (input) {
    input.addEventListener("input", function () {
      let val = this.value.replace(/[^\d+]/g, "");
      this.value = val;
    });
  });
});

