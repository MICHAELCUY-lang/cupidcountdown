// notifications.js - Client-side notification handling for Cupid

// Store for subscription object
let pushSubscription = null;

// Check if the browser supports service workers
function isPushNotificationSupported() {
  return "serviceWorker" in navigator && "PushManager" in window;
}

// Initialize push notifications
async function initializePushNotifications() {
  if (!isPushNotificationSupported()) {
    console.log("Push notifications not supported");
    return false;
  }

  try {
    // Register service worker
    const registration = await navigator.serviceWorker.register(
      "/service-worker.js"
    );
    console.log("Service Worker registered successfully");

    // Check notification permission
    const permission = await Notification.requestPermission();
    if (permission !== "granted") {
      console.log("Notification permission denied");
      return false;
    }

    // Get push subscription
    pushSubscription = await createPushSubscription(registration);

    // Send subscription to server
    if (pushSubscription) {
      sendSubscriptionToServer(pushSubscription);
      return true;
    }

    return false;
  } catch (error) {
    console.error("Error initializing push notifications:", error);
    return false;
  }
}

// Create push subscription
async function createPushSubscription(registration) {
  try {
    const options = {
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(
        // This is a public VAPID key that should be generated and stored securely on the server
        // For now, we're using a placeholder - this needs to be replaced with a real key
        "BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U"
      ),
    };

    const subscription = await registration.pushManager.subscribe(options);
    console.log("Push subscription created:", subscription);
    return subscription;
  } catch (error) {
    console.error("Error creating push subscription:", error);
    return null;
  }
}

// Send subscription to server
function sendSubscriptionToServer(subscription) {
  const subscriptionObject = {
    endpoint: subscription.endpoint,
    keys: {
      p256dh: arrayBufferToBase64(subscription.getKey("p256dh")),
      auth: arrayBufferToBase64(subscription.getKey("auth")),
    },
  };

  fetch("/save_subscription.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(subscriptionObject),
  })
    .then((response) => response.json())
    .then((data) => {
      console.log("Subscription sent to server:", data);
    })
    .catch((error) => {
      console.error("Error sending subscription to server:", error);
    });
}

// Check notification status and update UI
function checkNotificationStatus() {
  const notificationToggle = document.getElementById("notification-toggle");
  if (!notificationToggle) return;

  if (!isPushNotificationSupported()) {
    notificationToggle.disabled = true;
    notificationToggle.checked = false;
    document.getElementById("notification-status").textContent =
      "Your browser does not support notifications";
    return;
  }

  // Check if notifications are enabled
  if (
    Notification.permission === "granted" &&
    navigator.serviceWorker.controller
  ) {
    notificationToggle.checked = true;
    document.getElementById("notification-status").textContent =
      "Notifications are enabled";
  } else {
    notificationToggle.checked = false;
    document.getElementById("notification-status").textContent =
      "Notifications are disabled";
  }
}

// Toggle notifications
async function toggleNotifications() {
  const notificationToggle = document.getElementById("notification-toggle");

  if (notificationToggle.checked) {
    const success = await initializePushNotifications();
    if (!success) {
      notificationToggle.checked = false;
      document.getElementById("notification-status").textContent =
        "Failed to enable notifications";
    } else {
      document.getElementById("notification-status").textContent =
        "Notifications are enabled";
    }
  } else {
    // Unsubscribe from push notifications
    if (pushSubscription) {
      try {
        const success = await pushSubscription.unsubscribe();
        if (success) {
          document.getElementById("notification-status").textContent =
            "Notifications are disabled";
          fetch("/remove_subscription.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({ endpoint: pushSubscription.endpoint }),
          });
        }
      } catch (error) {
        console.error("Error unsubscribing from push notifications:", error);
      }
    }
  }
}

// Helper function to convert base64 string to Uint8Array for applicationServerKey
function urlBase64ToUint8Array(base64String) {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

// Helper function to convert ArrayBuffer to base64 string
function arrayBufferToBase64(buffer) {
  const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
  return window.btoa(binary);
}

// Check for new messages periodically (polling)
function startMessagePolling(interval = 10000) {
  setInterval(checkNewMessages, interval);
}

// Check for new messages
function checkNewMessages() {
  fetch("/check_messages.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.hasNewMessages) {
        playNotificationSound();
        updateChatInterface(data.messages);
      }
    })
    .catch((error) => {
      console.error("Error checking for new messages:", error);
    });
}

// Play notification sound
function playNotificationSound() {
  const audio = new Audio("/assets/sounds/notification.mp3");
  audio.play().catch((error) => {
    console.error("Error playing notification sound:", error);
  });
}

// Update chat interface with new messages
function updateChatInterface(messages) {
  // This function will depend on how your chat interface is structured
  // and should be tailored to your specific implementation
  const chatMessages = document.getElementById("chat-messages");
  if (!chatMessages) return;

  // Add new messages to the chat interface
  messages.forEach((message) => {
    const messageElem = document.createElement("div");
    messageElem.className = `message ${
      message.sender_id === currentUserId ? "sent" : "received"
    }`;
    messageElem.innerHTML = `
      <div class="message-content">
        <div class="message-text">${message.message}</div>
        <div class="message-time">${message.time}</div>
      </div>
    `;
    chatMessages.appendChild(messageElem);
  });

  // Scroll to bottom of chat
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Initialize when the page loads
document.addEventListener("DOMContentLoaded", function () {
  // Check if notification toggle exists
  const notificationToggle = document.getElementById("notification-toggle");
  if (notificationToggle) {
    checkNotificationStatus();
    notificationToggle.addEventListener("change", toggleNotifications);
  }

  // Try to initialize push notifications if permission was previously granted
  if (Notification.permission === "granted") {
    initializePushNotifications();
  }

  // Start polling for new messages
  startMessagePolling();
});
