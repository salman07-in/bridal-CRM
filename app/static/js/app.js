// Safiya's Makeover — Bridal CRM Client Interactivity
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Bootstrap Tooltips
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Mobile sidebar toggle button
  const sidebarToggle = document.getElementById("sidebarToggle");
  if (sidebarToggle) {
    sidebarToggle.addEventListener("click", function (e) {
      e.preventDefault();
      document.body.classList.toggle("sidebar-toggled");
    });
  }

  // Auto-dismiss alerts after 6 seconds
  const autoDismissAlerts = document.querySelectorAll(".alert-dismissible");
  autoDismissAlerts.forEach(function (alertEl) {
    setTimeout(function () {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(alertEl);
      if (bsAlert) {
        bsAlert.close();
      }
    }, 6000);
  });

  // Quick search debounce helper
  const searchInput = document.getElementById("customerQuickSearch");
  if (searchInput) {
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        this.form.submit();
      }
    });
  }

  // Phone input auto-formatter for Indian numbers
  const phoneInputs = document.querySelectorAll('input[name="mobile_number"]');
  phoneInputs.forEach(function (input) {
    input.addEventListener("input", function (e) {
      let val = this.value.replace(/[^\d+]/g, "");
      this.value = val;
    });
  });
});
