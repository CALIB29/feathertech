<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FeatherTech - Poultry Management System</title>
    <link href="assets/images/6.0.png" rel="icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        :root {
            --primary: #1a2a6c;
            --secondary: #4a0c0c;
            --accent: #fdbb2d;
            --light: #ffffff;
            --dark: #333333;
        }

        body {
            overflow-x: hidden;
            background: linear-gradient(135deg, var(--primary), var(--secondary), var(--accent));
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--light);
            min-height: 100vh;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            animation: fadeInDown 1s ease;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 2.5rem;
            color: var(--accent);
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        nav a {
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }

        nav a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background-color: var(--accent);
            transition: width 0.3s;
        }

        nav a:hover {
            color: var(--accent);
        }

        nav a:hover:after {
            width: 100%;
        }

        .hero {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 60px 0;
            gap: 40px;
        }

        .hero-content {
            flex: 1;
            min-width: 300px;
            animation: slideInLeft 1s ease;
        }

        .hero-content h2 {
            font-size: 3rem;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            line-height: 1.6;
            max-width: 600px;
        }

        .btn {
            display: inline-block;
            background: var(--light);
            color: var(--primary);
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            background: var(--accent);
        }

        .phone-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
            min-width: 300px;
            animation: fadeInUp 1s ease;
        }

        .phone {
            width: 280px;
            height: 520px;
            background: #222;
            border-radius: 40px;
            position: relative;
            transform-style: preserve-3d;
            transform: rotateY(-20deg) rotateX(10deg);
            transition: transform 0.5s ease;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .phone:hover {
            transform: rotateY(-5deg) rotateX(5deg);
        }

        .phone-screen {
            position: absolute;
            top: 20px;
            left: 15px;
            right: 15px;
            bottom: 20px;
            background: linear-gradient(45deg, #2c3e50, #4ca1af);
            border-radius: 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .app-content {
            text-align: center;
            color: var(--light);
        }

        .app-content h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .qr-code {
            width: 150px;
            height: 150px;
            background: var(--light);
            border-radius: 10px;
            margin: 20px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .qr-code i {
            font-size: 5rem;
            color: var(--primary);
        }

        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin: 60px 0;
            justify-content: center;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            flex: 1;
            min-width: 250px;
            max-width: 350px;
            text-align: center;
            transition: transform 0.3s;
            animation: fadeInUp 1s ease;
        }

        .feature:hover {
            transform: translateY(-10px);
        }

        .feature i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: var(--accent);
        }

        .feature h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .chicken-animation {
            position: absolute;
            font-size: 2rem;
            opacity: 0.7;
            z-index: -1;
            user-select: none;
            pointer-events: none;
        }

        /* Video Section */
        .video-section {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin: 40px 0;
            text-align: center;
        }

        .video-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--accent);
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 500px; /* Increased height */
            overflow: hidden;
            border-radius: 15px;
            margin: 20px auto;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .video-description {
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: left;
        }

        .video-description h3 {
            margin-bottom: 10px;
            color: var(--accent);
        }

        footer {
            text-align: center;
            padding: 40px 0;
            margin-top: 60px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {
            0% { transform: scale(0.95); }
            50% { transform: scale(1); }
            100% { transform: scale(0.95); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-content {
                margin-bottom: 40px;
            }
            
            nav ul {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .phone {
                transform: rotateY(0) rotateX(0);
            }
            
            .hero-content h2 {
                font-size: 2.5rem;
            }
            
            .video-container {
                height: 350px; /* Adjusted for mobile */
            }
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 1.5rem;
            }
            
            .hero-content h2 {
                font-size: 2rem;
            }
            
            .hero-content p {
                font-size: 1rem;
            }
            
            .btn {
                padding: 12px 30px;
            }
            
            .phone {
                width: 250px;
                height: 460px;
            }
            
            .video-section h2 {
                font-size: 2rem;
            }
            
            .video-container {
                height: 250px; /* Adjusted for small mobile */
            }
        }
    </style>
</head>
<body>
    <!-- Animated Chickens in Background -->
    <div class="chicken-animation" style="top: 10%; left: 5%;">🐔</div>
    <div class="chicken-animation" style="top: 40%; right: 8%;">🐓</div>
    <div class="chicken-animation" style="bottom: 15%; left: 10%;">🐤</div>
    <div class="chicken-animation" style="top: 20%; right: 15%;">🥚</div>

    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-feather"></i>
                <h1>FeatherTech Poultry Management System</h1>
            </div>
        </header>

        <section class="hero">
            <div class="hero-content">
                <h2>FeatherTech Poultry Management with QR System</h2>
                <p>FeatherTech helps Jingco Farm monitor and manage poultry activities in real-time using QR code system for vaccinations and chicken information tracking.</p>
                <a href="login.php" class="btn">Log In</a>
            </div>
            <div class="phone-container">
                <div class="phone">
                    <div class="phone-screen">
                        <div class="app-content">
                            <h3>FeatherTech App</h3>
                            <div class="qr-code">
                                <i class="fas fa-qrcode"></i>
                            </div>
                            <p>Scan to view chicken info and update vaccination records</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Video Section -->
        <section class="video-section">
            <h2>Smart. Efficient. Reliable.</h2>
            <div class="video-container">
                <video controls poster="assets/video/poster.jpg">
                    <source src="assets/video/video1.mp4" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            </div>
            <div class="video-description">
                <h3>About FeatherTech App</h3>
                <p>FeatherTech Poultry Management System helps caretakers/owner monitor and manage chicks, hens, and roosters in real time through QR code tracking and weather updates/send vaccination task, anytime and anywhere.</p>
            </div>
        </section>

        <section class="features">
            <div class="feature">
                <i class="fas fa-qrcode"></i>
                <h3>QR Code System</h3>
                <p>Easily update/send vaccinations task and individual chicken data with our QR code system.</p>
            </div>
            <div class="feature">
                <i class="fas fa-cloud-sun"></i>
                <h3>Weather Monitoring</h3>
                <p>Get real-time weather updates specific to your farm's location.</p>
            </div>
            <div class="feature">
                <i class="fas fa-mobile-alt"></i>
                <h3>Remote Access</h3>
                <p>Monitor your poultry operations from anywhere with our mobile app.</p>
            </div>
        </section>
    </div>

    <footer>
        <p>&copy; 2025 FeatherTech Poultry Management System - Jingco Farm</p>
    </footer>

    <script>
        // Enhanced chicken animation
        document.addEventListener('DOMContentLoaded', function() {
            const chickens = document.querySelectorAll('.chicken-animation');
            chickens.forEach(chicken => {
                animateChicken(chicken);
            });
            
            // Add interactive rotation to phone on click
            const phone = document.querySelector('.phone');
            let rotateY = -20;
            let rotateX = 10;
            
            phone.addEventListener('click', function() {
                rotateY += 45;
                rotateX += 5;
                this.style.transform = `rotateY(${rotateY}deg) rotateX(${rotateX}deg)`;
            });
        });

        function animateChicken(chicken) {
            const startX = Math.random() * 80;
            const startY = Math.random() * 80;
            const duration = 15 + Math.random() * 15;
            
            chicken.style.left = startX + 'vw';
            chicken.style.top = startY + 'vh';
            
            const keyframes = [
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 0.7 },
                { transform: `translate(${Math.random() * 100 - 50}px, ${Math.random() * 100 - 50}px) rotate(${Math.random() * 20 - 10}deg)`, opacity: 0.8 },
                { transform: `translate(${Math.random() * 100 - 50}px, ${Math.random() * 100 - 50}px) rotate(${Math.random() * 20 - 10}deg)`, opacity: 0.6 },
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 0.7 }
            ];
            
            const options = {
                duration: duration * 1000,
                iterations: Infinity
            };
            
            chicken.animate(keyframes, options);
        }
    </script>
</body>
</html>