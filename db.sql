CREATE DATABASE IF NOT EXISTS cupid_db;
USE cupid_db;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bio TEXT,
    interests TEXT,
    major VARCHAR(100),
    looking_for ENUM('friends', 'study_partner', 'romance') DEFAULT 'friends',
    profile_pic VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS menfess (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT TRUE,
    is_revealed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS menfess_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    menfess_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menfess_id) REFERENCES menfess(id) ON DELETE CASCADE,
    UNIQUE(user_id, menfess_id)
);

CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user1_id INT NOT NULL,
    user2_id INT NOT NULL,
    is_blind BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user1_last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user2_last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS compatibility_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_1 VARCHAR(255) NOT NULL,
    option_2 VARCHAR(255) NOT NULL,
    option_3 VARCHAR(255) NOT NULL,
    option_4 VARCHAR(255) NOT NULL,
    option_5 VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compatibility_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    personality_score DECIMAL(5,2),
    major VARCHAR(100),
    interests TEXT,
    answers JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO compatibility_questions (question_text, option_1, option_2, option_3, option_4, option_5) VALUES
('Bagaimana kamu menghabiskan waktu luang?', 'Membaca buku', 'Menonton film', 'Berolahraga', 'Bermain game', 'Menghabiskan waktu dengan teman'),
('Bagaimana kamu mengatasi stress?', 'Meditasi', 'Berolahraga', 'Tidur', 'Mengobrol dengan teman', 'Makan'),
('Apa yang kamu cari dalam hubungan?', 'Keamanan', 'Petualangan', 'Kesetiaan', 'Kebersamaan', 'Kemandirian'),
('Bagaimana kamu mengekspresikan kasih sayang?', 'Kata-kata manis', 'Sentuhan fisik', 'Memberikan hadiah', 'Melakukan sesuatu untuk orang lain', 'Menghabiskan waktu bersama'),
('Bagaimana pendapatmu tentang hubungan jarak jauh?', 'Sangat sulit', 'Bisa berhasil dengan usaha', 'Tergantung orangnya', 'Saya suka ruang pribadi', 'Tidak masalah bagi saya'),
('Seberapa penting kesamaan minat dalam hubungan?', 'Sangat penting', 'Penting', 'Netral', 'Tidak terlalu penting', 'Tidak penting sama sekali'),
('Seberapa penting aspek fisik dalam hubungan?', 'Sangat penting', 'Penting', 'Netral', 'Tidak terlalu penting', 'Tidak penting sama sekali'),
('Bagaimana kamu melihat peran pendidikan dalam hidup?', 'Sangat penting', 'Penting', 'Netral', 'Tidak terlalu penting', 'Tidak penting sama sekali'),
('Apa yang kamu harapkan dari partner dalam 5 tahun ke depan?', 'Sukses karir', 'Berkeluarga', 'Petualangan bersama', 'Stabilitas finansial', 'Belum tahu'),
('Apa pendapatmu tentang berbagi password sosial media?', 'Tidak masalah', 'Tergantung situasi', 'Hanya akun tertentu', 'Lebih baik tidak', 'Tidak setuju');

INSERT INTO users (name, email, password) VALUES
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- password: password
('Jane Smith', 'jane@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Alice Johnson', 'alice@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Bob Williams', 'bob@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Charlie Brown', 'charlie@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO profiles (user_id, bio, interests, major, looking_for, profile_pic) VALUES
(1, 'Saya mahasiswa Teknik Informatika yang senang coding dan bermain game.', 'Coding, Gaming, Hiking', 'Computer Science', 'study_partner', 'uploads/profiles/default.jpg'),
(2, 'Mahasiswa Kedokteran yang senang membaca novel dan menikmati kopi.', 'Reading, Coffee, Medical', 'Medicine', 'romance', 'uploads/profiles/default.jpg'),
(3, 'Suka seni dan desain. Sedang belajar fotografi.', 'Art, Design, Photography', 'Graphic Design', 'friends', 'uploads/profiles/default.jpg'),
(4, 'Suka olahraga terutama basket dan sepak bola.', 'Basketball, Football, Sports', 'Sports Science', 'study_partner', 'uploads/profiles/default.jpg'),
(5, 'Suka bermain musik. Bisa bermain gitar dan piano.', 'Music, Guitar, Piano', 'Music', 'romance', 'uploads/profiles/default.jpg');

INSERT INTO menfess (sender_id, receiver_id, message, is_anonymous, is_revealed) VALUES
(1, 2, 'Hai Jane, saya selalu melihatmu di perpustakaan. Kamu sangat cantik dan pintar.', 1, 0),
(2, 1, 'Halo John, saya suka caramu presentasi di kelas kemarin.', 1, 0),
(3, 4, 'Bob, kamu keren banget waktu main basket kemarin!', 1, 0),
(4, 3, 'Alice, desain postermu kemarin bagus banget. Aku suka gayamu.', 1, 0),
(5, 1, 'John, saya selalu kagum dengan koding kamu. Bisa ajarin aku kapan-kapan?', 1, 0);


CREATE TABLE profile_reveal_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE profile_view_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, target_user_id)
);

ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN verification_token VARCHAR(64) NULL;

ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(64) NULL,
ADD COLUMN reset_token_expiry DATETIME NULL;

ALTER TABLE compatibility_results 
MODIFY interests VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE chat_sessions ADD COLUMN is_approved TINYINT(1) DEFAULT 0;
ALTER TABLE chat_sessions ADD COLUMN user1_approved TINYINT(1) DEFAULT 0;
ALTER TABLE chat_sessions ADD COLUMN user2_approved TINYINT(1) DEFAULT 0;

