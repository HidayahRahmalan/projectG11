<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meet Our Developers - ChefTube</title>
    <link rel="icon" type="image/png" href="website/icon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f23 100%);
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Animated Background Elements */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 120, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 226, 0.2) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundMove 20s ease-in-out infinite;
        }

        @keyframes backgroundMove {
            0%, 100% { transform: translateX(0px) translateY(0px); }
            33% { transform: translateX(-30px) translateY(-20px); }
            66% { transform: translateX(20px) translateY(-30px); }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(100vh) translateX(0px); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) translateX(100px); opacity: 0; }
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-left: 30px;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s ease;
        }

        .logo-section:hover {
            transform: scale(1.05);
        }

        .logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(229, 9, 20, 0.3);
            transition: all 0.3s ease;
        }

        .logo:hover {
            box-shadow: 0 6px 20px rgba(229, 9, 20, 0.5);
        }

        .brand-name {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 30px;
        }

        .back-btn {
            padding: 12px 24px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            background: transparent;
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }

        .back-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .back-btn:hover::before {
            left: 100%;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        /* Main Content */
        .main-content {
            padding: 60px 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 80px;
        }

        .page-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #e50914, #ff6b6b, #8e44ad);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .page-subtitle {
            font-size: 20px;
            color: rgba(255, 255, 255, 0.8);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Developer Grid - Modified for single row */
        .developers-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto 80px;
        }

        .developer-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            overflow: hidden;
        }

        .developer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(229, 9, 20, 0.1), rgba(255, 107, 107, 0.1), rgba(142, 68, 173, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
            pointer-events: none;
        }

        .developer-card:hover::before {
            opacity: 1;
        }

        .developer-card:hover {
            transform: translateY(-15px) scale(1.05);
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.5),
                0 0 40px rgba(229, 9, 20, 0.3);
            border-color: rgba(229, 9, 20, 0.4);
        }

        .developer-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            position: relative;
            z-index: 2;
            background: linear-gradient(45deg, #1a1a2e, #16213e);
        }

        .developer-card:hover .developer-image {
            border-color: rgba(229, 9, 20, 0.6);
            transform: scale(1.1);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
        }

        .developer-name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            transition: color 0.3s ease;
        }

        .developer-card:hover .developer-name {
            color: #ff6b6b;
        }

        .resume-btn {
            background: linear-gradient(45deg, #e50914, #ff6b6b);
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            overflow: hidden;
        }

        .resume-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .resume-btn:hover::before {
            left: 100%;
        }

        .resume-btn:hover {
            background: linear-gradient(45deg, #ff6b6b, #e50914);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(229, 9, 20, 0.4);
        }

        .resume-btn i {
            font-size: 14px;
        }

        /* Group Picture Section */
        .group-section {
            text-align: center;
            margin-top: 60px;
        }

        .group-image {
            max-width: 800px;
            width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
            margin-bottom: 20px;
        }

        .group-image:hover {
            transform: scale(1.02);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            border-color: rgba(229, 9, 20, 0.4);
        }

        .group-hashtag {
            font-size: 24px;
            font-weight: 700;
            color: #ff6b6b;
            margin-top: 20px;
            text-shadow: 0 2px 10px rgba(255, 107, 107, 0.3);
        }

        /* Fade-in animation */
        .fade-in {
            opacity: 0;
            transform: translateY(50px);
            animation: fadeInUp 0.8s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .developers-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .developers-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 0 20px;
            }

            .main-content {
                padding: 40px 20px;
            }

            .page-title {
                font-size: 36px;
            }

            .page-subtitle {
                font-size: 18px;
            }

            .developer-card {
                padding: 20px;
            }

            .developer-image {
                width: 100px;
                height: 100px;
            }

            .developer-name {
                font-size: 16px;
            }

            .header-left, .header-right {
                padding-left: 20px;
                padding-right: 20px;
            }

            .group-image {
                max-width: 90%;
            }

            .group-hashtag {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            .developers-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(229, 9, 20, 0.6);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(229, 9, 20, 0.8);
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <div class="particles"></div>

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <a href="index.php" class="logo-section">
                <img src="website/icon.png" alt="ChefTube" class="logo">
                <div class="brand-name">ChefTube</div>
            </a>
        </div>

        <div class="header-right">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header fade-in">
            <h1 class="page-title">Meet Our Developers</h1>
            <p class="page-subtitle">
                The talented team behind ChefTube who work tirelessly to bring you the best cooking video experience
            </p>
        </div>

        <div class="developers-grid">
            <!-- Developer 1 -->
            <div class="developer-card fade-in" style="animation-delay: 0.2s;">
                <img src="website/image1.jpg" alt="Developer 1" class="developer-image">
                <h3 class="developer-name">MOHAMAD SYAKIR IMAN BIN HARIZA</h3>
                <a href="website/resume1.pdf" target="_blank" class="resume-btn">
                    <i class="fas fa-file-pdf"></i>
                    View Resume
                </a>
            </div>

            <!-- Developer 2 -->
            <div class="developer-card fade-in" style="animation-delay: 0.4s;">
                <img src="website/image2.jpg" alt="Developer 2" class="developer-image">
                <h3 class="developer-name">AKMAL HARITH BIN IBRAHIM</h3>
                <a href="website/2.pdf" target="_blank" class="resume-btn">
                    <i class="fas fa-file-pdf"></i>
                    View Resume
                </a>
            </div>

            <!-- Developer 3 -->
            <div class="developer-card fade-in" style="animation-delay: 0.6s;">
                <img src="website/image3.jpg" alt="Developer 3" class="developer-image">
                <h3 class="developer-name">NORSHAFIQAH BINTI NORHISHAM</h3>
                <a href="website/3.pdf" target="_blank" class="resume-btn">
                    <i class="fas fa-file-pdf"></i>
                    View Resume
                </a>
            </div>

            <!-- Developer 4 -->
            <div class="developer-card fade-in" style="animation-delay: 0.8s;">
                <img src="website/image4.jpg" alt="Developer 4" class="developer-image">
                <h3 class="developer-name">NURUL NAJWA BINTI MUHSIN</h3>
                <a href="website/4.pdf" target="_blank" class="resume-btn">
                    <i class="fas fa-file-pdf"></i>
                    View Resume
                </a>
            </div>

            <!-- Developer 5 -->
            <div class="developer-card fade-in" style="animation-delay: 1.0s;">
                <img src="website/image5.jpg" alt="Developer 5" class="developer-image">
                <h3 class="developer-name">AINUR SYUHADA BINTI AHMAD RIZAL</h3>
                <a href="website/4.pdf" target="_blank" class="resume-btn">
                    <i class="fas fa-file-pdf"></i>
                    View Resume
                </a>
            </div>
        </div>

        <!-- Group Picture Section -->
        <div class="group-section fade-in" style="animation-delay: 1.2s;">
            <img src="website/geng.jpeg" alt="Our Team" class="group-image">
            <div class="group-hashtag">#kitageng</div>
        </div>
    </main>

    <script>
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.querySelector('.particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDuration = (Math.random() * 20 + 10) + 's';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles
        createParticles();

        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe all fade-in elements
        document.querySelectorAll('.fade-in').forEach(element => {
            observer.observe(element);
        });

        // Developer card hover effects
        document.querySelectorAll('.developer-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.zIndex = '';
            });
        });

        // Performance optimization: Pause animations when not visible
        let animationsPaused = false;
        
        document.addEventListener('visibilitychange', function() {
            const particles = document.querySelectorAll('.particle');
            const backgroundAnimation = document.body;
            
            if (document.hidden && !animationsPaused) {
                particles.forEach(p => p.style.animationPlayState = 'paused');
                backgroundAnimation.style.animationPlayState = 'paused';
                animationsPaused = true;
            } else if (!document.hidden && animationsPaused) {
                particles.forEach(p => p.style.animationPlayState = 'running');
                backgroundAnimation.style.animationPlayState = 'running';
                animationsPaused = false;
            }
        });

        // Smooth scroll to top when page loads
        window.addEventListener('load', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Add click sound effect (optional)
        document.querySelectorAll('.resume-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Add a subtle click effect
                this.style.transform = 'translateY(-2px) scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>