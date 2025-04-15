<?php
// midtrans_notification.php
// Handle server-to-server notifications from Midtrans

require_once 'config.php';
require_once 'env.php';  // Load environment variables
require_once 'payment_gateway.php';

// Get JSON POST data
$notification = json_decode(file_get_contents('php://input'), true);
$orderId = $notification['order_id'] ?? null;
$transactionStatus = $notification['transaction_status'] ?? null;
$fraudStatus = $notification['fraud_status'] ?? null;

// Initialize payment gateway
$paymentGateway = new PaymentGateway();

// Verify signature
$serverKey = getenv('MIDTRANS_SERVER_KEY');
$validSignatureKey = hash('sha512', $orderId . $notification['status_code'] . $notification['gross_amount'] . $serverKey);

if ($notification['signature_key'] != $validSignatureKey) {
    http_response_code(403);
    echo "Invalid signature";
    exit();
}

// Convert transaction status to our payment status
$newStatus = 'pending';

if ($transactionStatus == 'capture') {
    // For credit card, we need to check the fraud status
    if ($fraudStatus == 'challenge') {
        $newStatus = 'pending'; // Manual verification required
    } else if ($fraudStatus == 'accept') {
        $newStatus = 'completed';
    }
} else if ($transactionStatus == 'settlement') {
    $newStatus = 'completed';
} else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
    $newStatus = 'failed';
} else if ($transactionStatus == 'pending') {
    $newStatus = 'pending';
}

// Update payment status in database
$sql = "UPDATE profile_reveal_payments SET status = ?, paid_at = NOW() WHERE order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $newStatus, $orderId);
$stmt->execute();

// If payment is completed, grant profile access permission
if ($newStatus === 'completed') {
    $sql = "SELECT user_id, target_user_id FROM profile_reveal_payments WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    
    if ($payment) {
        // Grant permission to view the profile
        $sql = "INSERT INTO profile_view_permissions (user_id, target_user_id, created_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE created_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $payment['user_id'], $payment['target_user_id']);
        $stmt->execute();
    }
}

// Return 200 OK to Midtrans
http_response_code(200);
echo "OK";