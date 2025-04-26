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
    <style>
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .dashboard-welcome {
            font-size: 28px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .dashboard-date {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
        }
        
        .stat-card.green::before {
            background: linear-gradient(45deg, #10b981, #34d399);
        }
        
        .stat-card.blue::before {
            background: linear-gradient(45deg, #3b82f6, #60a5fa);
        }
        
        .stat-card.purple::before {
            background: linear-gradient(45deg, #8b5cf6, #a78bfa);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            background: #fff0f3;
            color: #ff4b6e;
        }
        
        .stat-card.green .stat-icon {
            background: #ecfdf5;
            color: #10b981;
        }
        
        .stat-card.blue .stat-icon {
            background: #eff6ff;
            color: #3b82f6;
        }
        
        .stat-card.purple .stat-icon {
            background: #f5f3ff;
            color: #8b5cf6;
        }
        
        .stat-icon i {
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .online-users-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .online-users-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .online-users-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .online-users-header h2 i {
            color: #ff4b6e;
        }
        
        .online-users-body {
            padding: 24px;
        }
        
        .online-users-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .online-user-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            background: #f8f9fa;
            border-radius: 12px;
            gap: 10px;
        }
        
        .online-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .online-user-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }
        
        .online-user-name {
            font-weight: 500;
            color: #333;
        }
        
        .online-indicator {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            margin-left: 4px;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            70% {
                box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        .view-all-link {
            display: inline-flex;
            align-items: center;
            color: #ff4b6e;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            gap: 4px;
        }
        
        .view-all-link:hover {
            color: #e6435f;
        }
        
        .activity-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .activity-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-header h2 i {
            color: #ff4b6e;
        }
        
        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .activity-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .activity-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }
        
        .activity-table tr:last-child td {
            border-bottom: none;
        }
        
        .activity-table tr:hover {
            background: #fafafa;
        }
        
        .activity-user {
            font-weight: 500;
            color: #333;
        }
        
        .activity-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #f0f0f0;
            border-radius: 6px;
            font-size: 13px;
            color: #666;
        }
        
        .activity-time {
            color: #999;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .online-users-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .activity-table th,
            .activity-table td {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="dashboard-header">
                    <h1 class="dashboard-welcome">Welcome back, Admin!</h1>
                    <p class="dashboard-date">Today is <?php echo date('l, F j, Y'); ?></p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['active_users']); ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    
                    <div class="stat-card blue">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-value"><?php echo number_format($stats['new_users_today']); ?></div>
                        <div class="stat-label">New Users Today</div>
                    </div>
                </div>
                
                <!-- Online Users Widget -->
                <div class="online-users-card">
                    <div class="online-users-header">
                        <h2><i class="fas fa-circle"></i> Currently Online</h2>
                    </div>
                    <div class="online-users-body">
                        <?php
                        $onlineUsers = getOnlineUsers($conn);
                        $onlineCount = count($onlineUsers);
                        ?>
                        
                        <div class="stat-card purple" style="margin-bottom: 20px;">
                            <div class="stat-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-value"><?php echo $onlineCount; ?></div>
                            <div class="stat-label">Users Online</div>
                        </div>
                        
                        <?php if (empty($onlineUsers)): ?>
                            <p class="empty-message">No users currently online.</p>
                        <?php else: ?>
                            <div class="online-users-grid">
                                <?php foreach(array_slice($onlineUsers, 0, 5) as $user): ?>
                                <div class="online-user-item">
                                    <?php if (!empty($user['profile_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile" class="online-user-avatar">
                                    <?php else: ?>
                                    <div class="online-user-placeholder">
                                        <?php echo substr($user['name'], 0, 1); ?>
                                    </div>
                                    <?php endif; ?>
                                    <span class="online-user-name">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                        <span class="online-indicator"></span>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if ($onlineCount > 5): ?>
                                <a href="admin_online_users.php" class="view-all-link">
                                    +<?php echo $onlineCount - 5; ?> more
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <div style="text-align: right; margin-top: 16px;">
                                <a href="admin_online_users.php" class="btn btn-outline btn-sm">
                                    <i class="fas fa-users"></i> View All Online Users
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="activity-card">
                    <div class="activity-header">
                        <h2><i class="fas fa-history"></i> Recent Activity</h2>
                    </div>
                    <table class="activity-table">
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
                                <td class="activity-user"><?php echo htmlspecialchars($activity['user_name']); ?></td>
                                <td>
                                    <span class="activity-action">
                                        <?php if ($activity['action'] === 'Created Account'): ?>
                                            <i class="fas fa-user-plus"></i>
                                        <?php elseif ($activity['action'] === 'Sent Message'): ?>
                                            <i class="fas fa-envelope"></i>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($activity['action']); ?>
                                    </span>
                                </td>
                                <td class="activity-time">
                                    <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Auto-refresh dashboard data every 60 seconds
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update stats
                    document.querySelectorAll('.stat-value').forEach((el, index) => {
                        const newValue = doc.querySelectorAll('.stat-value')[index].textContent;
                        if (el.textContent !== newValue) {
                            el.textContent = newValue;
                            el.closest('.stat-card').classList.add('updated');
                            setTimeout(() => {
                                el.closest('.stat-card').classList.remove('updated');
                            }, 1000);
                        }
                    });
                    
                    // Update online users
                    const onlineUsersBody = document.querySelector('.online-users-body');
                    const newOnlineUsersBody = doc.querySelector('.online-users-body');
                    if (onlineUsersBody && newOnlineUsersBody) {
                        onlineUsersBody.innerHTML = newOnlineUsersBody.innerHTML;
                    }
                    
                    // Update activity table
                    const activityTable = document.querySelector('.activity-table tbody');
                    const newActivityTable = doc.querySelector('.activity-table tbody');
                    if (activityTable && newActivityTable) {
                        activityTable.innerHTML = newActivityTable.innerHTML;
                    }
                });
        }, 60000);
    </script>
</body>
</html>