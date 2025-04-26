<?php
// admin_sidebar.php

// Check if current page is active
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : '';
}
?>

<div class="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-heart-pulse"></i>
            <span class="logo-text">Cupid</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- Sidebar User Info -->
    <div class="sidebar-user">
        <div class="user-avatar">
            <?php if (isset($_SESSION['user_profile_pic']) && !empty($_SESSION['user_profile_pic'])): ?>
                <img src="<?php echo htmlspecialchars($_SESSION['user_profile_pic']); ?>" alt="Admin">
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?php echo substr($_SESSION['user_name'] ?? 'A', 0, 1); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
            <div class="user-role">Administrator</div>
        </div>
    </div>
    
    <!-- Search Box -->
    <div class="sidebar-search">
        <input type="text" placeholder="Search menu..." id="sidebarSearch">
        <i class="fas fa-search"></i>
    </div>
    
    <!-- Sidebar Menu -->
    <ul class="sidebar-menu">
        <li class="menu-section">
            <span>Main Navigation</span>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_dashboard.php'); ?>">
            <a href="admin_dashboard.php" data-tooltip="Dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
                <span class="menu-badge">New</span>
            </a>
        </li>
        
        <li class="menu-section">
            <span>User Management</span>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_users.php'); ?>">
            <a href="admin_users.php" data-tooltip="User Management">
                <i class="fas fa-users"></i>
                <span>All Users</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_online_users.php'); ?>">
            <a href="admin_online_users.php" data-tooltip="Online Users">
                <i class="fas fa-user-clock"></i>
                <span>Online Users</span>
                <span class="menu-badge status-online">LIVE</span>
            </a>
        </li>
        
        <li class="menu-section">
            <span>Content & Reports</span>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_content.php'); ?>">
            <a href="admin_content.php" data-tooltip="Content Management">
                <i class="fas fa-edit"></i>
                <span>Content</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_feedback.php'); ?>">
            <a href="admin_feedback.php" data-tooltip="Feedback">
                <i class="fas fa-comment-alt"></i>
                <span>Feedback</span>
                <span class="menu-badge notification">3</span>
            </a>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_moderation.php'); ?>">
            <a href="admin_moderation.php" data-tooltip="Moderation">
                <i class="fas fa-shield-alt"></i>
                <span>Moderation</span>
            </a>
        </li>
        
        <li class="menu-section">
            <span>System</span>
        </li>
        
        <li class="menu-item <?php echo isActive('admin_settings.php'); ?>">
            <a href="admin_settings.php" data-tooltip="Settings">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="dashboard.php" data-tooltip="View Site">
                <i class="fas fa-external-link-alt"></i>
                <span>View Site</span>
            </a>
        </li>
        
        <li class="menu-item">
            <a href="logout.php" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="storage-info">
            <div class="storage-label">Storage Used</div>
            <div class="storage-progress">
                <div class="storage-bar" style="width: 65%"></div>
            </div>
            <div class="storage-text">6.4 GB of 10 GB</div>
        </div>
        <div class="version-info">
            Cupid Admin v1.0.0
        </div>
    </div>
</div>

<style>
/* Modern Sidebar Styles */
.sidebar {
    width: 280px;
    height: 100vh;
    background: white;
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.05);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar-header {
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f0f0f0;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 24px;
    font-weight: 700;
    color: #ff4b6e;
}

