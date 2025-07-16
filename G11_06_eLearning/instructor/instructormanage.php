<?php
// FILE: instructor/instructormanage.php
require_once '../backend/auth_instructor.php';
require_once '../backend/connection.php';
$loggedInUserId = $_SESSION['user_id'];
try {
    $stmt = $conn->prepare("SELECT ModuleID, Title, Description, isPublished, publishedAt, AverageRating, TotalViews FROM Module WHERE UserID = :userID AND deleted_at IS NULL ORDER BY ModuleID DESC");
    $stmt->bindParam(':userID', $loggedInUserId, PDO::PARAM_INT);
    $stmt->execute();
    $allModules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $publishedModules = [];
    $draftModules = [];
    foreach ($allModules as $module) {
        if ($module['isPublished'] === 'yes') {
            $publishedModules[] = $module;
        } else {
            $draftModules[] = $module;
        }
    }
} catch (PDOException $e) {
    die("Error fetching modules: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Your Modules</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root { 
        --primary-color: #ea4c89; --primary-hover: #d13f76; 
        --secondary-color: #4b05fd; --secondary-hover: #3c04ca;
        --success-color: #28a745; --success-hover: #218838;
        --error-color: #dc3545; --error-hover: #c82333;
        --view-color: #17a2b8; --view-hover: #138496; /* NEW: Color for the view button */
        --light-gray: #f5f7fa; --border-color: #e0e0e0; 
        --text-color: #1e1e1e; --text-light: #555; 
    }
    body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); margin: 0; padding: 0 40px 40px; color: var(--text-color); }
    header { display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }
    .logo { font-size: 24px; font-weight: 700; color: var(--primary-color); }
    nav ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; }
    nav a { text-decoration: none; color: #333; font-size: 16px; padding: 6px 12px; border-radius: 6px; transition: 0.3s ease; }
    nav a:hover, nav a.active { background-color: var(--primary-color); color: white; }
    h1 { text-align: center; margin: 40px 0 30px; color: var(--primary-color); }
    .tab-nav { display: flex; justify-content: center; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }
    .tab-nav button { padding: 10px 20px; font-size: 16px; font-weight: 600; border: none; background: none; cursor: pointer; color: var(--text-light); border-bottom: 3px solid transparent; transition: color 0.3s, border-color 0.3s; }
    .tab-nav button:hover { color: var(--primary-color); }
    .tab-nav button.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    .module-list { max-width: 900px; margin: 0 auto; display: grid; gap: 20px; }
    .module-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
    .module-info { flex-grow: 1; }
    .module-info h3 { margin: 0 0 5px; color: var(--primary-color); }
    .module-info p { margin: 0; color: var(--text-light); font-size: 14px; }
    .module-stats { margin-top: 10px; display: flex; gap: 20px; font-size: 13px; color: var(--text-light); width: 100%; }
    .module-stats span { font-weight: 500; }
    .module-actions { display: flex; gap: 10px; align-items: center; margin-left: auto; padding-left: 20px; }
    .module-actions button, .module-actions a { padding: 8px 12px; font-size: 14px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: background-color 0.3s; text-decoration: none; color: white; display: inline-block; }
    .edit-btn { background-color: var(--secondary-color); }
    .edit-btn:hover { background-color: var(--secondary-hover); }
    .delete-btn { background-color: var(--error-color); }
    .delete-btn:hover { background-color: var(--error-hover); }
    .publish-btn { background-color: var(--success-color); }
    .publish-btn:hover { background-color: var(--success-hover); }
    .view-btn { background-color: var(--view-color); } /* NEW */
    .view-btn:hover { background-color: var(--view-hover); } /* NEW */
    .no-modules { text-align: center; color: var(--text-light); margin-top: 50px; }
  </style>
</head>
<body>
  <header>
    <div class="logo">E-Learning</div>
    <nav>
      <ul>
        <li><a href="instructorhomes.php">Dashboard</a></li>
        <li><a href="instructorupload.php">Upload</a></li>
        <li><a href="instructormanage.php" class="active">Manage</a></li>
        <li><a href="../Homepage/Home.html" onclick="confirmLogout(event)">Logout</a></li>
      </ul>
    </nav>
  </header>
  <h1>Manage Your Modules</h1>
  <div class="tab-nav">
      <button class="tab-btn active" data-tab="published">Published</button>
      <button class="tab-btn" data-tab="drafts">Drafts</button>
  </div>
  <div id="published" class="tab-content">
      <div class="module-list">
          <?php if (empty($publishedModules)): ?> <p class="no-modules">You have no published modules.</p> <?php else: ?>
              <?php foreach ($publishedModules as $module): ?>
                  <div class="module-card">
                      <div class="module-info">
                          <h3><?php echo htmlspecialchars($module['Title']); ?></h3>
                          <p><?php echo htmlspecialchars($module['Description']); ?></p>
                          <div class="module-stats">
                            <span>★ <?php echo htmlspecialchars($module['AverageRating'] ?? 'N/A'); ?></span>
                            <span>👀 <?php echo htmlspecialchars($module['TotalViews'] ?? 0); ?> Views</span>
                            <?php if ($module['publishedAt']): ?><span>🗓️ Pub: <?php echo date('M j, Y', strtotime($module['publishedAt'])); ?></span><?php endif; ?>
                          </div>
                      </div>
                      <div class="module-actions">
                          <!-- NEW: View button added -->
                          <a href="../backend/view_module.php?id=<?php echo $module['ModuleID']; ?>" class="view-btn">View</a>
                          <a href="../backend/edit_module.php?id=<?php echo $module['ModuleID']; ?>" class="edit-btn">Edit</a>
                          <form action="../backend/module_delete.php" method="POST" onsubmit="return confirm('Are you sure?');" style="margin:0;"><input type="hidden" name="id" value="<?php echo $module['ModuleID']; ?>"><button type="submit" class="delete-btn">Delete</button></form>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
  </div>
  <div id="drafts" class="tab-content" style="display: none;">
      <div class="module-list">
          <?php if (empty($draftModules)): ?> <p class="no-modules">You have no draft modules.</p> <?php else: ?>
              <?php foreach ($draftModules as $module): ?>
                  <div class="module-card">
                      <div class="module-info">
                          <h3><?php echo htmlspecialchars($module['Title']); ?></h3>
                          <p><?php echo htmlspecialchars($module['Description']); ?></p>
                      </div>
                      <div class="module-actions">
                          <form action="../backend/publish_module.php" method="POST" style="margin:0;"><input type="hidden" name="id" value="<?php echo $module['ModuleID']; ?>"><button type="submit" class="publish-btn">Publish</button></form>
                          <!-- NEW: View button added -->
                          <a href="../backend/view_module.php?id=<?php echo $module['ModuleID']; ?>" class="view-btn">View</a>
                          <a href="../backend/edit_module.php?id=<?php echo $module['ModuleID']; ?>" class="edit-btn">Edit</a>
                          <form action="../backend/module_delete.php" method="POST" onsubmit="return confirm('Are you sure?');" style="margin:0;"><input type="hidden" name="id" value="<?php echo $module['ModuleID']; ?>"><button type="submit" class="delete-btn">Delete</button></form>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>
  </div>
  <script>
      document.addEventListener('DOMContentLoaded', function() { /* JS is unchanged */ const tabButtons=document.querySelectorAll('.tab-btn'); const tabContents=document.querySelectorAll('.tab-content'); tabButtons.forEach(button=>{ button.addEventListener('click', function() { tabButtons.forEach(btn=>btn.classList.remove('active')); this.classList.add('active'); tabContents.forEach(content=>content.style.display='none'); const tabId=this.getAttribute('data-tab'); document.getElementById(tabId).style.display='block'; }); }); });

      
function confirmLogout(event) {
  event.preventDefault(); // Prevent immediate navigation
  if (confirm("Are you sure you want to logout?")) {
    window.location.href = "../Homepage/Home.html"; // Change this to your actual logout destination
  }
}

  </script>
</body>
</html>