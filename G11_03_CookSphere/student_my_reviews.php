<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['userid'];

// Handle Delete
if (isset($_GET['delete_comment_id'])) {
    $del_id = intval($_GET['delete_comment_id']);
    // Delete only if it belongs to the user
    $stmt = $conn->prepare("DELETE FROM comment WHERE CommentID = ? AND UserID = ?");
    $stmt->bind_param("ii", $del_id, $userid);
    $stmt->execute();
    header("Location: student_my_reviews.php");
    exit();
}

// Handle Update (Edit)
$edit_comment_id = intval($_POST['edit_comment_id'] ?? 0);
if ($edit_comment_id > 0 && isset($_POST['edited_comment'])) {
    $new_text = trim($_POST['edited_comment']);
    if ($new_text !== '') {
        // Update only if comment belongs to user
        $stmt = $conn->prepare("UPDATE comment SET Text = ? WHERE CommentID = ? AND UserID = ?");
        $stmt->bind_param("sii", $new_text, $edit_comment_id, $userid);
        $stmt->execute();
        header("Location: student_my_reviews.php");
        exit();
    }
}

// Fetch all user comments + recipe info
$sql = "
    SELECT c.CommentID, c.Text, c.CommentDate, r.Title AS RecipeTitle, r.RecipeID
    FROM comment c
    JOIN recipe r ON c.RecipeID = r.RecipeID
    WHERE c.UserID = ?
    ORDER BY c.CommentDate DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$reviews = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>My Reviews - CookSphere</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6 min-h-screen flex justify-center items-start">

  <div class="max-w-3xl w-full bg-white p-6 rounded-lg shadow">
    <!-- Back to Dashboard Button -->
    <div class="mb-6">
      <a href="student_dashboard.php" 
         class="inline-block bg-yellow-400 hover:bg-yellow-500 text-white font-semibold py-2 px-5 rounded-xl shadow transition">
        &larr; Back to Dashboard
      </a>
    </div>

    <h1 class="text-3xl font-bold mb-6 text-yellow-600">My Reviews</h1>

    <?php if (empty($reviews)): ?>
      <p class="text-gray-600">No reviews found.</p>
    <?php else: ?>
      <ul>
        <?php foreach ($reviews as $review): ?>
          <li class="border-b border-gray-200 pb-4 mb-4">
            <h2 class="text-lg font-semibold text-gray-800">
              On <a href="student_recipe_detail.php?recipe_id=<?= $review['RecipeID'] ?>" class="text-yellow-600 hover:underline"><?= htmlspecialchars($review['RecipeTitle']) ?></a>
            </h2>
            <p class="text-sm text-gray-500 mb-1">Posted on <?= date("d M Y, H:i", strtotime($review['CommentDate'])) ?></p>

            <?php if (isset($_GET['edit_comment_id']) && intval($_GET['edit_comment_id']) === $review['CommentID']): ?>
              <!-- Edit form -->
              <form method="POST" class="mb-2">
                <textarea name="edited_comment" rows="3" required class="w-full p-2 border rounded mb-2"><?= htmlspecialchars($review['Text']) ?></textarea>
                <input type="hidden" name="edit_comment_id" value="<?= $review['CommentID'] ?>" />
                <div class="flex gap-2">
                  <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-white py-1 px-4 rounded font-semibold transition">Save</button>
                  <a href="student_my_reviews.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 py-1 px-4 rounded transition">Cancel</a>
                </div>
              </form>
            <?php else: ?>
              <p class="text-gray-700 mb-2 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($review['Text'])) ?></p>
              <div class="space-x-4 text-sm font-semibold">
                <a href="student_my_reviews.php?edit_comment_id=<?= $review['CommentID'] ?>" class="text-blue-600 hover:underline">Edit</a>
                <a href="student_my_reviews.php?delete_comment_id=<?= $review['CommentID'] ?>" onclick="return confirm('Are you sure you want to delete this review?')" class="text-red-600 hover:underline">Delete</a>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

</body>
</html>
