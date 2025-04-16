<?php
// File: verify
// Verifikasi email pengguna setelah pendaftaran

// Start session
session_start();

require_once 'config.php';

// Set timezone to Jakarta (WIB/GMT+7)
date_default_timezone_set('Asia/Jakarta');

// Inisialisasi variabel
$error = '';
$success = '';

// Cek jika token verifikasi ada
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Cari user dengan token verifikasi ini
    $stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Jika user sudah terverifikasi
        if ($user['email_verified'] == 1) {
            $success = 'Email Anda sudah diverifikasi sebelumnya. Silakan login.';
        } else {
            // Update status verifikasi user
            $update_stmt = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            
            if ($update_stmt->execute()) {
                // Set session for the user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                // Check if user is admin
                $is_admin = ($user && isset($user['is_admin']) && $user['is_admin'] == 1);
                
                // Set the release date (April 16, 2025, 3:00 PM Jakarta time)
                $releaseDate = new DateTime('2025-04-16 15:00:00', new DateTimeZone('Asia/Jakarta'));
                $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                
                $success = 'Email Anda berhasil diverifikasi! Sekarang Anda akan diarahkan ke halaman Cupid.';
            } else {
                $error = 'Terjadi kesalahan saat memperbarui status verifikasi: ' . $conn->error;
            }
        }
    } else {
        $error = 'Token verifikasi tidak valid atau sudah kedaluwarsa.';
    }
} else {
    $error = 'Token verifikasi tidak ditemukan.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email - Cupid</title>
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
            max-width: 500px;
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
            text-align: center;
        }
        
        .card-header {
            margin-bottom: 25px;
        }
        
        .card-header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .icon-container {
            margin: 20px 0;
            font-size: 60px;
        }
        
        .success-icon {
            color: #28a745;
        }
        
        .error-icon {
            color: #dc3545;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 16px;
            text-decoration: none;
            transition: background-color 0.3s;
            margin-top: 20px;
        }
        
        .btn:hover {
            background-color: #e63e5c;
        }
        
        .message {
            margin-bottom: 20px;
            font-size: 16px;
            line-height: 1.6;
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
                <h1>Verifikasi Email</h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="icon-container">
                    <i class="fas fa-times-circle error-icon"></i>
                </div>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
                <p class="message">Terjadi kesalahan dalam proses verifikasi email Anda. 
                Silakan coba lagi atau hubungi dukungan pelanggan kami untuk bantuan.</p>
                <a href="cupid" class="btn">Kembali ke Beranda</a>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="icon-container">
                    <i class="fas fa-check-circle success-icon"></i>
                </div>
                <div class="success-message">
                    <?php echo $success; ?>
                </div>
                <p class="message">Akun Anda sekarang aktif. Anda dapat menikmati semua fitur Cupid.</p>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Set the redirect target based on release date and admin status
                    $releaseDate = new DateTime('2025-04-16 15:00:00', new DateTimeZone('Asia/Jakarta'));
                    $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
                    $is_admin = ($user && isset($user['is_admin']) && $user['is_admin'] == 1);
                    $redirect_target = ($now >= $releaseDate || $is_admin) ? 'dashboard' : 'countdown';
                    ?>
                    <a href="<?php echo $redirect_target; ?>" class="btn">Lanjutkan</a>
                    
                    <script>
                        // Auto redirect after 3 seconds
                        setTimeout(function() {
                            window.location.href = '<?php echo $redirect_target; ?>';
                        }, 3000);
                    </script>
                <?php else: ?>
                    <a href="login" class="btn">Login Sekarang</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>