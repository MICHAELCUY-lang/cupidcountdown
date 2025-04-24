<?php
// File: create_promotion_tables.php
// Script untuk membuat tabel-tabel yang diperlukan untuk fitur promosi

// Sertakan file konfigurasi
require_once 'config.php';

// Periksa apakah user adalah admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die("Access denied. Admin only.");
}

// Array untuk menyimpan hasil eksekusi query
$results = [];

// Buat tabel promotions
$create_promotions_table = "
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
)";

if ($conn->query($create_promotions_table) === TRUE) {
    $results[] = "Table 'promotions' created successfully";
} else {
    $results[] = "Error creating table 'promotions': " . $conn->error;
}

// Buat tabel promotion_transactions
$create_transactions_table = "
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
)";

if ($conn->query($create_transactions_table) === TRUE) {
    $results[] = "Table 'promotion_transactions' created successfully";
} else {
    $results[] = "Error creating table 'promotion_transactions': " . $conn->error;
}

// Buat tabel promotion_events
$create_events_table = "
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
)";

if ($conn->query($create_events_table) === TRUE) {
    $results[] = "Table 'promotion_events' created successfully";
} else {
    $results[] = "Error creating table 'promotion_events': " . $conn->error;
}

// Buat tabel promotion_dismissals
$create_dismissals_table = "
CREATE TABLE IF NOT EXISTS promotion_dismissals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    promotion_id INT NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, promotion_id, DATE(dismissed_at)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE
)";

if ($conn->query($create_dismissals_table) === TRUE) {
    $results[] = "Table 'promotion_dismissals' created successfully";
} else {
    $results[] = "Error creating table 'promotion_dismissals': " . $conn->error;
}

// Buat procedure update_expired_promotions
$create_procedure = "
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS update_expired_promotions()
BEGIN
    UPDATE promotions
    SET status = 'expired'
    WHERE status = 'active' AND expiry_date < NOW();
END //
DELIMITER ;
";

// MySQL Delimiter tidak bekerja dengan mysqli->query, jadi kita pisah querynya
$conn->query("DROP PROCEDURE IF EXISTS update_expired_promotions");
if ($conn->query("
    CREATE PROCEDURE update_expired_promotions()
    BEGIN
        UPDATE promotions
        SET status = 'expired'
        WHERE status = 'active' AND expiry_date < NOW();
    END
") === TRUE) {
    $results[] = "Procedure 'update_expired_promotions' created successfully";
} else {
    $results[] = "Error creating procedure 'update_expired_promotions': " . $conn->error;
}

// Buat event daily_expire_promotions
$conn->query("DROP EVENT IF EXISTS daily_expire_promotions");
if ($conn->query("
    CREATE EVENT daily_expire_promotions
    ON SCHEDULE EVERY 1 DAY
    DO
        CALL update_expired_promotions()
") === TRUE) {
    $results[] = "Event 'daily_expire_promotions' created successfully";
} else {
    $results[] = "Error creating event 'daily_expire_promotions': " . $conn->error;
}

// Tampilkan hasil
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Promotion Tables</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #ff4b6e;
        }
        .result {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .actions {
            margin-top: 30px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #ff4b6e;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <h1>Create Promotion Tables</h1>
    
    <h2>Execution Results:</h2>
    <?php foreach($results as $result): ?>
        <?php 
        $is_error = strpos($result, "Error") !== false;
        $class = $is_error ? "error" : "success";
        ?>
        <div class="result <?php echo $class; ?>">
            <?php echo $result; ?>
        </div>
    <?php endforeach; ?>
    
    <div class="actions">
        <a href="dashboard?page=promotion" class="btn">Go to Promotions</a>
        <a href="dashboard" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>