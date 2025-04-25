<?php
// File: payment.php
// Halaman untuk memproses pembayaran melalui Midtrans

// Sertakan file konfigurasi
require_once 'config.php';

// Pastikan user sudah login
requireLogin();

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Cek jika ada order_id
if (!isset($_GET['order_id']) && !isset($_SESSION['pending_order'])) {
    // Redirect ke halaman promosi jika tidak ada order_id
    header("Location: dashboard?page=promotion");
    exit();
}

// Dapatkan order details
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Get promotion details from database
    $promo_sql = "SELECT * FROM promotions WHERE order_id = ?";
    $promo_stmt = $conn->prepare($promo_sql);
    $promo_stmt->bind_param("s", $order_id);
    $promo_stmt->execute();
    $promo_result = $promo_stmt->get_result();
    
    if ($promo_result->num_rows == 0) {
        // Order tidak ditemukan
        header("Location: dashboard?page=promotion");
        exit();
    }
    
    $promo = $promo_result->fetch_assoc();
    
    $order_details = [
        'order_id' => $order_id,
        'promo_id' => $promo['id'],
        'amount' => $promo['price'],
        'title' => $promo['title']
    ];
} else {
    $order_details = $_SESSION['pending_order'];
    $order_id = $order_details['order_id'];
}

// Set Midtrans API configurations
// Ganti dengan konfigurasi Midtrans Anda
$midtrans_client_key = 'Mid-client-ptNBYAj_Htl9A_Bt';
$midtrans_server_key = 'Mid-server-Ql9gzKnXv7MEPJvFQCz1NiY_';
$is_production = false;
$api_url = $is_production ? 
    'https://app.midtrans.com/snap/v1/transactions' : 
    'https://app.sandbox.midtrans.com/snap/v1/transactions';

// Prepare transaction data for Midtrans
$transaction_details = [
    'order_id' => $order_id,
    'gross_amount' => $order_details['amount']  
];

$customer_details = [
    'first_name' => $user['name'],
    'email' => $user['email']
];

$item_details = [
    [
        'id' => 'PROMO-'.$order_details['promo_id'],
        'price' => $order_details['amount'],
        'quantity' => 1,
        'name' => 'Promosi: ' . $order_details['title']
    ]
];

$transaction_data = [
    'transaction_details' => $transaction_details,
    'customer_details' => $customer_details,
    'item_details' => $item_details
];

// Function to get Midtrans snap token
function getSnapToken($url, $server_key, $transaction_data) {
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($transaction_data),
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Basic " . base64_encode($server_key . ":")
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        return ['error' => $err];
    } else {
        return json_decode($response, true);
    }
}

// Get snap token from Midtrans
$snap_response = getSnapToken($api_url, $midtrans_server_key, $transaction_data);

if (isset($snap_response['error'])) {
    $payment_error = "Terjadi kesalahan: " . $snap_response['error'];
    $snap_token = '';
} else if (isset($snap_response['error_messages'])) {
    $payment_error = "Terjadi kesalahan: " . implode(", ", $snap_response['error_messages']);
    $snap_token = '';
} else {
    $snap_token = $snap_response['token'];
    $payment_error = '';
}

