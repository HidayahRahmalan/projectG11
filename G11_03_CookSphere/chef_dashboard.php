<?php
include 'header.php';
require 'connection.php';

$user_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '';

// Fetch all recipes (any user)
$sql = "SELECT r.RecipeID, r.Title, r.Description, r.CuisineType, r.DietaryType, r.UploadDate, u.FullName
        FROM recipe r
        JOIN user u ON r.UserID = u.UserID
        ORDER BY r.UploadDate DESC";
$result = $conn->query($sql);

// Fetch the first image for each recipe (if available)
$recipe_images = [];
$recipes = [];

// Get filters from GET parameters
$search_title = isset($_GET['search_title']) ? trim($_GET['search_title']) : '';
$search_ingredient = isset($_GET['search_ingredient']) ? trim($_GET['search_ingredient']) : '';
/*$cuisine_type = isset($_GET['cuisine_type']) ? trim($_GET['cuisine_type']) : '';
$dietary_type = isset($_GET['dietary_type']) ? trim($_GET['dietary_type']) : '';
$cuisine_type = isset($_GET['custom_cuisine']) ? trim($_GET['custom_cuisine']) : '';
$dietary_type = isset($_GET['custom_dietary']) ? trim($_GET['custom_dietary']) : '';*/

$cuisine_type = isset($_GET['cuisine_type']) ? trim($_GET['cuisine_type']) : '';
$dietary_type = isset($_GET['dietary_type']) ? trim($_GET['dietary_type']) : '';
$custom_cuisine = isset($_GET['custom_cuisine']) ? trim($_GET['custom_cuisine']) : '';
$custom_dietary = isset($_GET['custom_dietary']) ? trim($_GET['custom_dietary']) : '';



// Build SQL with filters
$conditions = [];
$params = [];

if ($search_title !== '') {
    $conditions[] = "r.Title LIKE ?";
    $params[] = '%' . $search_title . '%';
}
if ($search_ingredient !== '') {
    $ingredients = array_filter(array_map('trim', explode(',', $search_ingredient)));
    foreach ($ingredients as $ingredient) {
        $conditions[] = "r.Description LIKE ?";
        $params[] = '%' . $ingredient . '%';
    }
}

/*if ($cuisine_type !== '') {
    $conditions[] = "r.CuisineType = ?";
    $params[] = $cuisine_type;
}
if ($dietary_type !== '') {
    $conditions[] = "r.DietaryType = ?";
    $params[] = $dietary_type;
}*/
if ($cuisine_type !== '') {
    if ($cuisine_type === 'Others' && $custom_cuisine !== '') {
        $conditions[] = "r.CuisineType LIKE ?";
        $params[] = '%' . $custom_cuisine . '%';
    } elseif ($cuisine_type !== 'Others') {
        $conditions[] = "r.CuisineType = ?";
        $params[] = $cuisine_type;
    }
}

if ($dietary_type !== '') {
    if ($dietary_type === 'Others' && $custom_dietary !== '') {
        $conditions[] = "r.DietaryType LIKE ?";
        $params[] = '%' . $custom_dietary . '%';
    } elseif ($dietary_type !== 'Others') {
        $conditions[] = "r.DietaryType = ?";
        $params[] = $dietary_type;
    }
}

