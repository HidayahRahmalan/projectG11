<?php
// --- SETUP AND SECURITY ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'connection.php';

// --- PERMISSION CHECK ---
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['chef', 'student'])) {
    header("location: login.php?auth_error=true");
    exit;
}

$sql_cuisines = "SELECT * FROM cuisines ORDER BY cuisine_name ASC";
$result_cuisines = $conn->query($sql_cuisines);
$available_cuisines = $result_cuisines->fetch_all(MYSQLI_ASSOC);

// --- INITIALIZE VARIABLES ---
$errors = [];
$success_message = "";
$recipeTitle = $cuisineType = $dietaryRestrictions = $difficulty = "";
$prepTime = $cookTime = $recipeDescription = "";
$posted_ingredients = [];
$posted_steps = [];


// --- FORM PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 1. GATHER AND VALIDATE DATA ---
    $user_id = $_SESSION["user_id"];
    $recipeTitle = trim($_POST['recipeTitle'] ?? '');
    $cuisine_input = $_POST['cuisineType'] ?? '';
    $other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
    $final_cuisine_id = null;
    $dietaryRestrictions = $_POST['dietaryRestrictions'] ?? '';
    $difficulty = $_POST['difficulty'] ?? 'easy';
    $prepTime = trim($_POST['prepTime'] ?? '');
    $cookTime = trim($_POST['cookTime'] ?? '');
    $recipeDescription = trim($_POST['recipeDescription'] ?? '');
    $posted_ingredients = isset($_POST['ingredients']) ? $_POST['ingredients'] : [];
    $posted_steps = isset($_POST['steps']) ? $_POST['steps'] : [];

    if (empty($recipeTitle)) { $errors[] = "Recipe Title is required."; }
    if (empty($prepTime) || !filter_var($prepTime, FILTER_VALIDATE_INT) || $prepTime <= 0) { $errors[] = "Prep Time must be a valid number."; }
    if (empty($cookTime) || !filter_var($cookTime, FILTER_VALIDATE_INT) || $cookTime <= 0) { $errors[] = "Cook Time must be a valid number."; }
    if (empty(array_filter($posted_ingredients, 'trim'))) { $errors[] = "Please add at least one ingredient."; }
    if (empty(array_filter($posted_steps, 'trim'))) { $errors[] = "Please add at least one cooking step."; }
    if (empty($_FILES['recipeMedia']['name'][0])) { $errors[] = "At least one main recipe photo or video is required."; }

    $cuisine_input = $_POST['cuisineType'] ?? '';
    $other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
    $final_cuisine_id = null;

    if ($cuisine_input === 'other') {
        if (empty($other_cuisine_input)) {
            $errors[] = "Please specify the cuisine name when selecting 'Other'.";
        } else {
            $stmt_check = $conn->prepare("SELECT cuisine_id FROM cuisines WHERE cuisine_name = ?");
            $stmt_check->bind_param("s", $other_cuisine_input);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            if ($result->num_rows > 0) {
                $final_cuisine_id = $result->fetch_assoc()['cuisine_id'];
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO cuisines (cuisine_name) VALUES (?)");
                $stmt_insert->bind_param("s", $other_cuisine_input);
                $stmt_insert->execute();
                $final_cuisine_id = $conn->insert_id;
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    } elseif (!empty($cuisine_input)) {
        $stmt_get_id = $conn->prepare("SELECT cuisine_id FROM cuisines WHERE cuisine_name = ?");
        $stmt_get_id->bind_param("s", $cuisine_input);
        $stmt_get_id->execute();
        $result_id = $stmt_get_id->get_result();
        if($result_id->num_rows > 0) {
            $final_cuisine_id = $result_id->fetch_assoc()['cuisine_id'];
        } else {
            $errors[] = "Invalid cuisine selected.";
        }
        $stmt_get_id->close();
    }

    // --- 2. DATABASE INSERTION (if no validation errors) ---
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_recipe = "INSERT INTO recipes (user_id, title, description, cuisine_id, dietary_restrictions, difficulty, prep_time, cook_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_recipe = $conn->prepare($sql_recipe);
            $stmt_recipe->bind_param("ississii", $user_id, $recipeTitle, $recipeDescription, $final_cuisine_id, $dietaryRestrictions, $difficulty, $prepTime, $cookTime);
            $stmt_recipe->execute();
            $recipe_id = $conn->insert_id;
            $stmt_recipe->close();

            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            // Process main recipe media
            foreach ($_FILES['recipeMedia']['name'] as $key => $name) {
                if ($_FILES['recipeMedia']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['recipeMedia']['tmp_name'][$key];
                    $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $unique_filename = uniqid('recipe_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . $unique_filename;

                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $media_type = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : 'video';
                        $sql_media = "INSERT INTO media (recipe_id, media_type, file_path) VALUES (?, ?, ?)";
                        $stmt_media = $conn->prepare($sql_media);
                        $stmt_media->bind_param("iss", $recipe_id, $media_type, $target_path);
                        $stmt_media->execute();
                        $stmt_media->close();
                    } else { throw new Exception("Failed to move uploaded file: " . htmlspecialchars($name)); }
                }
            }

            // Process Ingredients
            $sql_ingredient = "INSERT INTO ingredients (recipe_id, ingredient_text, sort_order) VALUES (?, ?, ?)";
            $stmt_ingredient = $conn->prepare($sql_ingredient);
            foreach (array_filter($posted_ingredients, 'trim') as $index => $text) {
                $sort_order = $index + 1;
                $stmt_ingredient->bind_param("isi", $recipe_id, $text, $sort_order);
                $stmt_ingredient->execute();
            }
            $stmt_ingredient->close();

            // --- Process Steps with their images ---
            $sql_step = "INSERT INTO steps (recipe_id, step_text, step_image_path, sort_order) VALUES (?, ?, ?, ?)";
            $stmt_step = $conn->prepare($sql_step);
            foreach (array_filter($posted_steps, 'trim') as $index => $text) {
                $sort_order = $index + 1;
                $step_image_path = null; // Default to null

                // Check if a file was uploaded for this specific step
                if (isset($_FILES['step_images']['name'][$index]) && $_FILES['step_images']['error'][$index] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['step_images']['tmp_name'][$index];
                    $name = $_FILES['step_images']['name'][$index];
                    $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $unique_filename = uniqid('step_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $step_image_path = $target_path; // Set the path if upload is successful
                    } else {
                        // Optionally, throw an error if a step image fails to upload
                        throw new Exception("Failed to move uploaded step image: " . htmlspecialchars($name));
                    }
                }
                
                // Bind and execute for each step
                $stmt_step->bind_param("issi", $recipe_id, $text, $step_image_path, $sort_order);
                $stmt_step->execute();
            }
            $stmt_step->close();


            $conn->commit();
            $success_message = "Your recipe has been published successfully! <a href='recipe-details.php?id=$recipe_id'>View it here</a>.";

            // Clear form data on success
            $recipeTitle = $cuisineType = $dietaryRestrictions = $difficulty = "";
            $prepTime = $cookTime = $recipeDescription = "";
            $posted_ingredients = [];
            $posted_steps = [];

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Upload Recipe - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />
    <style>
      .form-error-list { 
        background: #f8d7da; 
        color: #721c24; 
        padding: 1rem; 
        border-radius: 10px; 
        margin-bottom: 1.5rem; 
        border: 1px solid #f5c6cb; 
      }
      .form-error-list ul { 
        margin: 0; 
        padding-left: 20px; 
      }
      .form-success { 
        background: #d4edda; 
        color: #155724; 
        padding: 1rem; 
        border-radius: 10px; 
        margin-bottom: 1.5rem; 
        border: 1px solid #c3e6cb; 
        text-align: center; 
      }
      .form-success a { 
        color: #155724; 
        font-weight: bold; 
        text-decoration: underline; 
      }
      .dynamic-input-group { 
        display: flex; 
        align-items: flex-start; 
        gap: 1rem; 
        margin-bottom: 1rem; 
      }
      .dynamic-input-group input, .dynamic-input-group textarea { 
        flex-grow: 1; 
      }
      .remove-btn { 
        background: #dc3545; 
        color: white; 
        border: none; 
        width: 40px; 
        height: 40px; 
        border-radius: 50%; 
        font-size: 1.6rem; 
        font-weight: bold; 
        cursor: pointer; 
        flex-shrink: 0; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        padding-bottom: 3px; 
      }
      #media-preview-list { 
        border: 1px solid #ddd; 
        border-radius: 8px; 
        padding: 1rem; 
        min-height: 50px; 
      }
      #media-preview-list ul { 
        list-style-type: none; 
        padding: 0; 
      }
      #media-preview-list li { 
        background-color: #f8f9fa; 
        padding: 0.5rem 1rem; 
        border-radius: 5px; 
        margin-bottom: 0.5rem; 
      }
      .user-info { 
        display: flex; 
        flex-direction: column; 
        align-items: flex-end; 
        line-height: 1.2; color: #333; 
        margin-right: -10px; 
      }
      .user-name { 
        font-weight: 600; 
        font-size: 0.9rem; 
      }
      .user-role { 
        font-size: 0.75rem; 
        color: #777; 
        text-transform: capitalize; 
      }
      .step-image-controls {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 0.5rem;
          width: 150px; /* Fixed width for the image upload area */
          flex-shrink: 0;
      }
      .step-image-preview {
          width: 150px;
          height: 100px;
          border: 2px dashed #ddd;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          overflow: hidden;
          background-color: #f8f9fa;
          position: relative;
      }
      .step-image-preview img {
          max-width: 100%;
          max-height: 100%;
          display: block;
      }
      .add-step-photo-btn {
          padding: 0.5em 1em;
          background-color: #5cb85c;
          color: white;
          border: none;
          border-radius: 5px;
          cursor: pointer;
          font-size: 0.8rem;
          width: 100%;
      }
      .remove-step-photo-btn {
          position: absolute;
          top: 5px;
          right: 5px;
          background-color: rgba(220, 53, 69, 0.8);
          color: white;
          border: none;
          border-radius: 50%;
          width: 24px;
          height: 24px;
          font-size: 1rem;
          font-weight: bold;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          line-height: 1;
      }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
          <a href="home.php" class="logo">🍳 CookTogether</a>
          <div class="nav-links">
            <a class="nav-link" href="home.php">Home</a>
            <a class="nav-link active" href="upload.php">Upload Recipe</a>
            <a class="nav-link" href="logout.php">Logout</a>
            <div class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['name']); ?>"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            </div>
          </div>
        </div>
    </nav>

    <div class="container">
        <div class="upload-section">
            <h1 class="upload-title">Share Your Recipe</h1>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                
                <?php if(!empty($success_message)): ?><div class="form-success"><?php echo $success_message; ?></div><?php endif; ?>
                <?php if(!empty($errors)): ?>
                    <div class="form-error-list"><strong>Please fix the following errors:</strong><ul>
                    <?php foreach ($errors as $error): ?><li><?php echo $error; ?></li><?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>

                <div class="form-section">
                    <h3 class="section-title">📝 Recipe Information</h3>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Recipe Title *</label><input type="text" class="form-input" name="recipeTitle" placeholder="Enter recipe title..."value="<?php echo htmlspecialchars($recipeTitle); ?>" required/></div>
                        <div class="form-group">
                            <label class="form-label">Cuisine Type</label>
                            <select class="form-select" name="cuisineType" id="cuisineTypeSelect">
                                <option value="">Select cuisine</option>
                                <?php foreach ($available_cuisines as $cuisine_item): ?>
                                    <?php 
                                        $cuisine_value = htmlspecialchars($cuisine_item['cuisine_name']);
                                        $selected = ($cuisine_value === ($_POST['cuisineType'] ?? '')) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $cuisine_value; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars(ucfirst($cuisine_value)); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other" <?php if (($_POST['cuisineType'] ?? '') === 'other') echo 'selected'; ?>>Other...</option>
                            </select>
                        </div>
                        <div class="form-group" id="otherCuisineGroup" style="display: none;">
                            <label class="form-label">Please specify other cuisine</label>
                            <input type="text" class="form-input" name="otherCuisineType" id="otherCuisineInput" value="<?php echo htmlspecialchars($_POST['otherCuisineType'] ?? ''); ?>">
                        </div>
                        <div class="form-group"><label class="form-label">Dietary Restrictions</label><select class="form-select" name="dietaryRestrictions"><option value="">None</option><option value="vegetarian" <?php if($dietaryRestrictions == 'vegetarian') echo 'selected'; ?>>🥬 Vegetarian</option><option value="vegan" <?php if($dietaryRestrictions == 'vegan') echo 'selected'; ?>>🌱 Vegan</option></select></div>
                        <div class="form-group"><label class="form-label">Difficulty Level</label><select class="form-select" name="difficulty"><option value="easy" <?php if($difficulty == 'easy') echo 'selected'; ?>>😊 Easy</option><option value="medium" <?php if($difficulty == 'medium') echo 'selected'; ?>>🤔 Medium</option><option value="hard" <?php if($difficulty == 'hard') echo 'selected'; ?>>😤 Hard</option></select></div>
                        <div class="form-group"><label class="form-label">Prep Time (minutes) *</label><input type="number" class="form-input" name="prepTime" value="<?php echo htmlspecialchars($prepTime); ?>" required/></div>
                        <div class="form-group"><label class="form-label">Cook Time (minutes) *</label><input type="number" class="form-input" name="cookTime" value="<?php echo htmlspecialchars($cookTime); ?>" required/></div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="recipeDescription" placeholder="Describe this recipe..."><?php echo htmlspecialchars($recipeDescription); ?></textarea></div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">🥕 Ingredients</h3>
                    <div id="ingredientsContainer">
                        <?php 
                        $ingredients_to_display = !empty($posted_ingredients) ? $posted_ingredients : [''];
                        foreach ($ingredients_to_display as $ing): ?>
                            <div class="dynamic-input-group">
                                <input type="text" class="form-input" name="ingredients[]" value="<?php echo htmlspecialchars($ing); ?>" placeholder="e.g., 1 cup of flour">
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addIngredientBtn">+ Add Ingredient</button>
                </div>

                <!-- --- Cooking Steps Section --- -->
                <div class="form-section">
                    <h3 class="section-title">👨‍🍳 Cooking Steps</h3>
                    <div id="stepsContainer">
                        <?php 
                        $steps_to_display = !empty($posted_steps) ? $posted_steps : [''];
                        foreach ($steps_to_display as $index => $step): ?>
                            <div class="dynamic-input-group">
                                <textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."><?php echo htmlspecialchars($step); ?></textarea>
                                <div class="step-image-controls">
                                    <div class="step-image-preview"></div>
                                    <button type="button" class="add-step-photo-btn">Add Photo</button>
                                    <input type="file" name="step_images[]" class="step-image-input" accept="image/*" style="display: none;">
                                </div>
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addStepBtn">+ Add Step</button>
                </div>
                
                <!-- This section is for MAIN recipe photos, not step photos -->
                <div class="form-section">
                    <h3 class="section-title">📸 Main Photos & Videos</h3>
                    <button type="button" id="addMediaBtn" class="add-step-btn">Add Main Photos & Videos</button>
                    <input type="file" id="recipeMediaInput" name="recipeMedia[]" accept="image/*,video/*" multiple style="display: none;" />
                    <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">These are the main cover images for your recipe. At least one is required.</p>
                    <div id="media-preview-list">
                        <ul id="file-list"></ul>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">🚀 Publish Recipe</button>
            </form>
        </div>
    </div>
    
