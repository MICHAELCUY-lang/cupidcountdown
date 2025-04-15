<?php
// countdown.php
// Shows a countdown timer until release date

require_once 'config.php';

// Set timezone to Jakarta (WIB/GMT+7)
date_default_timezone_set('Asia/Jakarta');

// Set the release date and time (April 16, 2025, 3:00 PM Jakarta time)
$releaseDate = new DateTime('2025-04-16 15:00:00', new DateTimeZone('Asia/Jakarta'));
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

// Check if countdown is over
$countdown_over = ($now >= $releaseDate);

// If countdown is over, redirect to dashboard
if ($countdown_over) {
    header('Location: dashboard.php');
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Check if user is admin
$is_admin = ($user && isset($user['is_admin']) && $user['is_admin'] == 1);

// If user is admin, allow them to bypass countdown
if ($is_admin) {
    // Show a notice but don't redirect automatically
    $admin_notice = true;
} else {
    $admin_notice = false;
}

// Format release date for display (in Jakarta time)
$releaseDateFormatted = $releaseDate->format('l, F j, Y \a\t g:i A') . ' WIB';

// Get current Jakarta time for display
$currentTimeJakarta = $now->format('l, F j, Y \a\t g:i:s A') . ' WIB';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon - Cupid</title>
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
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        header {
            background-color: var(--light);
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
            color: var(--dark);
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--light);
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
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--light);
        }
        
        .countdown-container {
            padding-top: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            flex: 1;
            padding-bottom: 50px;
        }
        
        .countdown-title {
            font-size: 36px;
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .countdown-subtitle {
            font-size: 18px;
            margin-bottom: 40px;
            color: var(--dark);
            max-width: 700px;
        }
        
        .current-time {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--dark);
            background-color: rgba(255, 255, 255, 0.7);
            padding: 8px 15px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .countdown-timer {
            display: flex;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .countdown-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 100px;
        }
        
        .countdown-number {
            font-size: 48px;
            font-weight: bold;
            color: var(--primary);
            background-color: var(--light);
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 10px;
        }
        
        .countdown-label {
            font-size: 16px;
            color: var(--dark);
        }
        
        .countdown-message {
            font-size: 18px;
            margin-bottom: 30px;
            color: var(--dark);
        }
        
        .countdown-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
            width: 100%;
            max-width: 1000px;
        }
        
        .feature-card {
            background-color: var(--light);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
        }
        
        .feature-icon {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .feature-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .feature-description {
            font-size: 14px;
            color: #666;
        }
        
        .admin-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        footer {
            background-color: var(--light);
            padding: 20px 0;
            text-align: center;
            font-size: 14px;
            color: #666;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 767px) {
            .countdown-timer {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .countdown-title {
                font-size: 28px;
            }
            
            .countdown-number {
                width: 80px;
                height: 80px;
                font-size: 36px;
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
                        <?php if ($is_admin): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                        <?php endif; ?>
                        <li>
                            <a href="logout.php" class="btn btn-outline">Logout</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <!-- Countdown Section -->
    <section class="countdown-container">
        <div class="container">
            <?php if ($admin_notice): ?>
            <div class="admin-notice">
                <p><strong>Admin Notice:</strong> As an admin, you can bypass the countdown and access all features.</p>
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
            </div>
            <?php endif; ?>
            
            <h1 class="countdown-title">Cupid is Coming Soon!</h1>
            <p class="countdown-subtitle">We're working hard to launch Cupid, your new matchmaking platform. Get ready to connect with amazing people based on your interests, hobbies, and personality.</p>
            
            <div class="current-time">
                <i class="fas fa-clock"></i> Current time: <span id="current-time"><?php echo $currentTimeJakarta; ?></span>
            </div>
            
            <div class="countdown-timer" id="countdown">
                <div class="countdown-box">
                    <div class="countdown-number" id="days">00</div>
                    <div class="countdown-label">Days</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-number" id="hours">00</div>
                    <div class="countdown-label">Hours</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-number" id="minutes">00</div>
                    <div class="countdown-label">Minutes</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-number" id="seconds">00</div>
                    <div class="countdown-label">Seconds</div>
                </div>
            </div>
            
            <p class="countdown-message">Official Launch: <strong><?php echo $releaseDateFormatted; ?></strong></p>
            
            <div class="countdown-features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mask"></i>
                    </div>
                    <h3 class="feature-title">Anonymous Crush Menfess</h3>
                    <p class="feature-description">Send anonymous messages to your crush. If they like you back, both identities will be revealed!</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="feature-title">Blind Chat</h3>
                    <p class="feature-description">Chat with random students without seeing their profile first. Discover personalities before appearances.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3 class="feature-title">Compatibility Test</h3>
                    <p class="feature-description">Take our personality quiz to find matches based on compatibility, major, and interests.</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Cupid. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Set the release date for countdown (April 16, 2025, 3:00 PM Jakarta time)
        const releaseDate = new Date('April 16, 2025 15:00:00 GMT+0700').getTime();
        
        // Function to format date with WIB timezone indicator
        function formatJakartaTime(date) {
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                hour12: true
            };
            return new Intl.DateTimeFormat('en-US', options).format(date) + ' WIB';
        }
        
        // Update the current time display
        function updateCurrentTime() {
            const now = new Date();
            // Adjust to Jakarta time by adding the offset
            // Jakarta is GMT+7, so adjust by the difference between local and GMT+7
            const jakartaTime = new Date(now.getTime() + (7*60*60*1000 - now.getTimezoneOffset()*60*1000));
            document.getElementById('current-time').textContent = formatJakartaTime(jakartaTime);
            return jakartaTime;
        }
        
        // Update the countdown every 1 second
        const countdown = setInterval(function() {
            // Get current date and time (adjusted to Jakarta time)
            const jakartaTime = updateCurrentTime();
            const now = jakartaTime.getTime();
            
            // Calculate the time remaining
            const distance = releaseDate - now;
            
            // Calculate days, hours, minutes, and seconds
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Display the result
            document.getElementById('days').innerHTML = String(days).padStart(2, '0');
            document.getElementById('hours').innerHTML = String(hours).padStart(2, '0');
            document.getElementById('minutes').innerHTML = String(minutes).padStart(2, '0');
            document.getElementById('seconds').innerHTML = String(seconds).padStart(2, '0');
            
            // If the countdown is over, redirect to dashboard
            if (distance < 0) {
                clearInterval(countdown);
                document.getElementById('days').innerHTML = '00';
                document.getElementById('hours').innerHTML = '00';
                document.getElementById('minutes').innerHTML = '00';
                document.getElementById('seconds').innerHTML = '00';
                
                // Redirect to dashboard after a short delay
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 3000);
            }
        }, 1000);
        
        // Update current time on page load
        updateCurrentTime();
    </script>
</body>
</html>