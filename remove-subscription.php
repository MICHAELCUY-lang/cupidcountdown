<?php
// remove-subscription.php
// Remove push notification subscription from database

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'User not logged in'
    ]);
    exit();
}

// Database connection
require_once 'config.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get JSON request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate request data
if (!$data) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request data'
    ]);
    exit();
}

// Check if we have an endpoint
if (isset($data['endpoint'])) {
    // Delete specific subscription by endpoint
    $delete_sql = "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("is", $user_id, $data['endpoint']);
} else {
    // Delete all subscriptions for this user
    $delete_sql = "DELETE FROM push_subscriptions WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
}

// Execute the delete query
if ($delete_stmt->execute()) {
    // Check if any rows were affected
    if ($delete_stmt->affected_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Subscription removed successfully',
            'rows_affected' => $delete_stmt->affected_rows
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'No matching subscription found to remove'
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error removing subscription: ' . $conn->error
    ]);
}