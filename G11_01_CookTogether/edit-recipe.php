<?php
// --- SETUP AND SECURITY ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'connection.php';

// --- PERMISSION CHECK ---
// A user must be logged in and have the role of 'chef' or 'student'.
if (!isset($_SESSION["loggedin"]) || !in_array($_SESSION['role'], ['chef', 'student'])) {
    header("location: login.php?auth_error=true");
    exit;
}

// --- 1. GET AND VALIDATE THE RECIPE ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || $_GET['id'] <= 0) {
    die("ERROR: A valid recipe ID is required to edit.");
}
$recipe_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// --- 2. FETCH EXISTING RECIPE DATA TO PRE-FILL THE FORM ---
$recipe = null;
$sql_recipe = "SELECT r.*, c.cuisine_name 
               FROM recipes r
               LEFT JOIN cuisines c ON r.cuisine_id = c.cuisine_id
               WHERE r.recipe_id = ? AND r.user_id = ?";
if ($stmt_recipe = $conn->prepare($sql_recipe)) {
    $stmt_recipe->bind_param("ii", $recipe_id, $user_id);
    $stmt_recipe->execute();
    $result_recipe = $stmt_recipe->get_result();
    if ($result_recipe->num_rows == 1) {
        $recipe = $result_recipe->fetch_assoc();
    } else {
        die("Recipe not found or you do not have permission to edit it.");
    }
    $stmt_recipe->close();
}

// --- 3. FETCH OTHER RELATED DATA (INGREDIENTS, STEPS, MEDIA) ---
$ingredients = [];
$sql_ingredients = "SELECT ingredient_text FROM ingredients WHERE recipe_id = ? ORDER BY sort_order ASC";
$stmt_ing = $conn->prepare($sql_ingredients);
$stmt_ing->bind_param("i", $recipe_id);
$stmt_ing->execute();
$ingredients = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ing->close();

$steps = [];
$sql_steps = "SELECT step_text FROM steps WHERE recipe_id = ? ORDER BY sort_order ASC";
$stmt_steps = $conn->prepare($sql_steps);
$stmt_steps->bind_param("i", $recipe_id);
$stmt_steps->execute();
$steps = $stmt_steps->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_steps->close();

$media = [];
$sql_media = "SELECT media_id, file_path, media_type FROM media WHERE recipe_id = ? ORDER BY media_id ASC";
$stmt_media = $conn->prepare($sql_media);
$stmt_media->bind_param("i", $recipe_id);
$stmt_media->execute();
$media = $stmt_media->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_media->close();

