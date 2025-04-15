<?php
// Sertakan file konfigurasi

require_once 'config.php';

// Pastikan user sudah login
requireLogin();

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
                END as liked
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

// Get matches (mutual likes)
$matches_sql = "SELECT DISTINCT u.id, u.name, p.profile_pic, p.bio
                FROM users u
                JOIN profiles p ON u.id = p.user_id
                JOIN menfess m ON (m.sender_id = u.id OR m.receiver_id = u.id)
                JOIN menfess_likes ml1 ON m.id = ml1.menfess_id AND ml1.user_id = ?
                JOIN menfess_likes ml2 ON m.id = ml2.menfess_id 
                WHERE 
                  ((m.sender_id = ? AND m.receiver_id = u.id AND ml2.user_id = u.id) OR
                   (m.receiver_id = ? AND m.sender_id = u.id AND ml2.user_id = u.id))";
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
    
    // Check if already liked
    $check_like_sql = "SELECT * FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
    $check_like_stmt = $conn->prepare($check_like_sql);
    $check_like_stmt->bind_param("ii", $user_id, $menfess_id);
    $check_like_stmt->execute();
    $check_like_result = $check_like_stmt->get_result();
    
    if ($check_like_result->num_rows > 0) {
        // Unlike
        $unlike_sql = "DELETE FROM menfess_likes WHERE user_id = ? AND menfess_id = ?";
        $unlike_stmt = $conn->prepare($unlike_sql);
        $unlike_stmt->bind_param("ii", $user_id, $menfess_id);
        $unlike_stmt->execute();
    } else {
        // Like
        $like_sql = "INSERT INTO menfess_likes (user_id, menfess_id) VALUES (?, ?)";
        $like_stmt = $conn->prepare($like_sql);
        $like_stmt->bind_param("ii", $user_id, $menfess_id);
        $like_stmt->execute();
    }
    
    // Refresh menfess data
    $menfess_stmt->execute();
    $menfess_result = $menfess_stmt->get_result();
    $menfess_messages = [];
    while ($row = $menfess_result->fetch_assoc()) {
        $menfess_messages[] = $row;
    }
    
    // Refresh matches
    $matches_stmt->execute();
    $matches_result = $matches_stmt->get_result();
    $matches = [];
    while ($row = $matches_result->fetch_assoc()) {
        $matches[] = $row;
    }
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
            header("Location: chat.php?session_id=" . $chat_id);
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
    
    // Save test results
    $test_sql = "INSERT INTO compatibility_results (user_id, personality_score, major, interests, answers) 
                VALUES (?, ?, ?, ?, ?)";
    $test_stmt = $conn->prepare($test_sql);
    $answers_json = json_encode($answers);
    $test_stmt->bind_param("idsss", $user_id, $personality_score, $major, $interests, $answers_json);
    
    if ($test_stmt->execute()) {
        $test_message = 'Compatibility test completed! Finding matches...';
        $test_taken = true;
        
        // Find compatible matches
        header("Location: dashboard.php");
        exit();
    } else {
        $test_message = 'Error saving test results: ' . $conn->error;
    }
}

// Get compatible matches if test taken
$compatible_matches = [];
if ($test_taken) {
    $matches_sql = "SELECT u.id, u.name, p.profile_pic, p.bio, p.major, p.interests,
               ABS(IFNULL(cr.personality_score, 0) - ?) as personality_diff,
               CASE WHEN cr.major = ? THEN 30 ELSE 0 END as major_match,
               CASE WHEN INSTR(LOWER(IFNULL(cr.interests, '')), LOWER(IFNULL(?, ''))) > 0 THEN 40 ELSE 0 END as interests_match,
               (100 - ABS(IFNULL(cr.personality_score, 0) - ?) * 0.3 + 
               CASE WHEN cr.major = ? THEN 30 ELSE 0 END + 
               CASE WHEN INSTR(LOWER(IFNULL(cr.interests, '')), LOWER(IFNULL(?, ''))) > 0 THEN 40 ELSE 0 END) as compatibility_score
               FROM compatibility_results cr
               JOIN users u ON cr.user_id = u.id
               LEFT JOIN profiles p ON u.id = p.user_id
               WHERE cr.user_id != ?
               ORDER BY compatibility_score DESC
               LIMIT 15";
    $matches_stmt = $conn->prepare($matches_sql);
    $matches_stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    $matches_stmt->execute();
    $compatible_matches_result = $matches_stmt->get_result();
    while ($row = $compatible_matches_result->fetch_assoc()) {
        $compatible_matches[] = $row;
    }
}

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
    align-items: center;
    margin-bottom: 20px;
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

