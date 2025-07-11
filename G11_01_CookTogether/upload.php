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
    if ($cuisine_input === 'other') { $cuisineType = trim(strtolower($_POST['otherCuisineType'] ?? ''));}
    if (empty($prepTime) || !filter_var($prepTime, FILTER_VALIDATE_INT) || $prepTime <= 0) { $errors[] = "Prep Time must be a valid number."; }
    if (empty($cookTime) || !filter_var($cookTime, FILTER_VALIDATE_INT) || $cookTime <= 0) { $errors[] = "Cook Time must be a valid number."; }
    if (empty(array_filter($posted_ingredients, 'trim'))) { $errors[] = "Please add at least one ingredient."; }
    if (empty(array_filter($posted_steps, 'trim'))) { $errors[] = "Please add at least one cooking step."; }

    if (empty($_FILES['recipeMedia']['name'][0])) {
        $errors[] = "At least one photo or video is required.";
    }

        $cuisine_input = $_POST['cuisineType'] ?? '';
    $other_cuisine_input = trim(strtolower($_POST['otherCuisineType'] ?? ''));
    $final_cuisine_id = null; // This will hold the ID we save to the recipes table

    if ($cuisine_input === 'other') {
        if (empty($other_cuisine_input)) {
            $errors[] = "Please specify the cuisine name when selecting 'Other'.";
        } else {
            // User selected "Other" and typed a new cuisine.
            // 1. Check if this new cuisine already exists.
            $stmt_check = $conn->prepare("SELECT cuisine_id FROM cuisines WHERE cuisine_name = ?");
            $stmt_check->bind_param("s", $other_cuisine_input);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            if ($result->num_rows > 0) {
                // It exists, so get its ID.
                $final_cuisine_id = $result->fetch_assoc()['cuisine_id'];
            } else {
                // It's brand new, so INSERT it and get the new ID.
                $stmt_insert = $conn->prepare("INSERT INTO cuisines (cuisine_name) VALUES (?)");
                $stmt_insert->bind_param("s", $other_cuisine_input);
                $stmt_insert->execute();
                $final_cuisine_id = $conn->insert_id; // Get the ID of the new entry
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    } elseif (!empty($cuisine_input)) {
        // User selected an existing cuisine from the dropdown.
        // We need to find its ID.
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

            $sql_ingredient = "INSERT INTO ingredients (recipe_id, ingredient_text, sort_order) VALUES (?, ?, ?)";
            $stmt_ingredient = $conn->prepare($sql_ingredient);
            foreach (array_filter($posted_ingredients, 'trim') as $index => $text) {
                $sort_order = $index + 1;
                $stmt_ingredient->bind_param("isi", $recipe_id, $text, $sort_order);
                $stmt_ingredient->execute();
            }
            $stmt_ingredient->close();

            $sql_step = "INSERT INTO steps (recipe_id, step_text, sort_order) VALUES (?, ?, ?)";
            $stmt_step = $conn->prepare($sql_step);
            foreach (array_filter($posted_steps, 'trim') as $index => $text) {
                $sort_order = $index + 1;
                $stmt_step->bind_param("isi", $recipe_id, $text, $sort_order);
                $stmt_step->execute();
            }
            $stmt_step->close();

            $conn->commit();
            $success_message = "Your recipe has been published successfully! <a href='recipe-details.php?id=$recipe_id'>View it here</a>.";
            
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
      .form-error-list { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #f5c6cb; }
      .form-error-list ul { margin: 0; padding-left: 20px; }
      .form-success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #c3e6cb; text-align: center; }
      .form-success a { color: #155724; font-weight: bold; text-decoration: underline; }
      .dynamic-input-group { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
      .dynamic-input-group input, .dynamic-input-group textarea { flex-grow: 1; }
      .remove-btn { background: #dc3545; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; line-height: 40px; text-align: center; flex-shrink: 0; }
      #media-preview-list { border: 1px solid #ddd; border-radius: 8px; padding: 1rem; min-height: 50px; }
      #media-preview-list ul { list-style-type: none; padding: 0; }
      #media-preview-list li { background-color: #f8f9fa; padding: 0.5rem 1rem; border-radius: 5px; margin-bottom: 0.5rem; }
      .dynamic-input-group {
          display: flex;
          /* This is the key property: It vertically centers all direct children. */
          align-items: center; 
          gap: 1rem;
          margin-bottom: 1rem;
      }

      .dynamic-input-group input, 
      .dynamic-input-group textarea {
          flex-grow: 1; /* Allows the input/textarea to take up remaining space */
      }

      .remove-btn {
          background: #dc3545;
          color: white;
          border: none;
          width: 40px;
          height: 40px;
          border-radius: 50%;
          font-size: 1.6rem; /* Slightly larger 'x' */
          font-weight: bold;
          cursor: pointer;
          flex-shrink: 0; /* Prevents the button from shrinking */

          /* New flex properties to perfectly center the '×' inside the circle */
          display: inline-flex;
          align-items: center;
          justify-content: center;
          padding-bottom: 3px; /* Optical adjustment to make the 'x' look centered */
      }
      .user-info {
            display: flex;
            flex-direction: column; /* Stack name and role vertically */
            align-items: flex-end;  /* Align text to the right */
            line-height: 1.2;
            color: #333;
            margin-right: -10px; /* Bring it a bit closer to the avatar */
        }
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .user-role {
            font-size: 0.75rem;
            color: #777;
            text-transform: capitalize; /* Makes "chef" look like "Chef" */
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
                                        // Keep the selected value if the form reloads with an error
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
                        // Use the posted ingredients if available, otherwise start with one empty one
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

                <div class="form-section">
                    <h3 class="section-title">👨‍🍳 Cooking Steps</h3>
                    <div id="stepsContainer">
                        <?php 
                        // Use the posted steps if available, otherwise start with one empty one
                        $steps_to_display = !empty($posted_steps) ? $posted_steps : [''];
                        foreach ($steps_to_display as $step): ?>
                            <div class="dynamic-input-group">
                                <textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."><?php echo htmlspecialchars($step); ?></textarea>
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addStepBtn">+ Add Step</button>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">📸 Photos & Videos</h3>
                    <button type="button" id="addMediaBtn" class="add-step-btn">Add Photos & Videos</button>
                    <input type="file" id="recipeMediaInput" name="recipeMedia[]" accept="image/*,video/*" multiple style="display: none;" />
                    <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">Click the button to add files. You can click it multiple times to add more. At least one image is required.</p>
                    <div id="media-preview-list">
                        <ul id="file-list"></ul>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">🚀 Publish Recipe</button>
            </form>
        </div>
    </div>
    
<script>
    // --- Master function to show/hide remove buttons ---
    function updateRemoveButtons(containerId) {
        const container = document.getElementById(containerId);
        const rows = container.querySelectorAll('.dynamic-input-group');
        
        // Loop through each row to manage its button
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-btn');
            if (removeBtn) {
                // If there is more than 1 row, show the button, otherwise hide it.
                removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
            }
        });
    }

    // --- Function to add a new input row ---
    function addDynamicInput(containerId, inputHTML) {
        const container = document.getElementById(containerId);
        const newRow = document.createElement('div');
        newRow.className = 'dynamic-input-group';
        newRow.innerHTML = inputHTML;
        container.appendChild(newRow);
        // After adding, update the buttons for that container
        updateRemoveButtons(containerId);
    }

    // --- Event Listeners ---

    // Add Ingredient Button
    document.getElementById('addIngredientBtn').addEventListener('click', function() {
        addDynamicInput(
            'ingredientsContainer', 
            '<input type="text" class="form-input" name="ingredients[]" placeholder="e.g., 1 tsp of salt"><button type="button" class="remove-btn">×</button>'
        );
    });

    // Add Step Button
    document.getElementById('addStepBtn').addEventListener('click', function() {
        addDynamicInput(
            'stepsContainer', 
            '<textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."></textarea><button type="button" class="remove-btn">×</button>'
        );
    });

    // Universal Remove Button Listener
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('remove-btn')) {
            const group = e.target.closest('.dynamic-input-group');
            const container = group.parentElement;
            group.remove();
            // After removing, update the buttons for that container
            updateRemoveButtons(container.id);
        }
    });

    // --- Initial Page Load Setup ---
    document.addEventListener('DOMContentLoaded', function() {
        // Run the check once for each container when the page first loads
        updateRemoveButtons('ingredientsContainer');
        updateRemoveButtons('stepsContainer');

        // Your existing media file script
        const addMediaBtn = document.getElementById('addMediaBtn');
        const recipeMediaInput = document.getElementById('recipeMediaInput');
        const fileList = document.getElementById('file-list');
        const dataTransfer = new DataTransfer();

        addMediaBtn.addEventListener('click', function() { recipeMediaInput.click(); });

        recipeMediaInput.addEventListener('change', function() {
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                dataTransfer.items.add(file);
                const listItem = document.createElement('li');
                listItem.textContent = file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                fileList.appendChild(listItem);
            }
            this.files = dataTransfer.files;
        });
    });

        // At the bottom of the page, inside your <script> tag
    document.addEventListener('DOMContentLoaded', function() {
        const cuisineSelect = document.getElementById('cuisineTypeSelect');
        const otherCuisineGroup = document.getElementById('otherCuisineGroup');
        const otherCuisineInput = document.getElementById('otherCuisineInput');

        function toggleOtherField() {
            if (cuisineSelect.value === 'other') {
                otherCuisineGroup.style.display = 'block'; // Show the text field
                otherCuisineInput.required = true; // Make it required if "Other" is selected
            } else {
                otherCuisineGroup.style.display = 'none'; // Hide it
                otherCuisineInput.required = false;
                otherCuisineInput.value = ''; // Clear the value if user changes their mind
            }
        }

        // Check on page load (for edit forms)
        toggleOtherField();

        // Check when the user changes the selection
        cuisineSelect.addEventListener('change', toggleOtherField);
    });

</script>
</body>
</html>