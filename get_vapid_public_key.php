<?php
// get_vapid_public_key.php
// Provides the VAPID public key for push notifications

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

// Load environment variables
require_once 'env.php';

// Get VAPID public key from environment variables
$publicKey = getenv('VAPID_PUBLIC_KEY');

// If key is not set in environment variables, use a default one
// NOTE: In production, you should generate your own VAPID keys and store them securely
if (!$publicKey) {
    // This is a placeholder key - DO NOT USE THIS IN PRODUCTION
    // Generate your own key pair using the Web Push library
    $publicKey = 'BHSBBg_NSIE_OuUnYNKY2ga2KxxYWWZhYPGHRFo0ixExiJIZ39SN8-zdNfzA6r8mmCwYvpbR_P2gt1eViJepxbo';
}

// Return the public key
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'publicKey' => $publicKey
]);