<?php
// File: forgot_password.php
// Halaman dan proses lupa password

// Start session
session_start();

require_once 'config.php';

// Cek jika user sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Inisialisasi variabel
$email = '';
$error = '';
$success = '';
$token = '';
$show_reset_form = false;

// Cek jika ada token reset password
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verifikasi token
    $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        // Token valid, tampilkan form reset password
        $show_reset_form = true;
    } else {
        $error = 'Token reset password tidak valid atau sudah kedaluwarsa.';
    }
}

// Proses form reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi password
    if (empty($password) || empty($confirm_password)) {
        $error = 'Harap isi semua field.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password konfirmasi tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password harus minimal 6 karakter.';
    } else {
        // Verifikasi token
        $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Hash password baru
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password dan reset token
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                $success = 'Password berhasil diubah! Silakan login dengan password baru Anda.';
                $show_reset_form = false;
            } else {
                $error = 'Terjadi kesalahan saat mengubah password: ' . $conn->error;
            }
        } else {
            $error = 'Token reset password tidak valid atau sudah kedaluwarsa.';
        }
    }
}

// Proses form permintaan reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = sanitizeInput($_POST['email']);
    
    // Validasi email
    if (empty($email)) {
        $error = 'Harap masukkan alamat email Anda.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (!preg_match('/@student\.president\.ac\.id$/', $email)) {
        $error = 'Hanya email dengan domain student.president.ac.id yang diperbolehkan.';
    } else {
        // Cek jika email ada di database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Email tidak terdaftar.';
        } else {
            $user = $result->fetch_assoc();
            
            // Generate token reset password
            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token berlaku 1 jam
            
            // Simpan token di database
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $reset_token, $expiry, $user['id']);
            
            if ($update_stmt->execute()) {
                // Kirim email reset password
                $to = $email;
                $subject = "Reset Password Cupid";
                
                // URL untuk reset password
                $reset_url = "https://" . $_SERVER['HTTP_HOST'] . "/forgot_password.php?token=" . $reset_token;
                
                $message = "
                <html>
                <head>
                    <title>Reset Password Cupid</title>
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
                            <p>Kami menerima permintaan untuk reset password akun Cupid Anda. Klik tombol di bawah ini untuk mengatur password baru:</p>
                            <p style='text-align: center;'>
                                <a href='$reset_url' class='button'>Reset Password</a>
                            </p>
                            <p>Atau Anda dapat menyalin dan menempelkan URL berikut ke browser Anda:</p>
                            <p>$reset_url</p>
                            <p>Link reset password ini akan kedaluwarsa dalam 1 jam.</p>
                            <p>Jika Anda tidak meminta reset password, silakan abaikan email ini.</p>
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
                $headers .= "From: Cupid <cupidpres@gmail.com>" . "\r\n";
                
                // Kirim email
                if (mail($to, $subject, $message, $headers)) {
                    $success = 'Instruksi reset password telah dikirim ke email Anda. Silakan cek inbox atau folder spam.';
                } else {
                    $error = 'Gagal mengirim email reset password. Silakan coba lagi nanti.';
                }
            } else {
                $error = 'Terjadi kesalahan saat memproses permintaan: ' . $conn->error;
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
    <title>Lupa Password - Cupid</title>
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
            color: var(--light) !important;
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
            text-align: center;
        }
        
        .password-requirements {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .password-requirements h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .password-requirements ul {
            padding-left: 20px;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
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
                <h1><?php echo $show_reset_form ? 'Reset Password' : 'Lupa Password'; ?></h1>
            </div>
            
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
            
            <?php elseif ($show_reset_form): ?>
            <!-- Form Reset Password -->
            <div class="description">
                Silakan masukkan password baru untuk akun Anda.
            </div>
            
            <div class="password-requirements">
                <h3>Persyaratan Password:</h3>
                <ul>
                    <li>Minimal 6 karakter</li>
                    <li>Sebaiknya kombinasi huruf dan angka</li>
                </ul>
            </div>
            
            <form method="post" action="forgot_password.php">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">Password Baru</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn">Reset Password</button>
                
                <div class="extra-links">
                    <p>Ingat password? <a href="login.php">Login</a></p>
                </div>
            </form>
            
            <?php else: ?>
            <!-- Form Permintaan Reset Password -->
            <div class="description">
                Masukkan email yang Anda gunakan saat mendaftar untuk menerima link reset password.
            </div>
            
            <form method="post" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="email@student.president.ac.id" required>
                    <div class="form-hint">Email dengan domain student.president.ac.id</div>
                </div>
                
                <button type="submit" name="request_reset" class="btn">Kirim Link Reset</button>
                
                <div class="extra-links">
                    <p>Ingat password? <a href="login.php">Login</a></p>
                    <p>Belum punya akun? <a href="register.php">Daftar</a></p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Jika form reset password
            if (document.querySelector('input[name="reset_password"]')) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                // Validate password length
                if (password.length < 6) {
                    e.preventDefault();
                    alert('Password harus minimal 6 karakter.');
                    return;
                }
                
                // Validate password match
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Password konfirmasi tidak cocok.');
                    return;
                }
            }
            
            // Jika form request reset
            if (document.querySelector('input[name="request_reset"]')) {
                const email = document.getElementById('email').value;
                
                // Validate email domain
                if (!email.endsWith('@student.president.ac.id')) {
                    e.preventDefault();
                    alert('Hanya email dengan domain student.president.ac.id yang diperbolehkan.');
                    return;
                }
            }
        });
    </script>
</body>
</html>