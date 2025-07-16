<?php
// THIS IS THE FIRST NEW LINE. It runs the guard file to check the session.
require_once '../backend/auth_instructor.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Instructor Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background-color: #f9f9fc;
      color: #1e1e1e;
      padding: 0 40px;
    }
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px 0;
      border-bottom: 1px solid #eee;
    }
    .logo {
      font-size: 24px;
      font-weight: 700;
      color: #ea4c89;
    }
    nav ul {
      list-style: none;
      display: flex;
      gap: 25px;
      margin: 0;
      padding: 0;
    }
    nav a {
      text-decoration: none;
      color: #333;
      font-size: 16px;
      padding: 6px 12px;
      border-radius: 6px;
      transition: 0.3s ease;
    }
    nav a:hover, nav a.active {
      background-color: #ea4c89;
      color: white;
    }

    .hero {
      margin: 40px 0;
      text-align: center;
    }
    .hero h1 {
      font-size: 36px;
      margin-bottom: 10px;
      color: #ea4c89;
    }
    .hero p {
      font-size: 18px;
      color: #555;
    }

    .dashboard {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 30px;
      margin-bottom: 60px;
    }

    .card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      text-align: center;
      transition: transform 0.2s ease;
    }
    .card:hover {
      transform: translateY(-4px);
    }
    .card h3 {
      margin-top: 15px;
      font-size: 20px;
      color: #ea4c89;
    }
    .card p {
      color: #666;
      font-size: 14px;
    }
    .card a {
      display: inline-block;
      margin-top: 15px;
      text-decoration: none;
      padding: 8px 16px;
      background-color: #ea4c89;
      color: white;
      border-radius: 6px;
      font-size: 14px;
    }

    @media (max-width: 600px) {
      body {
        padding: 20px;
      }
      .hero h1 {
        font-size: 28px;
      }
    }
  </style>
</head>
<body>

<header>
  <div class="logo">E-Learning</div>
  <nav>
    <ul>
      <li><a href="instructorhomes.php" class="active">Dashboard</a></li>
      <li><a href="instructorupload.php">Upload</a></li>
      <li><a href="instructormanage.php">Manage</a></li>
      <li><a href="../Homepage/Home.html" onclick="confirmLogout(event)">Logout</a></li>
    </ul>
  </nav>
</header>

<section class="hero">
  <!-- THIS IS THE SECOND NEW LINE. It shows the username from the session. -->
  <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
  <p>Manage your courses, upload new modules, and track student engagement.</p>
</section>

<section class="dashboard">
  <div class="card">
    <img src="https://img.icons8.com/fluency/48/upload.png" alt="Upload Icon"/>
    <h3>Upload Module</h3>
    <p>Upload PDFs, slides, and videos for your new course modules.</p>
    <a href="instructorupload.php">Go to Upload</a>
  </div>

  <div class="card">
    <img src="https://img.icons8.com/fluency/48/edit-file.png" alt="Manage Icon"/>
    <h3>Manage Modules</h3>
    <p>View, edit, or delete your existing course materials.</p>
    <a href="instructormanage.php">Manage Content</a>
  </div>
</section>

<script>
function confirmLogout(event) {
  event.preventDefault(); // Prevent immediate navigation
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "../Homepage/Home.html"; // Change this to your actual logout destination
  }
}
  </script>
</body>
</html>