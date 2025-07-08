<?php
session_start();
require 'connection.php';

$recipe_id = intval($_GET['recipe_id'] ?? 0);
if ($recipe_id === 0) return;

$user_id = $_SESSION['userid'] ?? null;
$username = $_SESSION['fullname'] ?? '';

// Posting a comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $user_id) {
    $text = trim($_POST['comment']);
    if ($text !== '') {
        $stmt = $conn->prepare("INSERT INTO comment (RecipeID, UserID, Text, CommentDate) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $recipe_id, $user_id, $text);
        $stmt->execute();
        $stmt->close();
        header("Location: ".$_SERVER['REQUEST_URI']."#comments");
        exit();
    }
}

// Fetch comments
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
?>

<div id="comments" class="mt-8">
  <h3 class="text-xl font-semibold mb-4">Comments</h3>

  <?php if ($user_id): ?>
    <form method="POST" class="mb-6">
      <textarea name="comment" rows="3" required
        class="w-full p-3 border rounded-md"
        placeholder="Share your thoughts..."></textarea>
      <button type="submit"
        class="mt-2 bg-yellow-400 hover:bg-yellow-500 text-white py-2 px-4 rounded">
        Post Comment
      </button>
    </form>
  <?php else: ?>
    <p class="text-gray-600 mb-6">
      Please <a href="index.php" class="text-yellow-600 underline">login</a> to post comments.
    </p>
  <?php endif; ?>

  <?php if (empty($comments)): ?>
    <p class="text-gray-600">No comments yet. Be the first!</p>
  <?php else: ?>
    <ul>
      <?php foreach ($comments as $c): ?>
        <li class="mb-4 border-b pb-2">
          <p class="text-gray-800"><?= htmlspecialchars($c['Text']) ?></p>
          <p class="text-sm text-gray-500">— <?= htmlspecialchars($c['FullName']) ?> on <?= date("d M Y, H:i", strtotime($c['CommentDate'])) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
