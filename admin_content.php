<?php
// admin_content.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Handle content updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_announcement'])) {
        $id = $_POST['announcement_id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($id)) {
            // Insert new announcement
            $sql = "INSERT INTO announcements (title, content, is_active, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $title, $content, $is_active);
        } else {
            // Update existing announcement
            $sql = "UPDATE announcements SET title = ?, content = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $title, $content, $is_active, $id);
        }
        
        if ($stmt->execute()) {
            redirect('admin_content.php?success=Announcement updated&tab=announcements');
        } else {
            redirect('admin_content.php?error=Failed to update announcement&tab=announcements');
        }
    }
    
    if (isset($_POST['update_faq'])) {
        $id = $_POST['faq_id'];
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $category = $_POST['category'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($id)) {
            // Insert new FAQ
            $sql = "INSERT INTO faqs (question, answer, category, is_active) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $question, $answer, $category, $is_active);
        } else {
            // Update existing FAQ
            $sql = "UPDATE faqs SET question = ?, answer = ?, category = ?, is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssii", $question, $answer, $category, $is_active, $id);
        }
        
        if ($stmt->execute()) {
            redirect('admin_content.php?success=FAQ updated&tab=faqs');
        } else {
            redirect('admin_content.php?error=Failed to update FAQ&tab=faqs');
        }
    }
    
    if (isset($_POST['update_policy'])) {
        $type = $_POST['policy_type'];
        $content = $_POST['policy_content'];
        
        $sql = "UPDATE site_settings SET value = ? WHERE setting_key = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $content, $type);
        
        if ($stmt->execute()) {
            redirect('admin_content.php?success=Policy updated&tab=policies');
        } else {
            redirect('admin_content.php?error=Failed to update policy&tab=policies');
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $id = $_POST['announcement_id'];
        
        $sql = "DELETE FROM announcements WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            redirect('admin_content.php?success=Announcement deleted&tab=announcements');
        } else {
            redirect('admin_content.php?error=Failed to delete announcement&tab=announcements');
        }
    }
    
    if (isset($_POST['delete_faq'])) {
        $id = $_POST['faq_id'];
        
        $sql = "DELETE FROM faqs WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            redirect('admin_content.php?success=FAQ deleted&tab=faqs');
        } else {
            redirect('admin_content.php?error=Failed to delete FAQ&tab=faqs');
        }
    }
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'announcements';

// Check if we have an announcement to edit
$edit_announcement = null;
if (isset($_GET['edit_announcement'])) {
    $id = intval($_GET['edit_announcement']);
    $sql = "SELECT * FROM announcements WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_announcement = $result->fetch_assoc();
    }
}

// Check if we have a FAQ to edit
$edit_faq = null;
if (isset($_GET['edit_faq'])) {
    $id = intval($_GET['edit_faq']);
    $sql = "SELECT * FROM faqs WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_faq = $result->fetch_assoc();
    }
}

// Get all announcements
$announcements = [];
$announcements_sql = "SELECT * FROM announcements ORDER BY created_at DESC";
$announcements_result = $conn->query($announcements_sql);
while ($row = $announcements_result->fetch_assoc()) {
    $announcements[] = $row;
}

// Get all FAQs
$faqs = [];
$faqs_sql = "SELECT * FROM faqs ORDER BY category, id";
$faqs_result = $conn->query($faqs_sql);
while ($row = $faqs_result->fetch_assoc()) {
    $faqs[] = $row;
}

