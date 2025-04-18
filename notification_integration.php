<?php
/**
 * Notification Integration
 * 
 * This file contains functions to integrate notifications with existing features
 */

/**
 * Create notification hooks for existing features
 * Add these to your existing PHP files
 */

// Include these at the top of relevant PHP files
// require_once 'notifications.php';

/**
 * Hook for new messages - Add this to chat.php after a message is sent
 */
function notifyNewMessage($conn, $receiver_id, $sender_id, $message, $chat_session_id) {
    // Get sender name
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sender = $result->fetch_assoc();
    $sender_name = $sender['name'];
    
    // Create notification
    $message_preview = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
    $content = $sender_name . " sent you a message: " . $message_preview;
    createNotification($conn, $receiver_id, $sender_id, 'message', $content, $chat_session_id);
    
    // Check if email notifications are enabled for this user
    $settings = getNotificationSettings($conn, $receiver_id);
    if ($settings['email_messages']) {
        // Get user email
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $receiver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $email = $user['email'];
            $subject = "New message from " . $sender_name;
            $email_content = "
                <h2>Hello,</h2>
                <p>You have received a new message from <strong>" . htmlspecialchars($sender_name) . "</strong>.</p>
                <p><em>\"" . htmlspecialchars($message_preview) . "\"</em></p>
                <p style='text-align: center;'>
                    <a href='https://" . $_SERVER['HTTP_HOST'] . "/chat?session_id=" . $chat_session_id . "' class='button'>
                        View Message
                    </a>
                </p>
                <p>Log in to your Cupid account to continue the conversation!</p>
            ";
            
            sendEmailNotification($email, $subject, $email_content);
        }
    }
}

/**
 * Hook for menfess likes - Add this to menfess.php after someone likes a menfess
 */
function notifyMenfessLike($conn, $menfess_id, $liker_id) {
    // Get menfess details
    $stmt = $conn->prepare("SELECT sender_id, message FROM menfess WHERE id = ?");
    $stmt->bind_param("i", $menfess_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $menfess = $result->fetch_assoc();
    
    if (!$menfess) return false;
    
    $sender_id = $menfess['sender_id'];
    $message_preview = strlen($menfess['message']) > 50 ? substr($menfess['message'], 0, 50) . '...' : $menfess['message'];
    
    // Get liker name
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $liker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $liker = $result->fetch_assoc();
    $liker_name = $liker['name'];
    
    // Create notification
    $content = $liker_name . " liked your menfess: \"" . $message_preview . "\"";
    createNotification($conn, $sender_id, $liker_id, 'like', $content, $menfess_id);
    
    // Check if email notifications are enabled for this user
    $settings = getNotificationSettings($conn, $sender_id);
    if ($settings['email_likes']) {
        // Get user email
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $sender_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $email = $user['email'];
            $subject = $liker_name . " liked your menfess";
            $email_content = "
                <h2>Hello,</h2>
                <p><strong>" . htmlspecialchars($liker_name) . "</strong> liked your menfess:</p>
                <p><em>\"" . htmlspecialchars($message_preview) . "\"</em></p>
                <p style='text-align: center;'>
                    <a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard?page=menfess' class='button'>
                        View Menfess
                    </a>
                </p>
                <p>Log in to your Cupid account to see who liked your message!</p>
            ";
            
            sendEmailNotification($email, $subject, $email_content);
        }
    }
}

/**
 * Hook for new matches - Add this to the match creation logic
 */
function notifyNewMatch($conn, $user1_id, $user2_id, $menfess_id) {
    // Get user names
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id IN (?, ?)");
    $stmt->bind_param("ii", $user1_id, $user2_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[$row['id']] = $row;
    }
    
    if (count($users) !== 2) return false;
    
    // Create notifications for both users
    $content1 = "You have a new match with " . $users[$user2_id]['name'] . "!";
    $content2 = "You have a new match with " . $users[$user1_id]['name'] . "!";
    
    createNotification($conn, $user1_id, $user2_id, 'match', $content1, $menfess_id);
    createNotification($conn, $user2_id, $user1_id, 'match', $content2, $menfess_id);
    
    // Send email notifications
    // User 1
    $settings1 = getNotificationSettings($conn, $user1_id);
    if ($settings1['email_matches']) {
        $email_content1 = "
            <h2>Congratulations!</h2>
            <p>You have a new match with <strong>" . htmlspecialchars($users[$user2_id]['name']) . "</strong>!</p>
            <p>You both liked each other's menfess, and now you can start chatting!</p>
            <p style='text-align: center;'>
                <a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard?page=matches' class='button'>
                    View Match
                </a>
            </p>
            <p>Don't wait - start a conversation now!</p>
        ";
        
        sendEmailNotification($users[$user1_id]['email'], "New Match on Cupid!", $email_content1, 'match');
    }
    
    // User 2
    $settings2 = getNotificationSettings($conn, $user2_id);
    if ($settings2['email_matches']) {
        $email_content2 = "
            <h2>Congratulations!</h2>
            <p>You have a new match with <strong>" . htmlspecialchars($users[$user1_id]['name']) . "</strong>!</p>
            <p>You both liked each other's menfess, and now you can start chatting!</p>
            <p style='text-align: center;'>
                <a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard?page=matches' class='button'>
                    View Match
                </a>
            </p>
            <p>Don't wait - start a conversation now!</p>
        ";
        
        sendEmailNotification($users[$user2_id]['email'], "New Match on Cupid!", $email_content2, 'match');
    }
}

