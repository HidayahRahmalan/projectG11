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

// --- 1. GET AND VALIDATE THE RECIPE ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || $_GET['id'] <= 0) {
    die("ERROR: A valid recipe ID is required to edit.");
}
$recipe_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// --- 2. FETCH EXISTING RECIPE DATA ---
$recipe = null;
$sql_recipe = "SELECT r.*, c.cuisine_name FROM recipes r LEFT JOIN cuisines c ON r.cuisine_id = c.cuisine_id WHERE r.recipe_id = ? AND r.user_id = ?";
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

// --- 3. FETCH RELATED DATA ---
$ingredients = [];
$sql_ingredients = "SELECT ingredient_text FROM ingredients WHERE recipe_id = ? ORDER BY sort_order ASC";
$stmt_ing = $conn->prepare($sql_ingredients);
$stmt_ing->bind_param("i", $recipe_id);
$stmt_ing->execute();
$ingredients = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ing->close();

// --- MODIFIED: Fetch steps with their ID and image path ---
$steps = [];
$sql_steps = "SELECT step_id, step_text, step_image_path FROM steps WHERE recipe_id = ? ORDER BY sort_order ASC";
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
      .dynamic-input-group { display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem; }
      .dynamic-input-group input, .dynamic-input-group textarea { flex-grow: 1; }
      .remove-btn { background: #dc3545; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; font-size: 1.6rem; font-weight: bold; cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; padding-bottom: 3px; }
      .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; }
      .media-item { position: relative; border: 1px solid #ddd; border-radius: 8px; padding: 0.5rem; }
      .media-item img, .media-item video { width: 100%; height: 100px; object-fit: cover; border-radius: 8px; }
      .media-item label { display: block; text-align: center; margin-top: 0.5rem; font-size: 0.9rem; }
      .user-info { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2; color: #333; margin-right: -10px; }
      .user-name { font-weight: 600; font-size: 0.9rem; }
      .user-role { font-size: 0.75rem; color: #777; text-transform: capitalize; }
      
      /* --- NEW/MODIFIED CSS for Step Image Upload --- */
      .step-image-controls { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; width: 150px; flex-shrink: 0; }
      .step-image-preview { width: 150px; height: 100px; border: 2px dashed #ddd; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: #f8f9fa; position: relative; }
      .step-image-preview img { max-width: 100%; max-height: 100%; display: block; }
      .add-step-photo-btn { padding: 0.5em 1em; background-color: #5cb85c; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 0.8rem; width: 100%; }
      .remove-step-photo-btn { position: absolute; top: 5px; right: 5px; background-color: rgba(220, 53, 69, 0.8); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="home.php" class="logo">🍳 CookTogether</a>
            <div class="nav-links">
                <a class="nav-link" href="home.php">Home</a>
                <a class="nav-link" href="upload.php">Upload Recipe</a>
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
            
            <!-- IMPORTANT: The form now points to update-recipe.php -->
            <form action="update-recipe.php" method="post" enctype="multipart/form-data">
                
                <input type="hidden" name="recipe_id" value="<?php echo $recipe['recipe_id']; ?>">

                <div class="form-section">
                    <h3 class="section-title">📝 Recipe Information</h3>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Recipe Title *</label><input type="text" class="form-input" name="recipeTitle" value="<?php echo htmlspecialchars($recipe['title']); ?>" required/></div>
                        <div class="form-group">
                            <label class="form-label">Cuisine Type</label>
                            <select class="form-select" name="cuisineType" id="cuisineTypeSelect">
                                <option value="">Select cuisine</option>
                                <?php foreach ($available_cuisines as $cuisine_item): ?>
                                    <option value="<?php echo $cuisine_item['cuisine_id']; ?>" <?php echo ($recipe['cuisine_id'] == $cuisine_item['cuisine_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($cuisine_item['cuisine_name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="other">Other...</option>
                            </select>
                        </div>
                        <div class="form-group" id="otherCuisineGroup" style="display: none;"><label class="form-label">Please specify other cuisine</label><input type="text" class="form-input" name="otherCuisineType" id="otherCuisineInput"></div>
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
                        <?php if (!empty($ingredients)) foreach ($ingredients as $ing): ?>
                            <div class="dynamic-input-group">
                                <input type="text" class="form-input" name="ingredients[]" value="<?php echo htmlspecialchars($ing['ingredient_text']); ?>">
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addIngredientBtn">+ Add Ingredient</button>
                </div>

                <!-- --- MODIFIED: Cooking Steps Section --- -->
                <div class="form-section">
                    <h3 class="section-title">👨‍🍳 Cooking Steps</h3>
                    <div id="stepsContainer">
                        <?php if (!empty($steps)) foreach ($steps as $step): ?>
                            <div class="dynamic-input-group">
                                <!-- Hidden input for step ID to identify existing steps -->
                                <input type="hidden" name="step_ids[]" value="<?php echo $step['step_id']; ?>">
                                <textarea class="form-textarea" name="steps[]"><?php echo htmlspecialchars($step['step_text']); ?></textarea>
                                
                                <div class="step-image-controls">
                                    <div class="step-image-preview">
                                        <?php if (!empty($step['step_image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($step['step_image_path']); ?>" alt="Step image">
                                            <button type="button" class="remove-step-photo-btn">×</button>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Hidden input to track existing image path. JS will clear this if user removes photo -->
                                    <input type="hidden" name="existing_step_images[]" class="existing-step-image-path" value="<?php echo htmlspecialchars($step['step_image_path']); ?>">
                                    
                                    <button type="button" class="add-step-photo-btn" style="<?php echo !empty($step['step_image_path']) ? 'display:none;' : ''; ?>">
                                        <?php echo !empty($step['step_image_path']) ? 'Change Photo' : 'Add Photo'; ?>
                                    </button>
                                    <input type="file" name="step_images[]" class="step-image-input" accept="image/*" style="display: none;">
                                </div>
                                <button type="button" class="remove-btn">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-step-btn" id="addStepBtn">+ Add Step</button>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">📸 Current Main Media</h3>
                    <p>To remove an existing image or video, check the "Delete" box below it.</p>
                    <div class="media-grid">
                        <?php foreach ($media as $item): ?>
                            <div class="media-item">
                                <?php if($item['media_type'] == 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($item['file_path']); ?>" alt="Recipe Media">
                                <?php else: ?>
                                    <video src="<?php echo htmlspecialchars($item['file_path']); ?>" width="150" controls></video>
                                <?php endif; ?>
                                <label><input type="checkbox" name="delete_media[]" value="<?php echo $item['media_id']; ?>"> Delete</label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">➕ Add New Main Photos & Videos</h3>
                    <div class="form-group"><input type="file" name="new_media[]" class="form-input" multiple accept="image/*,video/*"></div>
                </div>
                
                <button type="submit" class="submit-btn">✅ Update Recipe</button>
            </form>
        </div>
    </div>
    
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    function updateRemoveButtons(containerSelector, itemSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;
        const rows = container.querySelectorAll(itemSelector);
        const shouldShow = rows.length > 1;
        rows.forEach(row => {
            const removeBtn = row.querySelector('.remove-btn');
            if (removeBtn) {
                removeBtn.style.display = shouldShow ? 'inline-flex' : 'none';
            }
        });
    }

    // --- Ingredient Actions ---
    const ingredientsContainer = document.getElementById('ingredientsContainer');
    document.getElementById('addIngredientBtn').addEventListener('click', function() {
        const newIngredient = document.createElement('div');
        newIngredient.className = 'dynamic-input-group';
        newIngredient.innerHTML = `<input type="text" class="form-input" name="ingredients[]" placeholder="e.g., 1 cup of flour"><button type="button" class="remove-btn">×</button>`;
        ingredientsContainer.appendChild(newIngredient);
        updateRemoveButtons('#ingredientsContainer', '.dynamic-input-group');
    });

    // --- Step Actions ---
    const stepsContainer = document.getElementById('stepsContainer');
    document.getElementById('addStepBtn').addEventListener('click', function() {
        const newStep = document.createElement('div');
        newStep.className = 'dynamic-input-group';
        newStep.innerHTML = `
            <!-- For new steps, the ID is 'new'. The backend will know to INSERT instead of UPDATE -->
            <input type="hidden" name="step_ids[]" value="new">
            <textarea class="form-textarea" name="steps[]" placeholder="Describe this step..."></textarea>
            <div class="step-image-controls">
                <div class="step-image-preview"></div>
                <!-- A new step has no existing image -->
                <input type="hidden" name="existing_step_images[]" class="existing-step-image-path" value="">
                <button type="button" class="add-step-photo-btn">Add Photo</button>
                <input type="file" name="step_images[]" class="step-image-input" accept="image/*" style="display: none;">
            </div>
            <button type="button" class="remove-btn">×</button>
        `;
        stepsContainer.appendChild(newStep);
        updateRemoveButtons('#stepsContainer', '.dynamic-input-group');
    });

    // --- Universal Event Listener ---
    document.addEventListener('click', function(e) {
        // Handle removing an entire ingredient or step row
        if (e.target.classList.contains('remove-btn')) {
            const group = e.target.closest('.dynamic-input-group');
            const container = group.parentElement;
            group.remove();
            updateRemoveButtons('#' + container.id, '.dynamic-input-group');
        }

        // Handle clicking the "Add/Change Photo" button
        if (e.target.classList.contains('add-step-photo-btn')) {
            e.target.closest('.step-image-controls').querySelector('.step-image-input').click();
        }

        // Handle removing a step's photo
        if (e.target.classList.contains('remove-step-photo-btn')) {
            const controls = e.target.closest('.step-image-controls');
            const preview = controls.querySelector('.step-image-preview');
            const addBtn = controls.querySelector('.add-step-photo-btn');
            const existingPathInput = controls.querySelector('.existing-step-image-path');
            
            preview.innerHTML = ''; // Clear visual preview
            addBtn.style.display = 'block'; // Show "Add Photo" button
            addBtn.textContent = 'Add Photo';
            
            // CRITICAL: Clear the hidden input holding the old path.
            // This tells the backend the image was removed.
            if (existingPathInput) {
                existingPathInput.value = ''; 
            }
        }
    });

    // --- Event listener for step image file inputs ---
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('step-image-input')) {
            const file = e.target.files[0];
            if (file) {
                const controls = e.target.closest('.step-image-controls');
                const preview = controls.querySelector('.step-image-preview');
                const addBtn = controls.querySelector('.add-step-photo-btn');
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.innerHTML = `
                        <img src="${event.target.result}" alt="New step image preview">
                        <button type="button" class="remove-step-photo-btn">×</button>
                    `;
                    addBtn.style.display = 'none'; // Hide "Add Photo" button
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
        }
    }
    toggleOtherField();
    cuisineSelect.addEventListener('change', toggleOtherField);
});
</script>
</body>
</html>
