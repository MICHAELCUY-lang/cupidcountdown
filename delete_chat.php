<?php
// delete_chat.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['session_id']) || empty($_POST['session_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID is required']);
    exit();
}

if (!isset($_POST['delete_type']) || empty($_POST['delete_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Delete type is required']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "u287442801_cupid";
$password = "Cupid1234!";
$dbname = "u287442801_cupid";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$session_id = intval($_POST['session_id']);
$delete_type = $_POST['delete_type']; // 'for_me' or 'for_everyone'

// First, verify that the user is part of this chat session
$check_sql = "SELECT * FROM chat_sessions WHERE id = ? AND (user1_id = ? OR user2_id = ?)";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("iii", $session_id, $user_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not part of this chat session']);
    exit();
}

$chat_session = $result->fetch_assoc();

// Get partner ID
$partner_id = ($chat_session['user1_id'] == $user_id) ? $chat_session['user2_id'] : $chat_session['user1_id'];

// Start transaction
$conn->begin_transaction();

try {
    if ($delete_type == 'for_me') {
        // Create hidden_chats table if it doesn't exist
        $create_table_sql = "CREATE TABLE IF NOT EXISTS hidden_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id INT NOT NULL,
            hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_session (user_id, session_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
        )";
        $conn->query($create_table_sql);
        
        // Mark the chat as hidden for this user
        $hide_sql = "INSERT INTO hidden_chats (user_id, session_id) VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE hidden_at = CURRENT_TIMESTAMP";
        $hide_stmt = $conn->prepare($hide_sql);
        $hide_stmt->bind_param("ii", $user_id, $session_id);
        $hide_stmt->execute();
        
    } elseif ($delete_type == 'for_everyone') {
        // Delete all messages in this chat
        $delete_messages_sql = "DELETE FROM chat_messages WHERE session_id = ?";
        $delete_messages_stmt = $conn->prepare($delete_messages_sql);
        $delete_messages_stmt->bind_param("i", $session_id);
        $delete_messages_stmt->execute();
        
        // Delete the chat session
        $delete_session_sql = "DELETE FROM chat_sessions WHERE id = ?";
        $delete_session_stmt = $conn->prepare($delete_session_sql);
        $delete_session_stmt->bind_param("i", $session_id);
        $delete_session_stmt->execute();
        
        // Delete any profile view permissions if this was a blind chat
        if ($chat_session['is_blind'] == 1) {
            $delete_permission_sql = "DELETE FROM profile_view_permissions 
                                     WHERE (user_id = ? AND target_user_id = ?) 
                                     OR (user_id = ? AND target_user_id = ?)";
            $delete_permission_stmt = $conn->prepare($delete_permission_sql);
            $delete_permission_stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
            $delete_permission_stmt->execute();
        }
    } else {
        throw new Exception("Invalid delete type");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Chat deleted successfully',
        'delete_type' => $delete_type
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete chat: ' . $e->getMessage()]);
}