/**
 * Modify your existing menfess like handler to include notification
 * Add this to menfess.php or the relevant file
 */

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_menfess'])) {
    $menfess_id = $_POST['menfess_id'];
    
    // Check if already liked
    $check_sql = "SELECT * FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $menfess_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Unlike
        $unlike_sql = "DELETE FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
        $unlike_stmt = $conn->prepare($unlike_sql);
        $unlike_stmt->bind_param("ii", $user_id, $menfess_id);
        
        if ($unlike_stmt->execute()) {
            // Refresh the page to update
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        // Like
        $like_sql = "INSERT INTO menfess_likes (user_id, menfess_id) VALUES (?, ?)";
        $like_stmt = $conn->prepare($like_sql);
        $like_stmt->bind_param("ii", $user_id, $menfess_id);
        
        if ($like_stmt->execute()) {
            // Send notification to the menfess sender
            notifyMenfessLike($conn, $menfess_id, $user_id);
            
            // Check if both sender and receiver have liked the menfess
            $check_mutual_sql = "SELECT 
                                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = ? AND user_id = (SELECT receiver_id FROM menfess WHERE id = ?)) as receiver_liked,
                                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = ? AND user_id = (SELECT sender_id FROM menfess WHERE id = ?)) as sender_liked,
                                (SELECT sender_id FROM menfess WHERE id = ?) as sender_id,
                                (SELECT receiver_id FROM menfess WHERE id = ?) as receiver_id";
            $check_mutual_stmt = $conn->prepare($check_mutual_sql);
            $check_mutual_stmt->bind_param("iiiiii", $menfess_id, $menfess_id, $menfess_id, $menfess_id, $menfess_id, $menfess_id);
            $check_mutual_stmt->execute();
            $check_mutual_result = $check_mutual_stmt->get_result();
            $mutual = $check_mutual_result->fetch_assoc();
            
            if ($mutual['receiver_liked'] > 0 && $mutual['sender_liked'] > 0) {
                // It's a match! Reveal identities
                $reveal_sql = "UPDATE menfess SET is_revealed = 1 WHERE id = ?";
                $reveal_stmt = $conn->prepare($reveal_sql);
                $reveal_stmt->bind_param("i", $menfess_id);
                $reveal_stmt->execute();
                
                // Notify both users about the match
                notifyNewMatch($conn, $mutual['sender_id'], $mutual['receiver_id'], $menfess_id);
            }
            
            // Refresh the page to update
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}
*/

/**
 * Modify your chat message sender to include notification
 * Add this to chat.php or the relevant file
 */

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = $_POST['message'];
    
    if (!empty(trim($message))) {
        $insert_sql = "INSERT INTO chat_messages (session_id, sender_id, message) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $session_id, $user_id, $message);
        
        if ($insert_stmt->execute()) {
            // Get the receiver ID (the other person in the chat)
            $partner_id = ($chat_session['user1_id'] == $user_id) ? $chat_session['user2_id'] : $chat_session['user1_id'];
            
            // Send notification
            notifyNewMessage($conn, $partner_id, $user_id, $message, $session_id);
            
            // Successfully sent, refresh the page to display new message
            header("Location: chat.php?session_id=" . $session_id);
            exit();
        } else {
            $error_message = "Error sending message: " . $conn->error;
        }
    }
}
*/

/**
 * Check for new notifications periodically
 * Include this function in your JavaScript
 */
function pollNotifications() {
    // JavaScript example - add to your main.js or a separate notifications.js file
    /*
    function checkNotifications() {
        fetch('notification_api.php?action=get_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.count);
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }
    
    // Update notification badge/icon
    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
                
                // Play notification sound if it's enabled
                if (notificationSoundEnabled) {
                    playNotificationSound();
                }
            } else {
                badge.style.display = 'none';
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
    
    // Start polling when page loads
    document.addEventListener('DOMContentLoaded', startNotificationPolling);
    
    // Stop polling when page unloads
    window.addEventListener('beforeunload', stopNotificationPolling);
    */
}

/**
 * System notification for important announcements
 */
function sendSystemNotification($conn, $message, $userIds = null) {
    if ($userIds === null) {
        // Send to all users
        $sql = "SELECT id FROM users WHERE is_blocked = 0";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            createNotification($conn, $row['id'], null, 'system', $message);
        }
    } else {
        // Send to specific users
        foreach ($userIds as $userId) {
            createNotification($conn, $userId, null, 'system', $message);
        }
    }
}