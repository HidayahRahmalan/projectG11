<?php
session_start();
include 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$maintenance_id = $_GET['id'] ?? null;

if (!$maintenance_id) {
    echo "Invalid request.";
    exit;
}

// Fetch maintenance record
$stmt = $conn->prepare("SELECT * FROM maintenance WHERE maintenance_id = ?");
$stmt->bind_param("i", $maintenance_id);
$stmt->execute();
$result = $stmt->get_result();
$maintenance = $result->fetch_assoc();

if (!$maintenance) {
    echo "Maintenance request not found.";
    exit;
}

// Fetch media record
$media_stmt = $conn->prepare("SELECT * FROM media WHERE maintenance_id = ?");
$media_stmt->bind_param("i", $maintenance_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
$media = $media_result->fetch_assoc();


// MIME Type Mapper
function getMimeType($extension) {
    $map = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav'
    ];
    return $map[strtolower($extension)] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title >View Maintenance</title>
    <style>
     @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@600&display=swap');

        :root {
            --color-bg: #ffffff;
            --color-text-body: #6b7280;
            --color-text-head: #111827;
            --color-shadow: rgba(0, 0, 0, 0.05);
            --color-button-bg: #111827;
            --color-button-bg-hover: #000000;
            --radius: 0.75rem;
            --transition: 0.3s ease;
            --max-width: 1200px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text-body);
            font-size: 18px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            position: sticky;
            top: 0;
            background: var(--color-bg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            box-shadow: 0 2px 6px var(--color-shadow);
            z-index: 10;
        }

        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--color-text-head);
            user-select: none;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        nav a {
            color: var(--color-text-body);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition);
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }

        nav a:hover,
        nav a:focus {
            color: var(--color-button-bg);
            outline: none;
            background-color: rgba(0, 0, 0, 0.05);
        }

        main {
        flex-grow: 1;
        max-width: var(--max-width);
        width: 100%;
        padding: 3rem 2rem 4rem;
        margin: 0 auto;
        }

        h1 {
        font-weight: 700;
        font-size: 3rem;
        color: var(--color-text-head);
        margin-bottom: 0.5rem;
        user-select: none;
        text-align: center;
        }

        p.subtitle {
        font-weight: 400;
        font-size: 1.125rem;
        max-width: 600px;
        margin-top: 0;
        margin-bottom: 3rem;
        }

        form.card {
        background-color: var(--color-bg);
        box-shadow: 0 4px 12px var(--color-shadow);
        border-radius: var(--radius);
        padding: 2.5rem 3rem;
        max-width: 700px;
        margin: 0 auto;
        display: grid;
        gap: 1.5rem;
        grid-template-columns: 1fr;
        }

        label {
        font-weight: 600;
        color: var(--color-text-head);
        display: block;
        margin-bottom: 0.4rem;
        user-select: none;
        }

        input[type="file"],
        input[type="text"],
        textarea,
        select {
        width: 100%;
        border: 1.5px solid #d1d5db;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 1rem;
        font-family: inherit;
        background-color: #fafafa;
        color: var(--color-text-head);
        transition: border-color var(--transition), box-shadow var(--transition);
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
        border-color: var(--color-button-bg);
        outline: none;
        box-shadow: 0 0 8px var(--color-button-bg);
        background-color: #ffffff;
        }

        textarea {
        min-height: 120px;
        resize: vertical;
        }

        /* Responsive layout for larger screens */
        @media (min-width: 768px) {
        form.card {
            grid-template-columns: 1fr 1fr;
            gap: 2rem 3rem;
        }
        label[for="description"],
        textarea,
        label[for="status"],
        select[name="status"] {
            grid-column: 1 / -1;
        }
        }

        button.submit-btn {
        grid-column: 1 / -1;
        background-color: var(--color-button-bg);
        color: #fff;
        border: none;
        padding: 0.8rem 1.75rem;
        font-weight: 700;
        font-size: 1.125rem;
        border-radius: var(--radius);
        cursor: pointer;
        user-select: none;
        transition: background-color var(--transition), transform 0.2s ease;
        justify-self: start;
        }

        button.submit-btn:hover,
        button.submit-btn:focus {
        background-color: var(--color-button-bg-hover);
        outline: none;
        transform: scale(1.05);
        }

        .cancel-btn {
            display: inline-block;
            background-color: #e5e7eb; /* Light gray background */
            color: #111827; /* Dark text */
            padding: 0.6rem 1.5rem;
            border-radius: var(--radius);
            font-weight: 600;
            text-decoration: none;
            transition: background-color var(--transition), transform 0.2s ease;
            margin-bottom: 1.5rem;
        }

        .cancel-btn:hover,
        .cancel-btn:focus {
            background-color: #d1d5db;
            transform: scale(1.05);
            outline: none;
        }

        /* Helper text style */
        small.helper-text {
        font-weight: 400;
        font-size: 0.9rem;
        color: var(--color-text-body);
        user-select: none;
        }
  </style>
</head>
<body>
<header>
    <div class="logo" tabindex="0">MaintainEase</div>
    <nav aria-label="Primary navigation">
        <a href="home.php" class="active" aria-current="page">Home</a>
        <?php if ($_SESSION['role'] === 'staff'): ?>
            <a href="insertmaintenance.php">Requests</a>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <a href="admin_dashboard.php">Users</a>
            <a href="viewquery.php">Maintenance List</a>
        <?php endif; ?>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Sign Out</a>
    </nav>
