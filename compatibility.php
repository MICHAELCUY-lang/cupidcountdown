<?php
// Aktifkan error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get profile data
$profile_sql = "SELECT * FROM profiles WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();

// Tambahkan kemampuan untuk reset tes
$reset_test = false;
if (isset($_GET['reset']) && $_GET['reset'] == 'true') {
    // Hapus hasil tes sebelumnya jika ada
    $delete_sql = "DELETE FROM compatibility_results WHERE user_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $reset_test = true;
}

// Check if compatibility test already taken
$test_taken_sql = "SELECT * FROM compatibility_results WHERE user_id = ?";
$test_taken_stmt = $conn->prepare($test_taken_sql);
$test_taken_stmt->bind_param("i", $user_id);
$test_taken_stmt->execute();
$test_taken_result = $test_taken_stmt->get_result();
$test_taken = ($test_taken_result->num_rows > 0);
$test_results = $test_taken ? $test_taken_result->fetch_assoc() : null;

// Periksa apakah tabel compatibility_questions ada dan memiliki data
$table_check = $conn->query("SHOW TABLES LIKE 'compatibility_questions'");
if ($table_check->num_rows == 0) {
    die("Tabel compatibility_questions tidak ditemukan. Silakan buat tabel terlebih dahulu.");
}

// Get compatibility questions dengan error handling
$questions = [];
$questions_sql = "SELECT * FROM compatibility_questions";
$questions_result = $conn->query($questions_sql);
if ($questions_result) {
    while ($row = $questions_result->fetch_assoc()) {
        $questions[] = $row;
    }
}

// Jika tidak ada pertanyaan di database, tambahkan pesan
if (empty($questions)) {
    echo "<div style='text-align:center; padding: 20px; background-color: #f8d7da; color: #721c24; margin: 20px;'>
            <p>Tidak ada pertanyaan kompatibilitas yang ditemukan di database. Admin perlu menambahkan pertanyaan.</p>
          </div>";
}

