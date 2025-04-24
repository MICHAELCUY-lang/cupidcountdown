<?php
// File: dismiss_promotion.php
// Script untuk mencatat ketika user menutup popup promosi

// Sertakan file konfigurasi
require_once 'config.php';

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Pastikan user sudah login
requireLogin();
$user_id = $_SESSION['user_id'];

// Dapatkan promo_id dari request
$promo_id = isset($_POST['promo_id']) ? (int)$_POST['promo_id'] : 0;

if ($promo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid promotion ID']);
    exit();
}

// Catat dismissal di database
$dismiss_sql = "INSERT INTO promotion_dismissals (user_id, promotion_id, dismissed_at) VALUES (?, ?, NOW())";
$dismiss_stmt = $conn->prepare($dismiss_sql);
$dismiss_stmt->bind_param("ii", $user_id, $promo_id);

if ($dismiss_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record dismissal']);
}
?>

<?php
// File: record_promotion_click.php
// Script untuk mencatat klik pada promosi

// Sertakan file konfigurasi
require_once 'config.php';

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Dapatkan promo_id dari request
$promo_id = isset($_POST['promo_id']) ? (int)$_POST['promo_id'] : 0;

if ($promo_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid promotion ID']);
    exit();
}

// Update clicks di database
$update_sql = "UPDATE promotions SET clicks = clicks + 1 WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $promo_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to record click']);
}
?>

<?php
// File: update_promotion_status.php
// Script untuk mengupdate status promosi setelah pembayaran berhasil

// Sertakan file konfigurasi
require_once 'config.php';

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Pastikan user sudah login
requireLogin();
$user_id = $_SESSION['user_id'];

// Dapatkan order_id dan transaction_id dari request
$order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '';
$transaction_id = isset($_POST['transaction_id']) ? $_POST['transaction_id'] : '';

