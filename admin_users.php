<?php
// admin_users.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_admin'])) {
        $user_id = $_POST['user_id'];
        $is_admin = $_POST['is_admin'] ? 0 : 1; // Toggle admin status
        
        $sql = "UPDATE users SET is_admin = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $is_admin, $user_id);
        $stmt->execute();
        
        redirect('admin_users.php?success=User admin status updated');
    }
    
    if (isset($_POST['block_user'])) {
        $user_id = $_POST['user_id'];
        $is_blocked = $_POST['is_blocked'] ? 0 : 1; // Toggle blocked status
        
        $sql = "UPDATE users SET is_blocked = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $is_blocked, $user_id);
        $stmt->execute();
        
        redirect('admin_users.php?success=User block status updated');
    }
    
    if (isset($_POST['verify_user'])) {
        $user_id = $_POST['user_id'];
        
        $sql = "UPDATE users SET email_verified = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        redirect('admin_users.php?success=User email verified');
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Get users
$filters = [
    'search' => $search,
    'status' => $status,
    'page' => $page,
    'per_page' => $per_page
];

$users = getUsers($conn, $filters);
$total_users = countUsers($conn, $filters);
$total_pages = ceil($total_users / $per_page);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Cupid Admin</title>
    <?php include 'admin_header_includes.php'; ?>
    <style>
        /* Modern User Management Dashboard */
        .user-management-dashboard {
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
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
        
        .stat-card-users .stat-icon {
            background: rgba(255, 75, 110, 0.1);
            color: #ff4b6e;
        }
        
        .stat-card-verified .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .stat-card-active .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .stat-card-blocked .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
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
        
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff4b6e;
            background: white;
            box-shadow: 0 0 0 4px rgba(255, 75, 110, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #e6435f, #e65e75);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 75, 110, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #ff4b6e;
            color: #ff4b6e;
        }
        
        .btn-outline:hover {
            background: #ff4b6e;
            color: white;
        }
        
        .users-table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h2 i {
            color: #ff4b6e;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            text-align: left;
            padding: 16px 24px;
            background: #f8f9fa;
            font-weight: 600;
            font-size: 14px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            white-space: nowrap;
        }
        
        .users-table td {
            padding: 16px 24px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .users-table tr:hover {
            background: #fafafa;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
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
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }
        
        .user-email {
            font-size: 13px;
            color: #666;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            gap: 4px;
        }
        
        .badge i {
            font-size: 12px;
        }
        
        .badge-success {
            background: #e8f8f0;
            color: #10b981;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .badge-info {
            background: #e0f2fe;
            color: #0ea5e9;
        }
        
        .action-dropdown {
            position: relative;
        }
        
        .action-btn {
            background: #f0f0f0;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #ff4b6e;
            color: white;
        }
        
        .action-menu {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 5px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.2s ease;
        }
        
        .action-dropdown:hover .action-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .action-menu a,
        .action-menu button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            width: 100%;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .action-menu a:hover,
        .action-menu button:hover {
            background: #fff0f3;
            color: #ff4b6e;
        }
        
        .action-menu i {
            width: 16px;
            text-align: center;
        }
        
        .action-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
            gap: 8px;
        }
        
        .pagination-item {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .pagination-item:hover {
            background: #ff4b6e;
            color: white;
        }
        
        .pagination-item.active {
            background: #ff4b6e;
            color: white;
        }
        
        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        
        .alert-success {
            background: #e8f8f0;
            color: #10b981;
            border: 1px solid #10b981;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .alert-close {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .dashboard-hero {
                padding: 30px 20px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .users-table th, .users-table td {
                padding: 12px 16px;
            }
            
            .action-menu {
                right: auto;
                left: 0;
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
                <div class="user-management-dashboard">
                    <!-- Dashboard Hero Section -->
                    <div class="dashboard-hero">
                        <div class="dashboard-title">
                            <h1><i class="fas fa-users"></i> User Management</h1>
                            <p class="dashboard-subtitle">Manage and monitor all users registered on the platform</p>
                        </div>
                    </div>
                    
                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button class="alert-close" onclick="this.parentElement.remove();">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- User Statistics -->
                    <div class="stats-row">
                        <div class="stat-card stat-card-users">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        
                        <div class="stat-card stat-card-verified">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-number">
                                <?php 
                                $verified_count = 0;
                                foreach($users as $user) {
                                    if($user['email_verified']) $verified_count++;
                                }
                                echo number_format($verified_count);
                                ?>
                            </div>
                            <div class="stat-label">Verified Users</div>
                        </div>
                        
                        <div class="stat-card stat-card-active">
                            <div class="stat-icon">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div class="stat-number">
                                <?php 
                                $active_count = 0;
                                foreach($users as $user) {
                                    if(!empty($user['last_activity'])) $active_count++;
                                }
                                echo number_format($active_count);
                                ?>
                            </div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        
                        <div class="stat-card stat-card-blocked">
                            <div class="stat-icon">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <div class="stat-number">
                                <?php 
                                $blocked_count = 0;
                                foreach($users as $user) {
                                    if($user['is_blocked'] ?? 0) $blocked_count++;
                                }
                                echo number_format($blocked_count);
                                ?>
                            </div>
                            <div class="stat-label">Blocked Users</div>
                        </div>
                    </div>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <form method="get" action="admin_users.php" class="filter-form">
                            <div class="form-group">
                                <label for="search">Search Users</label>
                                <input type="text" id="search" name="search" class="form-control" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by name or email...">
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <a href="admin_users.php" class="btn btn-outline">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                        </form>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="users-table-card">
                        <div class="table-header">
                            <h2><i class="fas fa-list"></i> User Directory</h2>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Joined</th>
                                        <th>Status</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>#<?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php if (!empty($user['profile_pic'])): ?>
                                                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile">
                                                    <?php else: ?>
                                                    <div class="user-avatar-placeholder">
                                                        <?php echo substr($user['name'], 0, 1); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['email_verified']): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Verified
                                            </span>
                                            <?php else: ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times-circle"></i> Unverified
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['is_admin']): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-user-shield"></i> Admin
                                            </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($user['is_blocked'] ?? false): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-ban"></i> Blocked
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['last_activity'])): ?>
                                                <?php echo date('d M Y H:i', strtotime($user['last_activity'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-dropdown">
                                                <button class="action-btn">
                                                    <i class="fas fa-ellipsis-v"></i> Actions
                                                </button>
                                                <div class="action-menu">
                                                    <a href="admin_view_user.php?id=<?php echo $user['id']; ?>">
                                                        <i class="fas fa-eye"></i> View Profile
                                                    </a>
                                                    
                                                    <?php if (!$user['email_verified']): ?>
                                                    <form method="post" style="margin: 0;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="verify_user">
                                                            <i class="fas fa-check"></i> Verify Email
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <div class="action-divider"></div>
                                                    
                                                    <form method="post" style="margin: 0;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="is_admin" value="<?php echo $user['is_admin']; ?>">
                                                        <button type="submit" name="toggle_admin">
                                                            <?php if ($user['is_admin']): ?>
                                                                <i class="fas fa-user-times"></i> Remove Admin
                                                            <?php else: ?>
                                                                <i class="fas fa-user-shield"></i> Make Admin
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <form method="post" style="margin: 0;">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="is_blocked" value="<?php echo $user['is_blocked'] ?? 0; ?>">
                                                        <button type="submit" name="block_user" class="text-danger">
                                                            <?php if ($user['is_blocked'] ?? 0): ?>
                                                                <i class="fas fa-unlock"></i> Unblock User
                                                            <?php else: ?>
                                                                <i class="fas fa-ban"></i> Block User
                                                            <?php endif; ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="pagination-item">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" 
                              class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>" class="pagination-item">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
            
            // Search functionality with debounce
            const searchInput = document.getElementById('search');
            let searchTimeout;
            
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (e.target.value.length >= 3 || e.target.value.length === 0) {
                            // Only auto-submit if empty or 3+ characters
                            this.form.submit();
                        }
                    }, 500);
                });
            }
            
            // Confirmation for dangerous actions
            const dangerousActions = document.querySelectorAll('button[name="block_user"], button[name="toggle_admin"]');
            dangerousActions.forEach(button => {
                const parent = button.closest('form');
                if (parent) {
                    parent.addEventListener('submit', function(e) {
                        const userIdInput = this.querySelector('input[name="user_id"]');
                        const user_id = userIdInput ? userIdInput.value : '';
                        
                        let message = 'Are you sure you want to ';
                        if (button.name === 'block_user') {
                            const isBlocked = this.querySelector('input[name="is_blocked"]').value === '1';
                            message += isBlocked ? 'unblock' : 'block';
                            message += ' this user?';
                        } else if (button.name === 'toggle_admin') {
                            const isAdmin = this.querySelector('input[name="is_admin"]').value === '1';
                            message += isAdmin ? 'remove admin privileges from' : 'grant admin privileges to';
                            message += ' this user?';
                        }
                        
                        if (!confirm(message)) {
                            e.preventDefault();
                        }
                    });
                }
            });
            
            // Table row hover effect
            const tableRows = document.querySelectorAll('.users-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.002)';
                    this.style.transition = 'all 0.2s ease';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Add loading state to buttons on click
            const actionButtons = document.querySelectorAll('.action-menu button');
            actionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    if (!this.classList.contains('loading')) {
                        this.classList.add('loading');
                        const originalContent = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                        this.disabled = true;
                        
                        // Reset after form submission
                        setTimeout(() => {
                            this.classList.remove('loading');
                            this.innerHTML = originalContent;
                            this.disabled = false;
                        }, 5000);
                    }
                });
            });
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl+F or Cmd+F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    const searchInput = document.getElementById('search');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                
                // Escape to close action menus
                if (e.key === 'Escape') {
                    const openMenus = document.querySelectorAll('.action-dropdown:hover .action-menu');
                    openMenus.forEach(menu => {
                        menu.parentElement.blur();
                    });
                }
            });
            
            // Add touch support for action menus on mobile
            const actionDropdowns = document.querySelectorAll('.action-dropdown');
            actionDropdowns.forEach(dropdown => {
                const button = dropdown.querySelector('.action-btn');
                const menu = dropdown.querySelector('.action-menu');
                
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close other open menus
                    document.querySelectorAll('.action-menu.show').forEach(openMenu => {
                        if (openMenu !== menu) {
                            openMenu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current menu
                    menu.classList.toggle('show');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target)) {
                        menu.classList.remove('show');
                    }
                });
            });
        });