<?php
session_start();
require_once 'connection.php';

$available_cuisines = [];
$sql_cuisines = "SELECT cuisine_id, cuisine_name FROM cuisines ORDER BY cuisine_name ASC";
if ($result_cuisines = $conn->query($sql_cuisines)) {
    $available_cuisines = $result_cuisines->fetch_all(MYSQLI_ASSOC);
}

// --- 1. PAGINATION SETUP ---
$recipes_per_page = 6;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $recipes_per_page;

// --- 2. FILTERING LOGIC ---
$search_term = $_GET['search'] ?? '';
$cuisine_filter = $_GET['cuisine'] ?? '';
$diet_filter = $_GET['diet'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';

// --- 3. DYNAMICALLY BUILD THE WHERE CLAUSE ---
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search_term)) {
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ? OR EXISTS (SELECT 1 FROM ingredients i WHERE i.recipe_id = r.recipe_id AND i.ingredient_text LIKE ?))";
    $search_like = "%" . $search_term . "%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "sss";
}
if (!empty($cuisine_filter)) {
    $where_clauses[] = "r.cuisine_id = ?";
    $params[] = $cuisine_filter; 
    $types .= "i";
}
if (!empty($diet_filter)) {
    $where_clauses[] = "r.dietary_restrictions = ?";
    $params[] = $diet_filter; $types .= "s";
}
if (!empty($difficulty_filter)) {
    $where_clauses[] = "r.difficulty = ?";
    $params[] = $difficulty_filter; $types .= "s";
}
$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// --- 4. RUN TWO QUERIES FOR PAGINATION ---
$count_sql = "SELECT COUNT(DISTINCT r.recipe_id) FROM recipes r" . $where_sql;
$stmt_count = $conn->prepare($count_sql);
if (!empty($types)) { $stmt_count->bind_param($types, ...$params); }
$stmt_count->execute();
$total_recipes = $stmt_count->get_result()->fetch_row()[0];
$total_pages = ceil($total_recipes / $recipes_per_page);
$stmt_count->close();

$recipes_sql = "SELECT 
                    r.recipe_id, r.title, r.description, c.cuisine_name, r.dietary_restrictions, -- Select cuisine_name
                    r.difficulty, r.prep_time, r.cook_time, u.name AS author_name,
                    AVG(rev.rating) AS avg_rating, COUNT(DISTINCT rev.review_id) AS review_count,
                    (SELECT m.file_path FROM media m WHERE m.recipe_id = r.recipe_id AND m.media_type = 'image' ORDER BY m.media_id ASC LIMIT 1) AS main_image_path
                FROM recipes r
                JOIN user u ON r.user_id = u.user_id
                LEFT JOIN cuisines c ON r.cuisine_id = c.cuisine_id -- Add LEFT JOIN
                LEFT JOIN reviews rev ON r.recipe_id = rev.recipe_id"
                . $where_sql .
                " GROUP BY r.recipe_id ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?";

$params_for_recipes = $params;
$params_for_recipes[] = $recipes_per_page;
$params_for_recipes[] = $offset;
$types_for_recipes = $types . "ii";

