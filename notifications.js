// notifications.js - Client-side notification handler for Cupid
// Handles subscription, notification display and sound

class CupidNotifications {
  constructor() {
    this.swRegistration = null;
    this.isSubscribed = false;
    this.applicationServerPublicKey = null; // You'll need to set this from the server
    this.notificationSound = new Audio("/assets/sounds/notification.mp3");
    this.notificationCount = 0;
    this.unreadMessages = {};

    // Bind methods
    this.init = this.init.bind(this);
    this.setupEventListeners = this.setupEventListeners.bind(this);
    this.checkNotificationSupport = this.checkNotificationSupport.bind(this);
    this.requestNotificationPermission =
      this.requestNotificationPermission.bind(this);
    this.subscribeUserToPush = this.subscribeUserToPush.bind(this);
    this.updateSubscriptionOnServer =
      this.updateSubscriptionOnServer.bind(this);
    this.unsubscribeFromPush = this.unsubscribeFromPush.bind(this);
    this.updateUI = this.updateUI.bind(this);
    this.showNotification = this.showNotification.bind(this);
    this.playNotificationSound = this.playNotificationSound.bind(this);
    this.updateUnreadBadge = this.updateUnreadBadge.bind(this);
    this.markAsRead = this.markAsRead.bind(this);
    this.handleMessageFromServiceWorker =
      this.handleMessageFromServiceWorker.bind(this);
  }

  // Initialize notifications
  async init(publicKey) {
    try {
      // Save the public key
      this.applicationServerPublicKey = publicKey;

      // Check if service workers and push messaging are supported
      if (!this.checkNotificationSupport()) {
        console.log("Push notifications not supported");
        this.updateUI(false);
        return;
      }

      // Setup event listeners
      this.setupEventListeners();

      // Register service worker
      this.swRegistration = await navigator.serviceWorker.register(
        "/service-worker.js"
      );
      console.log("Service Worker registered:", this.swRegistration);

      // Check if already subscribed
      const subscription =
        await this.swRegistration.pushManager.getSubscription();
      this.isSubscribed = subscription !== null;
      console.log(
        "User is " + (this.isSubscribed ? "subscribed" : "not subscribed")
      );

      // Update the UI
      this.updateUI(this.isSubscribed);

      // Setup message listener for service worker
      navigator.serviceWorker.addEventListener(
        "message",
        this.handleMessageFromServiceWorker
      );

      return true;
    } catch (error) {
      console.error("Error initializing notifications:", error);
      return false;
    }
  }

  // Setup event listeners for UI elements
  setupEventListeners() {
    // Toggle button for notifications
    const toggleBtn = document.getElementById("notification-toggle");
    if (toggleBtn) {
      toggleBtn.addEventListener("change", async (e) => {
        if (e.target.checked) {
          // First request permission if needed
          const permission = await this.requestNotificationPermission();
          if (permission === "granted") {
            await this.subscribeUserToPush();
          } else {
            e.target.checked = false;
            this.showPermissionDeniedMessage();
          }
        } else {
          await this.unsubscribeFromPush();
        }
      });
    }

    // Notification bell icon click handler
    const notificationBell = document.getElementById("notification-bell");
    if (notificationBell) {
      notificationBell.addEventListener("click", () => {
        const panel = document.getElementById("notification-panel");
        if (panel) {
          panel.classList.toggle("show");
          if (panel.classList.contains("show")) {
            this.notificationCount = 0;
            this.updateUnreadBadge();
            // Mark notifications as read
            const notifications = document.querySelectorAll(
              ".notification-item.unread"
            );
            notifications.forEach((notification) => {
              notification.classList.remove("unread");
              const chatId = notification.getAttribute("data-chat-id");
              if (chatId) {
                this.markAsRead(chatId);
              }
            });
          }
        }
      });
    }

    // Close notification panel when clicking outside
    document.addEventListener("click", (e) => {
      const panel = document.getElementById("notification-panel");
      const bell = document.getElementById("notification-bell");
      if (
        panel &&
        bell &&
        !panel.contains(e.target) &&
        !bell.contains(e.target)
      ) {
        panel.classList.remove("show");
      }
    });
  }

  // Check if notifications are supported
  checkNotificationSupport() {
    if (!("serviceWorker" in navigator)) {
      console.log("Service Workers not supported");
      return false;
    }

    if (!("PushManager" in window)) {
      console.log("Push API not supported");
      return false;
    }

    return true;
  }

