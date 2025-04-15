<div class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="admin_dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="admin_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> User Management
            </a>
        </li>
              <li>
          <a href="admin_online_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_online_users.php' ? 'active' : ''; ?>">
              <i class="fas fa-user-clock"></i> Online Users
          </a>
      </li>
        <li>
            <a href="admin_payments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_payments.php' ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i> Payments
            </a>
        </li>
        <li>
            <a href="admin_content.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_content.php' ? 'active' : ''; ?>">
                <i class="fas fa-edit"></i> Content Management
            </a>
        </li>
        <li>
            <a href="admin_feedback.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_feedback.php' ? 'active' : ''; ?>">
                <i class="fas fa-comment-alt"></i> Feedback
            </a>
        </li>
        <li>
            <a href="admin_moderation.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_moderation.php' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i> Moderation
            </a>
        </li>
        <li>
            <a href="admin_settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
    </ul>
</div>