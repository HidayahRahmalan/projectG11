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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
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
            padding: 1rem;
            text-align: center;
        }
        .card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-bottom: 1px solid #ddd;
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
        .audio-wrapper {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            pointer-events: none; /* disables direct interaction */
            opacity: 0;
            transition: opacity 2s ease;
        }
        .audio-wrapper.show {
            opacity: 1;
            pointer-events: auto;
        }
        audio {
            width: 300px;
            height: 35px;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        /* Floating music toggle button */
        #toggle-audio {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--main-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease, transform 0.2s ease;
            z-index: 1001;
        }
        #toggle-audio:hover {
            background-color: #357ABD;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="audio-wrapper">
        <audio id="background-audio" src="Sunset_Drive.mp3"></audio>
    </div>
    <button id="toggle-audio" title="Play/Pause Music">🎵</button>
    <header>
        <h1>Welcome to Our Project Showcase</h1>
        <p>Explore our featured projects and meet our wonderful team</p>
    </header>

    <section class="team-section">
        <img class="team-image" src="images/kamiGeng.jpeg" alt="Team Member">
        <p style="margin-top: 1rem; font-weight: 600; color: white;">Student BITD Gempak</p>
        <a href="index.php" style="
        margin-top: 1rem;
        width: 280px;
        display: block;
        text-align: center;
        padding: 0.75rem 1.5rem;
        background-color: var(--main-color);
        color: white;
        font-weight: 600;
        border: none;
        border-radius: 0.5rem;
        text-decoration: none;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        transition: background-color 0.3s ease, transform 0.2s ease;
    "
    onmouseover="this.style.backgroundColor='#357ABD'; this.style.transform='translateY(-2px)';"
    onmouseout="this.style.backgroundColor='var(--main-color)'; this.style.transform='none';">
        Explore MaintainEase
    </a>
    </section>
    
    <main class="grid">
        <div class="card">
            <a href="resume/Resume_auni.pdf" target="_blank">
                <img src="resume/Auni.jpg" alt="Auni Najibah Binti Ismail">
            </a>
            <div class="card-content">
                <h2>Auni Najibah Binti Ismail</h2>
            </div>
        </div>

        <div class="card">
            <a href="resume/Resume_bhuva.pdf" target="_blank">
                <img src="resume/Bhuva.jpg" alt="Bhuvanasri A/P Sritharan">
            </a>
            <div class="card-content">
                <h2>Bhuvanasri A/P Sritharan</h2>
            </div>
        </div>

        <div class="card">
            <a href="resume/Resume_hannah.pdf" target="_blank">
                <img src="resume/Hannah.jpg" alt="Nurin Hannah Binti Zamberi">
            </a>
            <div class="card-content">
                <h2>Nurin Hannah Binti Zamberi</h2>
            </div>
        </div>

        <div class="card">
            <a href="resume/Resume_nadhira.pdf" target="_blank">
                <img src="resume/Nadhira.jpg" alt="Nurnadhira Binti Mohd Faisol">
            </a>
            <div class="card-content">
                <h2>Nurnadhira Binti Mohd Faisol</h2>
            </div>
        </div>

        <div class="card">
            <a href="resume/Resume_shahirah.pdf" target="_blank">
                <img src="resume/shahirah.jpg" alt="Nur Shahirah Binti Rahmat">
            </a>
            <div class="card-content">
                <h2>Nur Shahirah Binti Rahmat</h2>
            </div>
        </div>
    </main>


    <footer>
        &copy; <?= date('Y') ?> Project Team. All rights reserved.
    </footer>

    <script>
        const audio = document.getElementById('background-audio');
        const toggleBtn = document.getElementById('toggle-audio');
        const audioWrapper = document.querySelector('.audio-wrapper');

        // Fade in audio element
        window.addEventListener('load', () => {
            audioWrapper.classList.add('show');
            // Attempt to autoplay (may be blocked in some browsers)
            audio.volume = 0;
            audio.play().then(() => {
                // Gradually increase volume for fade-in effect
                let vol = 0;
                const fade = setInterval(() => {
                    if (vol < 1) {
                        vol += 0.05;
                        audio.volume = Math.min(vol, 1);
                    } else {
                        clearInterval(fade);
                    }
                }, 200);
            }).catch(() => {
                // If autoplay blocked, do nothing
            });
        });

        // Toggle play/pause on button click
        toggleBtn.addEventListener('click', () => {
            if (audio.paused) {
                audio.play();
            } else {
                audio.pause();
            }
        });
    </script>
</body>
</html>
