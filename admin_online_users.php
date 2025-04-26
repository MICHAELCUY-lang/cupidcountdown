<?php
// admin_online_users.php
require_once 'config.php';
require_once 'admin_functions.php';

        // Set timezone to Jakarta (WIB/GMT+7)
        date_default_timezone_set('Asia/Jakarta');

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Get online users (active in the last 5 minutes)
$onlineUsers = getOnlineUsers($conn);

// Page title
$page_title = "Online Users";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Cupid Admin</title>
    <?php include 'admin_header_includes.php'; ?>
    <style>
        /* Modern Online Users Dashboard */
        .online-users-dashboard {
            padding: 30px 0;
        }
        
        .dashboard-hero {
            background: linear-gradient(135deg, #fff0f3 0%, #fff 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(255, 75, 110, 0.1);
        }
        
        .dashboard-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255, 75, 110, 0.1) 0%, transparent 70%);
            animation: pulse 15s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.3; }
            100% { transform: scale(1); opacity: 0.5; }
        }
        
        .dashboard-title {
            position: relative;
            z-index: 1;
        }
        
        .dashboard-title h1 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .dashboard-title h1 i {
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .dashboard-subtitle {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        
        .stat-card-content {
            position: relative;
            z-index: 2;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        .stat-card-online .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .stat-card-avg .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .stat-card-peak .stat-icon {
            background: rgba(255, 75, 110, 0.1);
            color: #ff4b6e;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .online-users-content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
        }
        
        .users-list-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .users-list-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .users-list-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .users-list-header h2 i {
            color: #10b981;
        }
        
        .refresh-button {
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .refresh-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 75, 110, 0.3);
        }
        
        .users-list-body {
            padding: 0;
        }
        
        .user-item {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .user-item:last-child {
            border-bottom: none;
        }
        
        .user-item:hover {
            background: #f8f9fa;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            overflow: hidden;
            margin-right: 15px;
            position: relative;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
        }
        
        .user-status {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            background: #10b981;
            border: 2px solid white;
            border-radius: 50%;
            animation: status-pulse 2s infinite;
        }
        
        @keyframes status-pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .user-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #666;
        }
        
        .user-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .user-action {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .view-profile-btn {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-profile-btn:hover {
            background: #ff4b6e;
            color: white;
            transform: translateY(-2px);
        }
        
        .activity-heatmap {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .heatmap-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .heatmap-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .heatmap-body {
            padding: 25px;
        }
        
        .time-chart {
            display: grid;
            grid-template-rows: repeat(24, 1fr);
            gap: 5px;
        }
        
        .time-slot {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-label {
            width: 50px;
            font-size: 12px;
            color: #666;
        }
        
        .time-bar {
            flex: 1;
            height: 20px;
            background: #f0f0f0;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        
        .time-fill {
            height: 100%;
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            border-radius: 4px;
            transition: width 1s ease;
        }
        
        .activity-legend {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }
        
        .legend-color-high {
            background: #ff4b6e;
        }
        
        .legend-color-medium {
            background: #ff6584;
        }
        
        .legend-color-low {
            background: #ffd9e0;
        }
        
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff4b6e;
            box-shadow: 0 0 0 4px rgba(255, 75, 110, 0.1);
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 14px;
            color: #666;
        }
        
        @media (max-width: 1024px) {
            .online-users-content {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-hero {
                padding: 30px 20px;
            }
            
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .users-list-header {
                flex-direction: column;
                gap: 15px;
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
                <div class="online-users-dashboard">
                    <!-- Dashboard Hero Section -->
                    <div class="dashboard-hero">
                        <div class="dashboard-title">
                            <h1><i class="fas fa-user-clock"></i> Online Users</h1>
                            <p class="dashboard-subtitle">Track and monitor currently active users in real-time</p>
                            <p class="text-muted" style="font-size: 13px;">Last updated: <?php echo date('H:i:s'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Stats Overview -->
                    <div class="stats-overview">
                        <div class="stat-card stat-card-online">
                            <div class="stat-card-content">
                                <div class="stat-icon">
                                    <i class="fas fa-circle"></i>
                                </div>
                                <div class="stat-number"><?php echo count($onlineUsers); ?></div>
                                <div class="stat-label">Currently Online</div>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-avg">
                            <div class="stat-card-content">
                                <div class="stat-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="stat-number">45</div>
                                <div class="stat-label">Average Online</div>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-card-peak">
                            <div class="stat-card-content">
                                <div class="stat-icon">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div class="stat-number">128</div>
                                <div class="stat-label">Peak Today</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="filter-label">Search</label>
                                <input type="text" class="form-control" placeholder="Search by name or email...">
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Auto-refresh interval</label>
                                <select id="refresh-interval" class="form-control">
                                    <option value="0">Manual refresh only</option>
                                    <option value="30">Every 30 seconds</option>
                                    <option value="60">Every minute</option>
                                    <option value="300">Every 5 minutes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Main Content Grid -->
                    <div class="online-users-content">
                        <!-- Users List -->
                        <div class="users-list-card">
                            <div class="users-list-header">
                                <h2><i class="fas fa-users"></i> Online Users</h2>
                                <button class="refresh-button" onclick="window.location.reload();">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                            <div class="users-list-body">
                                <?php if (empty($onlineUsers)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash"></i>
                                        <h3>No Users Online</h3>
                                        <p>Currently, there are no active users on the platform</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($onlineUsers as $user): ?>
                                        <div class="user-item">
                                            <div class="user-avatar">
                                                <?php if (!empty($user['profile_pic'])): ?>
                                                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>">
                                                <?php else: ?>
                                                    <div class="user-avatar-placeholder">
                                                        <?php echo substr($user['name'], 0, 1); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="user-status"></div>
                                            </div>
                                            <div class="user-info">
                                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="user-meta">
                                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></span>
                                                    <span><i class="fas fa-clock"></i> <?php 
                                                        $last_activity = strtotime($user['last_activity']);
                                                        $time_diff = time() - $last_activity;
                                                        
                                                        if ($time_diff < 60) {
                                                            echo $time_diff . ' seconds ago';
                                                        } else if ($time_diff < 3600) {
                                                            echo floor($time_diff / 60) . ' minutes ago';
                                                        } else {
                                                            echo floor($time_diff / 3600) . ' hours ago';
                                                        }
                                                    ?></span>
                                                </div>
                                            </div>
                                            <div class="user-action">
                                                <a href="admin_view_user.php?id=<?php echo $user['id']; ?>" class="view-profile-btn">
                                                    <i class="fas fa-user"></i> View Profile
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Activity Heatmap -->
                        <!--<div class="activity-heatmap">-->
                        <!--    <div class="heatmap-header">-->
                        <!--        <h2>Activity Heatmap</h2>-->
                        <!--    </div>-->
                        <!--    <div class="heatmap-body">-->
                        <!--        <div class="time-chart">-->
                        <!--            <?php for($i = 0; $i < 24; $i++): ?>-->
                        <!--                <div class="time-slot">-->
                        <!--                    <div class="time-label"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</div>-->
                        <!--                    <div class="time-bar">-->
                        <!--                        <div class="time-fill" style="width: <?php echo rand(10, 100); ?>%"></div>-->
                        <!--                    </div>-->
                        <!--                </div>-->
                        <!--            <?php endfor; ?>-->
                        <!--        </div>-->
                        <!--        <div class="activity-legend">-->
                        <!--            <div class="legend-item">-->
                        <!--                <div class="legend-color legend-color-high"></div>-->
                        <!--                <span>High Activity</span>-->
                        <!--            </div>-->
                        <!--            <div class="legend-item">-->
                        <!--                <div class="legend-color legend-color-medium"></div>-->
                        <!--                <span>Medium Activity</span>-->
                        <!--            </div>-->
                        <!--            <div class="legend-item">-->
                        <!--                <div class="legend-color legend-color-low"></div>-->
                        <!--                <span>Low Activity</span>-->
                        <!--            </div>-->
                        <!--        </div>-->
                        <!--    </div>-->
                        <!--</div>-->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Auto-refresh functionality
        let refreshInterval;
        let timeLeft = 0;
        
        function startRefreshTimer(seconds) {
            clearInterval(refreshInterval);
            
            if (seconds === 0) return;
            
            timeLeft = seconds;
            
            refreshInterval = setInterval(() => {
                timeLeft--;
                
                if (timeLeft <= 0) {
                    window.location.reload();
                }
            }, 1000);
        }
        
        document.getElementById('refresh-interval').addEventListener('change', function() {
            const interval = parseInt(this.value);
            startRefreshTimer(interval);
            
            // Save preference
            localStorage.setItem('refreshInterval', interval);
        });
        
        // Load saved preference
        document.addEventListener('DOMContentLoaded', function() {
            const savedInterval = localStorage.getItem('refreshInterval');
            if (savedInterval) {
                document.getElementById('refresh-interval').value = savedInterval;
                startRefreshTimer(parseInt(savedInterval));
            }
            
            // Add smooth animations for charts
            const timeFills = document.querySelectorAll('.time-fill');
            timeFills.forEach((fill, index) => {
                setTimeout(() => {
                    fill.style.transition = 'width 1s ease';
                }, index * 50);
            });
        });
        
        // Search functionality
        const searchInput = document.querySelector('.filter-section input[type="text"]');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const userItems = document.querySelectorAll('.user-item');
                
                userItems.forEach(item => {
                    const userName = item.querySelector('.user-name').textContent.toLowerCase();
                    const userEmail = item.querySelector('.user-meta span:first-child').textContent.toLowerCase();
                    
                    if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>