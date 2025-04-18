<?php
// check_messages.php
// Check for new messages and return them as JSON

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Database connection
require_once 'config.php';

$user_id = $_SESSION['user_id'];

// Get last seen timestamp from request or session
$lastSeen = isset($_GET['last_seen']) ? $_GET['last_seen'] : (isset($_SESSION['last_message_check']) ? $_SESSION['last_message_check'] : 0);

// Update last seen timestamp in session
$_SESSION['last_message_check'] = time();

// Get all chat sessions for the user
$sessions_sql = "SELECT id FROM chat_sessions WHERE user1_id = ? OR user2_id = ?";
$sessions_stmt = $conn->prepare($sessions_sql);
$sessions_stmt->bind_param("ii", $user_id, $user_id);
$sessions_stmt->execute();
$sessions_result = $sessions_stmt->get_result();

$chatSessionIds = [];
while ($row = $sessions_result->fetch_assoc()) {
    $chatSessionIds[] = $row['id'];
}

// No chat sessions found
if (empty($chatSessionIds)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'hasNewMessages' => false,
        'messages' => []
    ]);
    exit();
}

// Format the session IDs for SQL IN clause
$sessionIdsString = implode(',', $chatSessionIds);

// Get new messages from all sessions
$messages_sql = "SELECT 
                    m.id,
                    m.session_id,
                    m.sender_id,
                    m.message,
                    m.created_at,
                    s.user1_id,
                    s.user2_id,
                    u.name as sender_name,
                    (SELECT profile_pic FROM profiles WHERE user_id = m.sender_id) as sender_pic
                FROM 
                    chat_messages m
                JOIN 
                    chat_sessions s ON m.session_id = s.id
                JOIN 
                    users u ON m.sender_id = u.id
                WHERE 
                    m.session_id IN ({$sessionIdsString})
                    AND m.sender_id != ?
                    AND m.is_read = 0
                    AND m.created_at > FROM_UNIXTIME(?)
                ORDER BY 
                    m.created_at ASC";

$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("ii", $user_id, $lastSeen);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

$newMessages = [];
while ($row = $messages_result->fetch_assoc()) {
    // Format the message for response
    $newMessages[] = [
        'id' => $row['id'],
        'session_id' => $row['session_id'],
        'sender_id' => $row['sender_id'],
        'sender_name' => $row['sender_name'],
        'sender_pic' => $row['sender_pic'] ?? 'assets/images/user_profile.png',
        'message' => $row['message'],
        'time' => date('H:i', strtotime($row['created_at'])),
        'timestamp' => strtotime($row['created_at']),
        'partner_id' => ($row['user1_id'] == $user_id) ? $row['user2_id'] : $row['user1_id']
    ];
    
    // Mark message as read
    $update_sql = "UPDATE chat_messages SET is_read = 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $row['id']);
    $update_stmt->execute();
    
    // Send push notification for this message
    sendPushNotification($row, $user_id, $conn);
}

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'hasNewMessages' => !empty($newMessages),
    'messages' => $newMessages
]);

/**
 * Send push notification for a new message
 * 
 * @param array $message Message data
 * @param int $user_id The user ID to send notification to
 * @param mysqli $conn Database connection
 */
function sendPushNotification($message, $user_id, $conn) {
    // Check if the push_subscriptions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($table_check->num_rows == 0) {
        return; // No subscriptions table
    }
    
    // Get user's push subscriptions
    $sql = "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return; // No subscriptions found
    }
    
    // Load Web Push library if available
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        error_log('Web Push library not found. Please install via Composer.');
        return;
    }
    
    require_once __DIR__ . '/vendor/autoload.php';
    
    // Check if the Web Push library is available
    if (!class_exists('Minishlink\WebPush\WebPush')) {
        error_log('Web Push class not found. Please install minishlink/web-push via Composer.');
        return;
    }
    
    use Minishlink\WebPush\WebPush;
    use Minishlink\WebPush\Subscription;
    
    // VAPID keys (these should be generated and stored securely)
    // For production, generate your own keys and store them securely
    $publicKey = getenv('VAPID_PUBLIC_KEY');
    $privateKey = getenv('VAPID_PRIVATE_KEY');
    
    if (!$publicKey || !$privateKey) {
        error_log('VAPID keys not configured');
        return;
    }
    
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:support@yourcupid.com',
            'publicKey' => $publicKey,
            'privateKey' => $privateKey
        ]
    ];
    
    $webPush = new WebPush($auth);
    
    // Format the sender name
    $senderName = $message['sender_name'];
    
    // Prepare notification payload
    $payload = json_encode([
        'title' => 'New message from ' . $senderName,
        'body' => substr($message['message'], 0, 100) . (strlen($message['message']) > 100 ? '...' : ''),
        'icon' => $message['sender_pic'],
        'url' => '/chat.php?session_id=' . $message['session_id'],
        'messageId' => $message['session_id'],
        'senderId' => $message['sender_id'],
        'tag' => 'chat-' . $message['session_id'] // Group notifications by chat session
    ]);
    
    // Send notification to all user's subscriptions
    while ($row = $result->fetch_assoc()) {
        $subscription = Subscription::create([
            'endpoint' => $row['endpoint'],
            'keys' => [
                'p256dh' => $row['p256dh'],
                'auth' => $row['auth']
            ]
        ]);
        
        $webPush->queueNotification($subscription, $payload);
    }
    
    // Send all notifications
    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            error_log("Push notification failed: {$report->getReason()}");
            
            // If subscription is expired or invalid, remove it
            if (strpos($report->getReason(), 'expired') !== false || 
                strpos($report->getReason(), 'invalid') !== false) {
                $endpoint = $report->getEndpoint();
                $delete_sql = "DELETE FROM push_subscriptions WHERE endpoint = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("s", $endpoint);
                $delete_stmt->execute();
            }
        }
    }
}