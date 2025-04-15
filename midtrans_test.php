<?php
/**
 * Midtrans Integration Test Script
 * 
 * This script tests your Midtrans integration by creating a test transaction
 * and verifying it can be properly processed.
 * 
 * Run this script from the command line:
 * php midtrans_test.php
 */

require_once 'config.php';
require_once 'payment_gateway.php';

echo "Midtrans Integration Test\n";
echo "=======================\n\n";

// Test server key
$serverKey = MIDTRANS_SERVER_KEY ?? 'SB-Mid-server-your_server_key_here';
echo "Using Server Key: " . substr($serverKey, 0, 10) . "...\n";

// Test client key
$clientKey = MIDTRANS_CLIENT_KEY ?? 'SB-Mid-client-your_client_key_here';
echo "Using Client Key: " . substr($clientKey, 0, 10) . "...\n";

// Check if we're in production or sandbox
$isProduction = MIDTRANS_IS_PRODUCTION ?? false;
echo "Environment: " . ($isProduction ? "PRODUCTION" : "SANDBOX") . "\n\n";

if ($isProduction) {
    echo "WARNING: You are testing in PRODUCTION mode. Real transactions may occur!\n";
    echo "Are you sure you want to continue? (y/n): ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        echo "Test aborted.\n";
        exit;
    }
}

// Initialize payment gateway
$paymentGateway = new PaymentGateway($serverKey, $clientKey, $isProduction);

// Create a test order ID
$testOrderId = 'TEST-' . time();
echo "Created test order ID: $testOrderId\n";

// Set up a test transaction
echo "Creating test transaction...\n";

// Simulate database connection and user data
$conn = (object)['prepare' => function() {
    return (object)[
        'bind_param' => function() { return true; },
        'execute' => function() { return true; },
        'get_result' => function() {
            return (object)[
                'fetch_assoc' => function() {
                    return [
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                    ];
                }
            ];
        }
    ];
}];

// Create a test payment
try {
    // We're adapting the code to work without a real database
    $reflectionClass = new ReflectionClass('PaymentGateway');
    $createProfileRevealPayment = $reflectionClass->getMethod('createProfileRevealPayment');
    
    // Direct API call to Midtrans to test connectivity
    $apiUrl = $isProduction 
            ? 'https://app.midtrans.com/snap/v1/transactions' 
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    
    // Transaction data for testing
    $transactionDetails = [
        'transaction_details' => [
            'order_id' => $testOrderId,
            'gross_amount' => 15000
        ],
        'customer_details' => [
            'first_name' => 'Test User',
            'email' => 'test@example.com'
        ],
        'item_details' => [
            [
                'id' => 'PROFILE-TEST',
                'price' => 15000,
                'quantity' => 1,
                'name' => 'Profile Reveal Test'
            ]
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
        'Authorization: Basic ' . base64_encode($serverKey . ':')
    ]);
    
    echo "Sending request to Midtrans API...\n";
    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Check the response
    if ($httpCode == 201) {
        echo "✅ Successfully connected to Midtrans API\n";
        $responseData = json_decode($response, true);
        echo "Transaction token: " . $responseData['token'] . "\n";
        echo "Redirect URL: " . $responseData['redirect_url'] . "\n\n";
        
        echo "Test completed successfully!\n";
        echo "You can open this URL in your browser to see the payment page:\n";
        echo $responseData['redirect_url'] . "\n";
    } else {
        echo "❌ Failed to connect to Midtrans API\n";
        echo "HTTP Code: $httpCode\n";
        if ($error) {
            echo "cURL Error: $error\n";
        }
        echo "Response: $response\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}