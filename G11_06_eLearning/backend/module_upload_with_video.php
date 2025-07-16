<?php

// --- CHANGE 1: ADD SECURITY AND START THE SESSION ---
require_once 'auth_instructor.php';

// --- CHANGE 2: GET THE INSTRUCTOR'S UserID FROM THE SESSION ---
$loggedInUserId = $_SESSION['user_id'];


// Your original working code starts here
include 'connection.php'; 
header('Content-Type: application/json');
$ffprobePath = 'C:\\ffmpeg\\ffmpeg-7.1.1-full_build\\bin\\ffprobe.exe'; 

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }
    if (empty($_POST['topic']) || empty($_POST['topicdescription'])) {
        throw new Exception('Topic and topic description are required.');
    }
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $topic = $_POST['topic'];
    $topicdescription = $_POST['topicdescription'];
    $action = $_POST['action'] ?? 'publish';
    $conn->beginTransaction();

    // Insert Topic
    $stmt = $conn->prepare("INSERT INTO Topic (TopicName, TopicDescription) VALUES (:topic, :topicdescription)");
    $stmt->bindParam(':topic', $topic);
    $stmt->bindParam(':topicdescription', $topicdescription);
    $stmt->execute();
    $topicID = $conn->lastInsertId();

    // --- CHANGE 3: UPDATE THE MODULE INSERT QUERY WITH 'UserID' ---
    $isPublished = ($action === 'draft') ? 'no' : 'yes';
    $moduleStmt = $conn->prepare("INSERT INTO Module (Title, Description, TopicID, isPublished, UserID) VALUES (:title, :description, :topicID, :isPublished, :userID)");
    $moduleStmt->bindParam(':title', $title);
    $moduleStmt->bindParam(':description', $description);
    $moduleStmt->bindParam(':topicID', $topicID);
    $moduleStmt->bindParam(':isPublished', $isPublished);
    // Bind the UserID we got from the session
    $moduleStmt->bindParam(':userID', $loggedInUserId); 
    $moduleStmt->execute();
    $moduleID = $conn->lastInsertId();

    $messageDetails = [];

    // --- Video processing logic remains the same (no changes needed below) ---
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $videoFile = $_FILES['video_file'];
        $allowedExtensions = ['mp4', 'avi', 'mov', 'mkv', 'webm'];
        $videoOriginalName = basename($videoFile['name']);
        $videoExtension = strtolower(pathinfo($videoOriginalName, PATHINFO_EXTENSION));

        if (!in_array($videoExtension, $allowedExtensions)) {
            throw new Exception('Unsupported video file type: ' . $videoExtension);
        }

        $videoTitle = pathinfo($videoOriginalName, PATHINFO_FILENAME);
        $uploadDir = __DIR__ . '/../uploads/videos/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }

        $videoFileName = uniqid('vid_', true) . '.' . $videoExtension;
        $videoDestination = $uploadDir . $videoFileName;

        if (!move_uploaded_file($videoFile['tmp_name'], $videoDestination)) {
            throw new Exception('Failed to move uploaded video file.');
        }

        if (!file_exists($ffprobePath)) {
            throw new Exception("ffprobe not found at specified path.");
        }
        
        $escapedPath = escapeshellarg($videoDestination);
        $cmd = "\"$ffprobePath\" -v quiet -print_format json -show_format -show_streams $escapedPath";
        $ffprobeOutput = shell_exec($cmd);

        if (!$ffprobeOutput) { throw new Exception('Failed to execute ffprobe.'); }
        $metadata = json_decode($ffprobeOutput, true);
        if (!$metadata || !isset($metadata['format'])) { throw new Exception('Invalid metadata from ffprobe.'); }

        $duration = floatval($metadata['format']['duration'] ?? 0);
        $width = 0; $height = 0; $codec = '';
        if (isset($metadata['streams'])) {
            foreach ($metadata['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    $width = $stream['width'] ?? 0;
                    $height = $stream['height'] ?? 0;
                    $codec = $stream['codec_name'] ?? '';
                    break;
                }
            }
        }
        
        $videoStmt = $conn->prepare("INSERT INTO Video (ModuleID, VideoTitle, FilePath, Duration, Width, Height, Codec, UploadDate) VALUES (:moduleID, :videoTitle, :filePath, :duration, :width, :height, :codec, NOW())");
        $videoStmt->bindParam(':moduleID', $moduleID);
        $videoStmt->bindParam(':videoTitle', $videoTitle);
        $videoStmt->bindParam(':filePath', $videoFileName);
        $videoStmt->bindParam(':duration', $duration);
        $videoStmt->bindParam(':width', $width);
        $videoStmt->bindParam(':height', $height);
        $videoStmt->bindParam(':codec', $codec);
        $videoStmt->execute();
        $messageDetails[] = 'Video processed.';
    }

    // --- Slides upload logic remains the same ---
    if (isset($_FILES['slide_file']) && is_array($_FILES['slide_file']['name'])) {
        $slideUploadDir = __DIR__ . '/../uploads/slides/';
        if (!is_dir($slideUploadDir)) { mkdir($slideUploadDir, 0775, true); }

        foreach ($_FILES['slide_file']['name'] as $key => $name) {
            if ($_FILES['slide_file']['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($name);
                $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $fileSize = $_FILES['slide_file']['size'][$key];
                $allowedExt = ['ppt', 'pptx'];

                if (in_array($fileExt, $allowedExt)) {
                    $fileName = uniqid('slide_', true) . '.' . $fileExt;
                    $destination = $slideUploadDir . $fileName;

                    if (move_uploaded_file($_FILES['slide_file']['tmp_name'][$key], $destination)) {
                        $slideStmt = $conn->prepare("INSERT INTO slide (ModuleID, SlideTitle, SlideDescription, FilePath, UploadDate, FileSize, Format) VALUES (:moduleID, :title, :description, :path, NOW(), :fileSize, :format)");
                        $slideDescription = '';
                        $slideStmt->bindParam(':moduleID', $moduleID);
                        $slideStmt->bindParam(':title', $originalName);
                        $slideStmt->bindParam(':description', $slideDescription);
                        $slideStmt->bindParam(':path', $fileName);
                        $slideStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
                        $slideStmt->bindParam(':format', $fileExt);
                        $slideStmt->execute();
                    }
                }
            }
        }
        $messageDetails[] = 'Slides processed.';
    }

    // --- Notes upload logic remains the same ---
    if (isset($_FILES['note_file']) && is_array($_FILES['note_file']['name'])) {
        $notesUploadDir = __DIR__ . '/../uploads/notes/';
        if (!is_dir($notesUploadDir)) { mkdir($notesUploadDir, 0775, true); }

        foreach ($_FILES['note_file']['name'] as $key => $name) {
            if ($_FILES['note_file']['error'][$key] === UPLOAD_ERR_OK) {
                $originalName = basename($name);
                $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $fileSize = $_FILES['note_file']['size'][$key];

                if ($fileExt === 'pdf') {
                    $fileName = uniqid('note_', true) . '.pdf';
                    $destination = $notesUploadDir . $fileName;

                    if (move_uploaded_file($_FILES['note_file']['tmp_name'][$key], $destination)) {
                        $noteStmt = $conn->prepare("INSERT INTO notes (ModuleID, NoteTitle, NoteDescription, FilePath, UploadDate, FileSize) VALUES (:moduleID, :title, :description, :path, NOW(), :fileSize)");
                        $noteDescription = '';
                        $noteStmt->bindParam(':moduleID', $moduleID);
                        $noteStmt->bindParam(':title', $originalName);
                        $noteStmt->bindParam(':description', $noteDescription);
                        $noteStmt->bindParam(':path', $fileName);
                        $noteStmt->bindParam(':fileSize', $fileSize, PDO::PARAM_INT);
                        $noteStmt->execute();
                    }
                }
            }
        }
        $messageDetails[] = 'Notes processed.';
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'moduleID' => $moduleID,
        'message' => 'Module uploaded successfully. ' . implode(' ', $messageDetails)
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>