</header>

<main>
    <a href="viewquery.php" class="cancel-btn">Cancel</a>
    <h1 >View Maintenance Request</h1>

    <form class="card" method="POST" action="update.php">
    <input type="hidden" name="maintenance_id" value="<?= $maintenance_id ?>">
        <div>
            <label for="title">Title</label>
            <input type="text" id="title" value="<?= htmlspecialchars($maintenance['title']) ?>" readonly />
        </div>

        <div>
            <label for="category">Category</label>
            <input type="text" id="category" value="<?= htmlspecialchars($maintenance['category']) ?>" readonly />
        </div>

        <div>
            <label for="urgency">Urgency</label>
            <input type="text" id="urgency" value="<?= htmlspecialchars($maintenance['urgency']) ?>" readonly />
        </div>

        <div>
            <label for="location">Location</label>
            <input type="text" id="location" value="<?= htmlspecialchars($maintenance['location']) ?>" readonly />
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="description">Description</label>
            <textarea id="description" name="description" readonly><?= htmlspecialchars($maintenance['description']) ?></textarea>
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="Open" <?= $maintenance['status'] == 'Open' ? 'selected' : '' ?>>Open</option>
                <option value="In Progress" <?= $maintenance['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="Closed" <?= $maintenance['status'] == 'Closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>

        <!-- Comment Section -->
        <div style="grid-column: 1 / -1;">
            <label for="comment">Comment</label>
            <textarea id="comment" name="comment" placeholder="Enter your comment here..."></textarea>
        </div>

        <!-- Display Media -->
        <div style="grid-column: 1 / -1;">
            <label>Uploaded Media</label>
            <?php if ($media): ?>
                <?php
                $mimeType = $media['mediatype'];
                $fileData = !empty($media['filemedia']) ? base64_encode($media['filemedia']) : null;
                $filePath = !empty($media['filepath']) ? $media['filepath'] : null;
                ?>
                <?php if ($fileData && strpos($mimeType, 'image/') === 0): ?>
                    <img src="data:<?= $mimeType ?>;base64,<?= $fileData ?>" alt="Image" style="max-width: 100%; border-radius: 8px;">
                <?php elseif ($fileData && strpos($mimeType, 'audio/') === 0): ?>
                    <audio controls style="width: 100%; margin-top: 10px;">
                        <source src="data:<?= $mimeType ?>;base64,<?= $fileData ?>" type="<?= $mimeType ?>">
                        Your browser does not support the audio element.
                    </audio>
                <?php elseif ($filePath && strpos($mimeType, 'video/') === 0): ?>
                    <video controls style="max-width: 100%; border-radius: 8px;">
                        <source src="<?= htmlspecialchars($filePath) ?>" type="<?= htmlspecialchars($mimeType) ?>">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <p>No media available or unsupported type.</p>
                <?php endif; ?>
            <?php else: ?>
                <p>No media attached.</p>
            <?php endif; ?>
        </div>

        <!-- Display Recorded Audio -->
        <div style="grid-column: 1 / -1;">
            <label>Recorded Audio</label>
            <?php if ($media && !empty($media['audio'])): ?>
                <?php $audioData = base64_encode($media['audio']); ?>
                <audio controls style="width: 100%; margin-top: 10px;">
                    <source src="data:audio/wav;base64,<?= $audioData ?>" type="audio/wav">
                    Your browser does not support the audio element.
                </audio>
            <?php else: ?>
                <p>No audio recorded.</p>
            <?php endif; ?>
        </div>

        <div style="grid-column: 1 / -1;">
            <label>Current Transcript</label>
            <?php if (!empty($maintenance['transcript'])): ?>
                <textarea readonly style="width:100%; min-height:100px;"><?= htmlspecialchars($maintenance['transcript']) ?></textarea>
            <?php else: ?>
                <p>No transcript available.</p>
            <?php endif; ?>
        </div>

        <button type="submit" class="submit-btn">Update Maintenance</button>
    </form>
</main>

    <script>
        function startVoiceControl() {
            recognition.start();
        }
        document.addEventListener('DOMContentLoaded', function () {
            const video = document.querySelector('video');
            if (!video) return; // No video found

            // Check for browser support
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                console.log("Speech Recognition not supported in this browser.");
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.lang = 'en-US';
            recognition.continuous = true; // Keep listening
            recognition.interimResults = false;

            recognition.onresult = function (event) {
                const transcript = event.results[event.results.length - 1][0].transcript.trim().toLowerCase();
                console.log("Voice command:", transcript);

                if (transcript.includes("play")) {
                    video.play();
                } else if (transcript.includes("stop") || transcript.includes("pause")) {
                    video.pause();
                } else if (transcript.includes("replay") || transcript.includes("restart")) {
                    video.currentTime = 0;
                    video.play();
                }
            };

            recognition.onerror = function (event) {
                console.error("Speech recognition error:", event.error);
            };

            recognition.onend = function () {
                // Auto-restart after it ends
                recognition.start();
            };

            // Start listening
            recognition.start();
        });
    </script>

</body>
</html>
