<?php
// admin_payments.php
// Admin page to manage and track payments

require_once 'config.php';

// Make sure user is logged in and is admin
requireLogin();

// Check if user is admin (you'll need to add an is_admin column to your users table)
$admin_sql = "SELECT is_admin FROM users WHERE id = ?";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $_SESSION['user_id']);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
$user = $admin_result->fetch_assoc();

if (!$user || $user['is_admin'] != 1) {
    redirect('dashboard.php');
    exit();
}

// Handle marking payment as complete (for manual verification)
if (isset($_POST['complete_payment']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    require_once 'payment_gateway.php';
    $paymentGateway = new PaymentGateway('your_api_key', 'https://payment.api/');
    $paymentGateway->completePayment($order_id);
    
    // Redirect to avoid form resubmission
    redirect('admin_payments.php?success=Payment completed');
}

// Handle refund
if (isset($_POST['refund_payment']) && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];
    
    // Update payment status
    $refund_sql = "UPDATE profile_reveal_payments SET status = 'refunded' WHERE order_id = ?";
    $refund_stmt = $conn->prepare($refund_sql);
    $refund_stmt->bind_param("s", $order_id);
    $refund_stmt->execute();
    
    // Remove permission
    $payment_sql = "SELECT user_id, target_user_id FROM profile_reveal_payments WHERE order_id = ?";
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("s", $order_id);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    $payment = $payment_result->fetch_assoc();
    
    if ($payment) {
        $delete_permission_sql = "DELETE FROM profile_view_permissions 
                                  WHERE user_id = ? AND target_user_id = ?";
        $delete_permission_stmt = $conn->prepare($delete_permission_sql);
        $delete_permission_stmt->bind_param("ii", $payment['user_id'], $payment['target_user_id']);
        $delete_permission_stmt->execute();
    }
    
    // Redirect to avoid form resubmission
    redirect('admin_payments.php?success=Payment refunded');
}

// Get all payments
$payments_sql = "SELECT p.*, 
                u1.name as user_name, 
                u2.name as target_user_name
                FROM profile_reveal_payments p
                JOIN users u1 ON p.user_id = u1.id
                JOIN users u2 ON p.target_user_id = u2.id
                ORDER BY p.created_at DESC";
$payments_result = $conn->query($payments_sql);
$payments = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}

// Calculate statistics
$total_payments = count($payments);
$completed_payments = 0;
$total_revenue = 0;

foreach ($payments as $payment) {
    if ($payment['status'] == 'completed') {
        $completed_payments++;
        $total_revenue += $payment['amount'];
    }
}

$conversion_rate = $total_payments > 0 ? round(($completed_payments / $total_payments) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Payments - Cupid</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
            max-width: 1200px;
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
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
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
        
        .btn-success {
            background-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .admin-container {
            padding-top: 100px;
            padding-bottom: 50px;
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 32px;
        }
        
        .card {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-refunded {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="cupid.php" class="logo">
                    <i class="fas fa-heart"></i> Cupid Admin
                </a>
                <nav>
                    <ul>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <li><a href="admin_users.php">Users</a></li>
                        <li><a href="admin_payments.php" class="active">Payments</a></li>
                        <li>
                            <a href="logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Admin Payments Section -->
    <section class="admin-container">
        <div class="container">
            <div class="page-header">
                <h1>Payment Management</h1>
            </div>
            
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_payments; ?></div>
                    <div class="stat-label">Total Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $completed_payments; ?></div>
                    <div class="stat-label">Completed Payments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $conversion_rate; ?>%</div>
                    <div class="stat-label">Conversion Rate</div>
                </div>
            </div>
            
            <div class="card">
                <h2 style="margin-bottom: 20px;">Payment History</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Target User</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No payments found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($payment['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($payment['target_user_name']); ?></td>
                            <td>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($payment['status'] === 'pending'): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to complete this payment?');">
                                        <input type="hidden" name="order_id" value="<?php echo $payment['order_id']; ?>">
                                        <button type="submit" name="complete_payment" class="btn btn-sm btn-success">Complete</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment['status'] === 'completed'): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to refund this payment? This will revoke profile view access.');">
                                        <input type="hidden" name="order_id" value="<?php echo $payment['order_id']; ?>">
                                        <button type="submit" name="refund_payment" class="btn btn-sm btn-danger">Refund</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <a href="view_payment.php?id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</body>
</html>