ALTER TABLE profiles 
ADD COLUMN searchable TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN show_online TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN allow_messages TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN show_major TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE deleted_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    session_id INT NOT NULL,
    deleted_at INT NOT NULL,
    INDEX (session_id, deleted_at)
);

CREATE TABLE IF NOT EXISTS hidden_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id INT NOT NULL,
    hidden_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_session (user_id, session_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
);

-- admin
-- Add is_admin column to users table if not exists
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0;

-- Create table for site settings
CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default site settings
INSERT INTO site_settings (setting_key, value) VALUES
('terms_of_service', 'Default Terms of Service content goes here.'),
('privacy_policy', 'Default Privacy Policy content goes here.');

-- Create table for announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for FAQs
CREATE TABLE IF NOT EXISTS faqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create table for user feedback
CREATE TABLE IF NOT EXISTS user_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category ENUM('bug', 'feature', 'complaint', 'suggestion', 'other') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'responded') NOT NULL DEFAULT 'new',
    admin_response TEXT,
    response_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create table for content reports
CREATE TABLE IF NOT EXISTS content_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    target_user_id INT NOT NULL,
    content_type ENUM('message', 'menfess', 'profile') NOT NULL,
    content_id INT NOT NULL,
    content TEXT NOT NULL,
    reason ENUM('spam', 'harassment', 'inappropriate', 'fake', 'other') NOT NULL,
    additional_info TEXT,
    status ENUM('pending', 'dismissed', 'actioned') NOT NULL DEFAULT 'pending',
    action_taken VARCHAR(50),
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create table for identity verifications
CREATE TABLE IF NOT EXISTS identity_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    id_document VARCHAR(255) NOT NULL,
    selfie_document VARCHAR(255),
    additional_info TEXT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create table for moderation logs
CREATE TABLE IF NOT EXISTS moderation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    target_user_id INT,
    details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Notification System Database Setup for Cupid
-- Add this to your db.sql file or run separately

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NULL,
    type ENUM('message', 'like', 'match', 'system') NOT NULL,
    content TEXT NOT NULL,
    related_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create notification settings table
CREATE TABLE IF NOT EXISTS notification_settings (
    user_id INT PRIMARY KEY,
    email_messages TINYINT(1) NOT NULL DEFAULT 1,
    email_likes TINYINT(1) NOT NULL DEFAULT 1,
    email_matches TINYINT(1) NOT NULL DEFAULT 1,
    browser_notifications TINYINT(1) NOT NULL DEFAULT 1,
    sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for faster notification queries
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

-- Sample notification data for testing
INSERT INTO notifications (user_id, sender_id, type, content, is_read)
VALUES 
(1, 2, 'message', 'Jane sent you a message', 0),
(1, 3, 'like', 'Alice liked your menfess', 0),
(1, NULL, 'system', 'Welcome to Cupid! Complete your profile to start matching.', 1),
(2, 1, 'match', 'You have a new match with John!', 0);

-- Default notification settings for existing users
INSERT INTO notification_settings (user_id)
SELECT id FROM users
ON DUPLICATE KEY UPDATE user_id = user_id;

-- SQL untuk membuat tabel-tabel yang diperlukan untuk fitur promosi

-- Tabel untuk menyimpan data promosi
CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    order_id VARCHAR(100),
    transaction_id VARCHAR(100),
    status ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL,
    expiry_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel untuk mencatat transaksi pembayaran
CREATE TABLE IF NOT EXISTS promotion_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_id VARCHAR(100),
    order_id VARCHAR(100),
    status VARCHAR(50) DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel untuk mencatat event promosi (impressions, clicks)
CREATE TABLE IF NOT EXISTS promotion_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    user_id INT,
    event_type ENUM('impression', 'click') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel untuk mencatat dismissal promosi oleh user
CREATE TABLE IF NOT EXISTS promotion_dismissals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    promotion_id INT NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, promotion_id, DATE(dismissed_at)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE
);

-- Procedure untuk membatalkan promosi yang expired
DELIMITER //
CREATE PROCEDURE update_expired_promotions()
BEGIN
    UPDATE promotions
    SET status = 'expired'
    WHERE status = 'active' AND expiry_date < NOW();
END //
DELIMITER ;

-- Event untuk menjalankan procedure update_expired_promotions setiap hari
CREATE EVENT IF NOT EXISTS daily_expire_promotions
ON SCHEDULE EVERY 1 DAY
DO
 CALL update_expired_promotions();

-- menambahkan admin
UPDATE users SET is_admin = 1 WHERE id = 123; -- ganti 123 dengan id admin yang sesuai

ALTER TABLE users 
ADD COLUMN last_activity TIMESTAMP NULL,
ADD INDEX (last_activity);

ALTER TABLE menfess ADD COLUMN is_revealed TINYINT DEFAULT 0;


-- Script untuk Login dan Register

-- login.php
-- Untuk login gunakan:
-- $email = $_POST['email'];
-- $password = $_POST['password'];
-- $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
-- $stmt->bind_param("s", $email);
-- $stmt->execute();
-- $result = $stmt->get_result();
-- if ($result->num_rows === 1) {
--     $user = $result->fetch_assoc();
--     if (password_verify($password, $user['password'])) {
--         $_SESSION['user_id'] = $user['id'];
--         header('Location: dashboard.php');
--         exit();
--     }
-- }

-- register.php
-- Untuk register gunakan:
-- $name = $_POST['name'];
-- $email = $_POST['email'];
-- $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
-- $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
-- $stmt->bind_param("sss", $name, $email, $password);
-- if ($stmt->execute()) {
--     $_SESSION['user_id'] = $conn->insert_id;
--     header('Location: dashboard.php');
--     exit();
-- }