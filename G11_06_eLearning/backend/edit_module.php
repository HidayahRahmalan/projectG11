<?php
// FILE: backend/edit_module.php (NEW LOCATION)

// 1. PATHS ARE UPDATED FOR THE NEW LOCATION
// Since this file is now in 'backend', we don't need to go up a level ('../')
require_once 'auth_instructor.php';
require_once 'connection.php';
$loggedInUserId = $_SESSION['user_id'];

// Get the Module ID from the URL and validate it
$moduleId = $_GET['id'] ?? null;
if (!$moduleId || !is_numeric($moduleId)) {
    // Redirect path is updated
    header('Location: ../instructor/instructormanage.php?error=invalid_id');
    exit;
}

try {
    $stmt = $conn->prepare(
        "SELECT m.ModuleID, m.Title, m.Description, m.isPublished, m.TopicID, t.TopicName, t.TopicDescription
         FROM Module m
         JOIN Topic t ON m.TopicID = t.TopicID
         WHERE m.ModuleID = :moduleID AND m.UserID = :userID AND m.deleted_at IS NULL"
    );
    $stmt->bindParam(':moduleID', $moduleId, PDO::PARAM_INT);
    $stmt->bindParam(':userID', $loggedInUserId, PDO::PARAM_INT);
    $stmt->execute();
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        // Redirect path is updated
        header('Location: ../instructor/instructormanage.php?error=notfound');
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" /><title>Edit Module</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root { --primary-color: #ea4c89; --primary-hover: #d13f76; --secondary-color: #4b05fd; --success-color: #28a745; --error-color: #dc3545; --light-gray: #f5f7fa; --border-color: #e0e0e0; --text-color: #1e1e1e; --text-light: #555; }
    body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); margin: 0; padding: 0 40px 40px; color: var(--text-color); line-height: 1.6; }
    header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }
    .logo { font-size: 24px; font-weight: 700; color: var(--primary-color); }
    nav ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; }
    nav a { text-decoration: none; color: #333; font-size: 16px; padding: 6px 12px; border-radius: 6px; transition: 0.3s ease; }
    nav a:hover, nav a.active { background-color: var(--primary-color); color: white; }
    h1 { text-align: center; margin: 40px 0 30px; color: var(--primary-color); }
    form { background: white; max-width: 700px; margin: 0 auto; padding: 40px; border-radius: 16px; box-shadow: 0 4px 25px rgba(0,0,0,0.07); }
    label { font-weight: 600; display: block; margin-bottom: 8px; }
    input[type="text"], select { width: 100%; padding: 12px; margin-bottom: 20px; font-size: 16px; border-radius: 8px; border: 1px solid var(--border-color); }
    .submit-buttons { display: flex; gap: 10px; margin-top: 20px; }
    button, .cancel-btn { flex: 1; padding: 14px 20px; font-size: 16px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; color: white !important; }
    button { background-color: var(--primary-color); }
    .cancel-btn { background-color: #6c757d; text-align: center; line-height: 1.5; }
  </style>
</head>
<body>
  <header>
    <div class="logo">E-Learning</div>
    <nav>
      <ul>
        <!-- Navigation links are updated for the new location -->
        <li><a href="../instructor/instructorhomes.php">Dashboard</a></li>
        <li><a href="../instructor/instructorupload.php">Upload</a></li>
        <li><a href="../instructor/instructormanage.php" class="active">Manage</a></li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </nav>
  </header>
  <h1>Edit Module</h1>

  <!-- The form action is updated for the new location -->
  <form action="update_module.php" method="POST">
    <input type="hidden" name="module_id" value="<?php echo htmlspecialchars($module['ModuleID']); ?>">
    <input type="hidden" name="topic_id" value="<?php echo htmlspecialchars($module['TopicID']); ?>">
    <label for="title">Module Title</label>
    <input type="text" id="title" name="title" required value="<?php echo htmlspecialchars($module['Title']); ?>" />
    <label for="description">Description</label>
    <input type="text" id="description" name="description" required value="<?php echo htmlspecialchars($module['Description']); ?>" />
    <label for="topic">Topic</label>
    <input type="text" id="topic" name="topic" required value="<?php echo htmlspecialchars($module['TopicName']); ?>" />
    <label for="topicdescription">Topic Description</label>
    <input type="text" id="topicdescription" name="topicdescription" required value="<?php echo htmlspecialchars($module['TopicDescription']); ?>" />
    <label for="isPublished">Status</label>
    <select id="isPublished" name="isPublished">
        <option value="yes" <?php if ($module['isPublished'] === 'yes') echo 'selected'; ?>>Published</option>
        <option value="no" <?php if ($module['isPublished'] === 'no') echo 'selected'; ?>>Draft</option>
    </select>
    <p style="color: #555; font-size: 14px; margin-top: 25px;">Note: To change files (video, slides, notes), please upload a new module.</p>
    <div class="submit-buttons">
      <button type="submit">Update Module</button>
      <!-- The cancel button link is updated -->
      <a href="../instructor/instructormanage.php" class="cancel-btn">Cancel</a>
    </div>
  </form>
</body>
</html>