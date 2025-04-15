<?php
// payment_callback.php
// Handle payment callbacks from Midtrans with improved error handling

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log the callback for debugging
$callback_data = print_r($_GET, true);
error_log("Payment callback received: " . $callback_data);

require_once 'config.php';
require_once 'payment_gateway.php';

// Initialize payment gateway
$paymentGateway = new PaymentGateway();

// Check if order_id and status are provided
if (!isset($_GET['order_id']) || !isset($_GET['status'])) {
    error_log("Payment callback error: Missing required parameters");
    redirect('dashboard.php?page=payments&error=missing_params');
    exit();
}

$orderId = $_GET['order_id'];
$status = $_GET['status'];
$transaction_status = isset($_GET['transaction_status']) ? $_GET['transaction_status'] : '';

error_log("Processing payment callback for Order ID: " . $orderId . ", Status: " . $status . ", Transaction Status: " . $transaction_status);

// Try to get payment data
try {
    // Verify the payment status
    $payment = $paymentGateway->checkPaymentStatus($orderId);
    error_log("Payment data retrieved: " . print_r($payment, true));
    
    if ($payment['status'] === 'not_found') {
        error_log("Payment not found in database: " . $orderId);
        redirect('dashboard.php?page=payments&error=payment_not_found');
        exit();
    }
    
    // Ensure we have target_user_id
    if (!isset($payment['target_user_id']) || empty($payment['target_user_id'])) {
        error_log("Missing target_user_id in payment data, trying to fetch from database");
        
        // Try to get it from the database directly
        $sql = "SELECT target_user_id FROM profile_reveal_payments WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $payment['target_user_id'] = $row['target_user_id'];
            error_log("Found target_user_id in database: " . $payment['target_user_id']);
        } else {
            error_log("Could not find target_user_id for order: " . $orderId);
            redirect('dashboard.php?page=payments&error=missing_target_user');
            exit();
        }
    }
    
    // If the transaction status from the callback indicates success, update payment status
    if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
        error_log("Transaction successful, updating payment status to completed");
        try {
            $paymentGateway->completePayment($orderId);
            $payment['status'] = 'completed';
        } catch (Exception $e) {
            error_log("Error completing payment: " . $e->getMessage());
        }
    }
    
    // Handle different callback statuses
    switch ($status) {
        case 'finish':
            error_log("Processing 'finish' status for order: " . $orderId);
            
            // Payment is completed or pending confirmation
            if ($payment['status'] === 'completed') {
                $targetUserId = intval($payment['target_user_id']);
                
                if ($targetUserId > 0) {
                    error_log("Redirecting to view_profile.php with id=" . $targetUserId);
                    redirect('view_profile.php?id=' . $targetUserId . '&from_payment=1&new=1');
                } else {
                    error_log("Invalid target_user_id: " . $targetUserId);
                    redirect('dashboard.php?page=payments&error=invalid_target');
                }
            } else {
                // Payment is still being processed
                error_log("Payment still pending, redirecting to dashboard");
                redirect('dashboard.php?page=payments&pending=' . $orderId);
            }
            break;
            
        case 'pending':
            // Payment is pending
            error_log("Payment pending, redirecting to dashboard");
            redirect('dashboard.php?page=payments&pending=' . $orderId);
            break;
            
        case 'error':
            // Payment failed
            error_log("Payment failed, redirecting to dashboard");
            redirect('dashboard.php?page=payments&failed=' . $orderId);
            break;
            
        default:
            // Unknown status
            error_log("Unknown payment status: " . $status);
            redirect('dashboard.php?page=payments&status=' . $status);
            break;
    }
} catch (Exception $e) {
    // Log the error and redirect to dashboard
    error_log("Exception in payment callback: " . $e->getMessage());
    redirect('dashboard.php?page=payments&error=exception&message=' . urlencode($e->getMessage()));
}

// Fallback redirect if none of the above worked
error_log("Fallback redirect triggered for order: " . $orderId);
redirect('dashboard.php?page=payments');