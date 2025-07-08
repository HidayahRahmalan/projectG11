<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$user_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'Guest';
$user_role = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : '';
?>
<!-- Header Component -->
<div class="w-full flex items-center justify-between px-8 py-4 bg-white bg-opacity-80 backdrop-blur shadow-md fixed top-0 left-0 z-50" style="border-radius:0 0 2rem 2rem;">
    <div class="flex items-center gap-4">
        <img src="logo.png" alt="logo" class="w-14 h-auto rounded-lg shadow">
        <span class="text-2xl font-bold text-gray-700 tracking-wide">CookSphere</span>
    </div>
    <nav class="flex items-center gap-6">
        <a href="chef_dashboard.php" class="text-gray-700 font-semibold hover:text-yellow-500 transition">Home</a>
        <a href="my_recipes.php" class="text-gray-700 font-semibold hover:text-yellow-500 transition">My Recipes</a>
        <a href="upload_recipe.php" class="text-gray-700 font-semibold hover:text-yellow-500 transition">Upload Recipe</a>
        <a href="logout.php" class="text-gray-700 font-semibold hover:text-red-500 transition">Logout</a>
        <span class="ml-6 px-4 py-2 bg-yellow-100 text-yellow-700 rounded-xl text-sm font-medium shadow">
            <?= htmlspecialchars($user_name) ?><?= $user_role ? " ($user_role)" : "" ?>
        </span>
    </nav>
</div>
<!-- Padding for fixed header -->
<div class="h-24"></div>
