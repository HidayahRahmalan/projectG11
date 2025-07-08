<?php
session_start();
require 'connection.php';

// Make sure user is logged in and is a student
if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['userid'];
$fullname = $_SESSION['fullname'];

// Fetch recipes with user info
$sql = "SELECT r.RecipeID, r.Title, r.CuisineType, r.DietaryType, r.UploadDate, u.FullName
        FROM recipe r
        JOIN user u ON r.UserID = u.UserID
        ORDER BY r.UploadDate DESC
        LIMIT 50";  // Increased limit for better search/filter experience
$result = $conn->query($sql);

$recipes = [];
$recipe_ids = [];
$cuisines = [];
$dietaries = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
        $recipe_ids[] = $row['RecipeID'];
        if (!in_array($row['CuisineType'], $cuisines)) {
            $cuisines[] = $row['CuisineType'];
        }
        if (!in_array($row['DietaryType'], $dietaries)) {
            $dietaries[] = $row['DietaryType'];
        }
    }
}

// Fetch images
$recipe_images = [];
if (!empty($recipe_ids)) {
    $ids_in = implode(',', array_map('intval', $recipe_ids));
    $img_sql = "SELECT RecipeID, MediaPath FROM media WHERE MediaType='image' AND RecipeID IN ($ids_in) GROUP BY RecipeID";
    $img_result = $conn->query($img_sql);
    if ($img_result) {
        while ($img = $img_result->fetch_assoc()) {
            $recipe_images[$img['RecipeID']] = $img['MediaPath'];
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <title>Student Dashboard - CookSphere</title>
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
<body class="bg-yellow-50 min-h-screen flex flex-col font-sans text-gray-800">

  <!-- Header -->
  <header class="flex justify-between items-center p-6 bg-yellow-400 shadow-md">
    <h1 class="text-3xl font-extrabold glow-yellow select-none text-yellow-900 tracking-wide">CookSphere</h1>
    <div class="text-yellow-900 font-semibold">Welcome, <span class="underline"><?= htmlspecialchars($fullname) ?></span></div>
  </header>

  <!-- Navigation -->
  <nav class="bg-yellow-100 shadow-md flex justify-center space-x-10 py-4 sticky top-0 z-30">
    <a href="student_dashboard.php" class="text-yellow-700 font-semibold hover:text-yellow-900 transition">Home</a>
    <a href="student_upload_recipe.php" class="hover:text-yellow-900 transition">Upload Recipe</a>
    <a href="student_my_recipes.php" class="hover:text-yellow-900 transition">My Recipes</a>
    <a href="student_my_reviews.php" class="hover:text-yellow-900 transition">My Reviews</a>
    <a href="logout.php" class="hover:text-yellow-900 transition">Logout</a>
  </nav>

  <!-- Main Content -->
  <main class="flex-grow p-8 max-w-7xl mx-auto">
    <h2 class="text-4xl font-bold mb-8 glow-yellow">Latest Recipes</h2>

    <!-- Search and Filter -->
    <div class="mb-8 flex flex-col sm:flex-row gap-4 items-center">
      <input type="text" id="searchInput" placeholder="Search recipes by title..." 
             class="flex-grow p-3 rounded-lg border border-yellow-300 focus:outline-yellow-400 focus:ring-2 focus:ring-yellow-400 transition" />

      <select id="cuisineFilter" class="p-3 rounded-lg border border-yellow-300 focus:outline-yellow-400 focus:ring-2 focus:ring-yellow-400 transition">
        <option value="">All Cuisines</option>
        <?php foreach ($cuisines as $cuisine): ?>
          <option value="<?= htmlspecialchars($cuisine) ?>"><?= htmlspecialchars($cuisine) ?></option>
        <?php endforeach; ?>
      </select>

      <select id="dietaryFilter" class="p-3 rounded-lg border border-yellow-300 focus:outline-yellow-400 focus:ring-2 focus:ring-yellow-400 transition">
        <option value="">All Dietary Types</option>
        <?php foreach ($dietaries as $diet): ?>
          <option value="<?= htmlspecialchars($diet) ?>"><?= htmlspecialchars($diet) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (empty($recipes)): ?>
      <p class="text-center text-yellow-700 text-lg">No recipes found.</p>
    <?php else: ?>
      <div id="recipesGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
        <?php foreach ($recipes as $recipe): ?>
          <a href="student_recipe_detail.php?recipe_id=<?= $recipe['RecipeID'] ?>" 
             class="recipe-card card-hover bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-transform duration-300 ease-in-out"
             data-title="<?= strtolower(htmlspecialchars($recipe['Title'])) ?>"
             data-cuisine="<?= strtolower(htmlspecialchars($recipe['CuisineType'])) ?>"
             data-dietary="<?= strtolower(htmlspecialchars($recipe['DietaryType'])) ?>">
            <div class="h-48 overflow-hidden">
              <?php if (isset($recipe_images[$recipe['RecipeID']])): ?>
                <img src="<?= htmlspecialchars($recipe_images[$recipe['RecipeID']]) ?>" alt="Recipe Image" class="w-full h-full object-cover brightness-95 hover:brightness-110 transition duration-300" />
              <?php else: ?>
                <img src="https://placehold.co/400x300?text=No+Image" alt="No image" class="w-full h-full object-cover brightness-95" />
              <?php endif; ?>
            </div>
            <div class="p-5 space-y-1">
              <h3 class="text-yellow-700 text-xl font-semibold tracking-wide"><?= htmlspecialchars($recipe['Title']) ?></h3>
              <p class="text-yellow-600 text-sm italic">By <?= htmlspecialchars($recipe['FullName']) ?></p>
              <p class="text-yellow-500 text-xs">Uploaded on <?= date("d M Y", strtotime($recipe['UploadDate'])) ?></p>
              <div class="mt-3 flex flex-wrap gap-3 text-xs font-medium">
                <span class="bg-yellow-200 text-yellow-700 px-3 py-1 rounded-full tracking-wide"><?= htmlspecialchars($recipe['CuisineType']) ?></span>
                <span class="bg-green-200 text-green-700 px-3 py-1 rounded-full tracking-wide"><?= htmlspecialchars($recipe['DietaryType']) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Footer -->
  <footer class="bg-yellow-100 text-center text-yellow-600 p-6 text-sm select-none">
    &copy; <?= date('Y') ?> CookSphere. All rights reserved.
  </footer>

<script>
  const searchInput = document.getElementById('searchInput');
  const cuisineFilter = document.getElementById('cuisineFilter');
  const dietaryFilter = document.getElementById('dietaryFilter');
  const recipeCards = document.querySelectorAll('.recipe-card');

  function filterRecipes() {
    const searchText = searchInput.value.toLowerCase();
    const selectedCuisine = cuisineFilter.value.toLowerCase();
    const selectedDietary = dietaryFilter.value.toLowerCase();

    recipeCards.forEach(card => {
      const title = card.getAttribute('data-title');
      const cuisine = card.getAttribute('data-cuisine');
      const dietary = card.getAttribute('data-dietary');

      const matchesSearch = title.includes(searchText);
      const matchesCuisine = selectedCuisine === '' || cuisine === selectedCuisine;
      const matchesDietary = selectedDietary === '' || dietary === selectedDietary;

      if (matchesSearch && matchesCuisine && matchesDietary) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }

  searchInput.addEventListener('input', filterRecipes);
  cuisineFilter.addEventListener('change', filterRecipes);
  dietaryFilter.addEventListener('change', filterRecipes);
</script>

</body>
</html>
