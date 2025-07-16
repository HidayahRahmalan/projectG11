<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Project Hub</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;600&display=swap');
        :root {
            --card-bg: rgba(255, 255, 255, 0.9);
            --radius: 1rem;
            --main-color: #4a90e2;
        }
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        html, body {
            height: 100%;
            font-family: 'Montserrat', sans-serif;
            color: #1a1a1a;
        }
        body {
            background: linear-gradient(135deg, #74ebd5 0%, #ACB6E5 100%);
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('images/background-pattern.png') center/cover no-repeat;
            opacity: 0.15;
            filter: blur(2px);
            z-index: -1;
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
            width: 280px;
            height: auto;
            border-radius: 1rem;
            object-fit: contain;
            border: 6px solid white;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2rem;
            width: 90%;
            max-width: 1200px;
            margin: 2rem auto;
        }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            text-decoration: none;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.25);
        }
        .card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .card-content {
            padding: 1.25rem;
        }
        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #333;
        }
        .card p {
            font-size: 0.95rem;
            color: #555;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .team-member {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(44, 62, 80, 0.08);
            padding: 20px 10px 30px 10px;
            margin-bottom: 20px;
            transition: box-shadow 0.3s;
        }
        .team-member:hover {
            box-shadow: 0 16px 32px rgba(44, 62, 80, 0.13);
        }
        .member-avatar-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            margin-bottom: 10px;
        }
        .member-avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.15);
            background: #eee;
        }
        .resume-preview {
            width: 220px;
            height: 220px;
            margin: 20px auto 10px auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(44, 62, 80, 0.10);
            background: #fafbfc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .resume-preview iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #fafbfc;
        }
        .member-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            margin-top: 5px;
        }
        .member-role {
            font-size: 1.1rem;
            color: var(--main-color);
            font-weight: 600;
            margin-bottom: 20px;
        }
        .member-info {
            color: #333;
        }
        .btn.btn-primary.mt-2 {
            margin-top: 0.75rem;
        }
        footer {
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
            color: #ffffffcc;
        }
    </style>
</head>
<body>
    <header>
        <h1>Welcome to Our Project Showcase</h1>
        <p>Explore our featured projects and meet our wonderful team</p>
    </header>

    <section class="team-section">
        <img class="team-image" src="images/kamiGeng.jpeg" alt="Team Member">
        <p style="margin-top: 1rem; font-weight: 600; color: white;">Student BITD Gempak</p>
    </section>

    <section class="team-section">
        <div class="container">
            <h2 class="section-title">Meet Our Team</h2>
            <div class="team-grid">
                <!-- Norleen Thohirah Binti Rafeli -->
                <div class="team-member">
                    <div class="member-avatar-wrapper">
                        <img src="team_members/picture/norleen.jpg" alt="Norleen" class="member-avatar">
                    </div>
                    <div class="member-info text-center">
                        <h3 class="member-name">Norleen Thohirah Binti Rafeli</h3>
                        <div class="resume-preview">
                            <iframe src="team_members/resume/NORLEEN RESUME.pdf" frameborder="0"></iframe>
                        </div>
                        <a href="team_members/resume/NORLEEN RESUME.pdf" class="btn btn-primary mt-2" target="_blank">View Full Resume (PDF)</a>
                    </div>
                </div>
                <!-- Nur Azalina Izani Binti Nasron -->
                <div class="team-member">
                    <div class="member-avatar-wrapper">
                        <img src="team_members/picture/azalina.jpg" alt="Azalina" class="member-avatar">
                    </div>
                    <div class="member-info text-center">
                        <h3 class="member-name">Nur Azalina Izani Binti Nasron</h3>
                        <div class="resume-preview">
                            <iframe src="team_members/resume/Azalina Resume.pdf" frameborder="0"></iframe>
                        </div>
                        <a href="team_members/resume/Azalina Resume.pdf" class="btn btn-primary mt-2" target="_blank">View Full Resume (PDF)</a>
                    </div>
                </div>
                <!-- Nuradlina Faqihah binti Mohd Adib -->
                <div class="team-member">
                    <div class="member-avatar-wrapper">
                        <img src="team_members/picture/adlina.jpg" alt="Nuradlina Faqihah binti Mohd Adib" class="member-avatar">
                    </div>
                    <div class="member-info text-center">
                        <h3 class="member-name">Nuradlina Faqihah binti Mohd Adib</h3>
                        <div class="resume-preview">
                            <iframe src="team_members/resume/Resume Nuradlina Faqihah binti Mohd Adib.pdf" frameborder="0"></iframe>
                        </div>
                        <a href="team_members/resume/Resume Nuradlina Faqihah binti Mohd Adib.pdf" class="btn btn-primary mt-2" target="_blank">View Full Resume (PDF)</a>
                    </div>
                </div>
                <!-- Barizah Mahamad Rusli -->
                <div class="team-member">
                    <div class="member-avatar-wrapper">
                        <img src="team_members/picture/Barizah.jpg" alt="Barizah" class="member-avatar">
                    </div>
                    <div class="member-info text-center">
                        <h3 class="member-name">Barizah Mahamad Rusli</h3>
                        <div class="resume-preview">
                            <iframe src="team_members/resume/Barizah Resume.pdf" frameborder="0"></iframe>
                        </div>
                        <a href="team_members/resume/Barizah Resume.pdf" class="btn btn-primary mt-2" target="_blank">View Full Resume (PDF)</a>
                    </div>
                </div>
                <!-- Maisara A/P Siput -->
                <div class="team-member">
                    <div class="member-avatar-wrapper">
                        <img src="team_members/picture/Maisara.png" alt="Maisara" class="member-avatar">
                    </div>
                    <div class="member-info text-center">
                        <h3 class="member-name">Maisara A/P Siput</h3>
                        <div class="resume-preview">
                            <iframe src="team_members/resume/Maisara Siput.pdf" frameborder="0"></iframe>
                        </div>
                        <a href="team_members/resume/Maisara Siput.pdf" class="btn btn-primary mt-2" target="_blank">View Full Resume (PDF)</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        &copy; <?= date('Y') ?> Project Team. All rights reserved.
    </footer>
</body>
</html> 
