<?php
// check_notifications.php
// This script checks for new notifications (messages, menfess, etc.)

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

// Get the last check time from request or use a default (5 minutes ago)
$last_check = isset($_GET['last_check']) ? $_GET['last_check'] : date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Array to store notifications
$notifications = [];

// Check for new chat messages
$chat_sql = "SELECT cm.*, cs.id as session_id,
            CASE WHEN cs.user1_id = ? THEN u2.name ELSE u1.name END as sender_name,
            CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END as sender_id
            FROM chat_messages cm
            JOIN chat_sessions cs ON cm.session_id = cs.id
            JOIN users u1 ON cs.user1_id = u1.id
            JOIN users u2 ON cs.user2_id = u2.id
            LEFT JOIN hidden_chats hc ON cs.id = hc.session_id AND hc.user_id = ?
            WHERE cm.created_at > ?
            AND cm.sender_id != ?
            AND (cs.user1_id = ? OR cs.user2_id = ?)
            AND hc.id IS NULL
            ORDER BY cm.created_at DESC";

$chat_stmt = $conn->prepare($chat_sql);
$chat_stmt->bind_param("iiisiii", $user_id, $user_id, $user_id, $last_check, $user_id, $user_id, $user_id);
$chat_stmt->execute();
$chat_result = $chat_stmt->get_result();

while ($message = $chat_result->fetch_assoc()) {
    // Skip deleted messages if that table exists
    if (tableExists($conn, 'deleted_messages')) {
        $deleted_check = $conn->prepare("SELECT COUNT(*) as count FROM deleted_messages WHERE message_id = ?");
        $deleted_check->bind_param("i", $message['id']);
        $deleted_check->execute();
        $deleted_result = $deleted_check->get_result();
        $is_deleted = $deleted_result->fetch_assoc()['count'] > 0;
        
        if ($is_deleted) {
            continue;
        }
    }
    
    $notifications[] = [
        'type' => 'chat',
        'id' => $message['id'],
        'session_id' => $message['session_id'],
        'sender_id' => $message['sender_id'],
        'sender_name' => $message['sender_name'],
        'message' => $message['message'],
        'created_at' => $message['created_at'],
        'url' => 'chat?session_id=' . $message['session_id']
    ];
}

// Check for new menfess
$menfess_sql = "SELECT m.*, 
               (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = m.id AND user_id = ?) as user_liked
               FROM menfess m
               WHERE m.created_at > ?
               AND m.receiver_id = ?
               ORDER BY m.created_at DESC";

$menfess_stmt = $conn->prepare($menfess_sql);
$menfess_stmt->bind_param("isi", $user_id, $last_check, $user_id);
$menfess_stmt->execute();
$menfess_result = $menfess_stmt->get_result();

while ($menfess = $menfess_result->fetch_assoc()) {
    $notifications[] = [
        'type' => 'menfess',
        'id' => $menfess['id'],
        'message' => $menfess['message'],
        'is_anonymous' => $menfess['is_anonymous'],
        'is_revealed' => $menfess['is_revealed'],
        'created_at' => $menfess['created_at'],
        'url' => 'dashboard?page=menfess'
    ];
}

// Check for new menfess likes/matches
if (isset($_GET['check_likes']) && $_GET['check_likes'] == 1) {
    $likes_sql = "SELECT ml.*, m.message, m.receiver_id, 
                 u.name as liker_name
                 FROM menfess_likes ml
                 JOIN menfess m ON ml.menfess_id = m.id
                 JOIN users u ON ml.user_id = u.id
                 WHERE ml.created_at > ?
                 AND m.sender_id = ?
                 AND ml.user_id = m.receiver_id";
    
    $likes_stmt = $conn->prepare($likes_sql);
    $likes_stmt->bind_param("si", $last_check, $user_id);
    $likes_stmt->execute();
    $likes_result = $likes_stmt->get_result();
    
    while ($like = $likes_result->fetch_assoc()) {
        $notifications[] = [
            'type' => 'menfess_like',
            'id' => $like['id'],
            'menfess_id' => $like['menfess_id'],
            'liker_id' => $like['user_id'],
            'liker_name' => $like['liker_name'],
            'message' => $like['message'],
            'created_at' => $like['created_at'],
            'url' => 'dashboard?page=menfess'
        ];
        
        // Check if this creates a match
        $check_match_sql = "SELECT 
                           (SELECT COUNT(*) FROM menfess_likes 
                            WHERE menfess_id = ? AND user_id = ?) as sender_liked,
                           (SELECT COUNT(*) FROM menfess_likes 
                            WHERE menfess_id = ? AND user_id = ?) as receiver_liked";
        
        $check_match_stmt = $conn->prepare($check_match_sql);
        $receiver_id = $like['receiver_id'];
        $menfess_id = $like['menfess_id'];
        $check_match_stmt->bind_param("iiii", $menfess_id, $receiver_id, $menfess_id, $user_id);
        $check_match_stmt->execute();
        $match_result = $check_match_stmt->get_result();
        $match_data = $match_result->fetch_assoc();
        
        if ($match_data['sender_liked'] > 0 && $match_data['receiver_liked'] > 0) {
            // It's a match! Add a match notification
            $notifications[] = [
                'type' => 'match',
                'id' => $like['menfess_id'],
                'match_name' => $like['liker_name'],
                'match_id' => $like['user_id'],
                'created_at' => $like['created_at'],
                'url' => 'dashboard?page=matches'
            ];
        }
    }
}

// Return the current time as the new last_check
$current_time = date('Y-m-d H:i:s');

// Return notifications as JSON
echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'last_check' => $current_time
]);

// Helper function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}
?>