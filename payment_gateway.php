<?php
// payment_gateway.php
// Payment gateway integration for profile reveal using Midtrans

// Include environment variables if not already included
if (!isset($_ENV['MIDTRANS_SERVER_KEY'])) {
    require_once 'env.php';
}

class PaymentGateway {
    private $midtransServerKey;
    private $midtransClientKey;
    private $isProduction;
    
    public function __construct($serverKey = null, $clientKey = null, $isProduction = false) {
    // Get values from environment variables or use passed parameters
    $this->midtransServerKey = $serverKey ?: getenv('MIDTRANS_SERVER_KEY');
    $this->midtransClientKey = $clientKey ?: getenv('MIDTRANS_CLIENT_KEY');
    $this->isProduction = $isProduction ?: (getenv('MIDTRANS_IS_PRODUCTION') === 'true');
}
    /**
     * Create a payment request for profile reveal
     * 
     * @param int $userId User requesting the reveal
     * @param int $targetUserId Target user whose profile is being revealed
     * @param int $amount Payment amount (in IDR)
     * @return array Payment details including redirect URL
     */
    public function createProfileRevealPayment($userId, $targetUserId, $amount = 15000) {
        global $conn;
        
        // Generate unique order ID
        $orderId = 'REVEAL-' . time() . '-' . $userId . '-' . $targetUserId;
        
        // Get user data for Midtrans
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Store payment request in database
        $insertSql = "INSERT INTO profile_reveal_payments 
                (order_id, user_id, target_user_id, amount, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("siid", $orderId, $userId, $targetUserId, $amount);
        $stmt->execute();
        
        // Set Midtrans API endpoint
        $apiUrl = $this->isProduction 
            ? 'https://app.midtrans.com/snap/v1/transactions' 
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
        
        // Get target user info for transaction details
        $targetSql = "SELECT name FROM users WHERE id = ?";
        $targetStmt = $conn->prepare($targetSql);
        $targetStmt->bind_param("i", $targetUserId);
        $targetStmt->execute();
        $targetResult = $targetStmt->get_result();
        $targetUser = $targetResult->fetch_assoc();
        
        // Prepare transaction data for Midtrans
        $transactionDetails = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount
            ],
            'customer_details' => [
                'first_name' => $user['name'],
                'email' => $user['email']
            ],
            'item_details' => [
                [
                    'id' => 'PROFILE-' . $targetUserId,
                    'price' => $amount,
                    'quantity' => 1,
                    'name' => 'Profile Reveal: ' . $targetUser['name']
                ]
            ],
            'callbacks' => [
                'finish' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_callback.php?status=finish&order_id=' . $orderId,
                'error' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_callback.php?status=error&order_id=' . $orderId,
                'pending' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment_callback.php?status=pending&order_id=' . $orderId
            ]
        ];
        
        // Set up cURL for Midtrans API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transactionDetails));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->midtransServerKey . ':')
        ]);
        
        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Parse the response
        if ($httpCode == 201) {
            $responseData = json_decode($response, true);
            
            return [
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_url' => $responseData['redirect_url'],
                'token' => $responseData['token'],
                'status' => 'pending'
            ];
        } else {
            // Log the error for debugging
            error_log("Midtrans API Error: " . $response);
            
            // Return local payment URL as fallback
            return [
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_url' => 'payment_process.php?order_id=' . $orderId,
                'status' => 'pending'
            ];
        }
    }
    
    /**
     * Check payment status
     * 
     * @param string $orderId The order ID to check
     * @return array Payment status details
     */
    public function checkPaymentStatus($orderId) {
        global $conn;
        
        // Get payment status from database
        $sql = "SELECT * FROM profile_reveal_payments WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['status' => 'not_found'];
        }
        
        $payment = $result->fetch_assoc();
        
        // If payment is still pending, check with Midtrans for latest status
        if ($payment['status'] === 'pending') {
            $apiUrl = $this->isProduction 
                ? 'https://api.midtrans.com/v2/' . $orderId . '/status' 
                : 'https://api.sandbox.midtrans.com/v2/' . $orderId . '/status';
            
            // Set up cURL for Midtrans API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->midtransServerKey . ':')
            ]);
            
            // Execute cURL request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Parse the response
            if ($httpCode == 200) {
                $responseData = json_decode($response, true);
                
                // Map Midtrans transaction status to our status
                $midtransStatus = $responseData['transaction_status'] ?? 'pending';
                $newStatus = 'pending';
                
                switch ($midtransStatus) {
                    case 'capture':
                    case 'settlement':
                        $newStatus = 'completed';
                        break;
                    case 'deny':
                    case 'cancel':
                    case 'expire':
                        $newStatus = 'failed';
                        break;
                    case 'pending':
                    default:
                        $newStatus = 'pending';
                        break;
                }
                
                // Update payment status in database if changed
                if ($newStatus !== $payment['status']) {
                    $updateSql = "UPDATE profile_reveal_payments SET status = ?, paid_at = NOW() WHERE order_id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param("ss", $newStatus, $orderId);
                    $updateStmt->execute();
                    
                    // Update permission if payment is completed
                    if ($newStatus === 'completed') {
                        $this->grantProfilePermission($payment['user_id'], $payment['target_user_id']);
                    }
                    
                    $payment['status'] = $newStatus;
                }
            }
        }
        
        return $payment;
    }
    
    /**
     * Complete a payment (manual completion or callback from payment gateway)
     * 
     * @param string $orderId Order ID to complete
     * @return bool Success status
     */
    public function completePayment($orderId) {
        global $conn;
        
        // Update payment status
        $sql = "UPDATE profile_reveal_payments SET status = 'completed', paid_at = NOW() WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $orderId);
        $result = $stmt->execute();
        
        // If payment is successful, update user permissions for profile viewing
        if ($result) {
            $sql = "SELECT user_id, target_user_id FROM profile_reveal_payments WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $orderId);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            
            if ($payment) {
                $this->grantProfilePermission($payment['user_id'], $payment['target_user_id']);
            }
        }
        
        return $result;
    }
    
    /**
     * Grant permission to view profile
     * 
     * @param int $userId User requesting to view the profile
     * @param int $targetUserId Target user whose profile is being viewed
     * @return bool Success status
     */
    private function grantProfilePermission($userId, $targetUserId) {
        global $conn;
        
        // Grant permission to view the profile
        $sql = "INSERT INTO profile_view_permissions (user_id, target_user_id, created_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE created_at = NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $targetUserId);
        return $stmt->execute();
    }
    
    /**
     * Get Midtrans Client Key
     * 
     * @return string Client Key for frontend integration
     */
    public function getClientKey() {
        return $this->midtransClientKey;
    }
}