// --- 3. FETCH DYNAMIC DATA FOR DROPDOWNS ---
$sql_cuisines = "SELECT cuisine_id, cuisine_name FROM cuisines ORDER BY cuisine_name ASC";
$result_cuisines = $conn->query($sql_cuisines);
$available_cuisines = $result_cuisines->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Edit Recipe - <?php echo htmlspecialchars($recipe['title']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />
    <style>
      .form-error-list { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid #f5c6cb; }
      .form-error-list ul { margin: 0; padding-left: 20px; }
      .dynamic-input-group { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
      .dynamic-input-group input, .dynamic-input-group textarea { flex-grow: 1; }
      .remove-btn { background: #dc3545; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.5rem; cursor: pointer; line-height: 40px; text-align: center; flex-shrink: 0;  display: inline-flex; align-items: center; justify-content: center; padding-bottom: 3px; }
      .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
      .media-item { position: relative; border: 1px solid #ddd; border-radius: 8px; padding: 0.5rem; }
      .media-item img, .media-item video { width: 100%; height: 100px; object-fit: cover; border-radius: 8px; }
      .media-item label { display: block; text-align: center; margin-top: 0.5rem; font-size: 0.9rem; }
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
            <h1 class="upload-title">Edit Your Recipe</h1>
            
            <form action="update-recipe.php" method="post" enctype="multipart/form-data">
                
                <input type="hidden" name="recipe_id" value="<?php echo $recipe['recipe_id']; ?>">

                <div class="form-section">
                    <h3 class="section-title">📝 Recipe Information</h3>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Recipe Title *</label><input type="text" class="form-input" name="recipeTitle" placeholder="Enter recipe title..." value="<?php echo htmlspecialchars($recipe['title']); ?>" required/></div>
                        <div class="form-group">
                            <label class="form-label">Cuisine Type</label>
                            <select class="form-select" name="cuisineType" id="cuisineTypeSelect">
                                <option value="">Select cuisine</option>
                                <?php foreach ($available_cuisines as $cuisine_item): ?>
                                    <?php 
                                        // The value is now the ID, the text is the name.
                                        $cuisine_id = $cuisine_item['cuisine_id'];
                                        $cuisine_name = htmlspecialchars($cuisine_item['cuisine_name']);
                                        
                                        // Compare the recipe's cuisine_id with the one from the loop.
                                        $selected = ($recipe['cuisine_id'] == $cuisine_id) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $cuisine_id; ?>" <?php echo $selected; ?>>
                                        <?php echo ucfirst($cuisine_name); ?>
                                    </option>
                                <?php endforeach; ?>
                                
                                <option value="other">Other...</option>
                            </select>
                        </div>

                        <div class="form-group" id="otherCuisineGroup" style="display: none;">
                            <label class="form-label">Please specify other cuisine</label>
                            <input type="text" class="form-input" name="otherCuisineType" id="otherCuisineInput" value="">
                        </div>

                        <div class="form-group"><label class="form-label">Dietary Restrictions</label><select class="form-select" name="dietaryRestrictions"><option value="">None</option><option value="vegetarian" <?php if($recipe['dietary_restrictions'] == 'vegetarian') echo 'selected'; ?>>🥬 Vegetarian</option><option value="vegan" <?php if($recipe['dietary_restrictions'] == 'vegan') echo 'selected'; ?>>🌱 Vegan</option></select></div>
                        <div class="form-group"><label class="form-label">Difficulty Level</label><select class="form-select" name="difficulty"><option value="easy" <?php if($recipe['difficulty'] == 'easy') echo 'selected'; ?>>😊 Easy</option><option value="medium" <?php if($recipe['difficulty'] == 'medium') echo 'selected'; ?>>🤔 Medium</option><option value="hard" <?php if($recipe['difficulty'] == 'hard') echo 'selected'; ?>>😤 Hard</option></select></div>
                        <div class="form-group"><label class="form-label">Prep Time (minutes) *</label><input type="number" class="form-input" name="prepTime" value="<?php echo htmlspecialchars($recipe['prep_time']); ?>" required/></div>
                        <div class="form-group"><label class="form-label">Cook Time (minutes) *</label><input type="number" class="form-input" name="cookTime" value="<?php echo htmlspecialchars($recipe['cook_time']); ?>" required/></div>
                    </div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="recipeDescription"><?php echo htmlspecialchars($recipe['description']); ?></textarea></div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">🥕 Ingredients</h3>
                    <div id="ingredientsContainer">
                        <?php if (empty($ingredients)): ?>
                            <!-- Show one empty field if there are no ingredients -->
                            <div class="dynamic-input-group">
                                <input type="text" class="form-input" name="ingredients[]" placeholder="e.g., 1 cup of flour">
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php else: ?>
                            <!-- Loop through existing ingredients -->
                            <?php foreach ($ingredients as $ing): ?>
                                <div class="dynamic-input-group">
                                    <input type="text" class="form-input" name="ingredients[]" value="<?php echo htmlspecialchars($ing['ingredient_text']); ?>">
                                    <button type="button" class="remove-btn">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addIngredientBtn">+ Add Ingredient</button>
                </div>

                <div class="form-section">
                    <h3 class="section-title">👨‍🍳 Cooking Steps</h3>
                    <div id="stepsContainer">
                        <?php if (empty($steps)): ?>
                            <!-- Show one empty field if there are no steps -->
                            <div class="dynamic-input-group">
                                <textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."></textarea>
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php else: ?>
                            <!-- Loop through existing steps -->
                            <?php foreach ($steps as $step): ?>
                                <div class="dynamic-input-group">
                                    <textarea class="form-textarea" name="steps[]"><?php echo htmlspecialchars($step['step_text']); ?></textarea>
                                    <button type="button" class="remove-btn">×</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addStepBtn">+ Add Step</button>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">📸 Current Media</h3>
                    <p>To remove an existing image or video, check the "Delete" box below it.</p>
                    <div class="media-grid">
                        <?php foreach ($media as $item): ?>
                            <div class="media-item">
                                <?php if($item['media_type'] == 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($item['file_path']); ?>" alt="Recipe Media">
                                <?php else: ?>
                                    <video src="<?php echo htmlspecialchars($item['file_path']); ?>" width="150"></video>
                                <?php endif; ?>
                                <label><input type="checkbox" name="delete_media[]" value="<?php echo $item['media_id']; ?>"> Delete this media</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">➕ Add New Photos & Videos</h3>
                    <div class="form-group">
                        <input type="file" name="new_media[]" class="form-input" multiple accept="image/*,video/*">
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">✅ Update Recipe</button>
            </form>
        </div>
    </div>
    
<script>
    // --- Master function to show/hide remove buttons ---
    function updateRemoveButtons(containerId) {
        const container = document.getElementById(containerId);
        const rows = container.querySelectorAll('.dynamic-input-group');
        
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-btn');
            if (removeBtn) {
                // If there's more than 1 row, show the button, otherwise hide it.
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