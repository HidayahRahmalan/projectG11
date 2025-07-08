<?php
include 'header.php';
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'chef') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['userid'];

// Fetch recipes by this chef
$sql = "SELECT RecipeID, Title, CuisineType, DietaryType, UploadDate 
        FROM recipe 
        WHERE UserID = ? 
        ORDER BY UploadDate DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Recipes - CookSphere</title>
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
            background: rgba(255,255,255,0.97);
            border-radius: 2rem;
            box-shadow: 0 20px 30px rgba(0,0,0,0.08);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 py-10 flex flex-col items-center">
        <div class="glass w-full max-w-4xl p-10 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">My Uploaded Recipes</h1>
                <a href="chef_dashboard.php" class="bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-2 px-6 rounded-xl shadow-md transition-all">Go to Home</a>
            </div>
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded-xl text-center font-semibold">
                    Recipe uploaded successfully!
                </div>
            <?php endif; ?>
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="bg-white border hover:shadow-lg transition p-6 rounded-2xl flex flex-col">
                        <h2 class="text-xl font-semibold mb-1 text-gray-700"><?= htmlspecialchars($row['Title']) ?></h2>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-xl text-xs"><?= htmlspecialchars($row['CuisineType']) ?></span>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-xl text-xs"><?= htmlspecialchars($row['DietaryType']) ?></span>
                        </div>
                        <span class="text-xs text-gray-400 mb-3">Uploaded: <?= date("d M Y", strtotime($row['UploadDate'])) ?></span>
                        <div class="flex-grow"></div>
                        <a href="recipe_detail.php?recipe_id=<?= $row['RecipeID'] ?>" class="mt-3 bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-2 rounded-xl text-center transition-all">View</a>
                    </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center text-gray-500 mt-8">You have not uploaded any recipes yet.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
