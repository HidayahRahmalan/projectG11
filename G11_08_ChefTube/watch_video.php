<?php
// Include the database connection file
include('dbconnect.php');

// Fetch video details from the database
$video_id = 1; // You can make this dynamic with a GET parameter (e.g., id=1)
$sql = "SELECT * FROM VIDEO WHERE vid_id = $video_id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $video = $result->fetch_assoc();
} else {
    die("Video not found.");
}

// Fetch comments for this video
$sql_comments = "SELECT * FROM COMMENT WHERE vid_id = $video_id";
$comments_result = $conn->query($sql_comments);

// Fetch total likes for the video
$total_likes = $video['like']; // Assuming 'like' column in the 'VIDEO' table holds the total likes
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Watch Video</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Global Styles */
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Header */
        .header {
            background-color: #ff0000;
            color: white;
            padding: 10px 20px;
            width: 100%;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Video Container */
        .video-container {
            width: 320px;
            height: 240px;
            background-color: black;
            margin-bottom: 20px;
            position: relative;
        }

        /* Video Player */
        video {
            width: 100%;
            height: 100%;
            object-fit: contain; /* Prevent distortion of the video */
        }

        /* Video Description */
        .video-container h2 {
            font-size: 24px;
            margin: 10px 0;
        }

        .video-description {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
            text-align: center;
        }

        /* Comments Section */
        .comments-section {
            width: 100%;
            background-color: #ffffff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }

        .comments-section h3 {
            font-size: 20px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .comment {
            padding: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .comment p {
            font-size: 14px;
            color: #555;
        }

        .comment .user {
            font-weight: bold;
        }

        /* Button Styles */
        .like-button, .dislike-button {
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }

        /* Likes Section */
        .likes-section {
            margin-top: 10px;
        }

        .likes-count {
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    CheftTube
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Video Container -->
    <div class="video-container">
        <!-- Video Player -->
        <video id="video-player" controls>
            <source src="http://localhost/chefttube/<?php echo $video['video']; ?>" type="video/mp4">
            Your browser does not support the video tag.
        </video>
    </div>

    <!-- Video Title -->
    <h2><?php echo $video['title']; ?></h2>

    <!-- Video Description -->
    <p class="video-description"><?php echo $video['description']; ?></p>

    <!-- Like / Dislike Buttons -->
    <div class="buttons">
        <button class="like-button">Like</button>
        <button class="dislike-button">Dislike</button>
    </div>

    <!-- Likes Count -->
    <div class="likes-section">
        <span class="likes-count"><?php echo $total_likes; ?> Likes</span>
    </div>
</div>

<!-- Comments Section -->
<div class="comments-section">
    <h3>Comments</h3>
    <?php
    if ($comments_result->num_rows > 0) {
        while ($comment = $comments_result->fetch_assoc()) {
            echo "<div class='comment'>";
            echo "<div class='user'>User " . $comment['user_id'] . ":</div>";
            echo "<p>" . $comment['description'] . "</p>";
            echo "</div>";
        }
    } else {
        echo "<p>No comments yet.</p>";
    }
    ?>
</div>

<script>
    // JavaScript to handle video thumbnail click and play the video
    document.getElementById('video-thumbnail').addEventListener('click', function() {
        // Hide the thumbnail
        document.getElementById('video-thumbnail').style.display = 'none';
        // Show the video player
        var videoPlayer = document.getElementById('video-player');
        videoPlayer.style.display = 'block';
        videoPlayer.play(); // Play the video
    });
</script>

</body>
</html>

<?php
// Close the database connection after all queries
$conn->close();
?>