  // Request notification permission
  async requestNotificationPermission() {
    try {
      const permission = await Notification.requestPermission();
      return permission;
    } catch (error) {
      console.error("Error requesting notification permission:", error);
      return "denied";
    }
  }

  // Subscribe user to push notifications
  async subscribeUserToPush() {
    try {
      const applicationServerKey = this.urlB64ToUint8Array(
        this.applicationServerPublicKey
      );

      const subscription = await this.swRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey,
      });

      console.log("User is subscribed:", subscription);

      // Update subscription on server
      await this.updateSubscriptionOnServer(subscription);

      this.isSubscribed = true;
      this.updateUI(true);

      return subscription;
    } catch (error) {
      console.error("Failed to subscribe user:", error);
      this.updateUI(false);
      return null;
    }
  }

  // Send subscription to server
  async updateSubscriptionOnServer(subscription) {
    if (!subscription) {
      console.log("Removing subscription from server");
      return fetch("/remove-subscription", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "unsubscribe",
        }),
      });
    } else {
      console.log("Sending subscription to server");
      return fetch("/save-subscription", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          subscription: subscription,
          action: "subscribe",
        }),
      });
    }
  }

  // Unsubscribe from push notifications
  async unsubscribeFromPush() {
    try {
      // Get the current subscription
      const subscription =
        await this.swRegistration.pushManager.getSubscription();

      if (subscription) {
        // Unsubscribe
        await subscription.unsubscribe();

        // Update server
        await this.updateSubscriptionOnServer(null);

        this.isSubscribed = false;
        this.updateUI(false);

        console.log("User is unsubscribed");
      }
    } catch (error) {
      console.error("Error unsubscribing:", error);
    }
  }

  // Update UI based on subscription status
  updateUI(isSubscribed) {
    const toggleBtn = document.getElementById("notification-toggle");
    const statusText = document.getElementById("notification-status");

    if (toggleBtn) {
      toggleBtn.checked = isSubscribed;
    }

    if (statusText) {
      statusText.textContent = isSubscribed
        ? "Notifications are enabled"
        : "Notifications are disabled";
    }
  }

  // Show permission denied message
  showPermissionDeniedMessage() {
    const statusText = document.getElementById("notification-status");
    if (statusText) {
      statusText.textContent =
        "Notification permission denied. Please enable notifications in your browser settings.";
      statusText.style.color = "#dc3545";
    }
  }

  // Show notification in the browser
  showNotification(data) {
    // Update notification count
    this.notificationCount++;
    this.updateUnreadBadge();

    // Play sound
    this.playNotificationSound();

    // Add to notification panel if it exists
    this.addNotificationToPanel(data);

    // If we're on the chat page with the correct session, just highlight the message
    if (window.location.pathname.includes("/chat")) {
      const urlParams = new URLSearchParams(window.location.search);
      const sessionId = urlParams.get("session_id");

      if (sessionId === data.chatId) {
        // We're already in this chat, so just highlight the new message
        console.log("Already in chat, highlighting message");
        // Implementation would depend on your DOM structure
        return;
      }
    }

    // Update the title to show notification
    this.updatePageTitle(data.senderName || "New message");

    // If foreground notification is supported, show it
    if (this.swRegistration && "showNotification" in this.swRegistration) {
      this.swRegistration.showNotification(data.title, {
        body: data.body,
        icon: data.icon || "/assets/images/cupid_notif_icon.png",
        badge: "/assets/images/cupid_badge.png",
        tag: `chat-${data.chatId}`,
        renotify: true,
        requireInteraction: false,
        actions: [
          {
            action: "reply",
            title: "Reply",
          },
          {
            action: "view",
            title: "View",
          },
        ],
        data: data,
      });
    }
  }

  // Add notification to the panel
  addNotificationToPanel(data) {
    const panel = document.getElementById("notification-list");
    if (!panel) return;

    // Track unread messages for this chat
    if (!this.unreadMessages[data.chatId]) {
      this.unreadMessages[data.chatId] = 0;
    }
    this.unreadMessages[data.chatId]++;

    // Create notification item
    const item = document.createElement("div");
    item.className = "notification-item unread";
    item.setAttribute("data-chat-id", data.chatId);

    // Format time
    const time = new Date(data.timestamp || Date.now());
    const timeStr = time.toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });

    item.innerHTML = `
      <div class="notification-avatar">
        <img src="${
          data.senderAvatar || "/assets/images/user_profile.png"
        }" alt="${data.senderName || "User"}">
      </div>
      <div class="notification-content">
        <div class="notification-header">
          <strong>${data.senderName || "Someone"}</strong>
          <span class="notification-time">${timeStr}</span>
        </div>
        <div class="notification-message">
          ${data.body || "Sent you a message"}
        </div>
      </div>
    `;

    // Make clickable
    item.addEventListener("click", () => {
      window.location.href = `/chat?session_id=${data.chatId}`;
    });

    // Add to panel
    panel.insertBefore(item, panel.firstChild);

    // Limit to 10 notifications
    if (panel.children.length > 10) {
      panel.removeChild(panel.lastChild);
    }
  }

  // Play notification sound
  playNotificationSound() {
    try {
      // Reset sound to beginning
      this.notificationSound.currentTime = 0;
      this.notificationSound.play().catch((e) => {
        console.log("Could not play notification sound:", e);
      });
    } catch (e) {
      console.error("Error playing notification sound:", e);
    }
  }

  // Update unread badge count
  updateUnreadBadge() {
    const badge = document.getElementById("notification-badge");
    if (badge) {
      if (this.notificationCount > 0) {
        badge.textContent =
          this.notificationCount > 9 ? "9+" : this.notificationCount;
        badge.style.display = "flex";
      } else {
        badge.style.display = "none";
      }
    }

    // Update chat menu badge if exists
    const chatMenuBadge = document.getElementById("chat-menu-badge");
    if (chatMenuBadge) {
      const totalUnread = Object.values(this.unreadMessages).reduce(
        (sum, count) => sum + count,
        0
      );
      if (totalUnread > 0) {
        chatMenuBadge.textContent = totalUnread > 9 ? "9+" : totalUnread;
        chatMenuBadge.style.display = "flex";
      } else {
        chatMenuBadge.style.display = "none";
      }
    }
  }

  // Mark messages from a chat as read
  markAsRead(chatId) {
    if (this.unreadMessages[chatId]) {
      this.notificationCount -= this.unreadMessages[chatId];
      delete this.unreadMessages[chatId];
      this.updateUnreadBadge();

      // Inform server that messages are read
      fetch("/mark_messages_read", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          chat_id: chatId,
        }),
      }).catch((e) => console.error("Error marking messages as read:", e));
    }
  }

  // Update page title with notification
  updatePageTitle(sender) {
    const originalTitle = document.title;
    const newTitle = `New message from ${sender} 💬`;

    // Toggle title for attention
    let titleInterval = setInterval(() => {
      document.title =
        document.title === originalTitle ? newTitle : originalTitle;
    }, 1000);

    // Reset title when window gets focus
    window.addEventListener(
      "focus",
      () => {
        clearInterval(titleInterval);
        document.title = originalTitle;
      },
      { once: true }
    );
  }

  // Handle messages from service worker
  handleMessageFromServiceWorker(event) {
    console.log("Message from service worker:", event.data);

    if (event.data.type === "reply-message") {
      // Handle reply action - focus the reply box
      const chatInput = document.querySelector(
        '.chat-form input[name="message"]'
      );
      if (chatInput) {
        chatInput.focus();
      }
    }
  }

  // Convert base64 string to Uint8Array for Web Push
  urlB64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, "+")
      .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
  }

  // Start polling for new messages
  startPolling(interval = 10000) {
    // Poll for new messages
    this.pollInterval = setInterval(() => {
      this.checkNewMessages();
    }, interval);
  }

  // Stop polling
  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
    }
  }

  // Check for new messages
  async checkNewMessages() {
    try {
      const response = await fetch("/check_messages");
      const data = await response.json();

      if (data.success && data.hasNewMessages) {
        // Process new messages
        data.messages.forEach((message) => {
          // Show notification for each message
          this.showNotification({
            chatId: message.session_id,
            senderId: message.sender_id,
            senderName: message.sender_name,
            senderAvatar: message.sender_pic,
            title: `Message from ${message.sender_name}`,
            body: message.message,
            timestamp: message.timestamp * 1000, // Convert to milliseconds
          });
        });
      }
    } catch (error) {
      console.error("Error checking for new messages:", error);
    }
  }
}

// Initialize when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  // Create global instance
  window.cupidNotifications = new CupidNotifications();

  // Fetch public key from server
  fetch("/get_vapid_public_key.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.publicKey) {
        // Initialize with public key
        window.cupidNotifications.init(data.publicKey);

        // Start polling for new messages
        window.cupidNotifications.startPolling();
      } else {
        console.error("Could not get VAPID public key");
      }
    })
    .catch((error) => {
      console.error("Error fetching VAPID public key:", error);
    });
});
