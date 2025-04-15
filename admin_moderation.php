<?php
// admin_moderation.php
require_once 'config.php';
require_once 'admin_functions.php';

// Make sure user is logged in and is admin
requireLogin();
requireAdmin();

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['dismiss_report'])) {
        $report_id = $_POST['report_id'];
        
        $sql = "UPDATE content_reports SET status = 'dismissed', resolved_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $report_id);
        
        if ($stmt->execute()) {
            redirect('admin_moderation.php?success=Report dismissed&tab=reports');
        } else {
            redirect('admin_moderation.php?error=Failed to dismiss report&tab=reports');
        }
    }
    
    if (isset($_POST['take_action'])) {
        $report_id = $_POST['report_id'];
        $action_taken = $_POST['action_taken'];
        $content_id = $_POST['content_id'];
        $content_type = $_POST['content_type'];
        $user_id = $_POST['user_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update report status
            $update_sql = "UPDATE content_reports SET status = 'actioned', action_taken = ?, resolved_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("si", $action_taken, $report_id);
            $update_stmt->execute();
            
            // Take action based on content type and action chosen
            if ($content_type === 'message') {
                if ($action_taken === 'delete_content') {
                    $delete_sql = "DELETE FROM chat_messages WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $content_id);
                    $delete_stmt->execute();
                }
            } elseif ($content_type === 'menfess') {
                if ($action_taken === 'delete_content') {
                    $delete_sql = "DELETE FROM menfess WHERE id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $content_id);
                    $delete_stmt->execute();
                }
            } elseif ($content_type === 'profile') {
                if ($action_taken === 'delete_content') {
                    $delete_sql = "UPDATE profiles SET bio = '', interests = '' WHERE user_id = ?";
                    $delete_stmt = $conn->prepare($delete_sql);
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                }
            }
            
            // If action is to block user
            if ($action_taken === 'block_user') {
                $block_sql = "UPDATE users SET is_blocked = 1 WHERE id = ?";
                $block_stmt = $conn->prepare($block_sql);
                $block_stmt->bind_param("i", $user_id);
                $block_stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            redirect('admin_moderation.php?success=Action taken successfully&tab=reports');
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            redirect('admin_moderation.php?error=Failed to take action: ' . $e->getMessage() . '&tab=reports');
        }
    }
    
    if (isset($_POST['verify_identity'])) {
        $verification_id = $_POST['verification_id'];
        $status = $_POST['verification_status'];
        $notes = $_POST['verification_notes'] ?? '';
        
        $sql = "UPDATE identity_verifications SET status = ?, admin_notes = ?, verified_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $notes, $verification_id);
        
        if ($stmt->execute()) {
            redirect('admin_moderation.php?success=Verification updated&tab=verifications');
        } else {
            redirect('admin_moderation.php?error=Failed to update verification&tab=verifications');
        }
    }
}

// Get active tab
$active_tab = $_GET['tab'] ?? 'reports';

// Get content reports
$reports_sql = "SELECT r.*, 
               u.name as reporter_name, 
               tu.name as target_user_name,
               tu.id as target_user_id
               FROM content_reports r
               JOIN users u ON r.user_id = u.id
               JOIN users tu ON r.target_user_id = tu.id
               ORDER BY r.created_at DESC";
$reports_result = $conn->query($reports_sql);
$reports = [];
while ($row = $reports_result->fetch_assoc()) {
    $reports[] = $row;
}

// Get identity verifications
$verifications_sql = "SELECT v.*, 
                    u.name as user_name, 
                    u.email as user_email
                    FROM identity_verifications v
                    JOIN users u ON v.user_id = u.id
                    ORDER BY v.created_at DESC";
