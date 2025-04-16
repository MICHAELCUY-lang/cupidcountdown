<?php
// countdown_helper.php
// Helper functions to check countdown status

/**
 * Check if the countdown period is over
 * 
 * @return bool True if countdown is over, false otherwise
 */
function isCountdownOver() {
    // Set timezone to Jakarta (WIB/GMT+7)
    date_default_timezone_set('Asia/Jakarta');
    
    $releaseDate = new DateTime('2025-04-18 15:00:00', new DateTimeZone('Asia/Jakarta'));
    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    return ($now >= $releaseDate);
}

/**
 * Check if user can access full site (either admin or countdown is over)
 * 
 * @param int $user_id User ID to check
 * @param mysqli $conn Database connection
 * @return bool True if user can access, false otherwise
 */
function canAccessFullSite($user_id, $conn) {
    // If countdown is over, everyone can access
    if (isCountdownOver()) {
        return true;
    }
    
    // Check if user is admin
    $sql = "SELECT is_admin FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    return ($user && isset($user['is_admin']) && $user['is_admin'] == 1);
}

/**
 * Redirect user based on countdown status
 * 
 * @param int $user_id User ID to check
 * @param mysqli $conn Database connection
 */
function redirectBasedOnCountdown($user_id, $conn) {
    if (isCountdownOver() || canAccessFullSite($user_id, $conn)) {
        // Allow access (do nothing)
        return;
    } else {
        // Redirect to countdown
        header('Location: countdown');
        exit();
    }
}