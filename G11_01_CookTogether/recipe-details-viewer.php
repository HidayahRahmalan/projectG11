<?php
// Start the session
session_start();
// Include the database connection
require_once 'connection.php';

// --- 1. GET AND VALIDATE THE RECIPE ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT) || $_GET['id'] <= 0) {
    die("ERROR: A valid recipe ID is required.");
}
$recipe_id = $_GET['id'];

// --- 2. FETCH THE MAIN RECIPE DETAILS ---
$recipe = null;
$sql_recipe = "SELECT 
                    r.recipe_id, r.title, r.description, r.prep_time, r.cook_time, r.difficulty, 
                    r.user_id AS author_id, 
                    u.name AS author_name, 
                    u.role AS author_role, 
                    u.institution AS author_institution, 
                    u.workplace AS author_workplace
               FROM recipes r 
               JOIN user u ON r.user_id = u.user_id 
               WHERE r.recipe_id = ?";
if ($stmt_recipe = $conn->prepare($sql_recipe)) {
    $stmt_recipe->bind_param("i", $recipe_id);
    $stmt_recipe->execute();
    $result = $stmt_recipe->get_result();
    if ($result->num_rows == 1) {
        $recipe = $result->fetch_assoc();
    } else {
        die("ERROR: Recipe not found.");
    }
    $stmt_recipe->close();
}

// --- 3. FETCH ALL RECIPE MEDIA ---
$recipe_media = [];
$sql_recipe_media = "SELECT file_path, media_type FROM media WHERE recipe_id = ? ORDER BY media_id ASC";
if ($stmt_recipe_media = $conn->prepare($sql_recipe_media)) {
    $stmt_recipe_media->bind_param("i", $recipe_id);
    $stmt_recipe_media->execute();
    $recipe_media = $stmt_recipe_media->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_recipe_media->close();
}

// --- 4. FETCH THE INGREDIENTS ---
$ingredients = [];
$sql_ingredients = "SELECT ingredient_text FROM ingredients WHERE recipe_id = ? ORDER BY sort_order ASC";
if($stmt_ingredients = $conn->prepare($sql_ingredients)) {
    $stmt_ingredients->bind_param("i", $recipe_id);
    $stmt_ingredients->execute();
    $ingredients = $stmt_ingredients->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_ingredients->close();
}

// --- 5. FETCH THE COOKING STEPS ---
$steps = [];
$sql_steps = "SELECT step_text FROM steps WHERE recipe_id = ? ORDER BY sort_order ASC";
if($stmt_steps = $conn->prepare($sql_steps)) {
    $stmt_steps->bind_param("i", $recipe_id);
    $stmt_steps->execute();
    $steps = $stmt_steps->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_steps->close();
}

// --- 6. FETCH REVIEWS AND THEIR MEDIA (CORRECTED + ADDED REVIEWER_ID) ---
$reviews = [];

$sql_reviews = "SELECT rev.review_id, rev.rating, rev.comment, rev.created_at, u.name AS reviewer_name, rev.user_id AS reviewer_id 
                FROM reviews rev 
                JOIN user u ON rev.user_id = u.user_id 
                WHERE rev.recipe_id = ? 
                ORDER BY rev.created_at DESC";

if ($stmt_reviews = $conn->prepare($sql_reviews)) {
    $stmt_reviews->bind_param("i", $recipe_id);
    $stmt_reviews->execute();
    $reviews_result = $stmt_reviews->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_reviews->close();

    if (!empty($reviews_result)) {
        $reviews_by_id = [];
        foreach ($reviews_result as $review) {
            $review['media'] = []; 
            $reviews_by_id[$review['review_id']] = $review;
        }
        $review_ids = array_keys($reviews_by_id);
        $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
        $types = str_repeat('i', count($review_ids));
        $sql_media = "SELECT review_id, file_path, media_type FROM review_photos WHERE review_id IN ($placeholders)";
        if ($stmt_media = $conn->prepare($sql_media)) {
            $stmt_media->bind_param($types, ...$review_ids);
            $stmt_media->execute();
            $media_result = $stmt_media->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_media->close();
            foreach ($media_result as $media_item) {
                if (isset($reviews_by_id[$media_item['review_id']])) {
                    $reviews_by_id[$media_item['review_id']]['media'][] = $media_item;
                }
            }
        }
        $reviews = array_values($reviews_by_id);
    }
}