// Get policies
$policies = [];
$policies_sql = "SELECT * FROM site_settings WHERE setting_key IN ('terms_of_service', 'privacy_policy')";
$policies_result = $conn->query($policies_sql);
while ($row = $policies_result->fetch_assoc()) {
    $policies[$row['setting_key']] = $row['value'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Cupid Admin</title>
    <?php include 'admin_header_includes.php'; ?>
    <style>
        .content-nav {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .content-nav .nav-item {
            padding: 10px 20px;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .content-nav .nav-item.active {
            color: #ff4b6e;
            background: #fff0f3;
        }
        
        .content-nav .nav-item:hover {
            color: #ff4b6e;
            background: #fff0f3;
        }
        
        .content-nav .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #ff4b6e;
        }
        
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .content-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        
        .content-header {
            padding: 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .content-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .content-header h2 i {
            color: #ff4b6e;
        }
        
        .content-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 24px;
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
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #ff4b6e;
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
        
        .btn-danger {
            background: #ff5c5c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e64848;
        }
        
        .content-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .content-table th {
            text-align: left;
            padding: 16px;
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .content-table td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }
        
        .content-table tr:hover {
            background: #fafafa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-active {
            background: #e8f8f0;
            color: #10b981;
        }
        
        .status-inactive {
            background: #f0f0f0;
            color: #666;
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
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
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #e8f8f0;
            color: #10b981;
            border: 1px solid #10b981;
        }
        
        .alert-danger {
            background: #fff0f0;
            color: #e53e3e;
            border: 1px solid #e53e3e;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        @media (max-width: 768px) {
            .content-nav {
                flex-wrap: wrap;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .table-actions {
                flex-direction: column;
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
                    <h1><i class="fas fa-file-alt"></i> Content Management</h1>
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
                
                <!-- Content Tabs -->
                <div class="content-nav">
                    <a href="?tab=announcements" class="nav-item <?php echo $active_tab === 'announcements' ? 'active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i> Announcements
                    </a>
                    <a href="?tab=faqs" class="nav-item <?php echo $active_tab === 'faqs' ? 'active' : ''; ?>">
                        <i class="fas fa-question-circle"></i> FAQs
                    </a>
                    <a href="?tab=policies" class="nav-item <?php echo $active_tab === 'policies' ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i> Policies
                    </a>
                </div>
                
                <!-- Announcements Tab -->
                <div class="tab-content <?php echo $active_tab === 'announcements' ? 'active' : ''; ?>" id="announcements-tab">
                    <div class="content-card">
                        <div class="content-header">
                            <h2>
                                <i class="fas fa-plus-circle"></i>
                                <?php echo $edit_announcement ? 'Edit Announcement' : 'Add New Announcement'; ?>
                            </h2>
                        </div>
                        
                        <div class="content-body">
                            <form method="post" class="content-form">
                                <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement ? $edit_announcement['id'] : ''; ?>">
                                
                                <div class="form-group">
                                    <label for="title">Title</label>
                                    <input type="text" id="title" name="title" class="form-control" 
                                           value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['title']) : ''; ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="content">Content</label>
                                    <textarea id="content" name="content" class="form-control" rows="5" required><?php echo $edit_announcement ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($edit_announcement && $edit_announcement['is_active']) ? 'checked' : ''; ?>>
                                    <label for="is_active">Active</label>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="update_announcement" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        <?php echo $edit_announcement ? 'Update Announcement' : 'Add Announcement'; ?>
                                    </button>
                                    
                                    <?php if ($edit_announcement): ?>
                                    <a href="admin_content.php?tab=announcements" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="content-header">
                            <h2><i class="fas fa-list"></i> All Announcements</h2>
                        </div>
                        
                        <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <p>No announcements found.</p>
                        </div>
                        <?php else: ?>
                        <table class="content-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Content</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $announcement): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($announcement['title']); ?></td>
                                    <td><?php echo substr(htmlspecialchars($announcement['content']), 0, 100) . (strlen($announcement['content']) > 100 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $announcement['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($announcement['created_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="admin_content.php?tab=announcements&edit_announcement=<?php echo $announcement['id']; ?>" 
                                               class="btn btn-sm btn-outline">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                <button type="submit" name="delete_announcement" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- FAQs Tab -->
                <div class="tab-content <?php echo $active_tab === 'faqs' ? 'active' : ''; ?>" id="faqs-tab">
                    <div class="content-card">
                        <div class="content-header">
                            <h2>
                                <i class="fas fa-plus-circle"></i>
                                <?php echo $edit_faq ? 'Edit FAQ' : 'Add New FAQ'; ?>
                            </h2>
                        </div>
                        
                        <div class="content-body">
                            <form method="post" class="content-form">
                                <input type="hidden" name="faq_id" value="<?php echo $edit_faq ? $edit_faq['id'] : ''; ?>">
                                
                                <div class="form-group">
                                    <label for="question">Question</label>
                                    <input type="text" id="question" name="question" class="form-control" 
                                           value="<?php echo $edit_faq ? htmlspecialchars($edit_faq['question']) : ''; ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="answer">Answer</label>
                                    <textarea id="answer" name="answer" class="form-control" rows="5" required><?php echo $edit_faq ? htmlspecialchars($edit_faq['answer']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" class="form-control" required>
                                        <option value="general" <?php echo ($edit_faq && $edit_faq['category'] === 'general') ? 'selected' : ''; ?>>General</option>
                                        <option value="account" <?php echo ($edit_faq && $edit_faq['category'] === 'account') ? 'selected' : ''; ?>>Account</option>
                                        <option value="payments" <?php echo ($edit_faq && $edit_faq['category'] === 'payments') ? 'selected' : ''; ?>>Payments</option>
                                        <option value="features" <?php echo ($edit_faq && $edit_faq['category'] === 'features') ? 'selected' : ''; ?>>Features</option>
                                    </select>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" id="is_active" name="is_active" 
                                           <?php echo ($edit_faq && $edit_faq['is_active']) ? 'checked' : ''; ?>>
                                    <label for="is_active">Active</label>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="update_faq" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        <?php echo $edit_faq ? 'Update FAQ' : 'Add FAQ'; ?>
                                    </button>
                                    
                                    <?php if ($edit_faq): ?>
                                    <a href="admin_content.php?tab=faqs" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="content-header">
                            <h2><i class="fas fa-list"></i> All FAQs</h2>
                        </div>
                        
                        <?php if (empty($faqs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-question-circle"></i>
                            <p>No FAQs found.</p>
                        </div>
                        <?php else: ?>
                        <table class="content-table">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($faq['question']); ?></td>
                                    <td>
                                        <span class="status-badge status-info">
                                            <?php echo ucfirst($faq['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $faq['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="admin_content.php?tab=faqs&edit_faq=<?php echo $faq['id']; ?>" 
                                               class="btn btn-sm btn-outline">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                                <button type="submit" name="delete_faq" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Policies Tab -->
                <div class="tab-content <?php echo $active_tab === 'policies' ? 'active' : ''; ?>" id="policies-tab">
                    <div class="content-card">
                        <div class="content-header">
                            <h2><i class="fas fa-file-contract"></i> Terms of Service</h2>
                        </div>
                        
                        <div class="content-body">
                            <form method="post" class="content-form">
                                <input type="hidden" name="policy_type" value="terms_of_service">
                                
                                <div class="form-group">
                                    <textarea id="policy_content" name="policy_content" class="form-control" rows="15"><?php echo isset($policies['terms_of_service']) ? htmlspecialchars($policies['terms_of_service']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="update_policy" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Terms of Service
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="content-header">
                            <h2><i class="fas fa-user-shield"></i> Privacy Policy</h2>
                        </div>
                        
                        <div class="content-body">
                            <form method="post" class="content-form">
                                <input type="hidden" name="policy_type" value="privacy_policy">
                                
                                <div class="form-group">
                                    <textarea id="policy_content" name="policy_content" class="form-control" rows="15"><?php echo isset($policies['privacy_policy']) ? htmlspecialchars($policies['privacy_policy']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="update_policy" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Privacy Policy
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'admin_footer.php'; ?>
    
    <script>
        // Tab content visibility
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?php echo $active_tab; ?>';
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            document.getElementById(currentTab + '-tab').style.display = 'block';
        });
    </script>
</body>
</html>