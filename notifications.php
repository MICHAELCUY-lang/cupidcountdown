<?php
/**
 * Notification System for Cupid
 * 
 * This file handles creating, retrieving, and managing notifications.
 */

// Create the notifications table if it doesn't exist
function createNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sender_id INT NULL,
        type ENUM('message', 'like', 'match', 'system') NOT NULL,
        content TEXT NOT NULL,
        related_id INT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $conn->query($sql);
}

/**
 * Create a new notification
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id Recipient user ID
 * @param int|null $sender_id Sender user ID (null for system notifications)
 * @param string $type Notification type ('message', 'like', 'match', 'system')
 * @param string $content Notification content/message
 * @param int|null $related_id Related content ID (message_id, menfess_id, etc.)
 * @return bool True on success, false on failure
 */
function createNotification($conn, $user_id, $sender_id, $type, $content, $related_id = null) {
    $sql = "INSERT INTO notifications (user_id, sender_id, type, content, related_id) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissi", $user_id, $sender_id, $type, $content, $related_id);
    return $stmt->execute();
}

/**
 * Get unread notifications count for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationsCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

/**
 * Get notifications for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications to return
 * @param int $offset Offset for pagination
 * @return array Array of notification objects
 */
function getNotifications($conn, $user_id, $limit = 20, $offset = 0) {
    $sql = "SELECT n.*, 
            CASE WHEN n.sender_id IS NOT NULL THEN u.name ELSE NULL END as sender_name,
            CASE WHEN n.sender_id IS NOT NULL THEN p.profile_pic ELSE NULL END as sender_pic
            FROM notifications n
            LEFT JOIN users u ON n.sender_id = u.id
            LEFT JOIN profiles p ON n.sender_id = p.user_id
            WHERE n.user_id = ?
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Mark notification as read
 * 
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security check)
 * @return bool True on success, false on failure
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 
            WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function markAllNotificationsAsRead($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Delete a notification
 * 
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security check)
 * @return bool True on success, false on failure
 */
function deleteNotification($conn, $notification_id, $user_id) {
    $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Clear all notifications for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function clearAllNotifications($conn, $user_id) {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Helper function to format notification time display
 * 
 * @param string $datetime Datetime string
 * @return string Formatted time string (e.g., "2 minutes ago", "Yesterday", etc.)
 */
function formatNotificationTime($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 172800) {
        return "Yesterday";
    } else {
        return date('d M Y', $timestamp);
    }
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $type Notification type for styling
 * @return bool True on success, false on failure
 */
function sendEmailNotification($to, $subject, $message, $type = 'general') {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Cupid <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
    
    // HTML Email Template
    $color = ($type == 'match') ? '#ff4b6e' : '#666666';
    $email_template = '
    <html>
    <head>
        <title>' . $subject . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; padding: 20px; background-color: #ffd9e0; color: #ff4b6e; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .button { display: inline-block; padding: 10px 20px; background-color: ' . $color . '; color: white; 
                     text-decoration: none; border-radius: 5px; font-weight: bold; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #777; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Cupid</h1>
            </div>
            <div class="content">
                ' . $message . '
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' Cupid. All rights reserved.<br>
                <small>If you don\'t want to receive these emails, you can update your notification settings in your profile.</small>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return mail($to, $subject, $email_template, $headers);
}

// Create the notifications_settings table if it doesn't exist
function createNotificationSettingsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notification_settings (
        user_id INT PRIMARY KEY,
        email_messages TINYINT(1) NOT NULL DEFAULT 1,
        email_likes TINYINT(1) NOT NULL DEFAULT 1,
        email_matches TINYINT(1) NOT NULL DEFAULT 1,
        browser_notifications TINYINT(1) NOT NULL DEFAULT 1,
        sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $conn->query($sql);
}

/**
 * Get user's notification settings
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return array User's notification settings
 */
function getNotificationSettings($conn, $user_id) {
    // First, make sure the table exists
    createNotificationSettingsTable($conn);
    
    // Check if user has notification settings
    $check_sql = "SELECT * FROM notification_settings WHERE user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    // If user doesn't have settings yet, create default settings
    if ($result->num_rows === 0) {
        $insert_sql = "INSERT INTO notification_settings (user_id) VALUES (?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("i", $user_id);
        $insert_stmt->execute();
        
        return [
            'email_messages' => 1,
            'email_likes' => 1,
            'email_matches' => 1,
            'browser_notifications' => 1,
            'sound_enabled' => 1
        ];
    }
    
    // Return user's settings
    return $result->fetch_assoc();
}

/**
 * Update user's notification settings
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param array $settings Associative array of settings to update
 * @return bool True on success, false on failure
 */
function updateNotificationSettings($conn, $user_id, $settings) {
    // Make sure the table exists
    createNotificationSettingsTable($conn);
    
    // Prepare the SQL query dynamically based on the settings provided
    $sql_parts = [];
    $types = "i"; // User ID parameter type
    $params = [$user_id]; // Start with user_id parameter
    
    foreach ($settings as $key => $value) {
        if (in_array($key, ['email_messages', 'email_likes', 'email_matches', 'browser_notifications', 'sound_enabled'])) {
            $sql_parts[] = "$key = ?";
            $types .= "i"; // All settings are integers (0 or 1)
            $params[] = $value ? 1 : 0;
        }
    }
    
    if (empty($sql_parts)) {
        return false; // No valid settings to update
    }
    
    // Build the final SQL query
    $sql = "INSERT INTO notification_settings (user_id, " . implode(", ", array_keys($settings)) . ") 
            VALUES (?, " . implode(", ", array_fill(0, count($settings), "?")) . ") 
            ON DUPLICATE KEY UPDATE " . implode(", ", $sql_parts);
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    return $stmt->execute();
}

// Create the notification tables when this file is included
function initializeNotificationSystem($conn) {
    createNotificationsTable($conn);
    createNotificationSettingsTable($conn);
}