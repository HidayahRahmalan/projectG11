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

    <main class="grid">
 
 
 
    </main>

    <footer>
        &copy; <?= date('Y') ?> Project Team. All rights reserved.
    </footer>
</body>
</html>
