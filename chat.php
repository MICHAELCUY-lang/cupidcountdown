<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: cupid.php');
    exit();
}

// Check if session_id is provided
if (!isset($_GET['session_id'])) {
    header('Location: dashboard.php?page=chat');
    exit();
}

$session_id = $_GET['session_id'];

// Database connection
$servername = "localhost";
$username = "u287442801_cupid";
$password = "Cupid1234!";
$dbname = "u287442801_cupid";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get chat session data
$session_sql = "SELECT cs.*, 
                u1.name as user1_name, 
                u2.name as user2_name,
                CASE WHEN cs.user1_id = ? THEN u2.name ELSE u1.name END as partner_name,
                CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END as partner_id,
                p.profile_pic
                FROM chat_sessions cs
                JOIN users u1 ON cs.user1_id = u1.id
                JOIN users u2 ON cs.user2_id = u2.id
                LEFT JOIN profiles p ON (CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END) = p.user_id
                WHERE cs.id = ? AND (cs.user1_id = ? OR cs.user2_id = ?)";
$session_stmt = $conn->prepare($session_sql);
$session_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $session_id, $user_id, $user_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();

if ($session_result->num_rows === 0) {
    header('Location: dashboard.php?page=chat');
    exit();
}

$chat_session = $session_result->fetch_assoc();
$partner_id = $chat_session['partner_id'];

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = $_POST['message'];
    
    if (!empty(trim($message))) {
        $insert_sql = "INSERT INTO chat_messages (session_id, sender_id, message) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $session_id, $user_id, $message);
        
        if ($insert_stmt->execute()) {
            // Get the partner ID for notification
            $partner_id = ($chat_session['user1_id'] == $user_id) ? $chat_session['user2_id'] : $chat_session['user1_id'];
            
            // Send notification
            include_once 'notifications.php';
            include_once 'notification_integration.php';
            notifyNewMessage($conn, $partner_id, $user_id, $message, $session_id);
            
            // Successfully sent, refresh the page to display new message
            header("Location: chat.php?session_id=" . $session_id);
            exit();
        } else {
            $error_message = "Error sending message: " . $conn->error;
        }
    }
}

