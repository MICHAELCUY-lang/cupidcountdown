<?php
// payment_process.php
// Process payment for profile reveal using Midtrans

require_once 'config.php';
require_once 'payment_gateway.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Make sure user is logged in
requireLogin();

// Check if order ID is provided
if (!isset($_GET['order_id'])) {
    redirect('dashboard.php?page=chat');
    exit();
}

$orderId = $_GET['order_id'];
$userId = $_SESSION['user_id'];

// Initialize payment gateway
$paymentGateway = new PaymentGateway();

// Check payment status
$payment = $paymentGateway->checkPaymentStatus($orderId);

// Debug output (remove in production)
// echo "<pre>Debug Payment Data: ";
// print_r($payment);
// echo "</pre>";

// Make sure the payment exists and belongs to the current user
if ($payment['status'] === 'not_found' || $payment['user_id'] != $userId) {
    redirect('dashboard.php?page=chat&error=invalid_payment');
    exit();
}

// Get payment details
$targetUserId = $payment['target_user_id'];

// Get target user information
$sql = "SELECT u.name, p.profile_pic, p.bio FROM users u 
        LEFT JOIN profiles p ON u.id = p.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $targetUserId);
$stmt->execute();
$targetUser = $stmt->get_result()->fetch_assoc();

// If payment is already completed, redirect to view profile
if ($payment['status'] === 'completed') {
    redirect('view_profile.php?id=' . $targetUserId . '&from_payment=1');
    exit();
}

// Get client key for Midtrans frontend integration
$clientKey = $paymentGateway->getClientKey();

// Determine if we're in production mode
$isProduction = defined('MIDTRANS_IS_PRODUCTION') ? MIDTRANS_IS_PRODUCTION : false;

// Set the correct Snap.js URL based on environment
$snapJsUrl = $isProduction 
    ? "https://app.midtrans.com/snap/snap.js" 
    : "https://app.sandbox.midtrans.com/snap/snap.js";

