// assets/js/notifications.js

// Notification settings from local storage
let notificationSoundEnabled =
  localStorage.getItem("notification_sound") !== "false";
let browserNotificationsEnabled =
  localStorage.getItem("browser_notifications") !== "false";

// DOM Elements
const notificationBadge = document.getElementById("notification-badge");
const notificationsList = document.getElementById("notifications-list");
const notificationButton = document.getElementById("notification-button");
const notificationPanel = document.getElementById("notification-panel");
const markAllReadBtn = document.getElementById("mark-all-read");
const clearAllBtn = document.getElementById("clear-all-notifications");
const soundToggle = document.getElementById("notification-sound-toggle");
const browserToggle = document.getElementById("browser-notifications-toggle");
const notificationSound = document.getElementById("notification-sound");

// Initialize notification system
function initNotifications() {
  // Load settings
  if (soundToggle) {
    soundToggle.checked = notificationSoundEnabled;
    soundToggle.addEventListener("change", function () {
      notificationSoundEnabled = this.checked;
      localStorage.setItem("notification_sound", this.checked);
    });
  }

  if (browserToggle) {
    browserToggle.checked = browserNotificationsEnabled;
    browserToggle.addEventListener("change", function () {
      browserNotificationsEnabled = this.checked;
      localStorage.setItem("browser_notifications", this.checked);

      // Request permission for browser notifications
      if (this.checked && Notification.permission !== "granted") {
        Notification.requestPermission();
      }
    });
  }

  // Toggle notification panel
  if (notificationButton) {
    notificationButton.addEventListener("click", function () {
      notificationPanel.classList.toggle("show");
      loadNotifications();
    });

    // Close panel when clicking outside
    document.addEventListener("click", function (e) {
      if (
        !notificationButton.contains(e.target) &&
        !notificationPanel.contains(e.target)
      ) {
        notificationPanel.classList.remove("show");
      }
    });
  }

  // Mark all as read
  if (markAllReadBtn) {
    markAllReadBtn.addEventListener("click", function () {
      fetch("notification_api.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=mark_all_read",
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            loadNotifications();
            updateNotificationBadge(0);
          }
        });
    });
  }

  // Clear all notifications
  if (clearAllBtn) {
    clearAllBtn.addEventListener("click", function () {
      if (confirm("Are you sure you want to clear all notifications?")) {
        fetch("notification_api.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: "action=clear_all",
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              loadNotifications();
              updateNotificationBadge(0);
            }
          });
      }
    });
  }

  // Start checking for notifications
  startNotificationPolling();
}

// Load notifications into panel
function loadNotifications() {
  if (notificationsList) {
    notificationsList.innerHTML =
      '<div class="notification-loading">Loading notifications...</div>';

    fetch("notification_api.php?action=get_notifications")
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          if (data.notifications.length === 0) {
            notificationsList.innerHTML =
              '<div class="empty-notifications">No notifications yet</div>';
          } else {
            notificationsList.innerHTML = "";
            data.notifications.forEach((notification) => {
              const item = createNotificationItem(notification);
              notificationsList.appendChild(item);
            });
          }
        }
      })
      .catch((error) => {
        console.error("Error loading notifications:", error);
        notificationsList.innerHTML =
          '<div class="empty-notifications">Error loading notifications</div>';
      });
  }
}

// Create notification item element
function createNotificationItem(notification) {
  const item = document.createElement("div");
  item.className = `notification-item ${
    notification.is_read === "0" ? "unread" : ""
  }`;

  // Create avatar
  const avatar = document.createElement("div");
  avatar.className = "notification-avatar";

  if (notification.sender_pic) {
    const img = document.createElement("img");
    img.src = notification.sender_pic;
    img.alt = notification.sender_name || "User";
    avatar.appendChild(img);
  } else {
    const placeholder = document.createElement("div");
    placeholder.className = "avatar-placeholder";
    placeholder.textContent = notification.sender_name
      ? notification.sender_name.charAt(0)
      : "C";
    avatar.appendChild(placeholder);
  }

  // Create content
  const content = document.createElement("div");
  content.className = "notification-content";

  const header = document.createElement("div");
  header.className = "notification-header";

  const time = document.createElement("div");
  time.className = "notification-time";
  time.textContent = notification.time_display;

  const message = document.createElement("div");
  message.className = "notification-message";
  message.textContent = notification.content;

  content.appendChild(header);
  header.appendChild(time);
  content.appendChild(message);

  // Add click handler
  item.addEventListener("click", function () {
    markNotificationRead(notification.id);

    // Handle navigation based on type
    if (notification.type === "message" && notification.related_id) {
      window.location.href = "chat?session_id=" + notification.related_id;
    } else if (notification.type === "like" && notification.related_id) {
      window.location.href = "dashboard?page=menfess";
    } else if (notification.type === "match" && notification.related_id) {
      window.location.href = "dashboard?page=matches";
    }
  });

  // Assemble the item
  item.appendChild(avatar);
  item.appendChild(content);

  return item;
}

// Mark single notification as read
function markNotificationRead(notificationId) {
  fetch("notification_api.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=mark_read&notification_id=${notificationId}`,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        checkNotifications();
      }
    });
}

// Play notification sound
function playNotificationSound() {
  if (notificationSoundEnabled && notificationSound) {
    notificationSound.play().catch((e) => {
      console.log("Error playing notification sound:", e);
    });
  }
}

// Show browser notification
function showBrowserNotification(title, body) {
  if (browserNotificationsEnabled && Notification.permission === "granted") {
    const notification = new Notification(title, {
      body: body,
      icon: "assets/images/cupid_nobg.png",
    });

    notification.onclick = function () {
      window.focus();
      notification.close();
    };
  }
}

// Check for new notifications
function checkNotifications() {
  fetch("notification_api.php?action=get_count")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        updateNotificationBadge(data.count);
      }
    })
    .catch((error) => {
      console.error("Error checking notifications:", error);
    });
}

// Update notification badge/icon
function updateNotificationBadge(count) {
  if (notificationBadge) {
    if (count > 0) {
      notificationBadge.textContent = count;
      notificationBadge.style.display = "flex";

      // Play notification sound if it's enabled
      if (notificationSoundEnabled) {
        playNotificationSound();
      }

      // Show browser notification
      if (
        browserNotificationsEnabled &&
        Notification.permission === "granted"
      ) {
        showBrowserNotification(
          "Cupid",
          "You have " + count + " new notification(s)"
        );
      }
    } else {
      notificationBadge.style.display = "none";
    }
  }
}

// Show toast notification
function showToast(message) {
  const toast = document.getElementById("toast-notification");
  const toastMessage = document.getElementById("toast-message");

  if (toast && toastMessage) {
    toastMessage.textContent = message;
    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 5000);

    // Close button
    const closeBtn = toast.querySelector(".toast-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        toast.classList.remove("show");
      });
    }
  }
}

// Initialize notification polling
let notificationInterval;
function startNotificationPolling() {
  checkNotifications(); // Check immediately
  notificationInterval = setInterval(checkNotifications, 30000); // Then check every 30 seconds
}

// Clean up on page unload
function stopNotificationPolling() {
  if (notificationInterval) {
    clearInterval(notificationInterval);
  }
}

// Request permission for browser notifications
function requestNotificationPermission() {
  if (
    Notification.permission !== "granted" &&
    Notification.permission !== "denied"
  ) {
    Notification.requestPermission();
  }
}

// Start everything when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  initNotifications();
  requestNotificationPermission();
});

// Stop polling when page unloads
window.addEventListener("beforeunload", stopNotificationPolling);
