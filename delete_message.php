<?php
// delete_chat.php
session_start();

// Enable error reporting for debugging (you can remove this in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to return JSON response
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'User not logged in');
}

// Log received POST data for debugging
$postData = file_get_contents('php://input');
error_log("DELETE_CHAT received data: " . print_r($_POST, true));

// Check if required parameters are provided
if (!isset($_POST['session_id']) || empty($_POST['session_id'])) {
    jsonResponse(false, 'Session ID is required');
}

if (!isset($_POST['delete_type']) || empty($_POST['delete_type'])) {
    jsonResponse(false, 'Delete type is required');
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
    jsonResponse(false, 'Database connection failed: ' . $conn->connect_error);
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
    jsonResponse(false, 'You are not part of this chat session');
}

$chat_session = $result->fetch_assoc();

// Get partner ID
$partner_id = ($chat_session['user1_id'] == $user_id) ? $chat_session['user2_id'] : $chat_session['user1_id'];

try {
    if ($delete_type == 'for_me') {
        // Insert into hidden_chats table
        $hide_sql = "INSERT INTO hidden_chats (user_id, session_id) VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE hidden_at = CURRENT_TIMESTAMP";
        $hide_stmt = $conn->prepare($hide_sql);
        $hide_stmt->bind_param("ii", $user_id, $session_id);
        
        if (!$hide_stmt->execute()) {
            throw new Exception("Failed to hide chat: " . $conn->error);
        }
        
        jsonResponse(true, 'Chat hidden successfully', [
            'session_id' => $session_id,
            'delete_type' => $delete_type
        ]);
        
    } elseif ($delete_type == 'for_everyone') {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete all messages in this chat
            $delete_messages_sql = "DELETE FROM chat_messages WHERE session_id = ?";
            $delete_messages_stmt = $conn->prepare($delete_messages_sql);
            $delete_messages_stmt->bind_param("i", $session_id);
            
            if (!$delete_messages_stmt->execute()) {
                throw new Exception("Failed to delete messages: " . $conn->error);
            }
            
            // Delete the chat session
            $delete_session_sql = "DELETE FROM chat_sessions WHERE id = ?";
            $delete_session_stmt = $conn->prepare($delete_session_sql);
            $delete_session_stmt->bind_param("i", $session_id);
            
            if (!$delete_session_stmt->execute()) {
                throw new Exception("Failed to delete chat session: " . $conn->error);
            }
            
            // Delete any profile view permissions if this was a blind chat
            if ($chat_session['is_blind'] == 1) {
                $delete_permission_sql = "DELETE FROM profile_view_permissions 
                                         WHERE (user_id = ? AND target_user_id = ?) 
                                         OR (user_id = ? AND target_user_id = ?)";
                $delete_permission_stmt = $conn->prepare($delete_permission_sql);
                $delete_permission_stmt->bind_param("iiii", $user_id, $partner_id, $partner_id, $user_id);
                
                if (!$delete_permission_stmt->execute()) {
                    throw new Exception("Failed to delete profile permissions: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            jsonResponse(true, 'Chat deleted for everyone successfully', [
                'session_id' => $session_id,
                'delete_type' => $delete_type
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw $e;
        }
    } else {
        jsonResponse(false, "Invalid delete type: " . $delete_type);
    }
    
} catch (Exception $e) {
    error_log("Delete chat error: " . $e->getMessage());
    jsonResponse(false, 'Failed to delete chat: ' . $e->getMessage());
}