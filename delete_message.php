<?php
// delete_message.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Check if message ID is provided
if (!isset($_POST['message_id']) || empty($_POST['message_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message ID is required']);
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
$message_id = intval($_POST['message_id']);

// Verify that the message belongs to the user
$check_sql = "SELECT * FROM chat_messages WHERE id = ? AND sender_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $message_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only delete your own messages']);
    exit();
}

// Get the session ID for the message
$message = $result->fetch_assoc();
$session_id = $message['session_id'];

// Delete the message (or mark it as deleted)
// Option 1: Hard delete
$delete_sql = "DELETE FROM chat_messages WHERE id = ?";

// Option 2: Soft delete (add a deleted column to your chat_messages table first)
// $delete_sql = "UPDATE chat_messages SET deleted = 1 WHERE id = ?";

$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $message_id);

if ($delete_stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'Message deleted',
        'message_id' => $message_id,
        'session_id' => $session_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
}