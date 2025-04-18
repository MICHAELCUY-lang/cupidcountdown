<?php
// save_subscription.php
// Save push notification subscription details to the database

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
$subscription = json_decode($jsonString, true);

// Validate subscription data
if (!isset($subscription['endpoint']) || !isset($subscription['keys']['p256dh']) || !isset($subscription['keys']['auth'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid subscription data']);
    exit();
}

$user_id = $_SESSION['user_id'];
$endpoint = $subscription['endpoint'];
$p256dh = $subscription['keys']['p256dh'];
$auth = $subscription['keys']['auth'];

// Check if the push_subscriptions table exists
$table_check = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
if ($table_check->num_rows == 0) {
    // Create the table if it doesn't exist
    $create_table_sql = "CREATE TABLE push_subscriptions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        endpoint VARCHAR(512) NOT NULL,
        p256dh VARCHAR(512) NOT NULL,
        auth VARCHAR(512) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id),
        UNIQUE KEY (user_id, endpoint)
    )";
    $conn->query($create_table_sql);
}

// Save subscription to database
try {
    // Check if subscription already exists for this user
    $check_sql = "SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $endpoint);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing subscription
        $subscription_id = $result->fetch_assoc()['id'];
        $update_sql = "UPDATE push_subscriptions SET p256dh = ?, auth = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssi", $p256dh, $auth, $subscription_id);
        $update_stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Subscription updated']);
    } else {
        // Insert new subscription
        $insert_sql = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isss", $user_id, $endpoint, $p256dh, $auth);
        $insert_stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Subscription saved']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error saving subscription: ' . $e->getMessage()]);
}