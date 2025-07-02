<?php 
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

// Ensure the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Read form data
$user_id = intval($_POST['user_id']);
$title = $_POST['title'];
$description = $_POST['description'];
$category = $_POST['category'];
$urgency = $_POST['urgency'];
$location = $_POST['location'];
$status = $_POST['status'];
$transcription = $_POST['transcription'] ?? null;

// Insert into maintenance table
$stmt = $conn->prepare("INSERT INTO maintenance (user_id, title, description, category, urgency, location, status, transcript) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssssss", $user_id, $title, $description, $category, $urgency, $location, $status, $transcription);

if ($stmt->execute()) {
    $maintenance_id = $stmt->insert_id;

    // Handle media upload
    if (isset($_FILES['mediaFile']) && $_FILES['mediaFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['mediaFile']['tmp_name'];
        $fileType = $_FILES['mediaFile']['type'];
        $isVideo = strpos($fileType, 'video/') === 0;

        if ($isVideo) {
            // Save video to /uploads folder
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = basename($_FILES['mediaFile']['name']);
            $timestampedName = time() . '_' . preg_replace('/\s+/', '_', $originalName);
            $targetFile = $uploadDir . $timestampedName;

            if (move_uploaded_file($fileTmpPath, $targetFile)) {
                $relativePath = 'uploads/' . $timestampedName;

                $media_stmt = $conn->prepare("INSERT INTO media (maintenance_id, mediatype, filepath, filemedia) VALUES (?, ?, ?, NULL)");
                $media_stmt->bind_param("iss", $maintenance_id, $fileType, $relativePath);
            } else {
                echo json_encode(['error' => 'Failed to save uploaded video.']);
                exit;
            }
        } else {
            // Handle image or audio file (store as BLOB)
            $fileContent = file_get_contents($fileTmpPath);
            $media_stmt = $conn->prepare("INSERT INTO media (maintenance_id, mediatype, filemedia, filepath) VALUES (?, ?, ?, NULL)");
            $null = null;
            $media_stmt->bind_param("isb", $maintenance_id, $fileType, $null);
            $media_stmt->send_long_data(2, $fileContent);
        }

        if (!$media_stmt->execute()) {
            echo json_encode(['error' => 'Failed to insert media file: ' . $media_stmt->error]);
            exit;
        }
    }

    // Handle recorded audio
    if (isset($_FILES['recordedAudio']) && $_FILES['recordedAudio']['error'] === UPLOAD_ERR_OK) {
        $audioTmpPath = $_FILES['recordedAudio']['tmp_name'];
        $audioContent = file_get_contents($audioTmpPath);

        $audio_stmt = $conn->prepare("UPDATE media SET audio = ? WHERE maintenance_id = ?");
        $null = null;
        $audio_stmt->bind_param("bi", $null, $maintenance_id);
        $audio_stmt->send_long_data(0, $audioContent);

        if (!$audio_stmt->execute()) {
            echo json_encode(['error' => 'Failed to insert audio: ' . $audio_stmt->error]);
            exit;
        }
    }

    // Insert audit log
    $audit_stmt = $conn->prepare("INSERT INTO audit_log (user_id, actiontype, actiontime) VALUES (?, 'Submitted report', NOW())");
    $audit_stmt->bind_param("i", $user_id);
    $audit_stmt->execute();

    echo json_encode(['message' => 'Maintenance request submitted successfully.']);
} else {
    echo json_encode(['error' => 'Failed to insert maintenance request: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