$verifications_result = $conn->query($verifications_sql);
$verifications = [];
while ($row = $verifications_result->fetch_assoc()) {
    $verifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderation Tools - Cupid Admin</title>
    <link rel="stylesheet" href="css/admin_styles.css">
    <?php include 'admin_header_includes.php'; ?>
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="admin-container">
        <div class="container">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="page-header">
                    <h1>Moderation Tools</h1>
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
                
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" data-tab="reports">
                        Content Reports
                    </div>
                    <div class="tab <?php echo $active_tab === 'verifications' ? 'active' : ''; ?>" data-tab="verifications">
                        Identity Verifications
                    </div>
                    <div class="tab <?php echo $active_tab === 'logs' ? 'active' : ''; ?>" data-tab="logs">
                        Moderation Logs
                    </div>
                </div>
                
                <!-- Content Reports Tab -->
                <div class="tab-content <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" id="reports-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Content Reports</h2>
                        </div>
                        
                        <?php if (empty($reports)): ?>
                        <p class="empty-state">No content reports found.</p>
                        <?php else: ?>
                        <div class="reports-list">
                            <?php foreach ($reports as $report): ?>
                            <div class="report-item <?php echo $report['status'] === 'pending' ? 'report-pending' : ''; ?>">
                                <div class="report-header">
                                    <div class="report-meta">
                                        <strong>Report #<?php echo $report['id']; ?></strong>
                                        <span class="report-date"><?php echo date('d M Y H:i', strtotime($report['created_at'])); ?></span>
                                        <span class="report-status badge badge-<?php 
                                            echo $report['status'] === 'pending' ? 'warning' : 
                                                ($report['status'] === 'actioned' ? 'success' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="report-users">
                                        <div class="reported-by">
                                            <span class="label">Reported by:</span>
                                            <span class="value"><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                        </div>
                                        
                                        <div class="reported-content">
                                            <span class="label">Content by:</span>
                                            <span class="value"><?php echo htmlspecialchars($report['target_user_name']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="report-details">
                                    <div class="report-type">
                                        <span class="label">Content Type:</span>
                                        <span class="value badge badge-info">
                                            <?php echo ucfirst($report['content_type']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="report-reason">
                                        <span class="label">Reason:</span>
                                        <span class="value badge badge-danger">
                                            <?php echo ucfirst($report['reason']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="report-content">
                                    <h4>Reported Content:</h4>
                                    <div class="content-box">
                                        <?php echo nl2br(htmlspecialchars($report['content'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($report['additional_info'])): ?>
                                <div class="report-additional">
                                    <h4>Additional Information:</h4>
                                    <div class="content-box">
                                        <?php echo nl2br(htmlspecialchars($report['additional_info'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($report['status'] === 'actioned'): ?>
                                <div class="report-action-taken">
                                    <h4>Action Taken:</h4>
                                    <div class="content-box">
                                        <p>
                                            <strong>Action:</strong> 
                                            <?php 
                                            $action = $report['action_taken'];
                                            echo $action === 'delete_content' ? 'Content Deleted' : 
                                                ($action === 'block_user' ? 'User Blocked' : 
                                                 ($action === 'warning' ? 'Warning Issued' : $action));
                                            ?>
                                        </p>
                                        <p>
                                            <strong>Resolved at:</strong> 
                                            <?php echo date('d M Y H:i', strtotime($report['resolved_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($report['status'] === 'pending'): ?>
                                <div class="report-actions">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" name="dismiss_report" class="btn btn-outline">Dismiss Report</button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-primary take-action-btn" data-id="<?php echo $report['id']; ?>">
                                        Take Action
                                    </button>
                                </div>
                                
                                <!-- Action form (hidden by default) -->
                                <div class="action-form" id="action-form-<?php echo $report['id']; ?>" style="display: none;">
                                    <form method="post">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="content_id" value="<?php echo $report['content_id']; ?>">
                                        <input type="hidden" name="content_type" value="<?php echo $report['content_type']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $report['target_user_id']; ?>">
                                        
                                        <div class="form-group">
                                            <label for="action-<?php echo $report['id']; ?>">Select Action:</label>
                                            <select id="action-<?php echo $report['id']; ?>" name="action_taken" required>
                                                <option value="delete_content">Delete Content</option>
                                                <option value="block_user">Block User</option>
                                                <option value="warning">Issue Warning</option>
                                                <option value="no_action">No Action Required</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-buttons">
                                            <button type="submit" name="take_action" class="btn">Submit Action</button>
                                            <button type="button" class="btn btn-outline cancel-action">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Identity Verifications Tab -->
                <div class="tab-content <?php echo $active_tab === 'verifications' ? 'active' : ''; ?>" id="verifications-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Identity Verifications</h2>
                        </div>
                        
                        <?php if (empty($verifications)): ?>
                        <p class="empty-state">No identity verifications found.</p>
                        <?php else: ?>
                        <div class="verifications-list">
                            <?php foreach ($verifications as $verification): ?>
                            <div class="verification-item <?php echo $verification['status'] === 'pending' ? 'verification-pending' : ''; ?>">
                                <div class="verification-header">
                                    <div class="verification-meta">
                                        <strong>Verification #<?php echo $verification['id']; ?></strong>
                                        <span class="verification-date"><?php echo date('d M Y H:i', strtotime($verification['created_at'])); ?></span>
                                        <span class="verification-status badge badge-<?php 
                                            echo $verification['status'] === 'pending' ? 'warning' : 
                                                ($verification['status'] === 'approved' ? 'success' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($verification['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="verification-user">
                                        <div class="user-info">
                                            <span class="label">User:</span>
                                            <span class="value"><?php echo htmlspecialchars($verification['user_name']); ?></span>
                                            <span class="user-email"><?php echo htmlspecialchars($verification['user_email']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="verification-docs">
                                    <div class="doc-item">
                                        <h4>ID Document:</h4>
                                        <div class="doc-preview">
                                            <img src="<?php echo htmlspecialchars($verification['id_document']); ?>" alt="ID Document">
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($verification['selfie_document'])): ?>
                                    <div class="doc-item">
                                        <h4>Selfie with ID:</h4>
                                        <div class="doc-preview">
                                            <img src="<?php echo htmlspecialchars($verification['selfie_document']); ?>" alt="Selfie with ID">
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($verification['additional_info'])): ?>
                                <div class="verification-additional">
                                    <h4>Additional Information:</h4>
                                    <div class="content-box">
                                        <?php echo nl2br(htmlspecialchars($verification['additional_info'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($verification['status'] !== 'pending'): ?>
                                <div class="verification-result">
                                    <h4>Verification Result:</h4>
                                    <div class="content-box">
                                        <p>
                                            <strong>Status:</strong> 
                                            <?php echo ucfirst($verification['status']); ?>
                                        </p>
                                        <?php if (!empty($verification['admin_notes'])): ?>
                                        <p>
                                            <strong>Admin Notes:</strong> 
                                            <?php echo nl2br(htmlspecialchars($verification['admin_notes'])); ?>
                                        </p>
                                        <?php endif; ?>
                                        <p>
                                            <strong>Verified at:</strong> 
                                            <?php echo date('d M Y H:i', strtotime($verification['verified_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($verification['status'] === 'pending'): ?>
                                <div class="verification-actions">
                                    <button type="button" class="btn btn-success verify-btn" data-id="<?php echo $verification['id']; ?>" data-status="approved">Approve Verification
                                    </button>
                                    <button type="button" class="btn btn-danger verify-btn" data-id="<?php echo $verification['id']; ?>" data-status="rejected">
                                        Reject Verification
                                    </button>
                                </div>
                                
                                <!-- Verification form (hidden by default) -->
                                <div class="verification-form" id="verification-form-<?php echo $verification['id']; ?>" style="display: none;">
                                    <form method="post">
                                        <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">
                                        <input type="hidden" name="verification_status" id="status-<?php echo $verification['id']; ?>">
                                        
                                        <div class="form-group">
                                            <label for="notes-<?php echo $verification['id']; ?>">Admin Notes:</label>
                                            <textarea id="notes-<?php echo $verification['id']; ?>" name="verification_notes" rows="4"></textarea>
                                        </div>
                                        
                                        <div class="form-buttons">
                                            <button type="submit" name="verify_identity" class="btn">Submit Verification</button>
                                            <button type="button" class="btn btn-outline cancel-verification">Cancel</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Moderation Logs Tab -->
                <div class="tab-content <?php echo $active_tab === 'logs' ? 'active' : ''; ?>" id="logs-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Moderation Logs</h2>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>Details</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get moderation logs
                                $logs_sql = "SELECT ml.*, 
                                           a.name as admin_name,
                                           u.name as target_name
                                           FROM moderation_logs ml
                                           JOIN users a ON ml.admin_id = a.id
                                           LEFT JOIN users u ON ml.target_user_id = u.id
                                           ORDER BY ml.created_at DESC
                                           LIMIT 100";
                                $logs_result = $conn->query($logs_sql);
                                
                                if ($logs_result && $logs_result->num_rows > 0) {
                                    while ($log = $logs_result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $log['id'] . '</td>';
                                        echo '<td>' . htmlspecialchars($log['admin_name']) . '</td>';
                                        echo '<td>' . htmlspecialchars($log['action']) . '</td>';
                                        echo '<td>' . ($log['target_name'] ? htmlspecialchars($log['target_name']) : 'N/A') . '</td>';
                                        echo '<td>' . htmlspecialchars($log['details']) . '</td>';
                                        echo '<td>' . date('d M Y H:i', strtotime($log['created_at'])) . '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="6" class="text-center">No moderation logs found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
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
        
        // Show/hide action form for reports
        document.querySelectorAll('.take-action-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-id');
                document.getElementById('action-form-' + reportId).style.display = 'block';
                this.style.display = 'none';
            });
        });
        
        // Cancel action
        document.querySelectorAll('.cancel-action').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.action-form');
                form.style.display = 'none';
                form.closest('.report-item').querySelector('.take-action-btn').style.display = 'inline-block';
            });
        });
        
        // Show/hide verification form
        document.querySelectorAll('.verify-btn').forEach(button => {
            button.addEventListener('click', function() {
                const verificationId = this.getAttribute('data-id');
                const status = this.getAttribute('data-status');
                document.getElementById('verification-form-' + verificationId).style.display = 'block';
                document.getElementById('status-' + verificationId).value = status;
                document.querySelector('.verification-actions').style.display = 'none';
            });
        });
        
        // Cancel verification
        document.querySelectorAll('.cancel-verification').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.verification-form');
                form.style.display = 'none';
                form.closest('.verification-item').querySelector('.verification-actions').style.display = 'flex';
            });
        });
    </script>
</body>
</html>