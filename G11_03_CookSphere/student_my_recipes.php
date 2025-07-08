<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['userid'];
$fullname = $_SESSION['fullname'];

// Get recipes by this student
$sql = "SELECT RecipeID, Title, UploadDate FROM recipe WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();

$recipes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }

    $recipe_ids = array_column($recipes, 'RecipeID');
    $in = implode(',', array_map('intval', $recipe_ids));

    // Fetch images
    $images = [];
    if (!empty($in)) {
        $img_result = $conn->query("SELECT RecipeID, MediaPath FROM media WHERE MediaType='image' AND RecipeID IN ($in) GROUP BY RecipeID");
        while ($img = $img_result->fetch_assoc()) {
            $images[$img['RecipeID']] = $img['MediaPath'];
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <title>My Recipes - Student | CookSphere</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .glow-yellow {
      text-shadow:
        0 0 6px #fcd34d,
        0 0 12px #fbbf24;
    }
    .card-hover:hover {
      transform: translateY(-6px);
      box-shadow:
        0 12px 20px rgba(251, 191, 36, 0.3),
        0 4px 6px rgba(251, 191, 36, 0.15);
    }
  </style>
</head>
<body class="bg-yellow-50 min-h-screen font-sans text-gray-800 flex flex-col items-center p-6">

  <div class="max-w-6xl w-full bg-white p-8 rounded-2xl shadow-lg">

    <!-- Back to Dashboard button -->
    <div class="mb-8">
      <a href="student_dashboard.php" class="inline-block bg-yellow-400 hover:bg-yellow-500 text-white font-semibold py-3 px-6 rounded-xl shadow transition">
        &larr; Back to Dashboard
      </a>
    </div>

    <h1 class="text-3xl font-extrabold text-yellow-700 glow-yellow mb-8">Hello <?= htmlspecialchars($fullname) ?>, these are your recipes:</h1>

    <?php if (isset($_GET['msg'])): ?>
      <div class="mb-6 p-4 bg-green-100 text-green-700 rounded-lg border border-green-300">
        <?= htmlspecialchars($_GET['msg']) ?>
      </div>
    <?php endif; ?>

    <?php if (count($recipes) === 0): ?>
      <p class="text-yellow-800 text-center text-lg py-20 select-none">You haven’t uploaded any recipes yet.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($recipes as $r): ?>
          <div class="bg-white border border-yellow-200 rounded-2xl p-5 shadow-md card-hover transition-transform duration-300 ease-in-out">
            <a href="recipe_detail.php?recipe_id=<?= $r['RecipeID'] ?>" class="block mb-4 rounded-lg overflow-hidden h-44 bg-yellow-50 flex items-center justify-center">
              <?php if (isset($images[$r['RecipeID']])): ?>
                <img src="<?= htmlspecialchars($images[$r['RecipeID']]) ?>" alt="Recipe Image" class="object-cover w-full h-full brightness-95 hover:brightness-110 transition" />
              <?php else: ?>
                <img src="https://placehold.co/300x200?text=No+Image" alt="No Image" class="object-cover w-full h-full brightness-75" />
              <?php endif; ?>
            </a>
            <h2 class="text-xl font-semibold text-yellow-700 mb-1"><?= htmlspecialchars($r['Title']) ?></h2>
            <p class="text-sm text-gray-500 mb-4">Uploaded: <?= date("d M Y", strtotime($r['UploadDate'])) ?></p>

            <!-- Action Buttons -->
            <div class="flex gap-3">
              <a href="student_edit_recipe.php?recipe_id=<?= $r['RecipeID'] ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-center font-semibold transition">
                Edit
              </a>
              <a href="student_delete_recipe.php?id=<?= $r['RecipeID'] ?>" 
                 onclick="return confirm('Are you sure you want to delete this recipe?');" 
                 class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-center font-semibold transition">
                Delete
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</body>
</html>
