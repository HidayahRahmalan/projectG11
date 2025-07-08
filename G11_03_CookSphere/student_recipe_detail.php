<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['recipe_id'])) {
    echo "Recipe not specified.";
    exit();
}

$recipe_id = intval($_GET['recipe_id']);

// Get recipe details + chef name
$sql = "SELECT r.RecipeID, r.Title, r.CuisineType, r.DietaryType, r.UploadDate, r.UserID, u.FullName
        FROM recipe r
        JOIN user u ON r.UserID = u.UserID
        WHERE r.RecipeID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Recipe not found.";
    exit();
}

$recipe = $result->fetch_assoc();

// Get steps
$steps = [];
$stmt_steps = $conn->prepare("SELECT StepNumber, Instruction FROM step WHERE RecipeID = ? ORDER BY StepNumber ASC");
$stmt_steps->bind_param("i", $recipe_id);
$stmt_steps->execute();
$res_steps = $stmt_steps->get_result();
while ($row = $res_steps->fetch_assoc()) {
    $steps[] = $row;
}

// Get media (images and videos)
$media = [];
$stmt_media = $conn->prepare("SELECT MediaType, MediaPath FROM media WHERE RecipeID = ?");
$stmt_media->bind_param("i", $recipe_id);
$stmt_media->execute();
$res_media = $stmt_media->get_result();
while ($row = $res_media->fetch_assoc()) {
    $media[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title><?= htmlspecialchars($recipe['Title']) ?> - Recipe Details</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 p-6">
  <!--<div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow">-->
  <div class="relative max-w-4xl mx-auto bg-white p-6 rounded-xl shadow">

    <h1 class="text-3xl font-bold text-yellow-600 mb-4"><?= htmlspecialchars($recipe['Title']) ?></h1>
    <p class="mb-1"><strong>Cuisine Type:</strong> <?= htmlspecialchars($recipe['CuisineType']) ?></p>
    <p class="mb-1"><strong>Dietary Type:</strong> <?= htmlspecialchars($recipe['DietaryType']) ?></p>
    <p class="mb-1"><strong>Uploaded by:</strong> <?= htmlspecialchars($recipe['FullName']) ?></p>
    <p class="mb-4 text-gray-500"><strong>Date:</strong> <?= date("d M Y", strtotime($recipe['UploadDate'])) ?></p>

    <!-- Media gallery -->
    <?php if (count($media) > 0): ?>
      <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($media as $m): ?>
          <?php if ($m['MediaType'] === 'image'): ?>
            <img src="<?= htmlspecialchars($m['MediaPath']) ?>" alt="Recipe Image" class="rounded shadow max-h-60 object-cover w-full" />
          <?php elseif ($m['MediaType'] === 'video'): ?>
            <video controls class="rounded shadow max-h-60 w-full">
              <source src="<?= htmlspecialchars($m['MediaPath']) ?>" type="video/mp4" />
              Your browser does not support the video tag.
            </video>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Steps -->
    <h2 class="text-2xl font-semibold mb-3">Steps</h2>
    <?php if (count($steps) > 0): ?>
      <ol class="list-decimal list-inside space-y-2 mb-6">
        <?php foreach ($steps as $step): ?>
          <li><?= nl2br(htmlspecialchars($step['Instruction'])) ?></li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p>No steps added yet.</p>
    <?php endif; ?>

    <!-- Back button -->
    <a href="student_dashboard.php" class="inline-block mt-4 bg-yellow-400 hover:bg-yellow-500 text-white py-2 px-4 rounded shadow">Back to Dashboard</a>
  </div>

  <!-- Comment Section -->
<?php
// Reopen DB for comment section
require 'connection.php';

$user_id = $_SESSION['userid'] ?? null;
$fullname = $_SESSION['fullname'] ?? '';

// Handle POST comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $user_id) {
    $comment = trim($_POST['comment']);
    if ($comment !== '') {
        $stmt = $conn->prepare("INSERT INTO comment (RecipeID, UserID, Text, CommentDate) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $recipe_id, $user_id, $comment);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI'] . "#comments");
        exit();
    }
}

// Fetch existing comments
$stmt = $conn->prepare("
    SELECT c.Text, c.CommentDate, u.FullName
    FROM comment c
    JOIN user u ON c.UserID = u.UserID
    WHERE c.RecipeID = ?
    ORDER BY c.CommentDate DESC
");
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$res = $stmt->get_result();
$comments = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<div id="comments" class="mt-10 border-t pt-6">
  <h2 class="text-2xl font-semibold mb-4">Comments</h2>

  <?php if ($user_id): ?>
    <form method="POST" class="mb-6">
      <textarea name="comment" rows="3" required
        class="w-full p-3 border rounded" placeholder="Share your thoughts..."></textarea>
      <button type="submit"
        class="mt-2 bg-yellow-400 hover:bg-yellow-500 text-white py-2 px-4 rounded">
        Post Comment
      </button>
    </form>
  <?php else: ?>
    <p class="text-gray-600 mb-4">
      <a href="index.php" class="text-yellow-600 underline">Login</a> to leave a comment.
    </p>
  <?php endif; ?>

  <?php if (empty($comments)): ?>
    <p class="text-gray-600">No comments yet. Be the first!</p>
  <?php else: ?>
    <ul class="space-y-4">
      <?php foreach ($comments as $c): ?>
        <li class="border-b pb-3">
          <p class="text-gray-800"><?= htmlspecialchars($c['Text']) ?></p>
          <p class="text-sm text-gray-500">— <?= htmlspecialchars($c['FullName']) ?> on <?= date("d M Y, H:i", strtotime($c['CommentDate'])) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>


</body>
</html>
