<?php

include 'connection.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Video file not uploaded or upload error occurred.');
    }

    $videoFile = $_FILES['video_file'];

    // Allowed file extensions
    $allowedExtensions = ['mp4', 'avi', 'mov', 'mkv', 'webm'];
    $videoOriginalName = basename($videoFile['name']);
    $videoExtension = strtolower(pathinfo($videoOriginalName, PATHINFO_EXTENSION));

    if (!in_array($videoExtension, $allowedExtensions)) {
        throw new Exception('Unsupported video file type. Allowed types: ' . implode(', ', $allowedExtensions));
    }

    // Generate title/description from file name
    $videoTitle = pathinfo($videoOriginalName, PATHINFO_FILENAME);
    $videoDescription = 'Auto description: ' . $videoTitle;

    $uploadDir = __DIR__ . '/../uploads/videos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // Unique filename
    $videoTmpPath = $videoFile['tmp_name'];
    $videoFileName = uniqid('vid_', true) . '.' . $videoExtension;
    $videoDestination = $uploadDir . $videoFileName;

    if (!move_uploaded_file($videoTmpPath, $videoDestination)) {
        throw new Exception('Failed to move uploaded video file.');
    }

    // Run ffprobe
    $ffprobePath = 'C:\\ffmpeg\\ffmpeg-2025-06-08-git-5fea5e3e11-full_build\\bin\\ffprobe.exe';
    if (!file_exists($ffprobePath)) {
        throw new Exception("ffprobe not found at: $ffprobePath");
    }

    $escapedPath = escapeshellarg($videoDestination);
    $cmd = "\"$ffprobePath\" -v quiet -print_format json -show_format -show_streams $escapedPath";
    $ffprobeOutput = shell_exec($cmd);

    if (!$ffprobeOutput) {
        throw new Exception('Failed to extract video metadata. Make sure ffprobe is accessible.');
    }

    $metadata = json_decode($ffprobeOutput, true);
    if (!$metadata || !isset($metadata['format'])) {
        throw new Exception('Invalid metadata returned from ffprobe.');
    }

    $duration = floatval($metadata['format']['duration'] ?? 0);
    $bitrate = intval($metadata['format']['bit_rate'] ?? 0);
    $width = 0;
    $height = 0;
    $codec = '';

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

    // Fetch the latest module ID
    $stmt = $conn->query("SELECT ModuleID FROM Module ORDER BY ModuleID DESC LIMIT 1");
    $latestModule = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$latestModule) {
        throw new Exception("No module found to link this video.");
    }
    $moduleID = $latestModule['ModuleID'];

    // Save video to database
    $videoStmt = $conn->prepare("
        INSERT INTO Video (
            ModuleID, VideoTitle, VideoDescription, FilePath, 
            Duration, Width, Height, Codec, Bitrate, UploadDate
        ) VALUES (
            :moduleID, :videoTitle, :videoDescription, :filePath, 
            :duration, :width, :height, :codec, :bitrate, NOW()
        )
    ");

    $videoStmt->bindParam(':moduleID', $moduleID);
    $videoStmt->bindParam(':videoTitle', $videoTitle);
    $videoStmt->bindParam(':videoDescription', $videoDescription);
    $videoStmt->bindParam(':filePath', $videoFileName);
    $videoStmt->bindParam(':duration', $duration);
    $videoStmt->bindParam(':width', $width);
    $videoStmt->bindParam(':height', $height);
    $videoStmt->bindParam(':codec', $codec);
    $videoStmt->bindParam(':bitrate', $bitrate);

    $videoStmt->execute();

    echo "✅ Video uploaded successfully and linked to Module ID: $moduleID";

} catch (Exception $e) {
    http_response_code(400);
    echo "❌ Error: " . $e->getMessage();
}
