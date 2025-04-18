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

// Delete the message
$delete_sql = "DELETE FROM chat_messages WHERE id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $message_id);

if ($delete_stmt->execute()) {
    // Record the deletion in the deleted_messages table
    // First check if the table exists, create it if it doesn't
    $check_table_sql = "SHOW TABLES LIKE 'deleted_messages'";
    $table_exists = $conn->query($check_table_sql)->num_rows > 0;
    
    if (!$table_exists) {
        // Create the deleted_messages table
        $create_table_sql = "CREATE TABLE deleted_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            session_id INT NOT NULL,
            deleted_at INT NOT NULL,
            INDEX (session_id, deleted_at)
        )";
        $conn->query($create_table_sql);
    }
    
    // Record the deletion
    $current_time = time();
    $record_sql = "INSERT INTO deleted_messages (message_id, session_id, deleted_at) VALUES (?, ?, ?)";
    $record_stmt = $conn->prepare($record_sql);
    $record_stmt->bind_param("iii", $message_id, $session_id, $current_time);
    $record_stmt->execute();
    
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