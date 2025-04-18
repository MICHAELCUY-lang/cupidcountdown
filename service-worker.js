// service-worker.js - Handles push notifications for Cupid

self.addEventListener("install", function (event) {
  self.skipWaiting();
  console.log("Service Worker installed");
});

self.addEventListener("activate", function (event) {
  console.log("Service Worker activated");
});

// Handle incoming push notifications
self.addEventListener("push", function (event) {
  console.log("Push notification received");

  let notificationData = {};

  if (event.data) {
    try {
      notificationData = event.data.json();
    } catch (e) {
      notificationData = {
        title: "New Notification",
        body: event.data.text(),
        icon: "/assets/images/cupid_notif_icon.png",
      };
    }
  }

  // Default notification data if none provided
  const title = notificationData.title || "Cupid";
  const options = {
    body: notificationData.body || "You have a new notification",
    icon: notificationData.icon || "/assets/images/cupid_notif_icon.png",
    badge: "/assets/images/cupid_badge.png",
    data: {
      url: notificationData.url || "/",
      messageId: notificationData.messageId,
      senderId: notificationData.senderId,
    },
    vibrate: [100, 50, 100],
    tag: notificationData.tag || "cupid-notification",
    actions: [
      {
        action: "view",
        title: "View",
      },
      {
        action: "close",
        title: "Close",
      },
    ],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

// Handle notification click
self.addEventListener("notificationclick", function (event) {
  console.log("Notification click received");

  event.notification.close();

  // Get the notification data
  const notificationData = event.notification.data;

  if (event.action === "close") {
    // User chose to close the notification
    return;
  }

  // Open the relevant URL when notification is clicked
  event.waitUntil(
    clients.matchAll({ type: "window" }).then(function (clientList) {
      // Check if there's already a window/tab open with the target URL
      for (let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        // If so, just focus it
        if (client.url === notificationData.url && "focus" in client) {
          return client.focus();
        }
      }

      // If not, open a new window/tab
      if (clients.openWindow) {
        let url = notificationData.url || "/dashboard.php?page=chat";

        // If we have messageId and senderId, redirect to the specific chat
        if (notificationData.messageId && notificationData.senderId) {
          url = `/chat.php?session_id=${notificationData.messageId}`;
        }

        return clients.openWindow(url);
      }
    })
  );
});
