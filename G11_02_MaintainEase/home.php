<?php
session_start();
include 'db.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Home - MaintainEase</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.05);
            --color-button-bg: #111827;
            --color-button-bg-hover: #000000;
            --radius: 0.75rem;
            --transition: 0.3s ease;
            --max-width: 1200px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-body);
            font-size: 18px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            position: sticky;
            top: 0;
            background: var(--color-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            box-shadow: 0 2px 6px var(--color-shadow);
            z-index: 10;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-text-head);
            user-select: none;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
            gap: 1.5rem;
        }

        nav a {
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        nav a:hover,
        nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
        }

        main {
            flex-grow: 1;
            max-width: var(--max-width);
            width: 100%;
            padding: 3rem 2rem 4rem;
            margin: 0 auto;
        }

        main.container {
            max-width: var(--max-content-width);
            margin: 3rem auto 4rem;
            padding: 0 1rem;
        }

        h1 {
            font-weight: 700;
            font-size: 3rem;
            color: white;
            margin-bottom: 0.5rem;
            user-select: none;
        }
        .banner-container {
            display: flex;
            justify-content: center; /* Center the image horizontally */
            position: relative;
            width: 100%;
            margin-bottom: 2rem;
        }

        .banner {
            width: 70%;
            display: block;
            border-radius: var(--border-radius);
        }

        .banner-text {
            position: absolute;
            top: 50%; /* Center vertically */
            left: 50%; /* Center horizontally */
            transform: translate(-50%, -50%); /* Perfect center */
            color: white;
            background-color: rgba(0, 0, 0, 0.5); /* Optional: for better contrast */
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
        }

        .banner-text h1 {
            margin: 0 0 0.5rem 0;
            font-size: 1.75rem;
        }

        footer {
            text-align: center;
            padding: 1.5rem;
            background-color: black;
            color: var(--header-font-color);
            position: relative;
            bottom: 0;
            width: 100%;
            margin-top: 3rem;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo" tabindex="0">MaintainEase</div>
        <nav aria-label="Primary navigation">
            <a href="home.php" class="active" aria-current="page">Home</a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'staff'): ?>
                    <a href="insertmaintenance.php">Submit Request</a>
                    <a href="request_list.php">My Requests</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Users</a>
                    <a href="viewquery.php">Maintenance List</a>
                <?php endif; ?>

                <a href="profile.php">Profile</a>
                <a href="logout.php">Sign Out</a>
            <?php else: ?>
                <a href="login.php">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container" role="main" aria-label="MaintainEase Home">
        <div class="banner-container">
            <img class="banner" src="img.png" alt="Banner Image">
            <div class="banner-text">
                <h1>
                <?php
                     if (isset($_SESSION['name'])) {
                         echo "Welcome, " . htmlspecialchars($_SESSION['name']) . "!";
                     } else {
                        echo "Welcome!";
                    }
                ?>
                </h1>
                <p>This is your dashboard.</p>
            </div>
        </div>

        <section>
            <h2 style="color: var(--color-text-head); margin-bottom: 1rem; text-align: center;">About MaintainEase</h2>
            <p style = "margin-bottom: 1rem; text-align: center;">MaintainEase is a maintenance request management system designed to streamline the process of submitting, tracking, and managing maintenance tasks within your organization.</p>
            <p style = "margin-bottom: 1rem; text-align: center;">Whether you are a staff member reporting an issue or an admin managing requests, MaintainEase simplifies the entire process with real-time updates and status tracking.</p>
            <br>
            
            <h2 style="color: var(--color-text-head); margin-bottom: 1.5rem; text-align: center;">How to Use MaintainEase</h2>

            <div style="display: flex; justify-content: space-around; flex-wrap: wrap; gap: 1.5rem;">
                <!-- Step 1 Card -->
                <div style="background-color: #f9f9f9; border-radius: var(--radius); padding: 1rem; box-shadow: 0 2px 6px var(--color-shadow); width: 300px; text-align: center;">
                    <img src="2.png" alt="Step 1" style="width: 100%; border-radius: var(--radius); margin-bottom: 1rem;">
                    <h3 style="color: var(--color-text-head); margin-bottom: 0.5rem;">Step 1</h3>
                    <p>Take a picture or video of the issue.</p>
                </div>

                <!-- Step 2 Card -->
                <div style="background-color: #f9f9f9; border-radius: var(--radius); padding: 1rem; box-shadow: 0 2px 6px var(--color-shadow); width: 300px; text-align: center;">
                    <img src="4.png" alt="Step 2" style="width: 100%; border-radius: var(--radius); margin-bottom: 1rem;">
                    <h3 style="color: var(--color-text-head); margin-bottom: 0.5rem;">Step 2</h3>
                    <p>Make a report and upload the media.</p>
                </div>

                <!-- Step 3 Card -->
                <div style="background-color: #f9f9f9; border-radius: var(--radius); padding: 1rem; box-shadow: 0 2px 6px var(--color-shadow); width: 300px; text-align: center;">
                    <img src="3.png" alt="Step 3" style="width: 100%; border-radius: var(--radius); margin-bottom: 1rem;">
                    <h3 style="color: var(--color-text-head); margin-bottom: 0.5rem;">Step 3</h3>
                    <p>Submit your report and wait for results.</p>
                </div>
            </div>
        </section>


    </main>

    <!-- Footer -->
    <footer>
        <p>For any inquiry, do contact us at +6011-26960698</p>
        <p>From: Auni, Bhuva, Hannah, Nadhira, Shahirah from BITD</p>
    </footer>
    
</body>

</html>
