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
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>User Management</h1>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Filter Form -->
                <div class="card">
                    <div class="card-header">
                        <h2>Filter Users</h2>
                    </div>
                    <form method="get" action="admin_users.php" class="filter-form">
                        <div class="form-group">
                            <label for="search">Search:</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or email">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status">
                                <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="admin_users.php" class="btn btn-outline">Reset</a>
                    </form>
                </div>
                
                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h2>Users</h2>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Verified</th>
                                <th>Last Activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
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
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['email_verified']): ?>
                                    <span class="badge badge-success">Verified</span>
                                    <?php else: ?>
                                    <span class="badge badge-danger">Unverified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo !empty($user['last_activity']) ? date('d M Y H:i', strtotime($user['last_activity'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <div class="actions-dropdown">
                                        <button class="btn btn-sm">Actions <i class="fas fa-caret-down"></i></button>
                                        <div class="actions-dropdown-content">
                                            <a href="admin_view_user.php?id=<?php echo $user['id']; ?>">View Details</a>
                                            
                                            <?php if (!$user['email_verified']): ?>
                                            <form method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="verify_user">Verify Email</button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <form method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="is_blocked" value="<?php echo $user['is_blocked'] ?? 0; ?>">
                                                <button type="submit" name="block_user">
                                                    <?php echo ($user['is_blocked'] ?? 0) ? 'Unblock User' : 'Block User'; ?>
                                                </button>
                                            </form>
                                            
                                            <form method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="is_admin" value="<?php echo $user['is_admin']; ?>">
                                                <button type="submit" name="toggle_admin">
                                                    <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
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
    
    <?php include 'admin_footer.php'; ?>
</body>
</html>