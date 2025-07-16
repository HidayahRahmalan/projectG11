<?php
// FILE: backend/view_module.php

// 1. Secure the page AND verify ownership.
require_once 'auth_instructor.php'; 
require_once 'connection.php';
$loggedInUserId = $_SESSION['user_id'];

// 2. Get the Module ID from the URL and validate it.
$moduleId = $_GET['id'] ?? null;
if (!$moduleId || !is_numeric($moduleId)) {
    header('Location: ../instructor/instructormanage.php?error=invalid_id');
    exit;
}

try {
    // 3. Fetch all data for this specific module, ensuring the instructor owns it.
    $stmtModule = $conn->prepare(
        "SELECT m.*, t.TopicName FROM Module m JOIN Topic t ON m.TopicID = t.TopicID WHERE m.ModuleID = :moduleID AND m.UserID = :userID"
    );
    $stmtModule->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $stmtModule->bindParam(':userID', $loggedInUserId, PDO::PARAM_INT);
    $stmtModule->execute();
    $module = $stmtModule->fetch(PDO::FETCH_ASSOC);

    // If the module doesn't exist or isn't owned by this instructor, redirect.
    if (!$module) {
        header('Location: ../instructor/instructormanage.php?error=notfound');
        exit;
    }

    // Get the video file
    $stmtVideo = $conn->prepare("SELECT FilePath FROM video WHERE ModuleID = :moduleID LIMIT 1");
    $stmtVideo->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $stmtVideo->execute();
    $video = $stmtVideo->fetch(PDO::FETCH_ASSOC);

    // Get ALL slide files
    $stmtSlides = $conn->prepare("SELECT SlideTitle, FilePath FROM slide WHERE ModuleID = :moduleID");
    $stmtSlides->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $stmtSlides->execute();
    $slides = $stmtSlides->fetchAll(PDO::FETCH_ASSOC);

    // Get ALL note files
    $stmtNotes = $conn->prepare("SELECT NoteTitle, FilePath FROM notes WHERE ModuleID = :moduleID");
    $stmtNotes->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $stmtNotes->execute();
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Review: <?php echo htmlspecialchars($module['Title']); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root { --primary-color: #ea4c89; --secondary-color: #4b05fd; --view-color: #17a2b8; --light-gray: #f5f7fa; --border-color: #e0e0e0; --text-color: #1e1e1e; --text-light: #555; }
    body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); margin: 0; padding: 0 40px 40px; color: var(--text-color); }
    header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }
    .logo { font-size: 24px; font-weight: 700; color: var(--primary-color); }
    nav ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; }
    nav a { text-decoration: none; color: #333; font-size: 16px; padding: 6px 12px; border-radius: 6px; transition: 0.3s ease; }
    nav a:hover, nav a.active { background-color: var(--primary-color); color: white; }
    .container { max-width: 900px; margin: 0 auto; }
    .module-header h1 { font-size: 36px; color: var(--primary-color); margin-bottom: 5px; }
    .module-header .topic { font-size: 18px; color: var(--text-light); font-weight: 500; margin-bottom: 10px; }
    .module-header .description { font-size: 16px; margin-bottom: 30px; }
    .video-player { background-color: black; margin-bottom: 20px; border-radius: 12px; overflow: hidden; }
    .video-player video { width: 100%; display: block; }
    .content-section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-top: 30px; }
    .content-section h2 { margin-top: 0; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
    .file-list { list-style: none; padding: 0; }
    .file-list li { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .file-list li:last-child { border-bottom: none; }
    .file-name { font-weight: 500; }
    .file-actions a { text-decoration: none; color: white; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; transition: 0.3s; margin-left: 10px; }
    .download-btn { background-color: var(--primary-color); }
    .no-content { color: var(--text-light); }
  </style>
</head>
<body>
  <header>
    <div class="logo">E-Learning</div>
    <nav>
      <ul>
        <li><a href="../instructor/instructorhomes.php">Dashboard</a></li>
        <li><a href="../instructor/instructorupload.php">Upload</a></li>
        <li><a href="../instructor/instructormanage.php" class="active">Manage</a></li>
        <li><a href="../Homepage/Home.html">Logout</a></li>
      </ul>
    </nav>
  </header>
  <div class="container">
    <div class="module-header">
        <h1><?php echo htmlspecialchars($module['Title']); ?></h1>
        <p class="topic">Topic: <?php echo htmlspecialchars($module['TopicName']); ?></p>
        <p class="description"><?php echo htmlspecialchars($module['Description']); ?></p>
    </div>
    <?php if ($video && !empty($video['FilePath'])): ?>
        <div class="video-player">
            <video controls><source src="../uploads/videos/<?php echo htmlspecialchars($video['FilePath']); ?>" type="video/mp4"></video>
        </div>
        <div class="file-actions" style="text-align: right;"><a href="../uploads/videos/<?php echo htmlspecialchars($video['FilePath']); ?>" class="download-btn" download>Download Video</a></div>
    <?php endif; ?>
    <?php if (!empty($slides)): ?>
        <div class="content-section">
            <h2>Slides</h2>
            <ul class="file-list">
                <?php foreach ($slides as $slide): ?>
                    <li>
                        <span class="file-name"><?php echo htmlspecialchars($slide['SlideTitle']); ?></span>
                        <div class="file-actions"><a href="../uploads/slides/<?php echo htmlspecialchars($slide['FilePath']); ?>" class="download-btn" download>Download</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($notes)): ?>
        <div class="content-section">
            <h2>Notes</h2>
            <ul class="file-list">
                <?php foreach ($notes as $note): ?>
                    <li>
                        <span class="file-name"><?php echo htmlspecialchars($note['NoteTitle']); ?></span>
                        <div class="file-actions"><a href="../uploads/notes/<?php echo htmlspecialchars($note['FilePath']); ?>" class="download-btn" download>Download</a></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if (empty($video) && empty($slides) && empty($notes)): ?>
        <div class="content-section"><p class="no-content">No content has been uploaded for this module yet.</p></div>
    <?php endif; ?>
  </div>
</body>
</html>