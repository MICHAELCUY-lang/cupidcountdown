    <?php
        // Sertakan file konfigurasi
        require_once 'config.php';

        // Set timezone to Jakarta (WIB/GMT+7)
        date_default_timezone_set('Asia/Jakarta');

        // Pastikan user sudah login
        requireLogin();

        // Current page for navigation - DEFINE THIS FIRST
        $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

        // Get user data
        $user_id = $_SESSION['user_id'];
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Get profile data if exists
        $profile_sql = "SELECT * FROM profiles WHERE user_id = ?";
        $profile_stmt = $conn->prepare($profile_sql);
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $profile_result = $profile_stmt->get_result();
        $profile = $profile_result->fetch_assoc();

        // Check if profile is complete
        $profile_complete = ($profile && !empty($profile['interests']) && !empty($profile['bio']));

        // Update user's last activity
        $update_activity_sql = "UPDATE users SET last_activity = NOW() WHERE id = ?";
        $update_activity_stmt = $conn->prepare($update_activity_sql);
        $update_activity_stmt->bind_param("i", $user_id);
        $update_activity_stmt->execute();

        // Handle profile update
        $profile_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
            $bio = $_POST['bio'];
            $interests = $_POST['interests'];
            $looking_for = $_POST['looking_for'];
            $major = $_POST['major'];
            
            // Handle privacy settings
            $searchable = isset($_POST['searchable']) ? 1 : 0;
            $show_online = isset($_POST['show_online']) ? 1 : 0;
            $allow_messages = isset($_POST['allow_messages']) ? 1 : 0;
            $show_major = isset($_POST['show_major']) ? 1 : 0;
            
            // Upload profile picture
            $profile_pic = '';
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_pic']['name'];
                $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($filetype), $allowed)) {
                    $newname = 'profile_' . $user_id . '.' . $filetype;
                    $upload_dir = 'uploads/profiles/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_dir . $newname)) {
                        $profile_pic = $upload_dir . $newname;
                    }
                }
            }
            
            // Add this code in the profile update section
        if (!empty($_POST['name']) && $_POST['name'] !== $user['name']) {
            $update_name_sql = "UPDATE users SET name = ? WHERE id = ?";
            $update_name_stmt = $conn->prepare($update_name_sql);
            $update_name_stmt->bind_param("si", $_POST['name'], $user_id);
            $update_name_stmt->execute();
            
            // Refresh user data
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        }

            if ($profile) {
                // Update existing profile with privacy settings
                $update_sql = "UPDATE profiles SET bio = ?, interests = ?, looking_for = ?, major = ?, 
                            searchable = ?, show_online = ?, allow_messages = ?, show_major = ?";
                $params = "ssssiiii";
                $param_values = [$bio, $interests, $looking_for, $major, 
                                $searchable, $show_online, $allow_messages, $show_major];
                
                if (!empty($profile_pic)) {
                    $update_sql .= ", profile_pic = ?";
                    $params .= "s";
                    $param_values[] = $profile_pic;
                }
                
                $update_sql .= " WHERE user_id = ?";
                $params .= "i";
                $param_values[] = $user_id;
                
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param($params, ...$param_values);
                
                if ($update_stmt->execute()) {
                    $profile_message = 'Profile updated successfully!';
                    // Refresh profile data
                    $profile_stmt->execute();
                    $profile_result = $profile_stmt->get_result();
                    $profile = $profile_result->fetch_assoc();
                    $profile_complete = true;
                } else {
                    $profile_message = 'Error updating profile: ' . $conn->error;
                }
            } else {
                // Create new profile with privacy settings
                $insert_sql = "INSERT INTO profiles (user_id, bio, interests, looking_for, major, profile_pic, 
                            searchable, show_online, allow_messages, show_major) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("issssiiiii", $user_id, $bio, $interests, $looking_for, $major, $profile_pic,
                                    $searchable, $show_online, $allow_messages, $show_major);
                
                if ($insert_stmt->execute()) {
                    $profile_message = 'Profile created successfully!';
                    // Refresh profile data
                    $profile_stmt->execute();
                    $profile_result = $profile_stmt->get_result();
                    $profile = $profile_result->fetch_assoc();
                    $profile_complete = true;
                } else {
                    $profile_message = 'Error creating profile: ' . $conn->error;
                }
            }
        }

        // Get received menfess
        $menfess_sql = "SELECT m.*, 
                        CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as type,
                        CASE 
                            WHEN (SELECT COUNT(*) FROM menfess_likes WHERE user_id = ? AND menfess_id = m.id) > 0 
                            THEN 1 ELSE 0 
                        END as liked,
                        m.is_revealed
                        FROM menfess m
                        WHERE m.receiver_id = ? OR m.sender_id = ?
                        ORDER BY m.created_at DESC";
        $menfess_stmt = $conn->prepare($menfess_sql);
        $menfess_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $menfess_stmt->execute();
        $menfess_result = $menfess_stmt->get_result();
        $menfess_messages = [];
        while ($row = $menfess_result->fetch_assoc()) {
            $menfess_messages[] = $row;
        }

        // Get matches (mutual likes) - simplified version
        $matches_sql = "SELECT DISTINCT u.id, u.name, p.profile_pic, p.bio
                    FROM users u
                    LEFT JOIN profiles p ON u.id = p.user_id
                    JOIN menfess m ON (
                        (m.sender_id = ? AND m.receiver_id = u.id) OR 
                        (m.receiver_id = ? AND m.sender_id = u.id)
                    )
                    WHERE m.is_revealed = 1 
                    AND (u.id != ?)";
        $matches_stmt = $conn->prepare($matches_sql);
        $matches_stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $matches_stmt = $conn->prepare($matches_sql);
        $matches_stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $matches_stmt->execute();
        $matches_result = $matches_stmt->get_result();
        $matches = [];
        while ($row = $matches_result->fetch_assoc()) {
            $matches[] = $row;
        }

        // Handle new menfess submission
        $menfess_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_menfess'])) {
            $crush_id = $_POST['crush_id'];
            $message = $_POST['message'];
            
            $insert_menfess_sql = "INSERT INTO menfess (sender_id, receiver_id, message, is_anonymous) VALUES (?, ?, ?, 1)";
            $insert_menfess_stmt = $conn->prepare($insert_menfess_sql);
            $insert_menfess_stmt->bind_param("iis", $user_id, $crush_id, $message);
            
            if ($insert_menfess_stmt->execute()) {
                $menfess_message = 'Menfess sent successfully!';
                // Refresh menfess data
                $menfess_stmt->execute();
                $menfess_result = $menfess_stmt->get_result();
                $menfess_messages = [];
                while ($row = $menfess_result->fetch_assoc()) {
                    $menfess_messages[] = $row;
                }
            } else {
                $menfess_message = 'Error sending menfess: ' . $conn->error;
            }
        }

        // Handle menfess like
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_menfess'])) {
            $menfess_id = $_POST['menfess_id'];
            
            // Log aktivitas untuk debugging
            error_log("Processing like for menfess ID: " . $menfess_id . " by user ID: " . $user_id);
            
            // Get menfess details first to determine sender and receiver
            $get_menfess_sql = "SELECT sender_id, receiver_id FROM menfess WHERE id = ?";
            $get_menfess_stmt = $conn->prepare($get_menfess_sql);
            $get_menfess_stmt->bind_param("i", $menfess_id);
            $get_menfess_stmt->execute();
            $menfess_details = $get_menfess_stmt->get_result()->fetch_assoc();
            
            $sender_id = $menfess_details['sender_id'];
            $receiver_id = $menfess_details['receiver_id'];
            
            // Check if already liked
            $check_like_sql = "SELECT * FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
            $check_like_stmt = $conn->prepare($check_like_sql);
            $check_like_stmt->bind_param("ii", $user_id, $menfess_id);
            $check_like_stmt->execute();
            $check_like_result = $check_like_stmt->get_result();
            
            if ($check_like_result->num_rows > 0) {
                // Unlike
                error_log("User " . $user_id . " unliking menfess " . $menfess_id);
                $unlike_sql = "DELETE FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
                $unlike_stmt = $conn->prepare($unlike_sql);
                $unlike_stmt->bind_param("ii", $user_id, $menfess_id);
                $unlike_stmt->execute();
                
                // Reset is_revealed jika mutual like tidak lagi terjadi
                // Reset is_revealed jika pengguna yang unlike adalah pengirim atau penerima
                if ($user_id == $sender_id || $user_id == $receiver_id) {
                    $update_revealed_sql = "UPDATE menfess SET is_revealed = 0 WHERE id = ?";
                    $update_revealed_stmt = $conn->prepare($update_revealed_sql);
                    $update_revealed_stmt->bind_param("i", $menfess_id);
                    $update_revealed_stmt->execute();
                    error_log("Reset is_revealed karena user " . $user_id . " unlike menfess " . $menfess_id);
                }
            } else {
                // Like
                error_log("User " . $user_id . " liking menfess " . $menfess_id);
                $like_sql = "INSERT INTO menfess_likes (user_id, menfess_id) VALUES (?, ?)";
                $like_stmt = $conn->prepare($like_sql);
                $like_stmt->bind_param("ii", $user_id, $menfess_id);
                $like_stmt->execute();
                
                // Check if both sender and receiver have liked the message
    $check_mutual_sql = "SELECT m.id, m.sender_id, m.receiver_id 
                        FROM menfess m 
                        WHERE m.id = ? 
                        AND EXISTS (
                            SELECT 1 FROM menfess_likes 
                            WHERE menfess_id = m.id AND user_id = m.sender_id
                        ) 
                        AND EXISTS (
                            SELECT 1 FROM menfess_likes 
                            WHERE menfess_id = m.id AND user_id = m.receiver_id
                        )";

    $check_mutual_stmt = $conn->prepare($check_mutual_sql);
    $check_mutual_stmt->bind_param("i", $menfess_id);
    $check_mutual_stmt->execute();
    $mutual_result = $check_mutual_stmt->get_result();
    $mutual_exists = ($mutual_result->num_rows > 0);

    if ($mutual_exists) {
        error_log("MUTUAL LIKE terdeteksi untuk menfess ID " . $menfess_id);
        
        // Update is_revealed menjadi 1
        $reveal_sql = "UPDATE menfess SET is_revealed = 1 WHERE id = ?";
        $reveal_stmt = $conn->prepare($reveal_sql);
        $reveal_stmt->bind_param("i", $menfess_id);
        $reveal_stmt->execute();
        
        // Log hasil update
        if ($reveal_stmt->affected_rows > 0) {
            error_log("Berhasil mengupdate is_revealed = 1 untuk menfess ID " . $menfess_id);
        } else {
            error_log("Gagal mengupdate is_revealed, affected rows: " . $reveal_stmt->affected_rows);
        }
    } else {
        error_log("Belum mutual like untuk menfess ID " . $menfess_id);
                }
            }            
            // Refresh menfess data
            $menfess_stmt->execute();
            $menfess_result = $menfess_stmt->get_result();
            $menfess_messages = [];
            while ($row = $menfess_result->fetch_assoc()) {
                // Check if this specific menfess is liked by current user
                $like_check_sql = "SELECT * FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
                $like_check_stmt = $conn->prepare($like_check_sql);
                $like_check_stmt->bind_param("ii", $user_id, $row['id']);
                $like_check_stmt->execute();
                $like_check_result = $like_check_stmt->get_result();
                
                // Set liked status for this specific menfess
                $row['liked'] = ($like_check_result->num_rows > 0) ? 1 : 0;
                $menfess_messages[] = $row;
            }
            
            // Refresh matches
            $matches_sql = "SELECT DISTINCT u.id, u.name, p.profile_pic, p.bio
                        FROM users u
                        LEFT JOIN profiles p ON u.id = p.user_id
                        JOIN menfess m ON (
                            (m.sender_id = ? AND m.receiver_id = u.id) OR 
                            (m.receiver_id = ? AND m.sender_id = u.id)
                        )
                        WHERE m.is_revealed = 1";
            $matches_stmt = $conn->prepare($matches_sql);
            $matches_stmt->bind_param("ii", $user_id, $user_id);
            $matches_stmt->execute();
            $matches_result = $matches_stmt->get_result();
            $matches = [];
            while ($row = $matches_result->fetch_assoc()) {
                $matches[] = $row;
            }
            
            // Redirect untuk refresh halaman
            header("Location: dashboard?page=menfess");
            exit();
        }

        // Get all users for crush selection
        $users_sql = "SELECT u.id, u.name, p.profile_pic, p.bio 
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

        // Handle blind chat request
        $blind_chat_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_blind_chat'])) {
            // Find a random user for blind chat
            $random_user_sql = "SELECT id FROM users WHERE id != ? ORDER BY RAND() LIMIT 1";
            $random_user_stmt = $conn->prepare($random_user_sql);
            $random_user_stmt->bind_param("i", $user_id);
            $random_user_stmt->execute();
            $random_user_result = $random_user_stmt->get_result();
            
            if ($random_user_result->num_rows > 0) {
                $random_user = $random_user_result->fetch_assoc();
                $random_user_id = $random_user['id'];
                
                // Create a new chat session
                $chat_sql = "INSERT INTO chat_sessions (user1_id, user2_id, is_blind) VALUES (?, ?, 1)";
                $chat_stmt = $conn->prepare($chat_sql);
                $chat_stmt->bind_param("ii", $user_id, $random_user_id);
                
                if ($chat_stmt->execute()) {
                    $chat_id = $conn->insert_id;
                    $blind_chat_message = 'Blind chat started! Redirecting...';
                    header("Location: chat?session_id=" . $chat_id);
                    exit();
                } else {
                    $blind_chat_message = 'Error starting blind chat: ' . $conn->error;
                }
            } else {
                $blind_chat_message = 'No users available for blind chat right now.';
            }
        }

        // Get active chat sessions
        // First check if hidden_chats table exists
        $table_check_sql = "SHOW TABLES LIKE 'hidden_chats'";
        $table_exists = $conn->query($table_check_sql)->num_rows > 0;

        // Create the hidden_chats table if it doesn't exist
        if (!$table_exists) {
            $create_table_sql = "CREATE TABLE IF NOT EXISTS hidden_chats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                session_id INT NOT NULL,
                hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY user_session (user_id, session_id)
            )";
            $conn->query($create_table_sql);
            $table_exists = true;
        }

        // Get active chat sessions (excluding hidden chats)
        if ($table_exists) {
            $chat_sessions_sql = "SELECT cs.*, 
                            u1.name as user1_name, 
                            u2.name as user2_name,
                            CASE WHEN cs.user1_id = ? THEN u2.name ELSE u1.name END as partner_name,
                            CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END as partner_id,
                            (SELECT p.profile_pic FROM profiles p WHERE p.user_id = CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END) as profile_pic,
                            (SELECT MAX(created_at) FROM chat_messages WHERE session_id = cs.id) as last_message_time
                            FROM chat_sessions cs
                            JOIN users u1 ON cs.user1_id = u1.id
                            JOIN users u2 ON cs.user2_id = u2.id
                            LEFT JOIN hidden_chats hc ON cs.id = hc.session_id AND hc.user_id = ?
                            WHERE (cs.user1_id = ? OR cs.user2_id = ?) AND hc.id IS NULL
                            ORDER BY CASE WHEN (SELECT MAX(created_at) FROM chat_messages WHERE session_id = cs.id) IS NULL THEN 0 ELSE 1 END DESC, 
                                    (SELECT MAX(created_at) FROM chat_messages WHERE session_id = cs.id) DESC";
            $chat_sessions_stmt = $conn->prepare($chat_sessions_sql);
            $chat_sessions_stmt->bind_param("iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        } else {
            // Fallback query without hidden_chats table (shouldn't be used since we create the table above)
            $chat_sessions_sql = "SELECT cs.*, 
                            u1.name as user1_name, 
                            u2.name as user2_name,
                            CASE WHEN cs.user1_id = ? THEN u2.name ELSE u1.name END as partner_name,
                            CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END as partner_id,
                            (SELECT p.profile_pic FROM profiles p WHERE p.user_id = CASE WHEN cs.user1_id = ? THEN u2.id ELSE u1.id END) as profile_pic,
                            (SELECT MAX(created_at) FROM chat_messages WHERE session_id = cs.id) as last_message_time
                            FROM chat_sessions cs
                            JOIN users u1 ON cs.user1_id = u1.id
                            JOIN users u2 ON cs.user2_id = u2.id
                            WHERE (cs.user1_id = ? OR cs.user2_id = ?)
                            ORDER BY last_message_time DESC";
            $chat_sessions_stmt = $conn->prepare($chat_sessions_sql);
            $chat_sessions_stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
        }

        $chat_sessions_stmt->execute();
        $chat_sessions_result = $chat_sessions_stmt->get_result();
        $chat_sessions = [];
        while ($row = $chat_sessions_result->fetch_assoc()) {
            $chat_sessions[] = $row;
        }

        // Get compatibility test questions if not yet taken
        $test_taken_sql = "SELECT * FROM compatibility_results WHERE user_id = ?";
        $test_taken_stmt = $conn->prepare($test_taken_sql);
        $test_taken_stmt->bind_param("i", $user_id);
        $test_taken_stmt->execute();
        $test_taken_result = $test_taken_stmt->get_result();
        $test_taken = ($test_taken_result->num_rows > 0);
        $test_results = $test_taken ? $test_taken_result->fetch_assoc() : null;

        $questions_sql = "SELECT * FROM compatibility_questions";
        $questions_result = $conn->query($questions_sql);
        $questions = [];
        while ($row = $questions_result->fetch_assoc()) {
            $questions[] = $row;
        }

        // Handle compatibility test submission
        $test_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
            $answers = [];
            $personality_score = 0;
            
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
                    $test_message = 'Compatibility test updated! Finding new matches...';
                    $test_taken = true;
                    
                    // Refresh test results
                    $test_taken_stmt->execute();
                    $test_taken_result = $test_taken_stmt->get_result();
                    $test_results = $test_taken_result->fetch_assoc();
                    
                    // Redirect to dashboard
                    header("Location: dashboard?page=compatibility");
                    exit();
                } else {
                    $test_message = 'Error updating test results: ' . $conn->error;
                }
            } else {
                // Save test results
                $test_sql = "INSERT INTO compatibility_results (user_id, personality_score, major, interests, answers) 
                            VALUES (?, ?, ?, ?, ?)";
                $test_stmt = $conn->prepare($test_sql);
                $answers_json = json_encode($answers);
                $test_stmt->bind_param("idsss", $user_id, $personality_score, $major, $interests, $answers_json);
                
                if ($test_stmt->execute()) {
                    $test_message = 'Compatibility test completed! Finding matches...';
                    $test_taken = true;
                    
                    // Refresh test results
                    $test_taken_stmt->execute();
                    $test_taken_result = $test_taken_stmt->get_result();
                    $test_results = $test_taken_result->fetch_assoc();
                    
                    // Redirect to dashboard
                    header("Location: dashboard?page=compatibility");
                    exit();
                } else {
                    $test_message = 'Error saving test results: ' . $conn->error;
                }
            }
        }

        // Get compatible matches if test taken
        $compatible_matches = [];
        if ($test_taken) {
            $matches_sql = "SELECT u.id, u.name, p.profile_pic, p.bio, p.major, p.interests,
                    ABS(IFNULL(cr.personality_score, 0) - ?) as personality_diff,
                    CASE WHEN cr.major = ? THEN 30 ELSE 0 END as major_match,
                    CASE WHEN LOWER(IFNULL(cr.interests, '')) COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', LOWER(IFNULL(?, '')), '%') THEN 40 ELSE 0 END as interests_match,
                    LEAST(100, (
                        /* Base personality match worth 50% */
                        (50 - (ABS(IFNULL(cr.personality_score, 0) - ?) * 0.15)) +
                        /* Major match worth 25% */
                        CASE WHEN cr.major = ? THEN 25 ELSE 0 END +  
                        /* Interests match worth 25% */
                        CASE WHEN LOWER(IFNULL(cr.interests, '')) COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', LOWER(IFNULL(?, '')), '%') THEN 25 ELSE 0 END
                    )) as compatibility_score
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
            
            // Update to include additional parameter for personality score in the normalized calculation
            $matches_stmt->bind_param("dssdssi", 
                                $personality_score, $user_major, $user_interests, 
                                $personality_score, $user_major, $user_interests, $user_id);
            $matches_stmt->execute();
            $matches_result = $matches_stmt->get_result();
            
            while ($row = $matches_result->fetch_assoc()) {
                $compatible_matches[] = $row;
            }
        }

    $popup_promo_sql = "SELECT p.*, u.name as user_name 
                        FROM promotions p 
                        JOIN users u ON p.user_id = u.id 
                        WHERE p.status = 'active' 
                        AND p.id NOT IN (
                            SELECT promotion_id FROM promotion_dismissals 
                            WHERE user_id = ? AND DATE(dismissed_at) = CURDATE()
                        ) 
                        ORDER BY RAND() LIMIT 1";
    $popup_promo_stmt = $conn->prepare($popup_promo_sql);
    $popup_promo_stmt->bind_param("i", $user_id);
    $popup_promo_stmt->execute();
    $popup_promo_result = $popup_promo_stmt->get_result();
    $popup_promo = $popup_promo_result->fetch_assoc();

    // Update impressions counter if we found a promotion to display
    if ($popup_promo) {
        $update_impression_sql = "UPDATE promotions SET impressions = impressions + 1 WHERE id = ?";
        $update_impression_stmt = $conn->prepare($update_impression_sql);
        $update_impression_stmt->bind_param("i", $popup_promo['id']);
        $update_impression_stmt->execute();
    }
    ?>

    <!-- Promotion Popup HTML -->
    <?php if ($popup_promo): ?>
    <div id="promotion-popup" class="promotion-popup">
        <div class="promotion-popup-content">
            <button class="promotion-popup-close" id="close-promotion" data-promo-id="<?php echo $popup_promo['id']; ?>">
                <i class="fas fa-times"></i>
            </button>
            <div class="promotion-popup-image">
                <img src="<?php echo htmlspecialchars($popup_promo['image_url']); ?>" alt="<?php echo htmlspecialchars($popup_promo['title']); ?>">
                <div class="promotion-popup-badge">Promosi</div>
            </div>
            <div class="promotion-popup-info">
                <h3><?php echo htmlspecialchars($popup_promo['title']); ?></h3>
                <p><?php echo htmlspecialchars($popup_promo['description']); ?></p>
                <div class="promotion-popup-meta">
                    <span class="promotion-popup-author">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($popup_promo['user_name']); ?>
                    </span>
                </div>
                <a href="<?php echo htmlspecialchars($popup_promo['target_url']); ?>" class="btn btn-block promotion-popup-btn" target="_blank" data-promo-id="<?php echo $popup_promo['id']; ?>">
                    <i class="fas fa-external-link-alt"></i> Kunjungi Link
                </a>
                <div class="promotion-popup-footer">
                    <a href="dashboard?page=promotion" class="promotion-popup-link">Buat promosi sendiri?</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CSS for Promotion Popup -->
    <style>
    /* Promotion Popup Styling */
    .promotion-popup {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        width: 350px;
        background-color: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        transform: translateY(100%);
        opacity: 0;
        animation: slideIn 0.5s ease forwards;
        animation-delay: 2s;
    }

    @keyframes slideIn {
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .promotion-popup-close {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: rgba(0, 0, 0, 0.6);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
    }

    .promotion-popup-close:hover {
        background-color: rgba(0, 0, 0, 0.8);
    }

    .promotion-popup-image {
        height: 180px;
        position: relative;
        overflow: hidden;
    }

    .promotion-popup-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .promotion-popup:hover .promotion-popup-image img {
        transform: scale(1.05);
    }

    .promotion-popup-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background-color: var(--primary);
        color: white;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }

    .promotion-popup-info {
        padding: 20px;
    }

    .promotion-popup-info h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
    }

    .promotion-popup-info p {
        color: #666;
        margin-bottom: 15px;
        font-size: 14px;
        line-height: 1.5;
    }

    .promotion-popup-meta {
        margin-bottom: 15px;
        font-size: 13px;
        color: #666;
    }

    .promotion-popup-meta i {
        color: var(--primary);
        margin-right: 5px;
    }

    .promotion-popup-btn {
        margin-bottom: 10px;
    }

    .promotion-popup-footer {
        text-align: center;
        font-size: 13px;
    }

    .promotion-popup-link {
        color: var(--primary);
        text-decoration: none;
    }

    .promotion-popup-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 480px) {
        .promotion-popup {
            width: calc(100% - 40px);
        }
    }
    </style>    

        // Current page for navigation
        $page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
        ?>

        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cupid - Dashboard</title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
            <link href="assets/css/style.css" rel="stylesheet">
            <link href="assets/css/notification-styles.css" rel="stylesheet">
            <style>
                /* Complete CSS for Cupid Dashboard */

        :root {
            --primary: #ff4b6e;
            --secondary: #ffd9e0;
            --dark: #333333;
            --light: #f5f5f5;
            --accent: #ff8fa3;
            --text-color: #333333;
            --bg-color: #f0f0f0;
            --card-bg: #ffffff;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --border-color: #eeeeee;
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --gradient-bg: linear-gradient(135deg, #ffd9e0 0%, #fff1f3 100%);
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --primary: #ff6b8a;
            --secondary: #662d39;
            --dark: #f5f5f5;
            --light: #222222;
            --accent: #ff8fa3;
            --text-color: #f5f5f5;
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            --border-color: #333333;
            --input-bg: #2a2a2a;
            --input-border: #444444;
            --gradient-bg: linear-gradient(135deg, #662d39 0%, #331520 100%);
        }

        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ff4b6e' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styling */
        header {
            background-color: var(--card-bg);
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
            color: var(--text-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        nav ul li a:hover {
            color: var(--primary);
        }

        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--light) !important;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn:hover {
            background-color: #e63e5c;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary) !important;
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--light) !important;
        }

        /* Dashboard Layout */
        .dashboard {
            padding-top: 100px;
            min-height: 100vh;
            background-color: var(--bg-color);
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }

        /* Sidebar Styling */
        .sidebar {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            height: fit-content;
            position: sticky;
            top: 100px;
            transition: background-color 0.3s ease;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: var(--secondary);
            color: var(--primary);
        }

        .sidebar-menu i {
            margin-right: 10px;
        }

        /* Main Content Area */
        .main-content {
            padding-bottom: 50px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .dashboard-header p {
            color: #666;
            font-size: 16px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;margin-bottom: 20px;
        }

        .page-header h3 {
            font-size: 22px;
            color: var(--text-color);
        }

        /* Card Styling */
        .card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            transition: background-color 0.3s ease;
        }

        .card-header {
            margin-bottom: 20px;
        }

        .card-header h3 {
            font-size: 20px;
            color: var(--text-color);
        }

        /* Alert Messages */
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

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--input-border);
            border-radius: 5px;
            font-size: 16px;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: border 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 75, 110, 0.1);
        }

        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Profile Styling */
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
            position: relative;
        }

        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .edit-pic-button {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: var(--primary);
            color: var(--light);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            font-size: 14px;
        }

        .edit-pic-button:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.3);
        }

        .profile-info h3 {
            font-size: 24px;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .profile-info p {
            color: #666;
        }

        .profile-info p i {
            color: var(--primary);
            margin-right: 8px;
        }

        .profile-completion {
            background-color: rgba(255, 75, 110, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .completion-text {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        .completion-bar {
            height: 8px;
            background-color: rgba(255, 75, 110, 0.1);
            border-radius: 4px;
            overflow: hidden;
        }

        .completion-progress {
            height: 100%;
            background-color: var(--primary);
            border-radius: 4px;
            transition: width 0.8s ease-in-out;
        }

        /* Profile Tabs */
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 25px;
        }

        .profile-tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 500;
            position: relative;
            transition: all 0.3s;
            color: #666;
        }

        .profile-tab:hover {
            color: var(--primary);
        }

        .profile-tab.active {
            color: var(--primary);
        }

        .profile-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Menfess Styling */
        .menfess-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .menfess-card {
            background-color: #f0f0f0;
            border-radius: 10px;
            padding: 20px;
            position: relative;
            transition: transform 0.2s ease;
        }

        .menfess-card:hover {
            transform: translateY(-3px);
        }

        .menfess-card.sent {
            background-color: var(--secondary);
            align-self: flex-end;
            max-width: 80%;
        }

        .menfess-card.received {
            background-color: #e4e6eb;
            align-self: flex-start;
            max-width: 80%;
        }

        [data-theme="dark"] .menfess-card.received {
            background-color: #252525;
        }

        [data-theme="dark"] .menfess-card.sent {
            background-color: var(--secondary);
        }

        .menfess-content {
            margin-bottom: 10px;
        }

        .menfess-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #777;
        }

        .menfess-like {
            display: flex;
            align-items: center;
            cursor: pointer;
            background: none;
            border: none;
        }

        .menfess-like i {
            margin-right: 5px;
            color: var(--primary);
        }

        .menfess-time {
            font-size: 12px;
        }

        /* Chat Styling */
        .chat-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .chat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        .chat-info {
            flex: 1;
        }

        .chat-name {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            color: var(--text-color);
        }

        .chat-last-msg {
            font-size: 14px;
            color: #666;
        }

        .chat-time {
            font-size: 12px;
            color: #999;
        }

        .lock-icon {
            margin-left: 5px;
            color: var(--primary);
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .feature-box {
            text-align: center;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .feature-box i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .feature-box h4 {
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .feature-box p {
            margin-bottom: 15px;
            color: #666;
        }

        /* User Grid */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .user-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .user-card-img {
            height: 200px;
            overflow: hidden;
        }

        .user-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .user-card:hover .user-card-img img {
            transform: scale(1.05);
        }

        .user-card-info {
            padding: 20px;
        }

        .user-card-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
            color: var(--text-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-card-bio {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 60px;
        }

        /* Compatibility Test Styling */
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

        .compatibility-details {
            flex: 1;
        }

        .question {
            margin-bottom: 25px;
        }

        .question h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--text-color);
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
            border: 1px solid var(--input-border);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            background-color: var(--card-bg);
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

        /* Score Details */
        .score-details {
            display: flex;
            justify-content: space-between;
            padding: 15px;
            background-color: var(--card-bg);
            border-radius: 5px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .score-item {
            text-align: center;
        }

        .score-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
        }

        .score-label {
            font-size: 12px;
            color: #666;
        }

        /* Empty State */
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

        /* Theme Toggle Button */
        .theme-toggle {
            margin-left: 15px;
            display: flex;
            align-items: center;
        }

        #theme-toggle-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--primary);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        #theme-toggle-btn:hover {
            background-color: rgba(255, 75, 110, 0.1);
        }

        #theme-toggle-btn .fa-moon {
            display: block;
            position: absolute;
            transform: translateY(0);
            opacity: 1;
            transition: all 0.3s ease;
        }

        #theme-toggle-btn .fa-sun {
            display: block;
            position: absolute;
            transform: translateY(30px);
            opacity: 0;
            transition: all 0.3s ease;
        }

        [data-theme="dark"] #theme-toggle-btn .fa-moon {
            transform: translateY(-30px);
            opacity: 0;
        }

        [data-theme="dark"] #theme-toggle-btn .fa-sun {
            transform: translateY(0);
            opacity: 1;
        }

        /* Interest Tags */
        .interests-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            min-height: 40px;
        }

        .interest-tag {
            background-color: var(--secondary);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .interest-tag i {
            margin-left: 8px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .interest-tag i:hover {
            color: #e63e5c;
            transform: scale(1.2);
        }

        .text-muted {
            color: #999;
            font-style: italic;
        }

        /* Privacy Options */
        .privacy-option {
            background-color: rgba(0, 0, 0, 0.03);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .privacy-option:hover {
            background-color: rgba(255, 75, 110, 0.1);
        }

        .privacy-option h4 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .privacy-option p {
            color: #666;
            font-size: 14px;
            margin-bottom: 0;
        }

        /* Toggle Switch */
        .toggle {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 26px;
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* File Upload */
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload .form-control {
            padding-right: 110px;
        }

        .file-upload-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            padding: 7px 15px;
            background-color: var(--primary);
            color: white;
            border-radius: 5px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload-btn:hover {
            background-color: #e63e5c;
        }

        /* Form Submission */
        .submit-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        /* Scrollbar Customization */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-color);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }

        /* Matches Styling */
        .matches-container {
            margin-bottom: 30px;
        }

        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .match-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .match-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }

        .match-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .match-card:hover .match-image img {
            transform: scale(1.05);
        }

        .match-info {
            padding: 20px;
        }

        .match-name {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .match-bio {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 60px;
        }

        .match-actions {
            display: flex;
            gap: 10px;
        }

        .empty-matches {
            text-align: center;
            padding: 40px 0;
        }

        .empty-icon {
            font-size: 50px;
            color: var(--secondary);
            margin-bottom: 20px;
        }

        /* Media Queries */
        @media (max-width: 991px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: static;
                margin-bottom: 30px;
            }
        }

        @media (max-width: 767px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-pic {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .profile-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .profile-tab {
                padding: 12px 15px;
            }
            
            .submit-wrapper {
                justify-content: center;
            }
            
            .matches-grid {
                grid-template-columns: 1fr;
            }
            
            .match-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .logo-container {
                display: block;
                margin-top: 15px;
            }
            
            .logo-container span {
                display: none;
            }
            
            .header-content {
                flex-wrap: wrap;
            }
            
            nav ul {
                margin-top: 10px;
            }
        }
            </style>
        </head>
        <body>
            
            <!-- Header -->
            <header>
            <div class="container">
                <div class="header-content">
                    <!-- Logo -->
                    <a href="cupid" class="logo">
                        <i class="fas fa-heart"></i> Cupid
                    </a>
                    
                    <!-- Navigation -->
                    <nav>
                        <ul>
                            <li><a href="dashboard">Dashboard</a></li>
                            
                            <!-- Notification Bell -->
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
                            
                            <!-- Theme Toggle -->
                            <li class="theme-toggle">
                                <button id="theme-toggle-btn" aria-label="Toggle dark mode">
                                    <i class="fas fa-moon"></i>
                                    <i class="fas fa-sun"></i>
                                </button>
                            </li>
                            
                            <!-- Logout Button -->
                            <li>
                                <a href="logout" class="btn btn-outline">Keluar</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </header>

            <!-- Dashboard Section -->
            <section class="dashboard">
                <div class="container">
                    <div class="dashboard-container">
                        <!-- Sidebar -->
                        <div class="sidebar">
                            <ul class="sidebar-menu">
                                <li>
                                    <a href="?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">
                                        <i class="fas fa-user"></i> Profil
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=menfess" class="<?php echo $page === 'menfess' ? 'active' : ''; ?>">
                                        <i class="fas fa-mask"></i> Crush Menfess
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=chat" class="<?php echo $page === 'chat' ? 'active' : ''; ?>">
                                        <i class="fas fa-comments"></i> Chat
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=compatibility" class="<?php echo $page === 'compatibility' ? 'active' : ''; ?>">
                                        <i class="fas fa-clipboard-check"></i> Tes Kecocokan
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=matches" class="<?php echo $page === 'matches' ? 'active' : ''; ?>">
                                        <i class="fas fa-heart"></i> Pasangan
                                    </a>
                                </li>
                                <li>
                                    <a href="?page=promotion" class="<?php echo $page === 'promotion' ? 'active' : ''; ?>">
                                        <i class="fas fa-tag"></i> Promosi
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <!-- Main Content -->
                        <div class="main-content">
                            <?php if ($page === 'dashboard'): ?>
                                <div class="dashboard-header">
                                    <h2>Dashboard</h2>
                                    <p>Selamat datang, <?php echo htmlspecialchars($user['name']); ?>!</p>
                                </div>
                                
                                <?php if (!$profile_complete): ?>
                                <div class="card profile-completion-card">
                                    <div class="profile-completion-content">
                                        <div class="profile-completion-info">
                                            <div class="completion-icon">
                                                <i class="fas fa-user-edit"></i>
                                            </div>
                                            <div class="completion-text">
                                                <h3>Lengkapi Profil Anda</h3>
                                                <p>Lengkapi profil Anda untuk meningkatkan peluang menemukan pasangan yang cocok!</p>
                                                <div class="profile-progress-container">
                                                    <div class="profile-progress-bar">
                                                        <div class="profile-progress-fill" style="width: <?php echo $completion_percentage ?? 25; ?>%"></div>
                                                    </div>
                                                    <span class="profile-progress-text"><?php echo $completion_percentage ?? 25; ?>% Lengkap</span>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="?page=profile" class="btn btn-lg">
                                            <i class="fas fa-arrow-right"></i> Lengkapi Profil
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Stats Cards Row -->
                                <div class="stats-container">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-mask"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3><?php echo count($menfess_messages ?? []); ?></h3>
                                            <p>Total Menfess</p>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-heart"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3><?php echo count($matches ?? []); ?></h3>
                                            <p>Match Saat Ini</p>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3><?php echo count($chat_sessions ?? []); ?></h3>
                                            <p>Chat Aktif</p>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                        <div class="stat-info">
                                            <h3><?php echo isset($test_results['personality_score']) ? round($test_results['personality_score']) : 0; ?></h3>
                                            <p>Skor Kompatibilitas</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Activities and Features in Grid Layout -->
                                <div class="dashboard-grid">
                                    <!-- Recent Activity Card -->
                                    <div class="card dashboard-card activity-card">
                                        <div class="card-header">
                                            <h3><i class="fas fa-history"></i> Aktivitas Terbaru</h3>
                                            <a href="#" class="card-action">Lihat Semua</a>
                                        </div>
                                        <div class="recent-activity">
                                            <?php if (empty($menfess_messages) && empty($chat_sessions)): ?>
                                                <div class="empty-state-mini">
                                                    <i class="fas fa-wind"></i>
                                                    <p>Belum ada aktivitas baru.</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="activity-list">
                                                    <?php 
                                                    $count = 0;
                                                    foreach ($menfess_messages as $message) {
                                                        if ($count >= 3) break;
                                                        $type = $message['type'] === 'sent' ? 'mengirim' : 'menerima';
                                                        $icon_class = $message['type'] === 'sent' ? 'paper-plane' : 'mask';
                                                        $icon_color = $message['type'] === 'sent' ? 'var(--accent)' : 'var(--primary)';
                                                    ?>
                                                    <div class="activity-item">
                                                        <div class="activity-icon" style="background-color: <?php echo $icon_color; ?>20;">
                                                            <i class="fas fa-<?php echo $icon_class; ?>" style="color: <?php echo $icon_color; ?>;"></i>
                                                        </div>
                                                        <div class="activity-content">
                                                            <div class="activity-title">Anda <?php echo $type; ?> pesan menfess</div>
                                                            <div class="activity-time"><?php echo date('d M Y, H:i', strtotime($message['created_at'])); ?></div>
                                                        </div>
                                                    </div>
                                                    <?php 
                                                        $count++;
                                                    }
                                                    
                                                    foreach ($chat_sessions as $session) {
                                                        if ($count >= 3) break;
                                                        // Check if blind chat and if permission exists
                                                        $has_permission = false;
                                                        $partner_name = "Anonymous User";
                                                        
                                                        if ($session['is_blind']) {
                                                            $partner_id = $session['partner_id'];
                                                            $permission_sql = "SELECT * FROM profile_view_permissions 
                                                                            WHERE user_id = ? AND target_user_id = ?";
                                                            $permission_stmt = $conn->prepare($permission_sql);
                                                            $permission_stmt->bind_param("ii", $user_id, $partner_id);
                                                            $permission_stmt->execute();
                                                            $permission_result = $permission_stmt->get_result();
                                                            $has_permission = ($permission_result->num_rows > 0);
                                                            
                                                            if ($has_permission) {
                                                                $partner_name = htmlspecialchars($session['partner_name']);
                                                            }
                                                        } else {
                                                            $partner_name = htmlspecialchars($session['partner_name']);
                                                        }
                                                    ?>
                                                    <div class="activity-item">
                                                        <div class="activity-icon" style="background-color: var(--accent)20;">
                                                            <i class="fas fa-comments" style="color: var(--accent);"></i>
                                                        </div>
                                                        <div class="activity-content">
                                                            <div class="activity-title">
                                                                Chat dengan <?php echo $partner_name; ?>
                                                                <?php if ($session['is_blind'] && !$has_permission): ?>
                                                                <span class="blind-badge"><i class="fas fa-eye-slash"></i></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="activity-time">
                                                                <?php echo isset($session['last_message_time']) && !empty($session['last_message_time']) 
                                                                    ? date('d M Y, H:i', strtotime($session['last_message_time'])) 
                                                                    : 'Belum ada pesan'; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php 
                                                        $count++;
                                                    }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Actions Card -->
                                    <div class="card dashboard-card actions-card">
                                        <div class="card-header">
                                            <h3><i class="fas fa-bolt"></i> Aksi Cepat</h3>
                                        </div>
                                        <div class="quick-actions">
                                            <a href="?page=menfess" class="quick-action-btn">
                                                <div class="quick-action-icon">
                                                    <i class="fas fa-mask"></i>
                                                </div>
                                                <span>Kirim Menfess</span>
                                            </a>
                                            <a href="?page=chat" class="quick-action-btn">
                                                <div class="quick-action-icon">
                                                    <i class="fas fa-comments"></i>
                                                </div>
                                                <span>Mulai Chat</span>
                                            </a>
                                            <a href="?page=compatibility" class="quick-action-btn">
                                                <div class="quick-action-icon">
                                                    <i class="fas fa-clipboard-check"></i>
                                                </div>
                                                <span>Tes Kecocokan</span>
                                            </a>
                                            <a href="?page=matches" class="quick-action-btn">
                                                <div class="quick-action-icon">
                                                    <i class="fas fa-heart"></i>
                                                </div>
                                                <span>Lihat Match</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature Highlights -->
                                <div class="card feature-showcase-card">
                                    <div class="card-header">
                                        <h3><i class="fas fa-star"></i> Fitur Utama</h3>
                                    </div>
                                    <div class="feature-showcase">
                                        <div class="feature-item">
                                            <div class="feature-icon">
                                                <i class="fas fa-mask"></i>
                                            </div>
                                            <div class="feature-content">
                                                <h4>Anonymous Crush Menfess</h4>
                                                <p>Kirim pesan anonim ke crush kamu! Identitas kamu akan terungkap hanya jika kalian saling menyukai.</p>
                                                <a href="?page=menfess" class="btn btn-outline">Kirim Menfess</a>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <div class="feature-icon">
                                                <i class="fas fa-comments"></i>
                                            </div>
                                            <div class="feature-content">
                                                <h4>Blind Chat</h4>
                                                <p>Chat dengan mahasiswa acak tanpa mengetahui identitas mereka. Temukan koneksi yang mengejutkan!</p>
                                                <a href="?page=chat" class="btn btn-outline">Mulai Chat</a>
                                            </div>
                                        </div>
                                        
                                        <div class="feature-item">
                                            <div class="feature-icon">
                                                <i class="fas fa-clipboard-check"></i>
                                            </div>
                                            <div class="feature-content">
                                                <h4>Compatibility Test</h4>
                                                <p>Temukan kecocokan berdasarkan kepribadian, minat, dan jurusan kamu. Dapatkan rekomendasi match terbaik!</p>
                                                <a href="?page=compatibility" class="btn btn-outline">Ikuti Tes</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <style>
                                /* Dashboard Redesign Styles */
                                .dashboard-header {
                                    margin-bottom: 30px;
                                }
                                
                                .dashboard-header h2 {
                                    font-size: 32px;
                                    font-weight: 700;
                                    margin-bottom: 10px;
                                    color: var(--text-color);
                                }
                                
                                .dashboard-header p {
                                    font-size: 18px;
                                    color: var(--text-color);
                                    opacity: 0.8;
                                }
                                
                                /* Profile Completion Card */
                                .profile-completion-card {
                                    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
                                    color: white;
                                    margin-bottom: 30px;
                                    border: none;
                                    overflow: hidden;
                                    position: relative;
                                }
                                
                                .profile-completion-card::before {
                                    content: '';
                                    position: absolute;
                                    top: 0;
                                    right: 0;
                                    bottom: 0;
                                    left: 0;
                                    background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
                                    opacity: 0.5;
                                }
                                
                                .profile-completion-content {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    padding: 25px;
                                    position: relative;
                                    z-index: 2;
                                }
                                
                                .profile-completion-info {
                                    display: flex;
                                    align-items: center;
                                    gap: 20px;
                                    flex: 1;
                                }
                                
                                .completion-icon {
                                    width: 60px;
                                    height: 60px;
                                    background: rgba(255, 255, 255, 0.2);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 24px;
                                }
                                
                                .completion-text h3 {
                                    font-size: 22px;
                                    font-weight: 600;
                                    margin-bottom: 5px;
                                }
                                
                                .completion-text p {
                                    margin-bottom: 15px;
                                    opacity: 0.9;
                                }
                                
                                .profile-progress-container {
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                }
                                
                                .profile-progress-bar {
                                    flex: 1;
                                    height: 8px;
                                    background: rgba(255, 255, 255, 0.2);
                                    border-radius: 4px;
                                    overflow: hidden;
                                }
                                
                                .profile-progress-fill {
                                    height: 100%;
                                    background: white;
                                    border-radius: 4px;
                                    transition: width 0.8s ease-in-out;
                                }
                                
                                .profile-progress-text {
                                    font-size: 14px;
                                    font-weight: 600;
                                    white-space: nowrap;
                                }
                                
                                .profile-completion-card .btn {
                                    background: white;
                                    color: var(--primary);
                                    border: none;
                                    transition: all 0.3s;
                                    padding: 12px 25px;
                                    font-weight: 600;
                                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                                }
                                
                                .profile-completion-card .btn:hover {
                                    transform: translateY(-3px);
                                    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
                                    background: rgba(255, 255, 255, 0.9);
                                }
                                
                                /* Stats Cards */
                                .stats-container {
                                    display: grid;
                                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                                    gap: 20px;
                                    margin-bottom: 30px;
                                }
                                
                                .stat-card {
                                    background-color: var(--card-bg);
                                    border-radius: 12px;
                                    padding: 20px;
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                    box-shadow: var(--card-shadow);
                                    transition: transform 0.3s, box-shadow 0.3s;
                                }
                                
                                .stat-card:hover {
                                    transform: translateY(-5px);
                                    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
                                }
                                
                                .stat-icon {
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 12px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 24px;
                                }
                                
                                .stat-card:nth-child(1) .stat-icon {
                                    background-color: rgba(255, 75, 110, 0.1);
                                    color: var(--primary);
                                }
                                
                                .stat-card:nth-child(2) .stat-icon {
                                    background-color: rgba(255, 143, 163, 0.1);
                                    color: var(--accent);
                                }
                                
                                .stat-card:nth-child(3) .stat-icon {
                                    background-color: rgba(0, 128, 255, 0.1);
                                    color: #0080ff;
                                }
                                
                                .stat-card:nth-child(4) .stat-icon {
                                    background-color: rgba(75, 192, 192, 0.1);
                                    color: #4bc0c0;
                                }
                                
                                .stat-info h3 {
                                    font-size: 28px;
                                    font-weight: 700;
                                    margin-bottom: 5px;
                                    color: var(--text-color);
                                }
                                
                                .stat-info p {
                                    color: var(--text-color);
                                    opacity: 0.7;
                                    font-size: 14px;
                                }
                                
                                /* Dashboard Grid Layout */
                                .dashboard-grid {
                                    display: grid;
                                    grid-template-columns: 1.5fr 1fr;
                                    gap: 20px;
                                    margin-bottom: 30px;
                                }
                                
                                .dashboard-card {
                                    height: 100%;
                                }
                                
                                .card-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 20px;
                                    padding-bottom: 15px;
                                    border-bottom: 1px solid var(--border-color);
                                }
                                
                                .card-header h3 {
                                    font-size: 18px;
                                    font-weight: 600;
                                    color: var(--text-color);
                                    display: flex;
                                    align-items: center;
                                    gap: 10px;
                                }
                                
                                .card-header h3 i {
                                    color: var(--primary);
                                }
                                
                                .card-action {
                                    color: var(--primary);
                                    font-size: 14px;
                                    text-decoration: none;
                                    font-weight: 500;
                                }
                                
                                .card-action:hover {
                                    text-decoration: underline;
                                }
                                
                                /* Recent Activity Styling */
                                .empty-state-mini {
                                    text-align: center;
                                    padding: 30px 0;
                                }
                                
                                .empty-state-mini i {
                                    font-size: 30px;
                                    color: #ccc;
                                    margin-bottom: 10px;
                                }
                                
                                .empty-state-mini p {
                                    color: #888;
                                }
                                
                                .activity-list {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 15px;
                                }
                                
                                .activity-item {
                                    display: flex;
                                    align-items: center;
                                    gap: 15px;
                                    padding: 12px 15px;
                                    background-color: var(--bg-color);
                                    border-radius: 10px;
                                    transition: transform 0.2s, box-shadow 0.2s;
                                }
                                
                                .activity-item:hover {
                                    transform: translateY(-2px);
                                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
                                }
                                
                                .activity-icon {
                                    width: 40px;
                                    height: 40px;
                                    border-radius: 10px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 16px;
                                }
                                
                                .activity-content {
                                    flex: 1;
                                }
                                
                                .activity-title {
                                    font-weight: 500;
                                    color: var(--text-color);
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                }
                                
                                .activity-time {
                                    font-size: 12px;
                                    color: #888;
                                    margin-top: 4px;
                                }
                                
                                .blind-badge {
                                    background-color: rgba(0, 0, 0, 0.1);
                                    color: #888;
                                    font-size: 10px;
                                    padding: 2px 5px;
                                    border-radius: 4px;
                                    margin-left: 5px;
                                }
                                
                                /* Quick Actions Styling */
                                .quick-actions {
                                    display: grid;
                                    grid-template-columns: repeat(2, 1fr);
                                    gap: 15px;
                                }
                                
                                .quick-action-btn {
                                    display: flex;
                                    flex-direction: column;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 20px;
                                    background-color: var(--bg-color);
                                    border-radius: 10px;
                                    text-decoration: none;
                                    color: var(--text-color);
                                    transition: all 0.3s ease;
                                }
                                
                                .quick-action-btn:hover {
                                    transform: translateY(-5px);
                                    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
                                }
                                
                                .quick-action-icon {
                                    width: 50px;
                                    height: 50px;
                                    border-radius: 12px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 20px;
                                    margin-bottom: 10px;
                                    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
                                    color: white;
                                }
                                
                                .quick-action-btn:nth-child(2) .quick-action-icon {
                                    background: linear-gradient(135deg, #0080ff 0%, #00bfff 100%);
                                }
                                
                                .quick-action-btn:nth-child(3) .quick-action-icon {
                                    background: linear-gradient(135deg, #4bc0c0 0%, #2ecc71 100%);
                                }
                                
                                .quick-action-btn:nth-child(4) .quick-action-icon {
                                    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
                                }
                                
                                .quick-action-btn span {
                                    font-weight: 500;
                                    font-size: 14px;
                                    margin-top: 5px;
                                }
                                
                                /* Feature Showcase Card */
                                .feature-showcase-card {
                                    margin-bottom: 30px;
                                }
                                
                                .feature-showcase {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 20px;
                                }
                                
                                .feature-item {
                                    display: flex;
                                    align-items: flex-start;
                                    gap: 20px;
                                    padding: 20px;
                                    background-color: var(--bg-color);
                                    border-radius: 12px;
                                    transition: transform 0.3s, box-shadow 0.3s;
                                }
                                
                                .feature-item:hover {
                                    transform: translateY(-3px);
                                    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.08);
                                }
                                
                                .feature-icon {
                                    width: 60px;
                                    height: 60px;
                                    border-radius: 50%;
                                    background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
                                    color: white;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    font-size: 24px;
                                    flex-shrink: 0;
                                }
                                
                                .feature-item:nth-child(2) .feature-icon {
                                    background: linear-gradient(135deg, #0080ff 0%, #00bfff 100%);
                                }
                                
                                .feature-item:nth-child(3) .feature-icon {
                                    background: linear-gradient(135deg, #4bc0c0 0%, #2ecc71 100%);
                                }
                                
                                .feature-content {
                                    flex: 1;
                                }
                                
                                .feature-content h4 {
                                    font-size: 18px;
                                    font-weight: 600;
                                    margin-bottom: 10px;
                                    color: var(--text-color);
                                }
                                
                                .feature-content p {
                                    margin-bottom: 15px;
                                    color: var(--text-color);
                                    opacity: 0.8;
                                }
                                
                                .feature-content .btn-outline {
                                    padding: 8px 15px;
                                    font-size: 14px;
                                }
                                
                                /* Responsive Adjustments */
                                @media (max-width: 991px) {
                                    .dashboard-grid {
                                        grid-template-columns: 1fr;
                                    }
                                    
                                    .profile-completion-content {
                                        flex-direction: column;
                                        align-items: flex-start;
                                        gap: 20px;
                                    }
                                    
                                    .profile-completion-card .btn {
                                        width: 100%;
                                    }
                                }
                                
                                @media (max-width: 768px) {
                                    .stats-container {
                                        grid-template-columns: repeat(2, 1fr);
                                    }
                                    
                                    .profile-completion-info {
                                        flex-direction: column;
                                        align-items: flex-start;
                                        text-align: left;
                                    }
                                    
                                    .feature-item {
                                        flex-direction: column;
                                        align-items: center;
                                        text-align: center;
                                    }
                                }
                                
                                @media (max-width: 480px) {
                                    .stats-container {
                                        grid-template-columns: 1fr;
                                    }
                                    
                                    .activity-icon {
                                        width: 35px;
                                        height: 35px;
                                        font-size: 14px;
                                    }
                                    
                                    .quick-actions {
                                        grid-template-columns: 1fr;
                                    }
                                    
                                    .quick-action-btn {
                                        flex-direction: row;
                                        gap: 15px;
                                        justify-content: flex-start;
                                        padding: 15px;
                                    }
                                    
                                    .quick-action-icon {
                                        margin-bottom: 0;
                                        width: 40px;
                                        height: 40px;
                                        font-size: 16px;
                                    }
                                }
                                </style>
                                <?php endif; ?>
                            
        <?php if ($page === 'profile'): ?>
    <div class="dashboard-header">
        <h2>Profil Saya</h2>
        <p>Lengkapi profil Anda untuk meningkatkan peluang menemukan koneksi yang bermakna.</p>
    </div>
    
    <?php if (!empty($profile_message)): ?>
    <div class="alert <?php echo strpos($profile_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
        <i class="<?php echo strpos($profile_message, 'success') !== false ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?>"></i>
        <?php echo $profile_message; ?>
    </div>
    <?php endif; ?>
    
    <!-- New Profile Hero Section -->
    <div class="profile-hero-card">
        <div class="profile-hero-backdrop"></div>
        <div class="profile-hero-content">
            <div class="profile-avatar-wrapper">
                <div class="profile-avatar">
                    <img src="<?php echo !empty($profile['profile_pic']) ? htmlspecialchars($profile['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="Profile Picture" id="profile-preview-img">
                    <label for="profile_pic" class="profile-avatar-edit">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
            </div>
            <div class="profile-hero-info">
                <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <i class="fas fa-envelope"></i>
                        <span><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <?php if(!empty($profile['major'])): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-graduation-cap"></i>
                        <span><?php echo htmlspecialchars($profile['major']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Progress -->
            <div class="profile-progress-container">
                <?php
                // Calculate profile completion percentage
                $total_fields = 5; // Name, email, bio, interests, major
                $filled_fields = 2; // Name and email are always filled
                
                if (!empty($profile)) {
                    if (!empty($profile['bio'])) $filled_fields++;
                    if (!empty($profile['interests'])) $filled_fields++;
                    if (!empty($profile['major'])) $filled_fields++;
                }
                
                $completion_percentage = round(($filled_fields / $total_fields) * 100);
                ?>
                <div class="profile-progress-info">
                    <span>Kelengkapan profil</span>
                    <span class="profile-progress-percentage"><?php echo $completion_percentage; ?>%</span>
                </div>
                <div class="profile-progress-bar">
                    <div class="profile-progress-fill" style="width: <?php echo $completion_percentage; ?>%;"></div>
                </div>
                <div class="profile-progress-tips">
                    Profil yang lengkap meningkatkan peluang mendapatkan match!
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Edit Form -->
    <div class="profile-edit-card">
        <form method="post" enctype="multipart/form-data" id="profile-form">
            <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display: none;">
            
            <!-- Modern Clean Tab Navigation -->
            <div class="profile-tabs-container">
                <div class="profile-tabs">
                    <button type="button" class="profile-tab active" data-tab="basic-info">
                        <i class="fas fa-user"></i>
                        <span>Informasi Dasar</span>
                    </button>
                    <button type="button" class="profile-tab" data-tab="bio-interests">
                        <i class="fas fa-heart"></i>
                        <span>Bio & Minat</span>
                    </button>
                    <button type="button" class="profile-tab" data-tab="privacy">
                        <i class="fas fa-shield-alt"></i>
                        <span>Privasi</span>
                    </button>
                </div>
                
                <div class="tab-save-button">
                    <button type="submit" name="update_profile" class="btn">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
            
            <!-- Tab Contents -->
            <div class="profile-tab-contents">
                <!-- Basic Info Tab -->
                <div class="tab-content active" id="basic-info-content">
                    <div class="tab-content-inner">
                        <div class="form-section">
                            <h4>Informasi Pribadi</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Nama Lengkap</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                    <div class="form-hint">Email tidak dapat diubah karena digunakan untuk verifikasi.</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="major">Jurusan</label>
                                <select id="major" name="major" class="form-control">
                                    <option value="">-- Pilih Jurusan --</option>
                                    <option value="Computer Science" <?php echo ($profile && $profile['major'] === 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Business" <?php echo ($profile && $profile['major'] === 'Business') ? 'selected' : ''; ?>>Business</option>
                                    <option value="Law" <?php echo ($profile && $profile['major'] === 'Law') ? 'selected' : ''; ?>>Law</option>
                                    <option value="Medicine" <?php echo ($profile && $profile['major'] === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                    <option value="Engineering" <?php echo ($profile && $profile['major'] === 'Engineering') ? 'selected' : ''; ?>>Engineering</option>
                                    <option value="Graphic Design" <?php echo ($profile && $profile['major'] === 'Graphic Design') ? 'selected' : ''; ?>>Graphic Design</option>
                                    <option value="Psychology" <?php echo ($profile && $profile['major'] === 'Psychology') ? 'selected' : ''; ?>>Psychology</option>
                                    <option value="Communication" <?php echo ($profile && $profile['major'] === 'Communication') ? 'selected' : ''; ?>>Communication</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="looking_for">Mencari</label>
                                <select id="looking_for" name="looking_for" class="form-control">
                                    <option value="friends" <?php echo ($profile && $profile['looking_for'] === 'friends') ? 'selected' : ''; ?>>Teman</option>
                                    <option value="study_partner" <?php echo ($profile && $profile['looking_for'] === 'study_partner') ? 'selected' : ''; ?>>Partner Belajar</option>
                                    <option value="romance" <?php echo ($profile && $profile['looking_for'] === 'romance') ? 'selected' : ''; ?>>Romansa</option>
                                </select>
                            </div>
                            
                            <div class="upload-instructions">
                                <h5>Tips Foto Profil</h5>
                                <ul>
                                    <li>Gunakan foto wajah yang jelas</li>
                                    <li>Pastikan pencahayaan yang baik</li>
                                    <li>Format yang didukung: JPG, PNG, GIF (max 2MB)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bio & Interests Tab -->
                <div class="tab-content" id="bio-interests-content">
                    <div class="tab-content-inner">
                        <div class="form-section">
                            <h4>Ceritakan Tentang Dirimu</h4>
                            
                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" class="form-control bio-textarea" rows="5" placeholder="Ceritakan tentang dirimu, apa yang membuatmu unik, atau hal menarik tentangmu..."><?php echo $profile ? htmlspecialchars($profile['bio']) : ''; ?></textarea>
                                <div class="character-counter">
                                    <span id="bio-char-count"><?php echo $profile ? strlen($profile['bio']) : '0'; ?></span>/500
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="interests">Minat & Hobi</label>
                                <div class="interests-input-container">
                                    <input type="text" id="interest-input" class="form-control" placeholder="Tambahkan minat (tekan Enter)">
                                    <input type="hidden" id="interests" name="interests" value="<?php echo $profile ? htmlspecialchars($profile['interests']) : ''; ?>">
                                </div>
                                
                                <div class="interests-container" id="interests-display">
                                    <?php 
                                    if ($profile && !empty($profile['interests'])) {
                                        $interests_array = explode(',', $profile['interests']);
                                        foreach ($interests_array as $interest) {
                                            $interest = trim($interest);
                                            if (!empty($interest)) {
                                                echo '<span class="interest-tag">' . htmlspecialchars($interest) . ' <i class="fas fa-times"></i></span>';
                                            }
                                        }
                                    } else {
                                        echo '<span class="no-interests">Belum ada minat yang ditambahkan</span>';
                                    }
                                    ?>
                                </div>
                                <div class="form-hint">
                                    <i class="fas fa-lightbulb"></i> Tambahkan hobi, aktivitas, atau topik yang kamu sukai. Minat yang sama bisa menjadi awal percakapan yang baik!
                                </div>
                            </div>
                            
                            <div class="interest-suggestions">
                                <h5>Rekomendasi Minat</h5>
                                <div class="suggestion-tags">
                                    <span class="suggestion-tag" data-interest="Musik">Musik</span>
                                    <span class="suggestion-tag" data-interest="Film">Film</span>
                                    <span class="suggestion-tag" data-interest="Fotografi">Fotografi</span>
                                    <span class="suggestion-tag" data-interest="Traveling">Traveling</span>
                                    <span class="suggestion-tag" data-interest="Buku">Buku</span>
                                    <span class="suggestion-tag" data-interest="Gaming">Gaming</span>
                                    <span class="suggestion-tag" data-interest="Olahraga">Olahraga</span>
                                    <span class="suggestion-tag" data-interest="Coding">Coding</span>
                                    <span class="suggestion-tag" data-interest="Memasak">Memasak</span>
                                    <span class="suggestion-tag" data-interest="Seni">Seni</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Privacy Settings Tab -->
                <div class="tab-content" id="privacy-content">
                    <div class="tab-content-inner">
                        <div class="form-section">
                            <h4>Pengaturan Privasi</h4>
                            <p class="privacy-intro">Atur preferensi privasi untuk menentukan bagaimana profil dan informasi Anda ditampilkan kepada pengguna lain.</p>
                            
                            <div class="privacy-options">
                                <div class="privacy-option">
                                    <div class="privacy-option-info">
                                        <div class="privacy-option-icon">
                                            <i class="fas fa-search"></i>
                                        </div>
                                        <div class="privacy-option-text">
                                            <h5>Tampilkan Profil Dalam Pencarian</h5>
                                            <p>Izinkan pengguna lain menemukan profil Anda dalam hasil pencarian dan rekomendasi kecocokan.</p>
                                        </div>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="searchable" value="1" <?php echo ($profile && isset($profile['searchable']) && $profile['searchable'] == 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="privacy-option">
                                    <div class="privacy-option-info">
                                        <div class="privacy-option-icon">
                                            <i class="fas fa-circle"></i>
                                        </div>
                                        <div class="privacy-option-text">
                                            <h5>Tampilkan Status Online</h5>
                                            <p>Tampilkan status online Anda kepada pengguna lain.</p>
                                        </div>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="show_online" value="1" <?php echo ($profile && isset($profile['show_online']) && $profile['show_online'] == 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="privacy-option">
                                    <div class="privacy-option-info">
                                        <div class="privacy-option-icon">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div class="privacy-option-text">
                                            <h5>Terima Pesan dari Siapa Saja</h5>
                                            <p>Izinkan pesan dari pengguna yang belum terhubung dengan Anda.</p>
                                        </div>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="allow_messages" value="1" <?php echo ($profile && isset($profile['allow_messages']) && $profile['allow_messages'] == 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="privacy-option">
                                    <div class="privacy-option-info">
                                        <div class="privacy-option-icon">
                                            <i class="fas fa-graduation-cap"></i>
                                        </div>
                                        <div class="privacy-option-text">
                                            <h5>Tampilkan Jurusan</h5>
                                            <p>Tampilkan informasi jurusan Anda kepada pengguna lain.</p>
                                        </div>
                                    </div>
                                    <label class="toggle">
                                        <input type="checkbox" name="show_major" value="1" <?php echo ($profile && isset($profile['show_major']) && $profile['show_major'] == 1) ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="privacy-notice">
                                <i class="fas fa-shield-alt"></i>
                                <p>Kami menjaga keamanan data Anda. Pengaturan privasi ini hanya mengontrol apa yang dilihat oleh pengguna lain, bukan apa yang disimpan oleh sistem.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- CSS Styles for the redesigned Profile Page -->
    <style>
    /* Profile Hero Card */
    .profile-hero-card {
        background-color: var(--card-bg);
        border-radius: 15px;
        overflow: hidden;
        position: relative;
        margin-bottom: 25px;
        box-shadow: var(--card-shadow);
    }
    
    .profile-hero-backdrop {
        height: 120px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        position: relative;
    }
    
    .profile-hero-backdrop::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.1' fill-rule='evenodd'/%3E%3C/svg%3E");
        opacity: 0.7;
    }
    
    .profile-hero-content {
        padding: 0 30px 30px;
        position: relative;
        margin-top: -60px;
    }
    
    .profile-avatar-wrapper {
        display: flex;
        justify-content: center;
        margin-bottom: 15px;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        position: relative;
        border: 5px solid var(--card-bg);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        background-color: #f0f0f0;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .profile-avatar-edit {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 36px;
        height: 36px;
        background-color: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        transition: all 0.3s;
    }
    
    .profile-avatar-edit:hover {
        transform: scale(1.1);
        background-color: #e63e5c;
    }
    
    .profile-hero-info {
        text-align: center;
        margin-bottom: 25px;
    }
    
    .profile-hero-info h3 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .profile-meta {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .profile-meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-color);
        opacity: 0.8;
    }
    
    .profile-meta-item i {
        color: var(--primary);
    }
    
    /* Profile Progress */
    .profile-progress-container {
        background-color: rgba(0, 0, 0, 0.03);
        border-radius: 12px;
        padding: 20px;
        margin-top: 15px;
    }
    
    [data-theme="dark"] .profile-progress-container {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .profile-progress-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .profile-progress-percentage {
        color: var(--primary);
    }
    
    .profile-progress-bar {
        height: 8px;
        background-color: rgba(0, 0, 0, 0.05);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    [data-theme="dark"] .profile-progress-bar {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .profile-progress-fill {
        height: 100%;
        background: linear-gradient(to right, var(--primary), var(--accent));
        border-radius: 4px;
        transition: width 1s ease-in-out;
    }
    
    .profile-progress-tips {
        font-size: 13px;
        color: #666;
        text-align: center;
        font-style: italic;
    }
    
    /* Edit Form Card */
    .profile-edit-card {
        background-color: var(--card-bg);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
    }
    
    /* Modern Tab Design */
    .profile-tabs-container {
        padding: 20px 20px 0;
        position: relative;
    }
    
    .profile-tabs {
        display: flex;
        overflow-x: auto;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none; /* IE and Edge */
        border-bottom: 1px solid var(--border-color);
    }
    
    .profile-tabs::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }
    
    .profile-tab {
        padding: 15px 25px;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-color);
        opacity: 0.7;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        transition: all 0.3s;
        white-space: nowrap;
    }
    
    .profile-tab:hover {
        opacity: 1;
        color: var(--primary);
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    [data-theme="dark"] .profile-tab:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .profile-tab.active {
        color: var(--primary);
        opacity: 1;
    }
    
    .profile-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 3px;
        background-color: var(--primary);
        border-radius: 3px 3px 0 0;
    }
    
    .tab-save-button {
        position: absolute;
        right: 20px;
        top: 12px;
    }
    
    .tab-save-button .btn {
        padding: 10px 20px;
        font-weight: 500;
    }
    
    /* Tab Contents */
    .profile-tab-contents {
        padding: 0;
    }
    
    .tab-content {
        display: none;
        animation: fadeIn 0.4s ease-in-out;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .tab-content-inner {
        padding: 30px;
    }
    
    .form-section {
        margin-bottom: 30px;
    }
    
    .form-section h4 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--text-color);
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    
    /* Form Styling */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .input-wrapper {
        position: relative;
    }
    
    .input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(153, 153, 153, 0.5);
        opacity: 0.7;
        z-index: 0;
        font-size: 14px;
        pointer-events: none;
    }
    
    .form-control {
        padding: 12px 15px 12px 45px;
        border: 1px solid var(--input-border);
        border-radius: 10px;
        width: 100%;
        font-size: 16px;
        background-color: var(--input-bg);
        color: var(--text-color);
        transition: all 0.3s;
        position: relative;
        z-index: 1;
    }
    
    .form-control::placeholder {
        color: #999;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 75, 110, 0.1);
    }
    
    .form-control:disabled,
    .form-control[readonly] {
        background-color: rgba(0, 0, 0, 0.03);
        cursor: not-allowed;
    }
    
    select.form-control {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 15px center;
        padding-right: 40px;
    }
    
    .bio-textarea {
        padding: 15px;
        min-height: 150px;
        resize: vertical;
    }
    
    .character-counter {
        display: flex;
        justify-content: flex-end;
        font-size: 12px;
        color: #999;
        margin-top: 5px;
    }
    
    .form-hint {
        margin-top: 8px;
        font-size: 13px;
        color: #666;
    }
    
    /* Interest Tags */
    .interests-input-container {
        margin-bottom: 15px;
    }
    
    .interests-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        min-height: 50px;
        margin-bottom: 10px;
        padding: 10px;
        border-radius: 10px;
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    [data-theme="dark"] .interests-container {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .no-interests {
        font-style: italic;
        color: #999;
    }
    
    .interest-tag {
        background-color: var(--primary);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        transition: all 0.3s;
        animation: scaleIn 0.3s ease-out;
    }
    
    .interest-tag i {
        margin-left: 8px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .interest-tag i:hover {
        color: rgba(255, 255, 255, 0.7);
    }
    
    @keyframes scaleIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    
    /* Interest Suggestions */
    .interest-suggestions {
        margin-top: 25px;
    }
    
    .interest-suggestions h5 {
        font-size: 15px;
        font-weight: 500;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .suggestion-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .suggestion-tag {
        background-color: rgba(0, 0, 0, 0.05);
        color: var(--text-color);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    [data-theme="dark"] .suggestion-tag {
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    .suggestion-tag:hover {
        background-color: var(--secondary);
        color: var(--primary);
    }
    
    /* Upload Instructions */
    .upload-instructions {
        background-color: rgba(0, 0, 0, 0.03);
        padding: 15px;
        border-radius: 10px;
        margin-top: 20px;
    }
    
    [data-theme="dark"] .upload-instructions {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .upload-instructions h5 {
        font-size: 15px;
        font-weight: 500;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .upload-instructions ul {
        padding-left: 20px;
        margin: 0;
    }
    
    .upload-instructions li {
        font-size: 13px;
        color: #666;
        margin-bottom: 5px;
    }
    
    /* Privacy Tab Styling */
    .privacy-intro {
        margin-bottom: 25px;
        color: #666;
    }
    
    .privacy-options {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .privacy-option {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background-color: rgba(0, 0, 0, 0.02);
        border-radius: 12px;
        transition: all 0.3s;
    }
    
    [data-theme="dark"] .privacy-option {
        background-color: rgba(255, 255, 255, 0.03);
    }
    
    .privacy-option:hover {
        background-color: rgba(255, 75, 110, 0.05);
    }
    
    .privacy-option-info {
        display: flex;
        gap: 15px;
        align-items: flex-start;
        flex: 1;
    }
    
    .privacy-option-icon {
        width: 36px;
        height: 36px;
        background-color: var(--secondary);
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .privacy-option-text h5 {
        font-size: 16px;
        font-weight: 500;
        margin-bottom: 5px;
        color: var(--text-color);
    }
    
    .privacy-option-text p {
        font-size: 13px;
        color: #666;
        margin: 0;
    }
    
    /* Toggle Switch */
    .toggle {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 26px;
        flex-shrink: 0;
    }
    
    .toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background-color: var(--primary);
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }
    
    .privacy-notice {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        margin-top: 30px;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.03);
        border-radius: 10px;
    }
    
    [data-theme="dark"] .privacy-notice {
        background-color: rgba(255, 255, 255, 0.05);
    }
    
    .privacy-notice i {
        color: var(--primary);
        font-size: 20px;
        margin-top: 2px;
    }
    
    .privacy-notice p {
        margin: 0;
        font-size: 13px;
        color: #666;
    }
    
    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .profile-tab {
            padding: 15px 20px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .tab-save-button {
            position: static;
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
    }
    
    @media (max-width: 768px) {
        .profile-hero-content {
            padding: 0 20px 20px;
        }
        
        .profile-tab span {
            display: none;
        }
        
        .profile-tab i {
            font-size: 18px;
        }
        
        .profile-tab {
            padding: 15px;
        }
        
        .tab-content-inner {
            padding: 20px;
        }
        
        .privacy-option {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .toggle {
            align-self: flex-end;
        }
    }
    </style>
    
    <!-- JavaScript for Profile Page -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.profile-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(`${targetTab}-content`).classList.add('active');
                });
            });
            
            // Profile picture upload preview
            const profilePic = document.getElementById('profile_pic');
            const profilePreview = document.getElementById('profile-preview-img');
            const avatarEditBtn = document.querySelector('.profile-avatar-edit');
            
            if (profilePic && profilePreview && avatarEditBtn) {
                avatarEditBtn.addEventListener('click', function() {
                    profilePic.click();
                });
                
                profilePic.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            profilePreview.src = e.target.result;
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            // Interests functionality
            const interestInput = document.getElementById('interest-input');
            const interestsField = document.getElementById('interests');
            const interestsDisplay = document.getElementById('interests-display');
            const suggestionTags = document.querySelectorAll('.suggestion-tag');
            
            // Function to add an interest
            function addInterest(interest) {
                // Clean up the interest
                interest = interest.trim();
                if (interest === '') return;
                
                // Get current interests
                let currentInterests = interestsField.value ? 
                    interestsField.value.split(',').map(i => i.trim()).filter(i => i !== '') : 
                    [];
                
                // Check if interest already exists
                if (currentInterests.includes(interest)) return;
                
                // Add to interests array
                currentInterests.push(interest);
                
                // Update hidden field
                interestsField.value = currentInterests.join(', ');
                
                // Update display
                updateInterestsDisplay();
            }
            
            // Function to update interests display
            function updateInterestsDisplay() {
                const interests = interestsField.value ? 
                    interestsField.value.split(',').map(i => i.trim()).filter(i => i !== '') : 
                    [];
                
                if (interests.length > 0) {
                    interestsDisplay.innerHTML = '';
                    
                    interests.forEach(interest => {
                        const tag = document.createElement('span');
                        tag.className = 'interest-tag';
                        tag.innerHTML = interest + ' <i class="fas fa-times"></i>';
                        
                        // Add remove functionality
                        tag.querySelector('i').addEventListener('click', function() {
                            removeInterest(interest);
                        });
                        
                        interestsDisplay.appendChild(tag);
                    });
                } else {
                    interestsDisplay.innerHTML = '<span class="no-interests">Belum ada minat yang ditambahkan</span>';
                }
            }
            
            // Function to remove an interest
            function removeInterest(interest) {
                // Get current interests
                let currentInterests = interestsField.value ? 
                    interestsField.value.split(',').map(i => i.trim()).filter(i => i !== '') : 
                    [];
                
                // Remove the interest
                currentInterests = currentInterests.filter(i => i !== interest);
                
                // Update hidden field
                interestsField.value = currentInterests.join(', ');
                
                // Update display
                updateInterestsDisplay();
            }
            
            // Add event listener to interest input
            if (interestInput) {
                interestInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        addInterest(this.value);
                        this.value = '';
                    }
                });
            }
            
            // Add event listener to suggestion tags
            suggestionTags.forEach(tag => {
                tag.addEventListener('click', function() {
                    const interest = this.getAttribute('data-interest');
                    addInterest(interest);
                    interestInput.focus();
                });
            });
            
            // Initialize interests display
            if (interestsDisplay) {
                updateInterestsDisplay();
                
                // Add remove functionality to existing tags
                document.querySelectorAll('.interest-tag i').forEach(removeIcon => {
                    removeIcon.addEventListener('click', function() {
                        const interestText = this.parentNode.textContent.trim().slice(0, -1).trim();
                        removeInterest(interestText);
                    });
                });
            }
            
            // Bio character counter
            const bioTextarea = document.getElementById('bio');
            const bioCharCount = document.getElementById('bio-char-count');
            
            if (bioTextarea && bioCharCount) {
                bioTextarea.addEventListener('input', function() {
                    const count = this.value.length;
                    bioCharCount.textContent = count;
                    
                    if (count > 450) {
                        bioCharCount.style.color = '#dc3545';
                    } else if (count > 400) {
                        bioCharCount.style.color = '#ffc107';
                    } else {
                        bioCharCount.style.color = '#999';
                    }
                });
            }
        });
    </script>
<?php endif; ?>
                                
                            <!-- Menfess Section for Dashboard -->
        <?php if ($page === 'menfess'): ?>
            <div class="dashboard-header">
                <h2>Crush Menfess</h2>
                <p>Kirim pesan anonim ke crush Anda. Jika keduanya saling suka, nama akan terungkap!</p>
            </div>
            
            <?php if (!empty($menfess_message)): ?>
            <div class="alert <?php echo strpos($menfess_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                <?php echo $menfess_message; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-paper-plane"></i> Kirim Menfess</h3>
                </div>
                <div class="menfess-form-container">
                    <form id="menfessForm" method="post" action="dashboard?page=menfess">
                        <div class="form-group">
                            <label for="crush_search">Cari Crush</label>
                            <div class="search-wrapper">
                                <input type="text" id="crush_search" class="form-control" placeholder="Ketik nama crush..." autocomplete="off">
                                <div id="search-results" class="search-results"></div>
                                <input type="hidden" name="crush_id" id="crush_id" required>
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <div class="selected-crush" id="selected-crush">
                                <span class="selected-label">Crush yang dipilih:</span>
                                <span class="no-crush-selected">Belum ada crush yang dipilih</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Pesan Menfess</label>
                            <textarea 
                                id="message" 
                                name="message" 
                                class="form-control" 
                                rows="4" 
                                placeholder="Tulis pesan rahasia untuk crush-mu..."
                                maxlength="280"
                                required></textarea>
                            <div class="character-counter">
                                <span id="char-count">0</span>/280
                            </div>
                            <div class="form-hint">
                                <i class="fas fa-info-circle"></i> Menfess ini akan dikirim secara anonim. Identitas Anda hanya terungkap jika keduanya saling menyukai pesan.
                            </div>
                        </div>
                        
                        <div class="form-buttons">
                            <button type="submit" name="send_menfess" class="btn">
                                <i class="fas fa-paper-plane"></i> Kirim Menfess
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Menfess Manager Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-envelope"></i> Menfess Manager</h3>
                </div>
                
                <!-- Custom Tabs Design -->
                <div class="custom-tabs">
                    <div class="tab-buttons">
                        <button class="tab-button active" data-tab="received-menfess">
                            <i class="fas fa-inbox"></i> Diterima
                            <span class="tab-badge"><?php echo count(array_filter($menfess_messages, function($msg) { return $msg['type'] === 'received'; })); ?></span>
                        </button>
                        <button class="tab-button" data-tab="sent-menfess">
                            <i class="fas fa-paper-plane"></i> Dikirim
                            <span class="tab-badge"><?php echo count(array_filter($menfess_messages, function($msg) { return $msg['type'] === 'sent'; })); ?></span>
                        </button>
                        <button class="tab-button" data-tab="matches-menfess">
                            <i class="fas fa-heart"></i> Matches
                            <span class="tab-badge"><?php echo count($matches); ?></span>
                        </button>
                    </div>
                    
                    <!-- Tab Contents -->
                    <div class="tab-contents">
                        <!-- Received Menfess Tab -->
                        <div id="received-menfess" class="tab-content active">
                            <?php
                            $received_menfess = array_filter($menfess_messages, function($msg) {
                                return $msg['type'] === 'received';
                            });
                            
                            if (empty($received_menfess)):
                            ?>
                            <div class="empty-menfess">
                                <div class="empty-icon">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <h3>Belum Ada Pesan Menfess</h3>
                                <p>Belum ada yang mengirimkan menfess kepadamu. Tunggu seseorang untuk mengungkapkan perasaannya!</p>
                            </div>
                            <?php else: ?>
                            <div class="menfess-list">
                                <?php foreach ($received_menfess as $menfess): ?>
                                <div class="menfess-card received">
                                    <div class="menfess-header">
                                        <div class="menfess-from">
                                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                                            <i class="fas fa-user-circle"></i> 
                                            <span>Dari: <?php 
                                                // Get sender name
                                                $sender_sql = "SELECT name FROM users WHERE id = ?";
                                                $sender_stmt = $conn->prepare($sender_sql);
                                                $sender_stmt->bind_param("i", $menfess['sender_id']);
                                                $sender_stmt->execute();
                                                $sender_result = $sender_stmt->get_result();
                                                $sender = $sender_result->fetch_assoc();
                                                echo htmlspecialchars($sender['name']); 
                                            ?></span>
                                            <span class="menfess-match-badge">
                                                <i class="fas fa-heart"></i> Match!
                                            </span>
                                            <?php else: ?>
                                            <i class="fas fa-mask"></i> 
                                            <span>Penggemar Rahasia</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="menfess-time"><?php echo date('d M Y', strtotime($menfess['created_at'])); ?></div>
                                    </div>
                                    <div class="menfess-content">
                                        <?php echo nl2br(htmlspecialchars($menfess['message'])); ?>
                                    </div>
                                    <div class="menfess-actions">
                                        <form method="post">
                                            <input type="hidden" name="menfess_id" value="<?php echo $menfess['id']; ?>">
                                            <button type="submit" name="like_menfess" class="menfess-like <?php echo $menfess['liked'] ? 'liked' : ''; ?>">
                                                <i class="<?php echo $menfess['liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                                <span><?php echo $menfess['liked'] ? 'Disukai' : 'Suka'; ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sent Menfess Tab -->
                        <div id="sent-menfess" class="tab-content">
                            <?php
                            $sent_menfess = array_filter($menfess_messages, function($msg) {
            return $msg['type'] === 'sent';
        });
                            
                            if (empty($sent_menfess)):
                            ?>
                            <div class="empty-menfess">
                                <div class="empty-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <h3>Belum Mengirim Menfess</h3>
                                <p>Kamu belum mengirimkan menfess ke siapapun. Ayo ungkapkan perasaanmu!</p>
                                <button class="btn btn-outline send-first-menfess">
                                    <i class="fas fa-paper-plane"></i> Kirim Menfess Pertama
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="menfess-list">
                                <?php foreach ($sent_menfess as $menfess): ?>
                                <div class="menfess-card sent">
                                    <div class="menfess-header">
                                        <div class="menfess-from">
                                            <i class="fas fa-paper-plane"></i> 
                                            <span>Kepada: <?php 
                                                // Get receiver name
                                                $receiver_sql = "SELECT name FROM users WHERE id = ?";
                                                $receiver_stmt = $conn->prepare($receiver_sql);
                                                $receiver_stmt->bind_param("i", $menfess['receiver_id']);
                                                $receiver_stmt->execute();
                                                $receiver_result = $receiver_stmt->get_result();
                                                $receiver = $receiver_result->fetch_assoc();
                                                echo htmlspecialchars($receiver['name']); 
                                            ?></span>
                                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                                            <span class="menfess-match-badge">
                                                <i class="fas fa-heart"></i> Match!
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="menfess-time"><?php echo date('d M Y', strtotime($menfess['created_at'])); ?></div>
                                    </div>
                                    <div class="menfess-content">
                                        <?php echo nl2br(htmlspecialchars($menfess['message'])); ?>
                                    </div>
                                    <div class="menfess-actions">
                                        <form method="post">
                                            <input type="hidden" name="menfess_id" value="<?php echo $menfess['id']; ?>">
                                            <button type="submit" name="like_menfess" class="menfess-like <?php echo $menfess['liked'] ? 'liked' : ''; ?>">
                                                <i class="<?php echo $menfess['liked'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                                <span><?php echo $menfess['liked'] ? 'Disukai' : 'Suka'; ?></span>
                                            </button>
                                        </form>
                                        <div class="menfess-status">
                                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                                            <span class="match-status">
                                                <i class="fas fa-heart"></i> Match!
                                            </span>
                                            <?php else: ?>
                                            <span class="pending-status">
                                                <i class="far fa-clock"></i> Menunggu mutual like
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Matches Tab -->
                        <div id="matches-menfess" class="tab-content">
                            <?php if (empty($matches)): ?>
                            <div class="empty-menfess">
                                <div class="empty-icon">
                                    <i class="fas fa-heart-broken"></i>
                                </div>
                                <h3>Belum Ada Match</h3>
                                <p>Kirim menfess dan like pesan untuk menemukan match! Matches terjadi ketika kamu dan crush sama-sama menyukai pesan menfess.</p>
                                <button class="btn btn-outline send-first-menfess">
                                    <i class="fas fa-paper-plane"></i> Kirim Menfess
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="matches-grid">
                                <?php foreach ($matches as $match): ?>
                                <div class="match-card">
                                    <div class="match-image">
                                        <img src="<?php echo !empty($match['profile_pic']) ? htmlspecialchars($match['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>">
                                        <div class="match-badge">
                                            <i class="fas fa-heart"></i> Match!
                                        </div>
                                    </div>
                                    <div class="match-info">
                                        <h3 class="match-name"><?php echo htmlspecialchars($match['name']); ?></h3>
                                        <div class="match-bio">
                                            <?php echo isset($match['bio']) && !empty($match['bio']) ? nl2br(htmlspecialchars(substr($match['bio'], 0, 100) . (strlen($match['bio']) > 100 ? '...' : ''))) : 'Belum ada bio.'; ?>
                                        </div>
                                        <div class="match-actions">
                                            <a href="view_profile?id=<?php echo $match['id']; ?>" class="btn btn-outline">
                                                <i class="fas fa-user"></i> Lihat Profil
                                            </a>
                                            <a href="start_chat?user_id=<?php echo $match['id']; ?>" class="btn">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add custom CSS for Menfess page -->
            <style>

                .menfess-actions {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 15px;
                    border-top: 1px solid rgba(0, 0, 0, 0.05);
                    background-color: rgba(0, 0, 0, 0.01);
                }

                .match-status {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 13px;
                    color: #28a745;
                }

                .pending-status {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 13px;
                    color: #888;
                }

                /* Menfess Form Styling */
                .menfess-form-container {
                    padding: 20px;
                }

                .search-wrapper {
                    position: relative;
                }
                
                .search-icon {
                    position: absolute;
                    right: 15px;
                    top: 15px;
                    transform: translateY(0);
                    color: #999;
                }
                
                .search-results {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #ddd;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    max-height: 200px;
                    overflow-y: auto;
                    z-index: 100;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                    display: none;
                }
                
                .search-results.active {
                    display: block;
                }
                
                .search-item {
                    padding: 10px 15px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                    transition: background-color 0.2s;
                }
                
                .search-item:hover {
                    background-color: #f5f5f5;
                }
                
                .search-item:last-child {
                    border-bottom: none;
                }
                
                .selected-crush {
                    margin-top: 10px;
                    padding: 10px;
                    border-radius: 5px;
                    background-color: #f8f9fa;
                    display: flex;
                    align-items: center;
                }
                
                .selected-label {
                    font-weight: 500;
                    margin-right: 10px;
                    color: #666;
                }
                
                .no-crush-selected {
                    color: #999;
                    font-style: italic;
                }
                
                .crush-tag {
                    display: inline-flex;
                    align-items: center;
                    background-color: var(--secondary);
                    color: var(--primary);
                    padding: 5px 10px;
                    border-radius: 15px;
                    margin-left: 5px;
                    font-size: 14px;
                }
                
                .crush-tag i {
                    margin-left: 5px;
                    cursor: pointer;
                }

                .character-counter {
                    text-align: right;
                    font-size: 12px;
                    color: #666;
                    margin-top: 5px;
                }

                .form-hint {
                    margin-top: 10px;
                    font-size: 13px;
                    color: #666;
                    background-color: #f8f9fa;
                    padding: 10px;
                    border-radius: 5px;
                    border-left: 3px solid var(--primary);
                }

                /* Custom Tabs */
                .custom-tabs {
                    margin-top: 10px;
                }

                .tab-buttons {
                    display: flex;
                    border-bottom: 1px solid var(--border-color);
                    overflow-x: auto;
                    scrollbar-width: none; /* Firefox */
                    -ms-overflow-style: none; /* IE and Edge */
                }

                .tab-buttons::-webkit-scrollbar {
                    display: none; /* Chrome, Safari, Opera */
                }

                .tab-button {
                    padding: 12px 20px;
                    background: none;
                    border: none;
                    color: #666;
                    font-weight: 500;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    white-space: nowrap;
                    transition: all 0.3s;
                    position: relative;
                }

                .tab-button.active {
                    color: var(--primary);
                }

                .tab-button.active::after {
                    content: '';
                    position: absolute;
                    bottom: -1px;
                    left: 0;
                    width: 100%;
                    height: 2px;
                    background-color: var(--primary);
                }

                .tab-badge {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    min-width: 20px;
                    height: 20px;
                    padding: 0 5px;
                    border-radius: 10px;
                    background-color: var(--secondary);
                    color: var(--primary);
                    font-size: 12px;
                    font-weight: bold;
                }

                .tab-button:hover {
                    background-color: rgba(255, 75, 110, 0.05);
                }

                .tab-content {
                    display: none;
                    padding: 20px;
                    animation: fadeIn 0.3s ease;
                }

                .tab-content.active {
                    display: block;
                }

                /* Menfess Cards */
                .menfess-list {
                    display: flex;
                    flex-direction: column;
                    gap: 15px;
                }

                .menfess-card {
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .menfess-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                }

                .menfess-card.received {
                    background-color: #f8f9fa;
                    border-left: 4px solid #0080ff;
                }

                .menfess-card.sent {
                    background-color: var(--secondary);
                    border-left: 4px solid var(--primary);
                }

                .menfess-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px;
                    background-color: rgba(0, 0, 0, 0.02);
                    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                }

                .menfess-from {
                    font-weight: 500;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .menfess-time {
                    font-size: 13px;
                    color: #888;
                }

                .menfess-content {
                    padding: 15px;
                    line-height: 1.6;
                    font-size: 15px;
                }

                .menfess-actions {
                    display: flex;
                    justify-content: space-between;
                    padding: 10px 15px;
                    border-top: 1px solid rgba(0, 0, 0, 0.05);
                    background-color: rgba(0, 0, 0, 0.01);
                }

                .menfess-like {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 6px 12px;
                    border-radius: 20px;
                    background: none;
                    border: 1px solid #ddd;
                    cursor: pointer;
                    transition: all 0.2s;
                    color: #666;
                }

                .menfess-like:hover {
                    background-color: rgba(255, 75, 110, 0.1);
                    border-color: var(--primary);
                    color: var(--primary);
                }

                .menfess-like.liked {
                    background-color: rgba(255, 75, 110, 0.1);
                    border-color: var(--primary);
                    color: var(--primary);
                }

                .menfess-like.liked i {
                    color: var(--primary);
                }

                .menfess-match-badge {
                    display: inline-flex;
                    align-items: center;
                    padding: 3px 8px;
                    border-radius: 12px;
                    background-color: #d4edda;
                    color: #155724;
                    font-size: 12px;
                    margin-left: 10px;
                }

                .menfess-match-badge i {
                    margin-right: 4px;
                }

                .liked-status, .pending-status {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 13px;
                }

                .liked-status {
                    color: var(--primary);
                }

                .pending-status {
                    color: #888;
                }

                /* Empty States */
                .empty-menfess {
                    text-align: center;
                    padding: 40px 20px;
                }

                .empty-icon {
                    font-size: 50px;
                    color: #ddd;
                    margin-bottom: 20px;
                    animation: pulse 2s infinite;
                }

                @keyframes pulse {
                    0% {
                        transform: scale(1);
                        opacity: 0.7;
                    }
                    50% {
                        transform: scale(1.05);
                        opacity: 1;
                    }
                    100% {
                        transform: scale(1);
                        opacity: 0.7;
                    }
                }

                .empty-menfess h3 {
                    font-size: 20px;
                    margin-bottom: 10px;
                    color: #555;
                }

                .empty-menfess p {
                    color: #888;
                    max-width: 400px;
                    margin: 0 auto 20px;
                }

                /* Match Cards */
                .matches-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                }

                .match-card {
                    background-color: var(--card-bg);
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
                    transition: transform 0.3s, box-shadow 0.3s;
                }

                .match-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                }

                .match-image {
                    height: 180px;
                    position: relative;
                    overflow: hidden;
                }

                .match-image img {
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    transition: transform 0.5s;
                }

                .match-card:hover .match-image img {
                    transform: scale(1.05);
                }

                .match-badge {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    padding: 5px 10px;
                    border-radius: 15px;
                    background-color: rgba(255, 75, 110, 0.9);
                    color: white;
                    font-size: 12px;
                    font-weight: bold;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
                }

                .match-info {
                    padding: 15px;
                }

                .match-name {
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: var(--text-color);
                }

                .match-bio {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 15px;
                    display: -webkit-box;
                    -webkit-line-clamp: 2;
                    -webkit-box-orient: vertical;
                    overflow: hidden;
                    height: 40px;
                }

                .match-actions {
                    display: flex;
                    gap: 8px;
                }

                .match-actions a {
                    flex: 1;
                    padding: 8px 0;
                    font-size: 14px;
                    text-align: center;
                }

                /* Responsive Styles */
                @media (max-width: 768px) {
                    .matches-grid {
                        grid-template-columns: 1fr;
                    }

                    .tab-button {
                        padding: 10px 15px;
                        font-size: 14px;
                    }
                }

                /* Dark mode styles for Menfess section */
        [data-theme="dark"] .selected-crush {
            background-color: #662d39; /* Darker pink background in dark mode */
            color: #ffd9e0; /* Light pink text color in dark mode */
        }

        [data-theme="dark"] .selected-label {
            color: #ffd9e0; /* Light pink color for label text in dark mode */
        }

        [data-theme="dark"] .no-crush-selected {
            color: #ff8fa3; /* Brighter pink for the "no crush selected" text */
        }

        [data-theme="dark"] .crush-tag {
            background-color: #ff4b6e; /* Brighter pink background for the tag */
            color: #ffffff; /* White text for better contrast */
        }

        /* Ensure dark mode has good contrast for search results */
        [data-theme="dark"] .search-results {
            background-color: #1e1e1e; /* Dark background for results dropdown */
            border-color: #444444;
        }

        [data-theme="dark"] .search-item {
            border-color: #333333;
        }

        [data-theme="dark"] .search-item:hover {
            background-color: #662d39; /* Dark pink hover state */
            color: #ffffff;
        }
            </style>

            <!-- JavaScript for Menfess functionality -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Tab switching
                    const tabButtons = document.querySelectorAll('.tab-button');
                    const tabContents = document.querySelectorAll('.tab-content');
                    
                    tabButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            // Remove active class from all tabs
                            tabButtons.forEach(btn => btn.classList.remove('active'));
                            tabContents.forEach(content => content.classList.remove('active'));
                            
                            // Add active class to clicked tab
                            this.classList.add('active');
                            const tabId = this.getAttribute('data-tab');
                            document.getElementById(tabId).classList.add('active');
                        });
                    });
                    
                    // Character counter for message textarea
                    const messageTextarea = document.getElementById('message');
                    const charCount = document.getElementById('char-count');
                    
                    if (messageTextarea && charCount) {
                        messageTextarea.addEventListener('input', function() {
                            const count = this.value.length;
                            charCount.textContent = count;
                            
                            // Add visual feedback when approaching limit
                            if (count > 260) {
                                charCount.style.color = '#dc3545';
                            } else if (count > 220) {
                                charCount.style.color = '#ffc107';
                            } else {
                                charCount.style.color = '#666';
                            }
                        });
                    }
                    
                    // User search functionality
                    const crushSearch = document.getElementById('crush_search');
                    const searchResults = document.getElementById('search-results');
                    const crushIdField = document.getElementById('crush_id');
                    const selectedCrush = document.getElementById('selected-crush');
                    
                    // Initial users data from PHP
                    const users = <?php echo json_encode($users); ?>;
                    
                    if (crushSearch && searchResults) {
                        // Search function
                        crushSearch.addEventListener('input', function() {
                            const query = this.value.toLowerCase().trim();
                            
                            // Clear results
                            searchResults.innerHTML = '';
                            
                            if (query.length < 2) {
                                searchResults.classList.remove('active');
                                return;
                            }
                            
                            // Filter users by name
                            const filteredUsers = users.filter(user => 
                                user.name.toLowerCase().includes(query)
                            ).slice(0, 10); // Limit to first 10 results
                            
                            if (filteredUsers.length > 0) {
                                searchResults.classList.add('active');
                                
                                // Create result items
                                filteredUsers.forEach(user => {
                                    const item = document.createElement('div');
                                    item.className = 'search-item';
                                    item.textContent = user.name;
                                    item.dataset.userId = user.id;
                                    item.dataset.userName = user.name;
                                    
                                    // Handle click on result
                                    item.addEventListener('click', function() {
                                        const userId = this.dataset.userId;
                                        const userName = this.dataset.userName;
                                        
                                        // Set hidden input value
                                        crushIdField.value = userId;
                                        
                                        // Update display
                                        selectedCrush.innerHTML = `
                                            <span class="selected-label">Crush yang dipilih:</span>
                                            <span class="crush-tag">${userName} <i class="fas fa-times remove-crush"></i></span>
                                        `;
                                        
                                        // Add event listener to remove button
                                        const removeBtn = selectedCrush.querySelector('.remove-crush');
                                        if (removeBtn) {
                                            removeBtn.addEventListener('click', function(e) {
                                                e.stopPropagation();
                                                crushIdField.value = '';
                                                selectedCrush.innerHTML = `
                                                    <span class="selected-label">Crush yang dipilih:</span>
                                                    <span class="no-crush-selected">Belum ada crush yang dipilih</span>
                                                `;
                                                crushSearch.value = '';
                                            });
                                        }
                                        
                                        // Clear and hide search results
                                        crushSearch.value = '';
                                        searchResults.innerHTML = '';
                                        searchResults.classList.remove('active');
                                    });
                                    
                                    searchResults.appendChild(item);
                                });
                            } else {
                                searchResults.classList.add('active');
                                searchResults.innerHTML = '<div class="search-item">Tidak ada hasil yang cocok</div>';
                            }
                        });
                        
                        // Hide search results when clicking outside
                        document.addEventListener('click', function(e) {
                            if (!crushSearch.contains(e.target) && !searchResults.contains(e.target)) {
                                searchResults.classList.remove('active');
                            }
                        });
                        
                        // Prevent form submission if no crush is selected
                        document.getElementById('menfessForm').addEventListener('submit', function(e) {
                            if (!crushIdField.value) {
                                e.preventDefault();
                                alert('Harap pilih crush terlebih dahulu');
                                crushSearch.focus();
                            }
                        });
                    }
                    
                    // Button to scroll to menfess form
                    const sendFirstButtons = document.querySelectorAll('.send-first-menfess');
                    
                    sendFirstButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            // Scroll to the menfess form
                            const formCard = document.querySelector('.card:first-of-type');
                            formCard.scrollIntoView({ behavior: 'smooth' });
                            
                            // Focus on the crush search field
                            setTimeout(() => {
                                document.getElementById('crush_search').focus();
                            }, 500);
                        });
                    });
                });
            </script>
        <?php endif; ?>

                        <?php if ($page === 'chat'): ?>
                                <div class="dashboard-header">
                                    <h2>Chat</h2>
                                    <p>Chat dengan mahasiswa lain.</p>
                                </div>
                                
                                <?php if (!empty($chat_message)): ?>
                                <div class="alert <?php echo strpos($chat_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                                    <?php echo $chat_message; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Mulai Chat Baru</h3>
                                    </div>
                                    <p>Mulai chat dengan mahasiswa acak.</p>
                                    <form method="post" style="margin-top: 20px;">
                                        <button type="submit" name="start_blind_chat" class="btn">Mulai Chat Baru</button>
                                    </form>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Chat Aktif</h3>
                                    </div>
                                    <div class="chat-list">
                                        <?php if (empty($chat_sessions)): ?>
                                            <p>Belum ada chat aktif.</p>
                                        <?php else: ?>
                                            <?php foreach ($chat_sessions as $session): ?>
                                                <a href="chat?session_id=<?php echo $session['id']; ?>" class="chat-item">
                                                    <div class="chat-avatar">
                                                        <img src="<?php echo !empty($session['profile_pic']) ? htmlspecialchars($session['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="Avatar">
                                                    </div>
                                                    <div class="chat-info">
                                                        <div class="chat-name">
                                                            <?php echo htmlspecialchars($session['partner_name']); ?>
                                                        </div>
                                                        <div class="chat-last-msg">Klik untuk melihat percakapan</div>
                                                    </div>
                                                    <div class="chat-time">
                                                    <?php 
                                                    if (isset($session['last_message_time']) && !empty($session['last_message_time'])) {
                                                        echo date('d M', strtotime($session['last_message_time'])); 
                                                    } else {
                                                        echo 'Baru';
                                                    }
                                                    ?>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                        <?php if ($page === 'compatibility'): ?>
                                <div class="dashboard-header">
                                    <h2>Tes Kecocokan</h2>
                                    <p>Ikuti tes untuk menemukan pasangan yang cocok berdasarkan kepribadian, jurusan, dan minat.</p>
                                </div>
                                
                                <?php if (!empty($test_message)): ?>
                                <div class="alert <?php echo strpos($test_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                                    <?php echo $test_message; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!$test_taken): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h3>Tes Kecocokan</h3>
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
                                            <h4><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h4>
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
                                        <h3>Hasil Tes Kecocokan</h3>
                                    </div>
                                    <p>Berdasarkan jawaban dan profil Anda, kami telah menemukan orang-orang yang cocok dengan Anda.</p>
                                    
                                    <div class="score-details" style="display: flex; justify-content: space-between; padding: 10px 15px; background-color: var(--card-bg); border-radius: 5px; margin-bottom: 15px;">
                                        <div class="score-item" style="text-align: center;">
                                            <div class="score-value" style="font-size: 18px; font-weight: 500; color: var(--primary);"><?php echo isset($test_results['personality_score']) ? round($test_results['personality_score']) : '0'; ?></div>
                                            <div class="score-label" style="font-size: 12px; color: #666;">Skor Kepribadian</div>
                                        </div>
                                        <div class="score-item" style="text-align: center;">
                                            <div class="score-value" style="font-size: 18px; font-weight: 500; color: var(--primary);"><?php echo isset($test_results['major']) && !empty($test_results['major']) ? htmlspecialchars($test_results['major']) : 'Tidak ada'; ?></div>
                                            <div class="score-label" style="font-size: 12px; color: #666;">Jurusan</div>
                                        </div>
                                        <div class="score-item" style="text-align: center;">
                                            <div class="score-value" style="font-size: 18px; font-weight: 500; color: var(--primary);"><?php echo count($compatible_matches); ?></div>
                                            <div class="score-label" style="font-size: 12px; color: #666;">Kecocokan Ditemukan</div>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h3>Pasangan Yang Cocok</h3>
                                        <a href="compatibility?reset=true" class="btn btn-outline">Ambil Tes Ulang</a>
                                    </div>
                                    
                                    <?php if (empty($compatible_matches)): ?>
                                    <div style="text-align: center; padding: 40px 0;">
                                        <i class="fas fa-search" style="font-size: 50px; color: #ccc; margin-bottom: 20px;"></i>
                                        <h3 style="font-size: 20px; margin-bottom: 10px; color: #666;">Belum Ada Kecocokan</h3>
                                        <p style="color: #999; margin-bottom: 20px;">Kami belum menemukan kecocokan berdasarkan hasil tes Anda. Silakan coba lagi nanti.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="user-grid">
                                        <?php foreach ($compatible_matches as $match): ?>
                                        <div class="user-card"> 
                                            <div class="user-card-img">
                                                <img src="<?php echo isset($match['profile_pic']) && !empty($match['profile_pic']) ? htmlspecialchars($match['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>">
                                            </div>
                                            <div class="user-card-info">
                                                <h3>
                                                    <?php echo htmlspecialchars($match['name']); ?>
                                                    <span style="float: right; background-color: var(--primary); color: white; padding: 3px 8px; border-radius: 15px; font-size: 14px;"><?php echo round($match['compatibility_score']); ?>%</span>
                                                </h3>
                                                <p style="margin-bottom: 10px; color: #666; font-size: 14px;"><?php echo isset($match['major']) && !empty($match['major']) ? htmlspecialchars($match['major']) : 'Jurusan tidak diketahui'; ?></p>
                                                <div class="user-card-bio">
                                                    <?php echo isset($match['bio']) && !empty($match['bio']) ? nl2br(htmlspecialchars(substr($match['bio'], 0, 100) . (strlen($match['bio']) > 100 ? '...' : ''))) : 'Belum ada bio.'; ?>
                                                </div>
                                                <div style="display: flex; gap: 10px; margin-top: 15px;">
                                                    <a href="view_profile?id=<?php echo $match['id']; ?>" class="btn btn-outline" style="flex: 1;">Profil</a>
                                                    <a href="start_chat?user_id=<?php echo $match['id']; ?>" class="btn" style="flex: 1;">Chat</a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tombol Reset Tes -->
                                    <div style="margin-top: 30px; text-align: center;">
                                        <a href="compatibility?reset=true" class="btn" style="background-color: #dc3545; color: white;">Reset Tes & Mulai Ulang</a>
                                    </div>
                                </div>
                                <?php endif; ?>

        <?php elseif ($page === 'matches'): ?>
            <div class="dashboard-header">
                <h2>Pasangan</h2>
                <p>Lihat orang-orang yang cocok dengan Anda berdasarkan menfess mutual.</p>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-heart"></i> Pasangan</h3>
                </div>
                <div class="matches-container">
                    <p>Orang-orang yang saling tertarik dengan Anda</p>
                    
                    <div class="matches-grid">
                        <?php if (empty($matches)): ?>
                            <div class="empty-matches">
                                <div class="empty-icon">
                                    <i class="fas fa-heart-broken"></i>
                                </div>
                                <h3>Belum Ada Pasangan</h3>
                                <p>Kirim menfess ke crush kamu dan tunggu balasannya untuk mulai membuat koneksi!</p>
                                <a href="?page=menfess" class="btn">Kirim Menfess</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($matches as $match): ?>
                                <div class="match-card">
                                    <div class="match-image">
                                        <img src="<?php echo !empty($match['profile_pic']) ? htmlspecialchars($match['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="<?php echo htmlspecialchars($match['name']); ?>">
                                        <div class="match-badge">
                                            <i class="fas fa-heart"></i> Match!
                                        </div>
                                    </div>
                                    <div class="match-info">
                                        <div class="match-header">
                                            <h3><?php echo htmlspecialchars($match['name']); ?></h3>
                                        </div>
                                        <div class="match-bio">
                                            <?php echo isset($match['bio']) ? nl2br(htmlspecialchars(substr($match['bio'], 0, 100) . (strlen($match['bio']) > 100 ? '...' : ''))) : 'Belum ada bio.'; ?>
                                        </div>
                                        <div class="match-actions">
                                            <a href="view_profile?id=<?php echo $match['id']; ?>" class="btn btn-outline">
                                                <i class="fas fa-user"></i> Lihat Profil
                                            </a>
                                            <a href="start_chat?user_id=<?php echo $match['id']; ?>" class="btn">
                                                <i class="fas fa-comments"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <style>
            /* Matches styling */
            .matches-container {
                margin-bottom: 30px;
            }
            
            .matches-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            
            .match-card {
                background-color: var(--card-bg);
                border-radius: 10px;
                overflow: hidden;
                box-shadow: var(--card-shadow);
                transition: transform 0.3s, box-shadow 0.3s;
            }
            
            .match-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            }
            
            .match-image {
                height: 200px;
                overflow: hidden;
                position: relative;
            }
            
            .match-image img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .match-badge {
                position: absolute;
                top: 10px;
                right: 10px;
                background-color: var(--primary);
                color: white;
                font-size: 12px;
                padding: 4px 8px;
                border-radius: 12px;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            
            .match-info {
                padding: 20px;
            }
            
            .match-header {
                margin-bottom: 10px;
            }
            
            .match-header h3 {
                font-size: 18px;
                font-weight: 500;
                color: var(--text-color);
            }
            
            .match-bio {
                font-size: 14px;
                color: #666;
                margin-bottom: 15px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
                height: 60px;
            }
            
            .match-actions {
                display: flex;
                gap: 10px;
            }
            
            .empty-matches {
                text-align: center;
                padding: 40px 0;
            }
            
            .empty-icon {
                font-size: 50px;
                color: var(--secondary);
                margin-bottom: 20px;
            }
            
            .empty-matches h3 {
                font-size: 20px;
                margin-bottom: 10px;
                color: var(--text-color);
            }
            
            .empty-matches p {
                color: #666;
                margin-bottom: 20px;
            }
            
            @media (max-width: 767px) {
                .match-actions {
                    flex-direction: column;
                }
            }
            </style>
        <?php endif; ?>

        <?php if ($page === 'promotion'): ?>
    <div class="dashboard-header">
        <h2>Promosi</h2>
        <p>Promosikan produk atau konten Anda ke seluruh pengguna Cupid</p>
    </div>

    <?php 
    // Periksa apakah ada pesan yang perlu ditampilkan
    $promo_message = '';
    if (isset($_SESSION['promo_message'])) {
        $promo_message = $_SESSION['promo_message'];
        unset($_SESSION['promo_message']);
    }

    if (!empty($promo_message)): ?>
        <div class="alert <?php echo strpos($promo_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
            <?php echo $promo_message; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullhorn"></i> Buat Promosi Baru</h3>
        </div>
        <div class="promotion-form-container">
            <form id="promotionForm" method="post" action="dashboard?page=promotion" enctype="multipart/form-data">
                <div class="promotion-pricing">
                    <div class="pricing-title">Paket Promosi</div>
                    <div class="pricing-cards">
                        <div class="pricing-card" data-duration="7">
                            <div class="pricing-card-header">7 Hari</div>
                            <div class="pricing-card-price">Rp 5.000</div>
                            <div class="pricing-card-features">
                                <div class="pricing-feature"><i class="fas fa-check"></i> Tayang di Dashboard</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Link ke Instagram/Website</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Laporan Performa Dasar</div>
                            </div>
                            <div class="pricing-card-select">
                                <label class="pricing-select-btn">
                                    <input type="radio" name="promo_duration" value="7" checked>
                                    <span>Pilih Paket</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="pricing-card" data-duration="14">
                            <div class="pricing-card-header">14 Hari</div>
                            <div class="pricing-card-price">Rp 9.000</div>
                            <div class="pricing-card-features">
                                <div class="pricing-feature"><i class="fas fa-check"></i> Tayang di Dashboard</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Link ke Instagram/Website</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Laporan Performa Lengkap</div>
                            </div>
                            <div class="pricing-card-select">
                                <label class="pricing-select-btn">
                                    <input type="radio" name="promo_duration" value="14">
                                    <span>Pilih Paket</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="pricing-card popular" data-duration="30">
                            <div class="pricing-card-badge">Terpopuler</div>
                            <div class="pricing-card-header">30 Hari</div>
                            <div class="pricing-card-price">Rp 17.000</div>
                            <div class="pricing-card-features">
                                <div class="pricing-feature"><i class="fas fa-check"></i> Tayang di Dashboard</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Link ke Instagram/Website</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Laporan Performa Lengkap</div>
                                <div class="pricing-feature"><i class="fas fa-check"></i> Prioritas Tayang</div>
                            </div>
                            <div class="pricing-card-select">
                                <label class="pricing-select-btn">
                                    <input type="radio" name="promo_duration" value="30">
                                    <span>Pilih Paket</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="promo_title">Judul Promosi</label>
                    <input type="text" id="promo_title" name="promo_title" class="form-control" placeholder="Masukkan judul promosi Anda" required maxlength="50">
                    <div class="form-hint">Maksimal 50 karakter</div>
                </div>
                
                <div class="form-group">
                    <label for="promo_description">Deskripsi</label>
                    <textarea id="promo_description" name="promo_description" class="form-control" rows="3" placeholder="Jelaskan produk/konten Anda secara singkat" required maxlength="200"></textarea>
                    <div class="character-counter"><span id="desc-count">0</span>/200</div>
                </div>
                
                <div class="form-group">
                    <label for="promo_image">Gambar Promosi</label>
                    <div class="promo-image-preview-container">
                        <div id="image-preview" class="promo-image-preview">
                            <i class="fas fa-image"></i>
                            <span>Pratinjau Gambar</span>
                        </div>
                        <div class="promo-image-upload">
                            <div class="file-upload">
                                <input type="text" class="form-control" readonly placeholder="Pilih file gambar..." id="file-name">
                                <label for="promo_image" class="file-upload-btn">Browse</label>
                            </div>
                            <input type="file" id="promo_image" name="promo_image" accept="image/*" required style="display: none;">
                            <div class="form-hint">Format gambar: JPG, PNG, GIF. Ukuran ideal 800x600 piksel. Maksimal 2MB.</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="promo_link">Link Tujuan</label>
                    <input type="url" id="promo_link" name="promo_link" class="form-control" placeholder="https://instagram.com/username_anda" required>
                    <div class="form-hint">Link ke Instagram, website, atau toko online Anda</div>
                </div>
                
                <div class="form-buttons">
                    <button type="submit" name="create_promotion" class="btn btn-lg">
                        <i class="fas fa-paper-plane"></i> Buat Promosi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php
    // Get active promotions for current user
    $active_promos_sql = "SELECT * FROM promotions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC";
    $active_promos_stmt = $conn->prepare($active_promos_sql);
    $active_promos_stmt->bind_param("i", $user_id);
    $active_promos_stmt->execute();
    $active_promos_result = $active_promos_stmt->get_result();
    $active_promos = [];
    while ($row = $active_promos_result->fetch_assoc()) {
        $active_promos[] = $row;
    }

    // Get pending promotions for current user
    $pending_promos_sql = "SELECT * FROM promotions WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC";
    $pending_promos_stmt = $conn->prepare($pending_promos_sql);
    $pending_promos_stmt->bind_param("i", $user_id);
    $pending_promos_stmt->execute();
    $pending_promos_result = $pending_promos_stmt->get_result();
    $pending_promos = [];
    while ($row = $pending_promos_result->fetch_assoc()) {
        $pending_promos[] = $row;
    }

    // Get promotion history
    $promo_history_sql = "SELECT * FROM promotions WHERE user_id = ? AND (status = 'expired' OR status = 'cancelled') ORDER BY created_at DESC LIMIT 10";
    $promo_history_stmt = $conn->prepare($promo_history_sql);
    $promo_history_stmt->bind_param("i", $user_id);
    $promo_history_stmt->execute();
    $promo_history_result = $promo_history_stmt->get_result();
    $promo_history = [];
    while ($row = $promo_history_result->fetch_assoc()) {
        $promo_history[] = $row;
    }
    ?>

    <!-- Active Promotions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullhorn"></i> Promosi Aktif</h3>
        </div>
        
        <?php if (empty($active_promos)): ?>
        <div class="empty-state">
            <i class="fas fa-ad"></i>
            <h3>Belum Ada Promosi Aktif</h3>
            <p>Buat promosi baru untuk mempromosikan produk atau konten Anda.</p>
        </div>
        <?php else: ?>
        <div class="promotion-items">
            <?php foreach ($active_promos as $promo): ?>
            <div class="promotion-item">
                <div class="promotion-image">
                    <img src="<?php echo htmlspecialchars($promo['image_url']); ?>" alt="<?php echo htmlspecialchars($promo['title']); ?>">
                </div>
                <div class="promotion-info">
                    <h4><?php echo htmlspecialchars($promo['title']); ?></h4>
                    <p><?php echo htmlspecialchars($promo['description']); ?></p>
                    <div class="promotion-meta">
                        <span class="promotion-duration">
                            <i class="fas fa-clock"></i> 
                            <?php 
                                $date1 = new DateTime(date('Y-m-d H:i:s'));
                                $date2 = new DateTime($promo['expiry_date']);
                                $interval = $date1->diff($date2);
                                echo $interval->format('%a hari tersisa');
                            ?>
                        </span>
                        <span class="promotion-views">
                            <i class="fas fa-eye"></i> <?php echo number_format($promo['impressions'] ?? 0); ?> tayangan
                        </span>
                        <span class="promotion-clicks">
                            <i class="fas fa-mouse-pointer"></i> <?php echo number_format($promo['clicks'] ?? 0); ?> klik
                        </span>
                    </div>
                    <div class="promotion-actions">
                        <a href="<?php echo htmlspecialchars($promo['target_url']); ?>" class="btn btn-outline btn-sm" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Lihat Link
                        </a>
                        <a href="promotion_stats?id=<?php echo $promo['id']; ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chart-bar"></i> Statistik
                        </a>
                        <form method="post" action="cancel_promotion" class="d-inline" onsubmit="return confirm('Yakin ingin membatalkan promosi ini?');">
                            <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm text-danger">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pending Promotions -->
    <?php if (!empty($pending_promos)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hourglass-half"></i> Menunggu Pembayaran</h3>
        </div>
        
        <div class="promotion-items">
            <?php foreach ($pending_promos as $promo): ?>
            <div class="promotion-item pending">
                <div class="promotion-image">
                    <img src="<?php echo htmlspecialchars($promo['image_url']); ?>" alt="<?php echo htmlspecialchars($promo['title']); ?>">
                    <div class="promotion-status">Menunggu Pembayaran</div>
                </div>
                <div class="promotion-info">
                    <h4><?php echo htmlspecialchars($promo['title']); ?></h4>
                    <p><?php echo htmlspecialchars($promo['description']); ?></p>
                    <div class="promotion-meta">
                        <span class="promotion-price">
                            <i class="fas fa-tag"></i> Rp <?php echo number_format($promo['price']); ?>
                        </span>
                        <span class="promotion-duration">
                            <i class="fas fa-calendar-alt"></i> <?php echo $promo['duration_days']; ?> hari
                        </span>
                        <span class="promotion-date">
                            <i class="fas fa-clock"></i> Dibuat: <?php echo date('d M Y H:i', strtotime($promo['created_at'])); ?>
                        </span>
                    </div>
                    <div class="promotion-actions">
                        <a href="payment?order_id=<?php echo htmlspecialchars($promo['order_id']); ?>" class="btn btn-sm">
                            <i class="fas fa-credit-card"></i> Lanjutkan Pembayaran
                        </a>
                        <form method="post" action="cancel_promotion" class="d-inline" onsubmit="return confirm('Yakin ingin membatalkan promosi ini?');">
                            <input type="hidden" name="promo_id" value="<?php echo $promo['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm text-danger">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Promotion History -->
    <?php if (!empty($promo_history)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Riwayat Promosi</h3>
        </div>
        
        <div class="promotion-table-container">
            <table class="promotion-table">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Tanggal</th>
                        <th>Durasi</th>
                        <th>Harga</th>
                        <th>Status</th>
                        <th>Tayangan</th>
                        <th>Klik</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($promo_history as $promo): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($promo['title']); ?></td>
                        <td><?php echo date('d M Y', strtotime($promo['created_at'])); ?></td>
                        <td><?php echo $promo['duration_days']; ?> hari</td>
                        <td>Rp <?php echo number_format($promo['price']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($promo['status']); ?>">
                                <?php 
                                switch($promo['status']) {
                                    case 'expired':
                                        echo 'Berakhir';
                                        break;
                                    case 'cancelled':
                                        echo 'Dibatalkan';
                                        break;
                                    default:
                                        echo ucfirst($promo['status']);
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo number_format($promo['impressions'] ?? 0); ?></td>
                        <td><?php echo number_format($promo['clicks'] ?? 0); ?></td>
                        <td>
                            <a href="promotion_stats?id=<?php echo $promo['id']; ?>" class="btn btn-outline btn-xs">
                                <i class="fas fa-chart-bar"></i> Stats
                            </a>
                            <?php if ($promo['status'] === 'expired' || $promo['status'] === 'cancelled'): ?>
                            <a href="renew_promotion?id=<?php echo $promo['id']; ?>" class="btn btn-xs">
                                <i class="fas fa-redo"></i> Perpanjang
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <style>
    /* Promotion Form Styling */
    .promotion-form-container {
        padding: 20px;
    }

    .promotion-pricing {
        margin-bottom: 30px;
    }

    .pricing-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--text-color);
    }

    .pricing-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .pricing-card {
        background-color: var(--card-bg);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        position: relative;
        transition: all 0.3s;
    }

    .pricing-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .pricing-card.popular {
        border-color: var(--primary);
        box-shadow: 0 5px 15px rgba(255, 75, 110, 0.1);
    }

    .pricing-card-badge {
        position: absolute;
        top: -10px;
        right: -10px;
        background-color: var(--primary);
        color: white;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }

    .pricing-card-header {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 15px;
        color: var(--text-color);
    }

    .pricing-card-price {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 20px;
    }

    .pricing-card-features {
        margin-bottom: 20px;
    }

    .pricing-feature {
        margin-bottom: 10px;
        font-size: 14px;
        color: var(--text-color);
    }

    .pricing-feature i {
        color: var(--primary);
        margin-right: 5px;
    }

    .pricing-select-btn {
        display: block;
        padding: 10px;
        background-color: var(--secondary);
        color: var(--primary);
        border-radius: 5px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
    }

    .pricing-select-btn:hover {
        background-color: var(--primary);
        color: white;
    }

    .pricing-card input[type="radio"] {
        display: none;
    }

    .pricing-card input[type="radio"]:checked + span {
        background-color: var(--primary);
        color: white;
        display: block;
        border-radius: 5px;
        padding: 10px;
    }

    .pricing-card.selected {
        border-color: var(--primary);
        background-color: rgba(255, 75, 110, 0.05);
    }

    /* Image Preview Styling */
    .promo-image-preview-container {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .promo-image-preview {
        width: 250px;
        height: 200px;
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .promo-image-preview i {
        font-size: 40px;
        color: var(--border-color);
        margin-bottom: 10px;
    }

    .promo-image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .promo-image-upload {
        flex: 1;
    }

    .character-counter {
        text-align: right;
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .form-buttons {
        margin-top: 30px;
        display: flex;
        justify-content: center;
    }

    /* Active Promotions Styling */
    .promotion-items {
        display: flex;
        flex-direction: column;
        gap: 20px;
        padding: 20px;
    }

    .promotion-item {
        display: flex;
        background-color: var(--card-bg);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .promotion-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .promotion-item.pending {
        border-left: 4px solid #ffc107;
    }

    .promotion-image {
        width: 200px;
        height: 200px;
        overflow: hidden;
        position: relative;
    }

    .promotion-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .promotion-status {
        position: absolute;
        top: 10px;
        left: 0;
        background-color: #ffc107;
        color: #000;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
    }

    .promotion-info {
        flex: 1;
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .promotion-info h4 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
    }

    .promotion-info p {
        color: #666;
        margin-bottom: 15px;
        flex-grow: 1;
    }

    .promotion-meta {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .promotion-meta span {
        font-size: 13px;
        color: #666;
        display: flex;
        align-items: center;
    }

    .promotion-meta i {
        margin-right: 5px;
        color: var(--primary);
    }

    .promotion-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    /* Promotion Table Styling */
    .promotion-table-container {
        overflow-x: auto;
        padding: 10px 20px 20px;
    }

    .promotion-table {
        width: 100%;
        border-collapse: collapse;
    }

    .promotion-table th,
    .promotion-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
    }

    .promotion-table th {
        background-color: var(--secondary);
        color: var(--primary);
        font-weight: 600;
    }

    .promotion-table tbody tr {
        transition: background-color 0.3s;
    }

    .promotion-table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-active {
        background-color: #d4edda;
        color: #155724;
    }

    .status-pending {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-expired {
        background-color: #f8f9fa;
        color: #6c757d;
    }

    .status-cancelled {
        background-color: #f8d7da;
        color: #721c24;
    }

    .btn-xs {
        padding: 3px 8px;
        font-size: 12px;
    }

    /* Empty State Styling */
    .empty-state {
        text-align: center;
        padding: 50px 20px;
    }

    .empty-state i {
        font-size: 60px;
        color: var(--secondary);
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
    }

    .empty-state p {
        color: #666;
        max-width: 400px;
        margin: 0 auto;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .promo-image-preview-container {
            flex-direction: column;
        }
        
        .promo-image-preview {
            width: 100%;
            height: 250px;
        }
        
        .promotion-item {
            flex-direction: column;
        }
        
        .promotion-image {
            width: 100%;
            height: 200px;
        }
        
        .pricing-cards {
            grid-template-columns: 1fr;
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pricing card selection
        const pricingCards = document.querySelectorAll('.pricing-card');
        
        pricingCards.forEach(card => {
            card.addEventListener('click', function() {
                pricingCards.forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
            });
            
            // Check if card is already selected
            const radio = card.querySelector('input[type="radio"]');
            if (radio.checked) {
                card.classList.add('selected');
            }
        });
        
        // Character counter for description
        const descriptionTextarea = document.getElementById('promo_description');
        const descCount = document.getElementById('desc-count');
        
        if (descriptionTextarea && descCount) {
            descriptionTextarea.addEventListener('input', function() {
                const count = this.value.length;
                descCount.textContent = count;
                
                if (count > 180) {
                    descCount.style.color = '#dc3545';
                } else if (count > 150) {
                    descCount.style.color = '#ffc107';
                } else {
                    descCount.style.color = '#666';
                }
            });
        }
        
        // Image preview
        const imageInput = document.getElementById('promo_image');
        const imagePreview = document.getElementById('image-preview');
        const fileNameDisplay = document.getElementById('file-name');
        
        if (imageInput && imagePreview) {
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileSize = file.size / 1024 / 1024; // Convert to MB
                    
                    if (fileSize > 2) {
                        alert('Ukuran gambar terlalu besar. Maksimal 2MB.');
                        this.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        if (fileNameDisplay) {
                            fileNameDisplay.value = file.name;
                        }
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Form validation
        const promotionForm = document.getElementById('promotionForm');
        
        if (promotionForm) {
            promotionForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Title validation
                const title = document.getElementById('promo_title').value.trim();
                if (title.length < 5) {
                    alert('Judul promosi terlalu pendek. Minimal 5 karakter.');
                    isValid = false;
                }
                
                // Description validation
                const description = document.getElementById('promo_description').value.trim();
                if (description.length < 20) {
                    alert('Deskripsi terlalu pendek. Minimal 20 karakter.');
                    isValid = false;
                }
                
                // Link validation
                const link = document.getElementById('promo_link').value.trim();
                if (!link.startsWith('http://') && !link.startsWith('https://')) {
                    alert('Link harus dimulai dengan http:// atau https://');
                    isValid = false;
                }
                
                // Image validation
                const image = document.getElementById('promo_image');
                if (image.files.length === 0) {
                    alert('Silakan pilih gambar untuk promosi Anda.');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
        }
    });
    </script>
    <?php endif; ?>
    </div>
        </section>
        <div id="toast-notification" class="toast-notification">
            <div class="toast-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div id="toast-message" class="toast-message"></div>
            <div class="toast-close">
                <i class="fas fa-times"></i>
            </div>
        </div>

        <!-- promosi -->
    <?php
        $popup_promo_sql = "SELECT p.*, u.name as user_name 
                        FROM promotions p 
                        JOIN users u ON p.user_id = u.id 
                        WHERE p.status = 'active' 
                        AND p.id NOT IN (
                            SELECT promotion_id FROM promotion_dismissals 
                            WHERE user_id = ? AND DATE(dismissed_at) = CURDATE()
                        ) 
                        ORDER BY RAND() LIMIT 1";
    $popup_promo_stmt = $conn->prepare($popup_promo_sql);
    $popup_promo_stmt->bind_param("i", $user_id);
    $popup_promo_stmt->execute();
    $popup_promo_result = $popup_promo_stmt->get_result();
    $popup_promo = $popup_promo_result->fetch_assoc();

    // Update impressions counter if we found a promotion to display
    if ($popup_promo) {
        $update_impression_sql = "UPDATE promotions SET impressions = impressions + 1 WHERE id = ?";
        $update_impression_stmt = $conn->prepare($update_impression_sql);
        $update_impression_stmt->bind_param("i", $popup_promo['id']);
        $update_impression_stmt->execute();
    }
    ?>

    <!-- Promotion Popup HTML -->
    <?php if ($popup_promo): ?>
    <div id="promotion-popup" class="promotion-popup">
        <div class="promotion-popup-content">
            <button class="promotion-popup-close" id="close-promotion" data-promo-id="<?php echo $popup_promo['id']; ?>">
                <i class="fas fa-times"></i>
            </button>
            <div class="promotion-popup-image">
                <img src="<?php echo htmlspecialchars($popup_promo['image_url']); ?>" alt="<?php echo htmlspecialchars($popup_promo['title']); ?>">
                <div class="promotion-popup-badge">Promosi</div>
            </div>
            <div class="promotion-popup-info">
                <h3><?php echo htmlspecialchars($popup_promo['title']); ?></h3>
                <p><?php echo htmlspecialchars($popup_promo['description']); ?></p>
                <div class="promotion-popup-meta">
                    <span class="promotion-popup-author">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($popup_promo['user_name']); ?>
                    </span>
                </div>
                <a href="<?php echo htmlspecialchars($popup_promo['target_url']); ?>" class="btn btn-block promotion-popup-btn" target="_blank" data-promo-id="<?php echo $popup_promo['id']; ?>">
                    <i class="fas fa-external-link-alt"></i> Kunjungi Link
                </a>
                <div class="promotion-popup-footer">
                    <a href="dashboard?page=promotion" class="promotion-popup-link">Buat promosi sendiri?</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- CSS for Promotion Popup -->
    <style>
    /* Promotion Popup Styling */
    .promotion-popup {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        width: 350px;
        background-color: var(--card-bg);
        border-radius: 10px;
        box-shadow: 0 5px 30px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        transform: translateY(100%);
        opacity: 0;
        animation: slideIn 0.5s ease forwards;
        animation-delay: 2s;
    }

    @keyframes slideIn {
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .promotion-popup-close {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: rgba(0, 0, 0, 0.6);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s;
    }

    .promotion-popup-close:hover {
        background-color: rgba(0, 0, 0, 0.8);
    }

    .promotion-popup-image {
        height: 180px;
        position: relative;
        overflow: hidden;
    }

    .promotion-popup-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .promotion-popup:hover .promotion-popup-image img {
        transform: scale(1.05);
    }

    .promotion-popup-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background-color: var(--primary);
        color: white;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
        border-radius: 20px;
    }

    .promotion-popup-info {
        padding: 20px;
    }

    .promotion-popup-info h3 {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-color);
    }

    .promotion-popup-info p {
        color: #666;
        margin-bottom: 15px;
        font-size: 14px;
        line-height: 1.5;
    }

    .promotion-popup-meta {
        margin-bottom: 15px;
        font-size: 13px;
        color: #666;
    }

    .promotion-popup-meta i {
        color: var(--primary);
        margin-right: 5px;
    }

    .promotion-popup-btn {
        margin-bottom: 10px;
    }

    .promotion-popup-footer {
        text-align: center;
        font-size: 13px;
    }

    .promotion-popup-link {
        color: var(--primary);
        text-decoration: none;
    }

    .promotion-popup-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 480px) {
        .promotion-popup {
            width: calc(100% - 40px);
        }
    }
    </style>

    <!-- JavaScript for Promotion Popup -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const promotionPopup = document.getElementById('promotion-popup');
        const closePromotion = document.getElementById('close-promotion');
        
        if (closePromotion) {
            closePromotion.addEventListener('click', function() {
                const promoId = this.getAttribute('data-promo-id');
                
                // Hide popup
                promotionPopup.style.animation = 'none';
                promotionPopup.style.transform = 'translateY(100%)';
                promotionPopup.style.opacity = '0';
                
                // Record dismissal in database
                fetch('dismiss_promotion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'promo_id=' + promoId
                });
                
                // Remove from DOM after animation
                setTimeout(() => {
                    promotionPopup.remove();
                }, 500);
            });
        }
        
        // Track clicks on promotion link
        const promoLink = document.querySelector('.promotion-popup-btn');
        if (promoLink) {
            promoLink.addEventListener('click', function() {
                const promoId = this.getAttribute('data-promo-id');
                
                // Record click in database
                fetch('record_promotion_click.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'promo_id=' + promoId
                });
            });
        }
    });
    </script>
            <script>
                // JavaScript untuk interaktivitas
                document.addEventListener('DOMContentLoaded', function() {
                    // Highlight active sidebar menu based on page parameter
                    const currentPage = '<?php echo $page; ?>';
                    document.querySelectorAll('.sidebar-menu a').forEach(link => {
                        const linkPage = link.getAttribute('href').split('=')[1];
                        if (linkPage === currentPage) {
                            link.classList.add('active');
                        }
                    });
                    
                    // Make radio options more user-friendly for compatibility test
                    if (currentPage === 'compatibility') {
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
                    }
                });
                
            // Function to toggle between light and dark themes
            function toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                // Set theme on document
                document.documentElement.setAttribute('data-theme', newTheme);
                
                // Save theme preference to localStorage
                localStorage.setItem('cupid-theme', newTheme);
            }
            
            // Initialize theme based on saved preference
            function initTheme() {
                const savedTheme = localStorage.getItem('cupid-theme');
                if (savedTheme) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            }
            
            // Add event listener to theme toggle button
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize theme
                initTheme();
                
                // Add event listener to theme toggle button
                const themeToggleBtn = document.getElementById('theme-toggle-btn');
                if (themeToggleBtn) {
                    themeToggleBtn.addEventListener('click', toggleTheme);
                }
            });

        document.addEventListener('DOMContentLoaded', function() {
            // Load current settings
            fetch('notification_api?action=get_settings')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.settings;
                        
                        // Update form checkboxes with null checks
                        const emailMessages = document.getElementById('email-messages');
                        if (emailMessages) {
                            emailMessages.checked = settings.email_messages == 1;
                        }
                        
                        const emailLikes = document.getElementById('email-likes');
                        if (emailLikes) {
                            emailLikes.checked = settings.email_likes == 1;
                        }
                        
                        const emailMatches = document.getElementById('email-matches');
                        if (emailMatches) {
                            emailMatches.checked = settings.email_matches == 1;
                        }
                        
                        const browserNotifications = document.getElementById('browser-notifications');
                        if (browserNotifications) {
                            browserNotifications.checked = settings.browser_notifications == 1;
                        }
                        
                        const soundEnabled = document.getElementById('sound-enabled');
                        if (soundEnabled) {
                            soundEnabled.checked = settings.sound_enabled == 1;
                        }
                    }
                })
                .catch(error => console.error('Error loading notification settings:', error));
            
            // Test sound button
            const testSound = document.getElementById('test-sound');
            if (testSound) {
                testSound.addEventListener('click', function() {
                    const sound = document.getElementById('notification-sound');
                    if (sound) {
                        sound.play();
                    }
                });
            }
            
            // Save settings
            const settingsForm = document.getElementById('notification-settings-form');
            if (settingsForm) {
                settingsForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'update_settings');
                    
                    // Convert checkboxes to 0/1
                    ['email_messages', 'email_likes', 'email_matches', 'browser_notifications', 'sound_enabled'].forEach(setting => {
                        const checkbox = document.querySelector(`[name="${setting}"]`);
                        if (checkbox) {
                            formData.set(setting, checkbox.checked ? 1 : 0);
                        }
                    });
                    
                    fetch('notification_api', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message
                                alert('Notification settings saved successfully');
                            } else {
                                alert('Failed to save notification settings');
                            }
                        })
                        .catch(error => {
                            console.error('Error saving notification settings:', error);
                            alert('An error occurred while saving notification settings');
                        });
                });
            }
            
            // Mark all as read
            const markAllRead = document.getElementById('mark-all-read');
            if (markAllRead) {
                markAllRead.addEventListener('click', function() {
                    console.log('Marking all as read...');
                    
                    fetch('notification_api?action=mark_all_read')
                        .then(response => {
                            console.log('Response received:', response);
                            return response.json();
                        })
                        .then(data => {
                            console.log('Data received:', data);
                            if (data.success) {
                                // Pastikan loadNotifications() dan updateUnreadCount() sudah didefinisikan
                                if (typeof loadNotifications === 'function') {
                                    loadNotifications();
                                }
                                if (typeof updateUnreadCount === 'function') {
                                    updateUnreadCount(0);
                                }
                            } else {
                                console.error('Error marking all as read:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error marking all as read:', error);
                        });
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
        const promotionPopup = document.getElementById('promotion-popup');
        const closePromotion = document.getElementById('close-promotion');
        
        if (closePromotion) {
            closePromotion.addEventListener('click', function() {
                const promoId = this.getAttribute('data-promo-id');
                
                // Hide popup
                promotionPopup.style.animation = 'none';
                promotionPopup.style.transform = 'translateY(100%)';
                promotionPopup.style.opacity = '0';
                
                // Record dismissal in database
                fetch('dismiss_promotion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'promo_id=' + promoId
                });
                
                // Remove from DOM after animation
                setTimeout(() => {
                    promotionPopup.remove();
                }, 500);
            });
        }
        
        // Track clicks on promotion link
        const promoLink = document.querySelector('.promotion-popup-btn');
        if (promoLink) {
            promoLink.addEventListener('click', function() {
                const promoId = this.getAttribute('data-promo-id');
                
                // Record click in database
                fetch('record_promotion_click.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'promo_id=' + promoId
                });
            });
        }
    });
            </script>
            <script src="assets/js/notifications.js"></script>
        </body>
        </html>