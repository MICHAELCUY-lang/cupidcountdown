<?php
// check_payment_status.php
// Simple endpoint to check payment status

require_once 'config.php';
require_once 'payment_gateway.php';

// Make sure user is logged in
requireLogin();

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id parameter']);
    exit();
}

$orderId = $_GET['order_id'];
$userId = $_SESSION['user_id'];

// Initialize payment gateway
$paymentGateway = new PaymentGateway();

// Check payment status
$payment = $paymentGateway->checkPaymentStatus($orderId);

// Make sure the payment exists and belongs to the current user
if ($payment['status'] === 'not_found' || $payment['user_id'] != $userId) {
    http_response_code(404);
    echo json_encode(['error' => 'Payment not found or does not belong to user']);
    exit();
}

// Return payment status
echo json_encode([
    'order_id' => $payment['order_id'],
    'status' => $payment['status'],
    'amount' => $payment['amount'],
    'target_user_id' => $payment['target_user_id'],
    'created_at' => $payment['created_at'],
    'paid_at' => $payment['paid_at'] ?? null
]);