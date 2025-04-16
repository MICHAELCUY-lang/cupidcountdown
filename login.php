<?php
// Sertakan file konfigurasi
require_once 'config.php';

// Set timezone to Jakarta (WIB/GMT+7)
date_default_timezone_set('Asia/Jakarta');

// Cek jika user sudah login, redirect ke dashboard
checkLoggedIn();

// Inisialisasi variabel
$email = '';
$error = '';
$success = '';

// Check for success message from verification
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password']; // Jangan sanitize password
    
    // Validasi input
    if (empty($email) || empty($password)) {
        $error = 'Harap isi email dan password.';
    } else {
        // Cek user di database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                // Periksa apakah email sudah diverifikasi
                if (isset($user['email_verified']) && $user['email_verified'] != 1) {
                    $error = 'Email Anda belum diverifikasi. Silakan cek email Anda untuk link verifikasi.';
                } else {
                    // Set session dan redirect ke dashboard
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    
                    // Check if user is admin
                    $is_admin = ($user && isset($user['is_admin']) && $user['is_admin'] == 1);
                    
                    // Set the release date (April 16, 2025, 3:00 PM Jakarta time)
                    $releaseDate = new DateTime('2025-04-16 15:00:00', new DateTimeZone('Asia/Jakarta'));
                    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                    
                    // Check if release date has arrived or if user is admin
                    if ($now >= $releaseDate || $is_admin) {
                        // Release date has arrived or user is admin, redirect to dashboard
                        redirect('dashboard');
                    } else {
                        // Release date hasn't arrived yet, redirect to countdown
                        redirect('countdown');
                    }
                }
            } else {
                $error = 'Password salah.';
            }
        } else {
            $error = 'Email tidak terdaftar.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cupid</title>
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
            background: linear-gradient(135deg, var(--secondary) 0%, #fff1f3 100%);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            padding: 0 20px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 36px;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 32px;
        }
        
        .card {
            background-color: var(--light);
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .card-header {
            margin-bottom: 25px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 75, 110, 0.2);
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #e63e5c;
        }
        
        .extra-links {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .extra-links a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .extra-links a:hover {
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #ddd;
        }
        
        .divider span {
            padding: 0 10px;
            color: #666;
            font-size: 14px;
        }
        
        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .resend-verification {
            margin-top: 10px;
            font-size: 13px;
        }
        
        .resend-verification a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .resend-verification a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <a href="cupid" class="logo">
                <i class="fas fa-heart"></i> Cupid
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h1>Masuk</h1>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
                <?php if (strpos($error, 'belum diverifikasi') !== false): ?>
                <div class="resend-verification">
                    <a href="resend_verification?email=<?php echo urlencode($email); ?>">Kirim ulang email verifikasi</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="login">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="email@student.president.ac.id" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Masuk</button>
                
                <div class="extra-links">
                    <p>Belum punya akun? <a href="register">Daftar</a></p>
                    <p style="margin-top: 10px;"><a href="forgot_password">Lupa password?</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>