<?php
session_start();
require_once 'connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>About Us - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />

<!-- In your <head> tag, replace the old <style> block with this one -->
<style>
        /* Main container and footer styles */
        .about-us-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 2rem;
            background-color: #f8f9fa;
            border-radius: 12px;
        }
        .footer {
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            background-color: #4a4255;
            color: #e9ecef;
        }

        /* === NEW: HERO IMAGE SECTION === */
        .about-hero-image-container {
            margin-bottom: 3rem; /* Space between image and vision section */
        }
        .about-hero-image-container img {
            width: 100%; /* Makes the image responsive */
            height: auto;
            display: block;
            border-radius: 12px; /* Match the container's rounded corners */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1); /* Nice subtle shadow */
            object-fit: cover; /* Ensures image covers the area well */
            max-height: 450px; /* Optional: sets a max height */
        }

        /* Vision Section */
        .vision-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 3rem 2rem;
            border-radius: 12px;
            margin-bottom: 3rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            text-align: center; /* Center align all content inside */
        }
        .vision-section h2 {
            font-size: 2.5rem;
            color: white;
            margin-bottom: 1rem;
            display: inline-flex; /* Changed from flex to center it properly */
            align-items: center;
            gap: 1rem;
        }
        .vision-section h2 .fa-lightbulb {
            font-size: 2.2rem;
        }
        .vision-section p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.7;
            max-width: 800px;
            margin: 0 auto;
        }

        /* === NEW: TEAM HEADER SECTION (FOR CENTERING) === */
        .team-header-section {
            text-align: center;
            margin-bottom: 3rem; /* Adds space before the grid of members */
        }
        .team-section-title {
            font-size: 2.5rem;
            color: #4a4255;
            margin-bottom: 1rem;
        }
        .team-section-subtitle {
            font-size: 1.1rem;
            color: #555;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Team Grid (Unchanged) */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
        }
        .team-member {
            background-color: #ffffff;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease-in-out;
            padding: 0;
            overflow: hidden;
        }
        .team-member:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
        }
        .team-member-header {
            background-color: #f0eefc;
            height: 100px;
        }
        .team-member img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: -70px;
            border: 6px solid #ffffff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .team-member-body {
            padding: 1.5rem;
            padding-top: 1rem;
        }
        .team-member h3 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
            color: #333;
        }
        .team-member .role {
            display: inline-block;
            background-color: #e9e6ff;
            color: #5a4fcf;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }
        .team-member .matric {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 1.5rem;
        }
        .team-member a.resume-btn {
            display: inline-block;
            padding: 0.7rem 1.4rem;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            transition: opacity 0.3s ease;
        }
        .team-member a.resume-btn:hover {
            opacity: 0.9;
        }
        header {
            text-align: center;
            padding: 2rem 1rem;
        }
        header h1 {
            font-weight: 600;
            font-size: 2.75rem;
            color: #fff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        header p {
            font-size: 1.2rem;
            color: #f4f4f4;
            margin-top: 0.5rem;
        }
        .team-section {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem auto;
            flex-direction: column;
        }
        .team-image {
            width: 420px;
            height: auto;
            border-radius: 1rem;
            object-fit: contain;
            border: 6px solid white;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }
</style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🍳 CookTogether</a>
            <div class="nav-links">
                <a class="nav-link" href="index.php">Home</a>
                <?php if(isset($_SESSION["loggedin"]) && in_array($_SESSION["role"], ['chef', 'student'])): ?>
                    <a class="nav-link" href="upload.php">Upload Recipe</a>
                <?php endif; ?>
                <a class="nav-link active" href="about.php">About Us</a> 
                <?php if(isset($_SESSION["loggedin"])): ?>
                    <a class="nav-link" href="logout.php">Logout</a>
                    <div class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['name']); ?>"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
                <?php else: ?>
                    <a class="nav-link" href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <header>
        <h1>Welcome to Our Project Showcase</h1>
        <p>Explore our featured projects and meet our wonderful team</p>
    </header>

    <section class="team-section">
        <img class="team-image" src="images/kamiGeng.jpeg" alt="Team Member">
        <p style="margin-top: 1rem; font-weight: 600; color: white;">Student BITD Gempak</p>
    </section>

    <div class="container">
        <div class="about-us-container">
            
            <div class="vision-section">
                <h2><i class="fas fa-lightbulb"></i> Our Vision</h2>
                <p>
                    Welcome to <strong>CookTogether</strong>, a vibrant digital kitchen created for culinary students and professional chefs. Our platform is designed to be a central hub where aspiring and established culinary artists can share their unique recipes, showcase their skills, and connect with a community that shares their passion.
                </p>
            </div>

            <!-- === NEW: WRAPPER DIV FOR CENTERING === -->
            <div class="team-header-section">
                <h2 class="team-section-title">Meet Our Team</h2>
                <p class="team-section-subtitle">We are a group of passionate individuals from UTeM who brought CookTogether to life as part of our university project.</p>
            </div>
            
            <div class="team-grid">
                <!-- Team Member 1 -->
                <div class="team-member">
                    <div class="team-member-header"></div>
                    <img src="img/haryani.jpg" alt="Nur Haryani Binti Wazir">
                    <div class="team-member-body">
                        <h3>Nur Haryani Binti Wazir</h3>
                        <p class="role">Team Leader</p>
                        <p class="matric">B032310038</p>
                        <a href="resumes/haryani_resume.pdf" class="resume-btn" target="_blank">View Resume</a>
                    </div>
                </div>

                <!-- Team Member 2 -->
                <div class="team-member">
                    <div class="team-member-header"></div>
                    <img src="img/shamimi.jpeg" alt="Nurfatin Shamimi Binti Roosly">
                    <div class="team-member-body">
                        <h3>Nurfatin Shamimi Binti Roosly</h3>
                        <p class="role">Database Designer</p>
                        <p class="matric">B032310868</p>
                        <a href="resumes/shamimi_resume.pdf" class="resume-btn" target="_blank">View Resume</a>
                    </div>
                </div>

                <!-- Team Member 3 -->
                <div class="team-member">
                    <div class="team-member-header"></div>
                    <img src="img/aqila.jpg" alt="Nur Aqila Nadzira Binti Shahlan">
                    <div class="team-member-body">
                        <h3>Nur Aqila Nadzira Binti Shahlan</h3>
                        <p class="role">Backend Developer</p>
                        <p class="matric">B032210322</p>
                        <a href="resumes/aqila_resume.pdf" class="resume-btn" target="_blank">View Resume</a>
                    </div>
                </div>

                <!-- Team Member 4 -->
                <div class="team-member">
                    <div class="team-member-header"></div>
                    <img src="img/izzati.jpg" alt="Nur Izzati Izwani Binti Edi Raof">
                    <div class="team-member--body">
                        <h3>Nur Izzati Izwani Binti Edi Raof</h3>
                        <p class="role">Multimedia Handler</p>
                        <p class="matric">B032210246</p>
                        <a href="resumes/izzati_resume.pdf" class="resume-btn" target="_blank">View Resume</a>
                    </div>
                </div>

                 <!-- Team Member 5 -->
                 <div class="team-member">
                    <div class="team-member-header"></div>
                    <img src="img/wei.jpg" alt="Tan Wei Zhao">
                    <div class="team-member-body">
                        <h3>Tan Wei Zhao</h3>
                        <p class="role">Frontend Developer</p>
                        <p class="matric">B032310063</p>
                        <a href="resumes/wei_resume.pdf" class="resume-btn" target="_blank">View Resume</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <p>© <?php echo date("Y"); ?> CookTogether. All Rights Reserved.</p>
    </footer>

</body>
</html>