// Check if we need to create a new token
$needNewToken = !isset($payment['token']) || empty($payment['token']);
if ($needNewToken) {
    // Recreate payment to get a fresh token
    $amount = $payment['amount'] ?? 15000; // Use existing amount or default to 15000
    $newPayment = $paymentGateway->createProfileRevealPayment($userId, $targetUserId, $amount);
    
    if (isset($newPayment['token']) && !empty($newPayment['token'])) {
        $payment['token'] = $newPayment['token'];
        // Debug output (remove in production)
        // echo "<p>Created new token: " . $payment['token'] . "</p>";
    } else {
        // Log error if we couldn't get a token
        error_log("Failed to create new payment token for order ID: " . $orderId);
        // Debug output (remove in production)
        // echo "<p>Failed to create new token</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Lihat Profil - Cupid</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script type="text/javascript" src="<?php echo $snapJsUrl; ?>" data-client-key="<?php echo $clientKey; ?>"></script>
    <style>
        :root {
            --primary: #ff4b6e;
            --secondary: #ffd9e0;
            --dark: #333333;
            --light: #ffffff;
            --accent: #ff8fa3;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--dark);
        }
        
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background-color: var(--light);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #e63e5c;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--light);
        }
        
        .payment-container {
            padding-top: 100px;
            padding-bottom: 50px;
        }
        
        .card {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-header {
            margin-bottom: 20px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .payment-details {
            margin-bottom: 30px;
        }
        
        .payment-details .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-details .label {
            font-weight: 500;
            color: #666;
        }
        
        .payment-details .value {
            font-weight: 600;
        }
        
        .user-preview {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .user-info p {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        
        .price-highlight {
            color: var(--primary);
            font-size: 24px;
            font-weight: bold;
        }
        
        .benefits {
            margin-bottom: 30px;
        }
        
        .benefits ul {
            list-style: none;
        }
        
        .benefits li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .benefits li i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .disclaimer {
            font-size: 13px;
            color: #777;
            text-align: center;
            margin-top: 30px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="cupid.php" class="logo">
                    <i class="fas fa-heart"></i> Cupid
                </a>
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li>
                            <a href="dashboard.php?page=chat" class="btn btn-outline">Kembali ke Chat</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Payment Section -->
    <section class="payment-container">
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1>Lihat Profil Lengkap</h1>
                </div>
                
                <?php if (isset($_GET['error'])): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>

                <?php if (!isset($payment['token']) || empty($payment['token'])): ?>
                <div class="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    Terjadi masalah dengan token pembayaran. Silakan coba lagi nanti atau hubungi support.
                </div>
                <?php endif; ?>
                
                <div class="user-preview">
                    <div class="user-avatar">
                        <img src="<?php echo !empty($targetUser['profile_pic']) ? htmlspecialchars($targetUser['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="User Avatar">
                    </div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($targetUser['name']); ?></h3>
                        <p>Lihat profil lengkap untuk mengetahui info lebih detail</p>
                    </div>
                </div>
                
                <div class="benefits">
                    <h3>Dengan melihat profil, Anda akan mendapatkan:</h3>
                    <ul>
                        <li><i class="fas fa-check-circle"></i> Info lengkap tentang minat dan hobi</li>
                        <li><i class="fas fa-check-circle"></i> Jurusan dan fakultas</li>
                        <li><i class="fas fa-check-circle"></i> Bio lengkap</li>
                        <li><i class="fas fa-check-circle"></i> Foto profil yang jelas</li>
                        <li><i class="fas fa-check-circle"></i> Informasi kecocokan</li>
                    </ul>
                </div>
                
                <div class="payment-details">
                    <div class="row">
                        <div class="label">Order ID:</div>
                        <div class="value"><?php echo htmlspecialchars($orderId); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Lihat Profil:</div>
                        <div class="value"><?php echo htmlspecialchars($targetUser['name']); ?></div>
                    </div>
                    <div class="row">
                        <div class="label">Harga:</div>
                        <div class="value price-highlight">Rp <?php echo number_format($payment['amount'] ?? 15000, 0, ',', '.'); ?></div>
                    </div>
                </div>
                
                <button id="pay-button" class="btn" style="width: 100%;">Bayar Sekarang</button>
                
                <p class="disclaimer">
                    Dengan menekan tombol "Bayar Sekarang", Anda setuju dengan syarat dan ketentuan Cupid mengenai pembayaran dan penggunaan fitur premium.
                </p>
            </div>
        </div>
    </section>

    <script type="text/javascript">
        // For Midtrans Snap integration
        document.getElementById('pay-button').addEventListener('click', function() {
            console.log('Pay button clicked');
            // Check token
            const token = '<?php echo $payment["token"] ?? ""; ?>';
            if (!token) {
                console.error('No payment token available');
                alert('Token pembayaran tidak tersedia. Silakan refresh halaman atau coba lagi nanti.');
                return;
            }
            
            console.log('Using token:', token);
            
            // Call SNAP API with transaction token
            try {
                snap.pay(token, {
                    onSuccess: function(result) {
                        console.log('Payment success:', result);
                        window.location.href = 'payment_callback.php?status=finish&order_id=<?php echo $orderId; ?>&transaction_status=settlement';
                    },
                    onPending: function(result) {
                        console.log('Payment pending:', result);
                        window.location.href = 'payment_callback.php?status=pending&order_id=<?php echo $orderId; ?>&transaction_status=pending';
                    },
                    onError: function(result) {
                        console.error('Payment error:', result);
                        window.location.href = 'payment_callback.php?status=error&order_id=<?php echo $orderId; ?>&transaction_status=deny';
                    },
                    onClose: function() {
                        console.log('Customer closed the popup without finishing payment');
                        alert('Pembayaran belum selesai. Silakan selesaikan pembayaran Anda.');
                    }
                });
            } catch (error) {
                console.error('Error calling snap.pay:', error);
                alert('Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.');
            }
        });
    </script>
</body>
</html>