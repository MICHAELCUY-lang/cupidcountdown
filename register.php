<?php
// File: register
// Halaman dan proses pendaftaran

// Start session
session_start();

require_once 'config.php';

// Cek jika user sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit();
}

// Inisialisasi variabel
$name = '';
$email = '';
$error = '';
$success = '';

// Pre-fill email if coming from login page redirect
if (isset($_GET['email'])) {
    $email = sanitizeInput($_GET['email']);
}

// Proses form registrasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Harap isi semua field.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password konfirmasi tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password harus minimal 6 karakter.';
    } 
    // Validasi domain email
    elseif (!preg_match('/@student\.president\.ac\.id$/', $email)) {
        $error = 'Hanya email dengan domain student.president.ac.id yang diperbolehkan.';
    } else {
        // Cek jika email sudah terdaftar
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email sudah terdaftar. Silakan <a href="login">login</a>.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate verification token
            $verification_token = bin2hex(random_bytes(32));
            
            // Insert new user with verification token and unverified status
            $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, email_verified, verification_token) VALUES (?, ?, ?, 0, ?)");
            $insert_stmt->bind_param("ssss", $name, $email, $hashed_password, $verification_token);
            
            if ($insert_stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Kirim email verifikasi
                $to = $email;
                $subject = "Verifikasi Email Cupid";
                
                // URL untuk verifikasi (sesuaikan dengan domain Anda)
                $verification_url = "https://" . $_SERVER['HTTP_HOST'] . "/verify?token=" . $verification_token;
                
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
                            <h2>Halo $name,</h2>
                            <p>Terima kasih telah mendaftar di Cupid. Untuk melanjutkan, silakan verifikasi alamat email Anda dengan mengklik tombol di bawah ini:</p>
                            <p style='text-align: center;'>
                                <a href='$verification_url' class='button'>Verifikasi Email</a>
                            </p>
                            <p>Atau Anda dapat menyalin dan menempelkan URL berikut ke browser Anda:</p>
                            <p>$verification_url</p>
                            <p>Link verifikasi ini akan kedaluwarsa dalam 24 jam.</p>
                            <p>Jika Anda tidak merasa mendaftar di Cupid, silakan abaikan email ini.</p>
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
                    $success = 'Pendaftaran berhasil! Silakan cek email Anda untuk verifikasi akun.';
                } else {
                    $error = 'Gagal mengirim email verifikasi. Silakan coba lagi nanti.';
                }
            } else {
                $error = 'Terjadi kesalahan saat mendaftar: ' . $conn->error;
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
    <title>Register - Cupid</title>
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
            padding: 40px 0;
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
        
        .error-message a {
            color: #721c24;
            font-weight: bold;
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
        
        .requirements {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .requirements h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .requirements ul {
            padding-left: 20px;
        }
        
        .requirements li {
            margin-bottom: 5px;
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
                <h1>Daftar</h1>
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
            <div class="extra-links" style="margin-bottom: 20px;">
                <p>Silakan <a href="login">login</a> setelah memverifikasi email Anda.</p>
            </div>
            <?php else: ?>
            
            <div class="requirements">
                <h3>Persyaratan Pendaftaran:</h3>
                <ul>
                    <li>Email dengan domain <strong>student.president.ac.id</strong></li>
                    <li>Password minimal 6 karakter</li>
                    <li>Verifikasi email diperlukan untuk aktivasi akun</li>
                </ul>
            </div>
            
            <form method="post" action="register">
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" 
                           placeholder="nama@student.president.ac.id" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                    <div class="form-hint">Gunakan email dengan domain student.president.ac.id</div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="form-hint">Minimal 6 karakter</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">Daftar</button>
                
                <div class="extra-links">
                    <p>Sudah punya akun? <a href="login">Masuk</a></p>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate email domain
            if (!email.endsWith('@student.president.ac.id')) {
                e.preventDefault();
                alert('Hanya email dengan domain student.president.ac.id yang diperbolehkan.');
                return;
            }
            
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
        });
    </script>
</body>
</html>