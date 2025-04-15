<?php
// admin_dashboard.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Get stats
$stats = getAdminDashboardStats($conn);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Cupid</title>
    <?php include 'admin_header_includes.php'; ?>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>Admin Dashboard</h1>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['new_users_today']; ?></div>
                        <div class="stat-label">New Users Today</div>
                    </div>
                    
                    <!-- Online Users Widget for Dashboard -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-user-clock"></i> Currently Online</h2>
    </div>
    <?php
    $onlineUsers = getOnlineUsers($conn);
    $onlineCount = count($onlineUsers);
    ?>
    <div class="online-users-summary">
        <div class="stat-card">
            <div class="stat-value"><?php echo $onlineCount; ?></div>
            <div class="stat-label">Users Online</div>
        </div>
        
        <div class="online-users-list" style="margin-top: 15px;">
            <?php if (empty($onlineUsers)): ?>
                <p>No users currently online.</p>
            <?php else: ?>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach(array_slice($onlineUsers, 0, 5) as $user): ?>
                    <div style="display: flex; align-items: center; background: #f5f5f5; padding: 8px; border-radius: 5px;">
                        <?php if (!empty($user['profile_pic'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile" 
                             style="width: 30px; height: 30px; border-radius: 50%; margin-right: 8px;">
                        <?php else: ?>
                        <div style="width: 30px; height: 30px; border-radius: 50%; background-color: #ff4b6e; color: white; 
                                   display: flex; align-items: center; justify-content: center; margin-right: 8px;">
                            <?php echo substr($user['name'], 0, 1); ?>
                        </div>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($user['name']); ?>
                        <span class="online-indicator" style="display: inline-block; width: 8px; height: 8px; background-color: #28a745; border-radius: 50%; margin-left: 5px;"></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($onlineCount > 5): ?>
                    <a href="admin_online_users.php" style="display: flex; align-items: center; color: #ff4b6e; font-weight: 500;">
                        +<?php echo $onlineCount - 5; ?> more
                    </a>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 10px; text-align: right;">
                    <a href="admin_online_users.php" class="btn btn-sm">View All</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

                    <div class="stat-card">
                        <div class="stat-value"><?php echo 'Rp ' . number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stats['recent_activity'] as $activity): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
</body>
</html>