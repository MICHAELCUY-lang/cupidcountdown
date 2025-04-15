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
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>Content Management</h1>
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
                
                <!-- Content Tabs -->
                <div class="tabs">
                    <div class="tab <?php echo $active_tab === 'announcements' ? 'active' : ''; ?>" data-tab="announcements">Announcements</div>
                    <div class="tab <?php echo $active_tab === 'faqs' ? 'active' : ''; ?>" data-tab="faqs">FAQs</div>
                    <div class="tab <?php echo $active_tab === 'policies' ? 'active' : ''; ?>" data-tab="policies">Policies</div>
                </div>
                
                <!-- Announcements Tab -->
                <div class="tab-content <?php echo $active_tab === 'announcements' ? 'active' : ''; ?>" id="announcements-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo $edit_announcement ? 'Edit Announcement' : 'Add New Announcement'; ?></h2>
                        </div>
                        
                        <form method="post" class="content-form">
                            <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement ? $edit_announcement['id'] : ''; ?>">
                            
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" value="<?php echo $edit_announcement ? htmlspecialchars($edit_announcement['title']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="content">Content</label>
                                <textarea id="content" name="content" rows="5" required><?php echo $edit_announcement ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo ($edit_announcement && $edit_announcement['is_active']) ? 'checked' : ''; ?>>
                                <label for="is_active">Active</label>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_announcement" class="btn">
                                    <?php echo $edit_announcement ? 'Update Announcement' : 'Add Announcement'; ?>
                                </button>
                                
                                <?php if ($edit_announcement): ?>
                                <a href="admin_content.php?tab=announcements" class="btn btn-outline">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>All Announcements</h2>
                        </div>
                        
                        <?php if (empty($announcements)): ?>
                        <p class="empty-state">No announcements found.</p>
                        <?php else: ?>
                        <table>
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
                                        <span class="badge <?php echo $announcement['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($announcement['created_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="admin_content.php?tab=announcements&edit_announcement=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-outline">
                                                Edit
                                            </a>
                                            
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                <button type="submit" name="delete_announcement" class="btn btn-sm btn-danger">Delete</button>
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
                    <div class="card">
                        <div class="card-header">
                            <h2><?php echo $edit_faq ? 'Edit FAQ' : 'Add New FAQ'; ?></h2>
                        </div>
                        
                        <form method="post" class="content-form">
                            <input type="hidden" name="faq_id" value="<?php echo $edit_faq ? $edit_faq['id'] : ''; ?>">
                            
                            <div class="form-group">
                                <label for="question">Question</label>
                                <input type="text" id="question" name="question" value="<?php echo $edit_faq ? htmlspecialchars($edit_faq['question']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="answer">Answer</label>
                                <textarea id="answer" name="answer" rows="5" required><?php echo $edit_faq ? htmlspecialchars($edit_faq['answer']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category" required>
                                    <option value="general" <?php echo ($edit_faq && $edit_faq['category'] === 'general') ? 'selected' : ''; ?>>General</option>
                                    <option value="account" <?php echo ($edit_faq && $edit_faq['category'] === 'account') ? 'selected' : ''; ?>>Account</option>
                                    <option value="payments" <?php echo ($edit_faq && $edit_faq['category'] === 'payments') ? 'selected' : ''; ?>>Payments</option>
                                    <option value="features" <?php echo ($edit_faq && $edit_faq['category'] === 'features') ? 'selected' : ''; ?>>Features</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" id="is_active" name="is_active" <?php echo ($edit_faq && $edit_faq['is_active']) ? 'checked' : ''; ?>>
                                <label for="is_active">Active</label>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_faq" class="btn">
                                    <?php echo $edit_faq ? 'Update FAQ' : 'Add FAQ'; ?>
                                </button>
                                
                                <?php if ($edit_faq): ?>
                                <a href="admin_content.php?tab=faqs" class="btn btn-outline">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>All FAQs</h2>
                        </div>
                        
                        <?php if (empty($faqs)): ?>
                        <p class="empty-state">No FAQs found.</p>
                        <?php else: ?>
                        <table>
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
                                        <span class="badge badge-info">
                                            <?php echo ucfirst($faq['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $faq['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $faq['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="admin_content.php?tab=faqs&edit_faq=<?php echo $faq['id']; ?>" class="btn btn-sm btn-outline">
                                                Edit
                                            </a>
                                            
                                            <form method="post" onsubmit="return confirm('Are you sure you want to delete this FAQ?');">
                                                <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                                <button type="submit" name="delete_faq" class="btn btn-sm btn-danger">Delete</button>
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
                    <div class="card">
                        <div class="card-header">
                            <h2>Terms of Service</h2>
                        </div>
                        
                        <form method="post" class="content-form">
                            <input type="hidden" name="policy_type" value="terms_of_service">
                            
                            <div class="form-group">
                                <textarea id="policy_content" name="policy_content" rows="15"><?php echo isset($policies['terms_of_service']) ? htmlspecialchars($policies['terms_of_service']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_policy" class="btn">Update Terms of Service</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2>Privacy Policy</h2>
                        </div>
                        
                        <form method="post" class="content-form">
                            <input type="hidden" name="policy_type" value="privacy_policy">
                            
                            <div class="form-group">
                                <textarea id="policy_content" name="policy_content" rows="15"><?php echo isset($policies['privacy_policy']) ? htmlspecialchars($policies['privacy_policy']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="update_policy" class="btn">Update Privacy Policy</button>
                            </div>
                        </form>
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
                
                // Update URL parameter
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + 
                               '?tab=' + this.getAttribute('data-tab');
                window.history.pushState({path: newUrl}, '', newUrl);
            });
        });
    </script>
</body>
</html>