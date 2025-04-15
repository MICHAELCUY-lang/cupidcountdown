<?php
session_start();

// Set Midtrans constants directly for reliability
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-PLcINWELsBNsoBdN');
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-k5VnZZNVNdRTXqed22jd4LRD');
define('MIDTRANS_IS_PRODUCTION', false);

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to file
ini_set('log_errors', 1);
ini_set('error_log', 'cupid_errors.log');

// Konfigurasi Database
$servername = "localhost";
$username = "u287442801_cupid";
$password = "Cupid1234!";
$dbname = "u287442801_cupid";

// Buat koneksi database
$conn = new mysqli($servername, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fungsi untuk memeriksa apakah user sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk memastikan user harus login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

// Fungsi untuk mengecek user yang sudah login dan seharusnya diarahkan ke dashboard
function checkLoggedIn() {
    if (isLoggedIn()) {
        redirect('dashboard.php');
    }
}

// Fungsi untuk sanitasi input
function sanitizeInput($input) {
    global $conn;
    return htmlspecialchars(strip_tags(trim($conn->real_escape_string($input))));
}