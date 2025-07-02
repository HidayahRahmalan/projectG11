<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maintenance_id = $_POST['maintenance_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $urgency = $_POST['urgency'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $transcript = isset($_POST['transcript']) ? trim($_POST['transcript']) : null;
    $user_id = $_SESSION['user_id'];

    if (!$maintenance_id) {
        echo json_encode(['error' => 'Invalid maintenance ID']);
        exit;
    }

    // Update maintenance table
    if (!empty($transcript)) {
        $stmt = $conn->prepare("UPDATE maintenance SET title = ?, urgency = ?, location = ?, description = ?, transcript = ? WHERE maintenance_id = ?");
        $stmt->bind_param("sssssi", $title, $urgency, $location, $description, $transcript, $maintenance_id);
    } else {
        $stmt = $conn->prepare("UPDATE maintenance SET title = ?, urgency = ?, location = ?, description = ? WHERE maintenance_id = ?");
        $stmt->bind_param("ssssi", $title, $urgency, $location, $description, $maintenance_id);
    }

    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Failed to update maintenance: ' . $stmt->error]);
        exit;
    }

    if (isset($_FILES['mediaFile']) && $_FILES['mediaFile']['error'] === UPLOAD_ERR_OK) {
        $mediaType = $_FILES['mediaFile']['type'];
        $isVideo = strpos($mediaType, 'video/') === 0;
    
        // Check if media already exists for this maintenance_id
        $checkStmt = $conn->prepare("SELECT media_id FROM media WHERE maintenance_id = ?");
        $checkStmt->bind_param("i", $maintenance_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $mediaExists = $checkResult->num_rows > 0;
        $checkStmt->close();
    
        if ($isVideo) {
            // Save video to uploads/ and store filepath
            $uploadDir = __DIR__ . '/uploads/';
            $originalName = basename($_FILES['mediaFile']['name']);
            $timestampedName = time() . '_' . preg_replace('/\s+/', '_', $originalName);
            $targetFile = $uploadDir . $timestampedName;
    
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
    
            if (move_uploaded_file($_FILES['mediaFile']['tmp_name'], $targetFile)) {
                $relativePath = 'uploads/' . $timestampedName;
    
                if ($mediaExists) {
                    // Update existing media
                    $stmt = $conn->prepare("UPDATE media SET mediatype = ?, filepath = ?, filemedia = NULL WHERE maintenance_id = ?");
                    $stmt->bind_param("ssi", $mediaType, $relativePath, $maintenance_id);
                } else {
                    // Insert new media
                    $stmt = $conn->prepare("INSERT INTO media (maintenance_id, mediatype, filepath, filemedia) VALUES (?, ?, ?, NULL)");
                    $stmt->bind_param("iss", $maintenance_id, $mediaType, $relativePath);
                }
    
                $stmt->execute();
                $stmt->close();
            } else {
                echo json_encode(['error' => 'Failed to move uploaded video.']);
                exit;
            }
    
        } else {
            // Handle image/audio: store in filemedia BLOB
            $mediaData = file_get_contents($_FILES['mediaFile']['tmp_name']);
    
            if ($mediaExists) {
                // Update existing media
                $stmt = $conn->prepare("UPDATE media SET mediatype = ?, filemedia = ?, filepath = NULL WHERE maintenance_id = ?");
                $null = null;
                $stmt->bind_param("sbi", $mediaType, $null, $maintenance_id);
                $stmt->send_long_data(1, $mediaData);
            } else {
                // Insert new media
                $stmt = $conn->prepare("INSERT INTO media (maintenance_id, mediatype, filemedia, filepath) VALUES (?, ?, ?, NULL)");
                $stmt->bind_param("isb", $maintenance_id, $mediaType, $null);
                $stmt->send_long_data(2, $mediaData);
            }
    
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // ==== Handle recorded audio ====
    if (isset($_FILES['recordedAudio']) && $_FILES['recordedAudio']['error'] === UPLOAD_ERR_OK) {
        $audioContent = file_get_contents($_FILES['recordedAudio']['tmp_name']);
        $audio_stmt = $conn->prepare("UPDATE media SET audio = ? WHERE maintenance_id = ?");
        $null = null;
        $audio_stmt->bind_param("bi", $null, $maintenance_id);
        $audio_stmt->send_long_data(0, $audioContent);
        if (!$audio_stmt->execute()) {
            echo json_encode(['error' => 'Failed to update audio: ' . $audio_stmt->error]);
            exit;
        }
    }

    // ==== Audit log ====
    $audit_stmt = $conn->prepare("INSERT INTO audit_log (user_id, actiontype, actiontime) VALUES (?, 'Updated maintenance request', NOW())");
    $audit_stmt->bind_param("i", $user_id);
    $audit_stmt->execute();

    echo json_encode(['message' => 'Maintenance request updated successfully.']);
    exit;
} else {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}