.sidebar-logo i {
    font-size: 28px;
    background: linear-gradient(45deg, #ff4b6e, #ff6584);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.logo-text {
    background: linear-gradient(45deg, #ff4b6e, #ff6584);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .logo-text {
    opacity: 0;
    width: 0;
}

.sidebar-toggle {
    background: none;
    border: none;
    font-size: 20px;
    color: #666;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.sidebar-toggle:hover {
    background: #f8f9fa;
    color: #ff4b6e;
}

.sidebar-user {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid #f0f0f0;
    background: #f8f9fa;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, #ff4b6e, #ff6584);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
}

.user-info {
    overflow: hidden;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .user-info {
    opacity: 0;
    width: 0;
}

.user-name {
    font-weight: 600;
    color: #333;
    font-size: 15px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 13px;
    color: #666;
}

.sidebar-search {
    padding: 15px 20px;
    position: relative;
}

.sidebar-search input {
    width: 100%;
    padding: 10px 35px 10px 15px;
    border: 2px solid #f0f0f0;
    border-radius: 10px;
    font-size: 14px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.sidebar-search input:focus {
    outline: none;
    border-color: #ff4b6e;
    background: white;
}

.sidebar-search i {
    position: absolute;
    right: 35px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.sidebar.collapsed .sidebar-search {
    display: none;
}

.sidebar-menu {
    flex: 1;
    overflow-y: auto;
    padding: 10px 0;
    scrollbar-width: thin;
    scrollbar-color: #ddd #f8f9fa;
}

.sidebar-menu::-webkit-scrollbar {
    width: 5px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: #ddd;
    border-radius: 5px;
}

.menu-section {
    padding: 10px 20px 5px;
    font-size: 12px;
    font-weight: 600;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 10px;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .menu-section span {
    display: none;
}

.menu-item {
    margin: 4px 12px;
}

.menu-item a {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #666;
    text-decoration: none;
    border-radius: 10px;
    transition: all 0.3s ease;
    position: relative;
    gap: 12px;
}

.menu-item a:hover {
    background: #fff0f3;
    color: #ff4b6e;
    transform: translateX(5px);
}

.menu-item.active a {
    background: linear-gradient(45deg, #ff4b6e, #ff6584);
    color: white;
    box-shadow: 0 5px 15px rgba(255, 75, 110, 0.3);
}

.menu-item i {
    font-size: 18px;
    width: 24px;
    text-align: center;
}

.menu-item span:not(.menu-badge) {
    font-size: 15px;
    font-weight: 500;
    transition: opacity 0.3s ease;
}

.sidebar.collapsed .menu-item span:not(.menu-badge) {
    display: none;
}

.menu-badge {
    margin-left: auto;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.menu-badge.notification {
    background: #ff4b6e;
    color: white;
}

.menu-badge.status-online {
    background: #10b981;
    color: white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid #f0f0f0;
    background: #f8f9fa;
}

.storage-info {
    margin-bottom: 15px;
}

.storage-label {
    font-size: 13px;
    color: #666;
    margin-bottom: 8px;
}

.storage-progress {
    height: 6px;
    background: #e0e0e0;
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 5px;
}

.storage-bar {
    height: 100%;
    background: linear-gradient(45deg, #ff4b6e, #ff6584);
    border-radius: 3px;
    transition: width 1s ease;
}

.storage-text {
    font-size: 12px;
    color: #999;
}

.version-info {
    font-size: 12px;
    color: #999;
    text-align: center;
}

.sidebar.collapsed .sidebar-footer {
    padding: 10px;
}

.sidebar.collapsed .storage-info,
.sidebar.collapsed .version-info {
    display: none;
}

/* Responsive Styles */
@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 99;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }
}

/* Tooltips for collapsed sidebar */
.sidebar.collapsed .menu-item a::after {
    content: attr(data-tooltip);
    position: absolute;
    left: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 13px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    white-space: nowrap;
    z-index: 1000;
}

.sidebar.collapsed .menu-item a:hover::after {
    opacity: 1;
    visibility: visible;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .sidebar {
        background: #1a1a1a;
        box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
    }
    
    .sidebar-header,
    .sidebar-user,
    .sidebar-footer {
        border-color: #333;
        background: #222;
    }
    
    .sidebar-search input {
        background: #222;
        border-color: #333;
        color: #fff;
    }
    
    .menu-item a {
        color: #ccc;
    }
    
    .menu-item a:hover {
        background: #333;
    }
    
    .menu-section {
        color: #888;
    }
    
    .user-name,
    .storage-label {
        color: #fff;
    }
    
    .user-role,
    .storage-text,
    .version-info {
        color: #888;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarSearch = document.getElementById('sidebarSearch');
    
    // Make sidebar draggable
    let isDragging = false;
    let currentX;
    let currentY;
    let initialX;
    let initialY;
    let xOffset = 0;
    let yOffset = 0;
    
    // Create drag handle
    const dragHandle = document.createElement('div');
    dragHandle.className = 'sidebar-drag-handle';
    dragHandle.innerHTML = '<i class="fas fa-grip-vertical"></i>';
    sidebar.prepend(dragHandle);
    
    // Load saved position
    const savedPosition = JSON.parse(localStorage.getItem('sidebarPosition'));
    if (savedPosition) {
        xOffset = savedPosition.x;
        yOffset = savedPosition.y;
        setTranslate(xOffset, yOffset, sidebar);
        sidebar.style.position = 'fixed';
    }
    
    // Drag start
    dragHandle.addEventListener('mousedown', dragStart);
    dragHandle.addEventListener('touchstart', dragStart);
    
    document.addEventListener('mousemove', drag);
    document.addEventListener('touchmove', drag);
    
    document.addEventListener('mouseup', dragEnd);
    document.addEventListener('touchend', dragEnd);
    
    function dragStart(e) {
        if (e.type === 'touchstart') {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }
        
        if (e.target === dragHandle || dragHandle.contains(e.target)) {
            isDragging = true;
            sidebar.style.position = 'fixed';
            sidebar.classList.add('dragging');
        }
    }
    
    function drag(e) {
        if (isDragging) {
            e.preventDefault();
            
            if (e.type === 'touchmove') {
                currentX = e.touches[0].clientX - initialX;
                currentY = e.touches[0].clientY - initialY;
            } else {
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
            }
            
            xOffset = currentX;
            yOffset = currentY;
            
            setTranslate(currentX, currentY, sidebar);
        }
    }
    
    function dragEnd(e) {
        initialX = currentX;
        initialY = currentY;
        isDragging = false;
        sidebar.classList.remove('dragging');
        
        // Save position
        localStorage.setItem('sidebarPosition', JSON.stringify({
            x: xOffset,
            y: yOffset
        }));
    }
    
    function setTranslate(xPos, yPos, el) {
        el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
    }
    
    // Reset position button
    const resetButton = document.createElement('button');
    resetButton.className = 'sidebar-reset-position';
    resetButton.innerHTML = '<i class="fas fa-undo"></i>';
    resetButton.title = 'Reset position';
    sidebar.appendChild(resetButton);
    
    resetButton.addEventListener('click', function() {
        xOffset = 0;
        yOffset = 0;
        setTranslate(0, 0, sidebar);
        sidebar.style.position = '';
        localStorage.removeItem('sidebarPosition');
    });
    
    // Toggle sidebar collapse
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });
    
    // Remember collapsed state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    // Sidebar search functionality
    sidebarSearch.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const menuItems = document.querySelectorAll('.menu-item');
        
        menuItems.forEach(item => {
            const menuText = item.textContent.toLowerCase();
            if (menuText.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        
        // Show/hide section titles based on visible items
        const sections = document.querySelectorAll('.menu-section');
        sections.forEach(section => {
            const nextItems = [];
            let nextElement = section.nextElementSibling;
            
            while (nextElement && !nextElement.classList.contains('menu-section')) {
                if (nextElement.classList.contains('menu-item')) {
                    nextItems.push(nextElement);
                }
                nextElement = nextElement.nextElementSibling;
            }
            
            const hasVisibleItems = nextItems.some(item => item.style.display !== 'none');
            section.style.display = hasVisibleItems ? 'block' : 'none';
        });
    });
    
    // Mobile sidebar toggle
    const mobileToggle = document.createElement('button');
    mobileToggle.className = 'mobile-sidebar-toggle';
    mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.body.appendChild(mobileToggle);
    
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    mobileToggle.addEventListener('click', function() {
        sidebar.classList.add('active');
        overlay.classList.add('active');
    });
    
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });
    
    // Add ripple effect to menu items
    document.querySelectorAll('.menu-item a').forEach(item => {
        item.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            this.appendChild(ripple);
            
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});

// Add necessary mobile toggle styles
const mobileStyles = document.createElement('style');
mobileStyles.textContent = `
    .mobile-sidebar-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 101;
        background: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        cursor: pointer;
        color: #333;
        font-size: 20px;
    }
    
    @media (max-width: 1024px) {
        .mobile-sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .admin-container {
            padding-left: 0 !important;
        }
    }
    
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        transform: scale(0);
        animation: ripple 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .sidebar-drag-handle {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 30px;
        background: linear-gradient(45deg, #ff4b6e, #ff6584);
        cursor: move;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        opacity: 0.6;
        transition: opacity 0.3s;
    }
    
    .sidebar-drag-handle:hover {
        opacity: 1;
    }
    
    .sidebar.dragging {
        user-select: none;
        opacity: 0.9;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }
    
    .sidebar-reset-position {
        position: absolute;
        bottom: 10px;
        right: 10px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #ff4b6e;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.7;
        transition: all 0.3s;
        z-index: 100;
    }
    
    .sidebar-reset-position:hover {
        opacity: 1;
        transform: scale(1.1);
    }
    
    .sidebar-reset-position i {
        font-size: 14px;
    }
`;
document.head.appendChild(mobileStyles);
</script>