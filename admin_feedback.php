<?php
// admin_feedback.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read'])) {
        $feedback_id = $_POST['feedback_id'];
        
        $sql = "UPDATE user_feedback SET status = 'read' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);
        
        if ($stmt->execute()) {
            redirect('admin_feedback.php?success=Feedback marked as read');
        } else {
            redirect('admin_feedback.php?error=Failed to update feedback');
        }
    }
    
    if (isset($_POST['mark_responded'])) {
        $feedback_id = $_POST['feedback_id'];
        $response = $_POST['response'];
        
        $sql = "UPDATE user_feedback SET status = 'responded', admin_response = ?, response_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $response, $feedback_id);
        
        if ($stmt->execute()) {
            redirect('admin_feedback.php?success=Response sent to user');
        } else {
            redirect('admin_feedback.php?error=Failed to send response');
        }
    }
    
    if (isset($_POST['delete_feedback'])) {
        $feedback_id = $_POST['feedback_id'];
        
        $sql = "DELETE FROM user_feedback WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);
        
        if ($stmt->execute()) {
            redirect('admin_feedback.php?success=Feedback deleted');
        } else {
            redirect('admin_feedback.php?error=Failed to delete feedback');
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Build SQL query
$sql = "SELECT f.*, u.name as user_name, u.email as user_email 
        FROM user_feedback f
        JOIN users u ON f.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

if ($status !== 'all') {
    $sql .= " AND f.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($category !== 'all') {
    $sql .= " AND f.category = ?";
    $params[] = $category;
    $types .= "s";
}

$sql .= " ORDER BY f.created_at DESC";

// Calculate pagination
$count_sql = str_replace("f.*, u.name as user_name, u.email as user_email", "COUNT(*) as count", $sql);
$count_stmt = $conn->prepare($count_sql);

if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}

$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_feedback = $count_result->fetch_assoc()['count'];
$total_pages = ceil($total_feedback / $per_page);

// Add pagination to query
$sql .= " LIMIT ?, ?";
$offset = ($page - 1) * $per_page;
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Execute the main query for feedback items
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$feedback_items = [];
while ($row = $result->fetch_assoc()) {
    $feedback_items[] = $row;
}

// Get feedback statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
    SUM(CASE WHEN status = 'responded' THEN 1 ELSE 0 END) as responded_count
    FROM user_feedback";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get category counts
$category_sql = "SELECT category, COUNT(*) as count FROM user_feedback GROUP BY category";
$category_result = $conn->query($category_sql);
$category_stats = [];
while ($row = $category_result->fetch_assoc()) {
    $category_stats[$row['category']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Cupid Admin</title>
    <?php include 'admin_header_includes.php'; ?>
    <style>
        .feedback-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
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
        
        .stat-card.yellow::before {
            background: linear-gradient(45deg, #f59e0b, #fbbf24);
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
        
        .stat-card.yellow .stat-icon {
            background: #fffbeb;
            color: #f59e0b;
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
        
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
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
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #ff4b6e;
            box-shadow: 0 0 0 4px rgba(255, 75, 110, 0.1);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
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
        
        .feedback-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .card-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h2 i {
            color: #ff4b6e;
        }
        
        .feedback-list {
            padding: 24px;
        }
        
        .feedback-item {
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .feedback-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .feedback-new {
            border-left: 4px solid #ff4b6e;
        }
        
        .feedback-header {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .feedback-user {
            display: flex;
            flex-direction: column;
        }
        
        .feedback-user strong {
            color: #333;
            font-size: 16px;
        }
        
        .feedback-email {
            color: #666;
            font-size: 14px;
        }
        
        .feedback-meta {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .feedback-date {
            color: #999;
            font-size: 14px;
        }
        
        .feedback-content {
            padding: 20px;
            line-height: 1.6;
            color: #444;
        }
        
        .feedback-response {
            margin: 0 20px 16px;
            padding: 16px;
            background: #f0f9ff;
            border-radius: 10px;
            border: 1px solid #bfdbfe;
        }
        
        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .response-header h4 {
            font-size: 16px;
            color: #0369a1;
            margin: 0;
        }
        
        .response-date {
            color: #666;
            font-size: 13px;
        }
        
        .response-content {
            color: #444;
        }
        
        .feedback-actions {
            padding: 16px 20px;
            background: #f8f9fa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .response-form {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f0f0f0;
        }
        
        .response-form .form-group {
            margin-bottom: 16px;
        }
        
        .response-form textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #10b981;
        }
        
        .badge-info {
            background: #e0f2fe;
            color: #0ea5e9;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #ddd;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            padding: 20px;
        }
        
        .pagination-item {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .pagination-item:hover {
            background: #f0f0f0;
            border-color: #ff4b6e;
            color: #ff4b6e;
        }
        
        .pagination-item.active {
            background: linear-gradient(45deg, #ff4b6e, #ff6584);
            color: white;
            border: none;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .feedback-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .feedback-meta {
                flex-wrap: wrap;
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
                <div class="page-header">
                    <h1><i class="fas fa-comments"></i> Feedback Management</h1>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="feedback-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Feedback</div>
                    </div>
                    
                    <div class="stat-card yellow">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['new_count']; ?></div>
                        <div class="stat-label">New</div>
                    </div>
                    
                    <div class="stat-card blue">
                        <div class="stat-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['read_count']; ?></div>
                        <div class="stat-label">Read</div>
                    </div>
                    
                    <div class="stat-card green">
                        <div class="stat-icon">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-value"><?php echo $stats['responded_count']; ?></div>
                        <div class="stat-label">Responded</div>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="filter-card">
                    <form method="get" action="admin_feedback.php" class="filter-form">
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status" class="form-control">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="responded" <?php echo $status === 'responded' ? 'selected' : ''; ?>>Responded</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <select id="category" name="category" class="form-control">
                                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="bug" <?php echo $category === 'bug' ? 'selected' : ''; ?>>Bug Report</option>
                                <option value="feature" <?php echo $category === 'feature' ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="complaint" <?php echo $category === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                <option value="suggestion" <?php echo $category === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="admin_feedback.php" class="btn btn-outline">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    </form>
                </div>
                
                <!-- Feedback List -->
                <div class="feedback-card">
                    <div class="card-header">
                        <h2><i class="fas fa-list"></i> Feedback Items</h2>
                    </div>
                    
                    <?php if (empty($feedback_items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No feedback found matching your filters.</p>
                    </div>
                    <?php else: ?>
                    <div class="feedback-list">
                        <?php foreach ($feedback_items as $feedback): ?>
                        <div class="feedback-item <?php echo $feedback['status'] === 'new' ? 'feedback-new' : ''; ?>">
                            <div class="feedback-header">
                                <div class="feedback-user">
                                    <strong><?php echo htmlspecialchars($feedback['user_name']); ?></strong>
                                    <span class="feedback-email"><?php echo htmlspecialchars($feedback['user_email']); ?></span>
                                </div>
                                
                                <div class="feedback-meta">
                                    <span class="feedback-date"><?php echo date('d M Y H:i', strtotime($feedback['created_at'])); ?></span>
                                    <span class="badge badge-info"><?php echo ucfirst($feedback['category']); ?></span>
                                    <span class="badge badge-<?php 
                                        echo $feedback['status'] === 'new' ? 'danger' : 
                                            ($feedback['status'] === 'read' ? 'warning' : 'success'); 
                                    ?>">
                                        <?php echo ucfirst($feedback['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="feedback-content">
                                <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                            </div>
                            
                            <?php if (!empty($feedback['admin_response'])): ?>
                            <div class="feedback-response">
                                <div class="response-header">
                                    <h4><i class="fas fa-reply"></i> Admin Response</h4>
                                    <span class="response-date"><?php echo date('d M Y H:i', strtotime($feedback['response_at'])); ?></span>
                                </div>
                                <div class="response-content">
                                    <?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="feedback-actions">
                                <?php if ($feedback['status'] === 'new'): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> Mark as Read
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($feedback['status'] !== 'responded'): ?>
                                <button type="button" class="btn btn-sm btn-primary respond-btn" data-id="<?php echo $feedback['id']; ?>">
                                    <i class="fas fa-reply"></i> Respond
                                </button>
                                <?php endif; ?>
                                
                                <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" name="delete_feedback" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Response form (hidden by default) -->
                            <div class="response-form" id="response-form-<?php echo $feedback['id']; ?>" style="display: none;">
                                <form method="post">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <div class="form-group">
                                        <label for="response-<?php echo $feedback['id']; ?>">Your Response:</label>
                                        <textarea id="response-<?php echo $feedback['id']; ?>" 
                                                name="response" 
                                                class="form-control" 
                                                rows="4" 
                                                required></textarea>
                                    </div>
                                    <div class="form-buttons">
                                        <button type="submit" name="mark_responded" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send Response
                                        </button>
                                        <button type="button" class="btn btn-outline cancel-response">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" class="pagination-item">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($page + 2, $total_pages); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" 
                          class="pagination-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>&status=<?php echo urlencode($status); ?>&category=<?php echo urlencode($category); ?>" class="pagination-item">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Show/hide response form
        document.querySelectorAll('.respond-btn').forEach(button => {
            button.addEventListener('click', function() {
                const feedbackId = this.getAttribute('data-id');
                const form = document.getElementById('response-form-' + feedbackId);
                
                // Hide all other response forms
                document.querySelectorAll('.response-form').forEach(f => {
                    if (f !== form) {
                        f.style.display = 'none';
                    }
                });
                
                // Toggle current form
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
                
                if (form.style.display === 'block') {
                    form.querySelector('textarea').focus();
                }
            });
        });
        
        // Cancel response
        document.querySelectorAll('.cancel-response').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.response-form');
                form.style.display = 'none';
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>