// service-worker.js - Service worker for Cupid website
// Handles background push notifications and caching

// Cache name for offline support
const CACHE_NAME = "cupid-cache-v1";
const urlsToCache = [
  "/",
  "/assets/css/style.css",
  "/assets/js/main.js",
  "/assets/images/cupid_notif_icon.png",
  "/assets/images/user_profile.png",
  "/offline.html",
];

// Install event - cache essential files
self.addEventListener("install", (event) => {
  console.log("Service Worker: Installing...");
  self.skipWaiting();

  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log("Service Worker: Caching files");
      return cache.addAll(urlsToCache);
    })
  );
});

// Activate event - clean up old caches
self.addEventListener("activate", (event) => {
  console.log("Service Worker: Activated");

  const cacheWhitelist = [CACHE_NAME];

  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheWhitelist.indexOf(cacheName) === -1) {
              console.log("Service Worker: Clearing old cache", cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache or network
self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Return cached response if found
      if (response) {
        return response;
      }

      // Clone the request because it's a one-time use
      const fetchRequest = event.request.clone();

      return fetch(fetchRequest)
        .then((response) => {
          // Check if we received a valid response
          if (
            !response ||
            response.status !== 200 ||
            response.type !== "basic"
          ) {
            return response;
          }

          // Clone the response because it's a one-time use
          const responseToCache = response.clone();

          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });

          return response;
        })
        .catch(() => {
          // If network request fails, serve offline page for navigation requests
          if (event.request.mode === "navigate") {
            return caches.match("/offline.html");
          }
        });
    })
  );
});

// Push notification event
self.addEventListener("push", (event) => {
  console.log("Push Notification received", event);

  let notificationData = {};

  try {
    if (event.data) {
      notificationData = event.data.json();
    }
  } catch (e) {
    console.error("Error parsing push data:", e);
    notificationData = {
      title: "New Notification",
      body: event.data ? event.data.text() : "You have a new notification",
    };
  }

  // Default notification options with WhatsApp-like behavior
  const title = notificationData.title || "Cupid Message";
  const options = {
    body: notificationData.body || "You have a new message",
    icon: notificationData.icon || "/assets/images/cupid_notif_icon.png",
    badge: "/assets/images/cupid_badge.png",
    image: notificationData.image, // Preview image if available
    timestamp: notificationData.timestamp || Date.now(),
    vibrate: [100, 50, 100, 50, 100], // Vibration pattern
    sound: "/assets/sounds/notification.mp3", // Custom sound
    tag: `chat-${notificationData.chatId || "general"}`, // Group messages by chat
    renotify: true, // Notify again even if using same tag
    requireInteraction: false, // Auto-dismiss after a while like WhatsApp
    actions: [
      {
        action: "reply",
        title: "Reply",
        icon: "/assets/images/reply_icon.png",
      },
      {
        action: "view",
        title: "View",
        icon: "/assets/images/view_icon.png",
      },
    ],
    // Custom data to be used when notification is clicked
    data: {
      url: notificationData.url || "/dashboard?page=chat",
      chatId: notificationData.chatId,
      senderId: notificationData.senderId,
      senderName: notificationData.senderName,
      messageId: notificationData.messageId,
      messageText: notificationData.body,
    },
  };

  event.waitUntil(
    // Show notification
    self.registration.showNotification(title, options).then(() => {
      // Play notification sound - this is handled by the notification itself
      // but we could do additional tracking or analytics here
      console.log("Notification displayed successfully");
    })
  );
});

// Notification click event
self.addEventListener("notificationclick", (event) => {
  console.log("Notification click received", event);

  // Close the notification
  event.notification.close();

  // Get data from notification
  const notificationData = event.notification.data;

  // Handle different action clicks
  if (event.action === "reply") {
    // Handle reply action - open chat with reply box focused
    event.waitUntil(
      clients.matchAll({ type: "window" }).then((clientList) => {
        // Check if there's already a window open
        for (const client of clientList) {
          if (client.url.includes("/chat") && "focus" in client) {
            client.focus();
            // Send message to client to open reply box
            return client.postMessage({
              type: "reply-message",
              chatId: notificationData.chatId,
              senderId: notificationData.senderId,
            });
          }
        }
        // If no matching window, open a new one with reply parameter
        return clients.openWindow(
          `/chat?session_id=${notificationData.chatId}&reply=true`
        );
      })
    );
  } else {
    // Default action or 'view' action - just open the chat
    event.waitUntil(
      clients.matchAll({ type: "window" }).then((clientList) => {
        // Check if there's already a window open
        for (const client of clientList) {
          if ("focus" in client) {
            return client.focus();
          }
        }
        // If no window is open, open a new one
        if (clients.openWindow) {
          return clients.openWindow(
            notificationData.url ||
              `/chat?session_id=${notificationData.chatId}`
          );
        }
      })
    );
  }
});

// Notification close event (when user dismisses notification)
self.addEventListener("notificationclose", (event) => {
  console.log("Notification was closed", event);
  // You could log this or take other actions when a user dismisses a notification
});
