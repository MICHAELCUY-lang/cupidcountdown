<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: cupid.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cupid_db";

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

// Get all users for crush selection
$users_sql = "SELECT u.id, u.name, u.email, p.profile_pic, p.major, p.bio 
              FROM users u
              LEFT JOIN profiles p ON u.id = p.user_id
              WHERE u.id != ?";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param("i", $user_id);
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Handle menfess submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_menfess'])) {
    $crush_id = $_POST['crush_id'];
    $content = $_POST['content'];
    
    // Check if input is valid
    if (empty($crush_id) || empty($content)) {
        $message = '<div class="alert alert-danger">Please fill in all fields.</div>';
    } else {
        // Insert menfess
        $insert_sql = "INSERT INTO menfess (sender_id, receiver_id, message, is_anonymous) VALUES (?, ?, ?, 1)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $user_id, $crush_id, $content);
        
        if ($insert_stmt->execute()) {
            $message = '<div class="alert alert-success">Your anonymous menfess has been sent successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error sending menfess: ' . $conn->error . '</div>';
        }
    }
}

// Get sent menfess
$sent_sql = "SELECT m.*, u.name as receiver_name, 
             (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = m.id) as like_count,
             (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = m.id AND user_id = m.receiver_id) as is_liked
             FROM menfess m
             JOIN users u ON m.receiver_id = u.id
             WHERE m.sender_id = ?
             ORDER BY m.created_at DESC";
$sent_stmt = $conn->prepare($sent_sql);
$sent_stmt->bind_param("i", $user_id);
$sent_stmt->execute();
$sent_result = $sent_stmt->get_result();
$sent_menfess = [];
while ($row = $sent_result->fetch_assoc()) {
    $sent_menfess[] = $row;
}

// Get received menfess
$received_sql = "SELECT m.*, 
                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = m.id) as like_count,
                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = m.id AND user_id = ?) as user_liked
                FROM menfess m
                WHERE m.receiver_id = ?
                ORDER BY m.created_at DESC";
$received_stmt = $conn->prepare($received_sql);
$received_stmt->bind_param("ii", $user_id, $user_id);
$received_stmt->execute();
$received_result = $received_stmt->get_result();
$received_menfess = [];
while ($row = $received_result->fetch_assoc()) {
    $received_menfess[] = $row;
}

// Handle menfess like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_menfess'])) {
    $menfess_id = $_POST['menfess_id'];
    
    // Check if already liked
    $check_sql = "SELECT * FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $menfess_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Unlike
        $unlike_sql = "DELETE FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
        $unlike_stmt = $conn->prepare($unlike_sql);
        $unlike_stmt->bind_param("ii", $user_id, $menfess_id);
        
        if ($unlike_stmt->execute()) {
            // Refresh the page to update
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        // Like
        $like_sql = "INSERT INTO menfess_likes (user_id, menfess_id) VALUES (?, ?)";
        $like_stmt = $conn->prepare($like_sql);
        $like_stmt->bind_param("ii", $user_id, $menfess_id);
        
        if ($like_stmt->execute()) {
            // Check if both sender and receiver have liked the menfess
            $check_mutual_sql = "SELECT 
                                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = ? AND user_id = (SELECT receiver_id FROM menfess WHERE id = ?)) as receiver_liked,
                                (SELECT COUNT(*) FROM menfess_likes WHERE menfess_id = ? AND user_id = (SELECT sender_id FROM menfess WHERE id = ?)) as sender_liked";
            $check_mutual_stmt = $conn->prepare($check_mutual_sql);
            $check_mutual_stmt->bind_param("iiii", $menfess_id, $menfess_id, $menfess_id, $menfess_id);
            $check_mutual_stmt->execute();
            $check_mutual_result = $check_mutual_stmt->get_result();
            $mutual = $check_mutual_result->fetch_assoc();
            
            if ($mutual['receiver_liked'] > 0 && $mutual['sender_liked'] > 0) {
                // It's a match! Reveal identities
                $reveal_sql = "UPDATE menfess SET is_revealed = 1 WHERE id = ?";
                $reveal_stmt = $conn->prepare($reveal_sql);
                $reveal_stmt->bind_param("i", $menfess_id);
                $reveal_stmt->execute();
                
                // Create a match notification (could be implemented further)
            }
            
            // Refresh the page to update
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }
}

