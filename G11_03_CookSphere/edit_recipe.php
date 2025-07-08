<?php
include 'header.php';
// Only allow logged-in chefs!
if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'chef') {
    header("Location: index.php");
    exit();
}

require 'connection.php'; // Your DB connection file

// Get recipe ID
if (!isset($_GET['recipe_id'])) {
    echo "No recipe selected.";
    exit();
}
$recipe_id = $_GET['recipe_id'];

// Fetch recipe data
$query = "SELECT * FROM recipe WHERE RecipeID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();
$recipe = $result->fetch_assoc();

if (!$recipe) {
    echo "Recipe not found.";
    exit();
}

$steps = [];
$mode = 'simple';

$step_query = "SELECT * FROM step WHERE RecipeID = ? ORDER BY StepNumber ASC";
$step_stmt = $conn->prepare($step_query);
$step_stmt->bind_param("s", $recipe_id);
$step_stmt->execute();
$step_result = $step_stmt->get_result();

while ($row = $step_result->fetch_assoc()) {
    // Fetch media for this step
    $media_query = "SELECT * FROM media WHERE StepID = ?";
    $media_stmt = $conn->prepare($media_query);
    $media_stmt->bind_param("i", $row['StepID']);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();

    $media_files = [];
    while ($media = $media_result->fetch_assoc()) {
        if (isset($media['MediaPath'])) {
            $media_files[] = $media['MediaPath'];
        }
    }

    // Attach media to step array
    $row['MediaFiles'] = $media_files;
    $steps[] = $row;
}

if (count($steps) > 0) {
    $mode = 'steps';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Recipe - CookSphere</title>
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
        .media-thumb { max-width: 220px; max-height: 170px; }
        .media-block video { max-width: 100%; max-height: 200px; border-radius: 1rem; }
        .media-block img { max-width: 100%; max-height: 170px; border-radius: 1rem; }
    </style>
</head>
<body>
<div class="container mx-auto px-4 py-10 flex flex-col items-center">
    <div class="glass w-full max-w-3xl p-10 mt-8">
        <div class="flex justify-between items-center mb-6">
            <a href="javascript:history.back()" class="text-yellow-700 hover:underline text-base flex items-center gap-1">&larr; Back</a>
        </div>
        <h1 class="text-2xl font-bold mb-4 text-gray-800">Edit Recipe</h1>

        <form action="process_edit_recipe.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="recipe_id" value="<?= htmlspecialchars($recipe['RecipeID']) ?>">
            <input type="hidden" name="mode" value="<?= $mode ?>">

            <div>
                <label class="block font-medium text-gray-700 mb-1">Recipe Title</label>
                <input type="text" name="title" required class="w-full px-4 py-2 border rounded-xl" value="<?= htmlspecialchars($recipe['Title']) ?>">
            </div>

            <div>
                <label class="block font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" required class="w-full px-4 py-2 border rounded-xl"><?= htmlspecialchars($recipe['Description']) ?></textarea>
            </div>

            <div class="flex gap-4">
                <div class="flex-1">
                    <label for="cuisineSelect" class="block font-medium text-gray-700 mb-1">Cuisine Type</label>
                    <select name="cuisine" id="cuisineSelect" required class="w-full px-4 py-2 border rounded-xl">
                        <?php
                        $cuisines = ['Malay','Italian','Chinese','Japanese','Thai','Indian','Mexican','French','Spanish','Other'];
                        foreach ($cuisines as $cuisine) {
                            $selected = $recipe['CuisineType'] === $cuisine ? 'selected' : '';
                            echo "<option value='$cuisine' $selected>$cuisine</option>";
                        }
                        ?>
                    </select>
                    <input type="text" name="customCuisine" id="customCuisineInput" class="w-full px-4 py-2 border rounded-xl mt-2" placeholder="Enter your cuisine type" style="display: none;">
                </div>

                <div class="flex-1">
                    <label for="dietarySelect" class="block font-medium text-gray-700 mb-1">Dietary Type</label>
                    <select name="dietary" id="dietarySelect" required class="w-full px-4 py-2 border rounded-xl" onchange="toggleDietaryInput()">
                        <?php
                        $dietaryOptions = ['Vegan','Non-Vegan','Vegetarian','Keto','Paleo','Halal','Low Carb','Other'];
                        foreach ($dietaryOptions as $diet) {
                            $selected = $recipe['DietaryType'] === $diet ? 'selected' : '';
                            echo "<option value='$diet' $selected>$diet</option>";
                        }
                        ?>
                    </select>
                    <input type="text" name="customDietary" id="customDietaryInput" class="w-full px-4 py-2 border rounded-xl mt-2" placeholder="Enter your dietary type" style="display: none;">
                </div>
            </div>

            <div>
                <label class="block font-medium text-gray-700 mb-1">Upload New Images/Videos (Optional)</label>
                <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="block">
                <div class="text-xs text-gray-500 mt-1">Leave empty if you don't want to update media.</div>
            </div>

            <?php if ($mode === 'steps'): ?>
                <div id="stepsContainer">
                    <label class="block font-medium text-gray-700 mb-2">Steps</label>
                    <?php foreach ($steps as $index => $step): ?>
                        <div class="step-block mb-4 border rounded-xl p-4 bg-gray-50">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-semibold">Step <?= $index + 1 ?></span>
                                <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700 text-sm font-semibold">Remove</button>
                            </div>

                            <label class="block text-gray-700 mb-1">Instruction</label>
                            <textarea name="steps[<?= $step['StepID'] ?>][instruction]" required class="w-full px-4 py-2 border rounded-xl mb-2"><?= htmlspecialchars($step['Instruction']) ?></textarea>

                           <?php if (!empty($step['MediaFiles'])): ?>
                                <div class="mb-3">
                                    <label class="block text-gray-700 font-medium mb-1">Current Media</label>
                                    <div class="flex flex-col gap-4">
                                        <?php foreach ($step['MediaFiles'] as $index => $file_path): ?>
                                            <?php $file_path_escaped = htmlspecialchars($file_path); ?>
                                            <div class="flex items-center gap-3">
                                                <?php if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $file_path)): ?>
                                                    <img src="<?= $file_path_escaped ?>" alt="Step Image"
                                                        class="rounded-md border border-gray-300 w-16 h-16 object-cover shadow">
                                                <?php elseif (preg_match('/\.(mp4|webm|ogg)$/i', $file_path)): ?>
                                                    <video controls
                                                        class="rounded-md border border-gray-300 w-16 h-16 object-cover shadow">
                                                        <source src="<?= $file_path_escaped ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php endif; ?>
                                                
                                                <label class="text-sm text-red-600 flex items-center gap-1">
                                                    <input type="checkbox" name="remove_media[<?= $step['StepID'] ?>][]" value="<?= $file_path ?>">
                                                    Remove
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <label class="block text-gray-700 mb-1">Upload New Media (optional)</label>
                            <input type="file" name="steps[<?= $step['StepID'] ?>][media][]" accept="image/*,video/*" multiple class="block mb-1">
                            <div class="text-xs text-gray-400">Upload to replace or leave empty to keep current media.</div>
                        </div>
                    <?php endforeach; ?>

                </div>
                <button type="button" onclick="addStep()" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 font-semibold px-4 py-2 rounded-xl mt-2 mb-4">+ Add Step</button>
            <?php endif; ?>


            <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-3 rounded-xl">Save Changes</button>
        </form>
    </div>
