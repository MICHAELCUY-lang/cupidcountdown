<?php
// admin_functions.php


function requireAdmin() {
    global $conn;
    
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    $user_id = $_SESSION['user_id'];
    $admin_sql = "SELECT is_admin FROM users WHERE id = ?";
    $admin_stmt = $conn->prepare($admin_sql);
    $admin_stmt->bind_param("i", $user_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $user = $admin_result->fetch_assoc();
    
    if (!$user || $user['is_admin'] != 1) {
        redirect('dashboard.php');
        exit();
    }
}

/**
 * Update user's last activity timestamp
 */
function updateUserActivity($userId, $conn) {
    $sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

/**
 * Get users who are currently online (active in the last X minutes)
 */
function getOnlineUsers($conn, $minutes = 1) {
    $sql = "SELECT id, name, email, last_activity, 
            (SELECT profile_pic FROM profiles WHERE user_id = users.id) as profile_pic
            FROM users 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY last_activity DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $minutes);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $onlineUsers = [];
    while ($row = $result->fetch_assoc()) {
        $onlineUsers[] = $row;
    }
    
    return $onlineUsers;
}

/**
 * Get dashboard statistics
 */
function getAdminDashboardStats($conn) {
    // Total users
    $users_sql = "SELECT COUNT(*) as count FROM users";
    $result = $conn->query($users_sql);
    $total_users = $result->fetch_assoc()['count'];
    
    // Active users (users active in the last 30 days)
    $active_sql = "SELECT COUNT(DISTINCT sender_id) as count FROM chat_messages 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $result = $conn->query($active_sql);
    $active_users = $result->fetch_assoc()['count'];
    
    // New users today
    $new_users_sql = "SELECT COUNT(*) as count FROM users 
                     WHERE DATE(created_at) = CURDATE()";
    $result = $conn->query($new_users_sql);
    $new_users_today = $result->fetch_assoc()['count'];
    
    // Total revenue
    $revenue_sql = "SELECT SUM(amount) as total FROM profile_reveal_payments 
                   WHERE status = 'completed'";
    $result = $conn->query($revenue_sql);
    $total_revenue = $result->fetch_assoc()['total'] ?? 0;
    
    // Recent activity
    $activity_sql = "SELECT a.*, u.name as user_name FROM (
                    SELECT id AS user_id, 'Created Account' as action, created_at FROM users
                    UNION ALL
                    SELECT sender_id AS user_id, 'Sent Message' as action, created_at FROM chat_messages
                    UNION ALL
                    SELECT user_id, 'Made Payment' as action, created_at FROM profile_reveal_payments
                    ) as a
                    JOIN users u ON a.user_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT 10";
    $result = $conn->query($activity_sql);
    $recent_activity = [];
    while ($row = $result->fetch_assoc()) {
        $recent_activity[] = $row;
    }
    
    return [
        'total_users' => $total_users,
        'active_users' => $active_users,
        'new_users_today' => $new_users_today,
        'total_revenue' => $total_revenue,
        'recent_activity' => $recent_activity
    ];
}

/**
 * Get all users with filtering options
 */
function getUsers($conn, $filters = []) {
    $sql = "SELECT u.*, p.major, p.profile_pic,
            (SELECT MAX(created_at) FROM chat_messages WHERE sender_id = u.id) as last_activity
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($filters['search'])) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%" . $filters['search'] . "%";
        $params[] = "%" . $filters['search'] . "%";
        $types .= "ss";
    }
    
    if (!empty($filters['status'])) {
        if ($filters['status'] == 'active') {
            $sql .= " AND (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) > 0";
        } elseif ($filters['status'] == 'inactive') {
            $sql .= " AND (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) = 0";
        }
    }
    
    // Add ordering
    $sql .= " ORDER BY u.created_at DESC";
    
    // Add pagination
    $page = $filters['page'] ?? 1;
    $per_page = $filters['per_page'] ?? 20;
    $offset = ($page - 1) * $per_page;
    
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $per_page;
    $types .= "ii";
    
    // Prepare and execute
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Count total users for pagination
 */
function countUsers($conn, $filters = []) {
    $sql = "SELECT COUNT(*) as count FROM users u WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply filters
    if (!empty($filters['search'])) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%" . $filters['search'] . "%";
        $params[] = "%" . $filters['search'] . "%";
        $types .= "ss";
    }
    
    if (!empty($filters['status'])) {
        if ($filters['status'] == 'active') {
            $sql .= " AND (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) > 0";
        } elseif ($filters['status'] == 'inactive') {
            $sql .= " AND (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) = 0";
        }
    }
    
    // Prepare and execute
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}