// Handle test submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $answers = [];
    $personality_score = 0;
    
    // Pastikan ada pertanyaan sebelum memproses
    if (!empty($questions)) {
        foreach ($questions as $question) {
            $q_id = $question['id'];
            if (isset($_POST['q_'.$q_id])) {
                $answer = $_POST['q_'.$q_id];
                $answers[$q_id] = $answer;
                
                // Calculate personality score based on answers
                $personality_score += intval($answer);
            }
        }
        
        // Normalize personality score to a 0-100 scale
        $max_possible = count($questions) * 5; // assuming 5 is max score per question
        $personality_score = ($personality_score / $max_possible) * 100;
        
        // Get major and interests from profile
        $major = $profile['major'] ?? '';
        $interests = $profile['interests'] ?? '';
        
        // Check if already taken test
        if ($test_taken) {
            // Update test
            $update_sql = "UPDATE compatibility_results SET 
                          personality_score = ?, 
                          major = ?, 
                          interests = ?, 
                          answers = ?
                          WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $answers_json = json_encode($answers);
            $update_stmt->bind_param("dsssi", $personality_score, $major, $interests, $answers_json, $user_id);
            
            if ($update_stmt->execute()) {
                $message = '<div class="alert alert-success">Compatibility test updated! Finding new matches...</div>';
                // Refresh test results
                $test_taken_stmt->execute();
                $test_taken_result = $test_taken_stmt->get_result();
                $test_results = $test_taken_result->fetch_assoc();
            } else {
                $message = '<div class="alert alert-danger">Error updating test results: ' . $conn->error . '</div>';
            }
        } else {
            // Save new test
            $insert_sql = "INSERT INTO compatibility_results (user_id, personality_score, major, interests, answers) 
                          VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $answers_json = json_encode($answers);
            $insert_stmt->bind_param("idsss", $user_id, $personality_score, $major, $interests, $answers_json);
            
            if ($insert_stmt->execute()) {
                $message = '<div class="alert alert-success">Compatibility test completed! Finding matches...</div>';
                $test_taken = true;
                // Refresh test results
                $test_taken_stmt->execute();
                $test_taken_result = $test_taken_stmt->get_result();
                $test_results = $test_taken_result->fetch_assoc();
            } else {
                $message = '<div class="alert alert-danger">Error saving test results: ' . $conn->error . '</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">Tidak dapat mengambil tes: Tidak ada pertanyaan yang tersedia.</div>';
    }
}

// Get compatible matches if test taken
$compatible_matches = [];
if ($test_taken) {
    try {
        $matches_sql = "SELECT u.id, u.name, p.profile_pic, p.bio, p.major, p.interests,
               ABS(IFNULL(cr.personality_score, 0) - ?) as personality_diff,
               CASE WHEN cr.major = ? THEN 30 ELSE 0 END as major_match,
               CASE WHEN LOWER(IFNULL(cr.interests, '')) LIKE CONCAT('%', LOWER(IFNULL(?, '')), '%') THEN 40 ELSE 0 END as interests_match,
               (100 - ABS(IFNULL(cr.personality_score, 0) - ?) * 0.3 + 
               CASE WHEN cr.major = ? THEN 30 ELSE 0 END + 
               CASE WHEN LOWER(IFNULL(cr.interests, '')) LIKE CONCAT('%', LOWER(IFNULL(?, '')), '%') THEN 40 ELSE 0 END) as compatibility_score
               FROM compatibility_results cr
               JOIN users u ON cr.user_id = u.id
               LEFT JOIN profiles p ON u.id = p.user_id
               WHERE cr.user_id != ?
               ORDER BY compatibility_score DESC
               LIMIT 15";
        $matches_stmt = $conn->prepare($matches_sql);
        
        // Get user's test data
        $personality_score = $test_results['personality_score'];
        $user_major = $test_results['major'] ?? '';
        $user_interests = $test_results['interests'] ?? '';
        
        $matches_stmt->bind_param("dsdsssi", $personality_score, $user_major, $user_interests, 
                               $personality_score, $user_major, $user_interests, $user_id);
        $matches_stmt->execute();
        $matches_result = $matches_stmt->get_result();
        
        while ($row = $matches_result->fetch_assoc()) {
            $compatible_matches[] = $row;
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compatibility Test - Cupid</title>
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
        
        .compatibility-container {
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
        
        .question {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .question:last-child {
            border-bottom: none;
        }
        
        .question h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .option {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .option:hover {
            background-color: var(--secondary);
            border-color: var(--primary);
        }
        
        .option.selected {
            background-color: var(--secondary);
            border-color: var(--primary);
        }
        
        .option input {
            margin-right: 10px;
        }
        
        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .match-score {
            display: inline-block;
            padding: 5px 10px;
            background-color: var(--primary);
            color: white;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .match-details {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .match-interests {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 15px;
        }
        
        .interest-tag {
            display: inline-block;
            padding: 5px 10px;
            background-color: var(--secondary);
            color: var(--primary);
            border-radius: 15px;
            font-size: 12px;
        }
        
        .match-bio {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .match-actions {
            display: flex;
            gap: 10px;
        }
        
        .score-details {
            display: flex;
            justify-content: space-between;
            padding: 10px 15px;
            background-color: #f8f8f8;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .score-item {
            text-align: center;
        }
        
        .score-value {
            font-size: 18px;
            font-weight: 500;
            color: var(--primary);
        }
        
        .score-label {
            font-size: 12px;
            color: #666;
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
            .matches-grid {
                grid-template-columns: 1fr;
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
                            <a href="dashboard.php?page=compatibility" class="btn btn-outline">Kembali</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Compatibility Section -->
    <section class="compatibility-container">
        <div class="container">
            <div class="page-header">
                <h1>Compatibility Test</h1>
                <p>Ikuti tes kecocokan untuk menemukan pasangan yang cocok berdasarkan kepribadian, jurusan, dan minat.</p>
            </div>
            
            <?php echo $message; ?>
            
            <?php if ($reset_test || !$test_taken): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Tes Kecocokan</h2>
                </div>
                <p>Jawab pertanyaan berikut dengan jujur untuk mendapatkan hasil yang paling akurat.</p>
                <?php if (empty($questions)): ?>
                    <div class="alert alert-danger">
                        Tidak ada pertanyaan kompatibilitas yang tersedia. Silakan hubungi admin.
                    </div>
                <?php else: ?>
                <form id="compatibility-form" method="post">
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="question">
                        <h3><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                        <div class="options">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="option">
                                <input type="radio" name="q_<?php echo $question['id']; ?>" value="<?php echo $i; ?>" required>
                                <?php echo htmlspecialchars($question['option_' . $i]); ?>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="submit_test" class="btn">Lihat Hasil</button>
                </form>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h2>Hasil Tes Kecocokan</h2>
                </div>
                <p>Berdasarkan jawaban dan profil Anda, kami telah menemukan orang-orang yang cocok dengan Anda.</p>
                
                <div class="score-details">
                    <div class="score-item">
                        <div class="score-value"><?php echo round($test_results['personality_score']); ?></div>
                        <div class="score-label">Skor Kepribadian</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?php echo !empty($test_results['major']) ? htmlspecialchars($test_results['major']) : 'Tidak ada'; ?></div>
                        <div class="score-label">Jurusan</div>
                    </div>
                    <div class="score-item">
                        <div class="score-value"><?php echo count($compatible_matches); ?></div>
                        <div class="score-label">Kecocokan Ditemukan</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Pasangan Yang Cocok</h3>
                    <a href="compatibility.php?reset=true" class="btn btn-outline">Ambil Tes Ulang</a>
                </div>
                
                <?php if (empty($compatible_matches)): ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Belum Ada Kecocokan</h3>
                    <p>Kami belum menemukan kecocokan berdasarkan hasil tes Anda. Silakan coba lagi nanti.</p>
                </div>
                <?php else: ?>
                <div class="matches-grid">
                    <?php foreach ($compatible_matches as $match): ?>
                    <div class="match-card">
                        <div class="match-image">
                            <img src="<?php echo !empty($match['profile_pic']) ? htmlspecialchars($match['profile_pic']) : '/assets/images/user_profile.png'; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>">
                        </div>
                        <div class="match-info">
                            <div class="match-name">
                                <span><?php echo htmlspecialchars($match['name']); ?></span>
                                <span class="match-score"><?php echo round($match['compatibility_score']); ?>%</span>
                            </div>
                            <div class="match-details">
                                <?php echo !empty($match['major']) ? htmlspecialchars($match['major']) : 'Jurusan tidak diketahui'; ?>
                            </div>
                            
                            <?php if (!empty($match['interests'])): ?>
                            <div class="match-interests">
                                <?php 
                                $interests = explode(',', $match['interests']);
                                foreach (array_slice($interests, 0, 3) as $interest): 
                                ?>
                                <span class="interest-tag"><?php echo htmlspecialchars(trim($interest)); ?></span>
                                <?php endforeach; ?>
                                
                                <?php if (count($interests) > 3): ?>
                                <span class="interest-tag">+<?php echo count($interests) - 3; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="match-bio">
                                <?php echo !empty($match['bio']) ? nl2br(htmlspecialchars($match['bio'])) : 'Belum ada bio.'; ?>
                            </div>
                            
                            <div class="match-actions">
                                <a href="view_profile.php?id=<?php echo $match['id']; ?>" class="btn btn-outline">Lihat Profil</a>
                                <a href="start_chat.php?user_id=<?php echo $match['id']; ?>" class="btn">Chat</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Tombol Reset Tes -->
                <div style="margin-top: 30px; text-align: center;">
                    <a href="compatibility.php?reset=true" class="btn" style="background-color: #dc3545; color: white;">Reset Tes & Mulai Ulang</a>
                </div>
                
            </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // Make radio options more user-friendly
        document.querySelectorAll('.option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Update visual selection
                const questionDiv = this.closest('.question');
                questionDiv.querySelectorAll('.option').forEach(op => {
                    op.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>