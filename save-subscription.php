<?php
// save-subscription.php
// Save push notification subscription to database

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

// Validate the data
if (!$data || !isset($data['action'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request data'
    ]);
    exit();
}

// Check if we're subscribing or unsubscribing
if ($data['action'] === 'subscribe' && isset($data['subscription'])) {
    // Subscribing - extract subscription details
    $subscription = $data['subscription'];
    
    // Verify required subscription properties
    if (!isset($subscription['endpoint']) || 
        !isset($subscription['keys']) || 
        !isset($subscription['keys']['p256dh']) || 
        !isset($subscription['keys']['auth'])) {
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid subscription data'
        ]);
        exit();
    }
    
    // Extract subscription details
    $endpoint = $subscription['endpoint'];
    $p256dh = $subscription['keys']['p256dh'];
    $auth = $subscription['keys']['auth'];
    
    // Create push_subscriptions table if not exists
    $create_table_sql = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint VARCHAR(500) NOT NULL,
        p256dh VARCHAR(200) NOT NULL,
        auth VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id, endpoint),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!$conn->query($create_table_sql)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $conn->error
        ]);
        exit();
    }
    
    // Check if subscription already exists
    $check_sql = "SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("is", $user_id, $endpoint);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing subscription
        $update_sql = "UPDATE push_subscriptions 
                       SET p256dh = ?, auth = ?, updated_at = NOW() 
                       WHERE user_id = ? AND endpoint = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssis", $p256dh, $auth, $user_id, $endpoint);
        
        if ($update_stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Subscription updated'
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Error updating subscription: ' . $conn->error
            ]);
        }
    } else {
        // Insert new subscription
        $insert_sql = "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) 
                       VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("isss", $user_id, $endpoint, $p256dh, $auth);
        
        if ($insert_stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Subscription saved'
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Error saving subscription: ' . $conn->error
            ]);
        }
    }
} elseif ($data['action'] === 'unsubscribe') {
    // Unsubscribing - remove all subscriptions for this user
    
    // Check if endpoint is provided
    if (isset($data['endpoint'])) {
        // Delete specific subscription
        $delete_sql = "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("is", $user_id, $data['endpoint']);
    } else {
        // Delete all subscriptions for this user
        $delete_sql = "DELETE FROM push_subscriptions WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
    }
    
    if ($delete_stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Subscription(s) removed'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Error removing subscription: ' . $conn->error
        ]);
    }
} else {
    // Invalid action
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid action'
    ]);
}