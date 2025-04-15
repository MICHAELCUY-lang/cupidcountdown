<?php
// File: resend_verification.php
// Resend verification email

require_once 'config.php';

// Inisialisasi variabel
$email = '';
$error = '';
$success = '';

// Cek jika email ada di parameter URL
if (isset($_GET['email']) && !empty($_GET['email'])) {
    $email = sanitizeInput($_GET['email']);
}

// Proses form pengiriman ulang verifikasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' || !empty($email)) {
    // Jika form disubmit, ambil email dari form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = sanitizeInput($_POST['email']);
    }
    
    // Validasi email
    if (empty($email)) {
        $error = 'Harap masukkan alamat email Anda.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!preg_match('/@student\.president\.ac\.id$/', $email)) {
        $error = 'Hanya email dengan domain student.president.ac.id yang diperbolehkan.';
    } else {
        // Cek user dengan email ini
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Email tidak terdaftar.';
        } else {
            $user = $result->fetch_assoc();
            
            // Cek jika email sudah diverifikasi
            if ($user['email_verified'] == 1) {
                $success = 'Email Anda sudah diverifikasi. Silakan login.';
            } else {
                // Generate token verifikasi baru
                $verification_token = bin2hex(random_bytes(32));
                
                // Update token di database
                $update_stmt = $conn->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                $update_stmt->bind_param("si", $verification_token, $user['id']);
                
                if ($update_stmt->execute()) {
                    // Kirim email verifikasi
                    $to = $email;
                    $subject = "Verifikasi Email Cupid - Pengiriman Ulang";
                    
                    // URL untuk verifikasi (sesuaikan dengan domain Anda)
                    $verification_url = "https://" . $_SERVER['HTTP_HOST'] . "/verify.php?token=" . $verification_token;
                    
                    $message = "
                    <html>
                    <head>
                        <title>Verifikasi Email Cupid</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { text-align: center; padding: 20px; background-color: #ffd9e0; color: #ff4b6e; }
                            .content { padding: 20px; background-color: #f9f9f9; }
                            .button { display: inline-block; padding: 10px 20px; background-color: #ff4b6e; color: white; 
                                     text-decoration: none; border-radius: 5px; font-weight: bold; }
                            .footer { padding: 20px; text-align: center; font-size: 12px; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Cupid</h1>
                            </div>
                            <div class='content'>
                                <h2>Halo " . htmlspecialchars($user['name']) . ",</h2>
                                <p>Anda telah meminta pengiriman ulang email verifikasi. Silakan klik tombol di bawah ini untuk memverifikasi alamat email Anda:</p>
                                <p style='text-align: center;'>
                                    <a href='$verification_url' class='button'>Verifikasi Email</a>
                                </p>
                                <p>Atau Anda dapat menyalin dan menempelkan URL berikut ke browser Anda:</p>
                                <p>$verification_url</p>
                                <p>Link verifikasi ini akan kedaluwarsa dalam 24 jam.</p>
                                <p>Jika Anda tidak meminta pengiriman ulang email verifikasi, silakan abaikan email ini.</p>
                            </div>
                            <div class='footer'>
                                &copy; " . date('Y') . " Cupid. All rights reserved.
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Set header untuk email HTML
                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: Cupid <noreply@" . $_SERVER['HTTP_HOST'] . ">" . "\r\n";
                    
                    // Kirim email
                    if (mail($to, $subject, $message, $headers)) {
                        $success = 'Email verifikasi telah dikirim ulang. Silakan cek inbox atau folder spam Anda.';
                    } else {
                        $error = 'Gagal mengirim email verifikasi. Silakan coba lagi nanti.';
                    }
                } else {
                    $error = 'Terjadi kesalahan saat memperbarui token verifikasi: ' . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Ulang Verifikasi - Cupid</title>
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
        
        .form-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        
        .description {
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <a href="cupid.php" class="logo">
                <i class="fas fa-heart"></i> Cupid
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h1>Kirim Ulang Verifikasi Email</h1>
            </div>
            
            <p class="description">
                Masukkan alamat email yang Anda gunakan saat mendaftar untuk menerima link verifikasi baru.
            </p>
            
            <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
            
            <div class="extra-links" style="margin-top: 30px;">
                <a href="login.php" class="btn">Kembali ke Login</a>
            </div>
            <?php else: ?>
            
            <form method="post" action="resend_verification.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="email@student.president.ac.id" required>
                    <div class="form-hint">Email dengan domain student.president.ac.id</div>
                </div>
                
                <button type="submit" class="btn">Kirim Link Verifikasi</button>
                
                <div class="extra-links">
                    <p>Sudah punya akun? <a href="login.php">Login</a></p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>