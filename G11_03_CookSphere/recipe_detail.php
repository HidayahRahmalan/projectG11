<?php
include 'header.php';
require 'connection.php';

$recipe_id = isset($_GET['recipe_id']) ? intval($_GET['recipe_id']) : 0;
if ($recipe_id <= 0) {
    echo "<div class='text-center text-red-500 font-bold mt-10'>Invalid recipe ID.</div>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recipe_id'])) {
    $recipe_id = intval($_POST['recipe_id']);

    // Delete from recipe media first (if applicable)
    $stmt = $conn->prepare("DELETE FROM media WHERE RecipeID = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $stmt->close();

    // Delete from steps (if applicable)
    $stmt = $conn->prepare("DELETE FROM step WHERE RecipeID = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the recipe
    $stmt = $conn->prepare("DELETE FROM recipe WHERE RecipeID = ?");
    $stmt->bind_param("i", $recipe_id);
    if ($stmt->execute()) {
        header("Location: my_recipes.php?deleted=success");
        exit();
    } else {
        echo "Error deleting recipe.";
    }
    $stmt->close();
}
// Fetch recipe and user info
$stmt = $conn->prepare(
    "SELECT r.*, u.FullName 
     FROM recipe r 
     JOIN user u ON r.UserID = u.UserID 
     WHERE r.RecipeID = ?"
);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$recipe) {
    echo "<div class='text-center text-red-500 font-bold mt-10'>Recipe not found.</div>";
    exit();
}

// Fetch media attached directly to recipe (StepID IS NULL)
$stmt = $conn->prepare(
    "SELECT * FROM media WHERE RecipeID = ? AND StepID IS NULL"
);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$recipe_media = $stmt->get_result();
$stmt->close();

// Fetch steps (if any)
$stmt = $conn->prepare(
    "SELECT * FROM step WHERE RecipeID = ? ORDER BY StepNumber ASC"
);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$steps = $stmt->get_result();
$stmt->close();

// Fetch media for steps (group by StepID)
$step_media = [];
$stmt = $conn->prepare(
    "SELECT * FROM media WHERE RecipeID = ? AND StepID IS NOT NULL"
);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$res = $stmt->get_result();
while ($media = $res->fetch_assoc()) {
    $step_media[$media['StepID']][] = $media;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_text'], $_SESSION['userid'])) {
    $comment_text = trim($_POST['comment_text']);
    $user_id = $_SESSION['userid'];
    $video_path = null;

    if (!empty($comment_text)) {
        // Handle optional video upload
        if (!empty($_FILES['video_review']['name'])) {
            $target_dir = "uploads/videos/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $video_name = basename($_FILES['video_review']['name']);
            $video_ext = pathinfo($video_name, PATHINFO_EXTENSION);
            $allowed_exts = ['mp4', 'mov', 'avi', 'webm'];

            if (in_array(strtolower($video_ext), $allowed_exts)) {
                $video_path = $target_dir . time() . '_' . uniqid() . '.' . $video_ext;
                move_uploaded_file($_FILES['video_review']['tmp_name'], $video_path);
            }
        }

        $stmt = $conn->prepare("INSERT INTO comment (RecipeID, UserID, Text, VideoReviewPath) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $recipe_id, $user_id, $comment_text, $video_path);
        $stmt->execute();
        $stmt->close();

        header("Location: recipe_detail.php?recipe_id=$recipe_id");
        exit();
    }
}
$stmtComm = $conn->prepare(
    "SELECT c.CommentID, c.Text, c.CommentDate, c.VideoReviewPath, u.FullName, u.Role, u.UserID
    FROM comment c 
    JOIN user u ON c.UserID = u.UserID 
    WHERE c.RecipeID = ? 
    ORDER BY c.CommentDate DESC"
);
$stmtComm->bind_param("i", $recipe_id);
$stmtComm->execute();
$comments = $stmtComm->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment_id'])) {
    $delete_comment_id = intval($_POST['delete_comment_id']);
    $user_id = $_SESSION['userid'];
    $user_role = $_SESSION['role'];

    // Get the comment details
    $stmt = $conn->prepare("SELECT c.CommentID, c.UserID AS CommentUserID, u.Role AS CommentUserRole, r.UserID AS RecipeOwnerID 
                            FROM comment c 
                            JOIN recipe r ON c.RecipeID = r.RecipeID 
                            JOIN user u ON c.UserID = u.UserID 
                            WHERE c.CommentID = ?");
    $stmt->bind_param("i", $delete_comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comment = $result->fetch_assoc();
    $stmt->close();

    if ($comment) {
        $canDelete = false;

        if ($user_id == $comment['CommentUserID']) {
            // Can delete own comment
            $canDelete = true;
        } elseif ($user_role === 'chef' && $user_id == $comment['RecipeOwnerID'] && $comment['CommentUserRole'] === 'student') {
            // Chef can delete a student’s comment on their own recipe
            $canDelete = true;
        }

        if ($canDelete) {
            $stmt = $conn->prepare("DELETE FROM comment WHERE CommentID = ?");
            $stmt->bind_param("i", $delete_comment_id);
            $stmt->execute();
            $stmt->close();

            header("Location: recipe_detail.php?recipe_id=$recipe_id");
            exit();
        } else {
            echo "<script>alert('Unauthorized to delete this comment.');</script>";
        }
    }
}


$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($recipe['Title']) ?> - Recipe Details | CookSphere</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        <div class="glass w-full max-w-3xl p-8 mt-8">
            <div class="flex justify-between items-center mb-6">
                <a href="javascript:history.back()" class="text-yellow-700 hover:underline text-base flex items-center gap-1">&larr; Back</a>
                <?php if (isset($_SESSION['userid']) && $_SESSION['userid'] == $recipe['UserID']): ?> 
                    <div class="flex items-center gap-4">
                        <a href="edit_recipe.php?recipe_id=<?= $recipe['RecipeID'] ?>" class="text-yellow-600 hover:text-yellow-800 text-sm flex items-center gap-1" title="Edit">
                            <i class="fas fa-edit fa-lg"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this recipe?');" class="inline-block m-0 p-0">
                            <input type="hidden" name="recipe_id" value="<?= $recipe['RecipeID'] ?>">
                            <button type="submit" class="text-yellow-600 hover:text-yellow-800 text-sm flex items-center gap-1">
                                <i class="fas fa-trash-alt fa-lg"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <h1 class="text-3xl font-bold mb-2 text-gray-800"><?= htmlspecialchars($recipe['Title']) ?></h1>
            <div class="mb-3 flex flex-wrap gap-4 items-center">
                <span class="text-gray-600 text-sm">By <b><?= htmlspecialchars($recipe['FullName']) ?></b></span>
                <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-xl text-xs"><?= htmlspecialchars($recipe['CuisineType']) ?></span>
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-xl text-xs"><?= htmlspecialchars($recipe['DietaryType']) ?></span>
                <span class="text-xs text-gray-400">Uploaded: <?= date("d M Y", strtotime($recipe['UploadDate'])) ?></span>
            </div>
            <div class="mb-4">
                <h2 class="font-semibold text-lg text-gray-700 mb-1">Description</h2>
                <p class="text-gray-700"><?= nl2br(htmlspecialchars($recipe['Description'])) ?></p>
            </div>
            
            <?php if ($recipe_media && $recipe_media->num_rows > 0): ?>
                <div class="mb-6">
                    <h2 class="font-semibold text-lg text-gray-700 mb-2">Recipe Images/Videos</h2>
                    <div class="flex flex-wrap gap-4">
                        <?php while ($media = $recipe_media->fetch_assoc()): ?>
                            <div class="media-block">
                                <?php if ($media['MediaType'] == 'image'): ?>
                                    <img src="<?= htmlspecialchars($media['MediaPath']) ?>" alt="Recipe Image" class="media-thumb shadow">
                                <?php elseif ($media['MediaType'] == 'video'): ?>
                                    <video controls class="media-thumb shadow">
                                        <source src="<?= htmlspecialchars($media['MediaPath']) ?>">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($steps && $steps->num_rows > 0): ?>
                <div class="mt-8">
                    <h2 class="font-bold text-xl text-gray-700 mb-4">Steps</h2>
                    <ol class="list-decimal ml-6 space-y-8">
                        <?php while ($step = $steps->fetch_assoc()): ?>
                        <li>
                            <div class="mb-2 font-semibold text-gray-800">Step <?= intval($step['StepNumber']) ?></div>
                            <div class="mb-3"><?= nl2br(htmlspecialchars($step['Instruction'])) ?></div>
                            <?php if (!empty($step_media[$step['StepID']])): ?>
                                <div class="flex flex-wrap gap-4 mb-2">
                                    <?php foreach ($step_media[$step['StepID']] as $media): ?>
                                        <div class="media-block">
                                            <?php if ($media['MediaType'] == 'image'): ?>
                                                <img src="<?= htmlspecialchars($media['MediaPath']) ?>" alt="Step Image" class="media-thumb shadow">
                                            <?php elseif ($media['MediaType'] == 'video'): ?>
                                                <video controls class="media-thumb shadow">
                                                    <source src="<?= htmlspecialchars($media['MediaPath']) ?>">
                                                    Your browser does not support the video tag.
                                                </video>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </li>
                        <?php endwhile; ?>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if ($comments->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($comment = $comments->fetch_assoc()): ?>
                        <div class="p-4 bg-gray-100 rounded-lg">
                            <div class="text-sm text-gray-600 mb-1">
                                <strong><?= htmlspecialchars($comment['FullName']) ?> (<?= htmlspecialchars($comment['Role']) ?>)</strong>
                                <span class="text-xs text-gray-400 ml-2"><?= date("d M Y H:i", strtotime($comment['CommentDate'])) ?></span>
                            </div>
                            <?php
                            $canDelete = false;
                            if (isset($_SESSION['userid'], $_SESSION['role'])) {
                                if ($_SESSION['userid'] == $comment['UserID']) {
                                    $canDelete = true; // Own comment
                                } elseif ($_SESSION['role'] === 'chef' && $_SESSION['userid'] == $recipe['UserID'] && $comment['Role'] === 'student') {
                                    $canDelete = true; // Chef deleting student's comment on their own recipe
                                }
                            }
                            ?>

                            <?php if ($canDelete): ?>
                                <form method="POST" class="inline-block float-right" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                    <input type="hidden" name="delete_comment_id" value="<?= $comment['CommentID'] ?>">
                                    <button type="submit" class="text-yellow-700 text-sm hover:underline">
                                        <i class="fas fa-trash"></i> Delete</button>
                                </form>
                            <?php endif; ?>

                            <p class="text-gray-800 mb-2"><?= nl2br(htmlspecialchars($comment['Text'])) ?></p>
                            <?php if (!empty($comment['VideoReviewPath'])): ?>
                                <video controls class="w-full max-w-md rounded">
                                    <source src="<?= htmlspecialchars($comment['VideoReviewPath']) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No comments yet. Be the first to comment!</p>
            <?php endif; ?>


            <form action="" method="POST" enctype="multipart/form-data" class="mb-6">
                <textarea name="comment_text" rows="3" required
                    class="w-full p-3 border rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-yellow-500"
                    placeholder="Write your comment..."></textarea>

                <label class="block mt-2 text-sm text-gray-700">Optional Video Review</label>
                <input type="file" name="video_review" accept="video/*"
                    class="block w-full mt-1 border p-2 rounded">

                <button type="submit"
                    class="mt-4 px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700">Post Comment</button>
            </form>

        </div>
    </div>
</body>
</html>