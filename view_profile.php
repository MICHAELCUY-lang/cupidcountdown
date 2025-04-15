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
$username = "u287442801_cupid";
$password = "Cupid1234!";
$dbname = "u287442801_cupid";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get profile ID from URL
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$from_payment = isset($_GET['from_payment']) ? true : false;
$is_new_purchase = isset($_GET['new']) ? true : false;

// Check if valid profile ID is provided
if ($profile_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Check if this is blind chat profile viewing
$chat_sql = "SELECT cs.* FROM chat_sessions cs
            WHERE cs.is_blind = 1
            AND ((cs.user1_id = ? AND cs.user2_id = ?) OR (cs.user1_id = ? AND cs.user2_id = ?))";
$chat_stmt = $conn->prepare($chat_sql);
$chat_stmt->bind_param("iiii", $user_id, $profile_id, $profile_id, $user_id);
$chat_stmt->execute();
$chat_result = $chat_stmt->get_result();
$is_blind_chat = ($chat_result->num_rows > 0);

// If this is a blind chat, check if user has permission to view this profile
if ($is_blind_chat) {
    $permission_sql = "SELECT * FROM profile_view_permissions WHERE user_id = ? AND target_user_id = ?";
    $permission_stmt = $conn->prepare($permission_sql);
    $permission_stmt->bind_param("ii", $user_id, $profile_id);
    $permission_stmt->execute();
    $permission_result = $permission_stmt->get_result();
    $has_permission = ($permission_result->num_rows > 0);
    
    // If user doesn't have permission, redirect to payment
    if (!$has_permission) {
        // Get chat session ID
        $chat_session = $chat_result->fetch_assoc();
        header('Location: create_profile_payment.php?chat_id=' . $chat_session['id'] . '&partner_id=' . $profile_id);
        exit();
    }
}

// Get user and profile data
$sql = "SELECT u.*, p.* 
        FROM users u 
        LEFT JOIN profiles p ON u.id = p.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit();
}

$profile_data = $result->fetch_assoc();

// Get compatibility score if available
$compatibility_score = null;
$compatibility_sql = "SELECT 
                     (100 - ABS(cr1.personality_score - cr2.personality_score) * 0.3 + 
                     CASE WHEN cr1.major = cr2.major THEN 30 ELSE 0 END + 
                     CASE WHEN cr1.interests LIKE CONCAT('%', cr2.interests, '%') THEN 40 ELSE 0 END) as compatibility_score
                     FROM compatibility_results cr1
                     JOIN compatibility_results cr2 ON cr1.user_id = ? AND cr2.user_id = ?";
$compatibility_stmt = $conn->prepare($compatibility_sql);
$compatibility_stmt->bind_param("ii", $user_id, $profile_id);
$compatibility_stmt->execute();
$compatibility_result = $compatibility_stmt->get_result();

