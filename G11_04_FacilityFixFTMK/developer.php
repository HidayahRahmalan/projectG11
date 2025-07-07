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

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
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

        .login-table {
            margin-top: 2rem;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .login-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .login-table th,
        .login-table td {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }

        .login-table th {
            background-color: #112D4E;
            color: white;
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

        .card-profile {
            width: 110px;
            height: 160px;
            margin: 2rem auto;
        }

        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.25);
        }

        .card-content {
            padding: 1.25rem;
        }

        .card h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .card h4 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .card p {
            font-size: 0.95rem;
            color: #555;
        }

        .team-section .grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem auto;
        }

        .card-profile {
            width: 180px;
            height: 300px;
            margin: 1rem;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .card-profile img {
            width: 120px;
            height: 150px;
            margin: 0 auto;
            border-radius: var(--radius);
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
        <p style="margin-top: 1rem; font-weight: 600; color: white;">Student BITD Gempak</p><br><br>

        <h2 style="margin-top: 1rem; font-weight: 600; color: white;">Team Members of Group 4</h2>
        <div class="grid">
            <!-- Team Member 1 -->
            <a href="document/resume-zulaikha.pdf" class="card card-profile" target="_blank">
                <img src="images/zulaikha.png" class="profile-img" alt="Resume Zulaikha Aqilah">
                <div class="card-content">
                    <h4>Nur Zulaikha Aqilah Binti Mohammad Suhairi</h4>
                    <p>Click to view resume</p>
                </div>
            </a>

            <!-- Team Member 2 -->
            <a href="document/resume-izzatul.pdf" class="card card-profile" target="_blank">
                <img src="images/izzatul.png" class="profile-img" alt="Resume Izzatul Widad">
                <div class="card-content">
                    <h4>Izzatul Widad Binti Azman</h4>
                    <p>Click to view resume</p>
                </div>
            </a>

            <!-- Team Member 3 -->
            <a href="document/resume-atilia.pdf" class="card card-profile" target="_blank">
                <img src="images/atilia.png" class="profile-img" alt="Resume Atilia Zainuddin">
                <div class="card-content">
                    <h4>Nur Shahira Atilia binti Zainuddin</h4>
                    <p>Click to view resume</p>
                </div>
            </a>

            <!-- Team Member 4 -->
            <a href="document/resume-syafiqah.pdf" class="card card-profile" target="_blank">
                <img src="images/syafiqah.png" class="profile-img" alt="Resume Syafiqah">
                <div class="card-content">
                    <h4>Nur Syafiqah Binti Shamsudin</h4>
                    <p>Click to view resume</p>
                </div>
            </a>

            <!-- Team Member 5 -->
            <a href="document/resume-athirah.pdf" class="card card-profile" target="_blank">
                <img src="images/athirah.png" class="profile-img" alt="Resume Athirah Amani">
                <div class="card-content">
                    <h4>Nur Athirah Amani Binti Abdullah</h4>
                    <p>Click to view resume</p>
                </div>
            </a>
        </div><br>

        <h2 style="margin-top: 1rem; font-weight: 600; color: white;">Our Work</h2><br>
    </section>

    <main class="grid">
        <div class="card">
            <div class="card-content">
                <h2 style="text-align: center; margin-bottom: 1rem;">About FacilityFix.FTMK</h2><br>
                <p style="text-align: center;">At UTeM’s Faculty of Information and Communication Technology (FTMK), reporting maintenance issues like broken chairs, leaking pipes, or faulty air conditioners is often slow and messy. People usually use phone calls, emails, or paper forms which can cause delays, confusion, and problems being forgotten.
                    FacilityFix.FTMK is a web-based system we created to make this process easier and faster. Staff can report issues online with clear details and upload photos or videos as proof. This helps the maintenance team understand the problem better and fix it sooner.
                    The system also lets users track the progress of their reports, making the whole process more organized, transparent, and efficient for everyone at FTMK.
                </p>
            </div>
        </div>

        <!-- Website navigation card -->
        <a href="index.php" class="card" target="_blank">
            <img src="images/facilityfix-preview.png" alt="FacilityFix.FTMK Preview">
            <div class="card-content">
                <h4>FacilityFix.FTMK</h4>
                <p>Click to visit our facility maintenance platform.</p>
            </div>
        </a>
    </main>

    <footer>
        &copy; <?= date('Y') ?> Project Team. All rights reserved.
    </footer>
</body>

</html>