// Get chat messages
$messages_sql = "SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC";
$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("i", $session_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();
$messages = [];
while ($row = $messages_result->fetch_assoc()) {
    $messages[] = $row;
}

// Update last seen
$update_seen_sql = "UPDATE chat_sessions SET 
                    user1_last_seen = CASE WHEN user1_id = ? THEN NOW() ELSE user1_last_seen END,
                    user2_last_seen = CASE WHEN user2_id = ? THEN NOW() ELSE user2_last_seen END
                    WHERE id = ?";
$update_seen_stmt = $conn->prepare($update_seen_sql);
$update_seen_stmt->bind_param("iii", $user_id, $user_id, $session_id);
$update_seen_stmt->execute();

// Check if the message is deleted
function isMessageDeleted($conn, $message_id) {
    $sql = "SELECT COUNT(*) as count FROM deleted_messages WHERE message_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['count'] > 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Cupid</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/notification-styles.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff4b6e;
            --secondary: #ffd9e0;
            --dark: #333333;
            --light: #ffffff;
            --accent: #ff8fa3;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f9f9;
            color: var(--dark);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        header {
            background-color: var(--light);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #e63e5c;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--light);
        }
        
        .chat-container {
            padding-top: 80px;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .chat-header {
            background-color: var(--light);
            border-radius: 10px 10px 0 0;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            z-index: 1;
        }
        
        .chat-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            background-color: #f0f0f0;
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .chat-avatar a {
            display: block;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        
        .chat-avatar a:hover img {
            opacity: 0.9;
            transform: scale(1.03);
            transition: all 0.3s ease;
        }
        
        .chat-profile {
            flex: 1;
        }
        
        .chat-profile h2 {
            font-size: 20px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .chat-profile p {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .chat-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: #f9f9f9;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            display: flex;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message-content {
            max-width: 70%;
            padding: 15px;
            border-radius: 18px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .sent .message-content {
            background-color: var(--primary);
            color: var(--light);
            border-bottom-right-radius: 5px;
        }
        
        .received .message-content {
            background-color: #f0f0f0;
            color: var(--dark);
            border-bottom-left-radius: 5px;
        }
        
        .message-text {
            line-height: 1.5;
            margin-bottom: 5px;
            word-break: break-word;
        }
        
        .message-time {
            font-size: 12px;
            opacity: 0.8;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .chat-input {
            padding: 15px 20px;
            background-color: var(--light);
            border-top: 1px solid #eee;
        }
        
        .chat-form {
            display: flex;
            gap: 10px;
        }
        
        .chat-form input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
            outline: none;
            transition: border 0.3s;
        }
        
        .chat-form input:focus {
            border-color: var(--primary);
        }
        
        .chat-form button {
            padding: 12px 20px;
            background-color: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-form button:hover {
            background-color: #e63e5c;
            transform: scale(1.05);
        }
        
        .chat-form button i {
            margin-left: 5px;
        }
        
        .inline-form {
            display: inline;
        }
        
        .approved-badge {
            color: #0caa0c;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            margin-left: 5px;
        }
        
        .approval-required-message {
            text-align: center;
            padding: 40px 0;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #666;
        }
        
        .approval-required-message i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 15px;
            opacity: 0.6;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Dropdown Menu */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }
        
        .dropdown-content a i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        .dropdown-content a:hover {
            background-color: #f1f1f1;
            color: var(--primary);
        }
        
        .show {
            display: block;
        }
        
        .message-delete {
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
            margin-left: 8px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .received .message-delete {
            color: rgba(0, 0, 0, 0.5);
        }
        
        .message:hover .message-delete {
            opacity: 1;
        }
        
        .message.deleted .message-content {
            background-color: #f0f0f0 !important;
            color: #999 !important;
            font-style: italic;
        }
        
        .message-deleted {
            padding: 10px;
            font-size: 14px;
            color: #999;
            text-align: center;
            margin: 10px 0;
            font-style: italic;
        }
        
        .empty-chat {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #999;
        }
        
        .empty-chat i {
            font-size: 50px;
            margin-bottom: 20px;
            color: var(--secondary);
        }
        
        /* Dark mode support */
        [data-theme="dark"] {
            --light: #1e1e1e;
            --dark: #f5f5f5;
            --secondary: #662d39;
            --accent: #ff8fa3;
            --card-bg: #2a2a2a;
            --border-color: #444444;
        }
        
        [data-theme="dark"] .received .message-content {
            background-color: #2a2a2a;
            color: #f5f5f5;
        }
        
        [data-theme="dark"] .dropdown-content {
            background-color: #1e1e1e;
        }
        
        [data-theme="dark"] .dropdown-content a {
            color: #f5f5f5;
        }
        
        [data-theme="dark"] .dropdown-content a:hover {
            background-color: #2a2a2a;
        }
        
        @media (max-width: 767px) {
            .message-content {
                max-width: 85%;
            }
            
            .chat-header {
                flex-wrap: wrap;
            }
            
            .chat-actions {
                margin-top: 10px;
                width: 100%;
                justify-content: flex-end;
            }
            
            .dropdown-content {
                right: 0;
                left: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="cupid.php" class="logo">
                    <i class="fas fa-heart"></i> Cupid
                </a>
                <nav>
                    <ul>
                        <li class="notification-container">
                            <div id="notification-button" class="notification-bell">
                                <i class="fas fa-bell"></i>
                                <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
                            </div>
                            <div id="notification-panel" class="notification-panel">
                                <div class="notification-header">
                                    <h3>Notifications</h3>
                                    <div class="notification-actions">
                                        <span id="mark-all-read" class="notification-clear">Mark all as read</span>
                                        <span id="clear-all-notifications" class="notification-clear">Clear all</span>
                                    </div>
                                </div>
                                <div id="notifications-list" class="notification-list">
                                    <div class="empty-notifications">No notifications yet</div>
                                </div>
                                <div class="notification-settings">
                                    <h4>Settings</h4>
                                    <div class="setting-item">
                                        <span class="setting-label">Notification Sound</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="notification-sound-toggle" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="setting-item">
                                        <span class="setting-label">Browser Notifications</span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" id="browser-notifications-toggle" checked>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li>
                            <a href="dashboard.php?page=chat" class="btn btn-outline">Kembali ke Chat</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Chat Section -->
    <section class="chat-container">
        <div class="container">
            <!-- Chat Header -->
            <div class="chat-header">
                <div class="chat-avatar">
                    <a href="<?php echo 'view_profile.php?id=' . $partner_id; ?>" title="Lihat profil">
                        <img src="<?php echo !empty($chat_session['profile_pic']) ? htmlspecialchars($chat_session['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="<?php echo htmlspecialchars($chat_session['partner_name']); ?>">
                    </a>
                </div>
                <div class="chat-profile">
                    <h2>
                        <?php echo htmlspecialchars($chat_session['partner_name']); ?>
                    </h2>
                </div>
                <div class="chat-actions">
                    <!-- View profile button - Direct link to profile -->
                    <a href="view_profile.php?id=<?php echo $partner_id; ?>" class="btn btn-sm" title="Lihat Profil">
                        <i class="fas fa-user"></i> Profil
                    </a>
                    
                    <!-- Delete Chat button -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline" onclick="toggleDeleteMenu()" title="Opsi Lainnya">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div id="deleteMenu" class="dropdown-content">
                            <a href="javascript:void(0)" onclick="confirmDeleteChat('for_me')">
                                <i class="fas fa-trash"></i> Hapus Chat Untukku
                            </a>
                            <a href="javascript:void(0)" onclick="confirmDeleteChat('for_everyone')">
                                <i class="fas fa-trash-alt"></i> Hapus Chat Untuk Semua
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Chat Main Area -->
            <div class="chat-main">
                <!-- Chat Messages -->
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($messages)): ?>
                        <div class="empty-chat">
                            <i class="fas fa-comments"></i>
                            <p>Belum ada pesan. Mulai percakapan sekarang!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <?php $is_deleted = isMessageDeleted($conn, $message['id']); ?>
                            <?php if ($is_deleted): ?>
                                <div class="message-deleted">
                                    <i class="fas fa-ban"></i> Pesan telah dihapus
                                </div>
                            <?php else: ?>
                                <div class="message <?php echo $message['sender_id'] === $user_id ? 'sent' : 'received'; ?>" id="message-<?php echo $message['id']; ?>">
                                    <div class="message-content">
                                        <div class="message-text">
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                            <?php if ($message['sender_id'] === $user_id): ?>
                                                <span class="message-delete" onclick="confirmDeleteMessage(<?php echo $message['id']; ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input -->
                <div class="chat-input">
                    <form method="post" class="chat-form">
                        <input type="text" name="message" placeholder="Ketik pesan..." required autofocus>
                        <button type="submit" name="send_message">Kirim <i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Toast Notification for alerts -->
    <div id="toast-notification" class="toast-notification">
        <div class="toast-icon">
            <i class="fas fa-bell"></i>
        </div>
        <div id="toast-message" class="toast-message"></div>
        <div class="toast-close">
            <i class="fas fa-times"></i>
        </div>
    </div>
        
    <script>
        // Auto scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Execute on page load
        window.onload = function() {
            scrollToBottom();
        };
        
        // Toggle dropdown menu
        function toggleDeleteMenu() {
            document.getElementById("deleteMenu").classList.toggle("show");
        }
        
        // Close the dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.matches('.btn-outline') && !event.target.matches('.fa-ellipsis-v')) {
                const dropdowns = document.getElementsByClassName("dropdown-content");
                for (let i = 0; i < dropdowns.length; i++) {
                    const openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
        }
        
        // Confirm delete message
        function confirmDeleteMessage(messageId) {
            if (confirm('Apakah Anda yakin ingin menghapus pesan ini?')) {
                // Create form data
                const formData = new FormData();
                formData.append('message_id', messageId);
                
                // Send delete request
                fetch('delete_message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Replace the message with "deleted" text
                        const messageEl = document.getElementById('message-' + messageId);
                        if (messageEl) {
                            messageEl.innerHTML = '<div class="message-deleted"><i class="fas fa-ban"></i> Pesan telah dihapus</div>';
                        }
                    } else {
                        showToast('Gagal menghapus pesan: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Terjadi kesalahan saat menghapus pesan');
                });
            }
        }
        
        // Confirm delete chat
        function confirmDeleteChat(deleteType) {
            let confirmMessage = '';
            
            if (deleteType === 'for_me') {
                confirmMessage = 'Hapus chat ini hanya untuk Anda? Chat ini akan dihapus dari daftar chat Anda.';
            } else if (deleteType === 'for_everyone') {
                confirmMessage = 'Hapus chat ini untuk semua? Semua pesan akan dihapus untuk Anda dan lawan bicara.';
            }
            
            if (confirm(confirmMessage)) {
                deleteChat(deleteType);
            }
        }
        
        // Delete chat function
        function deleteChat(deleteType) {
            const sessionId = <?php echo $session_id; ?>;
            
            // Create form data
            const formData = new FormData();
            formData.append('session_id', sessionId);
            formData.append('delete_type', deleteType);
            
            // Send delete request
            fetch('delete_chat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Chat berhasil dihapus');
                    // Redirect to dashboard after 1 second
                    setTimeout(function() {
                        window.location.href = 'dashboard.php?page=chat';
                    }, 1000);
                } else {
                    showToast('Gagal menghapus chat: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Terjadi kesalahan saat menghapus chat');
            });
        }
        
        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('toast-notification');
            const toastMessage = document.getElementById('toast-message');
            
            toastMessage.textContent = message;
            toast.classList.add('show');
            
            // Auto hide after 3 seconds
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Close toast when clicking the close button
        document.querySelector('.toast-close').addEventListener('click', function() {
            document.getElementById('toast-notification').classList.remove('show');
        });
        
        // Notification bell functionality
        document.addEventListener('DOMContentLoaded', function() {
            const notificationButton = document.getElementById('notification-button');
            const notificationPanel = document.getElementById('notification-panel');
            const notificationBadge = document.getElementById('notification-badge');
            
            // Toggle notification panel
            if (notificationButton) {
                notificationButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationPanel.classList.toggle('show');
                    
                    // If showing panel, mark notifications as read
                    if (notificationPanel.classList.contains('show')) {
                        loadNotifications();
                    }
                });
            }
            
            // Close panel when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationPanel && notificationPanel.classList.contains('show') && 
                    !notificationPanel.contains(e.target) && 
                    e.target !== notificationButton) {
                    notificationPanel.classList.remove('show');
                }
            });
            
            // Mark all as read
            const markAllRead = document.getElementById('mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function() {
                    fetch('notification_api.php?action=mark_all_read')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                loadNotifications();
                                updateUnreadCount(0);
                            }
                        })
                        .catch(error => console.error('Error marking all as read:', error));
                });
            }
            
            // Clear all notifications
            const clearAllBtn = document.getElementById('clear-all-notifications');
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to clear all notifications?')) {
                        fetch('notification_api.php?action=clear_all')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.getElementById('notifications-list').innerHTML = 
                                        '<div class="empty-notifications">No notifications yet</div>';
                                    updateUnreadCount(0);
                                }
                            })
                            .catch(error => console.error('Error clearing notifications:', error));
                    }
                });
            }
            
            // Load notifications 
            function loadNotifications() {
                const notificationsList = document.getElementById('notifications-list');
                if (notificationsList) {
                    notificationsList.innerHTML = '<div class="notification-loading">Loading notifications...</div>';
                    
                    fetch('notification_api.php?action=get_notifications')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.notifications.length === 0) {
                                    notificationsList.innerHTML = '<div class="empty-notifications">No notifications yet</div>';
                                } else {
                                    notificationsList.innerHTML = '';
                                    data.notifications.forEach(notification => {
                                        const notifItem = document.createElement('div');
                                        notifItem.className = 'notification-item' + (notification.is_read ? '' : ' unread');
                                        notifItem.dataset.id = notification.id;
                                        
                                        // Create notification content
                                        notifItem.innerHTML = `
                                            <div class="notification-avatar">
                                                <div class="avatar-placeholder">
                                                    <i class="${getNotificationIcon(notification.type)}"></i>
                                                </div>
                                            </div>
                                            <div class="notification-content">
                                                <div class="notification-message">${notification.message}</div>
                                                <div class="notification-time">${formatTimestamp(notification.created_at)}</div>
                                            </div>
                                        `;
                                        
                                        // Add click event to mark as read
                                        notifItem.addEventListener('click', function() {
                                            markAsRead(notification.id);
                                            // Handle click action based on notification type
                                            if (notification.action_link) {
                                                window.location.href = notification.action_link;
                                            }
                                        });
                                        
                                        notificationsList.appendChild(notifItem);
                                    });
                                }
                                
                                // Update unread count
                                updateUnreadCount(data.unread_count);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading notifications:', error);
                            notificationsList.innerHTML = '<div class="notification-loading">Error loading notifications.</div>';
                        });
                }
            }
            
            // Mark a notification as read
            function markAsRead(notificationId) {
                fetch(`notification_api.php?action=mark_read&id=${notificationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI
                            const notifItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                            if (notifItem) {
                                notifItem.classList.remove('unread');
                            }
                            
                            // Update unread count
                            updateUnreadCount(data.unread_count);
                        }
                    })
                    .catch(error => console.error('Error marking as read:', error));
            }
            
            // Update the badge with unread count
            function updateUnreadCount(count) {
                if (count > 0) {
                    notificationBadge.textContent = count;
                    notificationBadge.style.display = 'flex';
                    notificationBadge.classList.add('new');
                } else {
                    notificationBadge.style.display = 'none';
                    notificationBadge.classList.remove('new');
                }
            }
            
            // Get appropriate icon for notification type
            function getNotificationIcon(type) {
                switch (type) {
                    case 'message':
                        return 'fas fa-envelope';
                    case 'like':
                        return 'fas fa-heart';
                    case 'match':
                        return 'fas fa-heart';
                    case 'system':
                        return 'fas fa-bell';
                    default:
                        return 'fas fa-bell';
                }
            }
            
            // Format timestamp to relative time
            function formatTimestamp(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const diffTime = Math.abs(now - date);
                const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 7) {
                    return date.toLocaleDateString();
                } else if (diffDays > 0) {
                    return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
                } else {
                    const diffHours = Math.floor(diffTime / (1000 * 60 * 60));
                    if (diffHours > 0) {
                        return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
                    } else {
                        const diffMinutes = Math.floor(diffTime / (1000 * 60));
                        if (diffMinutes > 0) {
                            return diffMinutes + ' minute' + (diffMinutes > 1 ? 's' : '') + ' ago';
                        } else {
                            return 'Just now';
                        }
                    }
                }
            }
            
            // Check for new notifications periodically
            function checkForNewNotifications() {
                fetch('notification_api.php?action=get_unread_count')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateUnreadCount(data.count);
                        }
                    })
                    .catch(error => console.error('Error checking notifications:', error));
            }
            
            // Load notifications on page load
            loadNotifications();
            
            // Check for new notifications every 30 seconds
            setInterval(checkForNewNotifications, 30000);
        });
    </script>
    
    <script src="assets/js/notifications.js"></script>
</body>
</html>