<?php
include 'header.php';
// Only allow logged-in chefs!
if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'chef') {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Recipe - CookSphere</title>
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
    <div class="glass w-full max-w-3xl p-10 mt-8">
        <h1 class="text-2xl font-bold mb-2 text-gray-800">Upload a New Recipe</h1>
        <p class="text-gray-600 mb-6">Choose how you want to share your recipe:</p>
        <div class="mb-6 flex flex-col md:flex-row gap-4">
            <label class="flex-1 border rounded-2xl p-4 cursor-pointer hover:shadow-md transition">
                <input type="radio" name="mode" value="simple" id="mode_simple" class="mr-2" checked onchange="showForm()">
                <span class="font-semibold text-lg text-gray-700">Simple Recipe</span>
                <div class="text-sm text-gray-500 mt-2">Quick recipe with just a main instruction, images, and/or videos.<br>For straightforward or single-step recipes.</div>
            </label>
            <label class="flex-1 border rounded-2xl p-4 cursor-pointer hover:shadow-md transition">
                <input type="radio" name="mode" value="steps" id="mode_steps" class="mr-2" onchange="showForm()">
                <span class="font-semibold text-lg text-gray-700">Step-by-Step</span>
                <div class="text-sm text-gray-500 mt-2">Add multiple steps, each with instructions, images, and/or videos.<br>Great for more complex recipes with several stages.</div>
            </label>
        </div>
        <form action="process_upload_recipe.php" method="POST" enctype="multipart/form-data" id="simpleForm" class="space-y-6">
            <input type="hidden" name="mode" value="simple">
            <div>
                <label class="block font-medium text-gray-700 mb-1">Recipe Title</label>
                <input type="text" name="title" required class="w-full px-4 py-2 border rounded-xl">
            </div>
            <div>
                <label class="block font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" required class="w-full px-4 py-2 border rounded-xl"></textarea>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block font-medium text-gray-700 mb-1">Cuisine Type</label>
                    <select name="cuisine" required class="w-full px-4 py-2 border rounded-xl" onchange="toggleOtherInput(this, 'cuisineOtherInputSimple')">
                        <option value="" disabled selected>Select cuisine type</option>
                        <option value="Malay">Malay</option>
                        <option value="French">French</option>
                        <option value="Dessert">Dessert</option>
                        <option value="Pasta">Pasta</option>
                         <option value="Chinese">Chinese</option>
                        <option value="Indian">Indian</option>
                        <option value="Italian">Italian</option>
                        <option value="Japanese">Japanese</option>
                        <option value="Mexican">Mexican</option>
                        <option value="Thai">Thai</option>
                        <option value="Middle Eastern">Middle Eastern</option>
                        <option value="Western">Western</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" name="cuisine_other" id="cuisineOtherInputSimple" placeholder="Enter other cuisine type" class="w-full mt-2 px-4 py-2 border rounded-xl hidden">
                </div>
                <div class="flex-1">
                    <label class="block font-medium text-gray-700 mb-1">Dietary Type</label>
                    <select name="dietary" required class="w-full px-4 py-2 border rounded-xl" onchange="toggleOtherInput(this, 'dietaryOtherInputSimple')">
                        <option value="" disabled selected>Select dietary type</option>
                        <option value="Vegan">Vegan</option>
                        <option value="Vegetarian">Vegetarian</option>
                        <option value="Halal">Halal</option>
                        <option value="Non-Vegan">Non-Vegan</option>
                        <option value="Kosher">Kosher</option>
                        <option value="Low-Carb">Low-Carb</option>
                        <option value="Paleo">Paleo</option>
                        <option value="Keto">Keto</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" name="dietary_other" id="dietaryOtherInputSimple" placeholder="Enter other dietary type" class="w-full mt-2 px-4 py-2 border rounded-xl hidden">
                </div>
            </div>
            <!--<div>
                <label class="block font-medium text-gray-700 mb-1">Main Instruction</label>
                <textarea name="main_instruction" required class="w-full px-4 py-2 border rounded-xl"></textarea>
            </div>-->
            <div>
                <label class="block font-medium text-gray-700 mb-1">Images/Videos</label>
                <input type="file" name="media_files[]" accept="image/*,video/*" multiple class="block">
                <div class="text-xs text-gray-500 mt-1">You can select multiple files. (Accepted: JPG, PNG, MP4, etc.)</div>
            </div>
            <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-3 rounded-xl">Upload Recipe</button>
        </form>
        <form action="process_upload_recipe.php" method="POST" enctype="multipart/form-data" id="stepsForm" style="display:none;" class="space-y-6">
            <input type="hidden" name="mode" value="steps">
            <div>
                <label class="block font-medium text-gray-700 mb-1">Recipe Title</label>
                <input type="text" name="title" required class="w-full px-4 py-2 border rounded-xl">
            </div>
            <div>
                <label class="block font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" required class="w-full px-4 py-2 border rounded-xl"></textarea>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block font-medium text-gray-700 mb-1">Cuisine Type</label>
                    <select name="cuisine" required class="w-full px-4 py-2 border rounded-xl" onchange="toggleOtherInput(this, 'cuisineOtherInputStep')">
                        <option value="" disabled selected>Select cuisine type</option>
                        <option value="Malay">Malay</option>
                        <option value="French">French</option>
                        <option value="Dessert">Dessert</option>
                        <option value="Pasta">Pasta</option>
                        <option value="Chinese">Chinese</option>
                        <option value="Indian">Indian</option>
                        <option value="Italian">Italian</option>
                        <option value="Japanese">Japanese</option>
                        <option value="Mexican">Mexican</option>
                        <option value="Thai">Thai</option>
                        <option value="Middle Eastern">Middle Eastern</option>
                        <option value="Western">Western</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" name="cuisine_other" id="cuisineOtherInputStep" placeholder="Enter other cuisine type" class="w-full mt-2 px-4 py-2 border rounded-xl hidden">
                </div>
                <div class="flex-1">
                    <label class="block font-medium text-gray-700 mb-1">Dietary Type</label>
                    <select name="dietary" required class="w-full px-4 py-2 border rounded-xl" onchange="toggleOtherInput(this, 'dietaryOtherInputStep')">
                        <option value="" disabled selected>Select dietary type</option>
                        <option value="Vegan">Vegan</option>
                        <option value="Vegetarian">Vegetarian</option>
                        <option value="Halal">Halal</option>
                        <option value="Non-Vegan">Non-Vegan</option>
                        <option value="Kosher">Kosher</option>
                        <option value="Low-Carb">Low-Carb</option>
                        <option value="Paleo">Paleo</option>
                        <option value="Keto">Keto</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" name="dietary_other" id="dietaryOtherInputStep" placeholder="Enter other dietary type" class="w-full mt-2 px-4 py-2 border rounded-xl hidden">
                </div>

            </div>
            <div id="stepsContainer">
                <label class="block font-medium text-gray-700 mb-2">Steps</label>
                <!-- Steps will be appended here -->
            </div>
            <button type="button" onclick="addStep()" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 font-semibold px-4 py-2 rounded-xl mt-2 mb-4">+ Add Step</button>
            <button type="submit" class="w-full bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-3 rounded-xl">Upload Recipe</button>
        </form>
    </div>
</div>

<script>
function toggleOtherInput(selectElement, inputId) {
    const input = document.getElementById(inputId);
    if (selectElement.value === "Other") {
        input.classList.remove("hidden");
        input.required = true;
    } else {
        input.classList.add("hidden");
        input.required = false;
        input.value = "";
    }
}

function showForm() {
    if (document.getElementById('mode_simple').checked) {
        document.getElementById('simpleForm').style.display = '';
        document.getElementById('stepsForm').style.display = 'none';
    } else {
        document.getElementById('simpleForm').style.display = 'none';
        document.getElementById('stepsForm').style.display = '';
        if (document.querySelectorAll('.step-block').length === 0) addStep();
    }
}

// For dynamic steps (step-by-step mode)
let stepCount = 0;
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
        <textarea name="steps[${stepCount}][instruction]" required class="w-full px-4 py-2 border rounded-xl mb-2"></textarea>
        <label class="block text-gray-700 mb-1">Image/Video</label>
        <input type="file" name="steps[${stepCount}][media][]" accept="image/*,video/*" multiple class="block mb-1">
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