$stmt_recipes = $conn->prepare($recipes_sql);
$stmt_recipes->bind_param($types_for_recipes, ...$params_for_recipes);
$stmt_recipes->execute();
$recipes = $stmt_recipes->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_recipes->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Discover Recipes - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .pagination { display: flex; justify-content: center; align-items: center; margin-top: 3rem; gap: 0.5rem; }
        .pagination a, .pagination span { display: inline-block; padding: 0.8rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 500; border: 1px solid #ddd; }
        .pagination a { background-color: #fff; color: #333; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: all 0.2s ease-in-out; }
        .pagination a:hover { background: linear-gradient(45deg, #667eea, #764ba2); color: white; border-color: #667eea; transform: translateY(-2px); }
        .pagination .current-page { background: linear-gradient(45deg, #667eea, #764ba2); color: white; border-color: #667eea; }
        .pagination .disabled { background-color: #f0f2f5; color: #aaa; cursor: not-allowed; }
        .footer {
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
            background-color: #4a4255;
            color: #e9ecef;
        }
        /* Add these new rules inside your existing <style> tag */
        .new-contributor-cta {
            background-color: rgba(255, 255, 255, 0.15); /* A subtle, glassy background */
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-top: 2rem; /* Add space below the hero subtitle */
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
        }
        .new-contributor-cta h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.6rem;
            color: white;
            font-weight: 600;
        }
        .new-contributor-cta p {
            margin: 0 0 1.2rem 0;
            color: white;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .new-contributor-cta a.cta-button {
            background-color: #fff;
            color: #67597A; /* Your theme's purple color */
            padding: 0.8rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: all 0.2s ease-in-out;
        }
        .new-contributor-cta a.cta-button:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .nav-button {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white !important; /* Use !important to override .nav-link color if needed */
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            margin-left: 0.5rem; /* Adds space from the Login link */
            font-weight: 500;
        }
        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">🍳 CookTogether</a>
            <div class="nav-links">
                <a class="nav-link active" href="index.php">Home</a>
                <?php if(isset($_SESSION["loggedin"]) && in_array($_SESSION["role"], ['chef', 'student'])): ?>
                    <a class="nav-link" href="upload.php">Upload Recipe</a>
                <?php endif; ?>
                <a class="nav-link" href="about.php">About Us</a>
                <?php if(isset($_SESSION["loggedin"])): ?>
                    <a class="nav-link" href="logout.php">Logout</a>
                    <div class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['name']); ?>"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
                <?php else: ?>
                    <!-- This is the modified block -->
                    <a class="nav-link" href="login.php">Login</a>
                    
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="hero-section">
            <h1 class="hero-title">Discover Amazing Recipes</h1>
            <p class="hero-subtitle">Share your culinary creations and explore dishes from around the world</p>
             <div class="new-contributor-cta">
                <h3>Share Your Culinary Passion</h3>
                <p>Are you a Chef or a Culinary Student? Register now to upload your recipes and join our community!</p>
                <a href="register.php" class="cta-button">Become a Contributor</a>
            </div>
            <br>
            <form action="index.php" method="GET">
                <div class="search-bar" style="position: relative; display: flex; align-items: center;">
                    <input type="text" name="search" id="searchInput" class="search-input" placeholder="Search or use voice..." value="<?php echo htmlspecialchars($search_term); ?>" style="padding-right: 120px;">
                    <div class="voice-controls" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); display: flex; align-items: center; gap: 10px;">
                        <select id="languageSelector" title="Select Voice Language" style="border: 1px solid #ccc; border-radius: 5px; padding: 5px; font-size: 0.9rem;">
                            <option value="en-US">English</option>
                            <option value="ms-MY">Malay</option>
                        </select>
                        <button type="button" id="voiceSearchBtn" title="Search by Voice" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #555; padding: 0;">
                            <i class="fas fa-microphone"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-section">
                    <h3 class="filter-title"><i class="fas fa-filter"></i> Filter Recipes</h3>
                    <div class="filter-row">
                        <select name="cuisine" class="filter-select">
                            <option value="">All Cuisines</option>
                            <?php foreach ($available_cuisines as $cuisine): ?>
                                <?php
                                    $cuisine_id = $cuisine['cuisine_id'];
                                    $cuisine_name = htmlspecialchars($cuisine['cuisine_name']);
                                    $selected = ($cuisine_filter == $cuisine_id) ? 'selected' : '';
                                ?>
                                <option value="<?php echo $cuisine_id; ?>" <?php echo $selected; ?>>
                                    <?php echo ucfirst($cuisine_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="diet" class="filter-select"><option value="">All Diets</option><option value="vegetarian" <?php if($diet_filter == 'vegetarian') echo 'selected'; ?>>🥬 Vegetarian</option><option value="vegan" <?php if($diet_filter == 'vegan') echo 'selected'; ?>>🌱 Vegan</option></select>
                        <select name="difficulty" class="filter-select"><option value="">All Levels</option><option value="easy" <?php if($difficulty_filter == 'easy') echo 'selected'; ?>>😊 Easy</option><option value="medium" <?php if($difficulty_filter == 'medium') echo 'selected'; ?>>🤔 Medium</option><option value="hard" <?php if($difficulty_filter == 'hard') echo 'selected'; ?>>😤 Hard</option></select>
                        <button type="submit" class="view-recipe-btn" style="height: 100%;">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="recipes-grid">
            <?php if (empty($recipes)): ?>
                <div class="no-recipes-message"><h2>No recipes match your criteria!</h2><p>Try adjusting your search or filters.</p></div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <div class="recipe-card">
                        <a href="recipe-details.php?id=<?php echo $recipe['recipe_id']; ?>"><img src="<?php echo !empty($recipe['main_image_path']) ? htmlspecialchars($recipe['main_image_path']) : 'img/placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-image"></a>
                        <div class="recipe-content">
                            <div class="recipe-rating"><?php $rating = round($recipe['avg_rating'] ?? 0); for ($i = 1; $i <= 5; $i++): echo $i <= $rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; endfor; ?><span>(<?php echo $recipe['review_count']; ?>)</span></div>
                            <h3 class="recipe-title"><a href="recipe-details.php?id=<?php echo $recipe['recipe_id']; ?>"><?php echo htmlspecialchars($recipe['title']); ?></a></h3>
                            <div class="recipe-meta">
                                <?php if (!empty($recipe['cuisine_name'])): ?><span class="meta-tag"><?php echo ucfirst(htmlspecialchars($recipe['cuisine_name'])); ?></span><?php endif; ?>
                                <?php if (!empty($recipe['dietary_restrictions'])): ?><span class="meta-tag"><?php echo ucfirst(htmlspecialchars($recipe['dietary_restrictions'])); ?></span><?php endif; ?>
                                <span class="meta-tag"><?php echo htmlspecialchars($recipe['prep_time'] + $recipe['cook_time']); ?> min</span>
                                <span class="meta-tag"><?php echo ucfirst(htmlspecialchars($recipe['difficulty'])); ?></span>
                            </div>
                            <p class="recipe-description"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100)) . '...'; ?></p>
                            <a href="recipe-details-viewer.php?id=<?php echo $recipe['recipe_id']; ?>" class="view-recipe-btn">View Recipe</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php $query_string = http_build_query(['search' => $search_term, 'cuisine' => $cuisine_filter, 'diet' => $diet_filter, 'difficulty' => $difficulty_filter]); ?>
                <?php if ($current_page > 1): ?><a href="?page=<?php echo $current_page - 1; ?>&<?php echo $query_string; ?>">« Previous</a><?php else: ?><span class="disabled">« Previous</span><?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?><a href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>" class="<?php if ($i == $current_page) echo 'current-page'; ?>"><?php echo $i; ?></a><?php endfor; ?>
                <?php if ($current_page < $total_pages): ?><a href="?page=<?php echo $current_page + 1; ?>&<?php echo $query_string; ?>">Next »</a><?php else: ?><span class="disabled">Next »</span><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- SCRIPT FOR BILINGUAL VOICE SEARCH AI -->
    <script>
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = SpeechRecognition ? new SpeechRecognition() : null;

        const voiceSearchBtn = document.getElementById('voiceSearchBtn');
        const searchInput = document.getElementById('searchInput');
        const languageSelector = document.getElementById('languageSelector');

        if (recognition) {
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            voiceSearchBtn.addEventListener('click', () => {
                // Set the language from the dropdown before starting
                const selectedLanguage = languageSelector.value;
                recognition.lang = selectedLanguage; 
                
                try {
                    recognition.start();
                    voiceSearchBtn.style.color = '#dc3545'; // Red to indicate listening
                    searchInput.placeholder = 'Listening...';
                } catch (e) {
                    console.error("Error starting recognition:", e);
                    alert("Could not start voice recognition. It might be already active.");
                }
            });

            recognition.onresult = function(event) {
                const speechResult = event.results[0][0].transcript;
                searchInput.value = speechResult;
            };

            recognition.onspeechend = function() {
                recognition.stop();
            };

            recognition.onend = function() { // This event fires when recognition stops for any reason
                voiceSearchBtn.style.color = '#555'; // Revert color
                searchInput.placeholder = "Search or use voice...";
            }

            recognition.onerror = function(event) {
                console.error('Speech recognition error:', event.error);
                let errorMessage = 'An error occurred in speech recognition: ' + event.error;
                if (event.error === 'no-speech') {
                    errorMessage = "Sorry, I didn't hear anything. Please try again.";
                } else if (event.error === 'language-not-supported') {
                    errorMessage = "Sorry, the selected language is not supported by your browser.";
                } else if (event.error === 'not-allowed') {
                    errorMessage = "Permission to use the microphone was denied. Please allow it in your browser settings.";
                }
                alert(errorMessage);
            };

        } else {
            const voiceControls = document.querySelector('.voice-controls');
            if (voiceControls) {
                voiceControls.style.display = 'none';
            }
            console.error("Speech Recognition API not available in this browser.");
        }
    </script>

    <footer class="footer">
        <p>© <?php echo date("Y"); ?> CookTogether. All Rights Reserved.</p>
    </footer>
</body>
</html>
