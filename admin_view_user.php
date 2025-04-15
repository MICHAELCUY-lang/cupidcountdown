<?php
// admin_view_user.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Check if user ID is provided
if (!isset($_GET['id'])) {
    redirect('admin_users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Get user details
$sql = "SELECT u.*, p.* 
        FROM users u 
        LEFT JOIN profiles p ON u.id = p.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('admin_users.php?error=User not found');
    exit();
}

$user = $result->fetch_assoc();

// Get user's activity
$activity_sql = "SELECT 'chat' as type, created_at, message as content, 
                (SELECT name FROM users WHERE id = chat_messages.sender_id) as sender_name
                FROM chat_messages 
                WHERE sender_id = ? 
                UNION ALL
                SELECT 'menfess' as type, created_at, message as content,
                (SELECT name FROM users WHERE id = menfess.receiver_id) as receiver_name
                FROM menfess 
                WHERE sender_id = ?
                ORDER BY created_at DESC LIMIT 20";
$activity_stmt = $conn->prepare($activity_sql);
$activity_stmt->bind_param("ii", $user_id, $user_id);
$activity_stmt->execute();
$activity_result = $activity_stmt->get_result();

$activities = [];
while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}

// Get user's payments
$payments_sql = "SELECT * FROM profile_reveal_payments WHERE user_id = ? ORDER BY created_at DESC";
$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param("i", $user_id);
$payments_stmt->execute();
$payments_result = $payments_stmt->get_result();

$payments = [];
while ($row = $payments_result->fetch_assoc()) {
    $payments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Cupid Admin</title>
    <?php include 'admin_header_includes.php'; ?>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>User Details</h1>
                    <a href="admin_users.php" class="btn btn-outline">Back to Users</a>
                </div>
                
                <!-- User Profile Card -->
                <div class="card">
                    <div class="profile-header">
                        <div class="profile-image">
                            <?php if (!empty($user['profile_pic'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile">
                            <?php else: ?>
                            <div class="profile-image-placeholder">
                                <?php echo substr($user['name'], 0, 1); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                            <p class="user-meta"><?php echo htmlspecialchars($user['email']); ?></p>
                            
                            <div class="user-badges">
                                <?php if ($user['is_admin']): ?>
                                <span class="badge badge-primary">Admin</span>
                                <?php endif; ?>
                                
                                <?php if ($user['email_verified']): ?>
                                <span class="badge badge-success">Verified</span>
                                <?php else: ?>
                                <span class="badge badge-danger">Unverified</span>
                                <?php endif; ?>
                                
                                <?php if ($user['is_blocked'] ?? false): ?>
                                <span class="badge badge-danger">Blocked</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-actions">
                            <form method="post" action="admin_users.php">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="is_blocked" value="<?php echo $user['is_blocked'] ?? 0; ?>">
                                <button type="submit" name="block_user" class="btn btn-sm <?php echo ($user['is_blocked'] ?? 0) ? 'btn-success' : 'btn-danger'; ?>">
                                    <?php echo ($user['is_blocked'] ?? 0) ? 'Unblock User' : 'Block User'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="user-details">
                        <div class="detail-section">
                            <h3>Basic Information</h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">User ID:</span>
                                    <span class="detail-value"><?php echo $user['id']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Joined:</span>
                                    <span class="detail-value"><?php echo date('d M Y H:i', strtotime($user['created_at'])); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Major:</span>
                                    <span class="detail-value"><?php echo !empty($user['major']) ? htmlspecialchars($user['major']) : 'Not specified'; ?></span>
                                </div>
                                <div class="detail-item"><span class="detail-label">Looking For:</span>
                                    <span class="detail-value">
                                        <?php 
                                        $looking_for = $user['looking_for'] ?? '';
                                        if ($looking_for === 'friends') echo 'Friends';
                                        elseif ($looking_for === 'study_partner') echo 'Study Partner';
                                        elseif ($looking_for === 'romance') echo 'Romance';
                                        else echo 'Not specified';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($user['bio'])): ?>
                        <div class="detail-section">
                            <h3>Bio</h3>
                            <div class="user-bio">
                                <?php echo nl2br(htmlspecialchars($user['bio'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['interests'])): ?>
                        <div class="detail-section">
                            <h3>Interests</h3>
                            <div class="interests-tags">
                                <?php 
                                $interests = explode(',', $user['interests']);
                                foreach ($interests as $interest): 
                                ?>
                                <span class="interest-tag"><?php echo htmlspecialchars(trim($interest)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tabs for Activities, Payments, etc. -->
                <div class="tabs">
                    <div class="tab active" data-tab="activity">Activity</div>
                    <div class="tab" data-tab="payments">Payments</div>
                </div>
                
                <!-- Activity Tab Content -->
                <div class="tab-content active" id="activity-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Recent Activity</h2>
                        </div>
                        
                        <?php if (empty($activities)): ?>
                        <p class="empty-state">This user has no recorded activity yet.</p>
                        <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Content</th>
                                    <th>With</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['type'] === 'chat'): ?>
                                        <span class="badge badge-info">Chat</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">Menfess</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo substr(htmlspecialchars($activity['content']), 0, 100) . (strlen($activity['content']) > 100 ? '...' : ''); ?></td>
                                    <td>
                                        <?php 
                                        if ($activity['type'] === 'chat') {
                                            echo htmlspecialchars($activity['sender_name']);
                                        } else {
                                            echo htmlspecialchars($activity['receiver_name']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Payments Tab Content -->
                <div class="tab-content" id="payments-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Payment History</h2>
                        </div>
                        
                        <?php if (empty($payments)): ?>
                        <p class="empty-state">This user has no payment history yet.</p>
                        <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Target User</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): 
                                    // Get target user name
                                    $target_sql = "SELECT name FROM users WHERE id = ?";
                                    $target_stmt = $conn->prepare($target_sql);
                                    $target_stmt->bind_param("i", $payment['target_user_id']);
                                    $target_stmt->execute();
                                    $target_result = $target_stmt->get_result();
                                    $target_name = $target_result->fetch_assoc()['name'] ?? 'Unknown';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($target_name); ?></td>
                                    <td>Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $payment['status']; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($payment['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                const targetId = this.getAttribute('data-tab') + '-tab';
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(targetId).classList.add('active');
            });
        });
    </script>
</body>
</html>