<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- Master function to show/hide remove buttons for any container ---
        function updateRemoveButtons(containerSelector, itemSelector) {
            const container = document.querySelector(containerSelector);
            if (!container) return;
            const rows = container.querySelectorAll(itemSelector);
            rows.forEach(row => {
                const removeBtn = row.querySelector('.remove-btn');
                if (removeBtn) {
                    removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
                }
            });
        }

        // --- INGREDIENT DYNAMIC ACTIONS ---
        const ingredientsContainer = document.getElementById('ingredientsContainer');
        document.getElementById('addIngredientBtn').addEventListener('click', function() {
            const newIngredient = document.createElement('div');
            newIngredient.className = 'dynamic-input-group';
            newIngredient.innerHTML = `
                <input type="text" class="form-input" name="ingredients[]" placeholder="e.g., 1 tsp of salt">
                <button type="button" class="remove-btn">×</button>
            `;
            ingredientsContainer.appendChild(newIngredient);
            updateRemoveButtons('#ingredientsContainer', '.dynamic-input-group');
        });

        // --- STEP DYNAMIC ACTIONS ---
        const stepsContainer = document.getElementById('stepsContainer');
        document.getElementById('addStepBtn').addEventListener('click', function() {
            const newStep = document.createElement('div');
            newStep.className = 'dynamic-input-group';
            newStep.innerHTML = `
                <textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."></textarea>
                <div class="step-image-controls">
                    <div class="step-image-preview"></div>
                    <button type="button" class="add-step-photo-btn">Add Photo</button>
                    <input type="file" name="step_images[]" class="step-image-input" accept="image/*" style="display: none;">
                </div>
                <button type="button" class="remove-btn">×</button>
            `;
            stepsContainer.appendChild(newStep);
            updateRemoveButtons('#stepsContainer', '.dynamic-input-group');
        });

        // --- UNIVERSAL EVENT LISTENER FOR DYNAMIC CONTENT ---
        document.addEventListener('click', function(e) {
            // Handles removing an entire ingredient or step row
            if (e.target && e.target.classList.contains('remove-btn')) {
                const group = e.target.closest('.dynamic-input-group');
                const container = group.parentElement;
                group.remove();
                updateRemoveButtons('#' + container.id, '.dynamic-input-group');
            }

            // --- Handles clicking the "Add Photo" button for a step ---
            if (e.target && e.target.classList.contains('add-step-photo-btn')) {
                // Find the hidden file input within the same group and click it
                e.target.closest('.step-image-controls').querySelector('.step-image-input').click();
            }

            // ---  Handles removing a step's photo preview ---
            if (e.target && e.target.classList.contains('remove-step-photo-btn')) {
                const controls = e.target.closest('.step-image-controls');
                const preview = controls.querySelector('.step-image-preview');
                const fileInput = controls.querySelector('.step-image-input');
                const addBtn = controls.querySelector('.add-step-photo-btn');

                // Clear the preview and remove the remove button itself
                preview.innerHTML = '';
                // CRITICAL: Reset the file input's value. This deselects the file.
                fileInput.value = '';
                // Show the "Add Photo" button again
                addBtn.style.display = 'block';
            }
        });

        // --- EVENT LISTENER FOR STEP IMAGE FILE INPUTS ---
        document.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('step-image-input')) {
                const file = e.target.files[0];
                if (file) {
                    const controls = e.target.closest('.step-image-controls');
                    const preview = controls.querySelector('.step-image-preview');
                    const addBtn = controls.querySelector('.add-step-photo-btn');
                    
                    // Create a preview image
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        preview.innerHTML = `
                            <img src="${event.target.result}" alt="Step image preview">
                            <button type="button" class="remove-step-photo-btn">×</button>
                        `;
                        // Hide the "Add Photo" button since we now have a photo
                        addBtn.style.display = 'none';
                    }
                    reader.readAsDataURL(file);
                }
            }
        });

        // --- Initial Page Load Setup ---
        updateRemoveButtons('#ingredientsContainer', '.dynamic-input-group');
        updateRemoveButtons('#stepsContainer', '.dynamic-input-group');

        // --- Cuisine "Other" Field Toggle ---
        const cuisineSelect = document.getElementById('cuisineTypeSelect');
        const otherCuisineGroup = document.getElementById('otherCuisineGroup');
        const otherCuisineInput = document.getElementById('otherCuisineInput');
        function toggleOtherField() {
            if (cuisineSelect.value === 'other') {
                otherCuisineGroup.style.display = 'block';
                otherCuisineInput.required = true;
            } else {
                otherCuisineGroup.style.display = 'none';
                otherCuisineInput.required = false;
                otherCuisineInput.value = '';
            }
        }
        toggleOtherField();
        cuisineSelect.addEventListener('change', toggleOtherField);
        
        // --- Main Media File Script ---
        const addMediaBtn = document.getElementById('addMediaBtn');
        const recipeMediaInput = document.getElementById('recipeMediaInput');
        const fileList = document.getElementById('file-list');
        const dataTransfer = new DataTransfer();
        addMediaBtn.addEventListener('click', function() { recipeMediaInput.click(); });
        recipeMediaInput.addEventListener('change', function() {
            for (let i = 0; i < this.files.length; i++) {
                dataTransfer.items.add(this.files[i]);
                const listItem = document.createElement('li');
                listItem.textContent = this.files[i].name;
                fileList.appendChild(listItem);
            }
            this.files = dataTransfer.files;
        });
    });
</script>
</body>
</html>