/* Table Styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

th {
    background-color: rgba(0, 0, 0, 0.03);
    font-weight: 600;
    color: var(--text-color);
}

tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
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
    
    .tabs {
        flex-direction: column;
        border-bottom: none;
    }
    
    .tab {
        padding: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .tab.active {
        border-bottom: 1px solid var(--primary);
    }
    
    .features-grid {
        grid-template-columns: 1fr;
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
                <a href="cupid.php" class="logo">
                    <i class="fas fa-heart"></i> Cupid
                </a>
                <nav>
                    <ul>
                            <div class="theme-toggle">
    <button id="theme-toggle-btn" aria-label="Toggle dark mode">
        <i class="fas fa-moon"></i>
        <i class="fas fa-sun"></i>
    </button>
</div>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li>
                            <a href="logout.php" class="btn btn-outline">Keluar</a>
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
                            <a href="?page=payments" class="<?php echo $page === 'payments' ? 'active' : ''; ?>">
                                <i class="fas fa-credit-card"></i> Pembayaran
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
                        <div class="card">
                            <div class="card-header">
                                <h3>Lengkapi Profil Anda</h3>
                            </div>
                            <p>Lengkapi profil Anda untuk meningkatkan peluang menemukan pasangan yang cocok!</p>
                            <a href="?page=profile" class="btn" style="margin-top: 15px;">Lengkapi Profil</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3>Aktivitas Terbaru</h3>
                            </div>
                            <div class="recent-activity">
                                <?php if (empty($menfess_messages) && empty($chat_sessions)): ?>
                                    <p>Belum ada aktivitas baru.</p>
                                <?php else: ?>
                                    <ul style="list-style: none; padding: 0;">
                                        <?php 
                                        $count = 0;
                                        foreach ($menfess_messages as $message) {
                                            if ($count >= 3) break;
                                            $type = $message['type'] === 'sent' ? 'mengirim' : 'menerima';
                                            echo '<li style="padding: 10px 0; border-bottom: 1px solid #eee;">';
                                            echo '<i class="fas fa-mask" style="margin-right: 10px; color: var(--primary);"></i>';
                                            echo 'Anda ' . $type . ' pesan menfess. ';
                                            echo '<span style="color: #999; font-size: 12px;">' . date('d M Y H:i', strtotime($message['created_at'])) . '</span>';
                                            echo '</li>';
                                            $count++;
                                        }
                                        
                                        foreach ($chat_sessions as $session) {
                                            if ($count >= 3) break;
                                            echo '<li style="padding: 10px 0; border-bottom: 1px solid #eee;">';
                                            echo '<i class="fas fa-comments" style="margin-right: 10px; color: var(--primary);"></i>';
                                            
                                            // Check if blind chat and if permission exists
                                            $has_permission = false;
                                            if ($session['is_blind']) {
                                                $partner_id = $session['partner_id'];
                                                $permission_sql = "SELECT * FROM profile_view_permissions 
                                                                WHERE user_id = ? AND target_user_id = ?";
                                                $permission_stmt = $conn->prepare($permission_sql);
                                                $permission_stmt->bind_param("ii", $user_id, $partner_id);
                                                $permission_stmt->execute();
                                                $permission_result = $permission_stmt->get_result();
                                                $has_permission = ($permission_result->num_rows > 0);
                                                
                                                if (!$has_permission) {
                                                    echo 'Chat dengan Anonymous User';
                                                } else {
                                                    echo 'Chat dengan ' . htmlspecialchars($session['partner_name']);
                                                }
                                            } else {
                                                echo 'Chat dengan ' . htmlspecialchars($session['partner_name']);
                                            }
                                            
                                            if ($session['is_blind']) {
                                                echo ' (Blind Chat)';
                                            }
                                            echo ' <span style="color: #999; font-size: 12px;">' . 
                                                (isset($session['last_message_time']) && !empty($session['last_message_time']) 
                                                ? date('d M Y H:i', strtotime($session['last_message_time'])) 
                                                : 'Belum ada pesan') . 
                                                '</span>';
                                            echo '</li>';
                                            $count++;
                                        }
                                        ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3>Fitur Utama</h3>
                            </div>
                            <div class="features-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                                <div class="feature-box" style="text-align: center; padding: 20px; background-color: var(--bg-color); border-radius: 10px;">
                                    <i class="fas fa-mask" style="font-size: 40px; color: var(--primary); margin-bottom: 15px;"></i>
                                    <h4>Anonymous Crush Menfess</h4>
                                    <p style="margin-bottom: 15px;">Kirim pesan anonim ke crush kamu!</p>
                                    <a href="?page=menfess" class="btn btn-outline">Kirim Menfess</a>
                                </div>
                                <div class="feature-box" style="text-align: center; padding: 20px; background-color: var(--bg-color); border-radius: 10px;">
                                    <i class="fas fa-comments" style="font-size: 40px; color: var(--primary); margin-bottom: 15px;"></i>
                                    <h4>Blind Chat</h4>
                                    <p style="margin-bottom: 15px;">Chat dengan mahasiswa acak!</p>
                                    <a href="?page=chat" class="btn btn-outline">Mulai Chat</a>
                                </div>
                                <div class="feature-box" style="text-align: center; padding: 20px; background-color: var(--bg-color); border-radius: 10px;">
                                    <i class="fas fa-clipboard-check" style="font-size: 40px; color: var(--primary); margin-bottom: 15px;"></i>
                                    <h4>Compatibility Test</h4>
                                    <p style="margin-bottom: 15px;">Temukan kecocokan berdasarkan kepribadian!</p>
                                    <a href="?page=compatibility" class="btn btn-outline">Ikuti Tes</a>
                                </div>
                            </div>
                        </div>
                    
<?php elseif ($page === 'profile'): ?>
    <div class="dashboard-header">
        <h2>Profil</h2>
        <p>Kelola informasi profil Anda untuk meningkatkan peluang menemukan pasangan yang cocok.</p>
    </div>
    
    <?php if (!empty($profile_message)): ?>
    <div class="alert <?php echo strpos($profile_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
        <i class="<?php echo strpos($profile_message, 'success') !== false ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'; ?>"></i>
        <?php echo $profile_message; ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>Informasi Profil</h3>
        </div>
        
        <div class="profile-header">
            <div class="profile-pic">
                <img src="<?php echo !empty($profile['profile_pic']) ? htmlspecialchars($profile['profile_pic']) : '../assets/images/user_profile.png'; ?>" alt="Profile Picture">
                <label for="profile_pic" class="edit-pic-button">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
            <div class="profile-info">
                <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <?php if(!empty($profile['major'])): ?>
                <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($profile['major']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-completion">
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
            <div class="completion-text">
                <span>Kelengkapan Profil</span>
                <span><?php echo $completion_percentage; ?>%</span>
            </div>
            <div class="completion-bar">
                <div class="completion-progress" style="width: <?php echo $completion_percentage; ?>%;"></div>
            </div>
        </div>
        
        <div class="profile-tabs">
            <div class="profile-tab active" data-tab="basic">Informasi Dasar</div>
            <div class="profile-tab" data-tab="details">Detail Diri</div>
            <div class="profile-tab" data-tab="privacy">Privasi</div>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <!-- Basic Information Tab -->
            <div class="tab-content active" id="basic-tab">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    <div class="help-text">Email tidak dapat diubah karena digunakan untuk verifikasi.</div>
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
                    <label for="profile_pic">Foto Profil</label>
                    <div class="file-upload">
                        <input type="text" class="form-control" readonly placeholder="Pilih file foto..." id="file-name">
                        <label for="profile_pic" class="file-upload-btn">Browse</label>
                    </div>
                    <input type="file" id="profile_pic" name="profile_pic" style="display: none;">
                    <div class="help-text">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</div>
                </div>
            </div>
            
            <!-- Details Tab -->
            <div class="tab-content" id="details-tab">
                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" class="form-control" rows="5" placeholder="Ceritakan tentang dirimu..."><?php echo $profile ? htmlspecialchars($profile['bio']) : ''; ?></textarea>
                    <div class="help-text">Maksimal 500 karakter. Ceritakan tentang hobi, kesukaan, dan hal menarik tentang dirimu.</div>
                </div>
                
                <div class="form-group">
                    <label for="interests">Minat & Hobi</label>
                    <textarea id="interests" name="interests" class="form-control" rows="3" placeholder="Masukkan minat dan hobi (pisahkan dengan koma)"><?php echo $profile ? htmlspecialchars($profile['interests']) : ''; ?></textarea>
                    <div class="help-text">Contoh: Musik, Film, Fotografi, Hiking, Coding</div>
                </div>
                
                <div class="form-group">
                    <label>Minat yang Ditambahkan</label>
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
                            echo '<span class="text-muted">Belum ada minat yang ditambahkan</span>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="looking_for">Mencari</label>
                    <select id="looking_for" name="looking_for" class="form-control">
                        <option value="friends" <?php echo ($profile && $profile['looking_for'] === 'friends') ? 'selected' : ''; ?>>Teman</option>
                        <option value="study_partner" <?php echo ($profile && $profile['looking_for'] === 'study_partner') ? 'selected' : ''; ?>>Partner Belajar</option>
                        <option value="romance" <?php echo ($profile && $profile['looking_for'] === 'romance') ? 'selected' : ''; ?>>Romansa</option>
                    </select>
                </div>
            </div>
            
            <!-- Privacy Tab -->
                        <div class="privacy-option">
                <h4>
                    Tampilkan Profil Dalam Pencarian
                    <label class="toggle">
                        <input type="checkbox" name="searchable" value="1" <?php echo ($profile && isset($profile['searchable']) && $profile['searchable'] == 1) ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </h4>
                <p>Izinkan pengguna lain menemukan profil Anda dalam hasil pencarian dan rekomendasi kecocokan.</p>
            </div>
                
                <div class="privacy-option">
                    <h4>
                        Tampilkan Status Online
                        <label class="toggle">
                            <input type="checkbox" name="show_online" <?php echo ($profile && isset($profile['show_online']) && $profile['show_online'] == 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </h4>
                    <p>Tampilkan status online Anda kepada pengguna lain.</p>
                </div>
                
                <div class="privacy-option">
                    <h4>
                        Terima Pesan dari Siapa Saja
                        <label class="toggle">
                            <input type="checkbox" name="allow_messages" <?php echo ($profile && isset($profile['allow_messages']) && $profile['allow_messages'] == 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </h4>
                    <p>Izinkan pesan dari pengguna yang belum terhubung dengan Anda.</p>
                </div>
                
                <div class="privacy-option">
                    <h4>
                        Tampilkan Jurusan
                        <label class="toggle">
                            <input type="checkbox" name="show_major" <?php echo ($profile && isset($profile['show_major']) && $profile['show_major'] == 1) ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </h4>
                    <p>Tampilkan informasi jurusan Anda kepada pengguna lain.</p>
                </div>
            </div>
            
            <div class="submit-wrapper">
                <button type="submit" name="update_profile" class="btn">
                    <i class="fas fa-save"></i> Simpan Profil
                </button>
            </div>
        </form>
    </div>
    
    <script>
        // Handle tab switching
        document.querySelectorAll('.profile-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                const tabName = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabName + '-tab').classList.add('active');
            });
        });
        
        // Handle file upload preview
        document.getElementById('profile_pic').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const fileName = this.files[0].name;
                document.getElementById('file-name').value = fileName;
                
                // Optional: Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    const profilePic = document.querySelector('.profile-pic img');
                    profilePic.src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Handle dynamic interests display
        const interestsInput = document.getElementById('interests');
        const interestsDisplay = document.getElementById('interests-display');
        
        interestsInput.addEventListener('input', function() {
            const interests = this.value.split(',').filter(interest => interest.trim() !== '');
            
            if (interests.length > 0) {
                interestsDisplay.innerHTML = '';
                
                interests.forEach(interest => {
                    const tag = document.createElement('span');
                    tag.className = 'interest-tag';
                    tag.innerHTML = interest.trim() + ' <i class="fas fa-times"></i>';
                    interestsDisplay.appendChild(tag);
                    
                    // Add event listener to remove tag when clicked
                    tag.querySelector('i').addEventListener('click', function() {
                        const removedInterest = this.parentNode.textContent.trim().slice(0, -1).trim();
                        const currentInterests = interestsInput.value.split(',').map(i => i.trim());
                        const filteredInterests = currentInterests.filter(i => i !== removedInterest);
                        interestsInput.value = filteredInterests.join(', ');
                        this.parentNode.remove();
                        
                        if (interestsDisplay.children.length === 0) {
                            interestsDisplay.innerHTML = '<span class="text-muted">Belum ada minat yang ditambahkan</span>';
                        }
                    });
                });
            } else {
                interestsDisplay.innerHTML = '<span class="text-muted">Belum ada minat yang ditambahkan</span>';
            }
        });
        
        // Add click event to existing interest tags
        document.querySelectorAll('.interest-tag i').forEach(icon => {
            icon.addEventListener('click', function() {
                const removedInterest = this.parentNode.textContent.trim().slice(0, -1).trim();
                const currentInterests = interestsInput.value.split(',').map(i => i.trim());
                const filteredInterests = currentInterests.filter(i => i !== removedInterest);
                interestsInput.value = filteredInterests.join(', ');
                this.parentNode.remove();
                
                if (interestsDisplay.children.length === 0) {
                    interestsDisplay.innerHTML = '<span class="text-muted">Belum ada minat yang ditambahkan</span>';
                }
            });
        });
    </script>
                        
                   <?php elseif ($page === 'menfess'): ?>
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
            <h3>Kirim Menfess</h3>
        </div>
        <div class="card-body">
            <p class="card-description">
                Kirim pesan rahasia ke crush-mu tanpa mereka tahu siapa kamu! Jika mereka juga menyukaimu, identitas kalian akan terungkap.
            </p>
            
            <form id="menfessForm" method="post" action="dashboard.php?page=menfess">
                <div class="form-group">
                    <label for="crush_search">Cari Crush</label>
                    <div class="search-container">
                        <input type="text" id="crush_search" class="form-control" placeholder="Ketik nama crush..." autocomplete="off">
                        <div class="search-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <div id="search-results" class="search-results"></div>
                    </div>
                    <input type="hidden" name="crush_id" id="crush_id">
                </div>
                
                <div class="form-group">
                    <label for="menfess_message">Pesan Menfess</label>
                    <textarea 
                        id="menfess_message" 
                        name="message" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Tulis pesan rahasia untuk crush-mu..."
                        required></textarea>
                    <div class="character-counter">
                        <span id="char-count">0</span>/280
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
    
    <!-- Tampilan tab untuk menfess yang dikirim/diterima -->
    <div class="card">
        <div class="card-header">
            <h3>Menfess Manager</h3>
        </div>
        <div class="menfess-list">
            <div class="menfess-tabs">
                <div class="menfess-tab active" data-tab="received-menfess">Diterima</div>
                <div class="menfess-tab" data-tab="sent-menfess">Dikirim</div>
            </div>
            
            <!-- Menfess diterima -->
            <div id="received-menfess" class="menfess-content active">
                <?php
                $received_menfess = array_filter($menfess_messages, function($msg) {
                    return $msg['type'] === 'received';
                });
                
                if (empty($received_menfess)):
                ?>
                <div class="empty-menfess">
                    <i class="fas fa-inbox"></i>
                    <p>Belum ada pesan menfess yang diterima</p>
                </div>
                <?php else: ?>
                <?php foreach ($received_menfess as $menfess): 
                    // Periksa jika pengirim sudah menyukai pesan ini
                    $sender_liked = false;
                    if (isset($menfess['sender_id'])) {
                        $check_sender_like_sql = "SELECT COUNT(*) as count FROM menfess_likes 
                                               WHERE menfess_id = ? AND user_id = ?";
                        $check_stmt = $conn->prepare($check_sender_like_sql);
                        $check_stmt->bind_param("ii", $menfess['id'], $menfess['sender_id']);
                        $check_stmt->execute();
                        $like_result = $check_stmt->get_result()->fetch_assoc();
                        $sender_liked = ($like_result['count'] > 0);
                    }
                ?>
                <div class="menfess-card received">
                    <div class="menfess-header">
                        <div class="menfess-to-from">
                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                            <i class="fas fa-user"></i> 
                            <span>Dari: <?php echo htmlspecialchars(getSenderName($conn, $menfess['sender_id'])); ?></span>
                            <?php else: ?>
                            <i class="fas fa-mask"></i> 
                            <span>Penggemar Rahasia</span>
                            <?php endif; ?>
                        </div>
                        <div class="menfess-time"><?php echo date('d M Y', strtotime($menfess['created_at'])); ?></div>
                    </div>
                    <div class="menfess-message">
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
                            <?php if ($sender_liked): ?>
                            <span><i class="fas fa-heart" style="color: var(--primary);"></i> Pengirim menyukai pesan ini</span>
                            <?php endif; ?>
                            
                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                            <div class="menfess-match-badge">
                                <i class="fas fa-check-circle"></i> Match!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Menfess dikirim -->
            <div id="sent-menfess" class="menfess-content">
                <?php
                $sent_menfess = array_filter($menfess_messages, function($msg) {
                    return $msg['type'] === 'sent';
                });
                
                if (empty($sent_menfess)):
                ?>
                <div class="empty-menfess">
                    <i class="fas fa-paper-plane"></i>
                    <p>Belum ada pesan menfess yang dikirim</p>
                </div>
                <?php else: ?>
                <?php foreach ($sent_menfess as $menfess): 
                    // Periksa jika penerima sudah menyukai pesan ini
                    $receiver_liked = false;
                    if (isset($menfess['receiver_id'])) {
                        $check_receiver_like_sql = "SELECT COUNT(*) as count FROM menfess_likes 
                                                 WHERE menfess_id = ? AND user_id = ?";
                        $check_stmt = $conn->prepare($check_receiver_like_sql);
                        $check_stmt->bind_param("ii", $menfess['id'], $menfess['receiver_id']);
                        $check_stmt->execute();
                        $like_result = $check_stmt->get_result()->fetch_assoc();
                        $receiver_liked = ($like_result['count'] > 0);
                    }
                ?>
                <div class="menfess-card sent">
                    <div class="menfess-header">
                        <div class="menfess-to-from">
                            <i class="fas fa-paper-plane"></i> 
                            <span>Kepada: <?php echo isset($menfess['receiver_name']) ? htmlspecialchars($menfess['receiver_name']) : 'Unknown'; ?></span>
                        </div>
                        <div class="menfess-time"><?php echo date('d M Y', strtotime($menfess['created_at'])); ?></div>
                    </div>
                    <div class="menfess-message">
                        <?php echo nl2br(htmlspecialchars($menfess['message'])); ?>
                    </div>
                    <div class="menfess-actions">
                        <div class="menfess-status">
                            <?php if (isset($menfess['liked']) && $menfess['liked']): ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($receiver_liked): ?>
                            <span><i class="fas fa-heart" style="color: var(--primary);"></i> Penerima menyukai pesan Anda</span>
                            <?php endif; ?>
                            
                            <?php if (isset($menfess['is_revealed']) && $menfess['is_revealed']): ?>
                            <div class="menfess-match-badge">
                                <i class="fas fa-check-circle"></i> Match!
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

<style>
    .card-description {
        margin-bottom: 25px;
        color: #666;
        font-size: 15px;
        line-height: 1.6;
    }

    .search-container {
        position: relative;
        margin-bottom: 5px;
    }

    .search-icon {
        position: absolute;
        right: 15px;
        top: 12px;
        color: #999;
        pointer-events: none;
    }

    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        background-color: var(--card-bg);
        border: 1px solid var(--input-border);
        border-radius: 5px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
    }

    .search-result-item {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background-color: var(--secondary);
    }

    .search-result-image {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 12px;
        background-color: #f0f0f0;
    }

    .search-result-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .search-result-info {
        flex: 1;
    }

    .search-result-name {
        font-weight: 500;
        margin-bottom: 2px;
    }

    .search-result-detail {
        font-size: 12px;
        color: #666;
    }

    .character-counter {
        text-align: right;
        margin-top: 5px;
        font-size: 12px;
        color: #999;
    }

    /* Gaya untuk kartu menfess yang dikirim/diterima */
    .menfess-list {
        margin-top: 20px;
    }

    .menfess-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .menfess-tab {
        flex: 1;
        text-align: center;
        padding: 12px;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
    }

    .menfess-tab.active {
        color: var(--primary);
        font-weight: 500;
    }

    .menfess-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: var(--primary);
    }

    .menfess-content {
        display: none;
    }

    .menfess-content.active {
        display: block;
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .menfess-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .menfess-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .menfess-card.sent {
        background-color: var(--secondary);
        border-left: 4px solid var(--primary);
    }

    .menfess-card.received {
        background-color: #f8f9fa;
        border-left: 4px solid #adb5bd;
    }

    [data-theme="dark"] .menfess-card.received {
        background-color: #252525;
        border-left-color: #666;
    }

    .menfess-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: 14px;
    }

    .menfess-to-from {
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .menfess-to-from i {
        margin-right: 8px;
        color: var(--primary);
    }

    .menfess-time {
        color: #999;
        font-size: 12px;
    }

    .menfess-message {
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .menfess-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 12px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }

    .menfess-like {
        display: flex;
        align-items: center;
        gap: 8px;
        background: none;
        border: none;
        cursor: pointer;
        color: #666;
        transition: all 0.2s;
        font-size: 14px;
    }

    .menfess-like:hover {
        color: var(--primary);
    }

    .menfess-like.liked {
        color: var(--primary);
    }

    .menfess-like.liked i {
        transform: scale(1.2);
    }

    .menfess-status {
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .menfess-match-badge {
        background-color: var(--primary);
        color: white;
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .empty-menfess {
        text-align: center;
        padding: 30px 0;
        color: #999;
    }

    .empty-menfess i {
        font-size: 40px;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    .form-buttons {
        display: flex;
        justify-content: flex-end;
    }
    
    /* Responsiveness */
    @media (max-width: 767px) {
        .menfess-actions {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        
        .menfess-match-badge {
            align-self: flex-end;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const crushSearch = document.getElementById('crush_search');
    const crushId = document.getElementById('crush_id');
    const searchResults = document.getElementById('search-results');
    const messageField = document.getElementById('menfess_message');
    const charCount = document.getElementById('char-count');
    
    // Menampilkan hitungan karakter
    if (messageField) {
        messageField.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            // Membatasi jumlah karakter
            if (count > 280) {
                this.value = this.value.substring(0, 280);
                charCount.textContent = 280;
            }
            
            // Mengubah warna counter saat mendekati limit
            if (count > 230) {
                charCount.style.color = '#e63e5c';
            } else {
                charCount.style.color = '#999';
            }
        });
    }
    
    // Menangani pencarian crush
    if (crushSearch) {
        let searchTimeout;
        crushSearch.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            // Jika input kosong, sembunyikan hasil
            if (query === '') {
                searchResults.style.display = 'none';
                crushId.value = '';
                return;
            }
            
            // Set timeout untuk pencarian (throttling)
            searchTimeout = setTimeout(() => {
                // Lakukan pencarian
                fetchUsers(query);
            }, 300);
        });
    }
    
    // Menutup hasil pencarian saat klik di luar
    document.addEventListener('click', function(e) {
        if (searchResults && !searchResults.contains(e.target) && e.target !== crushSearch) {
            searchResults.style.display = 'none';
        }
    });
    
    // Fungsi untuk mengambil data pengguna
    function fetchUsers(query) {
        // AJAX call ke server untuk mencari user
        fetch(`search_users.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                displayResults(data);
            })
            .catch(error => {
                console.error('Error:', error);
                // Fallback dengan data dummy jika API belum tersedia
                const users = <?php echo json_encode($users); ?>;
                const filtered = users.filter(user => 
                    user.name.toLowerCase().includes(query.toLowerCase())
                );
                displayResults(filtered);
            });
    }
    
    // Fungsi untuk menampilkan hasil pencarian
    function displayResults(users) {
        // Bersihkan hasil sebelumnya
        if (!searchResults) return;
        searchResults.innerHTML = '';
        
        if (users.length === 0) {
            searchResults.innerHTML = '<div class="search-result-item">Tidak ada hasil ditemukan</div>';
            searchResults.style.display = 'block';
            return;
        }
        
        // Tambahkan setiap hasil
        users.forEach(user => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            const profilePic = user.profile_pic || '../assets/images/user_profile.png';
            item.innerHTML = `
                <div class="search-result-image">
                    <img src="${profilePic}" alt="${user.name}">
                </div>
                <div class="search-result-info">
                    <div class="search-result-name">${user.name}</div>
                    <div class="search-result-detail">${user.major || ''}</div>
                </div>
            `;
            
            // Saat item diklik
            item.addEventListener('click', function() {
                crushSearch.value = user.name;
                crushId.value = user.id;
                searchResults.style.display = 'none';
            });
            
            searchResults.appendChild(item);
        });
        
        // Tampilkan hasil
        searchResults.style.display = 'block';
    }
    
    // Tab switching untuk menfess
    const tabs = document.querySelectorAll('.menfess-tab');
    if (tabs.length > 0) {
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                const target = this.getAttribute('data-tab');
                document.querySelectorAll('.menfess-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(target).classList.add('active');
            });
        });
    }
    
    // Form validation
    const menfessForm = document.getElementById('menfessForm');
    if (menfessForm) {
        menfessForm.addEventListener('submit', function(e) {
            if (!crushId.value) {
                e.preventDefault();
                alert('Silakan pilih crush terlebih dahulu');
                return false;
            }
            
            if (!messageField.value.trim()) {
                e.preventDefault();
                alert('Pesan tidak boleh kosong');
                return false;
            }
        });
    }
});
</script>
<?php endif; ?>
                    
                    <?php if ($page === 'chat'): ?>
                        <div class="dashboard-header">
                            <h2>Chat</h2>
                            <p>Chat dengan mahasiswa lain atau mulai blind chat.</p>
                        </div>
                        
                        <?php if (!empty($blind_chat_message)): ?>
                        <div class="alert <?php echo strpos($blind_chat_message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?>">
                            <?php echo $blind_chat_message; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="card">
                            <div class="card-header">
                                <h3>Blind Chat</h3>
                            </div>
                            <p>Mulai chat dengan mahasiswa acak tanpa melihat profil mereka terlebih dahulu.</p>
                            <form method="post" style="margin-top: 20px;">
                                <button type="submit" name="start_blind_chat" class="btn">Mulai Blind Chat</button>
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
                                        <a href="chat.php?session_id=<?php echo $session['id']; ?>" class="chat-item">
                                            <div class="chat-avatar">
                                                <?php 
                                                // Check if blind chat and if user has permission
                                                $is_blind = $session['is_blind'];
                                                $partner_id = $session['partner_id'];
                                                $has_permission = false;
                                                
                                                if ($is_blind) {
                                                    // Check permission
                                                    $permission_sql = "SELECT * FROM profile_view_permissions 
                                                                    WHERE user_id = ? AND target_user_id = ?";
                                                    $permission_stmt = $conn->prepare($permission_sql);
                                                    $permission_stmt->bind_param("ii", $user_id, $partner_id);
                                                    $permission_stmt->execute();
                                                    $permission_result = $permission_stmt->get_result();
                                                    $has_permission = ($permission_result->num_rows > 0);
                                                }
                                                
                                                if (!$is_blind || $has_permission): 
                                                ?>
                                                    <img src="<?php echo !empty($session['profile_pic']) ? htmlspecialchars($session['profile_pic']) : 'assets/images/user_profile.png'; ?>" alt="Avatar">
                                                <?php else: ?>
                                                    <img src="assets/images/user_profile.png" alt="Anonymous">
                                                <?php endif; ?>
                                            </div>
                                            <div class="chat-info">
                                                <div class="chat-name">
                                                    <?php 
                                                    if ($is_blind && !$has_permission) {
                                                        echo 'Anonymous User';
                                                        echo '<i class="fas fa-lock lock-icon" title="Profil Terkunci"></i>';
                                                    } else {
                                                        echo htmlspecialchars($session['partner_name']);
                                                        if ($is_blind && $has_permission) {
                                                            echo '<i class="fas fa-unlock lock-icon" title="Profil Terbuka"></i>';
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($is_blind): ?>
                                                        <span style="font-size: 12px; color: var(--primary); text-decoration: none; margin-left: 5px;">
                                                            (Blind Chat)
                                                        </span>
                                                    <?php endif; ?>
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
                    
                   <?php elseif ($page === 'compatibility'): ?>
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
                                <a href="compatibility.php?reset=true" class="btn btn-outline">Ambil Tes Ulang</a>
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
                                            <a href="view_profile.php?id=<?php echo $match['id']; ?>" class="btn btn-outline" style="flex: 1;">Profil</a>
                                            <a href="start_chat.php?user_id=<?php echo $match['id']; ?>" class="btn" style="flex: 1;">Chat</a>
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
                                    <a href="view_profile.php?id=<?php echo $match['id']; ?>" class="btn btn-outline">
                                        <i class="fas fa-user"></i> Lihat Profil
                                    </a>
                                    <a href="start_chat.php?user_id=<?php echo $match['id']; ?>" class="btn">
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
                            
                            <?php if ($page === 'payments'): ?>
    <div class="dashboard-header">
        <h2>Riwayat Pembayaran</h2>
        <p>Lihat riwayat pembayaran dan transaksi profile reveal Anda.</p>
    </div>
    
    <div class="card">
        <?php
        // Get user's payment history
        $payments_sql = "SELECT prp.*, u.name as target_user_name, 
                        p.profile_pic as target_profile_pic
                        FROM profile_reveal_payments prp
                        JOIN users u ON prp.target_user_id = u.id
                        LEFT JOIN profiles p ON u.id = p.user_id
                        WHERE prp.user_id = ?
                        ORDER BY prp.created_at DESC";
        $payments_stmt = $conn->prepare($payments_sql);
        $payments_stmt->bind_param("i", $user_id);
        $payments_stmt->execute();
        $payments_result = $payments_stmt->get_result();
        $payments = [];
        while ($row = $payments_result->fetch_assoc()) {
            $payments[] = $row;
        }
        ?>
        
        <div class="payments-container">
            <div class="payments-header">
                <h2><i class="fas fa-credit-card"></i> Pembayaran Saya</h2>
                <p>Riwayat pembayaran untuk melihat profil pengguna</p>
            </div>
            
            <?php if (empty($payments)): ?>
                <div class="empty-payments">
                    <div class="empty-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3>Belum Ada Pembayaran</h3>
                    <p>Anda belum melakukan pembayaran apapun untuk melihat profil pengguna lain.</p>
                    <p class="empty-tip">Tip: Coba mulai <a href="?page=chat">Blind Chat</a> untuk menemukan pasangan baru!</p>
                </div>
            <?php else: ?>
                <div class="payments-list">
                    <?php foreach ($payments as $payment): ?>
                        <div class="payment-card">
                            <div class="payment-user">
                                <div class="payment-avatar">
                                    <img src="<?php echo !empty($payment['target_profile_pic']) ? htmlspecialchars($payment['target_profile_pic']) : 'assets/images/user_profile.png'; ?>" 
                                         alt="<?php echo htmlspecialchars($payment['target_user_name']); ?>">
                                </div>
                                <div class="payment-user-info">
                                    <h3><?php echo htmlspecialchars($payment['target_user_name']); ?></h3>
                                    <div class="payment-date">
                                        <i class="fas fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($payment['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="payment-status-badge <?php echo $payment['status']; ?>">
                                    <?php
                                    switch ($payment['status']) {
                                        case 'completed':
                                            echo '<i class="fas fa-check-circle"></i> Selesai';
                                            break;
                                        case 'pending':
                                            echo '<i class="fas fa-clock"></i> Menunggu';
                                            break;
                                        case 'failed':
                                            echo '<i class="fas fa-times-circle"></i> Gagal';
                                            break;
                                        case 'refunded':
                                            echo '<i class="fas fa-undo"></i> Dikembalikan';
                                            break;
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="payment-details">
                                <div class="payment-info">
                                    <div class="payment-info-item">
                                        <span class="label">Order ID:</span>
                                        <span class="value"><?php echo htmlspecialchars($payment['order_id']); ?></span>
                                    </div>
                                    <div class="payment-info-item">
                                        <span class="label">Jumlah:</span>
                                        <span class="value price">Rp <?php echo number_format($payment['amount'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="payment-info-item">
                                        <span class="label">Waktu:</span>
                                        <span class="value"><?php echo date('H:i', strtotime($payment['created_at'])); ?> WIB</span>
                                    </div>
                                    <?php if ($payment['status'] === 'completed' && !empty($payment['paid_at'])): ?>
                                    <div class="payment-info-item">
                                        <span class="label">Dibayar pada:</span>
                                        <span class="value"><?php echo date('d M Y H:i', strtotime($payment['paid_at'])); ?> WIB</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-actions">
                                    <?php if ($payment['status'] === 'completed'): ?>
                                        <a href="view_profile.php?id=<?php echo $payment['target_user_id']; ?>" class="btn">
                                            <i class="fas fa-eye"></i> Lihat Profil
                                        </a>
                                    <?php elseif ($payment['status'] === 'pending'): ?>
                                        <a href="payment_process.php?order_id=<?php echo $payment['order_id']; ?>" class="btn">
                                            <i class="fas fa-credit-card"></i> Bayar Sekarang
                                        </a>
                                        <a href="#" class="btn btn-outline cancel-payment" data-order-id="<?php echo $payment['order_id']; ?>">
                                            <i class="fas fa-times"></i> Batalkan
                                        </a>
                                    <?php elseif ($payment['status'] === 'failed'): ?>
                                        <a href="payment_process.php?order_id=<?php echo $payment['order_id']; ?>" class="btn">
                                            <i class="fas fa-redo"></i> Coba Lagi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    /* Payment Section Styling */
    .payments-container {
        margin-bottom: 30px;
    }
    
    .payments-header {
        margin-bottom: 25px;
        text-align: center;
    }
    
    .payments-header h2 {
        font-size: 24px;
        margin-bottom: 10px;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .payments-header p {
        color: #666;
        font-size: 16px;
    }
    
    .payments-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .payment-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        border: 1px solid var(--border-color);
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .payment-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }
    
    .payment-user {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        position: relative;
    }
    
    .payment-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        overflow: hidden;
        margin-right: 15px;
    }
    
    .payment-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .payment-user-info {
        flex: 1;
    }
    
    .payment-user-info h3 {
        font-size: 18px;
        margin-bottom: 5px;
        color: var(--text-color);
    }
    
    .payment-date {
        font-size: 13px;
        color: #888;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .payment-status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .payment-status-badge.completed {
        background-color: #d4edda;
        color: #155724;
    }
    
    .payment-status-badge.pending {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .payment-status-badge.failed {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .payment-status-badge.refunded {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .payment-details {
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .payment-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px 20px;
        flex: 1;
    }
    
    .payment-info-item {
        display: flex;
        flex-direction: column;
    }
    
    .payment-info-item .label {
        font-size: 12px;
        color: #888;
        margin-bottom: 5px;
    }
    
    .payment-info-item .value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-color);
    }
    
    .payment-info-item .price {
        color: var(--primary);
        font-weight: 600;
    }
    
    .payment-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .empty-payments {
        text-align: center;
        padding: 40px 20px;
        background-color: rgba(0,0,0,0.02);
        border-radius: 10px;
    }
    
    .empty-icon {
        font-size: 50px;
        color: var(--secondary);
        margin-bottom: 20px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
        100% { transform: scale(1); opacity: 1; }
    }
    
    .empty-payments h3 {
        font-size: 24px;
        margin-bottom: 10px;
        color: var(--text-color);
    }
    
    .empty-payments p {
        color: #666;
        margin-bottom: 5px;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .empty-tip {
        margin-top: 15px;
        font-style: italic;
        font-size: 14px;
    }
    
    .empty-tip a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
    }
    
    .empty-tip a:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 767px) {
        .payment-details {
            flex-direction: column;
            align-items: stretch;
        }
        
        .payment-info {
            grid-template-columns: 1fr;
        }
        
        .payment-actions {
            justify-content: center;
        }
    }
    </style>
    
    <script>
    // Handle payment cancellation
    document.addEventListener('DOMContentLoaded', function() {
        const cancelButtons = document.querySelectorAll('.cancel-payment');
        
        cancelButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (confirm('Apakah Anda yakin ingin membatalkan pembayaran ini?')) {
                    const orderId = this.getAttribute('data-order-id');
                    
                    // Here you would normally send an AJAX request to cancel the payment
                    // For now, we'll just redirect to a hypothetical cancel endpoint
                    window.location.href = 'cancel_payment.php?order_id=' + orderId;
                }
            });
        });
    });
    </script>
<?php endif; ?>
</section>
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
    </script>
</body>
</html>