if (empty($order_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

// Dapatkan data promosi
$promo_sql = "SELECT * FROM promotions WHERE order_id = ? AND user_id = ?";
$promo_stmt = $conn->prepare($promo_sql);
$promo_stmt->bind_param("si", $order_id, $user_id);
$promo_stmt->execute();
$promo_result = $promo_stmt->get_result();

if ($promo_result->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Promotion not found']);
    exit();
}

$promo = $promo_result->fetch_assoc();

// Update status promosi menjadi active
$activate_sql = "UPDATE promotions SET 
                status = 'active', 
                transaction_id = ?, 
                activated_at = NOW(), 
                expiry_date = DATE_ADD(NOW(), INTERVAL ? DAY) 
                WHERE id = ?";
$activate_stmt = $conn->prepare($activate_sql);
$activate_stmt->bind_param("sii", $transaction_id, $promo['duration_days'], $promo['id']);

if ($activate_stmt->execute()) {
    // Catat transaksi
    $transaction_sql = "INSERT INTO promotion_transactions 
                        (promotion_id, user_id, amount, transaction_id, order_id, status, payment_method, transaction_time) 
                        VALUES (?, ?, ?, ?, ?, 'success', 'midtrans', NOW())";
    $transaction_stmt = $conn->prepare($transaction_sql);
    $transaction_stmt->bind_param("iidss", $promo['id'], $user_id, $promo['price'], $transaction_id, $order_id);
    $transaction_stmt->execute();
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to activate promotion']);
}
?>

<?php
// File: payment_status.php
// Halaman untuk menampilkan status pembayaran

// Sertakan file konfigurasi
require_once 'config.php';

// Pastikan user sudah login
requireLogin();
$user_id = $_SESSION['user_id'];

// Dapatkan order_id dari URL
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';

if (empty($order_id)) {
    header("Location: dashboard?page=promotion");
    exit();
}

// Dapatkan data promosi
$promo_sql = "SELECT p.*, t.status as payment_status, t.payment_method, t.transaction_time 
              FROM promotions p 
              LEFT JOIN promotion_transactions t ON p.id = t.promotion_id 
              WHERE p.order_id = ? AND p.user_id = ?";
$promo_stmt = $conn->prepare($promo_sql);
$promo_stmt->bind_param("si", $order_id, $user_id);
$promo_stmt->execute();
$promo_result = $promo_stmt->get_result();

if ($promo_result->num_rows == 0) {
    header("Location: dashboard?page=promotion");
    exit();
}

$promo = $promo_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupid - Status Pembayaran</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .payment-status-container {
            max-width: 600px;
            margin: 100px auto;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }
        
        .payment-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .payment-header h2 {
            margin: 0 0 5px;
            font-size: 24px;
            color: var(--text-color);
        }
        
        .payment-header p {
            margin: 0;
            color: #666;
        }
        
        .payment-body {
            padding: 30px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .payment-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .payment-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .payment-detail-item:last-child {
            margin-bottom: 0;
        }
        
        .payment-detail-label {
            color: #666;
        }
        
        .payment-detail-value {
            font-weight: 500;
            color: var(--text-color);
        }
        
        .payment-actions {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="payment-status-container">
        <div class="payment-header">
            <h2>Status Pembayaran</h2>
            <p>Order ID: <?php echo $order_id; ?></p>
        </div>
        
        <div class="payment-body">
            <?php
            // Show different status based on promotion status
            if ($promo['status'] === 'active') {
                echo '<div class="text-center">';
                echo '<div class="status-badge status-success">Pembayaran Berhasil</div>';
                echo '<p>Promosi Anda sudah aktif dan akan ditampilkan kepada pengguna Cupid.</p>';
                echo '</div>';
            } elseif ($promo['status'] === 'pending') {
                // If payment is still pending
                echo '<div class="text-center">';
                echo '<div class="status-badge status-pending">Menunggu Pembayaran</div>';
                echo '<p>Kami belum menerima konfirmasi pembayaran Anda. Silakan selesaikan pembayaran untuk mengaktifkan promosi.</p>';
                echo '</div>';
            } else {
                // If payment failed or canceled
                echo '<div class="text-center">';
                echo '<div class="status-badge status-error">Pembayaran Gagal</div>';
                echo '<p>Terjadi masalah dengan pembayaran Anda. Silakan coba lagi.</p>';
                echo '</div>';
            }
            ?>
            
            <div class="payment-details">
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Judul Promosi</span>
                    <span class="payment-detail-value"><?php echo htmlspecialchars($promo['title']); ?></span>
                </div>
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Tanggal Order</span>
                    <span class="payment-detail-value"><?php echo date('d M Y H:i', strtotime($promo['created_at'])); ?></span>
                </div>
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Durasi</span>
                    <span class="payment-detail-value"><?php echo $promo['duration_days']; ?> hari</span>
                </div>
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Total</span>
                    <span class="payment-detail-value">Rp <?php echo number_format($promo['price']); ?></span>
                </div>
                <?php if ($promo['status'] === 'active' && isset($promo['payment_method'])): ?>
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Metode Pembayaran</span>
                    <span class="payment-detail-value"><?php echo $promo['payment_method']; ?></span>
                </div>
                <div class="payment-detail-item">
                    <span class="payment-detail-label">Waktu Pembayaran</span>
                    <span class="payment-detail-value"><?php echo date('d M Y H:i', strtotime($promo['transaction_time'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="payment-actions">
                <?php if ($promo['status'] === 'pending'): ?>
                <a href="payment?order_id=<?php echo $order_id; ?>" class="btn">
                    <i class="fas fa-credit-card"></i> Lanjutkan Pembayaran
                </a>
                <?php elseif ($promo['status'] === 'active'): ?>
                <a href="dashboard?page=promotion" class="btn">
                    <i class="fas fa-check-circle"></i> Lihat Promosi Saya
                </a>
                <?php else: ?>
                <a href="dashboard?page=promotion" class="btn">
                    <i class="fas fa-redo"></i> Coba Lagi
                </a>
                <?php endif; ?>
                <p style="margin-top: 15px;">
                    <a href="dashboard" class="text-primary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                    </a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>