$sql = "SELECT r.RecipeID, r.Title, r.CuisineType, r.DietaryType, r.UploadDate, u.FullName
        FROM recipe r
        JOIN user u ON r.UserID = u.UserID";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY r.UploadDate DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $recipe_ids = [];
    while ($row = $result->fetch_assoc()) {
        $recipe_ids[] = $row['RecipeID'];
        $recipes[] = $row;
    }
    if (!empty($recipe_ids)) {
        $in = implode(',', array_map('intval', $recipe_ids));
        $img_sql = "SELECT RecipeID, MediaPath FROM media WHERE MediaType='image' AND RecipeID IN ($in) GROUP BY RecipeID";
        $img_result = $conn->query($img_sql);
        if ($img_result && $img_result->num_rows > 0) {
            while ($img = $img_result->fetch_assoc()) {
                $recipe_images[$img['RecipeID']] = $img['MediaPath'];
            }
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chef Dashboard - CookSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: url('background.jpeg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
        }
        .glass {
            background: rgba(255, 255, 255, 0.97);
            border-radius: 2rem;
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-10 flex flex-col items-center">

    <!-- Welcome Section -->
    <div class="glass w-full max-w-3xl p-10 mb-10 flex flex-col items-start rounded-[2.5rem]">
        <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-1">Welcome, <span class="text-yellow-600"><?= htmlspecialchars($user_name) ?>!</span></h1>
        <p class="text-lg text-gray-600 mb-8">
            Here you can manage your recipes, add new ones, and view feedback from the community.
        </p>
        <div class="flex flex-col md:flex-row w-full gap-6">
            <a href="upload_recipe.php" class="flex-1 text-center bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-4 rounded-2xl shadow-md text-xl transition-all mb-2 md:mb-0 md:mr-2" style="box-shadow: 0 4px 0 #eab308;">
                Upload New Recipe
            </a>
            <a href="my_recipes.php" class="flex-1 text-center border-2 border-yellow-400 text-yellow-700 font-bold py-4 rounded-2xl shadow-md text-xl transition-all hover:bg-yellow-100 bg-white" style="box-shadow: 0 4px 0 #fde68a;">
                View My Recipes
            </a>
        </div>
    </div>

    <!-- All Uploaded Recipes -->
    <div class="glass w-full max-w-5xl p-10">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">All Uploaded Recipes</h2>
        <!-- Filter Section -->
        <form method="GET" class="mb-8 w-full max-w-5xl">
            <div class="flex flex-col md:flex-row gap-4 items-center">
                <input type="text" name="search_title" placeholder="Search Recipe..." value="<?= htmlspecialchars($_GET['search_title'] ?? '') ?>" class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400">

                <input type="text" name="search_ingredient" placeholder="Search by ingredients" 
                value="<?= htmlspecialchars($_GET['search_ingredient'] ?? '') ?>"
                class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400">

            </div>
            <div class="mt-6 flex justify-center gap-4">
                <select name="cuisine_type" class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    <option value="">All Cuisines</option>
                    <option value="Malay" <?= ($_GET['cuisine_type'] ?? '') == 'Malay' ? 'selected' : '' ?>>Malay</option>
                    <option value="Italian" <?= ($_GET['cuisine_type'] ?? '') == 'Italian' ? 'selected' : '' ?>>Italian</option>
                    <option value="Chinese" <?= ($_GET['cuisine_type'] ?? '') == 'Chinese' ? 'selected' : '' ?>>Chinese</option>
                    <option value="Japanese" <?= ($_GET['cuisine_type'] ?? '') == 'Japanese' ? 'selected' : '' ?>>Japanese</option>
                    <option value="Thai" <?= ($_GET['cuisine_type'] ?? '') == 'Thai' ? 'selected' : '' ?>>Thai</option>
                    <option value="Indian" <?= ($_GET['cuisine_type'] ?? '') == 'Indian' ? 'selected' : '' ?>>Indian</option>
                    <option value="Mexican" <?= ($_GET['cuisine_type'] ?? '') == 'Mexican' ? 'selected' : '' ?>>Mexican</option>
                    <option value="French" <?= ($_GET['cuisine_type'] ?? '') == 'French' ? 'selected' : '' ?>>French</option>
                    <option value="Spanish" <?= ($_GET['cuisine_type'] ?? '') == 'Spanish' ? 'selected' : '' ?>>Spanish</option>
                    <!-- Add more options -->
                    <option value="Others" <?= ($_GET['cuisine_type'] ?? '') == 'Others' ? 'selected' : '' ?>>Others</option>
                    <input type="text" name="custom_cuisine" id="custom_cuisine_input" placeholder="Please input other cuisine type"
                    class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-yellow-400 hidden" />
                </select>

                <select name="dietary_type" class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-yellow-400">
                    <option value="">All Diets</option>
                    <option value="Vegan" <?= ($_GET['dietary_type'] ?? '') == 'Vegan' ? 'selected' : '' ?>>Vegan</option>
                    <option value="Non-Vegan" <?= ($_GET['dietary_type'] ?? '') == 'Non-Vegan' ? 'selected' : '' ?>>Non-Vegan</option>
                    <option value="Vegetarian" <?= ($_GET['dietary_type'] ?? '') == 'Vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                    <option value="Keto" <?= ($_GET['dietary_type'] ?? '') == 'Keto' ? 'selected' : '' ?>>Keto</option>
                    <option value="Paleo" <?= ($_GET['dietary_type'] ?? '') == 'Paleo' ? 'selected' : '' ?>>Paleo</option>
                    <option value="Halal" <?= ($_GET['dietary_type'] ?? '') == 'Halal' ? 'selected' : '' ?>>Halal</option>
                    <option value="Low Carb" <?= ($_GET['dietary_type'] ?? '') == 'Low Carb' ? 'selected' : '' ?>>Low Carb</option>
                    <option value="Others" <?= ($_GET['dietary_type'] ?? '') == 'Others' ? 'selected' : '' ?>>Others</option>
                    <input type="text" name="custom_dietary" id="custom_dietary_input" placeholder="Please input other dietary type"
                    class="flex-1 px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-yellow-400 hidden" />
                </select>
                <button type="submit" class="bg-white border-2 border-yellow-400 text-yellow-700 font-bold px-6 py-2 rounded-xl shadow-md hover:border-yellow-500 transition-all text-center">
                    Search
                </button>
                <a href="<?= strtok($_SERVER["REQUEST_URI"], '?') ?>" class="bg-white border-2 border-yellow-400 text-yellow-700 font-bold px-6 py-2 rounded-xl shadow-md hover:border-yellow-500 transition-all text-center">
                    Reset
                </a>
            </div>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (!empty($recipes)): ?>
            <?php foreach ($recipes as $row): ?>
                <a href="recipe_detail.php?recipe_id=<?= $row['RecipeID'] ?>" class="block bg-white border hover:shadow-lg transition p-6 rounded-2xl">
                    <!-- Recipe image/placeholder -->
                    <div class="w-full h-40 bg-gray-100 flex items-center justify-center rounded-xl mb-4 overflow-hidden">
                        <?php if (isset($recipe_images[$row['RecipeID']])): ?>
                            <img src="<?= htmlspecialchars($recipe_images[$row['RecipeID']]) ?>" alt="Recipe Image" class="object-cover h-full w-full">
                        <?php else: ?>
                            <img src="https://placehold.co/220x160?text=Food+Image" alt="Recipe Image" class="object-cover h-full w-full">
                        <?php endif; ?>
                    </div>
                    <h2 class="text-xl font-semibold mb-1 text-gray-700"><?= htmlspecialchars($row['Title']) ?></h2>
                    <p class="text-gray-500 text-sm mb-2">
                        by <span class="font-bold"><?= htmlspecialchars($row['FullName']) ?></span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-xl text-xs"><?= htmlspecialchars($row['CuisineType']) ?></span>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-xl text-xs"><?= htmlspecialchars($row['DietaryType']) ?></span>
                    </div>
                    <span class="text-xs text-gray-400">Uploaded: <?= date("d M Y", strtotime($row['UploadDate'])) ?></span>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-3 text-center text-gray-500">No recipes uploaded yet.</div>
        <?php endif; ?>
        </div>
    </div>
</div>
<script>
    const cuisineSelect = document.querySelector('select[name="cuisine_type"]');
    const customCuisineInput = document.getElementById('custom_cuisine_input');

    function toggleCustomCuisine() {
        if (cuisineSelect.value === 'Others') {
            customCuisineInput.classList.remove('hidden');
        } else {
            customCuisineInput.classList.add('hidden');
            customCuisineInput.value = '';
        }
    }

    // Trigger on load (for editing or prefilled forms)
    toggleCustomCuisine();

    // Add event listener
    cuisineSelect.addEventListener('change', toggleCustomCuisine);

    const dietarySelect = document.querySelector('select[name="dietary_type"]');
    const customDietaryInput = document.getElementById('custom_dietary_input');

    function toggleDietaryCuisine() {
        if (dietarySelect.value === 'Others') {
            customDietaryInput.classList.remove('hidden');
        } else {
            customDietaryInput.classList.add('hidden');
            customDietaryInput.value = '';
        }
    }

    // Trigger on load (for editing or prefilled forms)
    toggleDietaryCuisine();

    // Add event listener
    dietarySelect.addEventListener('change', toggleDietaryCuisine);
</script>

</body>
</html>