</div>

<script>
function toggleDietaryInput() {
    var selectBox = document.getElementById("dietarySelect");
    var inputBox = document.getElementById("customDietaryInput");

    if (selectBox.value === "Other") {
        inputBox.style.display = "block";
        inputBox.required = true;
    } else {
        inputBox.style.display = "none";
        inputBox.required = false;
    }
}

document.getElementById("cuisineSelect").addEventListener("change", function() {
    var inputBox = document.getElementById("customCuisineInput");
    if (this.value === "Other") {
        inputBox.style.display = "block";
        inputBox.required = true;
    } else {
        inputBox.style.display = "none";
        inputBox.required = false;
    }
});

let stepCount = <?= count($steps) ?>;
function addStep() {
    stepCount++;
    const stepsContainer = document.getElementById('stepsContainer');
    const stepBlock = document.createElement('div');
    stepBlock.className = 'step-block mb-4 border rounded-xl p-4 bg-gray-50';
    stepBlock.innerHTML = `
        <div class="flex justify-between items-center mb-2">
            <span class="font-semibold">Step ${stepCount}</span>
            <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700 text-sm font-semibold">Remove</button>
        </div>
        <label class="block text-gray-700 mb-1">Instruction</label>
        <textarea name="steps[new_${stepCount}][instruction]" required class="w-full px-4 py-2 border rounded-xl mb-2"></textarea>
        <label class="block text-gray-700 mb-1">Image/Video</label>
        <input type="file" name="steps[new_${stepCount}][media][]" accept="image/*,video/*" multiple class="block mb-1">
        <div class="text-xs text-gray-400">You can upload multiple files for this step.</div>
    `;
    stepsContainer.appendChild(stepBlock);
}
function removeStep(btn) {
    btn.closest('.step-block').remove();
}
</script>
</body>
</html>