$user_has_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $sql_check_review = "SELECT 1 FROM reviews WHERE user_id = ? AND recipe_id = ? LIMIT 1";
    if ($stmt_check = $conn->prepare($sql_check_review)) {
        $stmt_check->bind_param("ii", $_SESSION['user_id'], $recipe_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $user_has_reviewed = true;
        }
        $stmt_check->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($recipe['title']); ?> - CookTogether</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style.css" />
    <style>
        .recipe-details-container { max-width: 900px; margin: 2rem auto; background-color: #fff; padding: 2rem 3rem; border-radius: 20px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); }
        .recipe-header .recipe-title { font-size: 2.8rem; font-weight: bold; margin-bottom: 0.5rem; }
        .recipe-header .recipe-author { font-size: 1.1rem; color: #555; margin-bottom: 1.5rem; }
        .recipe-header .recipe-description { font-size: 1.1rem; line-height: 1.7; color: #333; }
        .media-gallery { margin: 2rem 0; }
        .media-gallery img, .media-gallery video { width: 100%; height: auto; max-height: 500px; object-fit: cover; border-radius: 15px; margin-bottom: 1rem; }
        .recipe-info-bar { display: flex; justify-content: space-around; background-color: #f8f9fa; padding: 1rem; border-radius: 12px; text-align: center; margin-bottom: 2.5rem; }
        .info-item h4 { margin: 0 0 0.25rem 0; color: #888; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-item p { margin: 0; font-size: 1.1rem; font-weight: 600; }
        .recipe-body { display: grid; grid-template-columns: 1fr 2fr; gap: 3rem; margin-bottom: 3rem; }
        .section-title { font-size: 1.75rem; font-weight: bold; margin-bottom: 1.5rem; border-bottom: 3px solid #667eea; padding-bottom: 0.5rem; }
        .ingredients-list { list-style: none; padding: 0; }
        .ingredients-list li { padding: 0.75rem 0; border-bottom: 1px solid #eee; font-size: 1.1rem; }
        .steps-list { padding-left: 1.5rem; }
        .steps-list li { margin-bottom: 1.5rem; font-size: 1.1rem; line-height: 1.8; }
        .reviews-section { margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #eee; }
        .review-item { margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid #eee; }
        .review-item:last-child { border-bottom: none; }
        .review-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
        .reviewer-avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(45deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; flex-shrink: 0; }
        .reviewer-name { font-weight: bold; font-size: 1.1rem; }
        .review-rating .fa-star { color: #ffc107; }
        .review-comment { margin: 1rem 0; line-height: 1.7; }
        .review-media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .review-media-grid img, .review-media-grid video { width: 100%; border-radius: 8px; }
        #review-form { background-color: #f8f9fa; padding: 2rem; border-radius: 15px; margin-bottom: 2.5rem; }
        #review-form .form-group { margin-bottom: 1rem; }
        #review-form .form-label { font-weight: bold; margin-bottom: 0.5rem; display: block; }
        .auth-required-message { text-align: center; padding: 2rem; background-color: #f8f9fa; border-radius: 15px; }
        @media (max-width: 768px) { .recipe-body { grid-template-columns: 1fr; } }
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
   
    <div id="submit-success-notification" style="display: none; position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background-color: #28a745; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; font-size: 1.1rem;">
        <i class="fas fa-check-circle"></i> Your review has been submitted successfully!
    </div>
    <div id="edit-success-notification" style="display: none; position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background-color: #007bff; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; font-size: 1.1rem;">
        <i class="fas fa-info-circle"></i> Your review has been updated successfully!
    </div>
    <div id="delete-success-notification" style="display: none; position: fixed; top: 80px; left: 50%; transform: translateX(-50%); background-color: #dc3545; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); z-index: 1000; font-size: 1.1rem;">
        <i class="fas fa-trash-alt"></i> Your review has been deleted.
    </div>
   


    <nav class="navbar">
        <div class="nav-container">
          <a href="index.php" class="logo">🍳 CookTogether</a>
          <div class="nav-links">
            <a class="nav-link" href="index.php">Home</a>
            <a class="nav-link" href="about.php">About Us</a>
            <?php if (isset($_SESSION["loggedin"]) && in_array($_SESSION["role"], ['chef', 'student'])): ?><a class="nav-link" href="upload.php">Upload Recipe</a><?php endif; ?>
            <?php if (isset($_SESSION["loggedin"])): ?><a class="nav-link" href="logout.php">Logout</a>
            <div class="user-avatar" title="<?php echo htmlspecialchars($_SESSION['name']); ?>"><?php echo strtoupper(substr($_SESSION["name"], 0, 1)); ?></div>
            <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    </div>
            <?php else: ?>
                <a class="nav-link" href="login.php">Login</a><?php endif; ?>
          </div>
        </div>
    </nav>
    <div class="recipe-details-container">
        <header class="recipe-header">
            <h1 class="recipe-title"><?php echo htmlspecialchars($recipe['title']); ?></h1>
            <p class="recipe-author">
                <?php
                    // Start building the author byline string
                    $author_byline = "By ";

                    // Add the role if they are a chef or student
                    if ($recipe['author_role'] === 'chef') {
                        $author_byline .= "Chef ";
                    } elseif ($recipe['author_role'] === 'student') {
                        $author_byline .= "Student ";
                    }

                    // Add the author's name
                    $author_byline .= htmlspecialchars($recipe['author_name']);

                    // Conditionally add the workplace or institution
                    if ($recipe['author_role'] === 'chef' && !empty($recipe['author_workplace'])) {
                        $author_byline .= " from " . htmlspecialchars($recipe['author_workplace']);
                    } elseif ($recipe['author_role'] === 'student' && !empty($recipe['author_institution'])) {
                        $author_byline .= " from " . htmlspecialchars($recipe['author_institution']);
                    }

                    // Echo the final, complete byline
                    echo $author_byline;
                ?>
            </p>
            <p class="recipe-description"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
        </header>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['author_id']): ?><div class="author-actions" style="margin-bottom: 2rem; padding: 1rem; background: #f0f2f5; border-radius: 10px; display: flex; align-items: center; gap: 1rem; border: 1px solid #e1e8ed;"><h4 style="margin: 0; color: #555;">Author Actions:</h4><a href="edit-recipe.php?id=<?php echo $recipe['recipe_id']; ?>" class="submit-btn" style="background: #ffc107; color: #212529; padding: 0.5rem 1.5rem; font-size: 0.9rem; width: auto; margin: 0;">Edit Recipe</a><form action="delete-recipe.php" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this recipe? This action cannot be undone.');" style="margin: 0;"><input type="hidden" name="recipe_id" value="<?php echo $recipe['recipe_id']; ?>"><button type="submit" class="submit-btn" style="background: #dc3545; padding: 0.5rem 1.5rem; font-size: 0.9rem; width: auto; margin: 0;">Delete Recipe</button></form></div><?php endif; ?>

        <div class="media-gallery"><?php foreach ($recipe_media as $media): ?><?php if ($media['media_type'] == 'image'): ?><img src="<?php echo htmlspecialchars($media['file_path']); ?>" alt="Recipe media for <?php echo htmlspecialchars($recipe['title']); ?>" class="recipe-main-image"><?php elseif ($media['media_type'] == 'video'): ?><div class="video-section"><video controls width="100%"><source src="<?php echo htmlspecialchars($media['file_path']); ?>" type="video/mp4">Your browser does not support the video tag.</video></div><?php endif; ?><?php endforeach; ?></div>

        <div class="recipe-info-bar"><div class="info-item"><h4>Prep Time</h4><p><?php echo htmlspecialchars($recipe['prep_time']); ?> min</p></div><div class="info-item"><h4>Cook Time</h4><p><?php echo htmlspecialchars($recipe['cook_time']); ?> min</p></div><div class="info-item"><h4>Total Time</h4><p><?php echo htmlspecialchars($recipe['prep_time'] + $recipe['cook_time']); ?> min</p></div><div class="info-item"><h4>Difficulty</h4><p><?php echo ucfirst(htmlspecialchars($recipe['difficulty'])); ?></p></div></div>

        <div class="recipe-body"><div class="ingredients-section"><h2 class="section-title">Ingredients</h2><ul class="ingredients-list"><?php foreach ($ingredients as $ingredient): ?><li><?php echo htmlspecialchars($ingredient['ingredient_text']); ?></li><?php endforeach; ?></ul></div><div class="steps-section"><h2 class="section-title">Instructions</h2><ol class="steps-list"><?php foreach ($steps as $step): ?><li><?php echo nl2br(htmlspecialchars($step['step_text'])); ?></li><?php endforeach; ?></ol></div></div>
        
        <section class="reviews-section">
            <h2 class="section-title">Reviews & Comments (<?php echo count($reviews); ?>)</h2>
            
                <?php if (isset($_SESSION["loggedin"]) && $_SESSION['role'] === 'viewer' && !$user_has_reviewed): ?>
                        <form id="review-form" action="submit_review.php" method="post" enctype="multipart/form-data">
                            <!-- ... Kandungan borang tidak berubah ... -->
                            <h3>Leave Your Review</h3><input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>"><div class="form-group"><label for="rating" class="form-label">Overall Rating *</label><select name="rating" id="rating" class="form-input" required><option value="">-- Select a Rating --</option><option value="5">★★★★★ Excellent</option><option value="4">★★★★☆ Great</option><option value="3">★★★☆☆ Good</option><option value="2">★★☆☆☆ Okay</option><option value="1">★☆☆☆☆ Poor</option></select></div><div class="form-group"><label for="comment" class="form-label" style="display: flex; justify-content: space-between; align-items: center;"><span>Your Comment</span><select id="commentLanguageSelector" title="Select voice language" style="border: 1px solid #ccc; border-radius: 5px; padding: 3px 5px; font-size: 0.8rem;"><option value="ms-MY">Malay</option><option value="en-US">English</option></select></label><div style="position: relative;"><textarea name="comment" id="comment" class="form-textarea" rows="4" placeholder="Share your experience..."></textarea><button type="button" id="voiceCommentBtn" title="Use voice to type" style="position: absolute; right: 10px; bottom: 10px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #555;"><i class="fas fa-microphone"></i></button></div><p style="font-size: 0.85rem; color: #6c757d; margin-top: 0.5rem; font-weight: normal;">Tip: You can type or click the <i class="fas fa-microphone" aria-hidden="true"></i> icon to use your voice.</p></div><div class="form-grid" style="grid-template-columns: 1fr 1fr; gap: 1rem;"><div class="form-group"><label for="reviewPhotos" class="form-label">Add Photos</label><input type="file" name="reviewPhotos[]" id="reviewPhotos" class="form-input" multiple accept="image/*"></div><div class="form-group"><label for="reviewVideo" class="form-label">Add a Video</label><input type="file" name="reviewVideo" id="reviewVideo" class="form-input" accept="video/*"></div></div><button type="submit" class="submit-btn" style="margin-top: 1rem;">Submit Review</button>
                        </form>
                    <?php elseif ($user_has_reviewed): ?>
                        <div class="auth-required-message"><h4>You have already submitted a review for this recipe.</h4></div>
                    <?php elseif (isset($_SESSION["loggedin"]) && $_SESSION['role'] !== 'viewer'): ?>
                        <div class="auth-required-message"><h4>Only viewers can leave a review.</h4></div>
                    <?php else: ?>
                        <div class="auth-required-message"><h4><a href="login.php">Log in</a> or <a href="register_viewer.php">register</a> to leave a review.</h4></div>
                    <?php endif; ?>
                
            <div class="existing-reviews">
                <?php if (empty($reviews)): ?>
                    <p>Be the first to leave a review!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div class="reviewer-avatar"><?php echo strtoupper(substr($review['reviewer_name'], 0, 1)); ?></div>
                                <div>
                                    <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                                    <div class="review-rating"><?php for ($i = 0; $i < 5; $i++): ?><i class="fa-star <?php echo ($i < $review['rating']) ? 'fas' : 'far'; ?>"></i><?php endfor; ?></div>
                                </div>
                            </div>
                            
                            <!-- BUTANG EDIT/DELETE -->
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['reviewer_id']): ?>
                            <div class="review-actions" style="display: flex; gap: 0.75rem;">
                                <a href="edit_review.php?id=<?php echo $review['review_id']; ?>" title="Edit Review" style="color: #555; font-size: 0.9rem; text-decoration: none;"><i class="fas fa-edit"></i></a>
                                <form action="delete_review.php" method="POST" onsubmit="return confirm('Are you sure you want to delete your review?');" style="margin: 0; padding: 0;">
                                    <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe_id; ?>">
                                    <button type="submit" title="Delete Review" style="background: none; border: none; cursor: pointer; color: #dc3545; font-size: 0.9rem; padding: 0;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if(!empty($review['comment'])): ?><p class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p><?php endif; ?>
                        
                        <?php if (!empty($review['media'])): ?>
                            <div class="review-media-grid">
                                <?php foreach ($review['media'] as $media_item): ?>
                                    <?php if ($media_item['media_type'] == 'image'): ?>
                                        <img src="<?php echo htmlspecialchars($media_item['file_path']); ?>" alt="Review Media">
                                    <?php elseif ($media_item['media_type'] == 'video'): ?>
                                        <div style="margin-top: 1rem; grid-column: 1 / -1;">
                                            <video controls width="100%" style="border-radius:8px; max-width: 100%;"><source src="<?php echo htmlspecialchars($media_item['file_path']); ?>" type="video/mp4">Your browser does not support this video.</video>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <script>
        // Skrip voice recognition
        const reviewForm = document.getElementById('review-form');
        if (reviewForm) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = SpeechRecognition ? new SpeechRecognition() : null;
            const voiceCommentBtn = document.getElementById('voiceCommentBtn');
            const commentTextarea = document.getElementById('comment');
            const commentLanguageSelector = document.getElementById('commentLanguageSelector');
            if (recognition) {
                recognition.continuous = true;
                recognition.interimResults = true;
                let finalTranscript = '';
                voiceCommentBtn.addEventListener('click', () => {
                    if (voiceCommentBtn.classList.contains('listening')) {
                        recognition.stop();
                    } else {
                        const selectedLang = commentLanguageSelector.value;
                        recognition.lang = selectedLang;
                        finalTranscript = commentTextarea.value;
                        if (finalTranscript.length > 0 && !finalTranscript.endsWith(' ')) { finalTranscript += ' '; }
                        recognition.start();
                    }
                });
                recognition.onstart = () => { voiceCommentBtn.classList.add('listening'); voiceCommentBtn.style.color = '#dc3545'; commentTextarea.placeholder = 'Listening... Speak now.'; };
                recognition.onresult = (event) => { let interimTranscript = ''; for (let i = event.resultIndex; i < event.results.length; ++i) { if (event.results[i].isFinal) { finalTranscript += event.results[i][0].transcript; } else { interimTranscript += event.results[i][0].transcript; } } commentTextarea.value = finalTranscript + interimTranscript; };
                recognition.onend = () => { voiceCommentBtn.classList.remove('listening'); voiceCommentBtn.style.color = '#555'; commentTextarea.placeholder = 'Share your experience... or use the mic!'; };
                recognition.onerror = (event) => { console.error('Speech recognition error:', event.error); alert('Error in voice recognition: ' + event.error); };
            } else {
                voiceCommentBtn.style.display = 'none';
                console.error("Speech Recognition API not supported in this browser.");
            }
        }

        // Skrip untuk notifikasi kejayaan
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            
            function showNotification(notificationId, duration = 5000) {
                const notification = document.getElementById(notificationId);
                if (!notification) return;

                notification.style.display = 'block';
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s ease';
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        notification.style.display = 'none';
                        notification.style.opacity = '1';
                    }, 500);
                }, duration);
            }

            if (urlParams.has('review_success')) {
                showNotification('submit-success-notification');
            } else if (urlParams.has('edit_success')) {
                showNotification('edit-success-notification');
            } else if (urlParams.has('delete_success')) {
                showNotification('delete-success-notification');
            }

            if (urlParams.has('review_success') || urlParams.has('edit_success') || urlParams.has('delete_success')) {
                const currentId = urlParams.get('id');
                const newUrl = window.location.pathname + (currentId ? '?id=' + currentId : '');
                window.history.replaceState({ path: newUrl }, '', newUrl);
            }
        });
    </script>
</body>
</html>


