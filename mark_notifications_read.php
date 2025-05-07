<?php
// mark_notifications_read.php
// Marks notifications as read for the current user

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Database connection
require_once 'config.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Create notifications_read table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS notifications_read (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    notification_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_notification (user_id, notification_type, notification_id),
    INDEX (user_id, read_at)
)";
$conn->query($create_table_sql);

// Get notification IDs from request
$notification_ids = [];
$notification_type = 'all'; // Default to all types

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If specific notification IDs were sent
    if (isset($_POST['notification_ids']) && is_array($_POST['notification_ids'])) {
        $notification_ids = array_map('intval', $_POST['notification_ids']);
    }
    
    // If a specific notification type was specified
    if (isset($_POST['notification_type'])) {
        $notification_type = $_POST['notification_type'];
    }
}

// Mark notifications as read based on type
if ($notification_type !== 'all' && !empty($notification_ids)) {
    // Mark specific notifications as read
    foreach ($notification_ids as $notification_id) {
        $insert_sql = "INSERT IGNORE INTO notifications_read (user_id, notification_type, notification_id) 
                      VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isi", $user_id, $notification_type, $notification_id);
        $insert_stmt->execute();
    }
} else {
    // Mark all unread chat messages as read
    // We'll need to find all unread messages first
    $unread_chats_sql = "SELECT cm.id, cs.id as session_id
                        FROM chat_messages cm
                        JOIN chat_sessions cs ON cm.session_id = cs.id
                        LEFT JOIN notifications_read nr ON nr.notification_id = cm.id AND nr.notification_type = 'chat' AND nr.user_id = ?
                        WHERE cm.sender_id != ?
                        AND (cs.user1_id = ? OR cs.user2_id = ?)
                        AND nr.id IS NULL";
    
    $unread_chats_stmt = $conn->prepare($unread_chats_sql);
    $unread_chats_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $unread_chats_stmt->execute();
    $unread_chats_result = $unread_chats_stmt->get_result();
    
    while ($message = $unread_chats_result->fetch_assoc()) {
        $insert_sql = "INSERT IGNORE INTO notifications_read (user_id, notification_type, notification_id) 
                      VALUES (?, 'chat', ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $message['id']);
        $insert_stmt->execute();
    }
    
    // Mark all unread menfess as read
    $unread_menfess_sql = "SELECT m.id
                          FROM menfess m
                          LEFT JOIN notifications_read nr ON nr.notification_id = m.id AND nr.notification_type = 'menfess' AND nr.user_id = ?
                          WHERE m.receiver_id = ?
                          AND nr.id IS NULL";
    
    $unread_menfess_stmt = $conn->prepare($unread_menfess_sql);
    $unread_menfess_stmt->bind_param("ii", $user_id, $user_id);
    $unread_menfess_stmt->execute();
    $unread_menfess_result = $unread_menfess_stmt->get_result();
    
    while ($menfess = $unread_menfess_result->fetch_assoc()) {
        $insert_sql = "INSERT IGNORE INTO notifications_read (user_id, notification_type, notification_id) 
                      VALUES (?, 'menfess', ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $menfess['id']);
        $insert_stmt->execute();
    }
    
    // Mark all unread menfess likes as read
    $unread_likes_sql = "SELECT ml.id
                        FROM menfess_likes ml
                        JOIN menfess m ON ml.menfess_id = m.id
                        LEFT JOIN notifications_read nr ON nr.notification_id = ml.id AND nr.notification_type = 'menfess_like' AND nr.user_id = ?
                        WHERE m.sender_id = ?
                        AND nr.id IS NULL";
    
    $unread_likes_stmt = $conn->prepare($unread_likes_sql);
    $unread_likes_stmt->bind_param("ii", $user_id, $user_id);
    $unread_likes_stmt->execute();
    $unread_likes_result = $unread_likes_stmt->get_result();
    
    while ($like = $unread_likes_result->fetch_assoc()) {
        $insert_sql = "INSERT IGNORE INTO notifications_read (user_id, notification_type, notification_id) 
                      VALUES (?, 'menfess_like', ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $like['id']);
        $insert_stmt->execute();
    }
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Notifications marked as read'
]);
?>