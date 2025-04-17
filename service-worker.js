/**
 * Cupid Service Worker
 * Handles background notifications for the Cupid application
 */

const CACHE_NAME = "cupid-cache-v1";
const OFFLINE_URL = "/offline";

// URLs to cache for offline use
const urlsToCache = [
  "/",
  "/assets/css/style",
  "/assets/js/main",
  "/assets/js/notifications",
  "/assets/images/cupid_notification.png",
  "/assets/images/cupid_badge.png",
  OFFLINE_URL,
];

// Install event - cache resources for offline use
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log("Opened cache");
      return cache.addAll(urlsToCache);
    })
  );
});

// Activate event - clean up old caches
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((cacheName) => {
            return cacheName !== CACHE_NAME;
          })
          .map((cacheName) => {
            return caches.delete(cacheName);
          })
      );
    })
  );
});

// Fetch event - serve from cache if available
self.addEventListener("fetch", (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      // Cache hit - return response
      if (response) {
        return response;
      }
      return fetch(event.request).catch(() => {
        // If main page is requested but not available, return offline page
        if (event.request.mode === "navigate") {
          return caches.match(OFFLINE_URL);
        }
      });
    })
  );
});

// Push notification event - show notification to user
self.addEventListener("push", (event) => {
  const data = event.data.json();

  const options = {
    body: data.body || "New notification from Cupid",
    icon: data.icon || "/assets/images/cupid_notification.png",
    badge: data.badge || "/assets/images/cupid_badge.png",
    data: data.data || {},
    requireInteraction: true,
    vibrate: [100, 50, 100],
  };

  if (data.actions) {
    options.actions = data.actions;
  }

  event.waitUntil(self.registration.showNotification(data.title, options));
});

// Notification click event - open relevant page
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const notificationData = event.notification.data;
  let url = "/dashboard";

  // Determine which URL to open based on notification type
  if (notificationData) {
    if (notificationData.type === "chat") {
      url = `/chat?session_id=${notificationData.chatSessionId}`;
    } else if (notificationData.type === "menfess") {
      url = "/dashboard?page=menfess";
    } else if (notificationData.type === "match") {
      url = "/dashboard?page=matches";
    } else if (notificationData.url) {
      url = notificationData.url;
    }
  }

  // Open the target URL in a new window/tab
  event.waitUntil(
    clients.matchAll({ type: "window" }).then((windowClients) => {
      // Check if there is already a window/tab open with the target URL
      for (let i = 0; i < windowClients.length; i++) {
        const client = windowClients[i];
        if (client.url === url && "focus" in client) {
          return client.focus();
        }
      }
      // If no window/tab is open with the target URL, open a new one
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
