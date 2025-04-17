/**
 * Notification Handler
 * Periodically checks for new notifications and displays them
 */

// Notification handler object
const NotificationHandler = {
    // Last check timestamp
    lastCheck: new Date().toISOString(),
    
    // Check interval in milliseconds (default: 30 seconds)
    checkInterval: 30000,
    
    // Interval ID for polling
    intervalId: null,
    
    // Notification count for badge
    notificationCount: 0,
    
    // Store seen notification IDs to avoid duplicates
    seenNotifications: new Set(),
    
    // Check for new notifications
    checkNotifications: function() {
        if (!CupidNotifications.hasPermission()) {
            return;
        }
        
        // Build URL with last check time
        const url = `check_notifications?last_check=${encodeURIComponent(this.lastCheck)}&check_likes=1`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update last check time
                    this.lastCheck = data.last_check;
                    
                    // Process notifications
                    this.processNotifications(data.notifications);
                }
            })
            .catch(error => {
                console.error('Error checking notifications:', error);
            });
    },
    
    // Process notifications
    processNotifications: function(notifications) {
        if (!notifications || notifications.length === 0) {
            return;
        }
        
        let newCount = 0;
        
        // Process each notification
        notifications.forEach(notification => {
            // Skip if we've already seen this notification
            const notificationKey = `${notification.type}-${notification.id}`;
            if (this.seenNotifications.has(notificationKey)) {
                return;
            }
            
            // Mark as seen
            this.seenNotifications.add(notificationKey);
            newCount++;
            
            // Create browser notification based on type
            switch (notification.type) {
                case 'chat':
                    CupidNotifications.send(
                        `New message from ${notification.sender_name}`,
                        {
                            body: notification.message.length > 50 ? 
                                  notification.message.substring(0, 50) + '...' : 
                                  notification.message,
                            url: notification.url,
                            tag: `cupid-chat-${notification.session_id}`,
                            data: {
                                type: 'chat',
                                chatSessionId: notification.session_id,
                                senderId: notification.sender_id
                            }
                        }
                    );
                    break;
                    
                case 'menfess':
                    CupidNotifications.send(
                        'New Menfess Received!',
                        {
                            body: notification.message.length > 50 ? 
                                  notification.message.substring(0, 50) + '...' : 
                                  notification.message,
                            url: notification.url,
                            tag: `cupid-menfess-${notification.id}`,
                            data: {
                                type: 'menfess',
                                menfessId: notification.id
                            }
                        }
                    );
                    break;
                    
                case 'menfess_like':
                    CupidNotifications.send(
                        'Someone liked your Menfess!',
                        {
                            body: `${notification.liker_name} liked your message: "${notification.message.substring(0, 40)}..."`,
                            url: notification.url,
                            tag: `cupid-menfess-like-${notification.id}`,
                            data: {
                                type: 'menfess_like',
                                menfessId: notification.menfess_id,
                                likerId: notification.liker_id
                            }
                        }
                    );
                    break;
                    
                case 'match':
                    CupidNotifications.send(
                        'You have a new match!',
                        {
                            body: `${notification.match_name} has matched with you!`,
                            url: notification.url,
                            tag: `cupid-match-${notification.id}`,
                            data: {
                                type: 'match',
                                matchId: notification.match_id
                            }
                        }
                    );
                    break;
            }
        });
        
        // Update notification count
        this.notificationCount += newCount;
        this.updateNotificationBadge();
        
        // Also update UI if available
        this.updateNotificationUI(notifications);
    },
    
    // Update notification badge in UI
    updateNotificationBadge: function() {
        const badgeElement = document.getElementById('notification-badge');
        if (badgeElement) {
            if (this.notificationCount > 0) {
                badgeElement.textContent = this.notificationCount > 99 ? '99+' : this.notificationCount;
                badgeElement.classList.remove('hidden');
            } else {
                badgeElement.classList.add('hidden');
            }
        }
        
        // Update page title with notification count
        if (this.notificationCount > 0) {
            document.title = `(${this.notificationCount}) Cupid`;
        } else {
            document.title = 'Cupid';
        }
    },
    
    // Update notification UI elements if they exist
    updateNotificationUI: function(notifications) {
        // Update the notifications dropdown if it exists
        const notificationsList = document.getElementById('notifications-list');
        if (notificationsList) {
            // Add new notifications to the list
            notifications.forEach(notification => {
                const notificationItem = document.createElement('div');
                notificationItem.className = 'notification-item';
                
                // Create notification content based on type
                let icon, title, message;
                
                switch (notification.type) {
                    case 'chat':
                        icon = '<i class="fas fa-comment"></i>';
                        title = `New message from ${notification.sender_name}`;
                        message = notification.message.length > 40 ? 
                                 notification.message.substring(0, 40) + '...' : 
                                 notification.message;
                        break;
                        
                    case 'menfess':
                        icon = '<i class="fas fa-mask"></i>';
                        title = 'New Menfess Received!';
                        message = notification.message.length > 40 ? 
                                 notification.message.substring(0, 40) + '...' : 
                                 notification.message;
                        break;
                        
                    case 'menfess_like':
                        icon = '<i class="fas fa-heart"></i>';
                        title = 'Your Menfess was liked!';
                        message = `${notification.liker_name} liked your message`;
                        break;
                        
                    case 'match':
                        icon = '<i class="fas fa-check-circle"></i>';
                        title = 'New Match!';
                        message = `${notification.match_name} has matched with you!`;
                        break;
                }
                
                // Create notification time string
                const notificationTime = new Date(notification.created_at);
                const timeString = notificationTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                // Set HTML content
                notificationItem.innerHTML = `
                    <a href="${notification.url}" class="notification-link">
                        <div class="notification-icon">${icon}</div>
                        <div class="notification-content">
                            <div class="notification-title">${title}</div>
                            <div class="notification-message">${message}</div>
                            <div class="notification-time">${timeString}</div>
                        </div>
                    </a>
                `;
                
                // Add to list
                notificationsList.prepend(notificationItem);
                
                // Limit to 10 items
                if (notificationsList.children.length > 10) {
                    notificationsList.removeChild(notificationsList.lastChild);
                }
            });
            
            // Update the empty state if needed
            const emptyState = document.querySelector('.notifications-empty');
            if (emptyState) {
                if (notificationsList.children.length > 0) {
                    emptyState.style.display = 'none';
                } else {
                    emptyState.style.display = 'block';
                }
            }
        }
    },
    
    // Mark notifications as read
    markAsRead: function() {
        this.notificationCount = 0;
        this.updateNotificationBadge();
        
        // You could also send an AJAX request to mark notifications as read in the database
        // fetch('mark_notifications_read.php', { method: 'POST' });
    },
    
    // Start checking for notifications
    startChecking: function() {
        // Initial check
        this.checkNotifications();
        
        // Set up interval for checking
        this.intervalId = setInterval(() => {
            this.checkNotifications();
        }, this.checkInterval);
    },
    
    // Stop checking for notifications
    stopChecking: function() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    },
    
    // Initialize the notification handler
    init: function() {
        // Request notification permission if needed
        if (CupidNotifications.isSupported() && Notification.permission === 'default') {
            // Add notification permission button if it exists
            const permissionButton = document.getElementById('notification-permission');
            if (permissionButton) {
                permissionButton.addEventListener('click', () => {
                    CupidNotifications.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            permissionButton.classList.add('active');
                            permissionButton.textContent = 'Notifications: On';
                            this.startChecking();
                        } else {
                            permissionButton.classList.remove('active');
                            permissionButton.textContent = 'Notifications: Off';
                        }
                    });
                });
                
                // Update button state
                if (CupidNotifications.hasPermission()) {
                    permissionButton.classList.add('active');
                    permissionButton.textContent = 'Notifications: On';
                } else {
                    permissionButton.classList.remove('active');
                    permissionButton.textContent = 'Notifications: Off';
                }
            }
        }
        
        // Start checking for notifications if permission is granted
        if (CupidNotifications.hasPermission()) {
            this.startChecking();
        }
        
        // Add event listeners for notification-related UI elements
        const notificationToggle = document.getElementById('notification-toggle');
        if (notificationToggle) {
            notificationToggle.addEventListener('click', () => {
                const dropdown = document.getElementById('notifications-dropdown');
                if (dropdown) {
                    dropdown.classList.toggle('show');
                    if (dropdown.classList.contains('show')) {
                        this.markAsRead();
                    }
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (event) => {
                const dropdown = document.getElementById('notifications-dropdown');
                if (dropdown && !notificationToggle.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.remove('show');
                }
            });
        }
    }
};

// Initialize notification handler when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Wait for CupidNotifications to be available
    if (window.CupidNotifications) {
        NotificationHandler.init();
    } else {
        // If CupidNotifications is not loaded yet, wait for it
        const checkCupidNotifications = setInterval(() => {
            if (window.CupidNotifications) {
                clearInterval(checkCupidNotifications);
                NotificationHandler.init();
            }
        }, 100);
    }
});

// Make NotificationHandler globally accessible
window.NotificationHandler = NotificationHandler;