// Clear pending order from session after getting snap token
if (isset($_SESSION['pending_order'])) {
    unset($_SESSION['pending_order']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupid - Pembayaran Promosi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 100px auto;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .payment-header {
            background-color: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .payment-header h2 {
            margin: 0 0 5px;
            font-size: 24px;
        }
        
        .payment-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .payment-content {
            padding: 30px;
        }
        
        .payment-summary {
            background-color: var(--secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .payment-item.total {
            font-size: 20px;
            font-weight: 600;
            border-top: 2px solid rgba(0, 0, 0, 0.1);
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-actions {
            text-align: center;
        }
        
        .btn-pay {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-weight: 600;
        }
        
        .btn-pay:hover {
            background-color: #e63e5c;
            transform: translateY(-2px);
        }
        
        .payment-footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        
        .payment-back {
            display: inline-block;
            margin-top: 15px;
            color: var(--primary);
            text-decoration: none;
        }
        
        .payment-back:hover {
            text-decoration: underline;
        }
        
        .payment-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .payment-processing {
            text-align: center;
            padding: 20px;
            display: none;
        }
        
        .payment-processing img {
            width: 80px;
            margin-bottom: 15px;
        }
        
        .payment-success {
            text-align: center;
            padding: 30px;
            display: none;
        }
        
        .payment-success i {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .payment-success h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }
    </style>
    <!-- Midtrans Snap JS -->
    <script src="https://app.<?php echo $is_production ? '' : 'sandbox.'; ?>midtrans.com/snap/snap.js" data-client-key="<?php echo $midtrans_client_key; ?>"></script>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h2>Pembayaran Promosi</h2>
            <p>Order ID: <?php echo $order_id; ?></p>
        </div>
        
        <div class="payment-content">
            <?php if (!empty($payment_error)): ?>
            <div class="payment-error">
                <?php echo $payment_error; ?>
            </div>
            <?php endif; ?>
            
            <div class="payment-summary">
                <div class="payment-item">
                    <span>Judul Promosi</span>
                    <span><?php echo $order_details['title']; ?></span>
                </div>
                <div class="payment-item">
                    <span>Durasi</span>
                    <?php 
                    if (isset($promo)) {
                        echo '<span>' . $promo['duration_days'] . ' hari</span>';
                    } else {
                        // If promo details not available, just show generic text
                        echo '<span>Sesuai paket</span>';
                    }
                    ?>
                </div>
                <div class="payment-item total">
                    <span>Total Pembayaran</span>
                    <span>Rp <?php echo number_format($order_details['amount']); ?></span>
                </div>
            </div>
            
            <?php if (empty($payment_error)): ?>
            <div class="payment-actions">
                <button id="pay-button" class="btn-pay">
                    <i class="fas fa-credit-card"></i> Bayar Sekarang
                </button>
            </div>
            <?php endif; ?>
            
            <div class="payment-footer">
                <p>Pembayaran diproses dengan aman oleh Midtrans</p>
                <a href="dashboard?page=promotion" class="payment-back">
                    <i class="fas fa-arrow-left"></i> Kembali ke halaman promosi
                </a>
            </div>
            
            <div id="payment-processing" class="payment-processing">
                <img src="assets/images/loading.gif" alt="Loading">
                <p>Memproses pembayaran Anda, mohon tunggu...</p>
            </div>
            
            <div id="payment-success" class="payment-success">
                <i class="fas fa-check-circle"></i>
                <h3>Pembayaran Berhasil!</h3>
                <p>Promosi Anda akan segera aktif.</p>
                <a href="dashboard?page=promotion" class="btn">
                    Lihat Promosi Saya
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const payButton = document.getElementById('pay-button');
            const processingDiv = document.getElementById('payment-processing');
            const successDiv = document.getElementById('payment-success');
            const paymentContent = document.querySelector('.payment-summary');
            const paymentActions = document.querySelector('.payment-actions');
            
            if (payButton) {
                payButton.addEventListener('click', function() {
                    // Show processing
                    processingDiv.style.display = 'block';
                    paymentActions.style.display = 'none';
                    
                    // Call Midtrans Snap
                    snap.pay('<?php echo $snap_token; ?>', {
                        onSuccess: function(result) {
                            handlePaymentSuccess(result);
                        },
                        onPending: function(result) {
                            redirectToOrderStatus(result);
                        },
                        onError: function(result) {
                            redirectToOrderStatus(result);
                        },
                        onClose: function() {
                            // Hide processing when user closes the Midtrans popup
                            processingDiv.style.display = 'none';
                            paymentActions.style.display = 'block';
                        }
                    });
                });
            }
            
            function handlePaymentSuccess(result) {
                // Show success message
                processingDiv.style.display = 'none';
                paymentContent.style.display = 'none';
                paymentActions.style.display = 'none';
                successDiv.style.display = 'block';
                
                // Update promotion status in database
                fetch('update_promotion_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'order_id=<?php echo $order_id; ?>&transaction_id=' + result.transaction_id
                });
            }
            
            function redirectToOrderStatus(result) {
                // Redirect to status page
                window.location.href = 'payment_status.php?order_id=<?php echo $order_id; ?>';
            }
        });
    </script>
</body>
</html>