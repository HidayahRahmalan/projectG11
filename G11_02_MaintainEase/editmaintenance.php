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

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'staff'): ?>
                    <a href="insertmaintenance.php">Submit Request</a>
                    <a href="request_list.php">My Requests</a>
                <?php elseif ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Users</a>
                    <a href="viewquery.php">Maintenance List</a>
                <?php endif; ?>

                <a href="profile.php">Profile</a>
                <a href="logout.php">Sign Out</a>
            <?php else: ?>
                <a href="login.php">Sign In</a>
            <?php endif; ?>
        </nav>
    </header>

<main>
    <a href="request_list.php" class="cancel-btn">Cancel</a>
    <h1>Edit Maintenance Request</h1>

    <form class="card" method="POST" action="updatemt.php" enctype="multipart/form-data">
        <input type="hidden" name="maintenance_id" value="<?= $maintenance_id ?>">

        <div>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($maintenance['title']) ?>" required>
        </div>

        <div>
            <label for="category">Category (Cannot be changed)</label>
            <input type="text" id="category" name="category_display" value="<?= htmlspecialchars($maintenance['category']) ?>" readonly>
            <input type="hidden" name="category" value="<?= htmlspecialchars($maintenance['category']) ?>">
        </div>

        <div>
            <label for="urgency">Urgency</label>
            <select id="urgency" name="urgency" required>
                <option value="Low" <?= $maintenance['urgency'] == 'Low' ? 'selected' : '' ?>>Low</option>
                <option value="Medium" <?= $maintenance['urgency'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                <option value="High" <?= $maintenance['urgency'] == 'High' ? 'selected' : '' ?>>High</option>
                <option value="Critical" <?= $maintenance['urgency'] == 'Critical' ? 'selected' : '' ?>>Critical</option>
            </select>
        </div>

        <div>
            <label for="location">Location</label>
            <input type="text" id="location" name="location" value="<?= htmlspecialchars($maintenance['location']) ?>" required>
        </div>

        <div style="grid-column: 1 / -1;">
            <label for="description">Description</label>
            <textarea id="description" name="description" required><?= htmlspecialchars($maintenance['description']) ?></textarea>
        </div>

        <div style="grid-column: 1 / -1;">
            <label>Current Media File</label>
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

        <div style="grid-column: 1 / -1;">
            <label for="mediaFile">Upload New Media File (Optional)</label>
            <input type="file" id="mediaFile" name="mediaFile" accept="image/*,video/*,audio/*">
            <small class="helper-text">Leave blank if no change</small>
        </div>

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

        <div style="grid-column: 1 / -1;">
            <label>Record New Audio (Optional)</label>
            <button type="button" id="startRecordingBtn">Start Recording</button>
            <button type="button" id="stopRecordingBtn" disabled>Stop Recording</button>
            <audio id="audioPreview" controls style="display: none; margin-top: 10px;"></audio>
        </div>
        
        <div style="grid-column: 1 / -1;">
            <label for="transcription">New Transcript (Optional)</label>
            <textarea id="transcription" name="transcript" rows="4" placeholder="Speech transcription will appear here..."></textarea>
        </div>



        <button type="submit" class="submit-btn">Update Maintenance</button>
    </form>
</main>

    <script>
        let mediaRecorder;
        let audioChunks = [];
        let recordedAudioBlob = null;
        let recognition;
        let transcriptionBox = document.getElementById('transcription');

        // Initialize speech recognition
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SpeechRecognition();
            recognition.continuous = true;
            recognition.interimResults = true;

            recognition.onresult = (event) => {
                let transcript = '';
                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    transcript += event.results[i][0].transcript + ' ';
                }
                transcriptionBox.value = transcript.trim();
            };

            recognition.onerror = (event) => {
                console.error('Speech recognition error', event);
            };
        } else {
            alert('Speech recognition is not supported in this browser.');
        }

        document.getElementById('startRecordingBtn').addEventListener('click', async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = event => audioChunks.push(event.data);

                mediaRecorder.onstop = () => {
                    recordedAudioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                    const audioUrl = URL.createObjectURL(recordedAudioBlob);
                    const audioPreview = document.getElementById('audioPreview');
                    audioPreview.src = audioUrl;
                    audioPreview.style.display = 'block';
                };

                mediaRecorder.start();
                recognition && recognition.start();

                document.getElementById('startRecordingBtn').disabled = true;
                document.getElementById('stopRecordingBtn').disabled = false;
            } catch (err) {
                alert('Microphone access denied or not supported.');
            }
        });

        document.getElementById('stopRecordingBtn').addEventListener('click', () => {
            mediaRecorder.stop();
            recognition && recognition.stop();
            document.getElementById('startRecordingBtn').disabled = false;
            document.getElementById('stopRecordingBtn').disabled = true;
        });

        document.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            if (recordedAudioBlob) {
                formData.append('recordedAudio', recordedAudioBlob, 'recording.wav');
            }

            try {
                const response = await fetch('updatemt.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok) {
                    alert(result.message);
                    window.location.href = 'request_list.php';
                } else {
                    alert(result.error || 'Submission failed.');
                }
            } catch (error) {
                alert('Network error occurred.');
            }
        });
    </script>


</body>
</html>
