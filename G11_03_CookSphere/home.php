<?php
include 'header.php';
require 'connection.php';

// Fetch all recipes (with user info)
$sql = "SELECT r.RecipeID, r.Title, r.CuisineType, r.DietaryType, r.UploadDate, u.FullName 
        FROM recipe r 
        JOIN user u ON r.UserID = u.UserID
        ORDER BY r.UploadDate DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CookSphere - Home</title>
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
            background: rgba(255, 255, 255, 0.95);
            border-radius: 2rem;
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-10 flex flex-col items-center">
        <div class="glass w-full max-w-5xl p-10 mt-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">All Recipes</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <a href="recipe_detail.php?recipe_id=<?= $row['RecipeID'] ?>" class="block bg-white border hover:shadow-lg transition p-6 rounded-2xl">
                        <!-- Recipe image/placeholder -->
                        <div class="w-full h-40 bg-gray-100 flex items-center justify-center rounded-xl mb-4 overflow-hidden">
                            <img src="https://placehold.co/220x160?text=Food+Image" alt="Recipe Image" class="object-cover h-full w-full">
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
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-3 text-center text-gray-500">No recipes uploaded yet.</div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
