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
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>Feedback Management</h1>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Feedback</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['new_count']; ?></div>
                        <div class="stat-label">New</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['read_count']; ?></div>
                        <div class="stat-label">Read</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $stats['responded_count']; ?></div>
                        <div class="stat-label">Responded</div>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="card">
                    <div class="card-header">
                        <h2>Filter Feedback</h2>
                    </div>
                    <form method="get" action="admin_feedback.php" class="filter-form">
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select id="status" name="status">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="new" <?php echo $status === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="responded" <?php echo $status === 'responded' ? 'selected' : ''; ?>>Responded</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category:</label>
                            <select id="category" name="category">
                                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="bug" <?php echo $category === 'bug' ? 'selected' : ''; ?>>Bug Report</option>
                                <option value="feature" <?php echo $category === 'feature' ? 'selected' : ''; ?>>Feature Request</option>
                                <option value="complaint" <?php echo $category === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                                <option value="suggestion" <?php echo $category === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn">Apply Filters</button>
                        <a href="admin_feedback.php" class="btn btn-outline">Reset</a>
                    </form>
                </div>
                
                <!-- Feedback List -->
                <div class="card">
                    <div class="card-header">
                        <h2>Feedback Items</h2>
                    </div>
                    
                    <?php if (empty($feedback_items)): ?>
                    <p class="empty-state">No feedback found matching your filters.</p>
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
                                    <span class="feedback-category badge badge-info"><?php echo ucfirst($feedback['category']); ?></span>
                                    <span class="feedback-status badge badge-<?php 
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
                                    <h4>Admin Response</h4>
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
                                    <button type="submit" name="mark_read" class="btn btn-sm">Mark as Read</button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($feedback['status'] !== 'responded'): ?>
                                <button type="button" class="btn btn-sm btn-primary respond-btn" data-id="<?php echo $feedback['id']; ?>">
                                    Respond
                                </button>
                                <?php endif; ?>
                                
                                <form method="post" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <button type="submit" name="delete_feedback" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                            
                            <!-- Response form (hidden by default) -->
                            <div class="response-form" id="response-form-<?php echo $feedback['id']; ?>" style="display: none;">
                                <form method="post">
                                    <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                                    <div class="form-group">
                                        <label for="response-<?php echo $feedback['id']; ?>">Your Response:</label>
                                        <textarea id="response-<?php echo $feedback['id']; ?>" name="response" rows="4" required></textarea>
                                    </div>
                                    <div class="form-buttons">
                                        <button type="submit" name="mark_responded" class="btn">Send Response</button>
                                        <button type="button" class="btn btn-outline cancel-response">Cancel</button>
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
                            <i class="fas fa-chevron-left"></i> Previous
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
                            Next <i class="fas fa-chevron-right"></i>
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
                document.getElementById('response-form-' + feedbackId).style.display = 'block';
                this.style.display = 'none';
            });
        });
        
        // Cancel response
        document.querySelectorAll('.cancel-response').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.response-form');
                form.style.display = 'none';
                form.closest('.feedback-item').querySelector('.respond-btn').style.display = 'inline-block';
            });
        });
    </script>
</body>
</html>