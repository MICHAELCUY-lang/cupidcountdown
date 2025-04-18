<?php
// remove_subscription.php
// Remove push notification subscription from the database

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

// Get the subscription data from the request
$jsonString = file_get_contents('php://input');
$data = json_decode($jsonString, true);

// Validate data
if (!isset($data['endpoint'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$user_id = $_SESSION['user_id'];
$endpoint = $data['endpoint'];

// Remove subscription from database
try {
    // Check if push_subscriptions table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
    if ($table_check->num_rows == 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No subscriptions found']);
        exit();
    }
    
    // Delete subscription
    $delete_sql = "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("is", $user_id, $endpoint);
    $delete_stmt->execute();
    
    if ($delete_stmt->affected_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Subscription removed']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Subscription not found']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error removing subscription: ' . $e->getMessage()]);
}