// Get matches (mutual menfess)
$matches_sql = "SELECT DISTINCT
                CASE 
                    WHEN m.sender_id = ? THEN m.receiver_id
                    ELSE m.sender_id
                END as matched_user_id,
                u.name as matched_user_name,
                p.profile_pic,
                m.id as menfess_id,
                m.message
                FROM menfess m
                JOIN menfess_likes ml1 ON m.id = ml1.menfess_id
                JOIN menfess_likes ml2 ON m.id = ml2.menfess_id
                JOIN users u ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = u.id
                LEFT JOIN profiles p ON u.id = p.user_id
                WHERE 
                    (m.sender_id = ? OR m.receiver_id = ?)
                    AND ml1.user_id = m.sender_id
                    AND ml2.user_id = m.receiver_id
                    AND m.is_revealed = 1";
$matches_stmt = $conn->prepare($matches_sql);
$matches_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$matches_stmt->execute();
$matches_result = $matches_stmt->get_result();
$matches = [];
while ($row = $matches_result->fetch_assoc()) {
    $matches[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anonymous Crush Menfess - Cupid</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--light);
        }
        
        .menfess-container {
            padding-top: 100px;
            padding-bottom: 50px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: #666;
            font-size: 16px;
        }
        
        .card {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .card-header {
            margin-bottom: 20px;
        }
        
        .card-header h2 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 150px;
            resize: vertical;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-color: var(--primary);
            color: var(--primary);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .menfess-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .menfess-card {
            background-color: #f8f8f8;
            border-radius: 10px;
            padding: 20px;
            position: relative;
        }
        
        .menfess-card.sent {
            background-color: var(--secondary);
        }
        
        .menfess-card.received {
            background-color: #f0f0f0;
        }
        
        .menfess-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .menfess-from {
            font-weight: 500;
        }
        
        .menfess-time {
            font-size: 14px;
            color: #777;
        }
        
        .menfess-content {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .menfess-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        
        .menfess-like {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: #777;
        }
        
        .menfess-like.liked {
            color: var(--primary);
        }
        
        .menfess-like.liked i {
            color: var(--primary);
        }
        
        .match-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .match-card {
            background-color: var(--light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .match-image {
            height: 200px;
            overflow: hidden;
        }
        
        .match-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .match-info {
            padding: 20px;
        }
        
        .match-name {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .match-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .match-actions {
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #666;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
        
        @media (max-width: 767px) {
            .tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .tab {
                padding: 15px;
                border-bottom: 1px solid #ddd;
            }
            
            .tab.active {
                border-bottom: 1px solid var(--primary);
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
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li>
                            <a href="dashboard.php?page=menfess" class="btn btn-outline">Kembali</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Menfess Section -->
    <section class="menfess-container">
        <div class="container">
            <div class="page-header">
                <h1>Anonymous Crush Menfess</h1>
                <p>Kirim pesan anonymous ke orang yang kamu sukai. Jika keduanya saling suka, identitas akan terungkap!</p>
            </div>
            
            <?php echo $message; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2>Kirim Menfess</h2>
                </div>
                <form method="post">
                    <div class="form-group">
                        <label for="crush_id">Pilih Crush</label>
                        <select id="crush_id" name="crush_id" required>
                            <option value="">-- Pilih Crush --</option>
                            <?php foreach ($users as $user_item): ?>
                                <option value="<?php echo $user_item['id']; ?>"><?php echo htmlspecialchars($user_item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="content">Pesan</label>
                        <textarea id="content" name="content" placeholder="Tulis pesan anonymous ke crush kamu..." required></textarea>
                    </div>
                    <button type="submit" name="submit_menfess" class="btn">Kirim Menfess</button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>Menfess Manager</h2>
                </div>
                <div class="tabs">
                    <div class="tab active" data-tab="received">Diterima</div>
                    <div class="tab" data-tab="sent">Dikirim</div>
                    <div class="tab" data-tab="matches">Matches</div>
                </div>
                
                <div class="tab-content active" id="received">
                    <?php if (empty($received_menfess)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Belum Ada Menfess</h3>
                        <p>Belum ada yang mengirimkan menfess kepadamu.</p>
                    </div>
                    <?php else: ?>
                    <div class="menfess-list">
                        <?php foreach ($received_menfess as $menfess): ?>
                        <div class="menfess-card received">
                            <div class="menfess-header">
                                <div class="menfess-from">
                                    <?php if ($menfess['is_revealed']): ?>
                                        <i class="fas fa-user"></i> Dari: <?php echo htmlspecialchars(getSenderName($conn, $menfess['sender_id'])); ?>
                                    <?php else: ?>
                                        <i class="fas fa-mask"></i> Anonymous Menfess
                                    <?php endif; ?>
                                </div>
                                <div class="menfess-time">
                                    <?php echo date('d M Y H:i', strtotime($menfess['created_at'])); ?>
                                </div>
                            </div>
                            <div class="menfess-content">
                                <?php echo nl2br(htmlspecialchars($menfess['message'])); ?>
                            </div>
                            <div class="menfess-actions">
                                <form method="post">
                                    <input type="hidden" name="menfess_id" value="<?php echo $menfess['id']; ?>">
                                    <button type="submit" name="like_menfess" class="menfess-like <?php echo $menfess['user_liked'] ? 'liked' : ''; ?>" style="background: none; border: none;">
                                        <i class="<?php echo $menfess['user_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <?php echo $menfess['user_liked'] ? 'Liked' : 'Like'; ?>
                                    </button>
                                </form>
                                <div>
                                    <?php if ($menfess['is_revealed']): ?>
                                        <span class="match-badge" style="color: var(--primary);"><i class="fas fa-check-circle"></i> Match!</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content" id="sent">
                    <?php if (empty($sent_menfess)): ?>
                    <div class="empty-state">
                        <i class="fas fa-paper-plane"></i>
                        <h3>Belum Mengirim Menfess</h3>
                        <p>Kamu belum mengirimkan menfess ke siapapun.</p>
                    </div>
                    <?php else: ?>
                    <div class="menfess-list">
                        <?php foreach ($sent_menfess as $menfess): ?>
                        <div class="menfess-card sent">
                            <div class="menfess-header">
                                <div class="menfess-from">
                                    <i class="fas fa-paper-plane"></i> Kepada: <?php echo htmlspecialchars($menfess['receiver_name']); ?>
                                </div>
                                <div class="menfess-time">
                                    <?php echo date('d M Y H:i', strtotime($menfess['created_at'])); ?>
                                </div>
                            </div>
                            <div class="menfess-content">
                                <?php echo nl2br(htmlspecialchars($menfess['message'])); ?>
                            </div>
                            <div class="menfess-actions">
                                <div>
                                    <i class="<?php echo $menfess['is_liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                    <?php echo $menfess['is_liked'] ? 'Disukai' : 'Belum disukai'; ?>
                                </div>
                                <div>
                                    <?php if ($menfess['is_revealed']): ?>
                                        <span class="match-badge" style="color: var(--primary);"><i class="fas fa-check-circle"></i> Match!</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content" id="matches">
                    <?php if (empty($matches)): ?>
                    <div class="empty-state">
                        <i class="fas fa-heart-broken"></i>
                        <h3>Belum Ada Match</h3>
                        <p>Kamu belum memiliki match. Kirim menfess dan like pesan untuk menemukan match!</p>
                    </div>
                    <?php else: ?>
                    <div class="match-grid">
                        <?php foreach ($matches as $match): ?>
                        <div class="match-card">
                            <div class="match-image">
                                <img src="<?php echo !empty($match['profile_pic']) ? htmlspecialchars($match['profile_pic']) : '/api/placeholder/250/200'; ?>" alt="<?php echo htmlspecialchars($match['matched_user_name']); ?>">
                            </div>
                            <div class="match-info">
                                <h3 class="match-name"><?php echo htmlspecialchars($match['matched_user_name']); ?></h3>
                                <p class="match-message">"<?php echo htmlspecialchars(substr($match['message'], 0, 100)) . (strlen($match['message']) > 100 ? '...' : ''); ?>"</p>
                                <div class="match-actions">
                                    <a href="view_profile.php?id=<?php echo $match['matched_user_id']; ?>" class="btn btn-outline">Lihat Profil</a>
                                    <a href="start_chat.php?user_id=<?php echo $match['matched_user_id']; ?>" class="btn">Chat</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.getAttribute('data-tab');
                
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Show target content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(target).classList.add('active');
            });
        });
    </script>
</body>
</html>

<?php
// Helper function to get sender name
function getSenderName($conn, $sender_id) {
    $sql = "SELECT name FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    return $user ? $user['name'] : 'Unknown User';
}
?>