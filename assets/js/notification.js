/**
 * Cupid Notifications System
 * This script handles browser notifications for the Cupid application.
 */

// Notification helper object
const CupidNotifications = {
  // Check if notifications are supported in this browser
  isSupported: function () {
    return "Notification" in window;
  },

  // Request permission to show notifications
  requestPermission: function () {
    if (!this.isSupported()) {
      console.log("Browser notifications are not supported");
      return Promise.reject("Browser notifications not supported");
    }

    // Return existing permission if already granted
    if (Notification.permission === "granted") {
      return Promise.resolve("granted");
    }

    // Otherwise request permission
    return Notification.requestPermission();
  },

  // Check if permission has been granted
  hasPermission: function () {
    return this.isSupported() && Notification.permission === "granted";
  },

  // Send a notification
  send: function (title, options = {}) {
    if (!this.hasPermission()) {
      console.log("Notification permission not granted");
      return null;
    }

    // Set default options
    const defaultOptions = {
      icon: "/assets/images/cupid_notification.png", // Path to your notification icon
      badge: "/assets/images/cupid_badge.png", // Path to your notification badge
      vibrate: [100, 50, 100], // Vibration pattern for mobile
      requireInteraction: true, // Notification stays until user interacts
    };

    // Create and return the notification
    const notification = new Notification(title, {
      ...defaultOptions,
      ...options,
    });

    // Handle click event
    notification.onclick = function (event) {
      event.preventDefault(); // Prevent the browser from focusing the Notification's tab

      // Focus on the relevant window/tab if options.url is provided
      if (options.url) {
        window.open(options.url, "_blank");
        notification.close();
      }

      // Execute callback if provided
      if (typeof options.onClick === "function") {
        options.onClick();
      }
    };

    return notification;
  },

  // Send a chat notification
  sendChatNotification: function (senderName, message, chatUrl) {
    return this.send(`New message from ${senderName}`, {
      body: message.length > 50 ? message.substring(0, 50) + "..." : message,
      url: chatUrl,
      tag: "cupid-chat-notification", // Unique tag to avoid multiple notifications
      data: {
        type: "chat",
        senderId: senderId,
        chatSessionId: chatSessionId,
      },
    });
  },

  // Send a menfess notification
  sendMenfessNotification: function (message, menfessUrl) {
    return this.send("New Menfess Received!", {
      body: message.length > 50 ? message.substring(0, 50) + "..." : message,
      url: menfessUrl,
      tag: "cupid-menfess-notification", // Unique tag to avoid multiple notifications
      data: {
        type: "menfess",
      },
    });
  },

  // Initialize notification system
  init: function () {
    // Request permission on init if not already granted or denied
    if (this.isSupported() && Notification.permission === "default") {
      // Wait for user interaction before requesting permission
      document.addEventListener(
        "click",
        () => {
          this.requestPermission().then((permission) => {
            if (permission === "granted") {
              console.log("Notification permission granted");
            }
          });
        },
        { once: true }
      ); // Only trigger once
    }

    // Register a service worker for background notifications (optional enhancement)
    if ("serviceWorker" in navigator) {
      navigator.serviceWorker
        .register("/service-worker")
        .then((registration) => {
          console.log(
            "Service Worker registered with scope:",
            registration.scope
          );
        })
        .catch((error) => {
          console.error("Service Worker registration failed:", error);
        });
    }
  },
};

// Initialize notifications when DOM is loaded
document.addEventListener("DOMContentLoaded", function () {
  CupidNotifications.init();

  // Add notification toggle in UI (if needed)
  const notificationToggle = document.getElementById("notification-toggle");
  if (notificationToggle) {
    notificationToggle.addEventListener("click", function () {
      CupidNotifications.requestPermission().then((permission) => {
        if (permission === "granted") {
          notificationToggle.textContent = "Notifications: On";
          notificationToggle.classList.add("active");
        } else {
          notificationToggle.textContent = "Notifications: Off";
          notificationToggle.classList.remove("active");
        }
      });
    });

    // Update toggle state on load
    if (CupidNotifications.hasPermission()) {
      notificationToggle.textContent = "Notifications: On";
      notificationToggle.classList.add("active");
    } else {
      notificationToggle.textContent = "Notifications: Off";
      notificationToggle.classList.remove("active");
    }
  }
});

// Create a global reference for easy access from other scripts
window.CupidNotifications = CupidNotifications;
