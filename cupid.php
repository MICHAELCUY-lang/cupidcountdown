<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupid - Temukan Pasanganmu</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff3e6c;
            --primary-light: #ff94ab;
            --primary-dark: #d12e52;
            --secondary: #6c63ff;
            --dark: #2d2d2d;
            --light: #ffffff;
            --bg-light: #f8f9fa;
            --gray: #7c7c7c;
            --gradient: linear-gradient(135deg, #ff3e6c 0%, #ff8fab 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }
        
        body {
            background-color: var(--bg-light);
            color: var(--dark);
            overflow-x: hidden;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--light);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
            height: 80px;
            display: flex;
            align-items: center;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .logo {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo img {
            height: 50px;
            transition: all 0.3s ease;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            letter-spacing: 0.5px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }
        
        nav ul li a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s;
            font-size: 16px;
            padding: 8px 0;
            position: relative;
        }
        
        nav ul li a:not(.btn)::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }
        
        nav ul li a:not(.btn):hover::after {
            width: 100%;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: var(--light);
            border: none;
            box-shadow: 0 4px 15px rgba(255, 62, 108, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 22px rgba(255, 62, 108, 0.4);
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
            box-shadow: 0 4px 15px rgba(255, 62, 108, 0.2);
        }
        
        .btn-icon {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .menu-toggle {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 25px;
            height: 20px;
            cursor: pointer;
            z-index: 150;
        }
        
        .menu-toggle span {
            height: 2px;
            width: 100%;
            background-color: var(--dark);
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 0;
            background: linear-gradient(135deg, #fff0f3 0%, #f8f9fa 100%);
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -10%;
            right: -5%;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 148, 171, 0.2) 0%, rgba(255, 62, 108, 0.1) 100%);
            z-index: 0;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            bottom: -10%;
            left: -5%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(108, 99, 255, 0.1) 0%, rgba(255, 148, 171, 0.1) 100%);
            z-index: 0;
        }
        
        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 2;
        }
        
        .hero-text {
            flex: 1;
            max-width: 600px;
        }
        
        .hero-badge {
            display: inline-block;
            background-color: rgba(255, 62, 108, 0.1);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .hero-title {
            font-size: 48px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .hero-title span {
            color: var(--primary);
            position: relative;
            display: inline-block;
        }
        
        .hero-title span::after {
            content: '';
            position: absolute;
            bottom: 6px;
            left: 0;
            width: 100%;
            height: 8px;
            background-color: rgba(255, 62, 108, 0.2);
            z-index: -1;
            border-radius: 4px;
        }
        
        .hero-description {
            font-size: 18px;
            line-height: 1.7;
            color: var(--gray);
            margin-bottom: 30px;
        }
        
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: flex-end;
            position: relative;
        }
        
        .hero-image img {
            width: 100%;
            max-width: 500px;
            height: auto;
            object-fit: contain;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .heart-icon {
            position: absolute;
            font-size: 24px;
            color: var(--primary);
            animation: pulse 2s infinite;
        }
        
        .heart-icon:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .heart-icon:nth-child(2) {
            top: 60%;
            right: 10%;
            animation-delay: 0.5s;
        }
        
        .heart-icon:nth-child(3) {
            bottom: 20%;
            left: 30%;
            animation-delay: 1s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Features Section */
        .features {
            padding: 100px 0;
            position: relative;
            background-color: var(--light);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gradient);
            border-radius: 2px;
        }
        
        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .section-header p {
            font-size: 18px;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            background-color: var(--light);
            border-radius: 16px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.4s;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--gradient);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s;
        }
        
        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 15px 40px rgba(255, 62, 108, 0.15);
        }
        
        .feature-card:hover::before {
            opacity: 1;
        }
        
        .feature-card:hover .feature-icon,
        .feature-card:hover h3,
        .feature-card:hover p {
            color: var(--light);
        }
        
        .feature-card:hover .btn-outline {
            background-color: var(--light);
            color: var(--primary);
            border-color: var(--light);
        }
        
        .feature-icon {
            font-size: 50px;
            color: var(--primary);
            margin-bottom: 25px;
            transition: all 0.4s;
        }
        
        .feature-card h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
            transition: all 0.4s;
        }
        
        .feature-card p {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 30px;
            flex-grow: 1;
            transition: all 0.4s;
        }
        
        /* Statistics Section */
        .stats {
            padding: 80px 0;
            background: linear-gradient(135deg, #fff0f3 0%, #f8f9fa 100%);
            position: relative;
            overflow: hidden;
        }
        
        .stats::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 62, 108, 0.1);
        }
        
        .stats-heading {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .stats-heading h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .stats-heading p {
            font-size: 18px;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .stat-card {
            background-color: var(--light);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient);
        }
        
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-text {
            font-size: 16px;
            color: var(--gray);
        }
        
        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: var(--gradient);
            color: var(--light);
            position: relative;
            overflow: hidden;
        }
        
        .cta::before {
            content: '';
            position: absolute;
            top: -100px;
            left: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .cta::after {
            content: '';
            position: absolute;
            bottom: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .cta-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-description {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .btn-light {
            background-color: var(--light);
            color: var(--primary);
            border: none;
        }
        
        .btn-light:hover {
            background-color: var(--light);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--light);
            padding: 80px 0 30px;
            position: relative;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .footer-about {
            padding-right: 30px;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .footer-logo img {
            height: 40px;
        }
        
        .footer-logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
        }
        
        .footer-about p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 25px;
            line-height: 1.7;
            font-size: 15px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--light);
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .social-link:hover {
            background-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .footer-heading {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--light);
            position: relative;
            padding-bottom: 12px;
        }
        
        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-links a:hover {
            color: var(--primary);
            padding-left: 5px;
        }
        
        .footer-links a i {
            font-size: 12px;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            text-align: center;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Media Queries */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 40px;
            }
        }
        
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-text {
                max-width: 100%;
                margin-bottom: 50px;
            }
            
            .hero-image {
                justify-content: center;
            }
            
            .footer-content {
                grid-template-columns: 1fr 1fr;
                gap: 40px;
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                padding: 15px 0;
            }
            
            .logo img {
                height: 40px;
            }
            
            .logo-text {
                font-size: 20px;
            }
            
            /* Mobile menu styles */
            .menu-toggle {
                display: flex;
            }
            
            nav {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background-color: var(--light);
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                transition: right 0.3s ease;
                z-index: 200;
                padding: 100px 20px 20px;
            }
            
            nav.active {
                right: 0;
            }
            
            nav ul {
                flex-direction: column;
                align-items: flex-start;
                gap: 25px;
            }
            
            nav ul li {
                width: 100%;
            }
            
            nav ul li a {
                display: block;
                width: 100%;
                padding: 8px 0;
            }
            
            .close-menu {
                position: absolute;
                top: 20px;
                right: 20px;
                font-size: 24px;
                cursor: pointer;
                color: var(--dark);
                background: transparent;
                border: none;
                width: 30px;
                height: 30px;
                text-align: center;
                z-index: 250;
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 150;
            }
            
            .hero {
                min-height: auto;
                padding: 120px 0 60px;
            }
            
            .hero-title {
                font-size: 32px;
            }
            
            .section-header h2 {
                font-size: 30px;
            }
            
            .feature-card {
                padding: 30px 20px;
            }
            
            .cta-title {
                font-size: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-badge {
                margin-bottom: 15px;
            }
            
            .hero-title {
                font-size: 28px;
                margin-bottom: 15px;
            }
            
            .hero-description {
                font-size: 16px;
                margin-bottom: 25px;
            }
            
            .section-header h2 {
                font-size: 26px;
            }
            
            .section-header p {
                font-size: 16px;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .feature-icon {
                font-size: 40px;
            }
            
            .feature-card h3 {
                font-size: 20px;
            }
            
            .stat-number {
                font-size: 36px;
            }
            
            .cta {
                padding: 70px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo">
                    <img src="assets/images/cupid_nobg.png" alt="Cupid Logo">
                    <span class="logo-text">Cupid</span>
                </a>
                <nav id="nav">
                    <button id="close-menu" class="close-menu">&times;</button>
                    <ul>
                        <li><a href="#features">Fitur</a></li>
                        <li><a href="#stats">Statistik</a></li>
                        <li><a href="login">Masuk</a></li>
                        <li><a href="register" class="btn btn-primary">Daftar</a></li>
                    </ul>
                </nav>
                <div class="menu-toggle" id="menu-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <div class="hero-badge">Platform Pertemanan & Kencan Terbaik</div>
                    <h1 class="hero-title">Temukan <span>Pasangan</span> Yang Cocok Dengan Kepribadianmu</h1>
                    <p class="hero-description">Platform dimana kamu dapat menemukan pasangan yang cocok berdasarkan ketertarikan, hobi, dan tujuan yang sama. Apakah kamu mencari teman, partner belajar, atau romansa, Cupid membantu kamu terhubung dengan orang yang tepat.</p>
                    <a href="register" class="btn btn-primary btn-icon">
                        <i class="fas fa-heart"></i> Mulai Sekarang
                    </a>
                </div>
                <div class="hero-image">
                    <i class="fas fa-heart heart-icon"></i>
                    <i class="fas fa-heart heart-icon"></i>
                    <i class="fas fa-heart heart-icon"></i>
                    <img src="assets/images/cupid_nobg.png" alt="Cupid Hero Image">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Fitur Utama</h2>
                <p>Cupid menawarkan berbagai fitur menarik untuk membantu kamu menemukan pasangan yang cocok.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h3>Profile Creation</h3>
                    <p>Buat profil dengan minat, hobi, dan apa yang kamu cari (teman, partner belajar, atau romansa).</p>
                    <a href="dashboard" class="btn btn-outline">Buat Profil</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-mask"></i>
                    </div>
                    <h3>Anonymous Crush Menfess</h3>
                    <p>Kirim pesan anonim ke crush kamu. Jika keduanya saling suka, nama akan terungkap!</p>
                    <a href="dashboard" class="btn btn-outline">Kirim Menfess</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Random Chat</h3>
                    <p>Chat dengan mahasiswa acak</p>
                    <a href="dashboard" class="btn btn-outline">Mulai Chat</a>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Compatibility Test</h3>
                    <p>Kuis untuk mencocokkan mahasiswa berdasarkan kepribadian, jurusan, dan minat.</p>
                    <a href="dashboard" class="btn btn-outline">Ikuti Tes</a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Statistics Section -->
    <section class="stats" id="stats">
        <div class="container">
            <div class="stats-heading">
                <h2>Cupid Dalam Angka</h2>
                <p>Lihat bagaimana Cupid telah membantu banyak orang menemukan pasangan mereka</p>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number">100</div>
                    <div class="stat-text">Pengguna Aktif</div>
                </div>
                <!--<div class="stat-card">-->
                <!--    <div class="stat-number">2.5K+</div>-->
                <!--    <div class="stat-text">Pasangan Berhasil</div>-->
                <!--</div>-->
                <div class="stat-card">
                    <div class="stat-number">100+</div>
                    <div class="stat-text">Menfess Terkirim</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">98%</div>
                    <div class="stat-text">Tingkat Kepuasan</div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Siap Untuk Menemukan Pasangan?</h2>
                <p class="cta-description">Bergabunglah dengan ribuan mahasiswa lainnya yang telah menemukan pasangan mereka di Cupid. Daftar sekarang gratis!</p>
                <a href="register" class="btn btn-light btn-icon">
                    <i class="fas fa-arrow-right"></i> Daftar Sekarang
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-about">
                    <div class="footer-logo">
                        <img src="/api/placeholder/160/60" alt="Cupid Logo">
                        <span class="footer-logo-text">Cupid</span>
                    </div>
                    <p>Platform untuk menemukan pasangan yang cocok berdasarkan minat, hobi, dan tujuan yang sama. Temukan teman, partner belajar, atau romansa di Cupid.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div class="footer-links-section">
                    <h3 class="footer-heading">Fitur</h3>
                    <ul class="footer-links">
                        <li><a href="dashboard"><i class="fas fa-chevron-right"></i> Profile Creation</a></li>
                        <li><a href="dashboard"><i class="fas fa-chevron-right"></i> Anonymous Crush Menfess</a></li>
                        <li><a href="dashboard"><i class="fas fa-chevron-right"></i> Blind Chat</a></li>
                        <li><a href="dashboard"><i class="fas fa-chevron-right"></i> Compatibility Test</a></li>
                    </ul>
                </div>
                <div class="footer-links-section">
                    <h3 class="footer-heading">Perusahaan</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Tentang Kami</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Kontak</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Karir</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Blog</a></li>
                    </ul>
                </div>
                <div class="footer-links-section">
                    <h3 class="footer-heading">Bantuan</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Kebijakan Privasi</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Syarat & Ketentuan</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Dukungan</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Cupid. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu functionality
        const menuToggle = document.getElementById('menu-toggle');
        const nav = document.getElementById('nav');
        const closeMenu = document.getElementById('close-menu');
        const overlay = document.createElement('div');
        
        // Add overlay to the body
        overlay.id = 'overlay';
        overlay.className = 'overlay';
        document.body.appendChild(overlay);
        
        menuToggle.addEventListener('click', function() {
            nav.classList.add('active');
            
        });
        
        function closeNavMenu() {
            nav.classList.remove('active');
           
        }
        
        closeMenu.addEventListener('click', closeNavMenu);
        overlay.addEventListener('click', closeNavMenu);
        
        // Navigation smooth scroll
        const navLinks = document.querySelectorAll('nav ul li a');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetElement = document.querySelector(targetId);
                    
                    if (targetElement) {
                        closeNavMenu();
                        setTimeout(() => {
                            window.scrollTo({
                                top: targetElement.offsetTop - 80,
                                behavior: 'smooth'
                            });
                        }, 300);
                    }
                } else {
                    closeNavMenu();
                }
            });
        });
        
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 50) {
                header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.1)';
                header.style.height = '70px';
            } else {
                header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.05)';
                header.style.height = '80px';
            }
        });
        
        // Close menu if window is resized to desktop size
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && nav.classList.contains('active')) {
                closeNavMenu();
            }
        });
    </script>
</body>
</html>