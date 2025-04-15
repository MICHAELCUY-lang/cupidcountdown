<?php
// admin_online_users.php
require_once 'config.php';
require_once 'admin_functions.php';

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
        .online-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #28a745;
            border-radius: 50%;
            margin-left: 5px;
        }
        
        .time-ago {
            font-size: 12px;
            color: #666;
        }
        
        .refresh-button {
            float: right;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1><i class="fas fa-user-clock"></i> Online Users</h1>
                    <button class="btn refresh-button" onclick="window.location.reload();">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Users Active in Last 5 Minutes</h2>
                        <p class="text-muted">Last updated: <?php echo date('H:i:s'); ?></p>
                    </div>
                    <div class="online-users-list">
                        <?php if (empty($onlineUsers)): ?>
                            <p class="empty-state">No users currently online.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Last Activity</th>
                                        <th>Time Active</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($onlineUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-name-cell">
                                                <?php if (!empty($user['profile_pic'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile" class="user-avatar">
                                                <?php else: ?>
                                                <div class="user-avatar-placeholder">
                                                    <?php echo substr($user['name'], 0, 1); ?>
                                                </div>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                                <span class="online-indicator" title="Online"></span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('H:i:s', strtotime($user['last_activity'])); ?></td>
                                        <td class="time-ago">
                                            <?php 
                                            $last_activity = strtotime($user['last_activity']);
                                            $time_diff = time() - $last_activity;
                                            
                                            if ($time_diff < 60) {
                                                echo $time_diff . ' seconds ago';
                                            } else if ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' minutes ago';
                                            } else {
                                                echo floor($time_diff / 3600) . ' hours ago';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="admin_view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm">View Profile</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Auto-Refresh Settings</h2>
                    </div>
                    <div class="card-body">
                        <p>Choose how often to refresh the online users list:</p>
                        <select id="refresh-interval" class="form-control" style="width: 200px; margin-bottom: 10px;">
                            <option value="0">Manual refresh only</option>
                            <option value="30">Every 30 seconds</option>
                            <option value="60">Every minute</option>
                            <option value="300">Every 5 minutes</option>
                        </select>
                        <p id="next-refresh"></p>
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
            
            if (seconds === 0) {
                document.getElementById('next-refresh').textContent = 'Auto-refresh disabled.';
                return;
            }
            
            timeLeft = seconds;
            updateTimerDisplay();
            
            refreshInterval = setInterval(() => {
                timeLeft--;
                
                if (timeLeft <= 0) {
                    window.location.reload();
                } else {
                    updateTimerDisplay();
                }
            }, 1000);
        }
        
        function updateTimerDisplay() {
            document.getElementById('next-refresh').textContent = 
                `Next refresh in ${timeLeft} seconds.`;
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
        });
    </script>
</body>
</html>