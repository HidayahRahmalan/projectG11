<?php
require 'connection.php';
session_start();

$student_id = $_SESSION['userid'] ?? 0;
$recipe_id = (int) ($_GET['recipe_id'] ?? 0);

if (!$recipe_id) die('Recipe ID is missing.');

// Fetch recipe
$stmt = $conn->prepare("SELECT * FROM recipe WHERE RecipeID = ? AND UserID = ?");
$stmt->bind_param("ii", $recipe_id, $student_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
if (!$recipe) die("Recipe not found or access denied.");

// Fetch existing media
$media_query = $conn->query("SELECT * FROM media WHERE RecipeID = $recipe_id");
$media_files = [];
while ($m = $media_query->fetch_assoc()) {
    $media_files[] = $m;
}

// Fetch existing steps
$steps = [];
$stmt = $conn->prepare("SELECT StepID, StepNumber, Instruction FROM step WHERE RecipeID = ? ORDER BY StepNumber ASC");
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $steps[] = $row;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $cuisine = $_POST['cuisine'];
    $diet = $_POST['dietary'];

    if ($cuisine === 'Other') {
        $cuisine = $_POST['customCuisine'] ?? $cuisine;
    }
    if ($diet === 'Other') {
        $diet = $_POST['customDietary'] ?? $diet;
    }


    $stmt = $conn->prepare("UPDATE recipe SET Title=?, Description=?, CuisineType=?, DietaryType=? WHERE RecipeID=? AND UserID=?");
    $stmt->bind_param("ssssii", $title, $desc, $cuisine, $diet, $recipe_id, $student_id);
    $stmt->execute();

    if (isset($_POST['step_id'], $_POST['step_instruction'])) {
        for ($i = 0; $i < count($_POST['step_id']); $i++) {
            $step_id = $_POST['step_id'][$i];
            $instruction = trim($_POST['step_instruction'][$i]);

            if ($instruction === '') continue;

            if ($step_id === 'new') {
                $step_number = $i + 1;
                $stmt = $conn->prepare("INSERT INTO step (RecipeID, StepNumber, Instruction) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $recipe_id, $step_number, $instruction);
                $stmt->execute();
            } else {
                $step_id = (int)$step_id;
                $stmt = $conn->prepare("UPDATE step SET Instruction = ? WHERE StepID = ? AND RecipeID = ?");
                $stmt->bind_param("sii", $instruction, $step_id, $recipe_id);
                $stmt->execute();
            }
        }
    }

    if (!empty($_POST['delete_step_ids'])) {
        $delete_ids = explode(',', $_POST['delete_step_ids']);
        foreach ($delete_ids as $id) {
            $id = (int)$id;
            $stmt = $conn->prepare("DELETE FROM step WHERE StepID = ? AND RecipeID = ?");
            $stmt->bind_param("ii", $id, $recipe_id);
            $stmt->execute();
        }
    }


    // Handle new media upload
    foreach (['image', 'video'] as $type) {
        if (!empty($_FILES[$type]['tmp_name'])) {
            $filename = 'media/' . uniqid() . '_' . basename($_FILES[$type]['name']);
            move_uploaded_file($_FILES[$type]['tmp_name'], $filename);

            $stmt = $conn->prepare("INSERT INTO media (RecipeID, MediaType, MediaPath) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $recipe_id, $type, $filename);
            $stmt->execute();
        }
    }

    header("Location: student_my_recipes.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Recipe</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="max-w-2xl mx-auto mt-10 bg-white p-8 shadow-xl rounded-xl">
    <h2 class="text-2xl font-bold mb-6">Edit Your Recipe</h2>
    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <div>
            <label class="block font-semibold">Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($recipe['Title']) ?>" required class="w-full border px-4 py-2 rounded" />
        </div>
        <div>
            <label class="block font-semibold">Description</label>
            <textarea name="description" required class="w-full border px-4 py-2 rounded"><?= htmlspecialchars($recipe['Description']) ?></textarea>
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

        <!-- Steps -->
        <!-- Steps -->
        <div>
            <label class="block font-semibold mb-2">Edit Steps</label>
            <div id="stepContainer" class="space-y-4">
                <?php foreach ($steps as $index => $step): ?>
                    <div class="step-item bg-gray-50 border p-3 rounded relative">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Step <?= $index + 1 ?></label>
                        <input type="hidden" name="step_id[]" value="<?= $step['StepID'] ?>">
                        <textarea name="step_instruction[]" class="w-full p-2 border rounded" required><?= htmlspecialchars($step['Instruction']) ?></textarea>
                        <button type="button" class="absolute top-2 right-2 text-red-500 text-sm" onclick="removeExistingStep(this, <?= $step['StepID'] ?>)">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="delete_step_ids" id="delete_step_ids" value="">
            <button type="button" class="mt-3 text-sm text-blue-600 hover:underline" onclick="addStep()">+ Add Step</button>
        </div>


        <!-- Existing Media Preview -->
        <div>
            <label class="block font-semibold mb-2">Current Media</label>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach ($media_files as $media): ?>
                    <?php if ($media['MediaType'] === 'image'): ?>
                        <img src="<?= $media['MediaPath'] ?>" class="w-full h-40 object-cover rounded" alt="Image" />
                    <?php elseif ($media['MediaType'] === 'video'): ?>
                        <video controls class="w-full h-40 rounded">
                            <source src="<?= $media['MediaPath'] ?>" type="video/mp4">
                        </video>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- New Media Upload -->
        <div>
            <label class="block font-semibold">Upload New Image</label>
            <input type="file" name="image" accept="image/*" class="mt-1" />
        </div>
        <div>
            <label class="block font-semibold">Upload New Video</label>
            <input type="file" name="video" accept="video/*" class="mt-1" />
        </div>

        <div class="flex justify-between mt-6">
            <a href="student_my_recipes.php" class="text-gray-600 hover:underline">Cancel</a>
            <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded">Update</button>
        </div>
    </form>
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

        const container = document.getElementById('stepContainer');

        const wrapper = document.createElement('div');
        wrapper.className = 'step-item bg-gray-50 border p-3 rounded relative';

        wrapper.innerHTML = `
            <label class="block text-sm font-semibold text-gray-700 mb-1">Step ${stepCount}</label>
            <input type="hidden" name="step_id[]" value="new">
            <textarea name="step_instruction[]" class="w-full p-2 border rounded" required></textarea>
            <button type="button" class="absolute top-2 right-2 text-red-500 text-sm" onclick="removeNewStep(this)">Delete</button>
        `;

        container.appendChild(wrapper);
    }

    function removeExistingStep(button, stepID) {
        button.closest('.step-item').remove();

        const deleteInput = document.getElementById('delete_step_ids');
        const existing = deleteInput.value ? deleteInput.value.split(',') : [];
        existing.push(stepID);
        deleteInput.value = existing.join(',');
    }

    function removeNewStep(button) {
        button.closest('.step-item').remove();
        stepCount--;
    }
</script>
</body>
</html>
