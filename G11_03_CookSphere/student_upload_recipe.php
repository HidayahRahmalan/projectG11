<?php
session_start();
require 'connection.php';

if (!isset($_SESSION['userid']) || $_SESSION['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['userid'];
$fullname = $_SESSION['fullname'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $cuisine = $_POST['cuisine'] === 'Other' ? trim($_POST['cuisine_other']) : $_POST['cuisine'];
    $dietary = $_POST['dietary'] === 'Other' ? trim($_POST['dietary_other']) : $_POST['dietary'];

    $stmt = $conn->prepare("INSERT INTO recipe (UserID, Title, Description, CuisineType, DietaryType) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userid, $title, $description, $cuisine, $dietary);

    if ($stmt->execute()) {
        $recipe_id = $stmt->insert_id;

        for ($i = 0; $i < count($_POST['step_instruction']); $i++) {
            $instruction = $_POST['step_instruction'][$i];
            if (!empty($instruction)) {
                $stmt_step = $conn->prepare("INSERT INTO step (RecipeID, StepNumber, Instruction) VALUES (?, ?, ?)");
                $step_number = $i + 1;
                $stmt_step->bind_param("iis", $recipe_id, $step_number, $instruction);
                $stmt_step->execute();
                $step_id = $stmt_step->insert_id;

                if (isset($_FILES['step_media']['name'][$i]) && $_FILES['step_media']['error'][$i] === 0) {
                    $filename = 'media/' . uniqid() . basename($_FILES['step_media']['name'][$i]);
                    move_uploaded_file($_FILES['step_media']['tmp_name'][$i], $filename);
                    $media_type = (str_contains($_FILES['step_media']['type'][$i], 'video')) ? 'video' : 'image';

                    $stmt_media = $conn->prepare("INSERT INTO media (RecipeID, StepID, MediaType, MediaPath) VALUES (?, ?, ?, ?)");
                    $stmt_media->bind_param("iiss", $recipe_id, $step_id, $media_type, $filename);
                    $stmt_media->execute();
                }
            }
        }
        $success = "Recipe uploaded successfully!";
    } else {
        $error = "Something went wrong.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Upload Recipe - Student</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
    function addStep() {
      const container = document.getElementById('steps');
      const index = container.children.length;
      const stepHTML = `
        <div class="mb-4 step-item border p-3 rounded bg-gray-50 cursor-move shadow-sm hover:shadow-md transition">
          <label class="block text-sm font-semibold text-gray-700 step-label">Step ${index + 1}</label>
          <textarea name="step_instruction[]" required class="w-full p-2 border rounded mb-2" placeholder="Instruction"></textarea>
          <input type="file" name="step_media[]" accept="image/*,video/*" class="block mb-2" />
        </div>
      `;
      container.insertAdjacentHTML('beforeend', stepHTML);
      updateStepLabels();
    }

    function updateStepLabels() {
      const stepLabels = document.querySelectorAll('.step-label');
      stepLabels.forEach((label, i) => {
        label.textContent = `Step ${i + 1}`;
      });
    }

    document.addEventListener('DOMContentLoaded', () => {
      const stepsContainer = document.getElementById('steps');
      Sortable.create(stepsContainer, {
        animation: 150,
        ghostClass: 'bg-yellow-100',
        onEnd: () => updateStepLabels()
      });
    });
  </script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">

  <!-- Header -->
  <header class="bg-yellow-400 p-4 text-white flex justify-between items-center shadow">
    <h1 class="text-2xl font-bold">CookSphere - Upload Recipe</h1>
    <div>Welcome, <strong><?= htmlspecialchars($fullname) ?></strong></div>
  </header>



  <!-- Main Content -->
  <main class="flex-grow p-6 max-w-4xl mx-auto">
    <!-- Back to Dashboard Button -->
    <div class="mb-6">
      <a href="student_dashboard.php" 
         class="inline-block bg-yellow-400 hover:bg-yellow-500 text-white font-semibold py-2 px-5 rounded-xl shadow transition">
        &larr; Back to Dashboard
      </a>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
      <h2 class="text-2xl font-bold mb-4 text-yellow-600">Upload New Recipe</h2>

      <?php if ($success): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded font-semibold"><?= htmlspecialchars($success) ?></div>
      <?php elseif ($error): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded font-semibold"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="mb-4">
          <label class="block mb-1 font-medium text-gray-700">Recipe Title</label>
          <input type="text" name="title" required class="w-full p-2 border rounded" placeholder="Enter recipe title" />
        </div>

        <div class="mb-4">
          <label class="block mb-1 font-medium text-gray-700">Description</label>
          <textarea name="description" rows="3" class="w-full p-2 border rounded" placeholder="Brief description"></textarea>
        </div>

        <div class="mb-4 flex gap-4">
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

        <div id="steps" class="mb-4">
          <h3 class="text-lg font-bold mb-2">Steps (Drag to reorder)</h3>
          <!-- Initial Step -->
          <div class="mb-4 step-item border p-3 rounded bg-gray-50 cursor-move shadow-sm hover:shadow-md transition">
            <label class="block text-sm font-semibold text-gray-700 step-label">Step 1</label>
            <textarea name="step_instruction[]" required class="w-full p-2 border rounded mb-2" placeholder="Instruction"></textarea>
            <input type="file" name="step_media[]" accept="image/*,video/*" class="block mb-2" />
          </div>
        </div>

        <button type="button" onclick="addStep()" class="mb-4 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded shadow">
          + Add Step
        </button>

        <div>
          <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-white font-semibold px-6 py-2 rounded shadow">
            Upload Recipe
          </button>
        </div>
      </form>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-gray-200 text-center p-4 text-sm text-gray-600">
    &copy; <?= date('Y') ?> CookSphere
  </footer>
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
</script>

</body>
</html>