if ($compatibility_result->num_rows > 0) {
    $compatibility_data = $compatibility_result->fetch_assoc();
    $compatibility_score = round($compatibility_data['compatibility_score']);
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil <?php echo htmlspecialchars($profile_data['name']); ?> - Cupid</title>
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
        
        .profile-container {
            padding-top: 100px;
            padding-bottom: 50px;
        }
        
        .profile-header {
            display: flex;
            margin-bottom: 30px;
        }
        
        .profile-image {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .profile-meta {
            display: flex;
            margin-bottom: 20px;
        }
        
        .profile-meta-item {
            margin-right: 20px;
            display: flex;
            align-items: center;
        }
        
        .profile-meta-item i {
            color: var(--primary);
            margin-right: 5px;
        }
        
        .compatibility-score {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-weight: bold;
            font-size: 20px;
            margin-right: 10px;
        }
        
        .profile-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .profile-section {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .profile-section h2 {
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 2px solid var(--secondary);
            padding-bottom: 10px;
        }
        
        .bio {
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .interests {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .interest-tag {
            background-color: var(--secondary);
            color: var(--primary);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
        }
        
        .detail-item i {
            width: 40px;
            color: var(--primary);
        }
        
        .detail-label {
            font-weight: 500;
            margin-right: 10px;
        }
        
        .payment-success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .payment-success-modal {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .success-icon {
            font-size: 60px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .payment-success-modal h2 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .payment-success-modal p {
            margin-bottom: 10px;
            color: #666;
        }
        
        .payment-success-modal .btn {
            margin-top: 20px;
        }
        
        @media (max-width: 767px) {
            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .profile-image {
                margin-right: 0;
                margin-bottom: 20px;
            }
            
            .profile-meta {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .profile-actions {
                justify-content: center;
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
                            <a href="dashboard.php?page=chat" class="btn btn-outline">Kembali ke Chat</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-container">
        <div class="container">
            <div class="profile-header">
                <div class="profile-image">
                    <img src="<?php echo !empty($profile_data['profile_pic']) ? htmlspecialchars($profile_data['profile_pic']) : '/api/placeholder/200/200'; ?>" alt="<?php echo htmlspecialchars($profile_data['name']); ?>">
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($profile_data['name']); ?></h1>
                    
                    <div class="profile-meta">
                        <?php if (!empty($profile_data['major'])): ?>
                        <div class="profile-meta-item">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?php echo htmlspecialchars($profile_data['major']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($profile_data['looking_for'])): ?>
                        <div class="profile-meta-item">
                            <i class="fas fa-search"></i>
                            <span>
                                <?php 
                                $looking_for = $profile_data['looking_for'];
                                if ($looking_for === 'friends') echo 'Mencari Teman';
                                elseif ($looking_for === 'study_partner') echo 'Mencari Partner Belajar';
                                elseif ($looking_for === 'romance') echo 'Mencari Romansa';
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!is_null($compatibility_score)): ?>
                        <div class="profile-meta-item">
                            <div class="compatibility-score"><?php echo $compatibility_score; ?>%</div>
                            <span>Kecocokan dengan Anda</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-actions">
                        <?php if ($profile_id !== $user_id): ?>
                        <a href="start_chat.php?user_id=<?php echo $profile_id; ?>" class="btn">
                            <i class="fas fa-comments"></i> Mulai Chat
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-section">
                <h2>Tentang Saya</h2>
                
                <?php if (!empty($profile_data['bio'])): ?>
                <div class="bio">
                    <?php echo nl2br(htmlspecialchars($profile_data['bio'])); ?>
                </div>
                <?php else: ?>
                <p>Belum ada bio.</p>
                <?php endif; ?>
                
                <?php if (!empty($profile_data['interests'])): ?>
                <h3>Minat & Hobi</h3>
                <div class="interests">
                    <?php 
                    $interests = explode(',', $profile_data['interests']);
                    foreach ($interests as $interest): 
                    ?>
                    <span class="interest-tag"><?php echo htmlspecialchars(trim($interest)); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="details-grid">
                    <?php if (!empty($profile_data['major'])): ?>
                    <div class="detail-item">
                        <i class="fas fa-graduation-cap"></i>
                        <span class="detail-label">Jurusan:</span>
                        <span><?php echo htmlspecialchars($profile_data['major']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($profile_data['looking_for'])): ?>
                    <div class="detail-item">
                        <i class="fas fa-search"></i>
                        <span class="detail-label">Mencari:</span>
                        <span>
                            <?php 
                            $looking_for = $profile_data['looking_for'];
                            if ($looking_for === 'friends') echo 'Teman';
                            elseif ($looking_for === 'study_partner') echo 'Partner Belajar';
                            elseif ($looking_for === 'romance') echo 'Romansa';
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-item">
                        <i class="fas fa-envelope"></i>
                        <span class="detail-label">Email:</span>
                        <span><?php echo htmlspecialchars($profile_data['email']); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!is_null($compatibility_score)): ?>
            <div class="profile-section">
                <h2>Kecocokan</h2>
                
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <div class="compatibility-score" style="width: 80px; height: 80px; font-size: 28px;">
                        <?php echo $compatibility_score; ?>%
                    </div>
                    <div style="margin-left: 20px;">
                        <h3>Tingkat Kecocokan dengan Anda</h3>
                        <p>
                            <?php 
                            if ($compatibility_score >= 80) {
                                echo 'Sangat cocok! Kalian memiliki banyak kesamaan.';
                            } elseif ($compatibility_score >= 60) {
                                echo 'Cukup cocok. Kalian memiliki beberapa kesamaan.';
                            } elseif ($compatibility_score >= 40) {
                                echo 'Kecocokan sedang. Kalian memiliki beberapa perbedaan.';
                            } else {
                                echo 'Kurang cocok. Kalian memiliki banyak perbedaan.';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($from_payment && $is_new_purchase): ?>
    <div class="payment-success-overlay" id="paymentSuccessOverlay">
        <div class="payment-success-modal">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Pembayaran Berhasil!</h2>
            <p>Anda sekarang memiliki akses penuh ke profil ini.</p>
            <p>Terima kasih telah menggunakan Cupid!</p>
            <button onclick="closePaymentSuccessModal()" class="btn">Tutup</button>
        </div>
    </div>

    <script>
        function closePaymentSuccessModal() {
            document.getElementById('paymentSuccessOverlay').style.display = 'none';
        }
    </script>
    <?php endif; ?>
</body>
</html>