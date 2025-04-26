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
    <?php include 'admin_header_includes.php'; ?>
    <style>
        .moderation-nav {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .moderation-nav .nav-item {
            padding: 10px 20px;
            font-weight: 500;
            color: #666;
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .moderation-nav .nav-item.active {
            color: #ff4b6e;
            background: #fff0f3;
        }
        
        .moderation-nav .nav-item:hover {
            color: #ff4b6e;
            background: #fff0f3;
        }
        
        .moderation-nav .nav-item.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #ff4b6e;
        }
        
        .report-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .report-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }
        
        .report-pending {
            border-left: 4px solid #f59e0b;
        }
        
        .report-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .report-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .report-meta strong {
            font-size: 16px;
            color: #333;
        }
        
        .report-date {
            color: #666;
            font-size: 14px;
        }
        
        .report-users {
            display: flex;
            gap: 24px;
            font-size: 14px;
        }
        
        .report-users .label {
            color: #666;
            margin-right: 4px;
        }
        
        .report-users .value {
            color: #333;
            font-weight: 500;
        }
        
        .report-body {
            padding: 20px;
        }
        
        .report-info {
            display: flex;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .content-preview {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f0f0f0;
        }
        
        .content-preview h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }
        
        .additional-info {
            margin-bottom: 20px;
        }
        
        .additional-info h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }
        
        .report-actions {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }
        
        .action-form {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #f0f0f0;
        }
        
        .verification-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .verification-pending {
            border-left: 4px solid #3b82f6;
        }
        
        .verification-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .verification-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .verification-meta strong {
            font-size: 16px;
            color: #333;
        }
        
        .verification-date {
            color: #666;
            font-size: 14px;
        }
        
        .verification-docs {
            display: flex;
            gap: 20px;
            padding: 20px;
            flex-wrap: wrap;
        }
        
        .doc-item {
            flex: 1;
            min-width: 300px;
        }
        
        .doc-item h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }
        
        .doc-preview {
            border: 1px solid #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .doc-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .verification-actions {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
        }
        
        .verification-form {
            padding: 20px;
            background: #fff;
            border-top: 1px solid #f0f0f0;
        }
        
        .moderation-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .moderation-table th {
            text-align: left;
            padding: 16px;
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .moderation-table td {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            color: #666;
        }
        
        .moderation-table tr:hover {
            background: #fafafa;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .badge-success {
            background: #dcfce7;
            color: #10b981;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .badge-info {
            background: #e0f2fe;
            color: #0ea5e9;
        }
        
        .badge-secondary {
            background: #f3f4f6;
            color: #6b7280;
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
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
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
        
        .form-group {
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .moderation-nav {
                flex-wrap: wrap;
            }
            
            .report-users {
                flex-direction: column;
                gap: 8px;
            }
            
            .verification-docs {
                flex-direction: column;
            }
            
            .doc-item {
                min-width: 100%;
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
                    <h1><i class="fas fa-shield-alt"></i> Moderation Tools</h1>
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
                
                <!-- Tabs -->
                <div class="moderation-nav">
                    <a href="?tab=reports" class="nav-item <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
                        <i class="fas fa-flag"></i> Content Reports
                    </a>
                    <a href="?tab=verifications" class="nav-item <?php echo $active_tab === 'verifications' ? 'active' : ''; ?>">
                        <i class="fas fa-user-check"></i> Identity Verifications
                    </a>
                    <a href="?tab=logs" class="nav-item <?php echo $active_tab === 'logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Moderation Logs
                    </a>
                </div>
                
                <!-- Content Reports Tab -->
                <div class="tab-content <?php echo $active_tab === 'reports' ? 'active' : ''; ?>" id="reports-tab">
                    <?php if (empty($reports)): ?>
                    <div class="empty-state">
                        <i class="fas fa-flag"></i>
                        <p>No content reports found.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <div class="report-card <?php echo $report['status'] === 'pending' ? 'report-pending' : ''; ?>">
                        <div class="report-header">
                            <div class="report-meta">
                                <strong>Report #<?php echo $report['id']; ?></strong>
                                <span class="report-date"><?php echo date('d M Y H:i', strtotime($report['created_at'])); ?></span>
                                <span class="badge badge-<?php 
                                    echo $report['status'] === 'pending' ? 'warning' : 
                                        ($report['status'] === 'actioned' ? 'success' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($report['status']); ?>
                                </span>
                            </div>
                            
                            <div class="report-users">
                                <div>
                                    <span class="label">Reported by:</span>
                                    <span class="value"><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                </div>
                                <div>
                                    <span class="label">Content by:</span>
                                    <span class="value"><?php echo htmlspecialchars($report['target_user_name']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-body">
                            <div class="report-info">
                                <span class="badge badge-info">
                                    <i class="fas fa-file-alt"></i> <?php echo ucfirst($report['content_type']); ?>
                                </span>
                                <span class="badge badge-danger">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo ucfirst($report['reason']); ?>
                                </span>
                            </div>
                            
                            <div class="content-preview">
                                <h4>Reported Content:</h4>
                                <div><?php echo nl2br(htmlspecialchars($report['content'])); ?></div>
                            </div>
                            
                            <?php if (!empty($report['additional_info'])): ?>
                            <div class="additional-info">
                                <h4>Additional Information:</h4>
                                <div><?php echo nl2br(htmlspecialchars($report['additional_info'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($report['status'] === 'actioned'): ?>
                            <div class="content-preview">
                                <h4>Action Taken:</h4>
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
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($report['status'] === 'pending'): ?>
                        <div class="report-actions">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" name="dismiss_report" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Dismiss Report
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-primary take-action-btn" data-id="<?php echo $report['id']; ?>">
                                <i class="fas fa-gavel"></i> Take Action
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
                                    <select id="action-<?php echo $report['id']; ?>" name="action_taken" class="form-control" required>
                                        <option value="">Choose an action...</option>
                                        <option value="delete_content">Delete Content</option>
                                        <option value="block_user">Block User</option>
                                        <option value="warning">Issue Warning</option>
                                        <option value="no_action">No Action Required</option>
                                    </select>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="take_action" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Submit Action
                                    </button>
                                    <button type="button" class="btn btn-outline cancel-action">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Identity Verifications Tab -->
                <div class="tab-content <?php echo $active_tab === 'verifications' ? 'active' : ''; ?>" id="verifications-tab">
                    <?php if (empty($verifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-check"></i>
                        <p>No identity verifications found.</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($verifications as $verification): ?>
                    <div class="verification-card <?php echo $verification['status'] === 'pending' ? 'verification-pending' : ''; ?>">
                        <div class="verification-header">
                            <div class="verification-meta">
                                <strong>Verification #<?php echo $verification['id']; ?></strong>
                                <span class="verification-date"><?php echo date('d M Y H:i', strtotime($verification['created_at'])); ?></span>
                                <span class="badge badge-<?php 
                                    echo $verification['status'] === 'pending' ? 'warning' : 
                                        ($verification['status'] === 'approved' ? 'success' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($verification['status']); ?>
                                </span>
                            </div>
                            
                            <div class="report-users">
                                <div>
                                    <span class="label">User:</span>
                                    <span class="value"><?php echo htmlspecialchars($verification['user_name']); ?></span>
                                </div>
                                <div>
                                    <span class="label">Email:</span>
                                    <span class="value"><?php echo htmlspecialchars($verification['user_email']); ?></span>
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
                        <div class="report-body">
                            <div class="additional-info">
                                <h4>Additional Information:</h4>
                                <div><?php echo nl2br(htmlspecialchars($verification['additional_info'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($verification['status'] !== 'pending'): ?>
                        <div class="report-body">
                            <div class="content-preview">
                                <h4>Verification Result:</h4>
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
                            <button type="button" class="btn btn-success verify-btn" data-id="<?php echo $verification['id']; ?>" data-status="approved">
                                <i class="fas fa-check"></i> Approve Verification
                            </button>
                            <button type="button" class="btn btn-danger verify-btn" data-id="<?php echo $verification['id']; ?>" data-status="rejected">
                                <i class="fas fa-times"></i> Reject Verification
                            </button>
                        </div>
                        
                        <!-- Verification form (hidden by default) -->
                        <div class="verification-form" id="verification-form-<?php echo $verification['id']; ?>" style="display: none;">
                            <form method="post">
                                <input type="hidden" name="verification_id" value="<?php echo $verification['id']; ?>">
                                <input type="hidden" name="verification_status" id="status-<?php echo $verification['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="notes-<?php echo $verification['id']; ?>">Admin Notes:</label>
                                    <textarea id="notes-<?php echo $verification['id']; ?>" 
                                              name="verification_notes" 
                                              class="form-control" 
                                              rows="4"></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="verify_identity" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Submit Verification
                                    </button>
                                    <button type="button" class="btn btn-outline cancel-verification">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Moderation Logs Tab -->
                <div class="tab-content <?php echo $active_tab === 'logs' ? 'active' : ''; ?>" id="logs-tab">
                    <div class="report-card">
                        <div class="card-header">
                            <h2><i class="fas fa-history"></i> Moderation Logs</h2>
                        </div>
                        
                        <table class="moderation-table">
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
        // Tab content visibility
        document.addEventListener('DOMContentLoaded', function() {
            const currentTab = '<?php echo $active_tab; ?>';
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            const activeTab = document.getElementById(currentTab + '-tab');
            if (activeTab) {
                activeTab.style.display = 'block';
            }
        });
        
        // Show/hide action form for reports
        document.querySelectorAll('.take-action-btn').forEach(button => {
            button.addEventListener('click', function() {
                const reportId = this.getAttribute('data-id');
                const form = document.getElementById('action-form-' + reportId);
                
                // Hide all other forms
                document.querySelectorAll('.action-form').forEach(f => {
                    if (f !== form) {
                        f.style.display = 'none';
                    }
                });
                
                // Toggle current form
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            });
        });
        
        // Cancel action
        document.querySelectorAll('.cancel-action').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.action-form');
                form.style.display = 'none';
            });
        });
        
        // Show/hide verification form
        document.querySelectorAll('.verify-btn').forEach(button => {
            button.addEventListener('click', function() {
                const verificationId = this.getAttribute('data-id');
                const status = this.getAttribute('data-status');
                const form = document.getElementById('verification-form-' + verificationId);
                
                // Set status
                document.getElementById('status-' + verificationId).value = status;
                
                // Hide all other forms
                document.querySelectorAll('.verification-form').forEach(f => {
                    if (f !== form) {
                        f.style.display = 'none';
                    }
                });
                
                // Show form
                form.style.display = 'block';
                
                // Hide action buttons
                this.closest('.verification-actions').style.display = 'none';
            });
        });
        
        // Cancel verification
        document.querySelectorAll('.cancel-verification').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.verification-form');
                form.style.display = 'none';
                
                // Show action buttons
                form.closest('.verification-card').querySelector('.verification-actions').style.display = 'flex';
            });
        });
    </script>
</body>
</html>