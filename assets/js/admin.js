// assets/js/admin.js

document.addEventListener("DOMContentLoaded", function () {
  // Actions Dropdown
  const actionDropdowns = document.querySelectorAll(".actions-dropdown");

  actionDropdowns.forEach((dropdown) => {
    const button = dropdown.querySelector(".btn");
    const content = dropdown.querySelector(".actions-dropdown-content");

    if (button && content) {
      // Toggle dropdown on button click
      button.addEventListener("click", function (e) {
        e.stopPropagation();
        content.classList.toggle("active");
      });

      // Close dropdown when clicking outside
      document.addEventListener("click", function (e) {
        if (!dropdown.contains(e.target)) {
          content.classList.remove("active");
        }
      });
    }
  });

  // Form Validation
  const forms = document.querySelectorAll("form:not(.filter-form)");

  forms.forEach((form) => {
    form.addEventListener("submit", function (e) {
      const requiredFields = form.querySelectorAll("[required]");
      let isValid = true;

      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add("is-invalid");

          // Add error message if not exists
          if (
            !field.nextElementSibling ||
            !field.nextElementSibling.classList.contains("error-message")
          ) {
            const errorMessage = document.createElement("div");
            errorMessage.classList.add("error-message");
            errorMessage.textContent = "This field is required";
            field.parentNode.insertBefore(errorMessage, field.nextSibling);
          }
        } else {
          field.classList.remove("is-invalid");

          // Remove error message if exists
          if (
            field.nextElementSibling &&
            field.nextElementSibling.classList.contains("error-message")
          ) {
            field.parentNode.removeChild(field.nextElementSibling);
          }
        }
      });

      if (!isValid) {
        e.preventDefault();
      }
    });
  });

  // Dismissible alerts
  const alerts = document.querySelectorAll(".alert");

  alerts.forEach((alert) => {
    // Add close button if not exists
    if (!alert.querySelector(".close-btn")) {
      const closeBtn = document.createElement("span");
      closeBtn.classList.add("close-btn");
      closeBtn.innerHTML = "&times;";
      closeBtn.style.float = "right";
      closeBtn.style.cursor = "pointer";
      closeBtn.style.marginLeft = "15px";
      alert.insertBefore(closeBtn, alert.firstChild);

      // Close alert on click
      closeBtn.addEventListener("click", function () {
        alert.style.opacity = "0";
        setTimeout(() => {
          alert.style.display = "none";
        }, 300);
      });

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        alert.style.opacity = "0";
        setTimeout(() => {
          alert.style.display = "none";
        }, 300);
      }, 5000);
    }
  });

  // Chart initialization
  const chartElements = document.querySelectorAll(".admin-chart");

  if (chartElements.length > 0 && typeof Chart !== "undefined") {
    chartElements.forEach((chartElement) => {
      const ctx = chartElement.getContext("2d");
      const dataType = chartElement.dataset.type || "line";
      const dataLabels = JSON.parse(chartElement.dataset.labels || "[]");
      const dataValues = JSON.parse(chartElement.dataset.values || "[]");
      const dataColors = JSON.parse(chartElement.dataset.colors || "[]");
      const dataTitle = chartElement.dataset.title || "";

      new Chart(ctx, {
        type: dataType,
        data: {
          labels: dataLabels,
          datasets: [
            {
              label: dataTitle,
              data: dataValues,
              backgroundColor:
                dataColors.length > 0
                  ? dataColors
                  : [
                      "rgba(255, 75, 110, 0.2)",
                      "rgba(54, 162, 235, 0.2)",
                      "rgba(255, 206, 86, 0.2)",
                      "rgba(75, 192, 192, 0.2)",
                      "rgba(153, 102, 255, 0.2)",
                    ],
              borderColor:
                dataColors.length > 0
                  ? dataColors.map((color) => color.replace("0.2", "1"))
                  : [
                      "rgba(255, 75, 110, 1)",
                      "rgba(54, 162, 235, 1)",
                      "rgba(255, 206, 86, 1)",
                      "rgba(75, 192, 192, 1)",
                      "rgba(153, 102, 255, 1)",
                    ],
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    });
  }

  // Date range picker initialization
  const dateRangePickers = document.querySelectorAll(".date-range-picker");

  if (dateRangePickers.length > 0 && typeof flatpickr !== "undefined") {
    dateRangePickers.forEach((picker) => {
      flatpickr(picker, {
        mode: "range",
        dateFormat: "Y-m-d",
        showMonths: 2,
      });
    });
  }

  // Image preview on file input change
  const fileInputs = document.querySelectorAll(
    'input[type="file"][data-preview]'
  );

  fileInputs.forEach((input) => {
    const previewEl = document.getElementById(input.dataset.preview);

    if (previewEl) {
      input.addEventListener("change", function () {
        if (this.files && this.files[0]) {
          const reader = new FileReader();

          reader.onload = function (e) {
            previewEl.src = e.target.result;
          };

          reader.readAsDataURL(this.files[0]);
        }
      });
    }
  });
});
