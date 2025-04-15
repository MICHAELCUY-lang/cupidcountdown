<?php
// create_profile_payment.php
// Initialize payment for profile reveal using Midtrans

require_once 'config.php';
require_once 'payment_gateway.php';

// Make sure user is logged in
requireLogin();

// Check if partner ID is provided
if (!isset($_GET['partner_id']) || !isset($_GET['chat_id'])) {
    redirect('dashboard.php?page=chat');
    exit();
}

$partnerId = intval($_GET['partner_id']);
$chatId = intval($_GET['chat_id']);
$userId = $_SESSION['user_id'];

// Verify that this is a valid chat session
$session_sql = "SELECT * FROM chat_sessions 
                WHERE id = ? 
                AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
                AND is_blind = 1";
$session_stmt = $conn->prepare($session_sql);
$session_stmt->bind_param("iiiii", $chatId, $userId, $partnerId, $partnerId, $userId);
$session_stmt->execute();
$session_result = $session_stmt->get_result();

if ($session_result->num_rows === 0) {
    redirect('dashboard.php?page=chat&error=invalid_chat');
    exit();
}

// Check if user already has permission to view this profile
$permission_sql = "SELECT * FROM profile_view_permissions WHERE user_id = ? AND target_user_id = ?";
$permission_stmt = $conn->prepare($permission_sql);
$permission_stmt->bind_param("ii", $userId, $partnerId);
$permission_stmt->execute();
$permission_result = $permission_stmt->get_result();

if ($permission_result->num_rows > 0) {
    // User already has permission, redirect to profile
    redirect('view_profile.php?id=' . $partnerId);
    exit();
}

// Initialize payment gateway
$paymentGateway = new PaymentGateway();

// Create payment with fixed amount of 15,000 IDR
$payment = $paymentGateway->createProfileRevealPayment($userId, $partnerId, 15000);

// Redirect to payment process page
redirect($payment['payment_url']);